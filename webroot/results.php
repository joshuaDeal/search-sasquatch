<?php

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
	echo "			<pre>\n";
	echo " ____                              _       _       ____                      _     \n";
	echo "/ ___|  __ _ ___  __ _ _   _  __ _| |_ ___| |__   / ___|  ___  __ _ _ __ ___| |__  \n";
	echo "\___ \ / _` / __|/ _` | | | |/ _` | __/ __| '_ \  \___ \ / _ \/ _` | '__/ __| '_ \ \n";
	echo " ___) | (_| \__ \ (_| | |_| | (_| | || (__| | | |  ___) |  __/ (_| | | | (__| | | |\n";
	echo "|____/ \__,_|___/\__, |\__,_|\__,_|\__\___|_| |_| |____/ \___|\__,_|_|  \___|_| |_|\n";
	echo "                    |_|                                                            \n";
	echo "			</pre>";
	echo "			<div>\n";
	echo "				<form id=\"form\" action=\"./results.php\" method=\"get\">\n";
	echo "					<input type=\"search\" name=\"q\" value='$searchString'>\n";
	echo "					<button>Search</button>\n";
	echo "					<div id='safe-search'>";
	echo "						<label for='safe-search'>Safe Search</label>";
	echo "						<input type='radio' name='safe' id='on' value='1' checked='checked'>";
	echo "						<label for='on'>On</label>";
	echo "						<input type='radio' name='safe' id='off' value='0'>";
	echo "						<label for='off'>Off</label>";
	echo "					</div>";
	echo "				</form>\n";
	echo "			</div>\n";
	echo "		</header>\n";
	// Start of results
	printResults($searchString);
	echo "		<footer>";
	echo "			<p><a href='https://github.com/joshuadeal/search-sasquatch'>GitHub</a></p>";
	echo "		</footer>";
	echo "	</body>\n";
	echo "</html>\n";
}

function printResults($searchString) {
	$resultsPerPage = 20;
	$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

	// Preform the search.
	$cmd = "/opt/search-sasquatch/search.py -s '$searchString' -o 'json' -r $resultsPerPage -p $currentPage -c /opt/search-sasquatch/db_creds.gpg -k /opt/search-sasquatch/decryption_key.txt";
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

		// Print pagination links.
		echo "<div id='pagination'>\n";
		if ($currentPage > 1) {
			echo "<a href='?q=$searchString&page=" . 1 . "'><button><<</button></a> ";
			echo "<a href='?q=$searchString&page=" . ($currentPage - 1) . "'><button>Previous</button></a> ";
		}
		if ($data['total_pages'] < 10) {
			for ($i = 1; $i <= $data['total_pages']; $i++) {
				if ($i != $currentPage) {
					echo "<a href='?q=$searchString&page=$i'><button>$i</button></a> ";
				} else {
					echo "<a href='?q=$searchString&page=$i'><button id='current-page'>$i</button></a> ";
				}
			}
		} else {
			for ($i = ($currentPage - 4); $i <= $currentPage + 4; $i++) {
				if($i > 0 && $i <= $data['total_pages']) {
					if ($i != $currentPage) {
						echo "<a href='?q=$searchString&page=$i'><button>$i</button></a> ";
					} else {
						echo "<a href='?q=$searchString&page=$i'><button id='current-page'>$i</button></a> ";
					}
				}
			}
		}
		if ($currentPage < $data['total_pages']) {
			echo "<a href='?q=$searchString&page=" . ($currentPage + 1) . "'><button>Next</button></a>";
			echo "<a href='?q=$searchString&page=" . $data['total_pages'] . "'><button>>></button></a> ";
		}
		echo "</div>\n";
	} else {
		echo "No results found\n";
		echo $output;
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
