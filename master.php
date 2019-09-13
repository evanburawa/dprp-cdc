<?php
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

// changing this array should suffice to correct for moved columns in the masterTemplate.xlsx file
$columns = [
	"org" => "C",
	"s1" => "D",
	"s2" => "E",
	"s16" => "S",
	"s17" => "W",
	"stat_1a" => "T",
	"stat_1b" => "U",
	"stat_1c" => "V",
	"s18" => "X",
	"s28" => "AH",
	"stat_2a" => "AI",
	"stat_2b" => "AJ",
	"stat_2c" => "AK",
	"stat_2d" => "AL",
	"last" => "AM",
	"offset1" => 2,
	"offset2" => 5,
	"table2cols" => [2, "U", "V", "T", "AJ", "AK", "AL", "AM", "AI"]
];

function appendStatRows(&$sheetMatrix) {
	// REDCap::logEvent("DPRP", "In appendStatRows()", null, $rid, $eid, PROJECT_ID);
	// create and append rows of statistics that should show below participant data rows
	
	global $columns;
	$stat_sum = ["Group weight—sum", NULL, NULL];
	$stat_ave = ["Group weight—average", NULL, NULL];
	$stat_weekly = ["Weekly weight loss—group", NULL, NULL];
	
	$lastRow = count($sheetMatrix) + 1;
	$sumRow = $lastRow + 2;
	$weeklyRow = $lastRow + 4;
	$programRow = $lastRow + 5;
	
	$stat_program = ["Program weight loss—group", NULL, "=SUM({$columns['s2']}$weeklyRow:{$columns['s28']}$weeklyRow)"];
	$stat_percent = ["Percent loss", NULL, "=ROUND(({$columns['org']}$programRow/{$columns['s1']}$sumRow), 3) * 100 & \"%\""];
	$stat_goal7 = ["Program weight loss goal—7%", NULL, "=ROUND(0.07*SUM({$columns['s1']}2:{$columns['s1']}$lastRow), 0)"];
	$stat_goal5 = ["Program weight loss goal—5%", NULL, "=ROUND(0.05*SUM({$columns['s1']}2:{$columns['s1']}$lastRow), 0)"];
	
	// write formulas for stat_sum, stat_ave, stat_weekly -- fill in arrays with formula values
	for ($i = 1; $i <= 28; $i++) {
		if ($i > 16) {
			$col = numberToExcelColumn($columns["offset2"] + $i);
		} else {
			$col = numberToExcelColumn($columns['offset1'] + $i);
		}
		
		// // need more complicated summing / averaging formulae
		// $stat_sum[] = "=SUM({$col}2:$col$lastRow)";
		// $stat_ave[] = "=ROUND(AVERAGE({$col}2:$col$lastRow), 0)";
		
		$average_formula = "=ROUND(AVERAGE(";
		$sum_formula = "=ROUND(SUM(";
		for ($j = 1; $j <= count($sheetMatrix); $j++) {
			$j2 = $j + 1;
			$range1 = "{$columns['s1']}$j2:$col{$j2}";
			$last_value1 = "LOOKUP(2, 1/(ISNUMBER($range1)), $range1)";
			if ($i > 16) {
				$range1 = "{$columns['s1']}$j2:{$columns['s16']}{$j2}";
				$last_value1 = "LOOKUP(2, 1/(ISNUMBER($range1)), $range1)";
				$range2 = "{$columns['s17']}$j2:$col{$j2}";
				$last_value2 = "LOOKUP(2, 1/(ISNUMBER($range2)), $range2)";
				$formula = "IF(ISERROR($last_value2), $last_value1, $last_value2)";
				$average_formula .= $j == count($sheetMatrix) ? "$formula), 0)" : "$formula, ";
				$sum_formula .= $j == count($sheetMatrix) ? "$formula), 0)" : "$formula, ";
			} else {
				$average_formula .= $j == count($sheetMatrix) ? "$last_value1), 0)" : "$last_value1, ";
				$sum_formula .= $j == count($sheetMatrix) ? "$last_value1), 0)" : "$last_value1, ";
			}
		}
		$stat_sum[] = $sum_formula;
		$stat_ave[] = $average_formula;
		
		if ($i == 1 or $i == 17) {
			$stat_weekly[] = 0;
		} else {
			$range1 = "$col" . "2:$col$lastRow";
			$range2 = "$lastCol" . "2:$lastCol$lastRow";
			$stat_weekly[] = "=SUMIF($range1, \"<>\", $range2) - SUMIF($range2, \"<>\", $range1)";
		}
		
		$lastCol = $col;
		if ($i == 16) {		// add 3 blank cells to each stat row (for WT LOSS CORE and other calc columns)
			for ($j = 1; $j <= 3; $j++) {
				$stat_sum[] = NULL;
				$stat_ave[] = NULL;
				$stat_weekly[] = NULL;
			}
		}
	}
	
	// append
	$sheetMatrix[] = [];	// insert blank row
	$sheetMatrix[] = $stat_sum;
	$sheetMatrix[] = $stat_ave;
	$sheetMatrix[] = $stat_weekly;
	$sheetMatrix[] = $stat_program;
	$sheetMatrix[] = $stat_percent;
	$sheetMatrix[] = [];	// insert blank row
	$sheetMatrix[] = $stat_goal7;
	$sheetMatrix[] = $stat_goal5;
}

function appendTableTwo(&$sheetMatrix) {
	// REDCap::logEvent("DPRP", "In appendTableTwo", null, $rid, $eid, PROJECT_ID);
	global $records;
	global $project;
	global $workbook;
	global $labelPattern;
	global $columns;
	$sheetNumber = 1;
	
	$participants = [];
	foreach ($records as $rid => $record) {
		$eid = array_keys($record)[0];
		$sessions = &$records[$rid]["repeat_instances"][$eid]["sessionscoaching_log"];
		
		// see if this participant needs to be added to this sheet
		preg_match_all($labelPattern, $project->metadata['coach_name']['element_enum'], $matches);
		$coach = trim($matches[2][$record[$eid]['coach_name'] - 1]);
		preg_match_all($labelPattern, $project->metadata['cohort']['element_enum'], $matches);
		$cohort = trim($matches[2][$record[$eid]['cohort'] - 1]);
		
		$sheetTitle = $workbook->getSheet($sheetNumber)->getTitle();
		$need_record_for_non_conform_sheet = ($sheetTitle == "MISSING COACH OR COHORT VALUE" and (empty($cohort) or empty($coach)));
		if ($sheetTitle != $coach . " -- " . $cohort and $sheetTitle != "Header" and !$need_record_for_non_conform_sheet)
			continue;
		unset($coach, $cohort, $sheetTitle, $need_record_for_non_conform_sheet);
		
		$participant = [];
		$participant[] = $record[$eid]["last_name"];
		$participant[] = $record[$eid]["first_name"];
		preg_match_all($labelPattern, $project->metadata['status']['element_enum'], $matches);
		$participant[] = trim($matches[2][$record[$eid]['status'] - 1]);
		
		$orgcode = $record[$eid]["orgcode"];
		
		// add physical activity and possibly other info to spreadsheet
		for ($i = 1; $i <= 28; $i++) {
			if (!isset($sessions[$i]))
				continue;
			if ($i > 16) {
				$col = numberToExcelColumn($columns['offset2'] + $i);
			} else {
				$col = numberToExcelColumn($columns['offset1'] + $i);
			}
			
			if ($i == 17) {
				// skip 3 stat columns
				for ($j = 1; $j<= 3; $j++) {
					$participant[] = NULL;
				}
			}
			
			$cell_value = "";
			
			// add physical activity minutes if possible
			if (!empty($sessions[$i]["sess_pa"])) {
				$cell_value .= $sessions[$i]["sess_pa"];
			}
			
			// add date if actual date present and not same as scheduled date
			if (!empty($sessions[$i]["sess_actual_date"]) and ($sessions[$i]["sess_actual_date"] != $sessions[$i]["sess_scheduled_date"])) {
				$act_date = new DateTime($sessions[$i]["sess_actual_date"]);
				$cell_value .= ", " . $act_date->format("m/d/Y");
			}
			
			// highlight if this was a make-up session
			if ($sessions[$i]["sess_type"] == 3) {
				$cell_address = $col . (1 + count($sheetMatrix) + 3);
				// file_put_contents("C:/vumc/log.txt", "highlighting cell at $cell_address for participant $rid, session $i\n", FILE_APPEND);
				$workbook->getSheet($sheetNumber)->getStyle($cell_address)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
				// $cell_value .= ", HIGHLIGHT_THIS_CELL";
				// we will check for this token later and remove it, while highlighting the relevant cell
				// this is because it would be hard to find out which cell this is right now
			}
			
			// add token for sess_mode if needed
			if ($orgcode == "8540168") {							// participant is in Digital group
				if ($sessions[$i]["sess_mode"] == 1)
					$cell_value .= ", I";
				if ($sessions[$i]["sess_mode"] == 2)
					$cell_value .= ", O";
			} elseif ($orgcode == "792184") {						// participant is in In-Person group
				if ($sessions[$i]["sess_mode"] == 2)
					$cell_value .= ", O";
				if ($sessions[$i]["sess_mode"] == 3)
					$cell_value .= ", D";
			}
			
			$participant[] = $cell_value;
		}
		$participants[] = $participant;
	}
	
	// add empty row, then header, then table 2 data to sheetMatrix
	$sheetMatrix[] = [];
	
	$header = $workbook->getSheet(1)->rangeToArray("A1:{$columns['last']}1", NULL, TRUE, TRUE, TRUE)[1];
	$temp_header_array = [];
	foreach ($header as $col => $value) {
		if (array_search($col, $columns['table2cols'])) {
			$temp_header_array[] = NULL;
		} elseif ($value == "TABLE 1: WEIGHT\nLAST NAME") {
			$temp_header_array[] = "TABLE 2: PHYSICAL ACTIVITY\nLAST NAME";
		} else {
			$temp_header_array[] = $value;
		}
	}
	$sheetMatrix[] = $temp_header_array;
	
	// style new header row
	$header_cells_range = "A" . (1 + count($sheetMatrix)) . ":{$columns['last']}" . (1 + count($sheetMatrix));
	$workbook->getSheet($sheetNumber)->getStyle($header_cells_range)->getFont()->setBold(true);
	$workbook->getSheet($sheetNumber)->getStyle($header_cells_range)->getAlignment()->setWrapText(true);
	$workbook->getSheet($sheetNumber)->getStyle($header_cells_range)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
	$workbook->getSheet($sheetNumber)->getStyle($columns['org'] . (1 + count($sheetMatrix)))->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
	$workbook->getSheet($sheetNumber)->getRowDimension((string) 1 + count($sheetMatrix))->setRowHeight(45.75);
	
	foreach ($participants as $p) {
		$sheetMatrix[] = $p;
	}
}

function appendLegend(&$sheetMatrix) {
	// copy legend
	global $workbook;
	global $sheetValues;
	$row = count($sheetValues) + 3;
	$legend = $workbook->getSheet(0)->rangeToArray('A27:C35');
	$workbook->getSheet(1)->fromArray($legend, null, "A$row");
	$workbook->getSheet(1)->duplicateStyle($workbook->getSheet(0)->getStyle("A27"), "A$row");
}

// get actual coach/cohort values
$pid = (int) $_GET['pid'];
$coach_actual = $_GET['coach'];
$cohort_actual = $_GET['cohort'];

// regex for getting labels for project fields (like state, sess_type, etc)
$labelPattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
$project = new \Project((int) $_GET["pid"]);

// determine to raw values so we can filter records from getData
$foundRawCoachValue = false;
preg_match_all($labelPattern, $project->metadata["coach_name"]["element_enum"], $matches);
foreach ($matches[0] as $value) {
	$arr = explode(",", $value);
	$key = trim($arr[0]);
	$val = trim($arr[1]);
	if ($coach_actual == $val) {
		$coach = $key;
		$foundRawCoachValue = true;
	}
}
$foundRawCohortValue = false;
preg_match_all($labelPattern, $project->metadata["cohort"]["element_enum"], $matches);
foreach ($matches[0] as $value) {
	$arr = explode(",", $value);
	$key = trim($arr[0]);
	$val = trim($arr[1]);
	if ($cohort_actual == $val) {
		$cohort = $key;
		$foundRawCohortValue = true;
	}
}

// get records data
$filterLogic = "[coach_name] = '$coach' and [cohort] = '$cohort'";
$records = \REDCap::getData($pid, null, null, null, null, null, null, null, null, $filterLogic);

$targetRecordIDs = [];
foreach ($records as $rid => $record) {
	$targetRecordIDs[] = $rid;
}

$records = \REDCap::getData($pid, 'array', $targetRecordIDs);

// make DPP file
$workbook = IOFactory::load("masterTemplate.xlsx");

// append weight table (table 1) to "Header" sheet
$row = 2;
$sheetValues = [];
$session_scheduled_dates = [];
foreach ($records as $rid => $record) {
	$eid = array_keys($record)[0];
	
	// add last name, first name, emp id, org code
	$participant = [];
	$participant[] = $record[$eid]["last_name"];
	$participant[] = $record[$eid]["first_name"];
	
	preg_match_all($labelPattern, $project->metadata['status']['element_enum'], $matches);
	$participant[] = trim($matches[2][$record[$eid]['status'] - 1]);
	
	// add sessions 1-28 weights
	for ($i = 1; $i <= 28; $i++) {
		$instance = $record["repeat_instances"][$eid]["sessionscoaching_log"][$i];
		
		// find set header date value to scheduled session date
		if (empty($session_scheduled_dates[$i]) and !empty($instance['sess_scheduled_date'])) {
			$session_scheduled_dates[$i] = $instance['sess_scheduled_date'];
		}
		
		$participant[] = $instance["sess_weight"];
		if ($i == 16) {
			// add WT LOSS CORE, % CHANGE CORE, and Number sessions attended formulas
			$participant[] = "={$columns['s1']}$row - LOOKUP(2,1/(ISNUMBER({$columns['s1']}$row:{$columns['s16']}$row)), {$columns['s1']}$row:{$columns['s16']}$row)";
			$participant[] = "=ROUND({$columns['stat_1a']}$row / {$columns['s1']}$row, 3) * 100 & \"%\"";
			$participant[] = "=COUNTA({$columns['s1']}$row:{$columns['s16']}$row)";
		}
		if ($i == 28) {
			// add final 5 formula cells
			$participant[] = "=LOOKUP(2,1/(ISNUMBER({$columns['s1']}$row:{$columns['s16']}$row)), {$columns['s1']}$row:{$columns['s16']}$row) - LOOKUP(2,1/(ISNUMBER({$columns['s17']}$row:{$columns['s28']}$row)), {$columns['s17']}$row:{$columns['s28']}$row)";
			$participant[] = "={$columns['stat_1a']}$row+{$columns['stat_2a']}$row";
			$participant[] = "=ROUND({$columns['stat_2b']}$row / {$columns['s1']}$row, 3) * 100 & \"%\"";
			$participant[] = "=COUNTA({$columns['s17']}$row:{$columns['s28']}$row)";
			$participant[] = "={$columns['stat_1c']}$row+{$columns['stat_2d']}$row";
		}
	}
	
	$sheetValues[] = $participant;
	$row++;
}

// update header scheduled dates with collected schedule dates
// file_put_contents("C:/vumc/log.txt", "logging...\n");
$workbook->setActiveSheetIndex(1);
foreach ($session_scheduled_dates as $i => $date) {
	list($year, $month, $day) = explode("-", $date);
	$col = $i > 16 ? $i + 6 : $i + 3;
	// file_put_contents("C:/vumc/log.txt", "pieces: " . print_r($pieces, true) . "\n", FILE_APPEND);
	if (checkdate($month, $day, $year)) {
		// file_put_contents("C:/vumc/log.txt", "setting cell col: $i, row: 1, with date: " . "$month/$day/$year\n", FILE_APPEND);
		$workbook->getActiveSheet()->setCellValueByColumnAndRow($col, 1, "SESSION $i\n$month/$day/$year");
	}
}

appendStatRows($sheetValues);

appendTableTwo($sheetValues);

// debugging
// $records = \REDCap::getData($pid, null, null, null, null, null, null, null, null, $filterLogic);
// file_put_contents("C:/log.txt", "debugging:\n");
// file_put_contents("C:/log.txt", "records -> \n" . print_r($records, true) . "\n", FILE_APPEND);

// finally write sheetValues to workbook
$workbook->getSheet(1)
	->fromArray(
		$sheetValues,
		NULL,
		"A2"
	);
$workbook->getActiveSheet()->setTitle("DPRP Sessions");

appendLegend();

$workbook->setActiveSheetIndex(1);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"DPP Workbook File - $coach_actual - $cohort_actual.xlsx\"");
header('Cache-Control: max-age=0');	

$writer = IOFactory::createWriter($workbook, 'Xlsx');
$writer->save('php://output');
REDCap::logEvent("DPRP", "Generated DPRP Workbook File - Coach: $coach_actual - Cohort: $cohort_actual", null, $rid, $eid, PROJECT_ID);