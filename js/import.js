$('#upload').on('click', function() {
	var file_data = $('#workbook').prop('files')[0];
	var form_data = new FormData();
	form_data.append('workbook', file_data);
	$("#notes").hide();
	$("#notes div li").remove();
	$("#results").hide();
	$("#results").empty();
	$.ajax({
		url: 'import_ajax.php',
		dataType: 'json',
		cache: false,
		contentType: false,
		processData: false,
		data: form_data,
		type: 'post',
		success: function(response){
			// console.log(response);
			if (response.error === true) {
				$("#notes").show();
				response.notes.forEach(function(element) {
					$("#notes div").append("<li>" + element + "</li>");
				});
			}
		},
		complete: function(data) {
			console.log(data);
		}
	});
});

$('.custom-file-input').on('change', function() { 
	let fileName = $(this).val().split('\\').pop(); 
	$(this).next('.custom-file-label').addClass("selected").html(fileName); 
});