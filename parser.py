# Import needed libraries.
import csv, urllib2, re, time

# The name of the file to which we're saving the cleaned-up CSV.
output_filename = 'import.csv'

# The name of the file to which we're saving the SQL to import this data.
sql_filename = 'import.sql'

# This will only work for the month of April. Because there's no single URL for the report, it's
# going to be necessary to either iterate through all directories or get the most recent one. Until
# the SBE documents or explains what they're doing here, it's tough to know what do with this.
url = 'http://www.sbe.virginia.gov/sbe_csv/CF/2012_04/Report.csv'

# Retrieve the contents of the CSV report file from the SBE's server.
# If this fails, throw an error and quit.
csv_file = urllib2.urlopen(url)

# Convert the contents of the CSV file to a list.
# If this fails, throw an error and quit.
Report = csv.reader(csv_file)

# Convert list back to a CSV file and write to the filesystem.
output_file = open(output_filename, 'wb')
wr = csv.writer(output_file)

# Iterate through each line in the report.
for counter, row in enumerate(Report):

	if counter == 0:
		# Save the header row as our column names.
		column_names = row
		continue
		
	# Throw an error for any row that has fewer values than the header row.
	elif len(row) < len(column_names):
		#exception 'Ignoring the following row because it has fewer values than the header row.'
		print ', '.join(row)

	else:
		# Make modifications to all fields that require normalization
		row = dict(zip(column_names, row))
		
		# Normalize phone number formatting by removing everything non-numeric and then applying
		# a standard hyphenation mask.
		row['SubmitterPhone'] = re.sub('[^0-9]','', row['SubmitterPhone'])
		row['SubmitterPhone'] = row['SubmitterPhone'][:3] + '-' + row['SubmitterPhone'][3:6] + '-' + row['SubmitterPhone'][6:]
		
		# The ElectionCycle field is frequently empty. (Which doesn't make sense, intuitively, but
		# there it is.) When it is populated, it's in MM/YYYY format, unlike all other dates in the
		# file. Modify the format to be YYYY-MM-01 based.
		if len(row['ElectionCycle']) > 0:
			row['ElectionCycle'] = time.strftime('%Y-%m-%d', time.strptime(row['ElectionCycle'], '%m/%Y'))
		
		# Commented out because it's not working.
		#wr.writerow(row)
		# Convert every False to "n" and every True to "y"

# Create a SQL file that will create the table(s) and load the CSV from file.
sql_file = open(sql_filename, 'wb')
sql_create = """CREATE TABLE IF NOT EXISTS `filings` (
  `ReportId` mediumint(8) unsigned NOT NULL,
  `AccountId` char(38) collate utf8_bin NOT NULL,
  `CommitteeCode` varchar(12) collate utf8_bin NOT NULL,
  `CommitteeName` varchar(128) collate utf8_bin NOT NULL,
  `CommitteeType` varchar(128) collate utf8_bin NOT NULL,
  `CandidateName` varchar(128) collate utf8_bin default NULL,
  `IsStateWide` enum('y','n') collate utf8_bin NOT NULL default 'n',
  `IsGeneralAssembly` enum('y','n') collate utf8_bin NOT NULL,
  `IsLocal` enum('y','n') collate utf8_bin NOT NULL,
  `Party` varchar(64) collate utf8_bin NOT NULL,
  `FecNumber` char(9) collate utf8_bin default NULL,
  `ReportYear` year(4) NOT NULL,
  `FilingDate` datetime NOT NULL,
  `StartDate` datetime NOT NULL,
  `EndDate` datetime NOT NULL,
  `AddressLine1` varchar(128) collate utf8_bin NOT NULL,
  `AddressLine2` varchar(128) collate utf8_bin default NULL,
  `AddressLine3` varchar(128) collate utf8_bin default NULL,
  `City` varchar(128) collate utf8_bin NOT NULL,
  `StateCode` char(2) collate utf8_bin NOT NULL default 'VA',
  `ZipCode` varchar(10) collate utf8_bin NOT NULL,
  `FilingType` enum('report') collate utf8_bin NOT NULL default 'report',
  `IsFinalReport` enum('y','n') collate utf8_bin NOT NULL default 'n',
  `IsAmendment` enum('y','n') collate utf8_bin NOT NULL default 'n',
  `AmendmentCount` tinyint(3) unsigned NOT NULL,
  `SubmitterPhone` varchar(12) collate utf8_bin NOT NULL,
  `SubmitterEmail` varchar(128) collate utf8_bin NOT NULL,
  `ElectionCycle` date NOT NULL,
  `ElectionCycleStartDate` datetime NOT NULL,
  `ElectionCycleEndDate` datetime NOT NULL,
  `OfficeSought` varchar(128) collate utf8_bin NOT NULL,
  `District` varchar(128) collate utf8_bin NOT NULL,
  `NoActivity` enum('y','n') collate utf8_bin NOT NULL default 'n',
  `BalanceLastReportingPeriod` decimal(10,2) NOT NULL,
  `DateOfReferendum` datetime NOT NULL,
  `SubmittedDate` datetime NOT NULL,
  `DueDate` datetime NOT NULL,
  PRIMARY KEY  (`ReportId`),
  KEY `AccountId` (`AccountId`,`CandidateName`),
  KEY `Party` (`Party`,`FecNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;"""
sql_create += '\n\nLOAD DATA LOCAL INFILE "' + output_filename
sql_create += """" \n\
INTO TABLE filings \n\
FIELDS TERMINATED BY "," \n\
ENCLOSED BY '"' \n\
LINES TERMINATED BY '\\n' \n\
(ReportId, AccountId, CommitteeCode, CommitteeName, CommitteeType, CandidateName, IsStateWide, \
IsGeneralAssembly, IsLocal, Party, FecNumber, ReportYear, FilingDate, StartDate, EndDate, \
AddressLine1, AddressLine2, AddressLine3, City, StateCode, ZipCode, FilingType, IsFinalReport, \
IsAmendment, AmendmentCount, SubmitterPhone, SubmitterEmail, ElectionCycle, ElectionCycleStartDate, \
ElectionCycleEndDate, OfficeSought, District, NoActivity, BalanceLastReportingPeriod, \
DateofReferendum, SubmittedDate, DueDate)"""
sql_file.write(sql_create)
sql_file.close

# Echo instructions for how to load the SQL into MySQL.
print "CSV and SQL exported successfully. To load the database into MySQL, run the \n\
following command:\n\
\n\
mysql [database] < " + sql_filename + "\n\
\n\
Replace [database] with the name of the database in which you want to store\nthe data.";