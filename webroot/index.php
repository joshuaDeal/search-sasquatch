<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Search Sasquatch!</title>
		<?php
			if ($_GET['style'] == 'solarized') {
				echo "<link rel='stylesheet' href='style2.css'>\n";
			} else {
				echo "<link rel='stylesheet' href='style.css'>\n";
			}
		?>
		<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
		<link rel="search" type="application/opensearchdescription+xml" title="Search Sasquatch" href="./opensearch.xml">
		<meta name="description" content="An internet search engine written mostly in python. Currently TF-IDF based.">
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, inital-scale=1">
	</head>
	<body>
		<header>
			<pre>
 ____                      _       ____                              _       _     
/ ___|  ___  __ _ _ __ ___| |__   / ___|  __ _ ___  __ _ _   _  __ _| |_ ___| |__  
\___ \ / _ \/ _` | '__/ __| '_ \  \___ \ / _` / __|/ _` | | | |/ _` | __/ __| '_ \ 
 ___) |  __/ (_| | | | (__| | | |  ___) | (_| \__ \ (_| | |_| | (_| | || (__| | | |
|____/ \___|\__,_|_|  \___|_| |_| |____/ \__,_|___/\__, |\__,_|\__,_|\__\___|_| |_|
                                                      |_|                          
			</pre>
			<div>
				<form id="form" action="./results.php" method="get">
					<input type="search" name="q" placeholder="Search...">
					<button>Search</button>
					<div id='safe-search'>
						<label for='safe-search'>Safe Search</label>
						<input type="radio" name="safe" id='on' value='1' checked='checked'>
						<label for='on'>On</label>
						<input type="radio" name="safe" id='off' value='0'>
						<label for='off'>Off</label>
						<?php
							echo "<input type='hidden' name='style' value='" . $_GET['style'] . "'>\n";
						?>
					</div>
				</form>	
			</div>
		</header>
		<footer id='index'>
			<p><a href="https://github.com/joshuadeal/search-sasquatch">GitHub</a></p>
			<form action="index.php" method="get">
				<select name='style' id='style'>
					<option value="gruvbox">Gruvbox</option>
					<option value="solarized">Solarized</option>
				</select>
				<input type="submit" value="Submit">
			</form>
		</footer>
	</body>
</html>
