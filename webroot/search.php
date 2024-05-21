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

// Outputs html for webpage.
function printSite($searchString) {
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
	echo "			<div>\n";
	echo "				<form id=\"form\" action=\"./search.php\" method=\"post\">\n";
	echo "					<input type=\"search\" name=\"q\" value='$searchString'>\n";
	echo "					<button>Search</button>\n";
	echo "				</form>\n";
	echo "			</div>\n";
	echo "		</header>\n";
	// Start of results
	getResults($searchString);
	echo "	</body>\n";
	echo "</html>\n";
}

// Preforms TF-IDF search.
function getResults($searchString) {
	$serverName = "localhost";
	$dbName = "spaghetti_index";
	$username = "spaghetti-search";
	$password = "password";

	// Create db connection
	$conn = mysqli_connect($serverName, $username, $password, $dbName);
	
	// Check connection
	if (!$conn) {
		die("Connection failed" . mysqli_connect_error());
	}
	
	// Tokenize the search string
	$cleanString = preg_replace("/[^a-zA-Z0-9\s]+/", "", $searchString);
	$cleanArray = explode(' ', $cleanString);
	$searchTokens = [];
	foreach ($cleanArray as $token) {
		$token = " " . $token . " ";
		$searchTokens[] = $token;
	}
	
	// Calculate the TF for each token
	$searchTermFrequency = array_count_values($searchTokens);
	
	// Calculate the IDF for each token
	$idf = array();
	foreach ($searchTokens as $token) {
		$sql = "SELECT COUNT(DISTINCT id) AS document_count FROM sites WHERE keywords LIKE '%$token%' OR title LIKE '%$token%' OR description LIKE '%$token%' OR headers LIKE '%$token%' OR paragraphs LIKE '%$token%' OR lists LIKE '%$token%'";
		$result = mysqli_query($conn, $sql);
		$row = mysqli_fetch_assoc($result);
		$idf[$token] = $row['document_count'];
	}
	
	// Calculate the TF-IDF for each document
	$tfidf = array();
	$sql = "SELECT id, url, keywords, title, description, headers, paragraphs, lists FROM sites";
	$result = mysqli_query($conn, $sql);
	while ($row = mysqli_fetch_assoc($result)) {
		$tfidfScore = 0;
		foreach ($searchTokens as $token) {
			$tf = substr_count(strtolower($row['keywords'] . ' ' . $row['title'] . ' ' . $row['description'] . ' ' . $row['headers'] . ' ' . $row['paragraphs'] . ' ' . $row['lists']), $token);
			$idfValue = $idf[$token]; // Use IDF value calculated previously
	
			// Calculate TF-IDF score for each document
			if ($idfValue != 0) {
				$tfidfScore += $tf * log((mysqli_num_rows($result) + 1) / $idfValue); // Adjust the calculation as needed
			}
		}
		$tfidf[$row['url']] = $tfidfScore;
	}
	
	// Sort documents by TF-IDF
	arsort($tfidf);
	
	// Output the results
	foreach ($tfidf as $url => $score) {
		if ($score != 0) {
			$sql = "SELECT title, description, last_visited, paragraphs  FROM sites WHERE url = '$url'";
			$result = mysqli_query($conn, $sql);
			$row = mysqli_fetch_assoc($result);
	
			echo "<div id='result'>\n";
			echo "	<a href='$url'><h4>$row[title]</h4></a>\n";
			echo "	<p id='result-url'>$url</p>\n";
			if ($row['description'] == "No description provided.") {
				echo "  <p>$row[paragraphs]</p>\n";
			} else {
				echo "  <p>$row[description]</p>\n";
			}
			echo "	<p>TF-IDF Score: $score</p>\n";
			echo "</div>\n";
	
			mysqli_free_result($result);
		}
	}
	
	// Free results set
	mysqli_free_result($result);
	
	// Close connection
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

		printSite($searchString);
	}
}

main();
?>
