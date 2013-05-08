# Saberva

A collection of free, open source tools to parse Virginia State Board of Elections campaign filings.

The name, "Saberva," combines "State Board of Elections" and "Virginia" in a single word, and is rooted in the Spanish "saber," meaning "to know."

## saberva.php

Run at the command line: `php saberva.php`. It will retrieve a list of every campaign committee that has filed a report since January 1, 2012, and then retrieve a list of every report filed by that committee. The result is a large JSON file (several megabytes). Note that this does not parse the report XML files—it only gathers their URLs.

## LICENSE
Released under the MIT License.

