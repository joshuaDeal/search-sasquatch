#!/usr/bin/env python3

# A meta data extractor for html pages. Designed to extract meta data from each entry in a text file that contains a list of urls seperated by newlines.

import requests
import csv
import shutil
import os
from bs4 import BeautifulSoup
from crawler import getHtml

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

# TODO: Refactor this into several new functions. Each for getting a specific type of metadata.
# Gets relevant metadata from a given url. Returns a dict.
def getMeta(url):
	metaData = {'url':url}

	# Get the html content from the url.
	htmlContent = getHtml(url)

	if htmlContent != None:
		# Parse html content with BeautifulSoup.
		soup = BeautifulSoup(htmlContent, 'html.parser')

		# Get the page title.
		titleTag = soup.title
		if titleTag:
			metaData['title'] = titleTag.string
		else:
			print("Title tag not found.")
			metaData['title'] = "Untitled Page"

		# Get meta description.
		description = soup.find('meta', attrs={'name': 'description'})
		if description:
			metaData['description'] = description['content']
			print("Description: ",metaData['description'])
		else:
			print("Meta description not found.")
			metaData['description'] = "No description provided."

		# Get meta keywords.
		keyWords = soup.find('meta', attrs={'name': 'keywords'})
		if keyWords:
			metaData['keywords'] = keyWords['content']
		else:
			print("Meta keywords not found.")
			metaData['keywords'] = ""

		# Get headers.
		#headers = soup.find_all(['h1','h2','h3','h4','h5','h6'])
		#if headers:
		#	headersText = []
		#	for i in range(len(headers) - 1):
		#		text = ''
		#		current = headers[i]
		#		next_sibling = current.find_next_sibling()
		#		while current != headers[i + 1]:
		#			if current == next_sibling:
		#				text += current.text
		#			current = current.find_next()
		#		headersText.append(text)

		#	metaData['headers'] = str(headersText)
		#else:
		#	print("Headers not found")
		#	metaData['headers'] = ""

		return metaData
	return None

# Check if given url is already in the csv.
def checkCsv(fileName, url):
	with open(fileName, 'r', newline='') as file:
		reader = csv.DictReader(file)
		for row in reader:
			if 'url' in row and row['url'] == url:
				return True
	return False

# Create or update a csv file
def updateCsv(fileName, dataDict):
	# Define feildnames
	fieldNames = list(dataDict.keys())

	with open(fileName, 'a+', newline='') as file:
		writer = csv.DictWriter(file, fieldnames=fieldNames)

		# If the file is empty, we will create the header row.
		if file.tell() == 0:
			writer.writeheader()

		# Determine if to update an entry in the csv or if to add a new entry.
		if checkCsv(fileName,dataDict['url']):
			# TODO: Update entry.
			# Do note that I'm going to swich to using a database of some sort and the use of csv is just a placeholder.
			print("Preexisting entry found. Not updated because update logic is yet to be written.")
		else:
			# Add new entry.
			writer.writerow(dataDict)

def main():
	urlsFile = "raw-urls.txt"

	i = 0
	while True:
		url = getUrl(urlsFile,i)
		if url == 0:
			break
		print("Parsing " + url + "...")
		meta = getMeta(url)
		if meta != None:
			updateCsv("index.csv",meta)
			print("Added",url,"to csv")
		i = i + 1

if __name__ == "__main__":
	main()
