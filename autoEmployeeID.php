<?php
require_once('ldap.php');

function getEmployeeID($uid) {
	$entry = LdapLookup::lookupUserDetailsByKeys([$uid], ["uid"], true, false);
	return $entry[0]['vanderbiltpersonemployeeid'][0];
}

$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
if (isset($_POST['rid'])) {
	$vunetid = $_POST['vunetid'];
	$rid = $_POST['rid'];
	$eid = $_POST['eid'];
	
	$peid = getEmployeeID($vunetid);
	
	$data = [];
	$data[$rid] = [];
	$data[$rid][$eid] = [];
	$data[$rid][$eid]['participant_employee_id'] = $peid != null ? $peid : "failed";
	
	\REDcap::saveData(35, 'array', $data);
};