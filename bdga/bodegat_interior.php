<?php
session_start();
include 'db.php';
include 'funciones.php';

date_default_timezone_set('America/Santiago');

$ESTADO_FIJO = 'EN TERRENO';

/** ====== Filtros ====== */
$categoria_filtro      = isset($_GET['categoria']) ? $conn->real_escape_string(trim($_GET['categoria'])) : '';
$nombre_usuario_filtro = isset($_GET['codigo'])    ? $conn->real_escape_string($_GET['codigo'])         : '';
$fecha_inicio          = $_GET['fecha_inicio']     ?? '';
$fecha_fin             = $_GET['fecha_fin']        ?? '';

/** ====== Paginación ====== */
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10,20,30,40,50]) ? $cantidad_por_pagina : 10;
$pagina_actual       = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual       = max(1, $pagina_actual);
$offset              = ($pagina_actual - 1) * $cantidad_por_pagina;

/** ====== Base SOLO EN TERRENO + filtros ====== */
$sql_base = "FROM componentes WHERE estado = '$ESTADO_FIJO'";

if ($categoria_filtro !== '') {
    // igual que en bodega/bodegainterior: por igualdad o like? allí usas igualdad en interior
    $sql_base .= " AND categoria = '".$categoria_filtro."'";
}

if ($nombre_usuario_filtro !== '') {
    $like = "%$nombre_usuario_filtro%";
    $sql_base .= " AND (codigo LIKE '".$conn->real_escape_string($like)."' 
                     OR insumo LIKE '".$conn->real_escape_string($like)."')";
}

if ($fecha_inicio !== '') {
    $sql_base .= " AND fecha_ingreso >= '".$conn->real_escape_string($fecha_inicio)." 00:00:00'";
}
if ($fecha_fin !== '') {
    $sql_base .= " AND fecha_ingreso <= '".$conn->real_escape_string($fecha_fin)." 23:59:59'";
}

/** ====== Total páginas (agrupado como en bodegainterior.php) ====== */
$sql_total = "SELECT COUNT(*) as total FROM (
    SELECT COUNT(*) 
    $sql_base
    GROUP BY insumo, marca, ubicacion
) as agrupados";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas     = (int)mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas   = max(1, ceil($total_filas / $cantidad_por_pagina));

/** ====== Listado agrupado (igual estilo que bodegainterior.php) ====== */
$sql_final = "SELECT 
                insumo,
                marca,
                ubicacion,
                MAX(fecha_ingreso) AS fecha_ingreso,
                SUM(stock) AS stock
             $sql_base
             GROUP BY insumo, marca, ubicacion
             ORDER BY fecha_ingreso DESC
             LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $sql_final);
$items     = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

/** ====== Autocompletado SOLO EN TERRENO + mismos filtros ====== */
if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);

    $where = "estado = '$ESTADO_FIJO' AND (codigo LIKE '%$query%' OR insumo LIKE '%$query%')";
    if ($categoria_filtro !== '') {
        $where .= " AND categoria = '".$categoria_filtro."'";
    }
    if ($fecha_inicio !== '') {
        $where .= " AND fecha_ingreso >= '".$conn->real_escape_string($fecha_inicio)." 00:00:00'";
    }
    if ($fecha_fin !== '') {
        $where .= " AND fecha_ingreso <= '".$conn->real_escape_string($fecha_fin)." 23:59:59'";
    }

    $sql = "SELECT codigo, insumo FROM componentes WHERE $where LIMIT 10";
    $res = $conn->query($sql);
    $sug = [];
    while($row = $res->fetch_assoc()){ $sug[] = $row['codigo'].' - '.$row['insumo']; }
    header('Content-Type: application/json'); echo json_encode($sug); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="UTF-8">
    <title>Insumos en Terreno · Interior</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .botones-filtros{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pagination-container{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:12px}
        .pagination-container a{padding:6px 10px;border:1px solid #ccc;border-radius:4px;text-decoration:none}
        .pagination-container a.active{background:#ddd;font-weight:700}
        .input-sugerencias-wrapper{position:relative}
        .sugerencias-box{position:absolute;left:0;right:0;background:#fff;border:1px solid #ddd;display:none;z-index:10}
        .sugerencias-box > div{padding:6px 8px;cursor:pointer}
        .sugerencias-box > div:hover{background:#f3f3f3}
    </style>

    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Insumos Generales</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <div class="user-controls">
            <button id="cuenta-btn" onclick="toggleAccountInfo()"><?=
                htmlspecialchars($_SESSION['nombre'] ?? '') ?></button>
            <div id="accountInfo" style="display:none;">
                <p><strong>Usuario: </strong><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></p>
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
            <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria_filtro) ?>">

            <label for="codigo">Insumo:</label>
            <div class="input-sugerencias-wrapper">
                <input type="text" id="codigo" name="codigo" autocomplete="off"
                       placeholder="Escribe el insumo o código…"
                       value="<?= htmlspecialchars($nombre_usuario_filtro) ?>">
                <div id="sugerencias" class="sugerencias-box"></div>
            </div>

            <label for="fecha_inicio">Desde:</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">

            <label for="fecha_fin">Hasta:</label>
            <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">

            <div class="botones-filtros">
                <button type="submit">Filtrar</button>
                <button type="button" class="limpiar-filtros-btn"
                            onclick="window.location='bodegat_interior.php<?= $categoria_filtro !== '' ? '?categoria=' . urlencode($categoria_filtro) : '' ?>'">
                        Limpiar Filtros
                </button>
            </div>
        </form>
    </div>

    <?php if(!empty($items)): ?>
        <h2>Modelos en “<?= htmlspecialchars($categoria_filtro) ?>”</h2>
        <table>
            <tr>
                <th>Insumo</th>
                <th>Marca</th>
                <th>Ubicación</th>
                <th>Stock</th>
                <th>Último Ingreso</th>
                <th>Ver</th>
            </tr>
            <?php foreach($items as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['insumo']) ?></td>
                    <td><?= htmlspecialchars($r['marca']) ?></td>
                    <td><?= htmlspecialchars($r['ubicacion']) ?></td>
                    <td><?= htmlspecialchars($r['stock']) ?></td>
                    <td><?= htmlspecialchars($r['fecha_ingreso']) ?></td>
                    <td>
                        <form action="bodegat_interior2.php" method="GET" style="margin:0;">
                            <input type="hidden" name="modelo" value="<?= htmlspecialchars($r['insumo']) ?>">
                            <button type="submit" class="btn-dashboard">🔍 Ver</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Selector "Mostrar X" como en bodega -->
        <form method="GET" style="margin-bottom:10px;">
            <label for="cantidad">Mostrar:</label>
            <select name="cantidad" onchange="this.form.submit()">
                <?php foreach ([10,20,30,40,50] as $cantidad): ?>
                    <option value="<?= $cantidad ?>" <?= $cantidad_por_pagina == $cantidad ? 'selected' : '' ?>>
                        <?= $cantidad ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($categoria_filtro !== ''): ?>
                <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria_filtro) ?>">
            <?php endif; ?>
            <?php if ($nombre_usuario_filtro !== ''): ?>
                <input type="hidden" name="codigo" value="<?= htmlspecialchars($nombre_usuario_filtro) ?>">
            <?php endif; ?>
            <?php if ($fecha_inicio !== ''): ?>
                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
            <?php endif; ?>
            <?php if ($fecha_fin !== ''): ?>
                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
            <?php endif; ?>
            <input type="hidden" name="pagina" value="1">
        </form>

        <!-- Paginación (misma lógica que bodega/bodegainterior) -->
        <div class="pagination-container">
            <?php
            function enlace($pag, $cant, $cat, $cod, $fi, $ff) {
                $qs = ['pagina'=>$pag, 'cantidad'=>$cant];
                if ($cat !== '') $qs['categoria']    = $cat;
                if ($cod !== '') $qs['codigo']       = $cod;
                if ($fi  !== '') $qs['fecha_inicio'] = $fi;
                if ($ff  !== '') $qs['fecha_fin']    = $ff;
                return '?' . http_build_query($qs);
            }

            $rango_visible = 5;
            $inicio = max(1, $pagina_actual - floor($rango_visible / 2));
            $fin    = min($total_paginas, $inicio + $rango_visible - 1);

            if ($inicio > 1) {
                echo '<a href="'.enlace(1, $cantidad_por_pagina, $categoria_filtro, $nombre_usuario_filtro, $fecha_inicio, $fecha_fin).'">1</a>';
                if ($inicio > 2) echo '<span>…</span>';
            }

            for ($i = $inicio; $i <= $fin; $i++) {
                $active = $pagina_actual == $i ? 'active' : '';
                echo '<a class="'.$active.'" href="'.
                    enlace($i, $cantidad_por_pagina, $categoria_filtro, $nombre_usuario_filtro, $fecha_inicio, $fecha_fin).
                    '">'.$i.'</a>';
            }

            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) echo '<span>…</span>';
                echo '<a href="'.enlace($total_paginas, $cantidad_por_pagina, $categoria_filtro, $nombre_usuario_filtro, $fecha_inicio, $fecha_fin).'">'.$total_paginas.'</a>';
            }

            if ($pagina_actual > 1) {
                echo '<a href="'.enlace($pagina_actual-1, $cantidad_por_pagina, $categoria_filtro, $nombre_usuario_filtro, $fecha_inicio, $fecha_fin).'">Anterior</a>';
            }
            if ($pagina_actual < $total_paginas) {
                echo '<a href="'.enlace($pagina_actual+1, $cantidad_por_pagina, $categoria_filtro, $nombre_usuario_filtro, $fecha_inicio, $fecha_fin).'">Siguiente</a>';
            }
            ?>
        </div>
    <?php else: ?>
        <p>No hay resultados para esta categoría.</p>
    <?php endif; ?>
</div>

<script>
function toggleAccountInfo() {
  const info = document.getElementById('accountInfo');
  info.style.display = (info.style.display === 'none' || info.style.display === '') ? 'block' : 'none';
}

document.addEventListener("DOMContentLoaded", function(){
    const input = document.getElementById("codigo");
    const box   = document.getElementById("sugerencias");
    input.addEventListener("input", function(){
        const q = input.value;
        const params = new URLSearchParams(window.location.search);
        if (q.length < 2) { box.innerHTML=""; box.style.display="none"; return; }
        const url = new URL(window.location.href);
        const categoria = params.get('categoria') || '';
        const fi = document.getElementById('fecha_inicio')?.value || '';
        const ff = document.getElementById('fecha_fin')?.value    || '';
        fetch(`bodegat_interior.php?query=${encodeURIComponent(q)}&categoria=${encodeURIComponent(categoria)}&fecha_inicio=${encodeURIComponent(fi)}&fecha_fin=${encodeURIComponent(ff)}`)
            .then(r=>r.json()).then(d=>{
                box.innerHTML=""; if (d.length===0) { box.style.display="none"; return; }
                d.forEach(item=>{
                    const div=document.createElement("div");
                    div.textContent=item;
                    div.addEventListener("click",()=>{ input.value=item.split(" - ")[0]; box.innerHTML=""; box.style.display="none"; });
                    box.appendChild(div);
                });
                box.style.display="block";
            }).catch(()=>{ box.innerHTML=""; box.style.display="none"; });
    });
    document.addEventListener("click", e=>{ if(!box.contains(e.target) && e.target!==input){ box.style.display="none"; }});
});
</script>
</body>
</html>