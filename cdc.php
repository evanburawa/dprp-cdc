<?php
require_once('config.php');
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$cdc_js = file_get_contents("js/cdc.js");
echo "
	<script type='text/javascript'>$cdc_js</script>
	<h2>Generate CDC Report</h2>
	<h5>Select participant class</h5>
	<div class='custom-control custom-radio'>
		<input type='radio' id='reportInPersonRadio' name='customRadio' class='custom-control-input' value='792184'>
		<label class='custom-control-label' for='reportInPersonRadio'>In-Person</label>
	</div>
	<div class='custom-control custom-radio'>
		<input type='radio' id='reportDigitalRadio' name='customRadio' class='custom-control-input' value='8540168'>
		<label class='custom-control-label' for='reportDigitalRadio'>Digital</label>
	</div>
	<h5>Select participant type</h5>
	<div class='custom-control custom-radio'>
		<input type='radio' id='reportCompliantRadio' name='customRadio2' class='custom-control-input'>
		<label class='custom-control-label' for='reportCompliantRadio'>Compliant</label>
	</div>
	<div class='custom-control custom-radio'>
		<input type='radio' id='reportNoncompliantRadio' name='customRadio2' class='custom-control-input'>
		<label class='custom-control-label' for='reportNoncompliantRadio'>Non-compliant</label>
	</div>
	<h5>Select report date range</h5>
	<label for='firstDate'>Filter out participant sessions before:</label>
	<input type='text' id='firstDate' class='datepicker'>
	<br/>
	<label for='lastDate'>Filter out participant sessions after:</label>
	<input type='text' id='lastDate' class='datepicker'>
	<br/>
	<button id='genReportButton' class='btn btn-primary' style='margin: 32px 80px'>Generate Report</button>
";

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>