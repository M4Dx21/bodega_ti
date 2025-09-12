<?php
session_start();
include 'db.php';

$filtro = isset($_GET['codigo']) ? trim($conn->real_escape_string($_GET['codigo'])) : '';
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$sql_base = "FROM historial WHERE 1";
if (!empty($filtro)) {
    $sql_base .= " AND (
        num_serie LIKE '%$filtro%' OR 
        cantidad LIKE '%$filtro%' OR 
        fecha LIKE '%$filtro%'
    )";
}

$sql_total = "SELECT COUNT(*) as total " . $sql_base;
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

$sql_final = "SELECT id, num_serie, cantidad, fecha " . $sql_base . " ORDER BY id DESC LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $sql_final);
$registros = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Historial de Movimientos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Historial de Insumos</div>
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

                    <button onclick="window.location.href='historials.php'">📑 Historial Salida</button>
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
                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn" onclick="window.location='salidas.php'">Limpiar Filtros</button>
                </div>
            </form>
        </div>

        <?php if (!empty($registros)): ?>
            <h2>Historial de movimientos</h2>
            <table>
                <tr>
                    <th>Número de Serie</th>
                    <th>Cantidad</th>
                    <th>Fecha</th>
                </tr>
                <?php foreach ($registros as $fila): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['num_serie']) ?></td>
                        <td><?= htmlspecialchars($fila['cantidad']) ?></td>
                        <td><?= htmlspecialchars($fila['fecha']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <form method="GET" style="margin-bottom: 10px;">
                <label for="cantidad">Mostrar:</label>
                <select name="cantidad" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 40, 50] as $cantidad): ?>
                        <option value="<?= $cantidad ?>" <?= $cantidad_por_pagina == $cantidad ? 'selected' : '' ?>><?= $cantidad ?></option>
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
                    echo '<a href="?pagina=' . $i . '&cantidad=' . $cantidad_por_pagina . '" class="' . $active . '">' . $i . '</a>';
                }

                if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) echo '<span>...</span>';
                    echo '<a href="?pagina=' . $total_paginas . '&cantidad=' . $cantidad_por_pagina . '">' . $total_paginas . '</a>';
                }

                if ($pagina_actual > 1) {
                    echo '<a href="?pagina=' . ($pagina_actual - 1) . '&cantidad=' . $cantidad_por_pagina . '">Anterior</a>';
                }

                if ($pagina_actual < $total_paginas) {
                    echo '<a href="?pagina=' . ($pagina_actual + 1) . '&cantidad=' . $cantidad_por_pagina . '">Siguiente</a>';
                }
                ?>
            </div>
        <?php else: ?>
            <p>No se encontraron registros en el historial.</p>
        <?php endif; ?>
    </div>

    <script>
        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>