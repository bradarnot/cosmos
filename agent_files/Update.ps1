<#
.AUTHOR
Brad Arnot
.DESCRIPTION
Automatically updates the main script and runs it
#>

$manifestName = "fileList.mf"

#Get the path where the script was run
$directorypath =  Split-Path $MyInvocation.MyCommand.Path -parent
$configPath = ($directorypath + "\config.ini")

#Read config file and get ip address, version, and server
$config = Get-Content $configPath

for($i = 0; $i -lt $config.Length; $i++) {
	if($config[$i].length -le 0 -or $config[$i].substring(0, 1) -eq ";") {
		continue
	}
	if($config[$i].indexOf(";") -ne -1) {
		$config[$i] = $config[$i].substring(0, $config[$i].indexOf(";"))
	}
	$iplen = "IP".length
	if($config[$i].length -ge $iplen -and $config[$i].substring(0, $iplen) -eq "IP"){
		$ip = $config[$i].Substring($iplen + 1)
	}
}
#Initialize Web Client
$wc = New-Object System.Net.WebClient

#Get path to manifest file
$source = "http://" + $ip + "/" + $manifestName
$destination = ($directorypath + "\" + $manifestName)

write-host "Downloading file list..."

#Download manifest file
$wc.DownloadFile($source.Replace("\\", "/"), $destination)

write-host "Reading file list..."

#Parse the manifest file
if(-Not (Test-Path $destination)) {
	write-error "ERROR: Manifest file not downloaded!"
	write-error "Path: " $destination
	exit 1
}
$manifest = Get-Content $destination
$files = @()
write-host "Updating files..."
write-host ""
try {
	foreach($line in $manifest) {
		if($line.length -le 0 -or $line.substring(0, 1) -eq ";") {
			continue
		}
		if($line.indexOf(";") -ne -1) {
			$line = $line.substring(0, $line.indexOf(";"))
		}
		if($line.trim().toLower().StartsWith("path")){
			$currentPath = $line.trim().Substring("path".length + 1).Replace("/", "\").Replace("root", $directoryPath).trim()
		} elseif($line.trim().toLower().StartsWith("file")){
			$line = $line.Replace("/", "\")
			if($line.indexOf("\") -eq -1) {
				$files = $files + ($currentPath + "\" + $line.trim().Substring("file".length + 1).trim()).trim()
			} else {
				$names = $line.trim().Substring("file".length + 1).trim() -split "\\"
				$path = $directoryPath
				foreach($name in $names) {
					$path = ($path + "\" + $name).trim()
					if($name -eq $names[$names.Count-1]) {
						break;
					}
					if(-Not (Test-Path $path)) {
						New-Item $path -type directory | Out-Null
					}
				}
				$files += $path
			}
		}
	}
} Catch {
	write-error "ERROR: Error parsing the manifest file"
	write-error "$($_.Exception.Message)"
	exit 1
}

##############################
#Get last modified time of the files
foreach($destination in $files) {
	$source = "http://$ip" + $destination.substring($directoryPath.length)
	write-host "Updating: " $destination

	$currentModified = (Get-Item $destination -ErrorAction silentlycontinue).LastWriteTime 

	#Get last modified time of update script on web server
	$request = [System.Net.HttpWebRequest]::Create($source.Replace("\", "/"));
	$request.Method = "HEAD";
	$response = $request.GetResponse()
	$updateModified = ($response.LastModified) -as [DateTime] 
	$response.Close()

	#If the update script is newer than the main script, update the main script
	if(!$updateModified -or !$currentModified -or $updateModified -gt $currentModified) {
		write-host "Newer Version Available"
		write-host "Downloading newest version..."

		#Download the updated file
		$wc.DownloadFile($source.Replace("\", "/"), $destination)

	} else {write-host "Version already up to date"}

	if(-Not (Test-Path $destination)) {
		write-error "ERROR: File $destination download failed"
	}
}

remove-item ($directorypath + "\" + $manifestName)

write-host "Update Complete"
write-host ""

#Run the main script
cd $directoryPath
write-host "Getting Utilization Data..."
write-host ""
.\Usage.ps1