<?php
// exportar_excel.php
require 'vendor/autoload.php';   // Composer autoload
include 'db.php';                // Conexión $conn
session_start();                 // Opcional: si necesita validar rol

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 1) Consultar todos los campos EXCEPTO id
$sql = "SELECT 
            codigo, insumo, stock, categoria, marca, estado, ubicacion,
            observaciones, fecha_ingreso, caracteristicas, garantia, comprobante
        FROM componentes";
$result = $conn->query($sql);

// 2) Crear y poblar la hoja
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados (primera fila)
$encabezados = [
    'Código', 'Insumo', 'Stock', 'Categoría', 'Marca', 'Estado', 'Ubicación',
    'Observaciones', 'Fecha ingreso', 'Características', 'Garantía', 'Comprobante'
];
$sheet->fromArray($encabezados, null, 'A1');

// Filas de datos
$fila = 2;
while ($row = $result->fetch_assoc()) {
    // Conservar orden de los campos tal como en $encabezados
    $sheet->fromArray(array_values($row), null, 'A' . $fila);
    $fila++;
}

// 3) Enviar al navegador
$nombreArchivo = 'componentes_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
