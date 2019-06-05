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
	// make DPP file
	$spreadsheet = IOFactory::load("masterTemplate.xlsx");
	
	// this will hold data that we want to write to DPP excel file
	$dppData = [];
	
	// iterate through records
	$records = \REDCap::getData(PROJECT_ID);
	$row = 2;
	foreach ($records as $rid => $record) {
		$participant = [];
		$eid = array_keys($record)[0];
		
		// add last name, first name, emp id, org code
		$participant[] = $record[$eid]["last_name"];
		$participant[] = $record[$eid]["first_name"];
		$participant[] = $record[$eid]["participant_employee_id"];
		$participant[] = $record[$eid]["orgcode"];
		
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
	
	// make weekly loss stat arrays doing calculations in PHP
	// we will add these to dppData a bit further down
	$stat_ave = ["Average", NULL, NULL, NULL, "=ROUND(AVERAGE(E2:E$row), 0)"];
	$stat_goal7 = ["Weekly Wt Loss", NULL, NULL, "7% goal", "N/A"];
	$stat_goal5 = [NULL, NULL, NULL, "5%", "N/A"];
	$stat_totalLoss = ["Total Wt Loss", NULL, NULL, NULL, "N/A"];
	$stat_percentLoss = ["Percent Loss", NULL, NULL, NULL, "N/A"];
	
	/*		// write values directly
	for ($i = 1; $i <= 16; $i++) {
		$weightSum = 0;
		$weightCount = 0;
		for ($j = 0; $j <= count($records); $j++) {
			if (!empty($dppData[$j][3 + $i])) {
				$weightSum += $dppData[$j][3 + $i];
				$weightCount++;
			}
		}
		
		$stat_ave[3 + $i] = NULL;
		$stat_goal7[3 + $i] = NULL;
		$stat_goal5[3 + $i] = NULL;
		$stat_totalLoss[3 + $i] = NULL;
		$stat_percentLoss[3 + $i] = NULL;
		
		if ($weightCount != 0) {
			$stat_ave[3 + $i] = round($weightSum / $weightCount);
			if (!empty($lastWeightSum)) {
				$stat_totalLoss[3 + $i] = round($lastWeightSum - $weightSum);
				$stat_goal7[3 + $i] = round($lastWeightSum * .07);
				$stat_goal5[3 + $i] = round($lastWeightSum * .05);
				$stat_percentLoss[3 + $i] = round(($lastWeightSum - $weightSum) * 100 / $lastWeightSum, 1) . "%";
			}
			$lastWeightSum = $weightSum;
		}
	}
	*/
	
	file_put_contents("log.txt", "start log");
	
	// write formulas for stat cells
	$lastCol = "E";
	$lastRow = $row - 1 ;
	$lastRow5 = $lastRow + 5;
	for ($i = 2; $i <= 25; $i++) {
		if ($i > 16) {
			$col = numberToExcelColumn(6 + $i);
		} else {
			$col = numberToExcelColumn(3 + $i);
		}
		
		$range = "$col" . "2:$col" . $lastRow;
		$lastRange = "$lastCol" . "2:$lastCol" . $lastRow;
		
		if ($i == 17) {	// handle post-core session 1 column
			$stat_ave[] = "=ROUND(AVERAGE($range), 0)";
			$stat_goal7[] = "N/A";
			$stat_goal5[] = "N/A";
			$stat_totalLoss[] = "N/A";
			$stat_percentLoss[] = "N/A";
		} else {
			$stat_ave[] = "=ROUND(AVERAGE($range), 0)";
			$stat_goal7[] = "=ROUND(0.07*SUM($lastRange), 1)";
			$stat_goal5[] = "=ROUND(0.05*SUM($lastRange), 1)";
			$stat_totalLoss[] = "=ROUND(SUMIF($range, \"<>\", $lastRange) - SUMIF($lastRange, \"<>\", $range), 0)";
			$stat_percentLoss[] = "=ROUND({$col}{$lastRow5} * 100 / SUMIF($range, \"<>\", $lastRange), 1) & \"%\"";
		}
		if ($i == 16) {		// add 3 blank cells to each stat row (for WT LOSS CORE and other calc columns)
			for ($j = 1; $j <= 3; $j++) {
				$stat_ave[] = NULL;
				$stat_goal7[] = NULL;
				$stat_goal5[] = NULL;
				$stat_totalLoss[] = NULL;
				$stat_percentLoss[] = NULL;
			}
		}
		$lastCol = $col;
	}
	
	$dppData[] = [];	// insert blank row
	$dppData[] = $stat_ave;
	$dppData[] = $stat_goal7;
	$dppData[] = $stat_goal5;
	$dppData[] = $stat_totalLoss;
	$dppData[] = $stat_percentLoss;
	
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
