#!/bin/bash

# Input search string.
# Output Results.

printHelp() {
	echo "$0"
	echo "Usage:  $0 --[option]"
	echo "Options:"
	echo "	--help						Display this help message."
	echo "	--credentials-file <path to file>		Specify what file contains the database credentials."
	echo "	--key-file <path to file>			Specify what file contains the database credentials key."
	echo "	--search-string \"search string text\"		Specify the search string."
	echo "	--results-per-page <number>			Specify the number of result to load per each page."
	echo "	--page <number>					Specify what page to load the results for."
}

evalArguments() {
	arguments=("$@")

	for ((i=0;i<${#arguments[@]};i++)); do
		# Print help message
		if [ "${arguments[i]}" == "--help" ] || [ "${arguments[i]}" == "-h" ]; then
			printHelp
			exit
		fi

		# Set database credentials file.
		if [ "${arguments[i]}" == "--credentials-file" ] || [ "${arguments[i]}" == "-c" ]; then
			if [ $((i + 1 )) -lt ${#arguments[@]} ]; then
				credsFile="${arguments[i+ 1]}"
			else
				echo "Error: No file path was provided for ${arguments[i]}" >&2
				exit
			fi
		fi

		# Set database credentials key file.
		if [ "${arguments[i]}" == "--key-file" ] || [ "${arguments[i]}" == "-k" ]; then
			if [ $((i + 1 )) -lt ${#arguments[@]} ]; then
				keyFile="${arguments[i+ 1]}"
			else
				echo "Error: No file path was provided for ${arguments[i]}" >&2
				exit
			fi
		fi

		# Get search string
		if [ "${arguments[i]}" == "--search-string" ] || [ "${arguments[i]}" == "-s" ]; then
			if [ $((i + 1 )) -lt ${#arguments[@]} ]; then
				if [ $i -eq $(( ${#arguments[@]} - 1 )) ]; then
					echo "Error: No string was provided for ${arguments[i]}" >&2
					exit
				fi

				searchString=""
				while [[ ${arguments[i+1]} != "--"* && ${arguments[i+1]} != "-"* ]]; do
					searchString+=" ${arguments[i+1]}"
					((i++))

					if [ $i -eq $(( ${#arguments[@]} - 1 )) ]; then
						break
					fi
				done
				searchString="${searchString#" "}"
				echo "Search String: $searchString"
			else
				echo "Error: No string was provided for ${arguments[i]}" >&2
				exit
			fi
		fi

		# Set results per page.
		if [ "${arguments[i]}" == "--results-per-page" ] || [ "${arguments[i]}" == "-r" ]; then
			if [ $((i + 1 )) -lt ${#arguments[@]} ]; then
				resultsPerPage="${arguments[i+ 1]}"
			else
				echo "Error: No file path was provided for ${arguments[i]}" >&2
				exit
			fi
		fi

		# Set page number.
		if [ "${arguments[i]}" == "---page" ] || [ "${arguments[i]}" == "-p" ]; then
			if [ $((i + 1 )) -lt ${#arguments[@]} ]; then
				pageNumber="${arguments[i+ 1]}"
			else
				echo "Error: No file path was provided for ${arguments[i]}" >&2
				exit
			fi
		fi
	done
}

getMySqlCreds() {
	if [ -z $keyFile ] || [ -z $credsFile ]; then
		echo "Error: Invalid credential information provided" >&2
		exit
	fi

	output=()

	gpgCmd="gpg --decrypt --batch --passphrase-file $keyFile $credsFile 2> /dev/null" 
	gpgOutput=$(eval $gpgCmd)

	if [ $? -ne 0 ]; then
		echo "Error: Failed to decrypt file." >&2
		exit
	else
		output+=("$(echo $gpgOutput | cut -d ":" -f 1)")
		output+=("$(echo $gpgOutput | cut -d ":" -f 2)")
	fi

	echo ${output[@]}
}

preformSearch () {
	:
}

main() {
	# Evaluate command line arguments.
	evalArguments $@

	# Get credentials for database
	creds=($(getMySqlCreds))

	echo "${creds[0]}:${creds[1]}"
}

main $@
