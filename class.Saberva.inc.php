<?php

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
		elseif (strpos($json, 'An error occurred while processing your request') !== FALSE)
		{
			echo 'An error was returned by the SBE server.' . PHP_EOL;
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
		$html = $this->fetch_content();
		 
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
			
			
			/*
			 * Create a new object to hold this report's data.
			 */
			$report = new stdClass();
			
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
	 * Convert the SBE's XML into JSON, making some changes while we're at it.
	 */
	function xml_to_json()
	{
	
		if (!isset($this->xml))
		{
			return FALSE;
		}
		
		/*
		 * Turn the XML into an object.
		 */
		$report = simplexml_load_string($this->xml);
		if ($report === FALSE)
		{
			return FALSE;
		}
		
		/*
		 * Recess single-element LiA and LiD elements by a single level, in order to make them
		 * consistent with multiple-element reports. Making the fresh versions of LiA and LiD as
		 * objects doesn't work, mysteriously, and the array-based process equally mysteriously
		 * creates a blank extra element at the end of the array, which is why we then unset it.
		 */		
		if (count($report->ScheduleA->LiA) === 1)
		{
			$tmp = $report->ScheduleA->LiA;
			$report->ScheduleA->LiA = array();
			$report->ScheduleA->LiA[] = $tmp;
			unset($tmp);
			unset($report->ScheduleA->LiA->{1});
		}
		if (count($report->ScheduleA->LiD) === 1)
		{
			$tmp = $report->ScheduleA->LiD;
			$report->ScheduleA->LiD = array();
			$report->ScheduleA->LiD[] = $tmp;
			unset($tmp);
			unset($report->ScheduleA->LiD->{1});
		}

		/*
		 * Normalize all address records within this report.
		 */
		if (!empty($report->ReportHeader->Address->Line1))
		{
			$report->ReportHeader->Address->Line1 = $this->normalize_address($report->ReportHeader->Address->Line1);
		}
		if (!empty($report->ReportHeader->Address->Line2))
		{
			$report->ReportHeader->Address->Line2 = $this->normalize_address($report->ReportHeader->Address->Line2);
		}
		if (count($report->ScheduleA->LiA) > 0)
		{
			foreach ($report->ScheduleA->LiA as $LiA)
			{
				if (!empty($LiA->Contributor->Address->Line1))
				{
					$LiA->Contributor->Address->Line1 = $this->normalize_address($LiA->Contributor->Address->Line1);
				}
				if (!empty($LiA->Contributor->Address->Line2))
				{
					$LiA->Contributor->Address->Line2 = $this->normalize_address($LiA->Contributor->Address->Line2);
				}
				else
				{
					unset($LiA->Contributor->Address->Line2);
				}
				if (!empty($LiA->Contributor->PrimaryCityAndStateOfEmploymentOrBusiness))
				{
					$LiA->Contributor->PrimaryCityAndStateOfEmploymentOrBusiness = $this->normalize_address($LiA->Contributor->PrimaryCityAndStateOfEmploymentOrBusiness);
				}
			}
		}
		if (count($report->ScheduleD->LiD) > 0)
		{
			foreach ($report->ScheduleD->LiD as $LiD)
			{
				if (!empty($LiD->Payee->Address->Line1))
				{
					$LiD->Payee->Address->Line1 = $this->normalize_address($LiD->Payee->Address->Line1);
				}
				if (!empty($LiD->Payee->Address->Line2))
				{
					$LiD->Payee->Address->Line2 = $this->normalize_address($LiD->Payee->Address->Line2);
				}
				else
				{
					unset($LiD->Payee->Address->Line2);
				}
				if (!empty($LiD->Payee->PrimaryCityAndStateOfEmploymentOrBusiness))
				{
					$LiD->Payee->PrimaryCityAndStateOfEmploymentOrBusiness = $this->normalize_address($LiD->Payee->PrimaryCityAndStateOfEmploymentOrBusiness);
				}
			}
		}
		
		/*
		 * Encode the PHP object as JSON.
		 */
		$this->report_json = json_encode($report);
		if ($this->report_json === FALSE)
		{
			return FALSE;
		}
		
		return TRUE;
		
	}
	
	/**
	 * Normalize addresses (Road -> Rd.; Street -> St.; etc.) This relies on the presence of
	 * class.AddressStandardizationSolution.inc.php. If the class is not present, it will silently
	 * return the provided address, because that's less bad than returning false.
	 */
	function normalize_address($address)
	{
		
		if (class_exists('AddressStandardizationSolution'))
		{
			
			$normalizer = new AddressStandardizationSolution;
			$new_address = $normalizer->AddressLineStandardization($address);
			
			/*
			 * Sometimes AddressStandardizationSolution returns an object when it should return a
			 * blank address field (e.g., address is ".", it returns an empty object). When that
			 * happens, substitute a blank field.
			 */
			if (is_object($new_address))
			{
				$new_address = '';
			}
			
			return $new_address;
			
		}
		else
		{
			return $address;
		}
	}
	
	
	/**
	 * Retrieve the content for a given URL.
	 */
	function fetch_content()
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
		
		/*
		 * Get the HTTP status code, turn it into an integer, and make sure we haven't gotten an
		 * error.
		 */
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE) + 0;
		if ($http_status >= 400)
		{
			die('The Virginia State Board of Elections server is returning an HTTP ' . $http_status
				. ' header -- halting execution.' . PHP_EOL);
		}
		
		curl_close($curl);
		
		return $html;
		
	}
	
}
