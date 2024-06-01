#!/usr/bin/env python3

import sys
import mysql.connector
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

# Evaluate command line arguments.
def evalArguments():
	output = {}

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
	return output

# Takes a search string as input. Returns an array of result id numbers.
def preformSearch(searchString, creds):
	TITLE_POINTS = 6
	HEADER_POINTS = 4
	KEYWORD_POINTS = 5
	AGE_POINTS = 5
	serverName = "localhost"
	dbName = "sasquatch_index"
	extraPoints = TITLE_POINTS + HEADER_POINTS + KEYWORD_POINTS + AGE_POINTS - 4

	# Tokenize the search string.

	# Connect to database
	try:
		conn = mysql.connector.connect(host=serverName, unix_socket='/var/run/mysqld/mysqld.sock', database=dbName, user=creds['username'], password=creds['password'])

		cursor = conn.cursor()
	except mysql.connector.Error as error:
		print("Error connecting to database: {}".format(error))
	finally:
		if conn is not None and conn.is_connected():
			cursor.close()
			conn.close()

def main():
	arguments = evalArguments()
	creds = getMySqlCreds(arguments['credsFile'],arguments['keyFile'])

	preformSearch(arguments['searchString'], creds)

if __name__ == "__main__":
	main()
