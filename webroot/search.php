<?php

// Read MySql username and password from a file.
function getMySqlCreds($fileName, $keyFile) {
	$credentials = array();

	// Construct gpg command for decryption
	$gpgCommand = "sudo /sbin/gpg --decrypt --batch --passphrase-file $keyFile $fileName";

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
function printSite($searchString, $creds) {
	echo "<!DOCTYPE html>\n";
	echo "<html lang=\"en\">\n";
	echo "	<head>\n";
	echo "		<title>" . $searchString . " - Search Sasquatch!</title>\n";
	echo "		<link rel=\"stylesheet\" href=\"style.css\">\n";
	echo "		<link rel=\"shortcut icon\" href=\"favicon.ico\" type=\"image/x-icon\">\n";
	echo "		<meta name=\"description\" content=\"Search results for " . $searchString . "\">\n";
	echo "		<meta charset=\"utf-8\">\n";
	echo "		<meta name=\"viewport\" content=\"width=device-width, inital-scale=1\">\n";
	echo "	</head>\n";
	echo "	<body>\n";
	echo "		<header id=\"search\">\n";
	echo "			<pre>";
	echo " ____                              _       _       ____                      _     \n";
	echo "/ ___|  __ _ ___  __ _ _   _  __ _| |_ ___| |__   / ___|  ___  __ _ _ __ ___| |__  \n";
	echo "\___ \ / _` / __|/ _` | | | |/ _` | __/ __| '_ \  \___ \ / _ \/ _` | '__/ __| '_ \ \n";
	echo " ___) | (_| \__ \ (_| | |_| | (_| | || (__| | | |  ___) |  __/ (_| | | | (__| | | |\n";
	echo "|____/ \__,_|___/\__, |\__,_|\__,_|\__\___|_| |_| |____/ \___|\__,_|_|  \___|_| |_|\n";
	echo "                    |_|                                                            \n";
	echo "			</pre>";
	echo "			<div>\n";
	echo "				<form id=\"form\" action=\"./search.php\" method=\"get\">\n";
	echo "					<input type=\"search\" name=\"q\" value='$searchString'>\n";
	echo "					<button>Search</button>\n";
	echo "				</form>\n";
	echo "			</div>\n";
	echo "		</header>\n";
	// Start of results
	getResults($searchString, $creds);
	echo "	</body>\n";
	echo "</html>\n";
}

// Preforms TF-IDF search.
function getResults($searchString, $creds) {
	define("TITLE_POINTS", 6);
	define("HEADER_POINTS", 4);
	define("KEYWORD_POINTS", 5);
	define("AGE_POINTS", 5);
	$serverName = "localhost";
	$dbName = "sasquatch_index";
	$username = $creds['username'];
	$password = $creds['password'];
	$extraPoints = TITLE_POINTS + HEADER_POINTS + KEYWORD_POINTS + AGE_POINTS - 4;

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
		$searchTokens[] = strtolower($token);
	}
	
	// Calculate the TF for each token
	$searchTermFrequency = array_count_values($searchTokens);
	
	// Calculate the IDF for each token
	$idf = array();
	foreach ($searchTokens as $token) {
		$sql = "SELECT COUNT(DISTINCT id) AS document_count FROM sites WHERE LOWER(keywords) LIKE LOWER('%$token%') OR LOWER(title) LIKE LOWER('%$token%') OR LOWER(description) LIKE LOWER('%$token%') OR LOWER(headers) LIKE LOWER('%$token%') OR LOWER(paragraphs) LIKE LOWER('%$token%') OR LOWER(lists) LIKE LOWER('%$token%')";
		$result = mysqli_query($conn, $sql);
		$row = mysqli_fetch_assoc($result);
		$idf[$token] = $row['document_count'];
	}
	
	// Calculate the TF-IDF for each document
	$tfidf = array();
	$sql = "SELECT id, url, last_visited, keywords, title, description, headers, paragraphs, lists FROM sites";
	$result = mysqli_query($conn, $sql);
	while ($row = mysqli_fetch_assoc($result)) {
		$tfidfScore = 0;
		foreach ($searchTokens as $token) {
			$tf = substr_count(strtolower($row['keywords'] . ' ' . $row['title'] . ' ' . $row['description'] . ' ' . $row['headers'] . ' ' . $row['paragraphs'] . ' ' . $row['lists']), strtolower($token));
			$idfValue = $idf[strtolower($token)]; // Use IDF value calculated previously

			// Calculte TF-IDF score.
			$tfidfScore += $tf * log((mysqli_num_rows($result) + 1) / $idfValue);

			// Give extra points if the token appears in the title
			if (stripos(strtolower($row['title']), strtolower(trim($token))) !== false) {
				$tfidfScore += TITLE_POINTS; // Extra points
			}
	
			// Give extra points if the token appears in the header text. 
			if (stripos(strtolower($row['headers']), strtolower(trim($token))) !== false) {
				$tfidfScore += HEADER_POINTS; // Extra points
			}

			// Give extra points if the token appears in the keywords. 
			if (stripos(strtolower($row['keywords']), strtolower(trim($token))) !== false) {
				$tfidfScore += KEYWORD_POINTS; // Extra points
			}

			// Give extra points if the result age is young. 
			if ($row['last_visited'] !== false) {
				$timeStamp = strtotime(date('Y-m-d H:i:s'));
				$foundTime = strtotime($row['last_visited']);
				$timeDiff = $timeStamp - $foundTime;
				$days = $timeDiff / 86400;

				if ($days < 7) {
					$tfidfScore += AGE_POINTS;
				} elseif ($days < 14) {
					$tfidfScore += (AGE_POINTS / 2);
				} elseif ($days < 30) {
					$tfidfScore += (AGE_POINTS / 3);
				} elseif ($days < 365) {
					$tfidfScore += (AGE_POINTS / 5);
				}
			}
		}
		$tfidf[$row['url']] = $tfidfScore;
	}
	
	// Sort documents by TF-IDF
	arsort($tfidf);
	
	// Output the results
	foreach ($tfidf as $url => $score) {
		if ($score > $extraPoints) {
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
	// Make sure we have received a query parameter 'q' with the search terms.
	if (isset($_GET["q"])) {
		$searchString = filter_var($_GET["q"], FILTER_SANITIZE_STRING);

		if (empty($searchString)) {
			header('Location: http://search.example.com/');
			exit;
		}
		// Make sure that access to these files is restricted. They should only be accessed by administrators.
		$creds = getMySqlCreds('../db_creds.gpg', '../decryption_key.txt');
		printSite($searchString, $creds);
	}
}

main();
?>
