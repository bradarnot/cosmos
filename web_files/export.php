<?php
	$ext = $_GET["ext"];

	if ($ext == "csv") {
		$del = ",";
	} else if ($ext == "tsv") {
		$del = "\t";
	}

    header('Content-type: application/force-download');
	header('Content-Disposition: attachment; filename="export.' . $ext . '"');

	/*
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
	//*/

	// Read in server and database information from file
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

	if (!isset($_GET['cid'])) {
		$cid = "All";
	} else {
		$cid = $_GET['cid'];
	}

	$type = $_GET['type'];
	$timeFrame = $_GET['scope'];
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

	if ((is_numeric($cid) && strlen($cid) == 5) || $cid === "All") {
		if($cid === "All") {
			$cid = "[0-9][0-9][0-9][0-9][0-9]";
		}
	} else {
		die("<br>Please enter a valid customer id");
	}
	
	$noDataMessage = "Not enough data available over the last $scope days";

	$scope = $scope - 1;

	if ($type == "VSPPReport") {
		$stmt = "SELECT sum(counts.provisioned_dtps) as desktops, reports.timestamp as time, reports.cid as cid, reports.concurrent_users as users  FROM $table_reporting as reports JOIN $table_counts as counts ON reports.id=counts.id WHERE cid REGEXP '$cid' AND  reports.timestamp > DATE_ADD(CURDATE(), INTERVAL -$scope day) GROUP BY reports.id ORDER BY reports.cid, reports.timestamp";
		$dtp_result = mysqli_query($conn, $stmt);

		$maxTimes = array();
		$maxUsers = array();

		$dtp_row = mysqli_fetch_array($dtp_result);

		if(is_null($dtp_row[0])) {
			echo $noDataMessage;
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
					$graph_data[$curCID][date("Y-m-d", $time)] = $dtp_row["desktops"];
					$dtp_row = mysqli_fetch_array($dtp_result);
				}
				$sumUsers = $sumUsers + $maxUsers[$curCID];
				$sumDtps = $sumDtps + $graph_data[$curCID][date("Y-m-d", $maxTimes[$curCID])];
			}

			$keys = array_keys($graph_data);
			echo "cid" . $del;
			echo "provisioned_desktops";
			echo $del;
			echo "concurrent_users";
			echo "\n";
			foreach($keys as $index => $curCID) {
				echo $curCID;
				echo $del;
				echo $graph_data[$curCID][date("Y-m-d", $maxTimes[$curCID])];
				echo $del;
				echo $maxUsers[$curCID];
				echo "\n";
			}
			echo "total";
			echo $del;
			echo $sumDtps;
			echo $del;
			echo $sumUsers;
			echo "\n";
		} 
	} else if ($type == "ProvReport") {
		$scope += 1;
		$stmt = "SELECT reporting.id, sum(counts.provisioned_dtps) as dtps, reporting.timestamp as day, reporting.cid as cid FROM $table_counts as counts JOIN $table_reporting as reporting ON counts.id=reporting.id WHERE reporting.cid REGEXP '$cid' AND reporting.timestamp > DATE_ADD(CURDATE(), INTERVAL -$scope day)  GROUP BY reporting.id ORDER BY reporting.cid, reporting.timestamp";
		$prov_result = mysqli_query($conn, $stmt);
		$prov_row = mysqli_fetch_array($prov_result);

		if($cid === "[0-9][0-9][0-9][0-9][0-9]") {
			$cid = "All";
		}

		if (is_null($prov_row[0]) || mysqli_num_rows($prov_result) < 2) {
			echo $noDataMessage;
		} else {
			echo "cid" . $del;
			echo "date" . $del;
			echo "vm_change\n";
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
					echo $curCID;
					echo $del;
					echo date("m-d-Y", strtotime($prov_row["day"]) - (24*3600));
					echo $del;
					echo $diff;
					echo "\n";
				}
				$curCID = $prov_row["cid"];
			}
		}
	} else if ($type == "AgentReport") {
		$stmt = "SELECT reporting.timestamp as time, reporting.cid as cid FROM $table_reporting as reporting WHERE reporting.cid REGEXP '$cid' AND reporting.timestamp > DATE_ADD(CURDATE(), INTERVAL -$scope day) ORDER BY reporting.cid, reporting.timestamp";
		$date_result = mysqli_query($conn, $stmt);

		if($cid === "[0-9][0-9][0-9][0-9][0-9]") {
			$cid = "All";
		}

		$date_row = mysqli_fetch_array($date_result);
		$cids = array();

		if ($scope < 0) {
			echo $noDataMessage;
		} else {
			echo "cid" . $del;
			echo "date_missed\n";
			while($date_row) {
				$thisCID = $date_row["cid"];
				

				$today = new DateTime();
				$today->add(new DateInterval("P1D"));
				$date = new DateTime();
				$date->sub(new DateInterval("P" . ($scope+1) . "D"));

				while ($date->format("Y/M/d") != $today->format("Y/M/d")) { 
					if ($thisCID != $date_row["cid"]) {
						break;
					}
					$nextDate = date_create_from_format('Y-m-d H:i:s', $date_row["time"]);
					$date = $date->add(new DateInterval("P1D"));

					while ($date->format("Y/M/d") != $today->format("Y/M/d") && (!$nextDate || $date->format("Y/M/d") != $nextDate->format("Y/M/d"))) {
						if (!in_array($thisCID, $cids)) {
							$cids[] = $thisCID;
						}
						echo $thisCID;
						echo $del;
						echo $date->format("m-d-Y");
						echo "\n";
						$date = $date->add(new DateInterval("P1D"));
					}
					$date_row = mysqli_fetch_array($date_result);
				}
			}
		}
	} else {
		echo "I don't know";
	}
	mysqli_close($conn);
	//*/
?>