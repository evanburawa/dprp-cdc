<?php
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

require "libs/PhpSpreadsheet/vendor/autoload.php";
require_once "libs/PhpSpreadsheet/src/Bootstrap.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// make DPP file
$spreadsheet = IOFactory::load("DPPMasterTemplate.xlsx");

// move ave/wt loss/goal area temporarily
$weightLosses = $spreadsheet->getActiveSheet()
	->rangeToArray(
		"A11:D15",
		NULL,
		TRUE,
		TRUE,
		TRUE
	);

//test weightLosses set
$spreadsheet->getActiveSheet()
	->fromArray(
		$weightLosses,
		NULL,
		"A19"
	);
$styleBold = [
	"font" => [
		"bold" => true
	]
];
$spreadsheet->getActiveSheet()->getStyle("A19:A23")->applyFromArray($styleBold);

// rename worksheets
// $spreadsheet->getActiveSheet()->setTitle('Combined');

// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$spreadsheet->setActiveSheetIndex(0);

// Redirect output to a client’s web browser (Xlsx)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="DPP Participant Sessions.xlsx"');
header('Cache-Control: max-age=0');

// // If you're serving to IE over SSL, then the following may be needed
// header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
// header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
// header('Cache-Control: cache, must-revalidate, max-age=1'); // HTTP/1.1
// header('Pragma: public'); // HTTP/1.0

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
