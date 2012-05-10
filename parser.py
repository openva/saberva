# Import needed libraries.
import csv, urllib2

url = 'http://www.sbe.virginia.gov/sbe_csv/CF/Report.csv'

# Retrieve the contents of the CSV report file from the SBE's server.
# If this fails, throw an error and quit.
csv_file = urllib2.urlopen(url)

# Convert the contents of the CSV file to a list.
# If this fails, throw an error and quit.
Report = csv.reader(csv_file)

# Iterate through each line in the report.
for row in Report:

	# If this is the first row, store its length.
	# Ignore the header row, though count how many values it has.
	total_columns = len(row)
	
	# Throw an error for any row that has fewer values than the header row.
	
	
	# Make modifications to all fields that require normalization
	print ', '.join(row)


# Convert list back to a CSV file and write to the filesystem.

# Create a SQL file that will create the table(s) and load the CSV from file.

# Echo instructions for how to load the SQL into MySQL.
	
"""
Mapping of field values to SQL column.

ReportId					mediumint unsigned
AccountId					38 (ignore brackets?)
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