<?php
require('fpdf/fpdf.php');
include 'db.php';
session_start();
date_default_timezone_set('America/Santiago');

$usuario = $_SESSION['nombre'];

if (!isset($_POST['codigo'])) {
    echo "No se recibieron datos.";
    exit;
}

$codigos = $_POST['codigo'];
$insumos = $_POST['insumo'];
$marca = $_POST['marca'];
$categorias = $_POST['categoria'];
$cantidades = $_POST['cantidad'];
$ubicaciones = $_POST['ubicacion'];

for ($i = 0; $i < count($codigos); $i++) {
    $codigo = $codigos[$i];
    $cantidad = (int)$cantidades[$i];

    $stmt = $conn->prepare("SELECT stock, precio FROM componentes WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row && $row['stock'] >= $cantidad) {
        $nuevo_stock = $row['stock'] - $cantidad;
        $precio_unitario = $row['precio'] / max($row['stock'], 1);
        $nuevo_precio = $row['precio'] - ($precio_unitario * $cantidad);

        $stmt = $conn->prepare("UPDATE componentes SET stock = ?, precio = ? WHERE codigo = ?");
        $stmt->bind_param("ids", $nuevo_stock, $nuevo_precio, $codigo);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO salidas (num_serie, cantidad, fecha_salida, responsable) VALUES (?, ?, NOW(), ?)");
        $stmt->bind_param("sis", $codigo, $cantidad, $usuario);
        $stmt->execute();
        $stmt->close();
    }
}

class PDF extends FPDF {
    function Header() {
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Página '.$this->PageNo(),0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);

$pdf->Image('asset/gob.jpg', 10, 10, 18, 18); // izquierda, coordenadas x=10, y=10
$pdf->Image('asset/felix.jpg', 180, 10, 18, 18); // derecha, coordenadas x=180, y=10 (ajustar si tu página es tamaño carta o A4)

$pdf->Ln(15);

$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'ACTA DE ENTREGA EQUIPAMIENTO COMPUTACIONAL
'), 0, 1, 'C');

$pdf->Ln(2);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'En Santiago '. date('d/m/Y') . ' se hace entrega de equipamiento tecnológico de propiedad de '), 0, 1);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Hospital Dr. Félix Bulnes Cerda, el cual posee las siguientes características: '), 0, 1);

$pdf->Ln(5);

$tipos_equipo = [];
$marcas = [];
$modelos = [];
$series = [];
$ubicaciones_finales = [];

for ($i = 0; $i < count($codigos); $i++) {
    $tipos_equipo[] = $categorias[$i];
    $marcas[] = $marca[$i];
    $modelos[] = $insumos[$i];
    $series[] = $codigos[$i];
    $ubicaciones_finales[] = $ubicaciones[$i];
}

// Convertir los arrays a strings separados por " / "
$tipos_equipo_str = implode(' / ', $tipos_equipo);
$marcas_str = implode(' / ', $marcas);
$modelos_str = implode(' / ', $modelos);
$series_str = implode(' / ', $series);
$ubicaciones_str = implode(' / ', $ubicaciones_finales);

// Imprimir solo una vez la tabla
$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(240, 240, 240);

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
$pdf->Cell(130, 8, '', 1, 1);

$pdf->Cell(60, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'UBICACIÓN:'), 1, 0, 'L', true);
$pdf->Cell(130, 8, '', 1, 1);

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

$pdf->Ln(1);

$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Nota: Se solicita informar el traslado o algún daño sufrido por el equipo, esto para efectos de garantías o '), 0, 1);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'reparaciones a correo soporte.ti.hfbc@redsalud.gov.cl anexo 226868'), 0, 1);

$pdf->Ln(5);

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