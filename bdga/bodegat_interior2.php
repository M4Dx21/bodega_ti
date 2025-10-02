<?php
session_start();
include 'db.php';
include 'funciones.php';

date_default_timezone_set('America/Santiago');

$ESTADO_FIJO = 'EN TERRENO';

/** ====== Filtros ====== */
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10,20,30,40,50]) ? $cantidad_por_pagina : 10;
$pagina_actual       = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual       = max(1, $pagina_actual);
$offset              = ($pagina_actual - 1) * $cantidad_por_pagina;

$codigo_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';
$modelo_filtro = isset($_GET['modelo']) ? $conn->real_escape_string(urldecode(trim($_GET['modelo']))) : '';
$fecha_inicio  = $_GET['fecha_inicio'] ?? '';
$fecha_fin     = $_GET['fecha_fin'] ?? '';

/** Para reconstruir QS tras acciones */
$qsFiltros = [];
if ($modelo_filtro !== '') $qsFiltros['modelo']  = $modelo_filtro;
if ($codigo_filtro !== '') $qsFiltros['codigo']  = $codigo_filtro;
if ($fecha_inicio  !== '') $qsFiltros['fecha_inicio'] = $fecha_inicio;
if ($fecha_fin     !== '') $qsFiltros['fecha_fin']    = $fecha_fin;
$qsFiltros['cantidad'] = $cantidad_por_pagina;
$qsFiltros['pagina']   = $pagina_actual;
$qsFiltros = http_build_query($qsFiltros);

/** ====== Base SOLO EN TERRENO + filtros ====== */
$sql_base = "FROM componentes WHERE estado = '$ESTADO_FIJO'";

if ($modelo_filtro !== '') {
    $sql_base .= " AND insumo = '$modelo_filtro'";
}
if ($codigo_filtro !== '') {
    $like = "%$codigo_filtro%";
    $like = $conn->real_escape_string($like);
    $sql_base .= " AND (codigo LIKE '$like' OR insumo LIKE '$like')";
}
if ($fecha_inicio !== '') {
    $fi = $conn->real_escape_string($fecha_inicio);
    $sql_base .= " AND fecha_ingreso >= '$fi 00:00:00'";
}
if ($fecha_fin !== '') {
    $ff = $conn->real_escape_string($fecha_fin);
    $sql_base .= " AND fecha_ingreso <= '$ff 23:59:59'";
}

/** ====== Total registros ====== */
$sql_total = "SELECT COUNT(*) as total $sql_base";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas     = (int)mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas   = max(1, ceil($total_filas / $cantidad_por_pagina));

/** ====== Listado final ====== */
$sql_final = "SELECT * 
              $sql_base
              ORDER BY fecha_ingreso DESC
              LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $sql_final);
$rows      = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

/** ====== Autocompletado: SOLO EN TERRENO + respeta modelo/fechas ====== */
if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $where = "estado = '$ESTADO_FIJO' AND (codigo LIKE '%$query%' OR insumo LIKE '%$query%')";
    if ($modelo_filtro !== '') {
        $where .= " AND insumo = '$modelo_filtro'";
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
    while($r=$res->fetch_assoc()){ $sug[] = $r['codigo'].' - '.$r['insumo']; }
    header('Content-Type: application/json'); echo json_encode($sug); exit();
}

/** ====== Comprobante ====== */
if (isset($_GET['comprobante'])) {
    $id = (int)$_GET['comprobante'];
    $stmt = $conn->prepare("SELECT comprobante FROM componentes WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && !empty($res['comprobante'])) {
        $file = __DIR__ . '/comprobantes/' . $res['comprobante'];
        if (is_file($file)) {
            $mime = mime_content_type($file);
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="'.basename($file).'"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            echo "<script>alert('El archivo del comprobante no se encontró');history.back();</script>"; exit;
        }
    } else {
        echo "<script>alert('El insumo no tiene comprobante');history.back();</script>"; exit;
    }
}

/** ====== Eliminar ====== */
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $stmt = $conn->prepare("DELETE FROM componentes WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: bodegat_interior2.php?'.$qsFiltros);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="UTF-8">
    <title>Insumos en Terreno · Detalle</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .btn-accion{font-size:10px;padding:3px 6px;margin-right:3px;border:none;border-radius:3px;background:#007bff;color:#fff;text-decoration:none;display:inline-block;transition:background-color .2s}
        .btn-accion:hover{background:#0056b3}
        .btn-eliminar{background:#dc3545}.btn-eliminar:hover{background:#a71d2a}
        .btn-ver{background:#28a745}.btn-ver:hover{background:#1e7e34}
        .btn-acciones-group{display:flex;gap:4px;justify-content:center;align-items:center;flex-wrap:wrap}
        .botones-filtros{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pagination-container{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:12px}
        .pagination-container a{padding:6px 10px;border:1px solid #ccc;border-radius:4px;text-decoration:none}
        .pagination-container a.active{background:#ddd;font-weight:700}
        .input-sugerencias-wrapper{position:relative}
        .sugerencias-box{position:absolute;left:0;right:0;background:#fff;border:1px solid #ddd;display:none;z-index:10}
        .sugerencias-box>div{padding:6px 8px;cursor:pointer}
        .sugerencias-box>div:hover{background:#f3f3f3}
    </style>
</head>
<body>
<div class="header">
    <img src="asset/logo.png" alt="Logo">
    <div class="header-text">
        <div class="main-title">Insumos Generales</div>
        <div class="sub-title">Hospital Clínico Félix Bulnes</div>
    </div>
    <div class="user-controls">
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></button>
        <div id="accountInfo" style="display:none;">
            <p><strong>Usuario: </strong><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></p>
            <form action="logout.php" method="POST"><button type="submit" class="logout-btn">Salir</button></form>
        </div>
        <button type="button" class="volver-btn" onclick="window.history.go(-1);">Volver</button>
    </div>
</div>

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
            <label for="modelo">Modelo:</label>
            <input type="text" id="modelo" name="modelo" value="<?= htmlspecialchars($modelo_filtro) ?>" placeholder="Nombre exacto del insumo">

            <label for="codigo">Buscar:</label>
            <div class="input-sugerencias-wrapper">
                <input type="text" id="codigo" name="codigo" autocomplete="off"
                       placeholder="Código / Insumo"
                       value="<?= htmlspecialchars($codigo_filtro) ?>">
                <div id="sugerencias" class="sugerencias-box"></div>
            </div>

            <label for="fecha_inicio">Desde:</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">

            <label for="fecha_fin">Hasta:</label>
            <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">

            <div class="botones-filtros">
                <button type="submit">Filtrar</button>
                <button type="button" class="limpiar-filtros-btn"
                        onclick="window.location='bodegat_interior2.php<?= $modelo_filtro !== '' ? '?modelo=' . urlencode($modelo_filtro) : '' ?>'">
                        Limpiar Filtros
                </button>
            </div>
        </form>
    </div>

    <?php if(!empty($rows)): ?>
        <table>
            <tr>
                <th>Código</th>
                <th>Insumo</th>
                <th>Marca</th>
                <th>Categoría</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Stock</th>
                <th>Precio</th>
                <th>Fecha Ingreso</th>
                <th>Acciones</th>
            </tr>
            <?php foreach($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['codigo']) ?></td>
                    <td><?= htmlspecialchars($r['insumo']) ?></td>
                    <td><?= htmlspecialchars($r['marca']) ?></td>
                    <td><?= htmlspecialchars($r['categoria']) ?></td>
                    <td><?= htmlspecialchars($r['ubicacion']) ?></td>
                    <td><?= htmlspecialchars($r['estado']) ?></td>
                    <td><?= htmlspecialchars($r['stock']) ?></td>
                    <td><?= htmlspecialchars(number_format($r['precio'],0,',','.')) ?> CLP</td>
                    <td><?= htmlspecialchars($r['fecha_ingreso']) ?></td>
                    <td class="btn-acciones-group">
                        <a href="agregarcomp.php?editar=<?= (int)$r['id'] ?>&<?= $qsFiltros ?>" class="btn-accion">Editar</a>
                        <a href="?eliminar=<?= (int)$r['id'] ?>&<?= $qsFiltros ?>" class="btn-accion btn-eliminar"
                           onclick="return confirm('¿Estás seguro de eliminar este componente?');">Eliminar</a>
                        <?php if(!empty($r['comprobante'])): ?>
                            <a href="?comprobante=<?= (int)$r['id'] ?>" class="btn-accion btn-ver" target="_blank">Comprobante</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Selector "Mostrar X" -->
        <form method="GET" style="margin-bottom:10px;">
            <label for="cantidad">Mostrar:</label>
            <select name="cantidad" onchange="this.form.submit()">
                <?php foreach ([10,20,30,40,50] as $cantidad): ?>
                    <option value="<?= $cantidad ?>" <?= $cantidad_por_pagina == $cantidad ? 'selected' : '' ?>>
                        <?= $cantidad ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($modelo_filtro !== ''): ?>
                <input type="hidden" name="modelo" value="<?= htmlspecialchars($modelo_filtro) ?>">
            <?php endif; ?>
            <?php if ($codigo_filtro !== ''): ?>
                <input type="hidden" name="codigo" value="<?= htmlspecialchars($codigo_filtro) ?>">
            <?php endif; ?>
            <?php if ($fecha_inicio !== ''): ?>
                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
            <?php endif; ?>
            <?php if ($fecha_fin !== ''): ?>
                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
            <?php endif; ?>
            <input type="hidden" name="pagina" value="1">
        </form>

        <!-- Paginación con rango y extremos -->
        <div class="pagination-container">
            <?php
            function enlace_pag($pag, $cant, $modelo, $codigo, $fi, $ff) {
                $qs = ['pagina'=>$pag, 'cantidad'=>$cant];
                if ($modelo !== '') $qs['modelo'] = $modelo;
                if ($codigo !== '') $qs['codigo'] = $codigo;
                if ($fi  !== '')   $qs['fecha_inicio'] = $fi;
                if ($ff  !== '')   $qs['fecha_fin']    = $ff;
                return '?' . http_build_query($qs);
            }

            $rango_visible = 5;
            $inicio = max(1, $pagina_actual - floor($rango_visible / 2));
            $fin    = min($total_paginas, $inicio + $rango_visible - 1);

            if ($inicio > 1) {
                echo '<a href="'.enlace_pag(1, $cantidad_por_pagina, $modelo_filtro, $codigo_filtro, $fecha_inicio, $fecha_fin).'">1</a>';
                if ($inicio > 2) echo '<span>…</span>';
            }

            for ($i = $inicio; $i <= $fin; $i++) {
                $active = $pagina_actual == $i ? 'active' : '';
                echo '<a class="'.$active.'" href="'.enlace_pag($i, $cantidad_por_pagina, $modelo_filtro, $codigo_filtro, $fecha_inicio, $fecha_fin).'">'.$i.'</a>';
            }

            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) echo '<span>…</span>';
                echo '<a href="'.enlace_pag($total_paginas, $cantidad_por_pagina, $modelo_filtro, $codigo_filtro, $fecha_inicio, $fecha_fin).'">'.$total_paginas.'</a>';
            }

            if ($pagina_actual > 1) {
                echo '<a href="'.enlace_pag($pagina_actual-1, $cantidad_por_pagina, $modelo_filtro, $codigo_filtro, $fecha_inicio, $fecha_fin).'">Anterior</a>';
            }
            if ($pagina_actual < $total_paginas) {
                echo '<a href="'.enlace_pag($pagina_actual+1, $cantidad_por_pagina, $modelo_filtro, $codigo_filtro, $fecha_inicio, $fecha_fin).'">Siguiente</a>';
            }
            ?>
        </div>
    <?php else: ?>
        <p>No hay registros EN TERRENO para este filtro.</p>
    <?php endif; ?>
</div>

<script>
function toggleAccountInfo(){const i=document.getElementById('accountInfo');i.style.display=(i.style.display==='none'||i.style.display==='')?'block':'none';}
document.addEventListener("DOMContentLoaded",function(){
    const input=document.getElementById("codigo"), box=document.getElementById("sugerencias");
    input.addEventListener("input",function(){
        const q=input.value; const params=new URLSearchParams(window.location.search);
        if(q.length<2){box.innerHTML="";box.style.display="none";return;}
        const fi=document.getElementById('fecha_inicio')?.value||'';
        const ff=document.getElementById('fecha_fin')?.value||'';
        fetch(`bodegat_interior2.php?query=${encodeURIComponent(q)}&modelo=${encodeURIComponent(params.get('modelo')||'')}&fecha_inicio=${encodeURIComponent(fi)}&fecha_fin=${encodeURIComponent(ff)}`)
            .then(r=>r.json()).then(d=>{
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