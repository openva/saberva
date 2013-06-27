# Saberva

A free, open source tool to parse Virginia State Board of Elections campaign filings.

The name, "Saberva," combines "State Board of Elections" and "Virginia" in a single word, and is rooted in the Spanish "saber," meaning "to know."

## Usage

Run at the command line: `php parser.php`. It will retrieve a list of every campaign committee that has filed a report since January 1, 2012, and then retrieve a list of every report filed by that committee. The result is a large JSON file (several megabytes). It then iterates through that list of committees, creates a JSON file for each committee, retrieves every cited report, converts that report to JSON, and stores each report as a JSON file. (This produces thousands of JSON files.) Finally, it creates CSV versions of each JSON file, as well as `contributions.csv` and `expenses.csv` files that contain all contributions to and expenditures by all committees. 

Because the master committee list is demanding to assemble, `committees.json`, will not be refreshed unless a) 18 hours have elapsed since it was last built b) `--reload` is passed as a command-line argument (e.g., `php saberva.php --reload`) or c) `committees.json` does not exist.

### Options

Customizations can be made in `config.inc.php`.

`--reload` / `-r`: Force `committees.json`—the master committees list—to be rebuilt from the SBE's website, even if it is less than 18 hours old.

`--from-cache` / `-c`: Use the cached version of `committees.json`, no matter how old it is.

`--verbose` / `-v`: Display additional progress information.

`--progress-meter` / `-p`: Display a progress meter as `committees.json` is built.

`--help` / `-h`: Displays a list of parameters and usage examples.

Each switch must be provided individually (e.g., `php saberva.php -c -p`), rather than grouped (e.g., `php saberva -cp`).

## Resulting files

* committees.json
* committees.csv
* committees/*.json
* committees/*.csv
* report/*.json
* expenses.csv
* expenses/*.json
* expenses/*.csv
* contributions/*.csv
* contributions/*.json
* contributions.csv

## Data source update schedule

All of the information is pulled from [the Virginia State Board of Elections’ Campaign Finance Reports site](http://cfreports.sbe.virginia.gov/). Their site's data is updated once daily, at 5 PM EST. Although amendments can be filed on any day of the month, major changes occur as per the filing schedule ([e.g., the 2013 candidate committees](http://www.sbe.virginia.gov/Files/Forms/CampaignFinance/2013%20Candidate%20Reporting%20Deadlines.pdf)). There is no benefit to running this more than once per day, and for most purposes, it will only need to be run a few times a year (e.g., July 15 and January 15, for elected officials not on the ballot that November).

## LICENSE
Released under the MIT License.
