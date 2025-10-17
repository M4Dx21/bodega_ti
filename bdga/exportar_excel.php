<?php
require 'vendor/autoload.php';
include 'db.php';
session_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$sql = "SELECT 
            codigo         AS numero_serie,
            insumo         AS modelo,
            stock,
            categoria,
            marca,
            estado,
            ubicacion,
            observaciones,
            fecha_ingreso,
            caracteristicas,
            garantia,
            comprobante,
            precio,
            nro_orden,
            provedor       AS proveedor
        FROM componentes";
$result = $conn->query($sql);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Componentes');

$title = 'INVENTARIO DE COMPONENTES – HOSPITAL CLÍNICO FÉLIX BULNES • ' . date('d/m/Y H:i');
$sheet->mergeCells('A1:O1');
$sheet->setCellValue('A1', $title);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$logoPath = __DIR__ . '/asset/felix.jpg';
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo HCFB');
    $drawing->setDescription('Logo Hospital Clínico Félix Bulnes');
    $drawing->setPath($logoPath);
    $drawing->setHeight(42);
    $drawing->setCoordinates('A1');
    $drawing->setOffsetX(5);
    $drawing->setOffsetY(2);
    $drawing->setWorksheet($sheet);
}

$headers = [
    'NÚMERO DE SERIE','MODELO','STOCK','CATEGORÍA','MARCA','ESTADO','UBICACIÓN',
    'OBSERVACIONES','FECHA INGRESO','CARACTERÍSTICAS','GARANTÍA','COMPROBANTE',
    'PRECIO','NRO ORDEN','PROVEEDOR U ORIGEN'
];
$headerRow = 3;
$sheet->fromArray($headers, null, 'A'.$headerRow);

$sheet->getStyle("A{$headerRow}:O{$headerRow}")->getFont()->setBold(true);
$sheet->getStyle("A{$headerRow}:O{$headerRow}")
      ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6EEF8');
$sheet->getStyle("A{$headerRow}:O{$headerRow}")
      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$dataStart = $headerRow + 1;
$rowIndex  = $dataStart;

while ($row = $result->fetch_assoc()) {

    $sheet->setCellValueExplicit("A{$rowIndex}", (string)$row['numero_serie'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B{$rowIndex}", (string)$row['modelo'], DataType::TYPE_STRING);
    $sheet->setCellValue("C{$rowIndex}", is_numeric($row['stock']) ? (int)$row['stock'] : $row['stock']);
    $sheet->setCellValue("D{$rowIndex}", (string)$row['categoria']);
    $sheet->setCellValue("E{$rowIndex}", (string)$row['marca']);
    $sheet->setCellValue("F{$rowIndex}", (string)$row['estado']);
    $sheet->setCellValue("G{$rowIndex}", (string)$row['ubicacion']);
    $sheet->setCellValue("H{$rowIndex}", (string)$row['observaciones']);

    if (!empty($row['fecha_ingreso']) && $row['fecha_ingreso'] !== '0000-00-00') {
        $phpDate = strtotime($row['fecha_ingreso']);
        $sheet->setCellValue("I{$rowIndex}", XlsDate::PHPToExcel($phpDate));
    } else {
        $sheet->setCellValue("I{$rowIndex}", null);
    }

    $sheet->setCellValue("J{$rowIndex}", (string)$row['caracteristicas']);
    $sheet->setCellValue("K{$rowIndex}", (string)$row['garantia']);
    $sheet->setCellValue("L{$rowIndex}", (string)$row['comprobante']);

    if ($row['precio'] !== null && $row['precio'] !== '') {
        $precio = is_numeric($row['precio']) ? (float)$row['precio'] : (float)preg_replace('/[^\d.-]/', '', $row['precio']);
        $sheet->setCellValue("M{$rowIndex}", $precio);
    } else {
        $sheet->setCellValue("M{$rowIndex}", null);
    }

    $sheet->setCellValueExplicit("N{$rowIndex}", (string)$row['nro_orden'], DataType::TYPE_STRING);
    $sheet->setCellValue("O{$rowIndex}", (string)$row['proveedor']);

    if ($rowIndex % 2 === 0) {
        $sheet->getStyle("A{$rowIndex}:O{$rowIndex}")
              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FBFF');
    }

    $rowIndex++;
}
$dataEndRow = $rowIndex - 1;

$sheet->getStyle("I{$dataStart}:I{$dataEndRow}")
      ->getNumberFormat()->setFormatCode('dd/mm/yyyy');

$sheet->getStyle("M{$dataStart}:M{$dataEndRow}")
      ->getNumberFormat()->setFormatCode('"CLP" #,##0');

$sheet->getStyle("A{$dataStart}:A{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle("C{$dataStart}:C{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("M{$dataStart}:M{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

if ($dataEndRow >= $dataStart) {
    $sheet->getStyle("A{$headerRow}:O{$dataEndRow}")->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFB0B0B0');
}

$sheet->setAutoFilter("A{$headerRow}:O{$headerRow}");

$sheet->freezePane("A" . ($dataStart));

foreach (range('A','O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'componentes_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
