<?php
include 'db.php';

$codigo = $_GET['codigo'];
$stmt = $conn->prepare("SELECT codigo, insumo, marca, categoria, ubicacion, stock FROM componentes WHERE codigo = ?");
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "encontrado" => true,
        "codigo" => $row['codigo'],
        "insumo" => $row['insumo'],
        "marca" => $row['marca'],
        "categoria" => $row['categoria'],
        "ubicacion" => $row['ubicacion'],
        "stock" => $row['stock']
    ]);
} else {
    echo json_encode(["encontrado" => false]);
}
?>