<?php
define("NOAUTH", true);
require_once('config.php');
require_once "../../redcap_connect.php";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$cdc_js = file_get_contents("js/cdc.js");
echo "
	<script type='text/javascript'>$cdc_js</script>
	<h2>Generate CDC Report</h2>
	<h5>Select participant class</h5>
	<div class='custom-control custom-radio'>
		<input type='radio' id='reportInPersonRadio' name='customRadio' class='custom-control-input'>
		<label class='custom-control-label' for='reportInPersonRadio'>In-Person</label>
	</div>
	<div class='custom-control custom-radio'>
		<input type='radio' id='reportDigitalRadio' name='customRadio' class='custom-control-input'>
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
	
";

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>