<?php
session_start();
include 'db.php';
include 'funciones.php';

date_default_timezone_set('America/Santiago');

$ESTADO_FIJO = 'EN TERRENO';

$nombre_usuario_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';
$cantidad_por_pagina   = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina   = in_array($cantidad_por_pagina, [10,20,30,40,50]) ? $cantidad_por_pagina : 10;
$pagina_actual         = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual         = max(1, $pagina_actual);
$offset                = ($pagina_actual - 1) * $cantidad_por_pagina;

$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin    = $_GET['fecha_fin'] ?? '';

/** Base SOLO EN TERRENO */
$sql_base = "FROM componentes WHERE estado = '$ESTADO_FIJO'";

if (!empty($nombre_usuario_filtro)) {
    $sql_base .= " AND (codigo LIKE '%$nombre_usuario_filtro%' OR insumo LIKE '%$nombre_usuario_filtro%' OR categoria LIKE '%$nombre_usuario_filtro%')";
}
if ($fecha_inicio !== '') {
    $sql_base .= " AND fecha_ingreso >= '$fecha_inicio 00:00:00'";
}
if ($fecha_fin !== '') {
    $sql_base .= " AND fecha_ingreso <= '$fecha_fin 23:59:59'";
}

/** Total páginas por categoría */
$sql_total = "SELECT COUNT(*) as total FROM (
    SELECT COUNT(*) 
    $sql_base
    GROUP BY categoria
) as agrupados";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas     = (int)mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas   = max(1, ceil($total_filas / $cantidad_por_pagina));

/** Listado principal agrupado por categoría */
$sql_final = "SELECT 
                categoria,
                SUM(stock) AS stock,
                SUM(precio) AS precio_total,
                MAX(fecha_ingreso) AS fecha_ingreso
             $sql_base
             GROUP BY categoria
             ORDER BY fecha_ingreso DESC
             LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado       = mysqli_query($conn, $sql_final);
$categorias_data = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

/** Autocompletado EN TERRENO */
if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $sql   = "SELECT codigo, insumo, categoria 
              FROM componentes 
              WHERE estado = '$ESTADO_FIJO'
                AND (codigo LIKE '%$query%' OR insumo LIKE '%$query%' OR categoria LIKE '%$query%')
              LIMIT 10";
    $res   = $conn->query($sql);
    $suggestions = [];
    while ($row = $res->fetch_assoc()) {
        $suggestions[] = $row['codigo'] . " - " . $row['insumo'] . " (" . $row['categoria'] . ")";
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
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Insumos Generales</div>
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
<div class="container">
    <div class="botonera">
            <button onclick="window.location.href='bodega.php'">📦 Control de bodega</button>
            <button onclick="window.location.href='agregarcomp.php'">🗄️ Agregar Insumos</button>
            <button onclick="window.location.href='exportar_excel.php'">📤 Exportar Excel</button>
            <button onclick="window.location.href='historiale.php'">📑 Historial Entrada</button>
            <button onclick="window.location.href='historials.php'">📑 Historial Salida</button>
            <button class="btn-alerta" onclick="window.location.href='alertas.php'">🚨 Alertas de Stock</button>
        </div>
    <div style="margin:10px 0;">
        <span style="display:inline-block;background:#eef;border:1px solid #99f;color:#33f;padding:6px 10px;border-radius:6px;font-weight:600;">
            “EN TERRENO”
        </span>
    </div>

    <div class="filters">
        <form method="GET" action="">
            <label for="codigo">Buscar:</label>
            <div class="input-sugerencias-wrapper">
                <input type="text" id="codigo" name="codigo" autocomplete="off"
                       placeholder="Código / Insumo / Categoría"
                       value="<?= htmlspecialchars($nombre_usuario_filtro) ?>">
                <div id="sugerencias" class="sugerencias-box"></div>
            </div>

            <label for="fecha_inicio">Desde:</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">

            <label for="fecha_fin">Hasta:</label>
            <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">

            <div class="botones-filtros">
                <button type="submit">Filtrar</button>
                <button type="button" class="limpiar-filtros-btn" onclick="window.location='bodegat.php'">Limpiar Filtros</button>
            </div>
        </form>
    </div>

    <?php if (!empty($categorias_data)): ?>
        <h2>Categorías</h2>
        <table>
            <tr>
                <th>Categoría</th>
                <th>Stock Total</th>
                <th>Precio Total</th>
                <th>Ver Detalles</th>
            </tr>
            <?php foreach ($categorias_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['categoria']) ?></td>
                    <td><?= htmlspecialchars($row['stock']) ?></td>
                    <td><?= htmlspecialchars(number_format($row['precio_total'], 0, ',', '.')) ?> CLP</td>
                    <td>
                        <form action="bodegat_interior.php" method="GET" style="margin:0;">
                            <input type="hidden" name="categoria" value="<?= htmlspecialchars($row['categoria']) ?>">
                            <button type="submit" class="btn-dashboard">🔍 Ver</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No hay insumos en terreno para este filtro.</p>
    <?php endif; ?>
</div>

<script>
function toggleAccountInfo(){const i=document.getElementById('accountInfo');i.style.display=(i.style.display==='none'||i.style.display==='')?'block':'none';}
document.addEventListener("DOMContentLoaded",function(){
    const input=document.getElementById("codigo"), box=document.getElementById("sugerencias");
    input.addEventListener("input",function(){
        const q=input.value;
        if(q.length<2){box.innerHTML="";box.style.display="none";return;}
        fetch(`bodegat.php?query=${encodeURIComponent(q)}`).then(r=>r.json()).then(d=>{
            box.innerHTML=""; if(d.length===0){box.style.display="none";return;}
            d.forEach(item=>{
                const div=document.createElement("div"); div.textContent=item;
                div.addEventListener("click",()=>{input.value=item.split(" - ")[0]; box.innerHTML=""; box.style.display="none";});
                box.appendChild(div);
            }); box.style.display="block";
        }).catch(()=>{box.innerHTML="";box.style.display="none";});
    });
    document.addEventListener("click",e=>{ if(!box.contains(e.target) && e.target!==input){ box.style.display="none"; }});
});
</script>
</body>
</html>