<?php
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

require "config.php";
require "libs/PhpSpreadsheet/vendor/autoload.php";
require_once "libs/PhpSpreadsheet/src/Bootstrap.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function numberToExcelColumn($n) {
    for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
        $r = chr($n%26 + 0x41) . $r;
    return $r;
}

if ($_GET['action'] == 'export') {
	// iterate through records
	$records = \REDCap::getData(PROJECT_ID);
	
	// regex for getting labels for project fields (like state, sess_type, etc)
	$labelPattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
	$project = new \Project(PROJECT_ID);
	
	// make DPP file
	$spreadsheet = IOFactory::load("masterTemplate.xlsx");
	
	// this will hold data that we want to write to DPP excel file
	$dppData = [];
	
	$row = 2;
	foreach ($records as $rid => $record) {
		$participant = [];
		$eid = array_keys($record)[0];
		
		// add last name, first name, emp id, org code
		$participant[] = $record[$eid]["last_name"];
		$participant[] = $record[$eid]["first_name"];
		$participant[] = $record[$eid]["participant_employee_id"];
		
		preg_match_all($labelPattern, $project->metadata['status']['element_enum'], $matches);
		$participant[] = trim($matches[2][$record[$eid]['status'] - 1]);
		// $participant[] = $record[$eid]["status"];
		
		// add sessions 1-16 weights
		for ($i = 1; $i <= 25; $i++) {
			$instance = $record["repeat_instances"][$eid]["sessionscoaching_log"][$i];
			$participant[] = $instance["sess_weight"];
			if ($i == 16) {
				// add WT LOSS CORE, % CHANGE CORE, and Number sessions attended formulas
				$participant[] = "=E$row - LOOKUP(2,1/(ISNUMBER(E$row:T$row)), E$row:T$row)";
				$participant[] = "=ROUND(U$row / E$row, 3) * 100 & \"%\"";
				$participant[] = "=COUNTA(E$row:T$row)";
			}
			if ($i == 25) {
				// add final 5 formula cells
				$participant[] = "=LOOKUP(2,1/(ISNUMBER(E$row:T$row)), E$row:T$row) - LOOKUP(2,1/(ISNUMBER(X$row:AF$row)), X$row:AF$row)";
				$participant[] = "=U$row+AG$row";
				$participant[] = "=ROUND(AH$row / E$row, 3) * 100 & \"%\"";
				$participant[] = "=COUNTA(X$row:AF$row)";
				$participant[] = "=W$row+AJ$row";
			}
		}
		$dppData[] = $participant;
		$row++;
	}
	$lastRow = $row - 1;
	
	// make weekly loss stat arrays doing calculations in PHP
	// we will add these to dppData a bit further down
	$stat_sum = ["Group weight—sum", NULL, NULL, NULL];
	$stat_ave = ["Group weight—average", NULL, NULL, NULL];
	$stat_weekly = ["Weekly weight loss—group", NULL, NULL, NULL];
	
	$row = $lastRow + 2;
	$row_3 = $row + 3;
	$stat_program = ["Program weight loss—group", NULL, NULL, "=E$row - LOOKUP(2,1/(ISNUMBER(E$row:AF$row)), E$row:AF$row)"];
	$stat_percent = ["Percent loss", NULL, NULL, "=ROUND((D$row_3/E$row), 3) * 100 & \"%\""];
	$stat_goal7 = ["Program weight loss goal—7%", NULL, NULL, "=ROUND(0.07*SUM(E2:E$lastRow), 0)"];
	$stat_goal5 = ["Program weight loss goal—5%", NULL, NULL, "=ROUND(0.05*SUM(E2:E$lastRow), 0)"];
	
	// file_put_contents("log.txt", "start log");
	
	// write formulas for stat_sum, stat_ave, stat_weekly
	$lastCol = "E";
	for ($i = 1; $i <= 25; $i++) {
		if ($i > 16) {
			$col = numberToExcelColumn(6 + $i);
		} else {
			$col = numberToExcelColumn(4 + $i);
		}
		
		$stat_sum[] = "=SUM({$col}2:$col$lastRow)";
		$stat_ave[] = "=ROUND(AVERAGE({$col}2:$col$lastRow), 0)";
		if ($i == 1 or $i == 17) {
			$stat_weekly[] = 0;
		} else {
			$stat_weekly[] = "=$lastCol$row - $col$row";
		}
		
		// file_put_contents("log.txt", "\n formula written: \"=AVERAGE({$col}2:{$col}{$lastRow})\"", FILE_APPEND);
		$lastCol = $col;
		if ($i == 16) {		// add 3 blank cells to each stat row (for WT LOSS CORE and other calc columns)
			for ($j = 1; $j <= 3; $j++) {
				$stat_sum[] = NULL;
				$stat_ave[] = NULL;
				$stat_weekly[] = NULL;
			}
		}
	}
	
	$dppData[] = [];	// insert blank row
	$dppData[] = $stat_sum;
	$dppData[] = $stat_ave;
	$dppData[] = $stat_weekly;
	$dppData[] = $stat_program;
	$dppData[] = $stat_percent;
	$dppData[] = [];	// insert blank row
	$dppData[] = $stat_goal7;
	$dppData[] = $stat_goal5;
	
	// write dpp data to spreadsheet
	$spreadsheet->getActiveSheet()
		->fromArray(
			$dppData,
			NULL,
			"A2"
	);
	
	// Set active sheet index to the first sheet, so Excel opens this as the first sheet
	$spreadsheet->setActiveSheetIndex(0);
	
	// Redirect output to a client’s web browser (Xlsx)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="DPP Participant Sessions.xlsx"');
	header('Cache-Control: max-age=0');
	
	// // If you're serving to IE over SSL, then the following may be needed
	// header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
	// header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
	// header('Cache-Control: cache, must-revalidate, max-age=1'); // HTTP/1.1
	// header('Pragma: public'); // HTTP/1.0
	
	$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
}
