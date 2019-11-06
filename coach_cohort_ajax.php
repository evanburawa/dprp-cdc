<?php
require_once('config.php');
header('Content-type:application/json;charset=utf-8');
$project = new \Project((int) $_GET["pid"]);

// query for potential coach and cohort values
preg_match_all("/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/", $project->metadata["coach_name"]["element_enum"], $matches);
$coaches = array_map("trim", $matches[2]);
preg_match_all("/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/", $project->metadata["cohort"]["element_enum"], $matches);
$cohorts = array_map("trim", $matches[2]);

// AJAX: given coach and cohort POST values, count applicable records and send json back to user
$coach = $_GET['coach'];
$cohort = $_GET['cohort'];
$json = new stdClass();
if (!empty($coach) and !empty($cohort)) {
	$coachIndex = array_search($coach, $coaches, true);
	$cohortIndex = array_search($cohort, $cohorts, true);
	if (is_int($coachIndex) and is_int($cohortIndex)) {
		$coachIndex += 1;
		$cohortIndex += 1;
		$records = \REDCap::getData((int) $_GET['pid'], 'array', null, ['coach_name, cohort'], null, null, null, null, null, "[coach_name] = '$coachIndex' and [cohort] = '$cohortIndex'");
		$json->recordCount = count($records);
		exit(json_encode($json));
	} else {
		$json->error = "The REDCap server encountered an issue -- REDCap can't find the specified coach or cohort value in the DPP project. Please notify a REDCap administrator";
		exit(json_encode($json));
	}
}
$json->error = "The REDCap server encountered an issue -- missing cohort or coach parameter via XHR request. Please notify a REDCap administrator.";
exit(json_encode($json));