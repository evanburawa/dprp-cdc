<?php
define("NOAUTH", true);
require('config.php');
require('ldap.php');

foreach ($_GET as $key => $val) {
	$_GET[strtolower($key)] = $val;
}

if (isset($_GET['orgcode'])) {
	preg_match("/\d+/", $_GET['orgcode'], $orgcode);
	$orgcode = $orgcode[0];
} elseif (!isset($_GET['noncompliant'])) {
	exit("Please supply an orgcode URL parameter and/or 'noncompliant' URL parameter
	<br />e.g.:
	<br />https://redcap.vanderbilt.edu/plugins/dprp-cdc/index.php<b>?orgcode=8540168</b>
	<br />https://redcap.vanderbilt.edu/plugins/dprp-cdc/index.php<b>?noncompliant</b>");
}

function getEmployeeID($uid) {
	$entry = LdapLookup::lookupUserDetailsByKeys([$uid], ["uid"], true, false);
	return $entry[0]['vanderbiltpersonemployeeid'][0];
}

// detect which if any values are not compliant with DPRP standards
// if non-compliance is detected, error messages are appended to $line
function validateLine(& $line) {
	$errors = [];
	
	$stateAbbrevs = ["AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "FL", "GA", "HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME", "MD", "MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH", "NJ", "NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "RI", "SC", "SD", "TN", "TX", "UT", "VT", "VA", "WA", "WV", "WI", "WY", "AS", "DC", "FM", "GU", "MH", "MP", "PW", "PR", "VI", "AE", "AA", "AP"];
	
	// validate orgcode (alphanumeric 1-25)
	if (preg_match('/[^a-z0-9]/i', $line[0]))
		$errors[] = "ORGCODE value must be alphanumeric (contain only numbers and letters)";
	if (strlen($line[0]) < 1 or strlen($line[0]) > 25)
		$errors[] = "ORGCODE value must have a length of 1 to 25 alphanumeric characters";
	// validate participant ID
	if (preg_match('/[^a-z0-9]/i', $line[1]))
		$errors[] = "PARTICIP value must be alphanumeric (contain only numbers and letters)";
	if (strlen($line[1]) < 1 or strlen($line[1]) > 25)
		$errors[] = "PARTICIP value must have a length of 1 to 25 alphanumeric characters";
	// validate ENROLL
	if (preg_match('/[^0-9]/', $line[2]))
		$errors[] = "ENROLL must contain numeric characters only";
	if (intval($line[2]) < 1 or intval($line[2]) > 10)
		$errors[] = "ENROLL must be an integer value 1-10";
	// validate PAYER
	if (preg_match('/[^0-9]/', $line[3]))
		$errors[] = "PAYER must contain numeric characters only";
	if (intval($line[3]) < 1 or intval($line[3]) > 9)
		$errors[] = "PAYER must be an integer value 1-9";
	// validate STATE
	if (!in_array($line[4], $stateAbbrevs))
		$errors[] = "STATE must be a valid 2 character state abbreviation -- see: <a href=\"https://www.50states.com/abbreviations.htm\">50states</a> for valid abbrevations";
	// validate GLUCTEST, GDM, RISKTEST
	if ($line[5] !== 1 and $line[5] !== 2)
		$errors[] = "GLUCTEST MUST be either 1 or 2";
	if ($line[6] !== 1 and $line[6] !== 2)
		$errors[] = "GDM MUST be either 1 or 2";
	if ($line[7] !== 1 and $line[7] !== 2)
		$errors[] = "RISKTEST MUST be either 1 or 2";
	// validate AGE
	if (preg_match('/[^0-9]/', $line[8]))
		$errors[] = "AGE must contain numeric characters only";
	if (intval($line[8]) < 18 or intval($line[8]) > 125)
		$errors[] = "AGE must be an integer value 18-125";
	// validate ETHNIC
	if (!in_array($line[9], ["1", "2", "9"]))
		$errors[] = "ETHNIC MUST be either \"1\", \"2\", or \"9\"";
	// validate race values
	if (!in_array($line[10], ["1", "2"]))
		$errors[] = "AIAN MUST be either \"1\" or \"2\"";
	if (!in_array($line[11], ["1", "2"]))
		$errors[] = "ASIAN MUST be either \"1\" or \"2\"";
	if (!in_array($line[12], ["1", "2"]))
		$errors[] = "BLACK MUST be either \"1\" or \"2\"";
	if (!in_array($line[13], ["1", "2"]))
		$errors[] = "NHOPI MUST be either \"1\" or \"2\"";
	if (!in_array($line[14], ["1", "2"]))
		$errors[] = "WHITE MUST be either \"1\" or \"2\"";
	// validate SEX
	if (!in_array($line[15], ["1", "2", "9"]))
		$errors[] = "SEX MUST be either \"1\", \"2\", or \"9\"";
	// validate HEIGHT
	if (preg_match('/[^0-9]/', $line[16]))
		$errors[] = "HEIGHT value must contain numeric characters only";
	if (intval($line[16]) < 30 or intval($line[16]) > 98)
		$errors[] = "HEIGHT value must be an integer value 30-98";
	// validate EDU
	if (!in_array($line[17], ["1", "2", "3", "4", "9"]))
		$errors[] = "EDU MUST be either \"1\" - \"4\", or \"9\"";
	// validate DMODE
	if (!in_array($line[18], ["1", "2", "3"]))
		$errors[] = "DMODE MUST be \"1\" - \"3\"";
	// validate SESSID
	if (!in_array($line[19], ["88", "99"]) and intval($line[19]) < 1 and intval($line[19]) > 26)
		$errors[] = "SESSID MUST be an integer from 1-26 or 88 or 99";
	// validate SESSTYPE
	if (!in_array($line[20], ["C", "CM", "OM", "MU"]))
		$errors[] = 'SESSTYPE must be one of these values: "C", "CM", "OM", "MU"';
	// validate DATE
	$month = substr($line[21], 0, 2);
	$day = substr($line[21], 3, 2);
	$year = substr($line[21], 6, 4);
	if (preg_match("/[^0-9\/]/", $line[21]) or !checkdate($month, $day, $year))
		$errors[] = "DATE must be a valid date in format 'mm/dd/yyyy'";
	// validate WEIGHT
	if (preg_match('/[^0-9]/', $line[22]))
		$errors[] = "WEIGHT must contain numeric characters only";
	if ((intval($line[22]) < 70 or intval($line[22]) > 997) and intval($line[22]) !== 999)
		$errors[] = "WEIGHT must be an integer value 70-997 OR 999 (if not reported)";
	// validate PA
	if (preg_match('/[^0-9]/', $line[23]))
		$errors[] = "PA must contain numeric characters only";
	if ($line[23] == "" or $line[23] == null or ((intval($line[23]) < 0 or intval($line[23]) > 997) and intval($line[23]) !== 999))
		$errors[] = "PA must be an integer value 0-997 OR 999 (if not reported)";
	
	// append error messages to end of line array
	$line = array_merge($line, $errors);
}

// send cdc export as csv to user
function sendExport() {
	global $orgcode;
	$headers = [
		"ORGCODE",	//0
		"PARTICIP",
		"ENROLL",
		"PAYER",
		"STATE",
		"GLUCTEST",	//5
		"GDM",
		"RISKTEST",
		"AGE",
		"ETHNIC",
		"AIAN",		//10
		"ASIAN",
		"BLACK",
		"NHOPI",
		"WHITE",
		"SEX",		//15
		"HEIGHT",
		"EDU",
		"DMODE",
		"SESSID",
		"SESSTYPE",	//20
		"DATE",
		"WEIGHT",
		"PA"
	];
	
	$data = [$headers];
	$noncompliant = [$headers];
	$today = new DateTime("NOW");
	
	// get all participant data
	$records = \REDCap::getData(PROJECT_ID);
	$project = new \Project(PROJECT_ID);
	
	// get year value for when these records were created or first logged update
	$recordCreationDates = [];
	$sql = "SELECT pk, ts FROM redcap_log_event WHERE project_id=" . PROJECT_ID . " and pk in (" . implode(array_keys($records), ", ") . ") and (description=\"Create record\" OR description=\"Update record\") ORDER BY pk ASC, ts ASC";
	$query = db_query($sql);
	while ($row = db_fetch_assoc($query)) {
		if (!isset($recordCreationDates[$row['pk']])) {
			$recordCreationDates[$row['pk']] = substr($row['ts'], 2, 2);
		}
	}
	
	// regex for getting labels for project fields (like state, sess_type, etc)
	$labelPattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
	
	foreach ($records as $rid => $record) {
		$eid = array_keys($record)[0];
		
		// skip if orgcode set and not match
		if (isset($orgcode) and $orgcode != $record[$eid]['orgcode']) continue;
		
		$line = array_fill(0, 23, null);
		$line[0] = $record[$eid]['orgcode'];
		
		// determine participant ID
		$participantID = $recordCreationDates[$rid];
		preg_match_all($labelPattern, $project->metadata['participant_id_group']['element_enum'], $matches);
		$participantID .= trim($matches[2][$record[$eid]['participant_id_group'] - 1]);
		$participantID .= $record[$eid]['participant_id_group'];
		preg_match_all("/\d+/", $record[$eid]['participant_employee_id'], $matches);
		$participantID .= $matches[0][0];
		
		$line[1] = $participantID;
		$line[2] = $record[$eid]['program_referral'] === null ? 10 : $record[$eid]['program_referral'];
		$line[3] = $record[$eid]['payer'] === null ? 9 : $record[$eid]['payer'];
		preg_match_all($labelPattern, $project->metadata['state']['element_enum'], $matches);
		preg_match("/- ([A-Z]{2})(?:\s|$)/", $matches[2][$record[$eid]['state'] - 1], $matches);
		$line[4] = $matches[1];
		$line[5] = $record[$eid]['gluctest'] == 1 ? 1 : 2;
		$line[6] = $record[$eid]['gdm'] == 1 ? 1 : 2;
		$line[7] = $record[$eid]['risktest'] == 1 ? 1 : 2;
		$dob = new DateTime($record[$eid]['dob']);
		$line[8] = $dob->diff($today)->format('%y');
		$line[9] = $record[$eid]['ethnicity'] == null ? 9 : $record[$eid]['ethnicity'];
		$line[10] = $record[$eid]['race'][1] == 1 ? 1 : 2;
		$line[11] = $record[$eid]['race'][2] == 1 ? 1 : 2;
		$line[12] = $record[$eid]['race'][3] == 1 ? 1 : 2;
		$line[13] = $record[$eid]['race'][4] == 1 ? 1 : 2;
		$line[14] = $record[$eid]['race'][5] == 1 ? 1 : 2;
		$line[15] = $record[$eid]['sex'] == null ? 9 : $record[$eid]['sex'];
		preg_match_all("/[0-9]{1,2}/", $record[$eid]['height'], $matches);
		$line[16] = @$matches[0][0] * 12 + $matches[0][1];
		$line[17] = $record[$eid]['education'] == null ? 9 : $record[$eid]['education'];
		
		foreach ($record['repeat_instances'][$eid]['sessionscoaching_log'] as $i => $instance) {
			$line[18] = $instance['sess_mode'];
			$line[19] = $instance['sess_id'];
			preg_match_all($labelPattern, $project->metadata['sess_type']['element_enum'], $matches);
			preg_match_all("/\(([A-Z]|[A-Z][A-Z])\)/", $matches[2][$instance['sess_type'] - 1], $matches);
			$line[20] = $matches[1][0];
			$line[21] = date("m/d/Y", strtotime($instance['sess_date']));
			$line[22] = $instance['sess_weight'];
			$line[23] = $instance['sess_pa'];
			
			validateLine($line);
			
			// if error messages were appended...
			if (isset($line[24]) and isset($_GET['noncompliant'])) {
				$noncompliant[] = $line;
			}
			
			// no errors and no non-compliant param
			if (!isset($line[24]) and !isset($_GET['noncompliant'])) {
				$data[] = $line;
			}
		}
	}
	
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="DPRP CDC Export.csv"');
	$fp = fopen('php://output', 'wb');
	
	// non-compliance report
	if (isset($_GET['noncompliant'])) {
		$data = $noncompliant;
	}
	
	foreach ($data as $line) {
		fputcsv($fp, $line, ',');
	}
	fclose($fp);
}

// // test record fetching / regex
// $records = \REDCap::getData(PROJECT_ID, 'array');
// $project = new \Project(PROJECT_ID);
// $labelPattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
// preg_match_all($labelPattern, $project->metadata['sess_type']['element_enum'], $matches);
// echo("<pre>");
// print_r($matches);
// echo("</pre>");

// // test getEmployeeID
// echo("<pre>");
// $vunetid = "reedcw1";
// print_r(getEmployeeID($vunetid));
// echo("</pre>");


sendExport();