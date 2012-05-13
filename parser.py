# Import needed libraries.
import csv, urllib2, re, time

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
		
		print (row)


# Convert list back to a CSV file and write to the filesystem.

# Create a SQL file that will create the table(s) and load the CSV from file.

# Echo instructions for how to load the SQL into MySQL.
	
"""
Mapping of field values to SQL column.

ReportId					mediumint unsigned
AccountId					38
CommitteeCode				varchar (12)
CommitteeName				varchar (not null)
CommitteeType				enum (create list based on distinct values)
CandidateName				varchar (can be null)
IsStateWide					true/false
IsGeneralAssembly			true/false
IsLocal						true/false
Party						enum (create list based on distinct values)
FecNumber					????
ReportYear					year
FilingDate					datetime (microtime)
StartDate					datetime
EndDate						datetime
AddressLine1				varchar
AddressLine2				varchar
AddressLine3				varchar
City						varchar (normalize case)
StateCode					char (2)
ZipCode						varchar (5-10 characters)
FilingType					enum (Report, ???)
IsFinalReport				true/false
IsAmendment					true/false
AmendmentCount				tinyint unsigned
SubmitterPhone				varchar (normalize format)
SubmitterEmail				varchar
ElectionCycle				date (convert from MM/YYYY to MM/DD/YYYY)
ElectionCycleStartDate		datetime
ElectionCycleEndDate		datetime
OfficeSought				varchar (strip out prefix)
District					varchar (strip out prefix)
NoActivity					true/false
BalanceLastReportingPeriod	money (dollars cents)
DateOfReferendum			datetime
SubmittedDate				datetime
DueDate						datetime
"""