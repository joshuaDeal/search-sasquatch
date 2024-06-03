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
	printResults($searchString);
	echo "	</body>\n";
	echo "</html>\n";
}

function printResults($searchString) {
	// Preform the search.
	$cmd = "../search.py -s '$searchString' -o 'json' -r 4 -c ../db_creds.gpg -k ../decryption_key.txt";
	$output = shell_exec($cmd);

	// Decode the json output.
	$data = json_decode($output, true);

	// Make sure we have the results key
	if (isset($data['results'])) {
		// Loop through the results and create HTML code for displaying the output
		foreach ($data['results'] as $result) {
			echo "<div id='result'>\n";
			echo "	<a href='" . $result['url'] . "'><h4>" . $result['title'] . "</h4></a>\n";
			echo "	<p id='result-url'>" . $result['url'] . "</p>\n";
			echo "  <p>" . $result['description'] . "</p>\n";
			echo "	<p>Score: " . $result['score'] . "</p>\n";
			echo "</div>\n";
		}
	} else {
		echo "No results found\n";
	}
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
		printSite($searchString, $creds);
	}
}

main();
?>
