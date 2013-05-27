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
 * Specify the name of the directory in which we'll store the resulting reports.
 */
$output_dir = 'reports/';


/**
 * 
 */
class SaberVA
{

	/**
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
		
	} // end fetch_page()
	
	
	/*
	 * Fetch a list of all reports for a single committee.
	 */
	function fetch_reports()
	{
		
		if (empty($this->committee_id))
		{
			return FALSE;
		}
		
		/*
		 * Establish the URL at which the list is found.
		 */
		$url = 'http://cfreports.sbe.virginia.gov/Committee/Index/' . $this->committee_id;
		
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
		$this->reports = new stdClass();
		
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
			$this->reports->$i = $report;
			
			unset($report);
			
			$i++;
			
		}
		
		return TRUE;
		
	} // end fetch_reports()
	
	
	/**
	 * Convert the SBE's XML into JSON.
	 */
	function xml_to_json()
	{
	
		if (!isset($this->xml))
		{
			return FALSE;
		}
	
		$report = simplexml_load_string($this->xml);
		if ($report === FALSE)
		{
			return FALSE;
		}
		
		$this->report_json = json_encode($report);
		if ($this->report_json === FALSE)
		{
			return FALSE;
		}
		
		return TRUE;
		
	}
	
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
 * Create a new instance of the parser
 */
$parser = new SaberVA;

/*
 * Fetch the first page, simply to determine how many pages to retreive.
 */
$page = $parser->fetch_page(1);
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
	$page = $parser->fetch_page($i);
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
		$parser->committee_id = $committee->AccountId;
		$result = $parser->fetch_reports();
		if ($result !== FALSE)
		{
			$committees->$j->Reports = $parser->reports;
			echo $committee->CommitteeName . PHP_EOL;
		}
		else
		{
			echo $committee->CommitteeName . ' retrieval failed' . PHP_EOL;
		}
		
		/*
		 * Iterate our committee counter.
		 */
		$j++;
		
	}
	
	/*
	 * Don't flood the SBE's server -- only issue one request per second. (Of course, each request
	 * is spawning ten child requests, one for each row in the list of committes, so this isn't as
	 * conservative as it sounds.)
	 */
	sleep(1);
	
}

/*
 * Save the listing of all of the committees to a JSON file.
 */
file_put_contents($output_dir . 'committees.json', json_encode($committees));

/*
 * Iterate through the list of the committees to retrieve their XML and store that XML as JSON.
 */
foreach ($committees AS $committee)
{

	foreach ($committee->Reports as $report)
	{
		
		/*
		 * Define the path of the file that will store this report's JSON.
		 */
		$filename = $output_dir . $report->Id . '.json';
		
		/*
		 * If we already have a copy of this file, don't retrieve it again.
		 */
		if (file_exists($filename) === TRUE)
		{
			continue;
		}
		
		/*
		 * Save the remote XML to a string.
		 */
		$xml = file_get_contents($report->XmlUrl);
		if ($xml === FALSE)
		{
			echo $committee->CommitteeName . ': Report ' . $report->report_Id . ' could not be retrieved' . PHP_EOL;
			continue;
		}
		
		/*
		 * Convert this XML to JSON.
		 */
		$parser->xml = $xml;
		$result = $parser->xml_to_json();
		if ($result === FALSE)
		{
			echo $committee->CommitteeName . ': Report ' . $report->report_Id . ' skipped; invalid XML' . PHP_EOL;
			continue;
		}
		
		/*
		 * Save a copy of the JSON to a file.
		 */
		file_put_contents($filename, $parser->report_json);
		
		echo $committee->CommitteeName . ': Report ' . $report->mId . PHP_EOL;

/**
 * Create a CSV file to provide basic data about each committee.
 */
 
/*
 * Define the location of our output file.
 */
$fp = fopen($output_dir . 'committees.csv', 'w');
if ($fp === FALSE)
{
	echo 'Could not create committees.csv file to store committee metadata' . PHP_EOL;
}

else
{
	
	echo 'Stored metadata about each committee in committees.csv' . PHP_EOL;
	
	/*
	 * Create our CSV column headers.
	 */
	$csv = array('Code', 'Name', 'Candidate', 'Type', 'Balance', 'Date');
	fputcsv($fp, $csv);
	unset($csv);
	
	/*
	 * Iterate through all of the committees to generate and write a line of CSV for each of them.
	 */
	$csv = array();
	foreach ($committees as $committee)
	{
		$csv['code'] = $committee->CommitteeCode;
		$csv['name'] = $committee->CommitteeName;
		$csv['candidate'] = $committee->CandidateName;
		$csv['type'] = $committee->CommitteeType;
		$csv['balance'] = $committee->Reports->{0}->EndingBalance;
		$csv['date'] = $committee->Reports->{0}->PeriodEnd;
		
		fputcsv($fp, $csv);
		
		unset($csv);
		
	}
	
	/*
	 * Close our CSV file handle.
	 */
}
