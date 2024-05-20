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
	echo "		<header id=\"search\">\n";
	echo "			<h1>Spaghetti Search</h1>\n";
	echo "			<div>";
	echo "				<form id=\"form\" action=\"./search.php\" method=\"post\">";
	echo "					<input type=\"search\" name=\"q\" value=\"$searchString\">";
	echo "					<button>Search</button>";
	echo "				</form>";
	echo "			</div>";
	echo "		</header>\n";
	// Start of results
	$results = getResults($creds["username"], $creds["password"], $searchString);
	for ($i = 0; $i < count($results); $i++) {
		printResult($results[$i]['url'],$results[$i]['title'],$results[$i]['description'],$results[$i]['date'],$results[$i]['paragraphs']);
	}
	echo "	</body>\n";
	echo "</html>\n";
}

// Create html for a result.
function printResult($url,$title,$description,$date,$paragraphs) {
	// Display the results
	echo "<div id=\"result\">\n";
	echo "	<a href=\"$url\"><h4>$title</h4></a>\n";
	echo "	<p id=\"result-url\">$url</p>\n";
	if ($description == "No description provided.") {
		echo "	<p>$paragraphs</p>\n";
	} else {
		echo "	<p>$description</p>\n";
	}
	echo "	<p>last visited: $date</p>\n";
	echo "</div>\n";
}

// Search function. Gets database creds and search query as input.
function getResults($username, $password, $searchString) {
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
	$sql = "SELECT * FROM sites WHERE title LIKE '%$searchString%' OR description LIKE '%$searchString%' OR keywords LIKE '%$searchString%' OR headers LIKE '%$searchString%' OR paragraphs LIKE '%$searchString%' OR lists like '%$searchString%'";
	$result = mysqli_query($conn, $sql);

	// Parse data from result
	while ($row = mysqli_fetch_assoc($result)) {
		$output[] = array('title' => $row['title'], 'url' => $row['url'], 'description' => $row['description'], 'date' => $row['last_visited'], 'paragraphs' => $row['paragraphs'], 'lists' => $row['lists']);
	}

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

?>
