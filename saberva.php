<?php

/*
 * Set the timezone, not because we care, but because otherwise PHP throws warnings.
 */
date_default_timezone_set("America/New_York");

/*
 * Include the Simple HTML DOM Parser.
 */
include('simple_html_dom.php');

/*
 * Specify the name of the directory in which we'll store the report JSON files.
 */
$reports_dir = 'reports/';

/*
 * Specify the name of the directory in which we'll store the committee JSON files.
 */
$committees_dir = 'committees/';

/*
 * The URL for the website where these files will be provided, including a trailing slash.
 */
$website_prefix = 'http://openva.com/campaign-finance/';




/**
 * Campaign finance reports parser
 *
 * A collection of tools to gather and normalize campaign finance reports from the Virginia State
 * Board of Elections.
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
		$this->url = 'http://cfreports.sbe.virginia.gov/Committee/Index/' . $this->committee_id;
		
		/*
		 * Retrieve the HTML from $this->url.
		 */
		$html = $this->fetch_html();
		 
		/*
		 * Run the HTML through Simple HTML Dom.
		 */
		$html = str_get_html($html);
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
	
	
	/**
	 * Retrieve the HTML for a given URL.
	 */
	function fetch_html()
	{
		
		if (!isset($this->url))
		{
			return FALSE;
		}
		
		$curl = curl_init(); 
		curl_setopt($curl, CURLOPT_URL, $this->url);  
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);  
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);  
		$html = curl_exec($curl);
		curl_close($curl);
		
		return $html;
		
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
 * Get any command-line arguments.
 */
$options = array();
if ( isset($argv) && (count($argv) > 1) )
{
	if ($argv[1] == 'reload')
	{
		$options['reload'] = TRUE;
	}
}

/*
 * If we don't already have a saved copy of the committee data, then fetch it anew. (This is a time-
 * consuming process, involving scraping north of 1,200 pages, so we don't want to do this unless we
 * have to.)
 */
if ( !file_exists('committees.json') || ($options['reload'] === TRUE) )
{

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
	
	echo 'Iterating through ' . number_format($page->RecordCount) . ' records.' . PHP_EOL;
	
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
			 * Provide a URL for the JSON file where detailed information about this committee can
			 * be found.
			 */
			$committees->$j->api_url = $website_prefix . 'committees/' . $committee->CommitteeCode
				. '.json';
			
			/*
			 * Iterate our committee counter.
			 */
			$j++;
			
		}
		
		/*
		 * Don't flood the SBE's server -- only issue one request per second. (Of course, each
		 * request is spawning ten child requests, one for each row in the list of committes, so
		 * this isn't as conservative as it sounds.)
		 */
		sleep(1);
		
	}
	
	/*
	 * Save the listing of all of the committees to a JSON file.
	 */
	file_put_contents('committees.json', json_encode($committees));
}

/*
 * If we have a copy of the committee data at committees.json already, then pull the data out of
 * there, instead.
 */
else
{
	$committees = file_get_contents('committees.json');
	if ($committees === FALSE)
	{
		die('Fatal error: Could not get committee data from committees.json.' . PHP_EOL);
	}
	
	$committees = json_decode($committees);
	if ($committees === FALSE)
	{
		die('Fatal error: Committee data cached in committees.json is comprised of invalid JSON.'
			. PHP_EOL);
	}
}

/*
 * Remove these files, since we're about to recreate them. (We append to these files in a loop. If
 * we don't delete them here, these files will simply get longer every time that we run this.)
 */
unlink('contributions.csv');
unlink('expenses.csv');

/*
 * Iterate through the list of the committees to retrieve their XML and store that XML as JSON.
 */
foreach ($committees AS $committee)
{
	
	/*
	 * Define the path of the file that will store this committee's JSON.
	 */
	$filename = $committees_dir . $committee->CommitteeCode . '.json';
	
	/*
	 * Save the JSON for this committee.
	 */
	file_put_contents($filename, json_encode($committee));
	
	echo $committee->CommitteeName . ': ' . $committee->CommitteeCode . ' saved to ' . $filename . PHP_EOL;
	

	foreach ($committee->Reports as $report)
	{
		
		/*
		 * Define the path of the file that will store this report's JSON.
		 */
		$filename = $reports_dir . $report->Id . '.json';
		
		/*
		 * If we already have a copy of this file, don't retrieve it again.
		 */
		if (file_exists($filename) === FALSE)
		{
		
			/*
			 * Save the remote XML to a string.
			 */
			$parser->url = $report->XmlUrl;
			$xml = $parser->fetch_html();
			if ($xml === FALSE)
			{
				echo $committee->CommitteeName . ': Report ' . $report->Id . ' could not be retrieved' . PHP_EOL;
				continue;
			}
			
			/*
			 * Convert this XML to JSON.
			 */
			$parser->xml = $xml;
			$result = $parser->xml_to_json();
			if ($result === FALSE)
			{
				echo $committee->CommitteeName . ': Report ' . $report->Id . ' skipped; invalid XML' . PHP_EOL;
				continue;
			}
					
			/*
			 * Save a copy of the JSON to a file.
			 */
			file_put_contents($filename, $parser->report_json);
			
			/*
			 * Save this variable to be used later.
			 */
			$json = $parser->report_json;
			
		}
		
		/*
		 * If we already have a copy of this file, then simply reopen it.
		 */
		else
		{
			$json = file_get_contents($filename);
		}
		
		/*
		 * Save the report's contributions and expenses to their own objects.
		 */
		$tmp = json_decode($json);
		$contributions = $tmp->ScheduleA->LiA;
		$expenses = $tmp->ScheduleD->LiD;
		unset($tmp);

		/*
		 * Iterate through every individual contribution and and save it to a pair of CSV files.
		 */
		$fp_committee = fopen('contributions/' . $committee->CommitteeCode . '.csv', 'w');
		$fp_all = fopen('contributions.csv', 'a');
		if (count($contributions) > 0)
		{
			$headers = array('committee_code', 'report_id', 'individual', 'prefix', 'name_first',
				'name_middle', 'name_last', 'address_1', 'address_2', 'address_city',
				'address_state', 'address_zip', 'employer', 'occupation', 'employment_place',
				'date', 'amount', 'cumulative_amount');
			fputcsv($fp_committee, $headers);
			
			/*
			 * Create an array to store this data as JSON, in addition to CSV.
			 */
			$json = array();
			
			foreach ($contributions as $contribution)
			{
			
				$record = array
					(
						'committee_code' => $committee->CommitteeCode,
						'report_id' => $report->Id,
						'individual' => $contribution->Contributor->{@attributes}->IsIndividual,
						'prefix' => $contribution->Contributor->Prefix,
						'name_first' => $contribution->Contributor->FirstName,
						'name_middle' => $contribution->Contributor->MiddleName,
						'name_last' => $contribution->Contributor->LastName,
						'address_1' => $contribution->Contributor->Address->Line1,
						'address_2' => $contribution->Contributor->Address->Line2,
						'address_city' => $contribution->Contributor->Address->City,
						'address_state' => $contribution->Contributor->Address->State,
						'address_zip' => $contribution->Contributor->Address->ZipCode,
						'employer' => $contribution->Contributor->NameOfEmployer,
						'occupation' => $contribution->Contributor->OccupationOrTypeOfBusiness,
						'employment_place' => $contribution->Contributor->PrimaryCityAndStateOfEmploymentOrBusiness,
						'date' => $contribution->TransactionDate,
						'amount' => $contribution->Amount,
						'cumulative_amount' => $contribution->TotalToDate
					);
					
				fputcsv($fp_committee, $record);
				fputcsv($fp_all, $record);
				
				/*
				 * Append this record to our JSON array.
				 */
				$json[] = $record;
				
			}
		}
		fclose($fp_committee);
		fclose($fp_all);
		
		/*
		 * Turn the JSON array into actual JSON.
		 */
		$json = json_encode($json);
		file_put_contents('contributions/' . $committee->CommitteeCode . '.json', $json);
		
		/*
		 * Iterate through every individual expenses and and save it to a pair of CSV files.
		 */
		$fp_committee = fopen('expenses/' . $committee->CommitteeCode . '.csv', 'w');
		$fp_all = fopen('expenses.csv', 'a');
		if (count($expenses) > 0)
		{
			$headers = array('committee_code', 'report_id', 'individual', 'prefix', 'name_first',
				'name_middle', 'name_last', 'address_1', 'address_2', 'address_city',
				'address_state', 'address_zip', 'date', 'amount', 'authorized_by', 'purchased');
			fputcsv($fp_committee, $headers);
			
			/*
			 * Create an array to store this data as JSON, in addition to CSV.
			 */
			$json = array();
			
			foreach($expenses as $expense)
			{

				$record = array
					(
						'committee_code' => $committee->CommitteeCode,
						'report_id' => $report->Id,
						'individual' => $expense->Payee->{@attributes}->IsIndividual,
						'prefix' => $expense->Payee->Prefix,
						'name_first' => $expense->Payee->FirstName,
						'name_middle' => $expense->Payee->MiddleName,
						'name_last' => $expense->Payee->LastName,
						'address_1' => $expense->Payee->Address->Line1,
						'address_2' => $expense->Payee->Address->Line2,
						'address_city' => $expense->Payee->Address->City,
						'address_state' => $expense->Payee->Address->State,
						'address_zip' => $expense->Payee->Address->ZipCode,
						'date' => $expense->TransactionDate,
						'amount' => $expense->Amount,
						'authorized_by' => $expense->AuthorizingName,
						'purchased' => $expense->ItemOrService
					);
				
				fputcsv($fp_committee, $record);
				fputcsv($fp_all, $record);
				
				/*
				 * Append this record to our JSON array.
				 */
				$json[] = $record;
				
			}
		}
		
		fclose($fp_committee);
		fclose($fp_all);
		
		/*
		 * Turn the JSON array into actual JSON.
		 */
		$json = json_encode($json);
		file_put_contents('expenses/' . $committee->CommitteeCode . '.json', $json);
		
	}
	
}


/**
 * Create a CSV file to provide basic data about each committee.
 */
 
/*
 * Define the location of our output file.
 */
$fp = fopen('committees.csv', 'w');
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
	fclose($fp);
	
}
