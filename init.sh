#!/bin/bash

# Initialize the database and create the database user.
# TODO: Make sure this script isn't vulnerable to sql injections.

# Make sure the last command didn't generate an error.
checkError(){
	if [ $? -eq 0 ]; then
		echo "Done!"
	else
		echo "Failed. Error code: $?"
		exit
	fi
}

# Print help message.
printHelp(){
	echo "$0"
	echo "Usage:  $0 --[option]"
	echo "Options:"
	echo "	--help			Display this help message."
	echo "	--user <username>	Specify privileged user to initially use when configuring the database."
}

# Evaluate command line arguments
evalArguments(){
	arguments=("$@")
	for ((i=0;i<${#arguments[@]};i++)); do
		# Set up user to run mariadb with
		if [ "${arguments[i]}" == "-u"  ] || [ "${arguments[i]}" == "--user"  ]; then
			if [ $((i + 1)) -lt ${#arguments[@]} ]; then
				PRIVILEGED_USER="${arguments[i + 1]}"
			else
				echo "Error: Username not provided."
			fi
		fi

		# Print help message
		if [ "${arguments[i]}" == "-h"  ] || [ "${arguments[i]}" == "--help"  ]; then
			printHelp
			exit
		fi
	done
}

main(){
	# Default values
	PRIVILEGED_USER='root'
	USERNAME='search-sasquatch'
	HOST='localhost'
	DATABASE='sasquatch_index'

	# Evaluate command line arguments
	evalArguments $@

	# Get the password for the privileged user
	read -s -p "Please enter the password for $PRIVILEGED_USER: " PRIVILEGED_USER_PASS
	echo

	# Ask the user to set a password for the new mariadb user.
	read -s -p "Please enter a password for the new MariaDB user: " PASSWORD
	echo
	
	# Create the database
	echo "Creating database '$DATABASE'..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS -e "CREATE DATABASE $DATABASE;"
	checkError
	
	# Create user account
	echo "Creating mariadb user '$USERNAME'..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS -e "CREATE USER '$USERNAME'@'$HOST' IDENTIFIED BY '$PASSWORD';"
	checkError

	# TODO: **SECURITY** Keep in mind the security implications of this step. It could probably be improved upon.
	# Create decryption key file
	echo "Creating decryption key file..."
	openssl rand -base64 32 > decryption_key.txt
	checkError
	# Write user credentials to file that extractor.py can use.
	echo "Saving credentials to file..."
	echo "$USERNAME:$PASSWORD" | gpg --batch --yes --symmetric --cipher-algo AES256 --passphrase-file decryption_key.txt --output db_creds.gpg
	checkError

	# Set up user privileges
	echo "Setting up $USERNAME's privileges..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS -e "GRANT INSERT, SELECT, UPDATE, DELETE ON $DATABASE.* TO '$USERNAME'@'$HOST';"
	checkError
	echo "Setting up $USERNAME's constraints..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS -e "REVOKE DELETE ON *.* FROM '$USERNAME'@'$HOST';"
	checkError
	# Apply changes
	echo "Flushing privileges..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS -e "FLUSH PRIVILEGES;"
	checkError

	# Create a new table
	echo "Creating sites table in new database..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS $DATABASE -e "CREATE TABLE sites (id INT AUTO_INCREMENT PRIMARY KEY, first_visited DATETIME DEFAULT CURRENT_TIMESTAMP, last_visited DATETIME DEFAULT CURRENT_TIMESTAMP, safe TINYINT DEFAULT 1 ,url VARCHAR(255) UNIQUE, title VARCHAR(70), description VARCHAR(150), keywords VARCHAR(50), headers VARCHAR(300), paragraphs VARCHAR(500), lists VARCHAR(300));"
	checkError

	# Add cron job to run purge.sh.
	(crontab -l ; echo "0 0 * * * `pwd`/purge.sh") | crontab -
	(crontab -l ; echo "0 12 * * * `pwd`/purge.sh") | crontab -

	# Create directory /opt/search-sasquatch/
	webUser=$(ps aux | grep nginx | grep -v grep | grep -v root | awk '{print $1}')
	mkdir /opt/search-sasquatch
	cp ./*.{py,gpg,txt} /opt/search-sasquatch/
	chown :$webUser /opt/search-sasquatch
	chown :$webUser /opt/search-sasquatch/*
	chmod g=r /opt/search-sasquatch/*
	chmod g+x /opt/search-sasquatch/*.py
	chmod g-w /opt/search-sasquatch/*.py
	chmod o-xw /opt/search-sasquatch/*.py
}

main $@
