<?php
	// Error checking
	/*
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
	//*/

	// Read in server and database information from configuration file file

	$dataFile = fopen("databaseInfo.ini", "r") or die("Could not open file");
	while(!feof($dataFile)) {
		$line = fgets($dataFile);
		if (stristr($line, "DATABASE") != FALSE) {
			$databaseName = substr($line, stristr($line, "DATABASE") + 9);
			$databaseName = trim($databaseName);
		} else if (stristr($line, "USERNAME") != FALSE) {
			$username = substr($line, stristr($line, "USERNAME") + 9);
			$username = trim($username);
		} else if (stristr($line, "PASSWORD") != FALSE) {
			$password = substr($line, stristr($line, "PASSWORD") + 9);
			$password = trim($password);
		}  else if (stristr($line, "SERVER") != FALSE) {
			$server = substr($line, stristr($line, "SERVER") + 7);
			$server = trim($server);
		} else if (stristr($line, "TABLE_COUNTS") != FALSE) {
			$table_counts = substr($line, stristr($line, "TABLE_COUNTS") + 13);
			$table_counts = trim($table_counts);
		}  else if (stristr($line, "TABLE_REPORTING") != FALSE) {
			$table_reporting = substr($line, stristr($line, "TABLE_REPORTING") + 16);
			$table_reporting = trim($table_reporting);
		}
	}
	fclose($dataFile);
	/*
	echo "Database: ". $databaseName;
	echo "<br>Username: ". $username;
	echo "<br>Password: ". $password;
	echo "<br>Server: ". $server;
	echo "<br>desktop_counts: ". $table_counts;
	echo "<br>reporting_data: ". $table_reporting . "<br>";
	//*/

	//$connection_string = "Driver={MySQL};Server=$server;Database={$databaseName}";
	$conn = mysqli_connect($server, $username, $password, $databaseName);
	if($conn == false){
    	die("Could not connect to database");
	}
	//*/
?>
<!DOCTYPE html>
<html>
<head>
	<title>Utilization Reports</title>
	<link rel="stylesheet" type="text/css" href="site.css">
	<!--<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
	<script type="text/javascript" src="graphs.js"></script>-->
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
	<script type="text/javascript" src="app.js"></script>
	<script type="text/javascript" src="highcharts.js"></script>
	<script type="text/javascript" src="modules/exporting.js"></script>
</head>
<body>
	<div class="header shadow">
		<h2>Dizzion Cosmos</h2>
		<div id="title"><h2>VSPP Report</h2></div>
	</div>

	<div id="filters" class="shadow">
		<h4>Type of Report:</h4>
		<select type="text" name="typeReport" id="typeReport" onchange="changeType()">
			<option value="VSPPReport">VSPP Report</option>
			<option value="ProvReport">Provisioning Report</option>
			<option value="AgentReport">Agent Report</option>
		</select>
		
		<h4>Customer ID:</h4>
		<input id="cidText" list="cidList" type="text" name="cidPrompt" autofocus="autofocus" autocomplete="off" onchange="refreshPage()">
		
		<datalist id="cidList">
			<option value='All'>All</option>
			<?php
				//Fill the datalist with all the cid's reporting to the database
				$stmt = "SELECT DISTINCT cid FROM $table_reporting as reporting order by cid";
				$cid_result = mysqli_query($conn, $stmt);

				$cid_row = mysqli_fetch_array($cid_result);
				$cids = array();
				while(!is_null($cid_row["cid"])) {
					$nextCID = $cid_row["cid"];
					$cids[] = $nextCID;
					echo "<option value='$nextCID'>$nextCID</option>";
					$cid_row = mysqli_fetch_array($cid_result);
				}
				mysqli_close($conn);
			?>
		</datalist>
		<div class="more" onclick="toggleCustom()">Custom</div>
		<br>
		<select multiple id="custcid" style="display:none; width: 100px;">
			<?php
				foreach($cids as $key => $value) {
					echo "<option value='$value' selected='selected'>$value</option>";
				}
			?>
		</select>
		<br>
		<button type="button" id="custbtn" onclick="generateCustom()" style="display: none">Submit</button>
		<div id="timeSelect">
			<h4>Time frame:</h4>
			<select type="text" name="scopeSelector" id="scope" onchange="refreshPage()">
				<option value="1day">Day</option>
				<option value="1week">Week</option>
				<option value="1month">Month</option>
				<option value="3month">3 Months</option>
				<option value="1year">Year</option>
			</select>
		</div>

		<h4>Export as:</h4>
		<select type="text" name="exportSelector" id="exportSelector">
			<option value="csv">CSV</option>
			<option value="tsv">TSV</option>
		</select>

		<button type="button" id="exportButton" onclick="exportTable()">Export</button>

	</div>

	<div class="content">
		<div id="reports">
			<div id='main-report'>
			</div>
		 </div>
		 
	</div>
	
</body>
</html>