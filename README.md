# search-sasquatch
An internet search engine written mostly in python. Currently TF-IDF based.

# Acknowledgments / Attributions
It should be noted that the file `naughty-words.txt` is sourced from an [external project](https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words) and is licensed under the terms of the [Creative Commons Attribution 4.0 International License](http://creativecommons.org/licenses/by/4.0/). This is unlike the rest of this project, which is licensed under the terms of [GNU General Public License V3.0](https://www.gnu.org/licenses/gpl-3.0.html#license-text).

## init.sh
Preforms initial tasks for setting up an instance. Use the `--user` option to specify a privileged MariaDB/MySQL user.

## purge.sh
Purges old data from database after it reaches a specific age. Should be run as a cron job periodically.

## crawler.py
Webcrawler. Crawls the internet for new URLs and outputs its findings to a file called 'raw-urls.txt'. The `--sites` option can be used to pass a list of URLs separated by commas.

## extractor.py
Meta data extractor / web scraper. Extracts meta data from html and stores it in a MariaDB database. Uses the 'raw-urls.txt' file as input. The `--key` option must be used to provide the path to the key that decrypts db\_creds.gpg

## search.py
Preforms a TF-IDF based search against the database. Run `search.py --help` for more information.

## webroot/
Directory containing source files for website user interface / front end.

### results.php
Outputs a results page.
