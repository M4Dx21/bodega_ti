<?php
include 'db.php';
header('Content-Type: application/json');

$codigo = $_GET['codigo'] ?? '';
$response = ['encontrado' => false];

if ($codigo) {
    $stmt = $conn->prepare("SELECT * FROM componentes WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($row = $resultado->fetch_assoc()) {
        $response = [
            'encontrado' => true,
            'insumo' => $row['insumo'],
            'codigo' => $row['codigo'],
            'categoria' => $row['categoria'],
            'marca' => $row['marca'],
            'ubicacion' => $row['ubicacion'],
            'estado' => $row['estado'],
            'caracteristicas' => $row['caracteristicas'],
            'ubicacion' => $row['ubicacion'],
            'observaciones' => $row['observaciones']

        ];
    }
    $stmt->close();
}

echo json_encode($response);
$conn->close();