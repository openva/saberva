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
 * @link		http://www.github.com/openva/saberva
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
 * Include the mailing address normalizer.
 */
include('class.AddressStandardizationSolution.inc.php');

/*
 * Include the config file.
 */
require('config.inc.php');

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
if ( !file_exists('committees.json') || ( (time - filemtime('committees.json')) > MAX_CACHE_AGE) )
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

	if ( in_array('--reload', $argv) || in_array('-r', $argv) )
	{
		$options['reload'] = TRUE;
	}
	if ( in_array('--from-cache', $argv) || in_array('-c', $argv) )
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
	if ( in_array('--atomize', $argv) || in_array('-a', $argv) )
	{
		$options['atomize_json'] = TRUE;
	}
	if ( in_array('--help', $argv) || in_array('-h', $argv) )
	{
		echo 'Saberva: Parser for campaign finance data from the VA State Board of Elections.' . PHP_EOL . PHP_EOL;
		echo 'usage: php saberva.php [-rvphac]' . PHP_EOL . PHP_EOL;
		echo 'Arguments:' . PHP_EOL;
		echo "-c / --from-cache\tUse the cached version of committees.json, no matter \n"
			."                    \thow old it is." . PHP_EOL;
		echo "-h / --help\t\tDisplay this message." . PHP_EOL;
		echo "-p / --progress-meter\tDisplay a graph while building committees.json." . PHP_EOL;
		echo "-a / --atomize\t\tCreate individual JSON files for every contribution and"
			."                    \texpense." . PHP_EOL;
		echo "-r / --reload\t\tRebuild committees.json, regardless of its recency." . PHP_EOL;
		echo "-v / --verbose\t\tDisplay detailed output." . PHP_EOL;
		exit();
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
			$committees->$j->api_url = WEBSITE_PREFIX . COMMITTEES_DIR . $committee->CommitteeCode
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
			$graph_cols = 73;
			
			/*
			 * Calculate the percentage of completion. This is expressed as a whole number (e.g.,
			 * 50), rather than as a decimal (e.g., 0.5).
			 */
			$percent = round($i / ceil($total_records / count($page->Committees)) * ($graph_cols / 100) * 100);
			
			/*
			 * Clear out the entire line and update it with a new graph, on each iteration.
			 */
			echo str_repeat(chr(8), $display_cols)
				. '['
				. str_repeat('*', $percent * ($graph_cols / 100) )
				. str_repeat(' ', $graph_cols - ($percent * ($graph_cols / 100) ) )
				. '] '
				. str_pad($percent, 3, ' ', STR_PAD_LEFT) . '%';
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
if (file_exists('contributions.csv'))
{
	unlink('contributions.csv');
}
if (file_exists('expenses.csv'))
{
	unlink('expenses.csv');
}

/*
 * Iterate through the list of the committees to retrieve their XML and store that XML as JSON.
 */
if ($options['verbosity'] >= 5)
{
	echo number_format(count((array) $committees)) . ' committees' . PHP_EOL;
}

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
	
	if (count($committee->Reports) > 0)
	{
		
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
				$xml = $parser->fetch_content();
			
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
				if (filesize('contributions.csv') == 0)
				{
					fputcsv($fp_all, $headers);
				}
			
				/*
				 * Create an array to store this data as JSON, in addition to CSV.
				 */
				$json = array();
			
				$i=0;
			
				foreach ($contributions as $contribution)
				{
				
					/*
					 * If this is a blank record, skip it. This is a temporary shim, to deal with the
					 * inadvertent creation of blank records. See
					 * <https://github.com/openva/saberva/issues/19> for more.
					 */
					if (!isset($contribution->Amount))
					{
						continue;
					}
					
					/*
					 * Deal with empty strings being stored as objects. Per
					 * <https://github.com/openva/saberva/issues/21>.
					 */
					foreach ($contribution->Contributor as &$field)
					{
						if (is_object($field) && (count($field) == 0) )
						{
							$field = '';
						}
					}
					foreach ($contribution->Contributor->Address as &$field)
					{
						if (is_object($field) )
						{
							$field = '';
						}
					}

					$record = array
						('committee_code' => $committee->CommitteeCode,
						'report_id' => $report->Id,
						'individual' => (string) $contribution->Contributor->{@attributes}->IsIndividual,
						'prefix' => (string) $contribution->Contributor->Prefix,
						'name_first' => (string) $contribution->Contributor->FirstName,
						'name_middle' => (string) $contribution->Contributor->MiddleName,
						'name_last' => (string) $contribution->Contributor->LastName,
						'address_1' => (string) $contribution->Contributor->Address->Line1,
						'address_2' => (string) $contribution->Contributor->Address->Line2,
						'address_city' => (string) $contribution->Contributor->Address->City,
						'address_state' => (string) $contribution->Contributor->Address->State,
						'address_zip' => (string) $contribution->Contributor->Address->ZipCode,
						'employer' => (string) $contribution->Contributor->NameOfEmployer,
						'occupation' => (string) $contribution->Contributor->OccupationOrTypeOfBusiness,
						'employment_place' => (string) $contribution->Contributor->PrimaryCityAndStateOfEmploymentOrBusiness,
						'date' => (string) $contribution->TransactionDate,
						'amount' => (string) $contribution->Amount,
						'cumulative_amount' => (string) $contribution->TotalToDate);
					
					fputcsv($fp_committee, $record);
					fputcsv($fp_all, $record);
					
					/*
					 * Save this individual contribution as a JSON file.
					 */
					if ($options['atomize_json'] == TRUE)
					{
				
						if (is_dir('contributions/' . $committee->CommitteeCode . '/' . $report->Id . '/') === FALSE)
						{
							if (mkdir('contributions/' . $committee->CommitteeCode . '/' . $report->Id . '/', 0777, TRUE) === FALSE)
							{
								die('Cannot create contributions/' . $committee->CommitteeCode . '/' . $report->Id . '/');
							}
						}
					
						/*
						 * Add some additional fields to help us index this data.
						 */
						$contribution->type = 'contribution';
						$contribution->ReportId = $report->Id;
						$contribution->CommitteeCode = $committee->CommitteeCode;
						$contribution->CommitteeName = $committee->CommitteeName;
						if (isset($committee->CandidateName))
						{
							$contribution->CandidateName = $committee->CandidateName;
						}
					
						/*
						 * Save the expense as a JSON file.
						 */
						file_put_contents('contributions/' . $committee->CommitteeCode . '/' . $report->Id
							. '/' . str_pad($i, 5, '0', STR_PAD_LEFT) . '.json', json_encode($contribution));
						
					}
				
					/*
					 * Append this record to our JSON array.
					 */
					$json[] = $record;
				
					$i++;

				}
				
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
			if (filesize('expenses.csv') == 0)
			{
				fputcsv($fp_all, $headers);
			}
			
			/*
			 * Create an array to store this data as JSON, in addition to CSV.
			 */
			$json = array();
			
			$i=0;
			foreach($expenses as $expense)
			{
				
				/*
				 * Deal with empty objects, which plague this data. (Per issue #21.)
				 */
				if (is_object($expense->Payee->Address->Line1))
				{
					$expense->Payee->Address->Line1 = '';
				}
				if (is_object($expense->Payee->Address->Line2))
				{
					$expense->Payee->Address->Line2 = '';
				}
				
				$record = array
					(
						'committee_code' => $committee->CommitteeCode,
						'report_id' => $report->Id,
						'individual' => (string) $expense->Payee->{@attributes}->IsIndividual,
						'prefix' => (string) $expense->Payee->Prefix,
						'name_first' => (string) $expense->Payee->FirstName,
						'name_middle' => (string) $expense->Payee->MiddleName,
						'name_last' => (string) $expense->Payee->LastName,
						'address_1' => (string) $expense->Payee->Address->Line1,
						'address_2' => (string) $expense->Payee->Address->Line2,
						'address_city' => (string) $expense->Payee->Address->City,
						'address_state' => (string) $expense->Payee->Address->State,
						'address_zip' => (string) $expense->Payee->Address->ZipCode,
						'date' => (string) $expense->TransactionDate,
						'amount' => (string) $expense->Amount,
						'authorized_by' => (string) $expense->AuthorizingName,
						'purchased' => (string) $expense->ItemOrService
					);
				
				fputcsv($fp_committee, $record);
				fputcsv($fp_all, $record);
				
				/*
				 * Save this individual expense as a JSON file.
				 */
				if ($options['atomize_json'] == TRUE)
				{
					
					if (is_dir('expenses/' . $committee->CommitteeCode . '/' . $report->Id . '/') === FALSE)
					{
						if (mkdir('expenses/' . $committee->CommitteeCode . '/' . $report->Id . '/', 0777, TRUE) === FALSE)
						{
							die('Cannot create expenses/' . $committee->CommitteeCode . '/' . $report->Id . '/');
						}
					}
					
					/*
					 * Add some additional fields to help us index this data.
					 */
					$expense->type = 'expense';
					$expense->ReportId = $report->Id;
					$expense->CommitteeCode = $committee->CommitteeCode;
					$expense->CommitteeName = $committee->CommitteeName;
					if (isset($committee->CandidateName))
					{
						$expense->CandidateName = $committee->CandidateName;
					}
					
					/*
					 * Save the expense as a JSON file.
					 */
					file_put_contents('expenses/' . $committee->CommitteeCode . '/' . $report->Id
						. '/' . str_pad($i, 5, '0', STR_PAD_LEFT) . '.json', json_encode($expense));
						
				}
				
				/*
				 * Append this record to our JSON array.
				 */
				$json[] = $record;
				
				$i++;
				
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

file_put_contents('last_updated.txt', time());
