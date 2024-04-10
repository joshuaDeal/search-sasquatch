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

printHelp(){
	# TODO: Add help message.
	echo "Put the help message here"
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
	USERNAME='spaghetti-search'
	HOST='localhost'
	DATABASE='spaghetti_index'

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
	# TODO: Write user credentials to file that extractor.py can use.
	# Consider security when doing this. Encrypt the file, make sure extractor can decrypt it.

	# Set up user privileges
	echo "Setting up $USERNAME's privileges..."
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS -e "GRANT INSERT, SELECT, UPDATE ON $DATABASE.* TO '$USERNAME'@'$HOST';"
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
	mariadb -u $PRIVILEGED_USER -p$PRIVILEGED_USER_PASS $DATABASE -e "CREATE TABLE sites (id INT AUTO_INCREMENT PRIMARY KEY, first_visited DATETIME DEFAULT CURRENT_TIMESTAMP, last_visited DATETIME DEFAULT CURRENT_TIMESTAMP, url VARCHAR(255) UNIQUE, title VARCHAR(50), description VARCHAR(150), keywords VARCHAR(50));"
	checkError
}

main $@
