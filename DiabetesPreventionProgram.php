<?php
namespace Vanderbilt\DiabetesPreventionProgram;

class DiabetesPreventionProgram extends \ExternalModules\AbstractExternalModule {
	function redcap_save_record() {
		require($this->getUrl('ldap.php'));
		function getEmployeeID($uid) {
			$entry = LdapLookup::lookupUserDetailsByKeys([$uid], ["uid"], true, false);
			return $entry[0]['vanderbiltpersonemployeeid'][0];
		}
		
		if (empty($record)) goto exitTag;

		$data = \REDCap::getData($this->getProjectId(), 'array', $record);

		if ($data[$record][$event_id]['participant_employee_id'] != null) goto exitTag;

		$vunetid = $data[$record][$event_id]['vunetid'];
		if (empty($vunetid)) goto exitTag;

		$emp_id = getEmployeeID($vunetid);
		if (empty($emp_id)) goto exitTag;

		$data = [];
		$data[$record] = [];
		$data[$record][$event_id] = [];
		$data[$record][$event_id]['record_id'] = $record;
		$data[$record][$event_id]['participant_employee_id'] = $emp_id != null ? $emp_id : null;

		$result = \REDcap::saveData(PROJECT_ID, 'array', $data);
		exitTag:
	}
	
	function redcap_data_entry_form() {
		?>
		<script type='text/javascript'>
		function updateParticipantId() {
			// get last two digits of year from [cohort] value
			var selectedIndex = $("[name=cohort]").prop('selectedIndex')
			if (selectedIndex < 1)
				return
			var year = $("[name=cohort] option:eq(" + selectedIndex + ")").text().slice(-2)
			
			// get group character
			selectedIndex = $("[name=participant_id_group]").prop('selectedIndex')
			if (selectedIndex < 1)
				return
			var group = $("[name=participant_id_group] option:eq(" + selectedIndex + ")").text()
			
			// get status number value
			selectedIndex = $("[name=participant_id_category]").prop('selectedIndex')
			if (selectedIndex < 1)
				return
			var status = $("[name=participant_id_category] option:eq(" + selectedIndex + ")").text().replace(/(^\d+)(.+$)/i,'$1')
			
			// get emp_id
			var emp_id = $("[sq_id=participant_employee_id] td.data input").val()
			if (!emp_id)
				return
			emp_id = emp_id.padStart(7, '0')
			$("[sq_id=participant_employee_id] td.data input").val(emp_id)
			
			// make and return participant_id
			var part_id = String(year) + group + status + emp_id
			part_id = part_id.substring(0, 11)
			
			return part_id
			}
			$("#questiontable").on('change', "[sq_id=cohort] td.data, [sq_id=participant_id_group] td.data, [sq_id=participant_id_category] td.data, [sq_id=participant_employee_id] td.data", function() {
				$("[sq_id=participant_id] td.data input").val(updateParticipantId())
			});
			
			updateParticipantId()
		</script>
		<?php
	}
}

file_put_contents("C:/vumc/log.txt", "logging...\n");
function _log($text) {
	file_put_contents("C:/vumc/log.txt", $text . "\n", FILE_APPEND);
}