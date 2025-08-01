<?php
session_start();
include 'db.php';
include 'funciones.php';

$nombre_usuario_filtro = isset($_GET['categoria']) ? $conn->real_escape_string($_GET['categoria']) : '';
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$sql_base = "FROM componentes WHERE 1";

if (!empty($nombre_usuario_filtro)) {
    $sql_base .= " AND (categoria LIKE '%$nombre_usuario_filtro%')";
}

$sql_total = "SELECT COUNT(*) as total FROM (
    SELECT COUNT(*) 
    " . $sql_base . " 
    GROUP BY insumo, marca, estado, ubicacion
) as agrupados";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

$sql_final = "SELECT 
                categoria, 
                SUM(stock) AS stock,
                SUM(precio) AS precio_total,
                MAX(fecha_ingreso) AS fecha_ingreso
            " . $sql_base . " 
            GROUP BY categoria
            ORDER BY fecha_ingreso DESC 
            LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $sql_final);
$personas_dentro = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $sql = "SELECT codigo, insumo FROM componentes 
            WHERE codigo LIKE '%$query%' OR insumo LIKE '%$query%' 
            LIMIT 10";
    $result = $conn->query($sql);
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['codigo'] . " - " . $row['insumo'];
    }
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            .botones-filtros {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
    </style>
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Gestion de Bodega TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>

        <div class="user-controls">
            <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
            <div id="accountInfo" style="display: none;">
                <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">Salir</button>
                </form>
            </div>
            <button type="button" class="volver-btn" onclick="window.history.go(-1);">Volver</button>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <div class="filters">
            <form method="GET" action="">
                <label for="codigo">Insumo:</label>
                <div class="input-sugerencias-wrapper">
                    <input type="text" id="codigo" name="codigo" autocomplete="off"
                        placeholder="Escribe el insumo para buscar..."
                        value="<?php echo htmlspecialchars($nombre_usuario_filtro); ?>">
                    <div id="sugerencias" class="sugerencias-box"></div>
                </div>
                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn" onclick="window.location='bodega.php'">Limpiar Filtros</button>
                </div>
            </form>
        </div>
        <?php if (!empty($personas_dentro)): ?>
            <h2>Lista de Insumos</h2>
                <table>
                    <tr>
                        <th>Categoría</th>
                        <th>Stock Total</th>
                        <th>Precio Total</th>
                        <th>Ver Detalles</th>
                    </tr>
                    <?php foreach ($personas_dentro as $componente): ?>
                        <tr>
                            <td><?= htmlspecialchars($componente['categoria']) ?></td>
                            <td><?= htmlspecialchars($componente['stock']) ?></td>
                            <td><?= htmlspecialchars(number_format($componente['precio_total'], 0, ',', '.')) ?> CLP</td>
                            <td>
                                <form action="bodegainterior.php" method="GET" style="margin: 0;">
                                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($componente['categoria']) ?>">
                                    <button type="submit" class="btn-dashboard" title="Ver insumos de esta categoría">
                                        🔍 Ver
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
        <?php else: ?>
            <p>No se encontraron resultados para tu búsqueda.</p>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('click', function(event) {
            const alertBtn = document.querySelector('.btn-alertas');
            const panel = document.getElementById('alertPanel');
            
            if (!alertBtn.contains(event.target) && event.target !== alertBtn) {
                panel.style.display = 'none';
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            const input = document.getElementById("codigo");
            const sugerenciasBox = document.getElementById("sugerencias");

            input.addEventListener("input", function() {
                const query = input.value;

                if (query.length < 2) {
                    sugerenciasBox.innerHTML = "";
                    sugerenciasBox.style.display = "none";
                    return;
                }

                fetch(`bodega.php?query=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        sugerenciasBox.innerHTML = "";
                        if (data.length === 0) {
                            sugerenciasBox.style.display = "none";
                            return;
                        }

                        data.forEach(item => {
                            const div = document.createElement("div");
                            div.textContent = item;
                            div.addEventListener("click", () => {
                                input.value = item.split(" - ")[0];
                                sugerenciasBox.innerHTML = "";
                                sugerenciasBox.style.display = "none";
                            });
                            sugerenciasBox.appendChild(div);
                        });
                        sugerenciasBox.style.display = "block";
                    });
            });

            document.addEventListener("click", function(e) {
                if (!sugerenciasBox.contains(e.target) && e.target !== input) {
                    sugerenciasBox.style.display = "none";
                }
            });
        });

        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
