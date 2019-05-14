<?php
require('config.php');

// send cdc export as csv to user
function sendExport() {
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
	$today = new DateTime("NOW");
	
	// get all participant data
	$records = \REDCap::getData(PROJECT_ID, 'array');
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
		
		foreach ($record['repeat_instances'][$eid]['sessionscoaching_log'] as $i => $instance) {
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
			preg_match_all("/[\d]+/", $record[$eid]['height'], $matches);
			$line[16] = @$matches[0][0] * 12 + $matches[0][1];
			$line[17] = $record[$eid]['education'] == null ? 9 : $record[$eid]['education'];
			$line[18] = $instance['sess_mode'];
			$line[19] = $instance['sess_id'];
			preg_match_all($labelPattern, $project->metadata['sess_type']['element_enum'], $matches);
			preg_match_all("/\(([A-Z][A-Z])\)/", $matches[2][$instance['sess_type']], $matches);
			$line[20] = $matches[1][0];
			$line[21] = date("m/d/Y", strtotime($instance['sess_date']));
			$line[22] = $instance['sess_weight'];
			$line[23] = $instance['sess_pa'];
			
			if ($record[$eid]['have_diabetes'] != 1) {
				$data[] = $line;
			}
		}
	}
	
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="DPRP CDC Export.csv"');
	$fp = fopen('php://output', 'wb');
	foreach ($data as $line) {
		fputcsv($fp, $line, ',');
	}
	fclose($fp);
}

// $records = \REDCap::getData(PROJECT_ID, 'array');
// $project = new \Project(PROJECT_ID);
// echo("<pre>");
// print_r($project->metadata);
// echo("</pre>");

sendExport();