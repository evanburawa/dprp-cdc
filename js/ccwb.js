// on document ready
var DPP = {};

$('body').on('click', '#generateWorkbook', function() {
	if (DPP.cohort && DPP.coach && pid) {
		let url = "master.php?pid=" + pid + "&coach=" + DPP.coach + "&cohort=" + DPP.cohort;
		url = encodeURI(url);
		window.open(url);
	}
});

$('body').on('click', '.dropdown-menu a', function() {
	let dd = $(this).parent().siblings("button");
	dd.text($(this).text());
	$(".btn:first-child").val($(this).text());
	
	if (dd[0] == $("#coachDropdown")[0]) {
		DPP.coach = dd.text();
	} else if (dd[0] == $("#cohortDropdown")[0]) {
		DPP.cohort = dd.text();
	}
	
	let pid = getUrlParameter("pid");
	if (DPP.cohort && DPP.coach && pid) {
		// show user record count for this coach-cohort
		let url = encodeURI(`coach_cohort_ajax.php?pid=${pid}&coach=${DPP.coach}&cohort=${DPP.cohort}`);
		$.get({
			url: url,
			dataType: "json",
		}).done(function(response) {
			console.log('response', response);
			if (response.recordCount || response.recordCount === 0) {
				$("#generateWorkbook").attr('disabled', response.recordCount === 0);
				$("#ajaxInfo").text("Records found for this coach-cohort combination: " + response.recordCount);
				$("#ajaxInfo").show();
			} else {
				$("#generateWorkbook").attr('disabled', true);
				$("#ajaxInfo").text(response.error);
				$("#ajaxInfo").show();
			}
		});
	} else {
		$("#ajaxInfo").hide();
		$("#generateWorkbook").attr('disabled', true);
	}
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