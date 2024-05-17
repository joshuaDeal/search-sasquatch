#!/usr/bin/env python3

# A webcrawler. It start with a list of root pages and parses each pages's html for new urls to parse. It outputs the urls it discovers to a text file called "raw-urls.txt"

from bs4 import BeautifulSoup
import requests
from urllib.parse import urljoin
from urllib.parse import urlparse
from collections import deque

# Returns the html content from a provided url.
def getHtml(url):
	# Find out what the url scheme is so we can make sure that it's valid.
	scheme = urlparse(url).scheme

	# Check if the URL scheme is HTTP or HTTPS
	if scheme not in ['http', 'https']:
		print(f"Skipping URL with unsupported scheme: {url}")
		return None

	try:
		htmlContent = None
		response = requests.get(url)
		
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
	# Initialize a queue with the starting URL(s)
	url_queue = deque(["https://en.wikipedia.org/wiki/Main_Page","https://linuxreviews.org/","https://ebay.com", "https://www.jstor.org/","https://news.ycombinator.com/","https://techcrunch.com/","https://arxiv.org/","https://lobste.rs/","https://thepiratebay10.xyz/"])
	
	# Set to store visited URLs to avoid duplicates
	visited_urls = set()
	
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
					if url not in visited_urls:
						# Add new URLs to the queue
						url_queue.append(url)
						# Add newly discovered urls to urls file.
						updateIndex("raw-urls.txt",url)
						print("Added",url,"to index.")

if __name__ == "__main__":
	main()
