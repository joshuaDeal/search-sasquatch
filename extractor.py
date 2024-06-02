#!/usr/bin/env python3

# A meta data extractor for html pages. Designed to extract meta data from each entry in a text file that contains a list of urls seperated by newlines.
# TODO: Make sure that the handling of sql is safe.

import requests
import sys
import subprocess
import mysql.connector
from datetime import datetime
from bs4 import BeautifulSoup
from crawler import getHtml

# Print help message
def printHelp():
	print (sys.argv[0])
	print("Usage: " + sys.argv[0], "--option")
	print("\t--help\t\t\tDisplay this help message.")
	print("\t--key <file path>\tSpecify SQL server decryption key path.")

# Evaluate command line arguments.
def evalArguments():
	output = {}

	for i in range(len(sys.argv)):
		# Print help message.
		if sys.argv[i] == "--help" or sys.argv[i] == "-h":
			printHelp()
			sys.exit
		# Let user specify key file for database login.
		elif sys.argv[i] == "--key" or sys.argv[i] == "-k":
			output['keyFile'] = sys.argv[i+1]

	return output

# Get a url from the url file.
def getUrl(fileName, lineNumber):
	try:
		with open(fileName, 'r') as file:
			lines = file.readlines()
			# Check if the line number is within the range of lines in the file
			if 0 <= lineNumber < len(lines):
				# Return the line content without newline character
				return lines[lineNumber].strip()
			else:
				print(f"Line number {lineNumber} is out of range in file {fileName}")
				return 0
	except FileNotFoundError:
		print(f"File '{fileName}' not found")
		return 0

# Get the page title.
def parseTitle(htmlSoup):
	titleTag = htmlSoup.title
	if titleTag:
		#metaData['title'] = titleTag.string
		return titleTag.string
	else:
		print("Title tag not found.")
		return "Untitled Page"

# Get meta description.
def parseDescription(htmlSoup):
	description = htmlSoup.find('meta', attrs={'name': 'description'})
	if description:
		return description['content']
	else:
		print("Meta description not found.")
		return "No description provided."

# Get meta keywords.
def parseKeywords(htmlSoup):
	keyWords = htmlSoup.find('meta', attrs={'name': 'keywords'})
	if keyWords:
		return keyWords['content']
	else:
		print("Meta keywords not found.")
		return ""

# Get headers.
def parseHeaders(htmlSoup):
	headingText = ''
	headings = htmlSoup.find_all(['h1','h2','h3','h4','h4','h6'])

	for heading in headings:
		text = heading.get_text()
		headingText += text + ' '

	return headingText.strip()

# Get paragraphs.
def parseParagraphs(htmlSoup):
	pText = ''
	paras = htmlSoup.find_all(['p'])

	for para in paras:
		text = para.get_text()
		pText += text + ' '

	return pText.strip()

# Get lists
def parseLists(htmlSoup):
	listsText = ''
	lists = htmlSoup.find_all(['li','dt','dd'])

	for item in lists:
		text = item.get_text()
		listsText += text + ' '

	return listsText.strip()

# Gets relevant metadata from a given url. Returns a dict.
def getMeta(url, dataDict=None):
	metaData = {'url': url}

	if dataDict is None or 'first_visited' not in dataDict:
		metaData['first_visited'] = datetime.now()


	# Get html content.
	htmlContent = getHtml(url)

	if htmlContent != None:
		# Parse html content with BeautifulSoup.
		soup = BeautifulSoup(htmlContent, 'html.parser')

		# Get the page title.
		title = parseTitle(soup)
		if len(title) > 70:
			title = title[:70]
		metaData['title'] = title

		# Get meta description.
		description = parseDescription(soup)
		if len(description) > 150:
			description = description[:150]
		metaData['description'] = description

		# Get meta keywords.
		keywords = parseKeywords(soup)
		if len(keywords) > 50:
			keywords = keywords[:50]
		metaData['keywords'] = keywords

		# Get headers
		headers = parseHeaders(soup)
		if len(headers) > 300:
			headers = headers[:300]
		metaData['headers'] = headers

		# Get paragraphs
		paragraphs = parseParagraphs(soup)
		if len(paragraphs) > 500:
			paragraphs = paragraphs[:500]
		metaData['paragraphs'] = paragraphs

		# Get lists
		lists = parseLists(soup)
		if len(lists) > 300:
			lists = lists[:300]
		metaData['lists'] = lists

		return metaData
	return None

# Read MySQL username and password from a file
def getMySqlCreds(fileName,keyFile):
	credentials = {}

	# Construct gpg command for decryption
	gpgCommand = ['gpg','--decrypt','--batch','--passphrase-file',keyFile,fileName]

	# Try and decrypt the creds file
	try:
		decryptedData = subprocess.check_output(gpgCommand, stderr=subprocess.DEVNULL).decode('utf-8').strip()
		username, password = decryptedData.split(':')
		credentials['username'] = username
		credentials['password'] = password
		return credentials
	except subprocess.CalledProcessError as e:
		print(f"Error decrypting file: {e}")
		return None

def updateDataBase(dataDict,creds):
	connection = None

	try:
		# Create a connection to the MariaDB database
		connection = mysql.connector.connect(host='localhost', unix_socket='/var/run/mysqld/mysqld.sock', database='sasquatch_index', user=creds['username'], password=creds['password'])

		cursor = connection.cursor()

		# Update the database with the data from dataDict
		query = "INSERT INTO sites (url, title, description, keywords, headers, paragraphs, lists, first_visited, last_visited) VALUES (%s, %s, %s, %s, %s, %s, %s, IFNULL(first_visited, CURRENT_TIMESTAMP), IFNULL(last_visited, CURRENT_TIMESTAMP)) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), keywords = VALUES(keywords), headers = VALUES(headers), paragraphs = VALUES(paragraphs), lists = VALUES(lists), last_visited = CURRENT_TIMESTAMP"
		cursor.execute(query, (str(dataDict['url']), str(dataDict['title']), str(dataDict['description']), str(dataDict['keywords']), str(dataDict['headers']), str(dataDict['paragraphs']), str(dataDict['lists'])))
		# Commit the changes to the database
		connection.commit()

	except mysql.connector.Error as error:
		print("Error updating the database for url '{}': {}".format(dataDict['url'],error))

	finally:
		if connection is not None and connection.is_connected():
			cursor.close()
			connection.close()

def main():
	urlsFile = "raw-urls.txt"
	arguments = evalArguments()

	i = 0
	while True:
		url = getUrl(urlsFile,i)
		if url == 0:
			break
		print("Parsing " + url + "...")
		meta = getMeta(url)
		if meta != None:
			updateDataBase(meta,getMySqlCreds('db_creds.gpg',arguments['keyFile']))
		i = i + 1

if __name__ == "__main__":
	main()
