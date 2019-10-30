<?php
require_once('config.php');
?>
<div id="container">
	<div id="notes" class="card p5" style="width: 50rem;">
		<div class="card-body">
			<h5>File upload failed</h5>
		</div>
	</div>
	<div id="filepicker" class="card-body pb5 m3" style="width: 50rem;">
		<h5>Select a DPP Master File workbook to upload</h5>
		<div class="input-group">
			<div class="custom-file">
				<input type="file" class="custom-file-input" id="workbook" aria-describedby="upload">
				<label class="custom-file-label text-truncate" for="workbook">Choose file</label>
			</div>
			<div class="input-group-append">
				<button class="btn btn-outline-secondary" type="button" id="upload">Upload</button>
			</div>
		</div>
	</div>
	<div id="results" class="card pb5 m5">
		<div class="card-body">
		</div>
	</div>
</div>
<link rel="stylesheet" href="css/import.css"/>
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/import.js"></script>