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
 * Set the timezone, not because that's actually at issue here, but because otherwise PHP throws
 * warnings.
 */
date_default_timezone_set('America/New_York');
