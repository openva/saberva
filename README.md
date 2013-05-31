# Saberva

A collection of free, open source tools to parse Virginia State Board of Elections campaign filings.

The name, "Saberva," combines "State Board of Elections" and "Virginia" in a single word, and is rooted in the Spanish "saber," meaning "to know."

## saberva.php

Run at the command line: `php saberva.php`. It will retrieve a list of every campaign committee that has filed a report since January 1, 2012, and then retrieve a list of every report filed by that committee. The result is a large JSON file (several megabytes). It then iterates through that list of committees, creates a JSON file for each committee, retrieves every cited report, converts that report to JSON, and stores each report as a JSON file. (This produces thousands of JSON files.)

## Resulting files

* committees.json
* committees.csv
* committee/*.json
* report/*.json

## Data source update schedule

All of the information is pulled from [the Virginia State Board of Elections’ Campaign Finance Reports site](http://cfreports.sbe.virginia.gov/). Their site's data is updated once daily, at 5 PM EST. Although amendments can be filed on any day of the month, major changes occur as per the filing schedule ([e.g., the 2013 candidate committees](http://www.sbe.virginia.gov/Files/Forms/CampaignFinance/2013%20Candidate%20Reporting%20Deadlines.pdf)). There is no benefit to running this more than once per day, and for most purposes, it will only need to be run a few times a year (e.g., July 15 and January 15, for elected officials not on the ballot that November).

## LICENSE
Released under the MIT License.
