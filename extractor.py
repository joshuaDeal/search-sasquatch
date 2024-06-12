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
		return 0

# Check text for naughty terms.
def checkForNaughty(text, file):
	# Load naughty terms.
	naughtyTerms = []
	with open(file, 'r') as file:
		naughtyTerms = [line.strip().lower() for line in file]

	# Check for naughty terms in text
	text = text.lower()
	for term in naughtyTerms:
		if term in text.split():
			print("Flagging as NSFW...")
			return True

	return False

# Get the page title.
def parseTitle(htmlSoup):
	titleTag = htmlSoup.title
	if titleTag:
		#metaData['title'] = titleTag.string
		return titleTag.string
	else:
		return "Untitled Page"

# Get meta description.
def parseDescription(htmlSoup):
	description = htmlSoup.find('meta', attrs={'name': 'description'})
	if description:
		return description.get('content', '')
	else:
		return "No description provided."

# Get meta keywords.
def parseKeywords(htmlSoup):
	keyWords = htmlSoup.find('meta', attrs={'name': 'keywords'})
	if keyWords:
		return keyWords.get('content', '')
	else:
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

		# Check for naughty terms.
		text = ''.join(str(metaData.values()))
		if checkForNaughty(text, "./naughty-words.txt"):
			safe = 0
		else:
			safe = 1

		return metaData, safe
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

def updateDataBase(dataDict, safe, creds):
	connection = None

	try:
		# Create a connection to the MariaDB database
		connection = mysql.connector.connect(host='localhost', unix_socket='/var/run/mysqld/mysqld.sock', database='sasquatch_index', user=creds['username'], password=creds['password'])

		cursor = connection.cursor()

		# Update the database with the data from dataDict
		query = "INSERT INTO sites (url, title, description, keywords, headers, paragraphs, lists, first_visited, last_visited, safe) VALUES (%s, %s, %s, %s, %s, %s, %s, IFNULL(first_visited, CURRENT_TIMESTAMP), IFNULL(last_visited, CURRENT_TIMESTAMP), %s) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), keywords = VALUES(keywords), headers = VALUES(headers), paragraphs = VALUES(paragraphs), lists = VALUES(lists), last_visited = CURRENT_TIMESTAMP, safe = VALUES(safe)"
		cursor.execute(query, (str(dataDict['url']), str(dataDict['title']), str(dataDict['description']), str(dataDict['keywords']), str(dataDict['headers']), str(dataDict['paragraphs']), str(dataDict['lists']), safe))
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
			meta, safe = meta
			updateDataBase(meta, safe, getMySqlCreds('db_creds.gpg',arguments['keyFile']))
		i = i + 1

if __name__ == "__main__":
	main()
