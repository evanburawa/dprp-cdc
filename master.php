<?php
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

require "libs/PhpSpreadsheet/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Hello World !');

$writer = new Xlsx($spreadsheet);
$writer->save("hello world.xlsx");