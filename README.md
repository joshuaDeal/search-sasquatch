# search-sasquatch
An internet search engine written mostly in python. Currently TF-IDF based.

## init.sh
Initializes a database for storing webpages and the metadata collected from them. Also, creates a user account for managing that database.

## purge.sh
Purges old data from database after it reaches a specific age. Should be run as a cron job periodically.

## crawler.py
Webcrawler. Crawls the internet for new urls and outputs its findings to a file called 'raw-urls.txt'.

## extractor.py
Meta data extractor / web scraper. Extracts meta data from html and stores it in a Mariadb database. Uses the 'raw-urls.txt' file as input.

## webroot/
Directory containing source files for website user interface / front end.

### search.php
Preforms a TF-IDF based search and outputs a results page.
