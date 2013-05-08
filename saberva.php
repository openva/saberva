<?php

/**
 * TO DO
 * -When walking through the reports page, this will also include the Large Contributions table
 *  (I assume). The solution is probably to parse only the first table on the page.
 */

/*
 * Set the timezone, not because we care, but because otherwise PHP throws warnings.
 */
date_default_timezone_set("America/New_York");

/*
 * Include the Simple HTML DOM Parser.
 */
include('simple_html_dom.php');


/*
 * Fetch a single page of overview data about committees -- just one 10-item list from
 * http://cfreports.sbe.virginia.gov/.
 */
function fetch_page($page)
{
	
	global $ch;
	
	if (empty($page))
	{
		return FALSE;
	}
	
	/*
	 * Cast the page number as a string.
	 */
	$page = (string) $page;
	
	/*
	 * Specify the page number.
	 */
	curl_setopt($ch, CURLOPT_POSTFIELDS, array('page' => $page));
	
	/*
	 * Retrieve the JSON at this page number.
	 */
	$json = curl_exec($ch);
	
	if ($json === FALSE)
	{
		echo curl_error($ch) . PHP_EOL;
	}

	return $json;
}


/*
 * Fetch a list of all reports for a single committee.
 */
function fetch_reports($committee_id)
{
	
	if (empty($committee_id))
	{
		return FALSE;
	}
	
	/*
	 * Establish the URL at which the list is found.
	 */
	$url = 'http://cfreports.sbe.virginia.gov/Committee/Index/' . $committee_id;
	
	/*
	 * Retrieve the HTML.
	 */
	$html = file_get_html($url);
	if ($html === FALSE)
	{
		echo curl_error($ch2) . PHP_EOL;
		return FALSE;
	}
	
	/*
	 * Create an object to store the reports.
	 */
	$reports = new stdClass();
	
	/*
	 * Initialize the counter for each report.
	 */
	$i=0;
	
	/*
	 * Iterate through every table row with the class of "report."
	 */
	foreach ($html->find('tr.report') as $row)
	{
		
		$report->Period = $row->find('td', 0)->plaintext;
		$report->Amendment = $row->find('td', 1)->plaintext;
		$report->DateFiled = $row->find('td', 2)->plaintext;
		$report->Contributions = $row->find('td', 3)->plaintext;
		$report->EndingBalance = $row->find('td', 4)->plaintext;
		$report->Url = $row->find('td', 5)->find('a', 0)->href;		
		
		/*
		 * Iterate through every field, trim them down, and replace multiple spaces with single spaces.
		 */
		foreach ($report as &$tmp)
		{
			$tmp = trim($tmp);
			$tmp = preg_replace('/([\s]{2,})/', ' ', $tmp);
		}
		
		/*
		 * Without this line, when $tmp = explode() is run below, it modifies the last element of
		 * $report, bizarrely. (This is in PHP 5.3.15.)
		 */
		unset($tmp);
		
		/*
		 * Change the filing date into YYYY-MM-DD.
		 */
		$report->DateFiled = date('Y-m-d', strtotime($report->DateFiled));
	
		/*
		 * Extract the ID from the report's URL.
		 */
		$tmp = explode('/', $report->Url);
		$report->Id = $tmp[3];
		
		/*
		 * Add the domain name to the URL.
		 */
		$report->Url = 'http://cfreports.sbe.virginia.gov' . $report->Url;
		
		/*
		 * Turn the period field (e.g., "01/01/2012 to 03/31/2012") into a pair of dates.
		 */
		$tmp = explode(' ', $report->Period);
		$report->PeriodStart = date('Y-m-d', strtotime($tmp[0]));
		$report->PeriodEnd = date('Y-m-d', strtotime($tmp[2]));
		unset($report->Period);
		
		/*
		 * Add the URLs for the XML and PDF files.
		 */
		$report->XmlUrl = 'http://cfreports.sbe.virginia.gov/Report/ReportXML/' . $report->Id;
		$report->PdfUrl = 'http://cfreports.sbe.virginia.gov/Report/ReportPDF/' . $report->Id;
		
		/*
		 * If the amendment field is empty, then remove the element.
		 */
		if (empty($report->Amendment))
		{
			unset($report->Amendment);
		}
		
		/*
		 * Append this report to our collection of reports.
		 */
		$reports->$i = $report;
		
		unset($report);
		
		$i++;
		
	}
	
	return $reports;
	
}


/*
 * Initialize our cURL session.
 */
$ch = curl_init();
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
curl_setopt($ch, CURLOPT_URL, 'http://cfreports.sbe.virginia.gov/Home/SearchCommittees');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$allowed_protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
curl_setopt($ch, CURLOPT_PROTOCOLS, $allowed_protocols);
curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $allowed_protocols & ~(CURLPROTO_FILE | CURLPROTO_SCP));
curl_setopt($ch, CURLOPT_POST, 1);

/*
 * Fetch the first page, simply to determine how many pages to retreive.
 */
$page = fetch_page(1);
if ($page === FALSE)
{
	die('Could not retrieve first page.');
}
$page = json_decode($page);
$last_page = ceil($page->RecordCount  / $page->PageSize);

echo 'Iterating through '.$page->RecordCount . ' records.' . PHP_EOL;

/*
 * Create a new, empty object to store all of this committee data.
 */
$committees = new stdClass();

/*
 * Iterate through every page of records.
 */
$j=0;
for ($i=1; $i<=$last_page; $i++)
{

	/*
	 * Retrieve a page of records. (Containing 10 committee records, at this writing.)
	 */
	$page = fetch_page($i);
	if ($page === FALSE)
	{
		break;
	}
	$page = json_decode($page);

	/*
	 * Iterate through each of the 10 committees on this page.
	 */
	foreach ($page->Committees as $committee)
	{
		/*
		 * Add the committee data to our $committees object.
		 */
		$committees->$j = $committee;
		
		/*
		 * Retrieve all of the reports for this committee.
		 */
		$committees->$j->Reports = fetch_reports($committee->AccountId);
		
		/*
		 * Iterate our committee counter.
		 */
		$j++;
		
		echo $committee->CommitteeName . PHP_EOL;
		
	}
	
	/*
	 * Don't flood the SBE's server -- only issue one request per second. (Of course, each request
	 * is spawning ten child requests, one for each row in the list of committes, so this isn't as
	 * conservative as it sounds.)
	 */
	sleep(1);
	
}

file_put_contents('committees.json', json_encode($committees));
