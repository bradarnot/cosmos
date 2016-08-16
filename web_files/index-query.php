<?php 
	/*
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
	//*/

	// Read in server and database information from file
	set_time_limit(15);
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
	$conn = mysqli_connect($server, $username, $password, $databaseName);
	if($conn == false){
		die("Could not connect to database");
	}

	function endsWith($haystack, $needle) {
		// search forward starting from end minus needle length characters
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
	}

	//Set the cid variable to the appropriate value for the SQL queries
	//If no cid is given, by default get all the cid's
	if (!isset($_GET['cid'])) {
		$cid = "[0-9][0-9][0-9][0-9][0-9]";
	} else {
		$cid = $_GET['cid'];
	}

	if($cid == 'All')
		$cid = "[0-9][0-9][0-9][0-9][0-9]";

	//Set variables for the scope of the query and type of report to generate
	$type = $_GET['type'];
	$timeFrame = $_GET['scope'];

	//Convert scope from get request to days
	if (endsWith($timeFrame, "day")) {
		$num = substr($timeFrame, 0, -3);
		if (is_numeric($num)) {
			$num = (int)$num;
		} else {
			die("invalid format for scope:'$timeFrame'");
		}
		$scope = $num * 1;
	} else if (endsWith($timeFrame, "week")) {
		$num = substr($timeFrame, 0, -4);
		if (is_numeric($num)) {
			$num = (int)$num;
		} else {
			die("invalid format for scope:'$timeFrame'");
		}
		$scope = $num * 7;
	} else if (endsWith($timeFrame, "month")) {
		$num = substr($timeFrame, 0, -5);
		if (is_numeric($num)) {
			$num = (int)$num;
		} else {
			die("invalid format for scope:'$timeFrame'");
		}
		$scope = $num * 30;
	} else if (endsWith($timeFrame, "year")) {
		$num = substr($timeFrame, 0, -4);
		if (is_numeric($num)) {
			$num = (int)$num;
		} else {
			die("invalid format for scope:'$timeFrame'");
		}
		$scope = $num * 365;
	} else {
		die("Invalid format for scope'$timeFrame'");
	}

	if ((!is_numeric($cid) || !strlen($cid) == 5) && $cid != "[0-9][0-9][0-9][0-9][0-9]") {
		die("<br>Please enter a valid customer id");
	}
	
	$noDataMessage = "Not enough data available over the last $scope days";

	$scope = $scope - 1;

	//Generate the correct report and output in HTML to be sent to the main page
	if ($type == "VSPPReport") {
		$stmt = "SELECT sum(counts.provisioned_dtps) as desktops, reports.timestamp as time, reports.cid as cid, reports.concurrent_users as users  FROM $table_reporting as reports LEFT JOIN $table_counts as counts ON reports.id=counts.id WHERE cid REGEXP '$cid' AND  reports.timestamp > DATE_ADD(CURDATE(), INTERVAL -$scope day) GROUP BY reports.id ORDER BY reports.cid, reports.timestamp";
		$dtp_result = mysqli_query($conn, $stmt);

		$graph_data = array();
		$maxTimes = array();
		$maxUsers = array();

		$dtp_row = mysqli_fetch_array($dtp_result);

		//If the query returned data, display it
		if(is_null($dtp_row[0])) {
			echo "<p id='noData'>$noDataMessage</p>";
		} else {
			$curCID = $dtp_row["cid"];
			$graph_data[$curCID] = array();

			$maxTimes[$curCID] = strtotime($dtp_row["time"]) - (24*3600);
			$maxUsers[$curCID] = $dtp_row["users"];
			$sumUsers = 0;
			$sumDtps = 0;
			while(!is_null($dtp_row)) {
				$curCID = $dtp_row["cid"];
				if(!array_key_exists($curCID, $graph_data)) {
					$graph_data[$curCID] = array();
					$maxTimes[$curCID] = strtotime($dtp_row["time"]) - (24*3600);
					$maxUsers[$curCID] = $dtp_row["users"];
				}
				while($curCID == $dtp_row["cid"]) {
					$time = strtotime($dtp_row["time"]) - (24*3600);
					$users = $dtp_row["users"];
					if($time > $maxTimes[$curCID])
						$maxTimes[$curCID] = $time;
					if($users > $maxUsers[$curCID])
						$maxUsers[$curCID] = $users;
					if($dtp_row["desktops"])
						$graph_data[$curCID][date("Y-m-d", $time) . "dtps"] = $dtp_row["desktops"];
					else
						$graph_data[$curCID][date("Y-m-d", $time) . "dtps"] = 0;
					$graph_data[$curCID][date("Y-m-d", $time) . "users"] = $users;
					$dtp_row = mysqli_fetch_array($dtp_result);
				}
				$sumUsers = $sumUsers + $maxUsers[$curCID];
				$sumDtps = $sumDtps + $graph_data[$curCID][date("Y-m-d", $maxTimes[$curCID]) . "dtps"];
			}

			$keys = array_keys($graph_data);
			echo "<table class='format-table'><tr><td>";
			echo "<div class=' table shadow report-container'>";
			echo "<table id='VSPPReport' class='table responsive'>";
			echo "<thead>";
			echo "<tr>";
			echo "<th>";
			echo "Customer ID";
			echo "</th>";
			echo "<th>";
			echo "Provisioned Desktops<br>as of ";
			$d1 = strtotime("today");
			echo date("F jS, Y", $d1);
			echo "</th>";
			echo "<th>";
			echo "Maximum concurrent users<br>since ";
			$d2 = strtotime("today");
			$d2 = strtotime("-" . ($scope+1) . " days", $d2);
			echo date("F jS, Y", $d2);
			echo "</th>";
			echo "</tr></thead>";
			if($cid == "[0-9][0-9][0-9][0-9][0-9]") {
				echo "<tfoot>";
				echo "<tr>";
				echo "<td>Total</td>";
				echo "<td>$sumDtps</td>";
				echo "<td>$sumUsers</td>";
				echo "</tr>";
				echo "</tfoot>";
			}
			echo "<tbody>";
			foreach($keys as $index => $curCID) {
				echo "<tr onclick='vsppGraph($curCID)' style='cursor: pointer;'>";
				echo "<td data-title='Customer ID'>";
				echo $curCID;
				echo "</td>";
				echo "<td>";
				echo $graph_data[$curCID][date("Y-m-d", $maxTimes[$curCID]) . "dtps"];
				echo "</td>";
				echo "<td>";
				echo $maxUsers[$curCID];
				echo "</td>";
				echo "</tr>";
			}
			echo "</tbody>";
			echo "</table>";
			echo "</div>";
			echo "</td><td>";
			echo "<div id='VSPPGraph' class='shadow' style='height: 400px'></div>";
			echo "</td></tr></table>";
			foreach($keys as $index => $curCID) {
				echo "<div id='dtpData$curCID' style='display: none;'>";
				foreach ($graph_data[$curCID] as $key => $value) {
					if(substr($key, -4) == "dtps")
						echo "<div>" . substr($key, 0, 10) . ",$value</div>";
				}
				echo "</div>";
			}
			foreach($keys as $index => $curCID) {
				echo "<div id='userData$curCID' style='display: none;'>";
				foreach ($graph_data[$curCID] as $key => $value) {
					if(substr($key, -5) == "users")
						echo "<div>" . substr($key, 0, 10) . ",$value</div>";
				}
				echo "</div>";
			}
		} 
	} else if ($type == "ProvReport") {
		$scope += 1;
		$stmt = "SELECT reporting.id, sum(counts.provisioned_dtps) as dtps, reporting.timestamp as day, reporting.cid as cid FROM $table_counts as counts JOIN $table_reporting as reporting ON counts.id=reporting.id WHERE reporting.cid REGEXP '$cid' AND reporting.timestamp > DATE_ADD(CURDATE(), INTERVAL -$scope day)  GROUP BY reporting.id ORDER BY reporting.cid, reporting.timestamp";
		$prov_result = mysqli_query($conn, $stmt);
		$prov_row = mysqli_fetch_array($prov_result);

		//For storing the data to graph for each cid
		//Indexed by cid
		$prov_data = array();

		//If the query returned with data, display it
		if (is_null($prov_row[0]) || mysqli_num_rows($prov_result) < 2) {
			echo "<p id='noData'>$noDataMessage</p>";
		} else {
			echo "<table class='format-table'><tr><td>";
			echo "<div class='table shadow report-container'>";
			echo "<table id='ProvReport' class='table responsive'>";
			echo "<thead>";
			echo "<tr>";
			echo "<th>";
			echo "Customer ID";
			echo "</th>";
			echo "<th>";
			echo "Date of VM change ";
			echo "</th>";
			echo "<th>";
			echo "Number of VM's changed";
			echo "</th>";
			echo "</tr>";
			echo "</thead><tbody>";

			$curCID = $prov_row["cid"];
			$prov_data[$curCID] = array();
			while(!is_null($prov_row)) {
				$diff = 0;
				if(!array_key_exists($curCID, $prov_data)) {
					$prov_data[$curCID] = array();
				}
				while($curCID == $prov_row["cid"]) { 
					$provBefore = $prov_row["dtps"];
					$prov_row = mysqli_fetch_array($prov_result);
					if($curCID != $prov_row["cid"])
						break;
					$provAfter = $prov_row["dtps"];
					$d = strtotime($prov_row["day"]) - (24*3600);
					if ($provBefore != $provAfter) {
						$diff = $provAfter - $provBefore;
						$prov_data[$curCID][date("Y-m-d", $d)] = $diff;
					} else {
						$diff = 0;
						$prov_data[$curCID][date("Y-m-d", $d)] = $diff;
						continue;
					}
					echo "<tr onclick='provGraph($curCID)'>";
					echo "<td>";
					echo $curCID;
					echo "</td>";
					echo "<td>"; 
					echo date("F jS Y", $d);
					echo "</td>";
					echo "<td>";
					echo $diff;
					echo "</td>";
					echo "</tr>";
				}
				$curCID = $prov_row["cid"];
			}
			echo "</tbody></table>";
			echo"</div>";
			echo "</td><td>";
			echo "<div id='ProvGraph' class='shadow' style='height: 400px'></div>";
			echo "</td></tr></table>";
			$keys = array_keys($prov_data);
			foreach($keys as $index => $curCID) {
				echo "<div id='provData$curCID' style='display: none;'>";
				foreach($prov_data[$curCID] as $key => $value) {
					echo "<div>$key,$value</div>";
				}
				echo "</div>";
			}
		}
	} else if ($type == "AgentReport") {
		$stmt = "SELECT reporting.timestamp as time, reporting.cid as cid FROM $table_reporting as reporting WHERE reporting.cid REGEXP '$cid' AND reporting.timestamp > DATE_ADD(CURDATE(), INTERVAL -$scope day) ORDER BY reporting.cid, reporting.timestamp";
		$date_result = mysqli_query($conn, $stmt);
		$date_row = mysqli_fetch_array($date_result);

		$stmt = "SELECT DISTINCT reporting.cid as cid FROM $table_reporting as reporting ORDER BY cid";
		$cid_result = mysqli_query($conn, $stmt);
		$cid_row = mysqli_fetch_array($cid_result);

		//Holds list of cids that have at least one missed day
		$missedCID = array();
		//Holds list of HTML for tables of missed dates for each CID
		$reports = array();

		if ($scope < 0) {
			echo "<p id='noData'>$noDataMessage</p>";
		} else {
			echo "<table class='format-table'><tr><td>";
			echo "<div class='table shadow report-container'>";
			echo "<table id='AgentReport' class='table'>";
			echo "<thead>";
			echo "<tr>";
			echo "<th>";
			echo "Customer ID";
			echo "</th>";
			echo "<th>";
			echo "Script Status";
			echo "</th>";
			echo "</tr>";
			echo "</thead>";
			echo "<tbody>";

			$prints = "";
			
			$daysMiss = array();
			while($cid_row) {
				$thisCID = $cid_row["cid"];
				$daysMiss[$thisCID] = 0;
				$miss = false;
				$report = "";
				//$report = $report . ("<div class='expand-button' onclick='toggleAgent($thisCID, this)'><div id='cid" . $thisCID . "' class='cidLabel' style='top: -9px;left: 7px;'>Dates</div></div>");
				$report = $report .  "<div class='scrollTable'><table class='table'><thead></thead></table><div class='tablecontainer'><table class='table'>";
				$report = $report .  "<thead>";
				$report = $report .  "<tr>";
				$report = $report .  "<th>";
				$report = $report .  "Dates missed";
				$report = $report .  "</th>";
				$report = $report .  "</tr>";
				$report = $report .  "</thead><tbody>";

				$today = new DateTime();
				$today->add(new DateInterval("P1D"));
				$date = new DateTime();
				$date->sub(new DateInterval("P" . ($scope+1) . "D"));
				$start = clone $date;
				//echo "CID: " . $thisCID . "<br>";
				//echo "Today: " . $today->format("m/d/Y") . "<br>";

				while ($date->format("Y/M/d") != $today->format("Y/M/d")) { 
					if ($thisCID != $date_row["cid"]) {
						$date = $date->add(new DateInterval("P1D"));
						$start = clone $date;
						while($date->format("Y/M/d") != $today->format("Y/M/d")) {
							$miss = true;
							$date = $date->add(new DateInterval("P1D"));
							$daysMiss[$thisCID]++;
						}
						if($start->format("Y/M/d") != $date->format("Y/M/d")) {
							$report = $report .  "<tr>";
							$report = $report .  "<td>";
							$report = $report .  $start->format("m/d/Y");
							$tmp = clone $date;
							$tmp->sub(new DateInterval("P1D"))->format("m/d/Y");
							if($tmp->format("m/d/Y") != $start->format("m/d/Y")) {
								$report = $report . " - " .  $tmp->format("m/d/Y");
							}
							$report = $report .  "</td>";
							$report = $report .  "</tr>";
						}
						break;
					}
					$nextDate = date_create_from_format('Y-m-d H:i:s', $date_row["time"]);
					//echo "Next: " . $nextDate->format("m/d/Y") . "<br>";
					
					$date = $date->add(new DateInterval("P1D"));
					//echo "Date: " . $date->format("m/d/Y") . "<br>";
					$start = clone $date;

					while ($date->format("Y/M/d") != $today->format("Y/M/d") && (!$nextDate || $date->format("Y/M/d") != $nextDate->format("Y/M/d"))) {
						/*
						$report = $report .  "<tr>";
						$report = $report .  "<td>";
						$report = $report .  $date->format("m/d/Y");
						$report = $report .  "</td>";
						$report = $report .  "</tr>";
						//*/
						$miss = true;
						$date = $date->add(new DateInterval("P1D"));
						$daysMiss[$thisCID]++;
					}
					//*
					if($start->format("Y/M/d") != $date->format("Y/M/d")) {
						$report = $report .  "<tr>";
						$report = $report .  "<td>";
						$report = $report .  $start->format("m/d/Y");
						$tmp = clone $date;
						$tmp->sub(new DateInterval("P1D"))->format("m/d/Y");
						if($tmp->format("m/d/Y") != $start->format("m/d/Y")) {
							$report = $report . " - " .  $tmp->format("m/d/Y");
						}
						$report = $report .  "</td>";
						$report = $report .  "</tr>";
					}
					//*/
					$date_row = mysqli_fetch_array($date_result);
				}
				
				if ($miss) {
					$missedCID[] = $thisCID;
					echo "<tr class='error'>";
					echo "<td>";
					echo $thisCID;
					echo "</td>";
					echo "<td>";
					echo "Error <div class='more' onclick='more_details(this)'>more info</div>";
				} else {
					echo "<tr class='good'>";
					echo "<td>";
					echo $thisCID;
					echo "</td>";
					echo "<td>";
					echo "Reporting";
				}
				echo "</td>";
				echo "</tr>";
				$report = $report .  "</tbody></table></div></div>";
				$reports[$thisCID] = $report;
				$cid_row = mysqli_fetch_array($cid_result);
			}
			echo "</tbody>";
			echo"</table></div>";
			echo "</td>";
			echo "<td>";

			echo "<div id='expanded-content' class='shadow table' style='visibility: hidden'>";
				for ($i=0; $i < sizeof($missedCID); $i++) { 
					echo "<div id='expanded-" . $missedCID[$i] . "' style='display: none'>";
					echo "<div class='error-title'>";
					echo "<h3>Error Details</h3>";
					echo "</div>";
					echo "<div class='error-body'>";
					echo "<p><strong>CID:</strong> " . $missedCID[$i] . "</p>";
					echo "<p><strong>Error: </strong>Missed Update<br>to database</p>";
					echo "Database is missing data<br>for " . $daysMiss[$missedCID[$i]] . " of the last " . ($scope+1) . " days<br><br>";
					echo $reports[$missedCID[$i]];
					echo "</div>";
					echo "</div>";
				}
			echo "</div>";
			echo "</tr></table>";
		}
	} else {
		echo "I don't know";
	}
	mysqli_close($conn);
?>