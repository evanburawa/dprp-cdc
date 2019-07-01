$('#upload').on('click', function() {
	var file_data = $('#workbook').prop('files')[0];
	var form_data = new FormData();
	form_data.append('workbook', file_data);
	$("#notes").hide();
	$("#notes div li").remove();
	$("#results").hide();
	$("#results div").empty();
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
			} else if (typeof(response.participants) != "object") {
				$("#notes").show();
				$("#notes div").append("<li>There was a problem importing the workbook, please try again.</li>");
				console.log(response);
			} else {
				writeResultsTable(response.participants);
			}
		},
		complete: function(data) {
			console.log(data);
		}
	});
});
M
$('.custom-file-input').on('change', function() { 
	let fileName = $(this).val().split('\\').pop(); 
	$(this).next('.custom-file-label').addClass("selected").html(fileName); 
});

function writeResultsTable(participants) {
	let beforeTable = "";
	let afterTable = "";
	let results = `
				<h5>Import Results:</h5>`;
	participants.forEach(function(participant, partIndex) {
		results += `
				<table>
					<tbody>
						<tr>
							<th>Record ID</th>
							<th>First Name</th>
							<th>Last Name</th>
							<th>Employee ID</th>
						</tr>
						<tr>
							<td>${participant.recordID}</td>
							<td>${participant.firstName}</td>
							<td>${participant.lastName}</td>
							<td>${participant.empID}</td>
						</tr>`;
		if (typeof participant.error == "string") {
			results += `
						<tr>
							<th>Error</th>
							<td colspan="3">${participant.error}</td>
						</tr>`;
		} else {
			// fill before and after tables
			beforeTable = `
				<table>
					<tbody>
						<tr>
							<th>Session ID</th>
							<th>Type</th>
							<th>Delivery Mode</th>
							<th>Date</th>
							<th>Month in Program</th>
							<th>Weight</th>
							<th>Physical Activity</th>
						</tr>`;
			afterTable = beforeTable;
			for (i=1; i<=25; i++) {
				if (participant.before.hasOwnProperty(i)) {
					beforeTable += `
						<tr>
							<td>${participant.before[i]["sess_id"]}</td>
							<td>${participant.before[i]["sess_type"]}</td>
							<td>${participant.before[i]["sess_mode"]}</td>
							<td>${participant.before[i]["sess_date"]}</td>
							<td>${participant.before[i]["sess_month"]}</td>
							<td>${participant.before[i]["sess_weight"]}</td>
							<td>${participant.before[i]["sess_pa"]}</td>
						</tr>`;
					afterTable += `
						<tr>
							<td>${participant.after[i]["sess_id"]}</td>
							<td>${participant.after[i]["sess_type"]}</td>
							<td>${participant.after[i]["sess_mode"]}</td>
							<td>${participant.after[i]["sess_date"]}</td>
							<td>${participant.after[i]["sess_month"]}</td>
							<td>${participant.after[i]["sess_weight"]}</td>
							<td>${participant.after[i]["sess_pa"]}</td>
						</tr>`;
					
					let status = "No change";
					let style = "neutral";
					let a = participant.before[i];
					let b = participant.after[i];
					if (a == "0" && b == "null") {
						// do nothing; this is for when there was no value in REDCap and no value in import, so no change
					} else if (b == "null" && a != "0") {
						status = "Weight deleted";
						style = "deleted";
					} else if (b != "null" && (b != a)) {
						status = "Weight updated";
						style = "updated";
					}
					results += `
							<tr>
								<td>${i}</td>
								<td>${participant.before[i]}</td>
								<td>${participant.after[i]}</td>
								<td class="${style}">${status}</td>
							</tr>`;
				}
			}
			results += `
					</tbody>
				</table>`;
		}
		results += `
				<br>`;
	});
	$("#results div").html(results)
	$("#results").show();
}

$(function() {
	$("#results").hide();
});