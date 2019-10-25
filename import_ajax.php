<?php

/////////////
// from: https://stackoverflow.com/questions/13076480/php-get-actual-maximum-upload-size
function file_upload_max_size() {
  static $max_size = -1;

  if ($max_size < 0) {
    // Start with post_max_size.
    $post_max_size = parse_size(ini_get('post_max_size'));
    if ($post_max_size > 0) {
      $max_size = $post_max_size;
    }

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parse_size(ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size) {
      $max_size = $upload_max;
    }
  }
  return $max_size;
}

function parse_size($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}
/////////////

/////////////
// from: https://stackoverflow.com/questions/15188033/human-readable-file-size
function humanFileSize($size,$unit="") {
  if( (!$unit && $size >= 1<<30) || $unit == "GB")
    return number_format($size/(1<<30),2)."GB";
  if( (!$unit && $size >= 1<<20) || $unit == "MB")
    return number_format($size/(1<<20),2)."MB";
  if( (!$unit && $size >= 1<<10) || $unit == "KB")
    return number_format($size/(1<<10),2)."KB";
  return number_format($size)." bytes";
}
/////////////

function getParticipantRowNumber($firstName, $lastName, $empID) {
	// return row number of 2nd table that has args given
	global $workbook;
	// first, find header row of 2nd table
	$headerRow = 0;
	for ($i = 3; $i <= 19999; $i++) {
		if (strpos($workbook->getActiveSheet()->getCellByColumnAndRow(1, $i)->getValue(), "LAST NAME") !== false) {
			$headerRow = $i;
			break;
		}
	}
	
	if ($headerRow == 0) {
		return "DPP plugin couldn't find 2nd table of participant data. Please see sample master file for formatting help.";
	}
	
	$nextRow = $headerRow + 1;
	while (true) {
		$thisRowLastName = $workbook->getActiveSheet()->getCellByColumnAndRow(1, $nextRow)->getValue();
		$thisRowFirstName = $workbook->getActiveSheet()->getCellByColumnAndRow(2, $nextRow)->getValue();
		$thisRowEmpID = $workbook->getActiveSheet()->getCellByColumnAndRow(3, $nextRow)->getValue();
		if (empty($thisRowLastName) and empty($thisRowFirstName) and empty($thisRowEmpID)) {
			return "DPP plugin couldn't find this participant in 2nd data table (first name, last name, and employee ID must match).";
		} elseif ($thisRowLastName == $lastName and $thisRowFirstName == $firstName and $thisRowEmpID == $empID) {
			return $nextRow;
		}
		$nextRow++;
	}
}

$labelPattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
$labels = [];
function getLabel($rawValue, $fieldName) {
	global $project;
	global $labels;
	global $labelPattern;
	if (empty($labels[$fieldName])) {
		$labels[$fieldName] = [];
		preg_match_all($labelPattern, $project->metadata[$fieldName]['element_enum'], $matches);
		foreach ($matches[0] as $value) {
			$arr = explode(",", $value);
			$key = trim($arr[0]);
			$val = trim($arr[1]);
			$labels[$fieldName][$key] = $val;
		}
	}
	return $labels[$fieldName][$rawValue];
}

// check for $_FILES["workbook"]
if (empty($_FILES["workbook"])) {
	exit(json_encode([
		"error" => true,
		"notes" => [
			"Please attach a workbook file and then click 'Upload'."
		]
	]));
}

// check for transfer errors
if ($_FILES["workbook"]["error"] !== 0) {
	exit(json_encode([
		"error" => true,
		"notes" => [
			"An error occured while uploading your workbook. Please try again."
		]
	]));
}

// have file, so check name, size
$errors = [];
if (preg_match("/[^A-Za-z0-9. ()-]/", $_FILES["workbook"]["name"])) {
	$errors[] = "File names can only contain alphabet, digit, period, space, hyphen, and parentheses characters.";
	$errors[] = "	Allowed characters: A-Z a-z 0-9 . ( ) -";
}

if (strlen($_FILES["workbook"]["name"]) > 127) {
	$errors[] = "Uploaded file has a name that exceeds the limit of 127 characters.";
}

$maxsize = file_upload_max_size();
if ($maxsize !== -1) {
	if ($_FILES["workbook"]["size"] > $maxsize) {
		$fileReadable = humanFileSize($_FILES["workbook"]["size"], "MB");
		$serverReadable = humanFileSize($maxsize, "MB");
		$errors[] = "Uploaded file size ($fileReadable) exceeds server maximum upload size of $serverReadable.";
	}
}

if (!empty($errors)) {
	exit(json_encode([
		"error" => true,
		"notes" => $errors
	]));
}

// open workbook
require "config.php";
require "libs/PhpSpreadsheet/vendor/autoload.php";
require_once "libs/PhpSpreadsheet/src/Bootstrap.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

try {
	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
	$reader->setLoadSheetsOnly("DPP Sessions");
	$workbook = $reader->load($_FILES["workbook"]["tmp_name"]);
	unlink($_FILES["workbook"]["tmp_name"]);
} catch(\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
	REDCap::logEvent("DPP import failure", "PhpSpreadsheet library errors -> " . print_r($e, true) . "\n", null, $rid, $eid, PROJECT_ID);
    exit(json_encode([
		"error" => true,
		"notes" => [
			"There was an issue loading the workbook. Make sure it is an .xlsx file with a worksheet named 'DPP Sessions'.",
			"If you believe your file is a valid DPP Workbook file, please contact your REDCap administrator."
		]
	]));
}

// iterate through participant data and make changes, recording before, after values, or errors
$participants = [];
$done = false;
$row = 2;
$project = new \Project(PROJECT_ID);
while (!$done) {
	$firstName = $workbook->getActiveSheet()->getCellByColumnAndRow(2, $row)->getValue();
	$lastName = $workbook->getActiveSheet()->getCellByColumnAndRow(1, $row)->getValue();
	$empID = $workbook->getActiveSheet()->getCellByColumnAndRow(3, $row)->getValue();
	if (empty($firstName) and empty($lastName) and empty($empID)) {
		$done = true;
	} else {
		// get row number for this participant in 2nd table
		$row2 = getParticipantRowNumber($firstName, $lastName, $empID);
		
		$participant = [
			"firstName" => $firstName,
			"lastName" => $lastName
		];
		
		$records = \REDCap::getData(PROJECT_ID, 'array', NULL, NULL, NULL, NULL, NULL, NULL, NULL, "[first_name] = '$firstName' AND [last_name] = '$lastName'");
		
		if (empty($records) and (!is_int($row2) and !is_string($row2))) {
			$participant["error"] = "The DPP plugin found no record with first name: $firstName, last name: $lastName in the second table.";
		} elseif (is_string($row2)) {
			$participant["error"] = $row2;
		} else {
			$rid = array_keys($records)[0];
			$eid = array_keys($records[$rid])[0];
			$records = \REDCap::getData(PROJECT_ID, 'array', $rid);
			$sessions = &$records[$rid]["repeat_instances"][$eid]["sessionscoaching_log"];
			
			$participant["recordID"] = $rid;
			$participant["before"] = [];
			$participant["after"] = [];
			
			for ($i = 1; $i <= 28; $i++) {
				$offset = ($i >= 17) ? 6 : 3;
				
				$sess_weight = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, $row)->getValue();
				if (empty($sessions[$i]) and !empty($sess_weight)) {
					$sessions[$i] = [];
				}
				
				if (isset($sessions[$i])) {
					// record info so client can build "Before Import:" table
					$participant["before"][$i] = [
						"sess_id" => $sessions[$i]["sess_id"],
						"sess_type" => getLabel($sessions[$i]["sess_type"], "sess_type"),
						"sess_attended" => $sessions[$i]["sess_attended"],
						"sess_mode" => getLabel($sessions[$i]["sess_mode"], "sess_mode"),
						"sess_month" => $sessions[$i]["sess_month"],
						"sess_scheduled_date" => $sessions[$i]["sess_scheduled_date"],
						"sess_actual_date" => $sessions[$i]["sess_actual_date"],
						"sess_weight" => $sessions[$i]["sess_weight"],
						"sess_pa" => $sessions[$i]["sess_pa"]
					];
					
					// -- -- -- -- -- -- -- -- -- -- -- -- --
					// change data in $records to be saved via REDCap::saveData
					
					// establish some defaults
					$sess_id = $i;
					$sess_type = 1; // for "C" = core
					
					// use default group from processing form
					if ((string) $records[$rid][$eid]["orgcode"] == "8540168") {
						$sess_mode = 3; // 3 for "digital / distance learning" in sessions form
					} elseif ((string) $records[$rid][$eid]["orgcode"] == "792184") {
						$sess_mode = 1; // 1 for "In-person"
					}
					
					// get scheduled date (from header row)
					$headerValue = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, 1)->getValue();
					preg_match("/\d{1,2}\/\d{1,2}\/\d{4}/", $headerValue, $matches);
					$sess_scheduled_date = empty($matches) ? NULL : trim($matches[0]);
					
					// must be retrieved from table 2
					$sess_pa = NULL;
					
					$sess_attended = NULL;
					
					$table_2_values = explode(",", $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, $row2)->getValue());
					$table_2_cell_color = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, $row2)->getStyle()->getFill()->getStartColor()->getRGB();
					foreach ($table_2_values as $value) {
						$value = strtoupper(trim($value));
						if (is_numeric($value))
							$sess_pa = (int) $value;
						preg_match("/\d{1,2}\/\d{1,2}\/\d{4}/", $value, $matches);
						$date = empty($matches) ? NULL : $matches[0];
						if ($date !== NULL)
							$sess_actual_date = $date;
						if ($value === "I")
							$sess_mode = 1;
						if ($value === "O")
							$sess_mode = 2;
						if ($value === "D")
							$sess_mode = 3;
						if ($value === "A")
							$sess_attended = 1;
						if ($value == "M" or ($table_2_cell_color != "000000" and $table_2_cell_color != "FFFFFF")) // OR table 2 cell HIGHLIGHTED (TODO)
							$sess_type = 3; // 3 is "MU" or make-up session
					}
					
					// determine sess_month
					$session_1_header_value = $workbook->getActiveSheet()->getCell("E1")->getValue();
					preg_match("/\d{1,2}\/\d{1,2}\/\d{4}/", $session_1_header_value, $matches);
					$sess_month = NULL;
					$sess_date = $sess_actual_date;
					if (empty($sess_actual_date))
						$sess_date = $sess_scheduled_date;
					if (!empty($matches) and !empty($sess_date)) {
						$d1 = new DateTime($matches[0]);
						$d2 = new DateTime($sess_date);
						$sess_month = 12 * ((int) $d2->format("Y") - (int) $d1->format("Y")) + ((int) $d2->format("m") - (int) $d1->format("m")) + 1;
					}
					
					// sess type CORE or CORE MAINTENANCE depending on month (unless make-up)
					if ($sess_month >= 7 and $sess_type == 1) {
						$sess_type = 2;
					}
					
					if (empty($sess_weight) and empty($sess_pa) and $sess_attended != 1) {
						$sess_attended = 0;
					}
					
					// convert date to Y-m-d
					if (!empty($sess_actual_date)) {
						$sess_actual_date = new DateTime($sess_actual_date);
						$sess_actual_date = $sess_actual_date->format("Y-m-d");
					}
					
					// convert date to Y-m-d
					if (!empty($sess_scheduled_date)) {
						$sess_scheduled_date = new DateTime($sess_scheduled_date);
						$sess_scheduled_date = trim($sess_scheduled_date->format("Y-m-d"));
					}
					
					// apply determined session values
					$sessions[$i]["sess_id"] = $sess_id;
					$sessions[$i]["sess_type"] = $sess_type;
					$sessions[$i]["sess_attended"] = $sess_attended;
					$sessions[$i]["sess_mode"] = $sess_mode;
					$sessions[$i]["sess_month"] = $sess_month;
					$sessions[$i]["sess_scheduled_date"] = $sess_scheduled_date;
					$sessions[$i]["sess_actual_date"] = $sess_actual_date;
					$sessions[$i]["sess_weight"] = $sess_weight;
					$sessions[$i]["sess_pa"] = $sess_pa;
					$sessions[$i]["sessionscoaching_log_complete"] = 2;
					
					unset($sess_id, $sess_type, $sess_attended, $sess_mode, $sess_month, $sess_scheduled_date, $sess_actual_date, $sess_weight, $sess_pa, $sess_date);
				}
			}
		}
		
		// save data
		// file_put_contents("C:/log.txt", print_r($records, true) . "\n\n", FILE_APPEND);
		$result = \REDCap::saveData(PROJECT_ID, 'array', $records, "overwrite");
		if (!empty($result["errors"])) {
			// file_put_contents("C:/log.txt", print_r($result["errors"], true) . "\n", FILE_APPEND);
			$participant["error"] = "There was an issue updating the Coaching/Sessions Log data in REDCap -- changes not made. See log for more info.";
			\REDCap::logEvent("DPP import failure", "REDCap::saveData errors -> " . print_r($result["errors"], true) . "\n", null, $rid, $eid, PROJECT_ID);
			$row++;
			$participants[] = $participant;
			continue;
		}
		
		for ($i = 1; $i <= 28; $i++) {
			$offset = ($i >= 17) ? 6 : 3;
			if (isset($sessions[$i])) {
				$participant["after"][$i] = [
					"sess_id" => $sessions[$i]["sess_id"],
					"sess_type" => getLabel($sessions[$i]["sess_type"], "sess_type"),
					"sess_mode" => getLabel($sessions[$i]["sess_mode"], "sess_mode"),
					"sess_month" => $sessions[$i]["sess_month"],
					"sess_scheduled_date" => $sessions[$i]["sess_scheduled_date"],
					"sess_actual_date" => $sessions[$i]["sess_actual_date"],
					"sess_weight" => $sessions[$i]["sess_weight"],
					"sess_pa" => $sessions[$i]["sess_pa"]
				];
			}
		}
		
		$participants[] = $participant;
	}
	$row++;
}

if (empty($participants)) {
    exit(json_encode([
		"error" => true,
		"notes" => [
			"The workbook was opened successfully, however cells A2, B2, and C2 in the 'DPP Sessions' worksheet are empty.",
			"This plugin expects a first name and last name for at least one participant."
		]
	]));
}

exit(json_encode([
	"participants" => $participants
]));
