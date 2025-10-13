<?php
session_start();

// Restrict to admins
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

// Require Composer autoload for PhpSpreadsheet
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Excel generation requires phpoffice/phpspreadsheet. Please run Composer install.';
    exit();
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Student Import');

// Headers matching the importer expectations
$headers = ['lrn','lastname','firstname','middlename','suffix','strand','section'];
$col = 1; // 1-based index
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($col, 1, $h);
    $col++;
}

// Sample row
$sample = [
    '123456789012', // lrn (12 digits)
    'Dela Cruz',    // lastname
    'Juan',         // firstname
    'Santos',       // middlename (optional)
    '',             // suffix (optional)
    'STEM',         // strand (TVL, STEM, HUMSS, ABM)
    'David',        // section (must match allowed for the strand)
];
$col = 1;
foreach ($sample as $val) {
    $sheet->setCellValueByColumnAndRow($col, 2, $val);
    $col++;
}

// Style header
$headerStyle = $sheet->getStyle('A1:G1');
$headerStyle->getFont()->setBold(true);
$headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EEF7');

// Freeze top row and auto-size columns
$sheet->freezePane('A2');
foreach (range('A', 'G') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

$filename = 'student_import_template.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
