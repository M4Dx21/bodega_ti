<?php
require 'vendor/autoload.php'; 
include 'db.php';
session_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$sql = "SELECT 
            codigo, insumo, stock, categoria, marca, estado, ubicacion,
            observaciones, fecha_ingreso, caracteristicas, garantia, comprobante, precio
        FROM componentes";
$result = $conn->query($sql);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$encabezados = [
    'Código', 'Insumo', 'Stock', 'Categoría', 'Marca', 'Estado', 'Ubicación',
    'Observaciones', 'Fecha ingreso', 'Características', 'Garantía', 'Comprobante', 'Precio'
];
$sheet->fromArray($encabezados, null, 'A1');

$fila = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->fromArray(array_values($row), null, 'A' . $fila);
    $fila++;
}

$nombreArchivo = 'componentes_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
