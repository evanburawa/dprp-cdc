// on document ready
$(document).ready(
	
);

$('body').on('click', '#generateWorkbook', function() {
	
});

$('body').on('click', '.dropdown-menu a', function() {
	let dd = $(this).parent().siblings("button");
	console.log(dd);
	dd.text($(this).text());
	$(".btn:first-child").val($(this).text());
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