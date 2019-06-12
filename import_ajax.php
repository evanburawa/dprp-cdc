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
	// $reader->setReadDataOnly(true);
	$reader->setLoadSheetsOnly("Combined");
	$workbook = $reader->load($_FILES["workbook"]["tmp_name"]);
	unlink($_FILES["workbook"]["tmp_name"]);
} catch(\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
	if (!file_exists("libs/errorlog.txt")) {
		file_put_contents("libs/errorlog.txt", $e);
	} else {
		file_put_contents("libs/errorlog.txt", $e, FILE_APPEND);
	}
    exit(json_encode([
		"error" => true,
		"notes" => [
			"There was an issue loading the workbook. Make sure it is an .xlsx file with a worksheet named 'Combined'.",
			"If you believe your file is a valid .xlsx workbook, please contact your REDCap administrator."
		]
	]));
}

// // check to see what we have in-memory after transfer
// $writer = IOFactory::createWriter($workbook, 'Xlsx');
// $writer->save("workbook check.xlsx");

// iterate through participant data and make changes, noting failures
$participants = [];
$done = false;
$row = 2;
while (!$done) {
	$firstName = $workbook->getActiveSheet()->getCellByColumnAndRow(2, $row)->getValue();
	$lastName = $workbook->getActiveSheet()->getCellByColumnAndRow(1, $row)->getValue();
	$empID = $workbook->getActiveSheet()->getCellByColumnAndRow(3, $row)->getValue();
	if (empty($firstName) and empty($lastName) and empty($empID)) {
		$done = true;
	} else {
		$participant = [
			"firstName" => $firstName,
			"lastName" => $lastName,
			"empID" => $empID
		];
		
		$records = \REDCap::getData(PROJECT_ID, 'array', NULL, NULL, NULL, NULL, NULL, NULL, NULL, "[first_name] = \"$firstName\" AND [last_name] = \"$lastName\" AND [participant_employee_id] = \"$empID\"");
		
		if (empty($records)) {
			$participant["error"] = "No record found with first name: $firstName, last name: $lastName, and employee ID: $empID";
		} else {
			$rid = array_keys($records)[0];
			$eid = array_keys($records[$rid])[0];
			$records = \REDCap::getData(PROJECT_ID, 'array', $rid);
			$sessions = &$records[$rid]["repeat_instances"][$eid]["sessionscoaching_log"];
			
			$participant["recordID"] = $rid;
			$participant["before"] = [];
			$participant["after"] = [];
			for ($i = 1; $i <= 16; $i++) {
				$participant["before"][$i] = json_encode((int) $sessions[$i]["sess_weight"]);
				$sessions[$i]["sess_weight"] = $workbook->getActiveSheet()->getCellByColumnAndRow($i + 4, $row)->getValue();
				$participant["after"][$i] = json_encode($sessions[$i]["sess_weight"]);
			}
			for ($i = 17; $i <= 25; $i++) {
				$participant["before"][$i] = json_encode((int) $sessions[$i]["sess_weight"]);
				$sessions[$i]["sess_weight"] = $workbook->getActiveSheet()->getCellByColumnAndRow($i + 7, $row)->getValue();
				$participant["after"][$i] = json_encode($sessions[$i]["sess_weight"]);
			}
			
			// save data
			$result = \REDCap::saveData(PROJECT_ID, 'array', $records);
			if (!empty($result["errors"])) {
				$participant["error"] = "There was an issue updating the Coaching/Sessions Log data in REDCap";
				if (!file_exists("saveDataErrors.txt")) {
					file_put_contents("saveDataErrors.txt", print_r($result["errors"], true));
				} else {
					file_put_contents("saveDataErrors.txt", print_r($result["errors"], true), FILE_APPEND);
				}
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
			"The workbook was opened successfully, however cells A2, B2, and C2 in the 'Combined' worksheet are empty.",
			"This plugin expects a first name, last name, or employee ID for at least one participant."
		]
	]));
}

exit(json_encode([
	"participants" => $participants
]));
