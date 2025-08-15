<?php
// Start session.
session_start();

// Initialize the resultCache.
if (!isset($_SESSION['resultCache'])) {
	$_SESSION['resultCache'] = [];
}

// Outputs html for webpage.
function printSite($searchString, $creds) {
	echo "<!DOCTYPE html>\n";
	echo "<html lang=\"en\">\n";
	echo "	<head>\n";
	echo "		<title>" . $searchString . " - Search Sasquatch!</title>\n";
	if ($_GET['style'] == 'solarized') {
		echo "		<link rel=\"stylesheet\" href=\"style2.css\">\n";
	} elseif ($_GET['style'] == 'ubuntu') {
		echo "		<link rel=\"stylesheet\" href=\"style3.css\">\n";
	} else {
		echo "		<link rel=\"stylesheet\" href=\"style.css\">\n";
	}
	echo "		<link rel=\"shortcut icon\" href=\"favicon.ico\" type=\"image/x-icon\">\n";
	echo "		<meta name=\"description\" content=\"Search results for " . $searchString . "\">\n";
	echo "		<meta charset=\"utf-8\">\n";
	echo "		<meta name=\"viewport\" content=\"width=device-width, inital-scale=1\">\n";
	echo "	</head>\n";
	echo "	<body>\n";
	echo "		<header id=\"search\">\n";
	echo "			<pre>\n";
	echo " ____                      _       ____                              _       _     \n";
	echo "/ ___|  ___  __ _ _ __ ___| |__   / ___|  __ _ ___  __ _ _   _  __ _| |_ ___| |__  \n";
	echo "\___ \ / _ \/ _` | '__/ __| '_ \  \___ \ / _` / __|/ _` | | | |/ _` | __/ __| '_ \ \n";
	echo " ___) |  __/ (_| | | | (__| | | |  ___) | (_| \__ \ (_| | |_| | (_| | || (__| | | |\n";
	echo "|____/ \___|\__,_|_|  \___|_| |_| |____/ \__,_|___/\__, |\__,_|\__,_|\__\___|_| |_|\n";
	echo "                                                      |_|                          \n";
	echo "			</pre>\n";
	echo "			<div>\n";
	echo "				<form id=\"form\" action=\"./results.php\" method=\"get\">\n";
	echo "					<input type=\"search\" name=\"q\" value='$searchString'>\n";
	echo "					<button>Search</button>\n";
	echo "					<div id='safe-search'>\n";
	echo "						<label for='safe-search'>Safe Search</label>\n";
	// Remeber if safe search was enabled or disabled.
	if ($_GET['safe'] == 1) {
		echo "						<input type='radio' name='safe' id='on' value='1' checked='checked'>\n";
		echo "						<label for='on'>On</label>\n";
		echo "						<input type='radio' name='safe' id='off' value='0'>\n";
		echo "						<label for='off'>Off</label>\n";
	} else {
		echo "						<input type='radio' name='safe' id='on' value='1'>\n";
		echo "						<label for='on'>On</label>\n";
		echo "						<input type='radio' name='safe' id='off' value='0' checked='checked'>\n";
		echo "						<label for='off'>Off</label>\n";
	}
	echo "					</div>\n";
	echo "					<div id='search-mode'>\n";
	// Remeber if we are in web or image mode.
	if ($_GET['mode'] != "image") {
		echo "						<label for='web-search'>\n";
		echo "							<input type ='radio' name='mode' id='web-search' value='web' checked='checked'>\n";
		echo "							Web Search\n";
		echo "							</label>\n";
		echo "						<label for='image-search'>\n";
		echo "							<input type ='radio' name='mode' id='image-search' value='image'>\n";
		echo "							Image Search\n";
		echo "							</label>\n";
	} elseif ($_GET['mode'] == "image") {
		echo "						<label for='web-search'>\n";
		echo "							<input type ='radio' name='mode' id='web-search' value='web'>\n";
		echo "							Web Search\n";
		echo "							</label>\n";
		echo "						<label for='image-search'>\n";
		echo "							<input type ='radio' name='mode' id='image-search' value='image' checked='checked'>\n";
		echo "							Image Search\n";
		echo "							</label>\n";
	}
	echo "					</div>\n";
	// Remeber the style sheet.
	echo "							<input type='hidden' name='style' value='" . $_GET['style'] . "'>\n";
	echo "				</form>\n";
	echo "			</div>\n";
	echo "		</header>\n";
	// Start of results
	printResults($searchString);
	echo "		<footer>\n";
	echo "			<p><a href='https://github.com/joshuadeal/search-sasquatch'>GitHub</a></p>\n";
	echo "			<form action='results.php' method='get'>\n";
	echo "				<select name='style' id='style'>\n";
	echo "					<option value='gruvbox'>Gruvbox</option>\n";
	echo "					<option value='solarized'>Solarized</option>\n";
	echo "					<option value='ubuntu'>Ubuntu</option>\n";
	echo "				</select>\n";
	echo "				<input type='submit' value='Submit'>\n";
	echo "				<input type='hidden' name='q' value='" . $_GET['q'] . "'>\n";
	echo "				<input type='hidden' name='page' value='" . $_GET['page'] . "'>\n";
	echo "				<input type='hidden' name='safe' value='" . $_GET['safe'] . "'>\n";
	echo "			</form>\n";
	echo "		</footer>\n";
	echo "	</body>\n";
	echo "</html>\n";
}

function printResults($searchString) {
	$resultsPerPage = 20;
	$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

	// Check cache for current search.
	if (isset($_SESSION['resultCache'][$searchString][$_GET['safe']][$_GET['mode']])) {
		echo "This search is cached!";
		$data = $_SESSION['resultCache'][$searchString][$_GET['safe']][$_GET['mode']];
	} else {
		echo "This search is not cached!";

		// Preform the search.
		if ($_GET['safe'] == 1 and $_GET['mode'] == 'image') {
			$cmd = "/opt/search-sasquatch/search.py -i -s '$searchString' -o 'json' -r $resultsPerPage -p $currentPage -c /opt/search-sasquatch/db_creds.gpg -k /opt/search-sasquatch/decryption_key.txt";
		} elseif ($_GET['safe'] == 0 and $_GET['mode'] == 'image') {
			$cmd = "/opt/search-sasquatch/search.py -i -n -s '$searchString' -o 'json' -r $resultsPerPage -p $currentPage -c /opt/search-sasquatch/db_creds.gpg -k /opt/search-sasquatch/decryption_key.txt";
		} elseif ($_GET['safe'] == 1) {
			$cmd = "/opt/search-sasquatch/search.py -s '$searchString' -o 'json' -r $resultsPerPage -p $currentPage -c /opt/search-sasquatch/db_creds.gpg -k /opt/search-sasquatch/decryption_key.txt";
		} else if ($_GET['safe'] == 0){
			$cmd = "/opt/search-sasquatch/search.py -n -s '$searchString' -o 'json' -r $resultsPerPage -p $currentPage -c /opt/search-sasquatch/db_creds.gpg -k /opt/search-sasquatch/decryption_key.txt";
		}
	
		$output = shell_exec($cmd);
	
		// Decode the json output.
		$data = json_decode($output, true);

		// Cache result.
		$_SESSION['resultCache'][$searchString][$_GET['safe']][$_GET['mode']] = $data;
	}


	// Make sure we have the results key (web search mode)
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
		printPages($currentPage,$searchString,$data);
	// Output image results if in image mode.
	} elseif ($_GET['mode'] == 'image' and $data) {
		echo "<div id='result' style='text-align: center;'>\n";

		foreach ($data as $imageUrl => $sourceUrl){
			if ($imageUrl != "total_pages") {
				echo "	<a href='$sourceUrl'><img src='$imageUrl' loading='lazy' style='max-height: 400px; max-width: 400px; min-height: 100px; min-width: 100px;'></a>\n";
			}
		}

		echo "</div>\n";

		// Print pagination links.
		printPages($currentPage,$searchString,$data);
	} else {
		echo "No results found\n";
		echo $output;
	}
}


// Print pagination links.
function printPages($currentPage,$searchString,$data) {
	echo "<div id='pagination'>\n";
	if ($currentPage > 1) {
		echo "<a href='?q=$searchString&page=" . 1 . "&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button><<</button></a> ";
		echo "<a href='?q=$searchString&page=" . ($currentPage - 1) . "&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button>Previous</button></a> ";
	}
	if ($data['total_pages'] < 10) {
		for ($i = 1; $i <= $data['total_pages']; $i++) {
			if ($i != $currentPage) {
				echo "<a href='?q=$searchString&page=$i&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button>$i</button></a> ";
			} else {
				echo "<a href='?q=$searchString&page=$i&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button id='current-page'>$i</button></a> ";
			}
		}
	} else {
		for ($i = ($currentPage - 4); $i <= $currentPage + 4; $i++) {
			if($i > 0 && $i <= $data['total_pages']) {
				if ($i != $currentPage) {
					echo "<a href='?q=$searchString&page=$i&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button>$i</button></a> ";
				} else {
					echo "<a href='?q=$searchString&page=$i&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button id='current-page'>$i</button></a> ";
				}
			}
		}
	}
	if ($currentPage < $data['total_pages']) {
		echo "<a href='?q=$searchString&page=" . ($currentPage + 1) . "&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button>Next</button></a>";
		echo "<a href='?q=$searchString&page=" . $data['total_pages'] . "&safe=" . $_GET['safe'] . "&style=" . $_GET['style'] . "&mode=" . $_GET['mode'] . "'><button>>></button></a> ";
	}
	echo "</div>\n";
}

function main() {
	// Make sure we have received a query parameter 'q' with the search terms.
	if (isset($_GET["q"])) {
		$searchString = filter_var($_GET["q"], FILTER_SANITIZE_STRING);

		if (empty($searchString)) {
			$url = "http://search.example.com/?style=" . $_GET['style'];

			header("Location: $url");
			exit;
		}
		// Make sure that access to these files is restricted. They should only be accessed by administrators.
		printSite($searchString, $creds);
	}
}

main();
?>
