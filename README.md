# spaghetti-search
An internet search engine written mostly in python.

## init.sh
Initializes a database for storing webpages and the metadata collected from them. Also, creates a user account for managing that database.

## crawler.py
Webcrawler. Crawls the internet for new urls and outputs its findings to a file called 'raw-urls.txt'.

## extractor.py
Meta data extractor / web scraper. Extracts meta data from html and stores it in a Mariadb database. Uses the 'raw-urls.txt' file as input.
