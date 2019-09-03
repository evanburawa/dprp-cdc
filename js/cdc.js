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
	let pid = getUrlParameter('pid');
	let firstdate = $("#firstDate").val();
	let lastdate = $("#lastDate").val();
	let url = 'index.php?pid={pid}&orgcode=8540168';
	if (firstdate)
		url += 'firstdate={firstdate}';
	if (lastdate)
		url += 'lastdate={lastdate}';
	console.log('url', url);
	// $.get({
		// url: 'index.php?pid={pid}&orgcode=8540168&firstdate={firstdate}&lastdate={lastdate}',
		// success: function(response) {
			// console.log(response);
		// }
	// });
});

// thanks: https://stackoverflow.com/questions/19491336/get-url-parameter-jquery-or-how-to-get-query-string-values-in-js
var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = window.location.search.substring(1),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
        }
    }
};