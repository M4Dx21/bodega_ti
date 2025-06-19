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