<?php

/**
 * Configuration file
 *
 * Settings to configure the functionality of Saberva
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		MIT
 * @version		0.1
 * @link		http://www.github.com/waldoj/saberva
 * @since		0.1
 *
 */

/*
 * Specify the name of the directory in which we'll store the report JSON files.
 */
define('REPORTS_DIR', 'reports/');

/*
 * Specify the name of the directory in which we'll store the committee JSON files.
 */
define('COMMITTEES_DIR', 'committees/');

/*
 * The URL for the website where these files will be provided, including a trailing slash.
 */
define('WEBSITE_PREFIX', 'http://openva.com/campaign-finance/');

/*
 * The age of the committees.json file that will result in its data automatically being rebuilt
 * via queries to the SBE's server, expressed in seconds.
 */
define('MAX_CACHE_AGE', '68000');

/*
 * Set the timezone, not because that's actually at issue here, but because otherwise PHP throws
 * warnings.
 */
date_default_timezone_set('America/New_York');
