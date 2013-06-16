<?php

/**
 * Saberva parser
 *
 * The executable that produces Virginia campaign finance data.
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
 * Include the Saberva class.
 */
require('class.Saberva.inc.php');

/*
 * Include the Simple HTML DOM Parser.
 */
include('class.simple_html_dom.inc.php');

/*
 * Include the config file.
 */
include('config.inc.php');


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
 * If our committees.json file is older than our maximum cache age, then we want to reload
 * it from the SBE's website.
 */
if ( (time - filemtime('committees.json')) > MAX_CACHE_AGE)
{
	$options['reload'] = TRUE;
}

/*
 * Get any command-line arguments. This override any automatically set defaults.
 */
$options = array();
$options['verbosity'] = 3;
$options['progress'] = FALSE;
if ( isset($argv) && (count($argv) > 1) )
{
	if (in_array('--reload', $argv))
	{
		$options['reload'] = TRUE;
	}
	if (in_array('--from-cache', $argv))
	{
		$options['reload'] = FALSE;
	}
	if ( in_array('--verbose', $argv) || in_array('-v', $argv) )
	{
		$options['verbosity'] = 10;
	}
	if ( in_array('--progress-meter', $argv) || in_array('-p', $argv) )
	{
		$options['progress_meter'] = TRUE;
		$options['verbosity'] = 1;
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
		die('Could not retrieve first page.' . PHP_EOL);
	}
	$page = json_decode($page);
	$last_page = ceil($page->RecordCount  / $page->PageSize);
	$total_records = $page->RecordCount;
	
	if ($options['verbosity'] >= 1)
	{
		echo 'Iterating through ' . number_format($total_records) . ' records.' . PHP_EOL;
	}
	
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
				if ($options['verbosity'] >= 5)
				{
					echo $committee->CommitteeName . ' reports retrieved.' . PHP_EOL;
				}
				
				/*
				 * Provide an API URL for each report.
				 */
				foreach ($committees->$j->Reports as $report)
				{
					$report->api_url = WEBSITE_PREFIX . REPORTS_DIR . $report->Id . '.json';
				}
			}
			else
			{
				if ($options['verbosity'] >= 1)
				{
					echo $committee->CommitteeName . ' retrieval failed' . PHP_EOL;
				}
			}
			
			/*
			 * Provide a URL for the JSON file where detailed information about this committee can
			 * be found.
			 */
			$committees->$j->api_url = WEBSITE_PREFIX . 'committees/' . $committee->CommitteeCode
				. '.json';
			
			/*
			 * Iterate our committee counter.
			 */
			$j++;
			
		}
		
		/*
		 * Display the progress of downloading the committee records.
		 */
		if ($options['progress_meter'] === TRUE)
		{
			
			$display_cols = 80;
			
			/*
			 * Calculate the percentage of completion. This is expressed as a whole number (e.g.,
			 * 50), rather than as a decimal (e.g., 0.5).
			 */
			$percent = round($i / ceil($total_records / count($page->Committees)) * (($display_cols - 6) / 100) * 100);
			
			/*
			 * Clear out the entire line and update it with a new graph, on each iteration. 
			 */
			echo str_repeat(chr(8), $display_cols)
				. '['
				. str_repeat('*', $percent)
				. str_repeat(' ', $display_cols - $percent - 6)
				. '] '
				. str_pad($percent, 2, '0', STR_PAD_LEFT) . '%';
		}
		
		/*
		 * Don't flood the SBE's server -- only issue one request per second. (Of course, each
		 * request is spawning ten child requests, one for each row in the list of committes, so
		 * this isn't as conservative as it sounds.)
		 */
		sleep(1);
		
	}
	
	if ($options['progress_meter'] === TRUE)
	{
		echo PHP_EOL . PHP_EOL;
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
	$filename = COMMITTEES_DIR . $committee->CommitteeCode . '.json';
	
	/*
	 * Save the JSON for this committee.
	 */
	file_put_contents($filename, json_encode($committee));
	
	if ($options['verbosity'] >= 5)
	{
		echo $committee->CommitteeName . ': ' . $committee->CommitteeCode . ' saved to '
			. $filename . PHP_EOL;
	}
	
	foreach ($committee->Reports as $report)
	{
		
		/*
		 * Define the path of the file that will store this report's JSON.
		 */
		$filename = REPORTS_DIR . $report->Id . '.json';
		
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
				if ($options['verbosity'] >= 3)
				{
					echo $committee->CommitteeName . ': Report ' . $report->Id . ' could not be retrieved' . PHP_EOL;
				}
				continue;
			}
			
			/*
			 * Convert this XML to JSON.
			 */
			$parser->xml = $xml;
			$result = $parser->xml_to_json();
			if ($result === FALSE)
			{
				if ($options['verbosity'] >= 3)
				{
					echo $committee->CommitteeName . ': Report ' . $report->Id . ' skipped; invalid XML' . PHP_EOL;
				}
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
		 * Iterate through every individual expense and and save it to a pair of CSV files.
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
	if ($options['verbosity'] >= 1)
	{
		echo 'Could not create committees.csv file to store committee metadata' . PHP_EOL;
	}
}

else
{

	if ($options['verbosity'] >= 2)
	{
		echo 'Stored metadata about each committee in committees.csv' . PHP_EOL;
	}
	
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
