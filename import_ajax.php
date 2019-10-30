<?php
// Report all PHP errors
// error_reporting(-1);

// Same as error_reporting(E_ALL);
// ini_set('error_reporting', E_ALL);
// define("NOAUTH", true);
// require "config.php";

// $info = [];
// $info['conn'] = print_r($conn, true);
/////////////
// file_put_contents("C:/vumc/log.txt", PROJECT_ID);
function _log($text) {
	// file_put_contents("C:/vumc/log.txt", $text . "\n", FILE_APPEND);
}

define(PROJECT_ID, $module->getProjectId());

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

function getParticipantRowNumber($firstName, $lastName, $partID) {
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
		return "The DPP module couldn't find 2nd table of participant data. Please see sample master file for formatting help.";
	}
	
	$nextRow = $headerRow + 1;
	while (true) {
		$thisRowLastName = $workbook->getActiveSheet()->getCellByColumnAndRow(1, $nextRow)->getValue();
		$thisRowFirstName = $workbook->getActiveSheet()->getCellByColumnAndRow(2, $nextRow)->getValue();
		$thisRowPartID = $workbook->getActiveSheet()->getCellByColumnAndRow(4, $nextRow)->getValue();
		if (empty($thisRowLastName) and empty($thisRowFirstName) and empty($thisRowPartID)) {
			return "The DPP module couldn't find this participant in 2nd data table. First and last name provided must match, if a participant ID is provided, it must also match.";
		} elseif ($thisRowLastName == $lastName and $thisRowFirstName == $firstName and $thisRowPartID == $partID) {
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
	echo json_encode([
		"error" => true,
		"notes" => [
			"Please attach a workbook file and then click 'Upload'."
		]
	]);
	return;
}

// check for transfer errors
if ($_FILES["workbook"]["error"] !== 0) {
	echo json_encode([
		"error" => true,
		"notes" => [
			"An error occured while uploading your workbook. Please try again."
		]
	]);
	return;
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
	echo json_encode([
		"error" => true,
		"notes" => $errors
	]);
	return;
}

// open workbook
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
	\REDCap::logEvent("DPP import failure", "PhpSpreadsheet library errors -> " . print_r($e, true) . "\n", null, $rid, $eid, PROJECT_ID);
    echo json_encode([
		"error" => true,
		"notes" => [
			"There was an issue loading the workbook. Make sure it is an .xlsx file with a worksheet named 'DPP Sessions'.",
			"If you believe your file is a valid DPP Workbook file, please contact your REDCap administrator."
		]
	]);
	return;
}

exit(json_encode(['abc' => 'def']));
// iterate through participant data and make changes, recording before, after values, or errors
$participants = [];
$done = false;
$project = new \Project(PROJECT_ID);
$row = 2;
$parameters = [
	'project_id' => PROJECT_ID,
	'return_format' => 'json'
];

$filterLogic = [];
while (!$done) {
	$firstName = $workbook->getActiveSheet()->getCellByColumnAndRow(2, $row)->getValue();
	$lastName = $workbook->getActiveSheet()->getCellByColumnAndRow(1, $row)->getValue();
	$partID = $workbook->getActiveSheet()->getCellByColumnAndRow(4, $row)->getValue();
	// _log($firstName . ' ' . $lastName . ' ' . $partID);
	if (empty($firstName) and empty($lastName) and empty($partID)) {
		$done = true;
	} else {
		// get row number for this participant in 2nd table
		$row2 = getParticipantRowNumber($firstName, $lastName, $partID);
		
		$participant = [
			"firstName" => $firstName,
			"lastName" => $lastName,
			"partID" => $partID,
		];
		
		if (is_string($row2)) {
			$participant["error"] = $row2;
		}
		
		$participants[] = $participant;
		
		$filterLogic[] = "([last_name]='$lastName' and [first_name]='$firstName')";
	}
	$row++;
}

$parameters['filterLogic'] = implode(' or ', $filterLogic);
unset($filterLogic);
$info['params1'] = $parameters;

ob_start();
$records = json_decode(\REDCap::getData($parameters), true);
$info['ob_first_get_data_call'] = print_r(ob_flush(), true);
ob_end_clean();

$info['record by name count'] = count($records);

// refetch with rids to get repeat instances (session data)
$record_ids = [];
foreach ($records as $record) {
	$record_ids[] = $record['record_id'];
	foreach ($participants as $p_index => $participant) {
		if ($record['first_name'] == $participant['firstName'] and $record['last_name'] == $participant['lastName']) {
			$participants[$p_index]['record_id'] = $record['record_id'];
		}
	}
}
unset($parameters['filterLogic']);

$parameters['records'] = $record_ids;
$info['params2'] = $parameters;
$records = json_decode(\REDCap::getData($parameters), true);
$info['record by rids count'] = count($records);
$session_1_header_value = $workbook->getActiveSheet()->getCell("E1")->getValue();
$records_to_save = [];
$row = 2;

foreach ($participants as $participant_index => $participant) {
	$base_record = null;
	$session_template = null;
	$sessions = [];
	foreach ($records as $ri => $record) {
		if (!empty($record['participant_id']) and !empty($participant['partID'])) {
			if ($record['participant_id'] != $participant['partID'])
				continue;
		}
		if ($record['first_name'] === $participant['firstName'] and $record['last_name'] === $participant['lastName']) {
			$base_record = &$records[$ri];
			$session_template = $record;
			foreach($session_template as $key => $value) {
				$session_template[$key] = null;
			}
			$session_template['record_id'] = $participant['record_id'];
			$session_template['redcap_repeat_instrument'] = "sessionscoaching_log";
		} elseif ($record['record_id'] == $participant['record_id'] and $record["redcap_repeat_instrument"] == "sessionscoaching_log") {
			$sessions[$record["redcap_repeat_instance"]] = &$records[$ri];
		}
	}
	
	// _log("base:\n" . print_r($base_record, true));
	// _log("session_template:\n" . print_r($session_template, true));
	// _log("sessions:\n" . print_r($sessions, true));
	// _log("participants:\n" . print_r($participants, true));
	
	if ($base_record === null) {
		$participant["error"] = "The DPP module found no REDCap database record with first name: $firstName, last name: $lastName.";
	} else {
		// $rid = $target_rid;
		// $records_to_update[] = $rid;
		// $eid = key($target_record);
		// $sessions = &$records[$rid]["repeat_instances"][$eid]["sessionscoaching_log"];
		
		$rid = $participant['record_id'];
		$firstName = $participant['firstName'];
		$lastName = $participant['lastName'];
		$partID = $participant['partID'];
		$participant["before"] = [];
		$participant["after"] = [];
		$row2 = getParticipantRowNumber($firstName, $lastName, $partID);
		
		for ($i = 1; $i <= 28; $i++) {
			$offset = ($i >= 17) ? 7 : 4;
			
			$sess_weight = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, $row)->getValue();
			$sess_table_2_value = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, $row2)->getValue();
			if (empty($sessions[$i]) and (!empty($sess_weight) or !empty($sess_table_2_values))) {
				$sessions[$i] = $session_template;
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
				if ((string) $base_record["orgcode"] == "8540168") {
					$sess_mode = 3; // 3 for "digital / distance learning" in sessions form
				} elseif ((string) $base_record["orgcode"] == "792184") {
					$sess_mode = 1; // 1 for "In-person"
				}
				
				// get scheduled date (from header row)
				$headerValue = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, 1)->getValue();
				$datePart = preg_split("/[\s]+/", $headerValue)[2];
				$date = null;
				foreach (['/', '-', '.'] as $sep) {
					$pieces = explode($sep, $datePart);
					if (count($pieces) == 3 and checkdate($pieces[0], $pieces[1], $pieces[2])) {
						$date = $pieces[0] . '/' . $pieces[1] . '/' . $pieces[2];
						break;
					}
				}
				$sess_scheduled_date = empty($date) ? NULL : $date;
				unset($datePart);
				
				// must be retrieved from table 2
				$sess_pa = NULL;
				
				$sess_attended = NULL;
				
				$table_2_values = explode(",", $sess_table_2_value);
				$table_2_cell_color = $workbook->getActiveSheet()->getCellByColumnAndRow($i + $offset, $row2)->getStyle()->getFill()->getStartColor()->getRGB();
				foreach ($table_2_values as $value) {
					$value = strtoupper(trim($value));
					if (is_numeric($value))
						$sess_pa = (int) $value;
					$date = null;
					foreach (['/', '-', '.'] as $sep) {
						$pieces = explode($sep, $value);
						if (count($pieces) == 3 and checkdate($pieces[0], $pieces[1], $pieces[2])) {
							$date = $pieces[0] . '/' . $pieces[1] . '/' . $pieces[2];
							break;
						}
					}
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
				$datePart = preg_split("/[\s]+/", $session_1_header_value)[2];
				$date = null;
				foreach (['/', '-', '.'] as $sep) {
					$pieces = explode($sep, $datePart);
					if (count($pieces) == 3 and checkdate($pieces[0], $pieces[1], $pieces[2])) {
						$date = $pieces[0] . '/' . $pieces[1] . '/' . $pieces[2];
						break;
					}
				}
				$sess_month = NULL;
				$sess_date = $sess_actual_date;
				if (empty($sess_actual_date))
					$sess_date = $sess_scheduled_date;
				if (!empty($date) and !empty($sess_date)) {
					$d1 = new DateTime($date);
					$d2 = new DateTime($sess_date);
					// the following assumes 4 weeks (28 days) is 1 month -- this is in line with what is stated in the DPRP standards is a program "month"
					$sess_month = round(12 * ((int) $d2->format("Y") - (int) $d1->format("Y")) + ((int) $d2->format("m") - (int) $d1->format("m")) + ((int) $d2->format('d') - (int) $d1->format('d'))/28 - 1/4)+1;
				}
				unset($datePart);
				
				// sess type CORE or CORE MAINTENANCE depending on month (unless make-up)
				if ($sess_month >= 7 and $sess_type == 1) {
					$sess_type = 2;
				}
				
				if (empty($sess_weight) and empty($sess_pa) and $sess_attended != 1) {
					$sess_attended = 0;
				} else {
					$sess_attended = 1;
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
	
	
	if ($base_record) {
		$records_to_save[] = $base_record;
	}
	
	for ($i = 1; $i <= 28; $i++) {
		$offset = ($i >= 17) ? 7 : 4;
		if (isset($sessions[$i])) {
			$records_to_save[] = &$sessions[$i];
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
	$participants[$participant_index] = $participant;
	$row++;
}

if (empty($participants)) {
    echo json_encode([
		"error" => true,
		"notes" => [
			"The workbook was opened successfully, however cells A2, B2, and C2 in the 'DPP Sessions' worksheet are empty.",
			"This module expects a first name and last name for at least one participant."
		]
	]);
	return;
}

// save data
ob_start();
$result = \REDCap::saveData(PROJECT_ID, 'json', json_encode($records_to_save), "overwrite");

$info['save results'] = print_r($result, true);
$info['ob'] = print_r(ob_flush(), true);
ob_end_clean();

if (!empty($result["errors"])) {
	\REDCap::logEvent("DPP import failure", "REDCap::saveData errors -> " . print_r($result["errors"], true) . "\n", null, $rid, $eid, PROJECT_ID);
	echo json_encode([
		'error' => true,
		'notes' => "There was an issue updating the Coaching/Sessions Log data in REDCap -- changes not made. See log for more info.",
		"info" => $info
	]);
	return;
}

echo json_encode([
	"participants" => $participants,
	"info" => $info
]);