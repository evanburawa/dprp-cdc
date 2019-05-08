<?php
require('config.php');

// send cdc export as csv to user
function sendExport() {
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="DPRP CDC Export.csv"');
	$headers = [
		"ORGCODE",
		"PARTICIP",
		"ENROLL",
		"PAYER",
		"STATE",
		"GLUCTEST",
		"GDM",
		"RISKTEST",
		"AGE",
		"ETHNIC",
		"AIAN",
		"ASIAN",
		"BLACK",
		"NHOPI",
		"WHITE",
		"SEX",
		"HEIGHT",
		"EDU",
		"DMODE",
		"SESSID",
		"SESSTYPE",
		"DATE",
		"WEIGHT",
		"PA"
	];
	
	$data = [$headers];
	// get all participant data
	// TODO: exclude from report if ever had type 1 or type 2
	
	$fp = fopen('php://output', 'wb');
	foreach ($data as $line) {
		fputcsv($fp, $line, ',');
	}
	fclose($fp);
}

sendExport();