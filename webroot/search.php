<?php

// Read MySql username and password from a file.
function getMySqlCreds($fileName, $keyFile) {
	$credentials = array();

	// Construct gpg command for decryption
	$gpgCommand = "gpg --decrypt --batch --passphrase-file $keyFile $fileName";

	// Try and decrypt the creds file
	exec($gpgCommand, $output, $returnCode);
	if ($returnCode === 0) {
		$decryptedData = implode("\n", $output);
		list($username, $password) = explode(':', $decryptedData);
		$credentials['username'] = $username;
		$credentials['password'] = $password;
		return $credentials;
	} else {
		echo "Error decrypting file\n";
		return null;
	}
}

function printSite($searchString, $creds) {
	echo "<!DOCTYPE html>\n";
	echo "<html lang=\"en\">\n";
	echo "	<head>\n";
	echo "		<title>" . $searchString . " - Spaghetti Search!</title>\n";
	echo "		<link rel=\"stylesheet\" href=\"style.css\">\n";
	echo "		<link rel=\"shortcut icon\" href=\"favicon.ico\" type=\"image/x-icon\">\n";
	echo "		<meta name=\"description\" content=\"Search results for " . $searchString . "\">\n";
	echo "		<meta charset=\"utf-8\">\n";
	echo "		<meta name=\"viewport\" content=\"width=device-width, inital-scale=1\">\n";
	echo "	</head>\n";
	echo "	<body>\n";
	echo "		<p>You have searched for \"" . $searchString . "\".</p>\n";
	printResult($creds["username"], $creds["password"], $searchString,1);
	printResult($searchString,2);
	printResult($searchString,3);
	echo "	</body>\n";
	echo "</html>\n";
}

function printResult($username, $password, $searchSting, $resultNumber) {
	// Get information from database
	$servername = "localhost";
	$dbname = "spaghetti_index";

	// Display the results
	echo "<div id=\"result\">\n";
	echo "	<a href=\"http://example.com\"><h4>This is result number " . $resultNumber . "</h4></a>\n";
	echo "	<p>The description will go right here!</p>\n";
	echo "</div>\n";
}

function main() {
	// Make sure we have received a post request.
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		$searchString = filter_var($_POST["q"], FILTER_SANITIZE_STRING);

		if (empty($searchString)) {
			header('Location: http://search.example.com/');
			exit;
		}

		$creds = getMySqlCreds("../db_creds.gpg", "../decryption_key.txt");
		printSite($searchString, $creds);
	}
}

main();
#$foo = getMySqlCreds("../db_creds.gpg","../decryption_key.txt");
#echo $foo["username"] . ":" . $foo["password"] . "\n";

?>
