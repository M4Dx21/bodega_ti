<?php
session_start();
include 'db.php';

$filtro = isset($_GET['codigo']) ? trim($conn->real_escape_string($_GET['codigo'])) : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $conn->real_escape_string($_GET['fecha_inicio']) : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $conn->real_escape_string($_GET['fecha_fin']) : '';

$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$sql_base = "FROM salidas WHERE 1";

if (!empty($filtro)) {
    $sql_base .= " AND (
        num_serie LIKE '%$filtro%' OR 
        responsable LIKE '%$filtro%' OR
        observaciones LIKE '%$filtro%'
    )";
}

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql_base .= " AND DATE(fecha_salida) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
} elseif (!empty($fecha_inicio)) {
    $sql_base .= " AND DATE(fecha_salida) >= '$fecha_inicio'";
} elseif (!empty($fecha_fin)) {
    $sql_base .= " AND DATE(fecha_salida) <= '$fecha_fin'";
}

$sql_total = "SELECT COUNT(*) as total " . $sql_base;
$sql_final = "SELECT * " . $sql_base . " ORDER BY fecha_salida DESC LIMIT $cantidad_por_pagina OFFSET $offset";

$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

$resultado = mysqli_query($conn, $sql_final);
$salidas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

// Autocompletado
if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $sql = "SELECT num_serie, observaciones FROM salidas 
            WHERE num_serie LIKE '%$query%' OR observaciones LIKE '%$query%' 
            LIMIT 10";
    $result = $conn->query($sql);
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['num_serie'] . " - " . $row['observaciones'];
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
    <meta charset="UTF-8">
    <title>Control de Salidas - Bodega</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Historial de Salidas de Insumos</div>
            <div class="sub-title">Bodega Central</div>
        </div>
        <div class="user-controls">
            <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
            <div id="accountInfo" style="display: none;">
                <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">Salir</button>
                </form>
            </div>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <div class="botonera">
            <form action="agregarcomp.php" method="post">
                <button type="submit">🗄️ Agregar Insumos</button>
            </form>
            <button onclick="window.location.href='bodega.php'">📦 Control de bodega</button>
            <button onclick="window.location.href='historiale.php'">📑 Historial Entrada</button>
            <button class="btn-alerta" onclick="window.location.href='alertas.php'">🚨 Alertas de Stock</button>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <label for="codigo">Buscar:</label>
                <div class="input-sugerencias-wrapper">
                    <input type="text" id="codigo" name="codigo" autocomplete="off"
                        placeholder="Filtrar por número de serie, responsable o detalle..."
                        value="<?php echo htmlspecialchars($filtro); ?>">
                    <div id="sugerencias" class="sugerencias-box"></div>
                </div>

                <label for="fecha_inicio">Desde:</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">

                <label for="fecha_fin">Hasta:</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">

                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn" onclick="window.location='salidas.php'">Limpiar Filtros</button>
                </div>
            </form>
        </div>

        <?php if (!empty($salidas)): ?>
            <h2>Historial de Salidas</h2>
            <table>
                <tr>
                    <th>N° Serie</th>
                    <th>Cantidad</th>
                    <th>Fecha de Salida</th>
                    <th>Responsable</th>
                    <th>Observaciones</th>
                </tr>
                <?php foreach ($salidas as $salida): ?>
                <tr>
                    <td><?= htmlspecialchars($salida['num_serie']) ?></td>
                    <td><?= htmlspecialchars($salida['cantidad']) ?></td>
                    <td><?= htmlspecialchars($salida['fecha_salida']) ?></td>
                    <td><?= htmlspecialchars($salida['responsable']) ?></td>
                    <td><?= htmlspecialchars($salida['observaciones']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <form method="GET" style="margin-bottom: 10px;">
                <label for="cantidad">Mostrar:</label>
                <select name="cantidad" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 40, 50] as $c): ?>
                        <option value="<?= $c ?>" <?= $cantidad_por_pagina == $c ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="pagina" value="1">
            </form>

            <div class="pagination-container">
                <?php
                $rango_visible = 5;
                $inicio = max(1, $pagina_actual - floor($rango_visible / 2));
                $fin = min($total_paginas, $inicio + $rango_visible - 1);

                if ($inicio > 1) {
                    echo '<a href="?pagina=1&cantidad=' . $cantidad_por_pagina . '">1</a>';
                    if ($inicio > 2) echo '<span>...</span>';
                }

                for ($i = $inicio; $i <= $fin; $i++) {
                    $active = $pagina_actual == $i ? 'active' : '';
                    echo '<a href="?pagina=' . $i . '&cantidad=' . $cantidad_por_pagina 
                         . '&codigo=' . urlencode($filtro) 
                         . '&fecha_inicio=' . urlencode($fecha_inicio) 
                         . '&fecha_fin=' . urlencode($fecha_fin) 
                         . '" class="' . $active . '">' . $i . '</a>';
                }

                if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) echo '<span>...</span>';
                    echo '<a href="?pagina=' . $total_paginas . '&cantidad=' . $cantidad_por_pagina 
                         . '&codigo=' . urlencode($filtro) 
                         . '&fecha_inicio=' . urlencode($fecha_inicio) 
                         . '&fecha_fin=' . urlencode($fecha_fin) 
                         . '">' . $total_paginas . '</a>';
                }

                if ($pagina_actual > 1) {
                    echo '<a href="?pagina=' . ($pagina_actual - 1) . '&cantidad=' . $cantidad_por_pagina 
                         . '&codigo=' . urlencode($filtro) 
                         . '&fecha_inicio=' . urlencode($fecha_inicio) 
                         . '&fecha_fin=' . urlencode($fecha_fin) 
                         . '">Anterior</a>';
                }

                if ($pagina_actual < $total_paginas) {
                    echo '<a href="?pagina=' . ($pagina_actual + 1) . '&cantidad=' . $cantidad_por_pagina 
                         . '&codigo=' . urlencode($filtro) 
                         . '&fecha_inicio=' . urlencode($fecha_inicio) 
                         . '&fecha_fin=' . urlencode($fecha_fin) 
                         . '">Siguiente</a>';
                }
                ?>
            </div>
        <?php else: ?>
            <p>No se encontraron resultados para tu búsqueda.</p>
        <?php endif; ?>
    </div>

    <script>
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

                fetch(`salidas.php?query=${encodeURIComponent(query)}`)
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