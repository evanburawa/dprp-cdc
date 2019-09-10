<?php
define("NOAUTH", true);
require_once('config.php');
require_once "../../redcap_connect.php";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// // debug printing
file_put_contents("C:/vumc/log.txt", print_r($project->metadata["coach_name"]["element_enum"], true) . "\n\n");

// query for potential coach and cohort values
$project = new \Project((int) $_GET["pid"]);
preg_match_all("/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/", $project->metadata["coach_name"]["element_enum"], $matches);
$coaches = $matches[2];
preg_match_all("/(\d+),?\s?(.+?)(?=\x{005c}\x{006E}|$)/", $project->metadata["cohort"]["element_enum"], $matches);
$cohorts = $matches[2];
file_put_contents("C:/vumc/log.txt", print_r($coaches, true) . "\n\n", FILE_APPEND);
file_put_contents("C:/vumc/log.txt", print_r($cohorts, true) . "\n\n", FILE_APPEND);

// create dropdowns
$coach_dd = "
		<div class='dropdown'>
			<button class='btn btn-outline-primary dropdown-toggle' type='button' id='coachDropdown' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
				Coach
			</button>
			<div class='dropdown-menu' aria-labelledby='coachDropdown'>";
// add each coach name as a drop-down item
foreach ($coaches as $i => $name) {
	$coach_dd .= "
				<a class='dropdown-item' href='#'>$name</a>";
}
$coach_dd .= "
			</div>
		</div>";
		
$cohort_dd = "
		<div class='dropdown'>
			<button class='btn btn-outline-primary dropdown-toggle' type='button' id='cohortDropdown' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
				Cohort
			</button>
			<div class='dropdown-menu' aria-labelledby='cohortDropdown'>";
// add each coach name as a drop-down item
foreach ($cohorts as $i => $name) {
	$cohort_dd .= "
				<a class='dropdown-item' href='#'>$name</a>";
}
$cohort_dd .= "
			</div>
		</div>";

$ccwb_js = file_get_contents("js/ccwb.js");
$html = "
	<script type='text/javascript'>$ccwb_js</script>
	<h2>Generate Coach-Cohort Workbook File</h2>
	<h5>Select Coaches to Include:</h5>
	$coach_dd
	<h5>Select Cohorts to Include:</h5>
	$cohort_dd
	<button id='generateWorkbook' class='btn btn-primary' style='margin: 32px 80px'>Generate Coach-Cohort Workbook</button>
";

echo $html;

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>