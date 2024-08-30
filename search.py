#!/usr/bin/env python3

import sys
import math
import mariadb
import re
import json
import requests
from bs4 import BeautifulSoup
from datetime import datetime
from urllib.parse import urljoin
from extractor import getMySqlCreds

# Print a helpful message.
def printHelp():
	print(sys.argv[0])
	print("Usage: " + sys.argv[0], "--option")
	print("\t--help\t\t\t\t\tDisplay this help message.")
	print("\t--credentials-file <path to file>\tSpecify what file contains the credentials for the database.")
	print("\t--key-file <path to file>\t\tSpecify what file contains the key for the credentials file.")
	print("\t--search-string \"search string text\"\tSpecify the search string.")
	print("\t--results-per-page <number>\t\tSpecify the number of results per page.")
	print("\t--page <number>\t\t\t\tSpecify what page to load.")
	print("\t--output <format>\t\t\tSpecify what format to use in output.")
	print("\t--nsfw\t\t\t\t\tDisable safe search.")
	print("\t--image\t\t\t\t\tPerform an image search.")

# Evaluate command line arguments.
def evalArguments():
	# Some default values.
	output = {}
	output['pageNumber'] = 1
	output['resultsPerPage'] = 0
	output['outputMode'] = 'cli'
	output['safe'] = True
	output['imageMode'] = False

	for i in range(len(sys.argv)):
		# Print help message.
		if sys.argv[i] == "--help" or sys.argv[i] == "-h":
			printHelp()
			sys.exit()
		# Let user set key file.
		elif sys.argv[i] == "--key" or sys.argv[i] == "-k":
			output['keyFile'] = sys.argv[i+1]
		# Let user set credentials file.
		elif sys.argv[i] == "--credentials-file" or sys.argv[i] == "-c":
			output['credsFile'] = sys.argv[i+1]
		# Let user input a search string.
		elif sys.argv[i] == "--search-string" or sys.argv[i] == "-s":
			output['searchString'] = sys.argv[i+1]
		# Let user set the number of result per page.
		elif sys.argv[i] == "--results-per-page" or sys.argv[i] == "-r":
			output['resultsPerPage'] = sys.argv[i+1]
		# Let user set the page number.
		elif sys.argv[i] == "--page" or sys.argv[i] == "-p":
			output['pageNumber'] = sys.argv[i+1]
		# Let user set output mode.
		elif sys.argv[i] == "--output" or sys.argv[i] == "-o":
			output['outputMode'] = sys.argv[i+1]
		# Let user disable safe search.
		elif sys.argv[i] == "--nsfw" or sys.argv[i] == "-n":
			output['safe'] = False
		elif sys.argv[i] == "--image" or sys.argv[i] == "-i":
			output['imageMode'] = True
	return output

# Count how many times a item appears in an array.
def listCountValues(input):
	valueCounts = {}

	for value in input:
		currentCount = valueCounts.get(value, 0)

		valueCounts[value] = currentCount + 1

	return valueCounts

# Takes a search string as input. Returns an array of result id numbers.
def performSearch(searchString, safe, creds):
	TITLE_POINTS = 6
	HEADER_POINTS = 4
	KEYWORD_POINTS = 5
	AGE_POINTS = 5
	serverName = "localhost"
	dbName = "sasquatch_index"
	extraPoints = TITLE_POINTS + HEADER_POINTS + KEYWORD_POINTS + AGE_POINTS - 4

	# Tokenize the search string.
	pattern = r'[^a-zA-Z0-9\s]'
	cleanList = re.sub(pattern, ' ', searchString).lower().split()
	searchTokens = [' ' + token + ' ' for token in cleanList]

	# Calculate the Term Frequency (TF) for each token.
	searchTermFrequency = listCountValues(searchTokens)

	# Connect to database
	try:
		conn = mariadb.connect(host=serverName, unix_socket='/var/run/mysqld/mysqld.sock', database=dbName, user=creds['username'], password=creds['password'])

		cursor = conn.cursor()

		# Calculate the Inverse Document Frequency (IDF) for each token.
		idf={}
		for token in searchTokens:
			query = "SELECT COUNT(DISTINCT id) AS document_count FROM sites WHERE LOWER(keywords) LIKE LOWER(%s) OR LOWER(title) LIKE LOWER(%s) OR LOWER(description) LIKE LOWER(%s) OR LOWER(headers) LIKE LOWER(%s) OR LOWER(paragraphs) LIKE LOWER(%s) OR LOWER(lists) LIKE LOWER(%s)"
			cursor.execute(query, ('%' + token + '%',) * 6)
			result = cursor.fetchone()[0]
			idf[token] = result

		# Find number of rows.
		query = "SELECT COUNT(*) FROM sites"
		cursor.execute(query)
		num_rows = cursor.fetchone()[0]

		# Calculate the TF-IDF score for each document.
		tfidf={}
		if safe == 1:
			query = "SELECT id, url, last_visited, keywords, title, description, headers, paragraphs, lists FROM sites WHERE safe = 1"
		else:
			query = "SELECT id, url, last_visited, keywords, title, description, headers, paragraphs, lists FROM sites"

		cursor.execute(query)

		row = cursor.fetchone()
		while row:
			tfidfScore = 0
			for token in searchTokens:
				db_id = row[0]
				keywords = row[3]
				title = row[4]
				description = row[5]
				headers = row[6]
				paragraphs = row[7]
				lists = row[8]
				last_visited = row[2]

				text_to_search = ' '.join([str(val) for val in [keywords, title, description, headers, paragraphs, lists]])
				tf = text_to_search.lower().count(token.lower())
				idfValue = idf[token]

				# Calculate TF-IDF score.
				if idfValue > 0:
					tfidfScore += tf * math.log((num_rows + 1) / idfValue)
				else:
					pass

				# Give extra points if the token appears in the title.
				if token in title:
					#print("Title points awarded")
					tfidfScore += TITLE_POINTS

				# Give extra points if the token appears in the header text.
				if token in headers:
					#print("Header points awarded")
					tfidfScore += HEADER_POINTS

				# Give extra points if the token appears in the keywords.
				if token in keywords:
					#print("Keyword points awarded")
					tfidfScore += KEYWORD_POINTS

				# Give extra points if the result age is young.
				if last_visited:
					timeStamp = int(datetime.now().timestamp())
					foundTime = int(last_visited.timestamp())
					timeDiff = timeStamp - foundTime
					days = timeDiff / 86400

					if days < 7:
						#print("7 days age points awarded")
						tfidfScore += AGE_POINTS
					elif days < 14:
						#print("14 days age points awarded")
						tfidfScore += (AGE_POINTS / 2)
					elif days < 30:
						#print("30 days age points awarded")
						tfidfScore += (AGE_POINTS / 3)
					elif days < 365:
						#print("1 year age points awarded")
						tfidfScore += (AGE_POINTS / 5)

			tfidf[db_id] = tfidfScore
			row = cursor.fetchone()

		# Drop any results that are too low
		cleanResults = {key: value for key, value in tfidf.items() if value > extraPoints}

		# Sort documents by score
		sortedResults = dict(sorted(cleanResults.items(), key=lambda x : x[1], reverse=True))
	except mariadb.Error as error:
		print("Error connecting to database: {}".format(error))
	finally:
		if conn is not None and conn.ping():
			cursor.close()
			conn.close()

	return sortedResults

def printCliResults(results, resultsPerPage, page, creds):
	start_index = (page - 1) * resultsPerPage
	end_index = page * resultsPerPage if resultsPerPage > 0 else len(results)

	print("Total results:", len(results))
	print("Pages:", math.ceil(len(results) / resultsPerPage))
	print()

	try:
		conn = mariadb.connect(host="localhost", unix_socket="/var/run/mysqld/mysqld.sock", database="sasquatch_index", user=creds['username'], password=creds['password'])
		cursor = conn.cursor()

		for index, (result_id, score) in enumerate(results.items()):
			if index >= start_index and index < end_index:
				query = "SELECT title, url, description, paragraphs FROM sites WHERE id = %s"
				cursor.execute(query, (result_id,))
				result = cursor.fetchone()
				if result:
					title, url, description, paragraphs = result
					if description != "No description provided.":
						print("%s\n%s\n%s\nResult ID: %d\nScore: %d\n" % (title.replace("\n", ""), url, description.replace("\n", ""), result_id, score))
					else:
						print("%s\n%s\n%s\nResult ID: %d\nScore: %d\n" % (title.replace("\n", ""), url, paragraphs.replace("\n", ""), result_id, score))

	except mariadb.Error as error:
		print("Error retrieving data from database: {}".format(error))
	finally:
		if conn is not None and conn.ping():
			cursor.close()
			conn.close()

def getJsonResults(results, resultsPerPage, page, creds):
	start_index = (page - 1) * resultsPerPage
	end_index = page * resultsPerPage if resultsPerPage > 0 else len(results)

	try:
		conn = mariadb.connect(host="localhost", unix_socket="/var/run/mysqld/mysqld.sock", database="sasquatch_index", user=creds['username'], password=creds['password'])
		cursor = conn.cursor()

		total_results = len(results)
		total_pages = math.ceil(total_results / resultsPerPage)

		output_data = {"total_results": total_results, "total_pages": total_pages, "results": []}

		for index, (result_id, score) in enumerate(results.items()):
			if index >= start_index and index < end_index:
				query = "SELECT title, url, description, paragraphs FROM sites WHERE id = %s"
				cursor.execute(query, (result_id,))
				result = cursor.fetchone()
				if result:
					title, url, description, paragraphs = result
					if description != "No description provided.":
						result_data = {"title": title.replace("\n", ""), "url": url, "description": description.replace("\n", ""), "result_id": result_id, "score": score}
					else:
						result_data = {"title": title.replace("\n", ""), "url": url, "description": paragraphs.replace("\n", ""), "result_id": result_id, "score": score}
					output_data["results"].append(result_data)

		json_output = json.dumps(output_data, indent=2, ensure_ascii=False)
		return json_output
		#print(json_output)

	except mariadb.Error as error:
		print("Error retrieving data from database: {}".format(error))
	finally:
		if conn is not None and conn.ping():
			cursor.close()
			conn.close()

# Parse urls for relevant images. (image search)
def parseImageUrls(jsonResults):
	imageFormats = ('.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg')

	# Get urls from input results.
	data = json.loads(jsonResults)
	urls = [result['url'] for result in data['results']]

	# Parse input urls for image urls
	images = {}
	for url in urls:
		#print("Looking for images at", url, "...")
		try:
			# Send GET request to the URL.
			response = requests.get(url)
			response.raise_for_status()

			# Parse response content with BeautifulSoup.
			soup = BeautifulSoup(response.content, 'html.parser')
			ignoreTags = {'nav', 'header', 'footer', 'aside', 'script', 'style'}
			#imgTags = soup.find_all('img')

			# Extract valid image urls.
			for img in soup.find_all('img'):
				# Skip image if its the child of an ignored tag.
				if img.find_parents(ignoreTags):
					#print("Ignoring", img.get('src'), "!")
					continue

				imgUrl = img.get('src')

				# Make sure we have a full url and not a relative path.
				if imgUrl:
					fullImgUrl = urljoin(url, imgUrl)

				# Make sure we have a valid image url.
				if fullImgUrl.endswith(imageFormats):
					#print("Found", fullImgUrl, "!")
					images[fullImgUrl] = url

		except requests.RequestException as e:
			#print("Error fetching", url + ":", e)
			pass

	# Make sure we include meta data like total number of pages in the output dataset.
	images['total_pages'] = data['total_pages']
	
	return images

def main():
	arguments = evalArguments()
	creds = getMySqlCreds(arguments['credsFile'],arguments['keyFile'])

	results = performSearch(arguments['searchString'], arguments['safe'], creds)

	if arguments['imageMode'] and arguments['outputMode'] == 'json':
		print(json.dumps(parseImageUrls(getJsonResults(results, int(arguments['resultsPerPage']), int(arguments['pageNumber']), creds))))
	elif arguments['imageMode'] and arguments['outputMode'] == 'cli':
		for image, source in parseImageUrls(getJsonResults(results, int(arguments['resultsPerPage']), int(arguments['pageNumber']), creds)).items():
			if image != "total_pages":
				print("Image:", str(image) + "\n" + "Source:", str(source) + "\n")
	elif arguments['outputMode'] == 'cli':
		printCliResults(results, int(arguments['resultsPerPage']), int(arguments['pageNumber']), creds)
	elif arguments['outputMode'] == 'json':
		print(getJsonResults(results, int(arguments['resultsPerPage']), int(arguments['pageNumber']), creds))

if __name__ == "__main__":
	main()
