#!/usr/bin/env python3

# A webcrawler. It start with a list of root pages and parses each pages's html for new urls to parse. It outputs the urls it discovers to a text file called "raw-urls.txt"

import requests
import sys
import re
from bs4 import BeautifulSoup
from urllib.parse import urljoin
from urllib.parse import urlparse
from collections import deque

# Print help message
def printHelp():
	print(sys.argv[0])
	print("Usage: " + sys.argv[0], "--[option]")
	print("Options:")
	print("\t--help\t\t\t\t\tDisplay this help message.")
	print("\t--sites \"URLs separated by commas\"\tSpecify a list of root URLs to crawl.")


# Evaluate command line arguments.
def evalArguments():
	output = {}
	output['sites'] = ''

	for i in range(len(sys.argv)):
		# Let the user specify a list of sites to begin the crawling.
		if sys.argv[i] == "--help" or sys.argv[i] == "-h":
			printHelp()
			sys.exit()
		elif sys.argv[i] == "--sites" or sys.argv[i] == "-s":
			output['sites'] = [s.strip() for s in sys.argv[i+1].split(",")]

	return output

# Returns the html content from a provided url.
def getHtml(url):
	# Check if url is empty.
	if not url:
		print("Skipping empty URL")
		return None

	# Find out what the url scheme is so we can make sure that it's valid.
	scheme = urlparse(url).scheme

	# Check if the URL scheme is HTTP or HTTPS
	if scheme not in ['http', 'https']:
		print(f"Skipping URL with unsupported scheme: {url}")
		return None

	try:
		htmlContent = None
		response = requests.get(url, timeout=15)
		
		if response.status_code == 200:
			htmlContent = response.content
		else:
			print(f'Failed to fetch URL: {url}')
			print(f'Status code: {response.status_code}')
		return htmlContent
	# In case of an ssl related error.
	except requests.exceptions.SSLError as err:
		print(f"SSL Error occurred for URL: {url}")
		return None
	# In case of a request execption related error.
	except requests.exceptions.RequestException as req_err:
		print(f"Request Exception occurred for URL: {url} - {req_err}")
		return None

# Apends the url to the url file.
def updateIndex(fileName, url):
	with open(fileName, 'a') as file:
		file.writelines(url)
		file.write('\n')

# Extracts urls from html.
def getUrls(htmlContent, baseUrl):
	urls = []
	soup = BeautifulSoup(htmlContent, 'html.parser')
	for link in soup.find_all('a', href=True):
		url = link.get('href')
		# Check if url is absolute or relative.
		if url.startswith('http') or url.startswith('https'):
			urls.append(url)
		# If the url is relative, use urljoin() to make it an absolute url.
		else:
			fullUrl = urljoin(baseUrl, url)
			urls.append(fullUrl)
	return urls

def main():
	# Evaluate command line arguments
	arguments = evalArguments()

	if arguments['sites'] == '':
		# Initialize a default queue with the starting URL(s)
		url_queue = deque(["https://en.wikipedia.org/wiki/Main_Page", "https://www.jstor.org/","https://news.ycombinator.com/","https://techcrunch.com/","https://arxiv.org/","https://lobste.rs/","https://thepiratebay10.xyz/"])
	else:
		url_queue = deque(arguments['sites'])
	
	# Set to store visited URLs to avoid duplicates
	visited_urls = set()

	# Regex pattern for fragment identifiers.
	fragmentPat = r'#.*$'
	
	while url_queue:
		# Get the next URL to crawl
		current_url = url_queue.popleft()
		if current_url not in visited_urls:
			visited_urls.add(current_url)
			# Fetch HTML content
			html_content = getHtml(current_url)
			# Make sure that html content was found
			if html_content != None:
				# Extract URLs from the current page
				new_urls = getUrls(html_content, current_url)
				for url in new_urls:
					# Avoid revisiting already processed URLs
					if url not in visited_urls and not re.search(fragmentPat, url):
						# Add new URLs to the queue
						url_queue.append(url)
						# Add newly discovered urls to urls file.
						updateIndex("raw-urls.txt",url)
						print("Added",url,"to index.")

if __name__ == "__main__":
	main()
