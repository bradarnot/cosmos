<#
.AUTHOR
Brad Arnot
.DESCRIPTION
Gets the number of provisioned desktops and max concurrent users for a customer
#>
try {
	[void][System.Reflection.Assembly]::LoadWithPartialName("MySql.Data")
} catch {
	write-error "MySQL .Net Connector is not installed!!"
	exit 1
}
#Snapin for PowerCLI cmdlets
& "C:\Program Files\VMware\VMware View\Server\Extras\PowerShell\add-snapin.ps1"
if ((Get-PSSnapin -Name VMware.View.Broker -ErrorAction SilentlyContinue) -eq $null ) {
	Add-PSSnapin VMware.View.Broker
}
remove-item ".\InstallUtil.InstallLog" -erroraction silentlycontinue

#Read config file and get SQL Server and customer id
$configPath = Split-Path $MyInvocation.MyCommand.Path -parent
$configPath = ($configPath + "\config.ini")
$config = Get-Content $configPath

#Read the data from config file
foreach($line in $config) {
	if($line.length -lt 3 -or $line.substring(0, 1) -eq ";") {
		continue
	}
	if($line.indexOf(";") -ne -1) {
		$line = $line.substring(0, $line.indexOf(";"))
	}
	$slen = "SERVER".length
	$clen = "CID".length
	$ulen = "USER".length
	$plen = "PASSWORD".length
	$dlen = "DATABASE".length
	$tlen = "TABLE_COUNTS".length
	$rlen = "TABLE_REPORTING".length
	$portlen = "PORT".length
	if($line.trim().toLower().StartsWith("server")){
		$server = $line.substring($slen + 1).trim()
	} elseif($line.trim().toLower().StartsWith("cid")){
		$cid = $line.substring($clen + 1).trim()
	} elseif($line.trim().toLower().StartsWith("user")){
		$user = $line.substring($ulen + 1).trim()
	} elseif($line.trim().toLower().StartsWith("password")){
		$pswd = $line.substring($plen + 1).trim()
	} elseif($line.trim().toLower().StartsWith("database")){
		$database = $line.substring($dlen + 1).trim()
	} elseif($line.trim().toLower().StartsWith("table_counts")){
		$counts_table = $line.substring($tlen + 1).trim()
	} elseif($line.trim().toLower().StartsWith("table_reporting")){
		$reporting_table = $line.substring($rlen + 1).trim()
	}  elseif($line.trim().toLower().StartsWith("port")){
		$port = $line.substring($portlen + 1).trim()
	}
}

#Counts max concurrent users in last 24 hours from event database using PowerCLI cmdlet
$maxCCUsers = (Get-EventReport -viewName user_count_events -startDate ((Get-Date).AddDays(-1)) | Select Usercount | Measure-Object usercount -max).Maximum

if(!$maxCCUsers) {
	write-host "Warning: Concurrent Users is null... setting to 0"
	$maxCCUsers = 0
}

#Connect to mySQL Server & Database
$connStr = "server=$server;port=$port;uid=$user;pwd=$pswd;database=$database"
$connection = New-Object MySql.Data.MySqlClient.MySqlConnection($connStr)
$connection.Open()

#Fill reporting_data table
# Query to insert data into DB
$command = $connection.CreateCommand()
$command.Connection = $connection
$sql ="INSERT INTO "+ $reporting_table + " (cid, concurrent_users, timestamp)
	VALUES (@CID, @maxUsers, @dateTime)
    " 
$command.CommandText = $sql
$out = $command.Parameters.Add("@CID", $cid)
$out = $command.Parameters.Add("@maxUsers", $maxCCUsers)
$datetime = (get-date).toString('yyyy-MM-dd hh:mm:ss')
$out = $command.Parameters.Add("@dateTime", $datetime)
$nrows = $command.ExecuteNonQuery()
$command.Dispose()

# Issue query to get the most recent ID in the table
$command = $connection.CreateCommand()
$command.Connection = $connection

$sql ="SELECT LAST_INSERT_ID();" 
$command.CommandText = $sql

$lastID = $command.ExecuteReader()
if ($lastID.Read()) {
	$newID = $lastID[0];
} else {
	$newID = 0
}
$lastID.close()
$command.Dispose()

#Fill desktop_counts table
$pools = Get-Pool -pool_id *
$dtps = Get-DesktopVM -ErrorAction silentlycontinue
foreach($pool in $pools) {

	if($pool.pool_id -like "*Base-GI*") {continue}

	#Count number of provisioned desktops
	$provDtps = @($dtps | where {$_.pool_id -eq $pool.pool_id}).Count

	if(!$provDtps) {
		write-host "Warning: Provisioned desktops is null... setting to 0"
		$provDtps = 0
	}

	#Count number of desktops that are assigned to a user
	$activeDtps = @($dtps | where {($_.pool_id -eq $pool.pool_id) -and $_.user_sid}).Count

	if(!$activeDtps) {
		write-host "Warning: Active desktops is null... setting to 0"
		$activeDtps = 0
	}

	#write-host "Max concurrent users: " $maxCCUsers
	#write-host "Provisioned Desktops: " $provDtps
	#write-host "Active Desktops: " $activeDtps

	# Query to insert data into DB
	$command = $connection.CreateCommand()
	$command.Connection = $connection
	$sql ="INSERT INTO "+ $counts_table + " (id, pool_name, provisioned_dtps, active_dtps)
		VALUES (@newID, @pool, @prov, @active)
	    " 
	$command.CommandText = $sql
	$out = $command.Parameters.Add("@newID", $newID)
	$out = $command.Parameters.Add("@pool", $pool.displayName)
	$out = $command.Parameters.Add("@prov", $provDtps)
	$out = $command.Parameters.Add("@active", $activeDtps)
	$nrows = $command.ExecuteNonQuery()
	$command.Dispose()
}


$connection.Close()