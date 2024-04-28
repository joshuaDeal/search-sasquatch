<?php

// TODO: Make this program work with gpg! Don't hardcode mysql log in credentials!

// Read MySql username and password from a file.
function getMySqlCreds($fileName, $keyFile) {
	$credentials = array();

	// Construct gpg command for decryption
	$gpgCommand = "sudo gpg --decrypt --batch --passphrase-file $keyFile $fileName";

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
	printResult($creds["username"], $creds["password"], $searchString,2);
	printResult($creds["username"], $creds["password"], $searchString,3);
	echo "	</body>\n";
	echo "</html>\n";
}

function printResult($username, $password, $searchSting, $resultNumber) {
	// Get information from database
	$servername = "localhost";
	$dbname = "spaghetti_index";

	//echo $servername . " " . $username . " " . $password . " " . $dbname . "\n";

	// Create database connection
	$conn = mysqli_connect($servername,$username,$password,$dbname);

	// Check the connection
	if (!$conn) {
		die("Connection failed" . mysqli_connect_error());
	}

	// Preform query
	$sql = "SELECT * FROM sites LIMIT 1";
	$result = mysqli_query($conn, $sql);

	// Parse data from result
	$data = mysqli_fetch_assoc($result);
	$title = $data['title'];
	$url = $data['url'];
	$description = $data['description'];

	// Display the results
	echo "<div id=\"result\">\n";
	echo "	<a href=\"$url\"><h4>$title</h4></a>\n";
	echo "	<p>$description</p>\n";
	echo "</div>\n";

	// Free result set
	mysqli_free_result($result);

	// Close database connection
	mysqli_close($conn);
}

function main() {
	// Make sure we have received a post request.
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		$searchString = filter_var($_POST["q"], FILTER_SANITIZE_STRING);

		if (empty($searchString)) {
			header('Location: http://search.example.com/');
			exit;
		}

		//$creds = getMySqlCreds("../db_creds.gpg", "../decryption_key.txt");
		$creds = array("username" => "spaghetti-search", "password" => "password");
		printSite($searchString, $creds);
	}
}

main();

?>
