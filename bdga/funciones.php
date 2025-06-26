<?php
require 'db.php';

function obtenerInsumosBajoStock($conn, $umbral = 5) {
    $sql = "SELECT 
                insumo, 
                SUM(stock) AS stock_total, 
                GROUP_CONCAT(DISTINCT ubicacion SEPARATOR ', ') AS ubicaciones
            FROM componentes
            GROUP BY insumo
            HAVING stock_total < ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $umbral);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $alertas = [];
    while ($row = $resultado->fetch_assoc()) {
        $alertas[] = [
            'insumo' => $row['insumo'],
            'stock' => $row['stock_total'],
            'ubicacion' => $row['ubicaciones']
        ];
    }

    return $alertas;
}

$editando = false;
$componente_edit = null;
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    mysqli_query($conn, "DELETE FROM componentes WHERE id = $id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['editar'])) {
    $editando = true;
    $id = $_GET['editar'];
    $resultado = mysqli_query($conn, "SELECT * FROM componentes WHERE id = $id");
    $componente_edit = mysqli_fetch_assoc($resultado);
}

if (isset($_POST['guardar_cambios'])) {
    $id = $_POST["id"];
    $nombre = $_POST["insumo"];
    $codigo = $_POST["codigo"];
    $cantidad = $_POST["stock"];
    $especialidad = $_POST["categoria"];
    $formato = $_POST["marca"];
    $estado = $_POST["estado"];
    $ubicacion = $_POST["ubicacion"];
    $observaciones = $_POST["observaciones"];
    $garantia = $_POST["garantia"];
    $fecha_ingreso = date('Y-m-d H:i:s');

    $update = "UPDATE componentes SET 
            insumo = '$nombre', 
            stock = stock + $cantidad, 
            categoria = '$especialidad', 
            marca = '$formato', 
            estado = '$estado', 
            ubicacion = '$ubicacion',
            observaciones = '$observaciones', 
            fecha_ingreso = '$fecha_ingreso',
            garantia = '$garantia' ".
            ($archivo_nombre ? ", comprobante = '$archivo_nombre'" : "") . "
           WHERE codigo = '$codigo'";
    mysqli_query($conn, $update);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}