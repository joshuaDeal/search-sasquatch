#!/bin/bash
#This script is a fucking disaster.

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

	gpgCmd="gpg --decrypt --batch --passphrase-file $keyFile $credsFile 2> /dev/null" 
	gpgOutput=$(eval $gpgCmd)

	if [ $? -ne 0 ]; then
		echo "Error: Failed to decrypt file." >&2
		exit
	else
		username=("$(echo $gpgOutput | cut -d ":" -f 1)")
		password=("$(echo $gpgOutput | cut -d ":" -f 2)")
	fi
}

arrayCountValues() {
	input=("$@")

	declare -A valueCounts

	# Count the occurrences of each value
	for value in "${input[@]}"; do
		(( valueCounts[$value]++ ))
	done

	# Print the associative array
	for key in "${!valueCounts[@]}"; do
		echo "$key:${valueCounts[$key]}"
	done
}

preformSearch() {
	TITLE_POINTS=6
	HEADER_POINTS=4
	KEYWORD_POINTS=5
	AGE_POINTS=5
	serverName="localhost"
	dbName="sasquatch_index"
	conn="mariadb -u $username -p$password -h $serverName $dbName --batch --skip-column-names"
	extraPoints=$(($TITLE_POINTS + $HEADER_POINTS + $KEYWORD_POINTS + AGE_POINTS - 4))

	# Tokenize the search string.
	# Note that this regex removes all non alphanumeric characters (excluding spaces). Consider if Replacing such characters with spaces rather than removing them would be a better option.
	cleanString=$(echo "$searchString" | tr -cd '[:alnum:] [:space:]' | tr '[:upper:]' '[:lower:]')
	searchTokens=()
	while read -r -d ' ' term; do
		searchTokens+=(" $term ")
	done <<< "$cleanString "

	# Calculate the Term Frequency (TF) for each token.
	declare -A searchTermFrequency

	while read -r line; do
		key=${line%:*}
		value=${line#*:}
		searchTermFrequency[$key]=$value
	done < <(arrayCountValues ${searchTokens[@]})

	# Print out searchTermFrequency
	#for key in "${!searchTermFrequency[@]}"; do
	#	echo "$key:${searchTermFrequency[$key]}"
	#done

	# Calculate the Inverse Document Frequency (IDF) for each token.
	declare -A idf

	for token in "${searchTokens[@]}"; do
		sql="SELECT COUNT(DISTINCT id) AS document_count FROM sites WHERE LOWER(keywords) LIKE LOWER('%$token%') OR LOWER(title) LIKE LOWER('%$token%') OR LOWER(description) LIKE LOWER('%$token%') OR LOWER(headers) LIKE LOWER('%$token%') OR LOWER(paragraphs) LIKE LOWER('%$token%') OR LOWER(lists) LIKE LOWER('%$token%')"
	
		result=$(echo $sql | $conn)

		documentCount=$(echo $result | awk '{print $2}')

		idf[$token]=$documentCount
	done

	# Print the IDF for each term.
	#for token in "${searchTokens[@]}"; do
	#	echo "Token: $token, IDF: ${idf[$token]}"
	#done

	# Calculate the TF-IDF score for each document.
	sql="SELECT id, url, last_visited, keywords, title, description, headers, paragraphs, lists FROM sites"
	result=$(echo $sql | $conn)

	echo "$result" | head -n 2

	while IFS= read -r -d $'\n' row; do
		tfidfScore=0
		
		# Split row data into an array
		IFS=$'\t' read -r -a rowArray <<< "${row}"
		#rowArray=($row)

		for token in "${searchTokens[@]}"; do
			tf=$(echo "${rowArray[3]} ${rowArray[4]} ${rowArray[5]} ${rowArray[6]} ${rowArray[7]} ${rowArray[8]}" | tr '[:upper:]' '[:lower:]' | grep -o -i "$token" | wc -l)
			idfValue=${idf[$token]}

			# Calculate TF-IDF score.
			tfidfScore=$(echo "scale=6; $tf * l((${#results[@]} + 1) / $idfValue) + $tfidfScore" | bc -l)
			echo "The current item is ${rowArray[0]}, its title is ${rowArray[4]}, and its initial score is $tfidfScore."

			# Give extra points if the token appears in the title.
			if [[ "${rowArray[4],,}" == *"{$token,,}"* ]]; then
				tfidfScore=$((tfidfScre + TITLE_POINTS))
			fi

			# Give extra points if the token appears in the headers.
			if [[ "${rowArray[6],,}" == *"{$token,,}"* ]]; then
				tfidfScore=$((tfidfScre + HEADER_POINTS))
			fi

			# Give extra points if the token appears in the keywords.
			if [[ "${rowArray[3],,}" == *"{$token,,}"* ]]; then
				tfidfScore=$((tfidfScre + KEYWORD_POINTS))
			fi
	
			# Give extra points for young age.
			if [ "${rowArray[2]}" != "false" ]; then
				timeStamp=$(date +%s)
				foundTime=$(date -d "${rowArray[2]}" +%s)
				timeDiff=$((timeStamp - foundTime))
				days=$((timeDiff / 86400))

				if [ $days -lt 7 ]; then
					tfidfScore=$(echo "scale=6; $tfidfScore + $AGE_POINTS" | bc)
				elif [ $days -lt 14 ]; then
					tfidfScore=$(echo "scale=6; $tfidfScore + $AGE_POINTS / 2" | bc)
				elif [ $days -lt 30 ]; then
					tfidfScore=$(echo "scale=6; $tfidfScore + $AGE_POINTS / 3" | bc)
				elif [ $days -lt 365 ]; then
					tfidfScore=$(echo "scale=6; $tfidfScore + $AGE_POINTS / 5" | bc)
				fi
			fi
		done

		# Sort documents by TF-IDF
	done <<< "$result"
}

main() {
	# Evaluate command line arguments.
	evalArguments $@

	# Get credentials for database
	getMySqlCreds

	# Preform the search
	preformSearch
}

main $@

#myArray=("apple" "banana" "apple" "orange" "banana" "apple")
#arrayCountValues ${myArray[@]}
