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
				<hr>
				<h6>Participant ${participant.recordID}</h6>
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
		}
		results += `
					</tbody>
				</table>
				<br>
				<div class="row">`;
		
		beforeTable = "";
		afterTable = "";
		
		// if no error, add before/after tables
		if (typeof participant.error != "string") {
			beforeTable = `
				<div class="col">
				<h6>Before Import:</h6>
				<table class="before">
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
			afterTable = `
				<div class="col">
				<h6>After Import:</h6>
				<table class="after">
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
					
					// select style for each cell
					let styles = [];
					let fields = ["sess_id", "sess_type", "sess_mode", "sess_date", "sess_month", "sess_weight", "sess_pa"];
					fields.forEach(function(field) {
						if (participant.after[i][field] == null)
							participant.after[i][field] = "";
						let a = participant.before[i][field];
						let b = participant.after[i][field];
						let style = "neutral";
						if (a && !b) {
							style = "deleted";
						} else if (a != b && b) {
							style = "updated";
						}
						// console.log(typeof a + " -- " + typeof b + " -- " + style);
						styles.push(style);
					});
					
					afterTable += `
						<tr>
							<td class="${styles[0]}">${participant.after[i]["sess_id"]}</td>
							<td class="${styles[1]}">${participant.after[i]["sess_type"]}</td>
							<td class="${styles[2]}">${participant.after[i]["sess_mode"]}</td>
							<td class="${styles[3]}">${participant.after[i]["sess_date"]}</td>
							<td class="${styles[4]}">${participant.after[i]["sess_month"]}</td>
							<td class="${styles[5]}">${participant.after[i]["sess_weight"]}</td>
							<td class="${styles[6]}">${participant.after[i]["sess_pa"]}</td>
						</tr>`;
				}
			}
			beforeTable += `
					</tbody>
				</table>
				</div>`;
			afterTable += `
					</tbody>
				</table>
				</div>`;
		}
		results += beforeTable + afterTable + `
				</div>
				<br>`;
	});
	$("#results div").html(results)
	$("#results").show();
}

$(function() {
	$("#results").hide();
});