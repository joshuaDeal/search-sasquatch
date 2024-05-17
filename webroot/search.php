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
	$results = getResults($creds["username"], $creds["password"], $searchString,1);
	for ($i = 0; $i < count($results); $i++) {
		printResult($results[$i]['url'],$results[$i]['title'],$results[$i]['description'],$results[$i]['date']);
	}
	echo "	</body>\n";
	echo "</html>\n";
}

// Create html for a result.
function printResult($url,$title,$description,$date) {
	// Display the results
	echo "<div id=\"result\">\n";
	echo "	<a href=\"$url\"><h4>$title</h4></a>\n";
	echo "	<p id=\"result-url\">$url</p>\n";
	echo "	<p>$description</p>\n";
	echo "	<p>last visited: $date</p>\n";
	echo "</div>\n";
}

// Search function. Gets database creds and search query as input.
function getResults($username, $password, $searchString, $resultNumber) {
	$output = array();

	// Get information from database
	$servername = "localhost";
	$dbname = "spaghetti_index";

	// Create database connection
	$conn = mysqli_connect($servername,$username,$password,$dbname);

	// Check the connection
	if (!$conn) {
		die("Connection failed" . mysqli_connect_error());
	}

	// Preform query
	$sql = "SELECT * FROM sites WHERE title LIKE '%$searchString%' OR description LIKE '%$searchString%' OR keywords LIKE '%$searchString%'";
	$result = mysqli_query($conn, $sql);

	// Parse data from result
	//$data = mysqli_fetch_assoc($result);
	//foreach ($data as $key => $value) {
	//	echo "$key: $value <br>";
	//}
	//$output['title'] = $data['title'];
	//$output['url'] = $data['url'];
	//$output['description'] = $data['description'];
	//$output['date'] = $data['last_visited'];

	// Parse data from result
	while ($row = mysqli_fetch_assoc($result)) {
		//foreach ($row as $key => $value) {
		//	echo "$key: $value <br>";
		//}

		$output[] = array('title' => $row['title'], 'url' => $row['url'], 'description' => $row['description'], 'date' => $row['last_visited']);
	}

	//Display Result
	//printResult($url,$title,$description,$date);

	// Free result set
	mysqli_free_result($result);

	// Close database connection
	mysqli_close($conn);

	// Output our findings
	return $output;
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
#getResults($creds["username"], $creds["password"], $searchString,1);

?>
