<?php
session_start();
include 'db.php';

$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$sql_base = "FROM componentes WHERE stock < 10";

$sql_total = "SELECT COUNT(*) as total FROM (
    SELECT categoria
    $sql_base
    GROUP BY categoria
) as agrupados";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

$sql_final = "SELECT 
                categoria, 
                SUM(stock) AS stock_total,
                SUM(precio) AS precio_total,
                MAX(fecha_ingreso) AS fecha_ingreso
            $sql_base
            GROUP BY categoria
            HAVING stock_total < 10
            ORDER BY fecha_ingreso DESC 
            LIMIT $cantidad_por_pagina OFFSET $offset";

$resultado = mysqli_query($conn, $sql_final);
$categorias = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Alertas de Stock</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Alertas de Stock</div>
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

    <div class="container">
            <div class="botonera">
                <button onclick="window.location.href='bodegat.php'">🖥️ Insumos General</button>
                <button onclick="window.location.href='agregarcomp.php'">🗄️ Agregar Insumos</button>
                <button onclick="window.location.href='bodega.php'">📦 Control de bodega</button>
                <button onclick="window.location.href='historiale.php'">📑 Historial Entrada</button>
                <button onclick="window.location.href='historials.php'">📑 Historial Salida</button>
                <button class="btn-alerta" onclick="window.location.href='alertas.php'">🚨 Alertas de Stock</button>
            </div>
        <h2>Insumos con Stock Bajo</h2>
        <?php if (!empty($categorias)): ?>
            <table>
                <tr>
                    <th>Categoría</th>
                    <th>Stock Total</th>
                    <th>Precio Total</th>
                    <th>Ver Detalles</th>
                </tr>
                <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['categoria']) ?></td>
                        <td style="color: red; font-weight: bold;"><?= htmlspecialchars($cat['stock_total']) ?></td>
                        <td><?= htmlspecialchars(number_format($cat['precio_total'], 0, ',', '.')) ?> CLP</td>
                        <td>
                            <form action="bodegainterior.php" method="GET" style="margin: 0;">
                                <input type="hidden" name="categoria" value="<?= htmlspecialchars($cat['categoria']) ?>">
                                <button type="submit" class="btn-dashboard" title="Ver insumos de esta categoría">
                                    🔍 Ver
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No hay insumos con stock bajo en este momento ✅</p>
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
