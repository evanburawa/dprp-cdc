// on document ready
$(document).ready(
	function() {
		$('.datepicker').datepicker({
			changeMonth: true,
			changeYear: true
		});
	}
);

$('body').on('click', '#genReportButton', function() {
	// do ajax here
});