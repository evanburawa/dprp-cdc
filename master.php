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

// get actual coach/cohort values
$pid = (int) $_GET['pid'];
$coach_actual = $_GET['coach'];
$cohort_actual = $_GET['cohort'];

// determine to raw values so we can filter records from getData
$foundRawCoachValue = false;
$project = new \Project((int) $_GET["pid"]);
preg_match_all("/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/", $project->metadata["coach_name"]["element_enum"], $matches);
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
preg_match_all("/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/", $project->metadata["cohort"]["element_enum"], $matches);
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
$records = \REDCap::getData(PROJECT_ID, null, null, null, null, null, null, null, null, $filterLogic);

// regex for getting labels for project fields (like state, sess_type, etc)
$labelPattern = "/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/";
$project = new \Project(PROJECT_ID);

// make DPP file
$workbook = IOFactory::load("masterTemplate.xlsx");




