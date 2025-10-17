<?php
require('fpdf/fpdf.php');
include 'db.php';
session_start();
date_default_timezone_set('America/Santiago');

$usuario = $_SESSION['nombre'] ?? '';

// Validación básica
if (!isset($_POST['codigo'])) {
    echo "No se recibieron datos.";
    exit;
}

// Arrays desde el formulario
$codigos      = $_POST['codigo'];
$insumos      = $_POST['insumo'];
$marca        = $_POST['marca'];
$categorias   = $_POST['categoria'];
$cantidades   = $_POST['cantidad'];
$ubicaciones  = $_POST['ubicacion'];
$destinos     = $_POST['destino'] ?? [];   // <- NUEVO: destinos por cada fila

// Procesar cada ítem: descuenta stock, recalcula precio y registra salida con DESTINO
for ($i = 0; $i < count($codigos); $i++) {
    $codigo   = $codigos[$i];
    $cantidad = (int)$cantidades[$i];
    $destino  = isset($destinos[$i]) ? trim($destinos[$i]) : ''; // <- lee destino alineado al ítem

    // Obtiene stock y precio actual
    $stmt = $conn->prepare("SELECT stock, precio FROM componentes WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row && (int)$row['stock'] >= $cantidad) {
        $stock_actual    = (int)$row['stock'];
        $precio_actual   = (float)$row['precio'];
        $nuevo_stock     = $stock_actual - $cantidad;
        $precio_unitario = $stock_actual > 0 ? ($precio_actual / $stock_actual) : 0.0;
        $nuevo_precio    = $precio_actual - ($precio_unitario * $cantidad);

        // Actualiza stock y precio
        $stmt = $conn->prepare("UPDATE componentes SET stock = ?, precio = ? WHERE codigo = ?");
        $stmt->bind_param("ids", $nuevo_stock, $nuevo_precio, $codigo);
        $stmt->execute();
        $stmt->close();

        // Inserta salida con DESTINO
        $stmt = $conn->prepare("
            INSERT INTO salidas (num_serie, cantidad, destino, fecha_salida, responsable)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->bind_param("siss", $codigo, $cantidad, $destino, $usuario);
        $stmt->execute();
        $stmt->close();
    }
}

/* ================= PDF ================= */

class PDF extends FPDF {
    function Header() { $this->Ln(5); }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Página '.$this->PageNo(),0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);

$pdf->Image('asset/gob.jpg', 10, 10, 18, 18);
$pdf->Image('asset/felix.jpg', 180, 10, 18, 18);

$pdf->Ln(15);

$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'ACTA DE ENTREGA EQUIPAMIENTO TECNOLÓGICO'), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'En Santiago '. date('d/m/Y') . ' mediante la presente acta se hace entrega del equipamiento tecnológico,'), 0, 1);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'descrito a continuación, de propiedad de Hospital Dr. Félix Bulnes Cerda.   '), 0, 1);

$pdf->Ln(5);

$tipos_equipo = [];
$marcas = [];
$modelos = [];
$series = [];
$ubicaciones_finales = [];
$destinos_finales = [];

for ($i = 0; $i < count($codigos); $i++) {
    $tipos_equipo[]       = $categorias[$i];
    $marcas[]             = $marca[$i];
    $modelos[]            = $insumos[$i];
    $series[]             = $codigos[$i];
    $ubicaciones_finales[]= $ubicaciones[$i];
    $destinos_finales[]   = $destinos[$i] ?? '';
}

$tipos_equipo_str = implode(' / ', $tipos_equipo);
$marcas_str       = implode(' / ', $marcas);
$modelos_str      = implode(' / ', $modelos);
$series_str       = implode(' / ', $series);
$ubicaciones_str  = implode(' / ', $ubicaciones_finales);
$destinos_str     = implode(' / ', $destinos_finales);

$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(255, 255, 255);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'TIPO DE EQUIPO:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $tipos_equipo_str), 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MARCA:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $marcas_str), 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MODELO:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $modelos_str), 1, 1);

$pdf->Cell(60, 20, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'SERIE:'), 1, 0, 'L', true);
$pdf->Cell(130, 20, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $series_str), 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'NOMBRE DEL EQUIPO:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, '', 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'SERVICIO:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $destinos_str), 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'UBICACIÓN:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $ubicaciones_str), 1, 1);

$pdf->Ln(3);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'PERSONAL DE TI QUE ENTREGA:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $usuario), 1, 1);

$pdf->Ln(3);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'RECIBIDO POR:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, '', 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'RUT:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, '', 1, 1);

$pdf->Cell(60, 15, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'FIRMA:'), 1, 0, 'L', true);
$pdf->Cell(130, 15, '', 1, 1);

$pdf->Ln(5);

$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Recibo conforme el equipo asignado, junto a la información migrada no habiendo reparos u objeciones'), 0, 1);
$pdf->Ln(2);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Nota: Se solicita informar traslado, incidencia o daño en el equipo, esto para efectos de garantías,'), 0, 1);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'reparaciones y/o asistencia, en el correo soporteti.hfbc@redsalud.gob.cl y en caso de consultas al anexo 226868'), 0, 1);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', ' y/o el correo descrito anteriormente'), 0, 1);

$pdf->Ln(5);

$carpetaComprobantes = __DIR__ . '/temp/';
$hace28Dias = time() - (28 * 24 * 60 * 60);
if (is_dir($carpetaComprobantes)) {
    foreach (glob($carpetaComprobantes . '*') as $archivo) {
        if (is_file($archivo) && filemtime($archivo) < $hace28Dias) {
            @unlink($archivo);
        }
    }
}

$nombreArchivo = 'retiro_insumos_' . date('Ymd_His') . '.pdf';
$rutaArchivo = 'temp/' . $nombreArchivo;
$pdf->Output('F', $rutaArchivo);

echo '
    <html>
    <head><meta charset="UTF-8"></head>
    <body>
    <script>
        const enlace = document.createElement("a");
        enlace.href = "' . $rutaArchivo . '";
        enlace.download = "' . $nombreArchivo . '";
        document.body.appendChild(enlace);
        enlace.click();
        setTimeout(() => {
            window.location.href = "principal.php?mensaje=' . urlencode("Insumos retirados correctamente.") . '";
        }, 1000);
    </script>
    </body>
    </html>
';
exit;
?>