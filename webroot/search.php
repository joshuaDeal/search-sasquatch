<?php

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
	echo "		<p>You have searched for \"" . $searchString . "\".</p>\n";
	echo "	</body>\n";
	echo "</html>\n";
}

function main() {
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
