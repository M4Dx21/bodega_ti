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
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'ACTA DE ENTREGA EQUIPAMIENTO COMPUTACIONAL
'), 0, 1, 'C');

$pdf->Ln(2);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'En Santiago '. date('d/m/Y H:i:s') . ' se hace entrega de equipamiento tecnológico de propiedad '), 0, 1);
$pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'de Hospital Dr. Félix Bulnes Cerda, el cual posee las siguientes características: '), 0, 1);

$pdf->Ln(5);


$pdf->SetFont('Arial','',10);
for ($i = 0; $i < count($codigos); $i++) {
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'TIPO DE EQUIPO: ' . $categorias[$i]), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MARCA: ' . $marca[$i]), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MODELO: ' . $insumos[$i]), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'SERIE: ' . $codigos[$i]), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'NOMBRE DEL EQUIPO: '), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'SERVICIO: '), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'UBICACIÓN: '), 0, 1);
    
    $pdf->Ln(3);

    $pdf->Cell(0,10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'PERSONAL DE TI QUE ENTREGA: ' . $usuario), 0, 1);
    
    $pdf->Ln(3);
    
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'RECIBIDO POR: '), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'RUT: '), 0, 1);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'FIRMA: '), 0, 1);
    
    $pdf->Ln(3);

    $pdf->Cell(0, 0, '', 'T');
    $pdf->Ln(5);
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