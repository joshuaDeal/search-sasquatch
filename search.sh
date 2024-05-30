#!/bin/bash

# Input search string.
# Output Results.

printHelp() {
	echo "$0"
	echo "Usage:  $0 --[option"
	echo "Options:"
	echo "	--help			Display this help message."
	echo "	--credentials-file	Specify what file contains the database credentials"
	echo "	--key-file		Specify what file contains the database credentials key"
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
	done
}

getMySqlCreds() {
	if [ -z $keyFile ] || [ -z $credsFile ]; then
		echo "Error: Invalid credential information provided" >&2
		exit
	fi

	output=()

	gpgCmd="gpg --decrypt --batch --passphrase-file $keyFile $credsFile"
	gpgOutput=`$gpgCmd`

	if [ $? -ne 0 ]; then
		echo "Error: Failed to decrypt file." >&2
		exit
	else
		output+=("$(echo $gpgOutput | cut -d ":" -f 1)")
		output+=("$(echo $gpgOutput | cut -d ":" -f 2)")
	fi

	echo ${output[@]}
}

main() {
	# Evaluate command line arguments.
	evalArguments $@

	# Get credentials for database
	creds=($(getMySqlCreds $1 $2))

	echo "${creds[0]}:${creds[1]}"
}

main $@
