#!/usr/bin/env python3

import sys
from extractor import getMySqlCreds

def evalArguments():
	output = {}

	for i in range(len(sys.argv)):
		# Let user set key file.
		if sys.argv[i] == "--key" or sys.argv[i] == "-k":
			output['keyFile'] = sys.argv[i+1]
		# Let user set credentials file.
		elif sys.argv[i] == "--credentials-file" or sys.argv[i] == "-c":
			output['credsFile'] = sys.argv[i+1]
	return output

def main():
	arguments = evalArguments()
	creds = getMySqlCreds(arguments['credsFile'],arguments['keyFile'])

if __name__ == "__main__":
	main()
