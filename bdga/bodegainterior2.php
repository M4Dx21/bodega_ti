<?php
session_start();
include 'db.php';
include 'funciones.php';

date_default_timezone_set('America/Santiago');

// ---------------------------
// Parámetros de filtros y paginación
// ---------------------------
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual = max(1, $pagina_actual);
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$nombre_usuario_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';
$modelo_filtro = isset($_GET['modelo']) ? $conn->real_escape_string(urldecode(trim($_GET['modelo']))) : '';

// Para reconstruir querystring al volver de acciones (eliminar, etc.)
$qsFiltros = [];
if ($modelo_filtro !== '')           $qsFiltros['modelo']  = $modelo_filtro;
if ($nombre_usuario_filtro !== '')   $qsFiltros['codigo']  = $nombre_usuario_filtro;
$qsFiltros['cantidad'] = $cantidad_por_pagina;
$qsFiltros['pagina']   = $pagina_actual;
$qsFiltros = http_build_query($qsFiltros);

// ---------------------------
// REGLA: Este archivo es del flujo BODEGA, por lo tanto EXCLUYE EN TERRENO
// ---------------------------
$ESTADO_EXCLUIR = 'EN TERRENO';

// ---------------------------
// Base de consulta (siempre stock >= 1 y estado <> EN TERRENO)
// ---------------------------
$sql_base = "FROM componentes WHERE stock >= 1 AND estado <> '$ESTADO_EXCLUIR'";

// Filtro por modelo exacto (insumo)
if ($modelo_filtro !== '') {
    $sql_base .= " AND insumo = '$modelo_filtro'";
}

// Filtro por código o insumo (búsqueda)
if ($nombre_usuario_filtro !== '') {
    $sql_base .= " AND (codigo LIKE '%$nombre_usuario_filtro%' OR insumo LIKE '%$nombre_usuario_filtro%')";
}

// ---------------------------
// Alertas de stock (como lo tenías)
// ---------------------------
$insumosBajos = obtenerInsumosBajoStock($conn);
if ($insumosBajos !== false && !empty($insumosBajos)) {
    $_SESSION['alertas_stock'] = $insumosBajos;
}

// ---------------------------
/* Total de páginas (agrupando por insumo/marca/estado/ubicación dentro del filtro)
   Si no necesitas agrupar, puedes contar directo, pero dejo tu enfoque */
$sql_total = "SELECT COUNT(*) as total FROM (
    SELECT COUNT(*) 
    $sql_base
    GROUP BY insumo, marca, estado, ubicacion
) as agrupados";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = (int)mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = max(1, ceil($total_filas / $cantidad_por_pagina));

// ---------------------------
// Listado principal con filtros y paginación
// ---------------------------
$sql_final = "SELECT * 
    $sql_base
    ORDER BY fecha_ingreso DESC 
    LIMIT $cantidad_por_pagina OFFSET $offset";

$resultado = mysqli_query($conn, $sql_final);
$personas_dentro = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

// ---------------------------
// Autocompletado: también EXCLUYE EN TERRENO + stock >= 1 (+ respeta modelo si venía)
// ---------------------------
if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);

    $where_auto = "estado <> '$ESTADO_EXCLUIR' AND stock >= 1 AND (codigo LIKE '%$query%' OR insumo LIKE '%$query%')";
    if ($modelo_filtro !== '') {
        $where_auto .= " AND insumo = '$modelo_filtro'";
    }

    $sql = "SELECT codigo, insumo FROM componentes 
            WHERE $where_auto
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

// ---------------------------
// Ver comprobante
// ---------------------------
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
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            echo "<script>alert('El archivo del comprobante no se encontró en el servidor');history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('El insumo no tiene comprobante asociado');history.back();</script>";
        exit;
    }
}

// ---------------------------
// Eliminar
// ---------------------------
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $stmt = $conn->prepare("DELETE FROM componentes WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    header('Location: bodegainterior2.php?' . $qsFiltros);
    exit;
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

            .btn-accion {
                font-size: 10px;
                padding: 3px 6px;
                margin-right: 3px;
                border: none;
                border-radius: 3px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                display: inline-block;
                transition: background-color 0.2s;
            }

            .btn-accion:hover {
                background-color: #0056b3;
            }

            .btn-eliminar {
                background-color: #dc3545;
            }

            .btn-eliminar:hover {
                background-color: #a71d2a;
            }

            .btn-ver {
                background-color: #28a745;
            }

            .btn-ver:hover {
                background-color: #1e7e34;
            }

            .btn-acciones-group {
                display: flex;
                flex-direction: row;
                gap: 3px;
                justify-content: center;
                align-items: center;
                flex-wrap: wrap;
            }
            .botones-filtros {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .btn-alertas {
                position: relative;
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                padding: 6px 12px;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 14px;
            }
            
            .btn-alertas:hover {
                background-color: #f5c6cb;
            }
            
            .alert-badge {
                background-color: #dc3545;
                color: white;
                border-radius: 50%;
                width: 18px;
                height: 18px;
                font-size: 11px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .alert-panel {
                display: none;
                position: absolute;
                top: 35px;
                right: 0;
                width: 280px;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                border-radius: 5px;
                z-index: 1000;
                padding: 12px;
            }
    </style>
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Control de Bodega</div>
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
            <div class="botonera">
                <button onclick="window.location.href='bodegat.php'">🖥️ Insumos General</button>
                <button onclick="window.location.href='agregarcomp.php'">🗄️ Agregar Insumos</button>
                <button onclick="window.location.href='exportar_excel.php'">📤 Exportar Excel</button>
                <button onclick="window.location.href='historiale.php'">📑 Historial Entrada</button>
                <button onclick="window.location.href='historials.php'">📑 Historial Salida</button>
                <button class="btn-alerta" onclick="window.location.href='alertas.php'">🚨 Alertas de Stock</button>
            </div>
        <div class="filters">
            <form method="GET" action="">
                <label for="codigo">Insumo:</label>
                <div class="input-sugerencias-wrapper">
                    <input type="text" id="codigo" name="codigo" autocomplete="off"
                        placeholder="Escribe el insumo para buscar..."
                        value="<?= htmlspecialchars($nombre_usuario_filtro) ?>">
                    <div id="sugerencias" class="sugerencias-box"></div>
                </div>

                <?php if ($modelo_filtro !== ''): ?>
                    <input type="hidden" name="modelo" value="<?= htmlspecialchars($modelo_filtro) ?>">
                <?php endif; ?>

                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn"
                        onclick="window.location='bodegainterior2.php<?= $modelo_filtro !== '' ? '?modelo=' . urlencode($modelo_filtro) : '' ?>'">
                        Limpiar Filtros
                    </button>
                </div>
            </form>
             </div>
            <?php if (!empty($modelo_filtro)): ?>
                <h2><?= htmlspecialchars($modelo_filtro) ?></h2>
            <?php endif; ?>
        <?php if (!empty($personas_dentro)): ?>
            <table>
                <tr>
                    <th>Código</th>
                    <th>Modelo</th>
                    <th>Stock</th>
                    <th>Categoria</th>
                    <th>Marca</th>
                    <th>Estado</th>
                    <th>Ubicacion</th>
                    <th>Especificaciones</th>
                    <th>Ingreso</th>
                    <th>Garantia</th>
                    <th>Precio</th>
                    <th>Provedor</th>
                    <th>NRO Orden</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($personas_dentro as $componente): ?>
                    <tr>
                        <td><?= htmlspecialchars($componente['codigo']) ?></td>
                        <td><?= htmlspecialchars($componente['insumo']) ?></td>
                        <td><?= htmlspecialchars($componente['stock']) ?></td>
                        <td><?= htmlspecialchars($componente['categoria']) ?></td>
                        <td><?= htmlspecialchars($componente['marca']) ?></td>
                        <td><?= htmlspecialchars($componente['estado']) ?></td>
                        <td><?= htmlspecialchars($componente['ubicacion']) ?></td>
                        <td><?= htmlspecialchars($componente['caracteristicas']) ?></td>
                        <td><?= date('d-m-y', strtotime($componente['fecha_ingreso'])) ?></td>
                        <td><?= date('d-m-y', strtotime($componente['garantia'])) ?></td>
                        <td><?= htmlspecialchars(number_format($componente['precio'], 0, ',', '.')) ?> CLP</td>
                        <td><?= htmlspecialchars($componente['provedor']) ?></td>
                        <td><?= htmlspecialchars($componente['nro_orden']) ?></td>
                        <td><?= htmlspecialchars($componente['observaciones']) ?></td>
                        <td class="btn-acciones-group">
                            <a  href="agregarcomp.php?editar=<?= $componente['id'] ?>&<?= $qsFiltros ?>"
                                class="btn-accion">Editar</a>
                            <a  href="?eliminar=<?= $componente['id'] ?>&<?= $qsFiltros ?>"
                                class="btn-accion btn-eliminar"
                                onclick="return confirm('¿Estás seguro de eliminar este componente?');">
                                Eliminar
                            </a>
                            <a href="?comprobante=<?= $componente['id'] ?>" class="btn-accion btn-ver" target="_blank">Comprobante</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
                <form method="GET" style="margin-bottom: 10px;">
                    <label for="cantidad">Mostrar:</label>
                    <select name="cantidad" onchange="this.form.submit()">
                        <?php foreach ([10, 20, 30, 40, 50] as $cantidad): ?>
                            <option value="<?= $cantidad ?>" <?= $cantidad_por_pagina == $cantidad ? 'selected' : '' ?>>
                                <?= $cantidad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($modelo_filtro !== ''): ?>
                        <input type="hidden" name="modelo" value="<?= htmlspecialchars($modelo_filtro) ?>">
                    <?php endif; ?>
                    <?php if (!empty($nombre_usuario_filtro)): ?>
                        <input type="hidden" name="codigo" value="<?= htmlspecialchars($nombre_usuario_filtro) ?>">
                    <?php endif; ?>
                    
                    <input type="hidden" name="pagina" value="1">
                </form>
            <div class="pagination-container">
                <?php
                function enlace($pagina, $cantidad, $modelo, $codigo = '') {
                    $qs = [
                        'pagina' => $pagina,
                        'cantidad' => $cantidad
                    ];
                    if (!empty($modelo)) $qs['modelo'] = $modelo;
                    if (!empty($codigo)) $qs['codigo'] = $codigo;
                    return '?' . http_build_query($qs);
                }

                $rango_visible = 5;
                $inicio = max(1, $pagina_actual - floor($rango_visible / 2));
                $fin = min($total_paginas, $inicio + $rango_visible - 1);

                if ($inicio > 1) {
                    echo '<a href="'.enlace(1, $cantidad_por_pagina, $modelo_filtro, $nombre_usuario_filtro).'">1</a>';
                    if ($inicio > 2) echo '<span>...</span>';
                }

                for ($i = $inicio; $i <= $fin; $i++) {
                    $active = $pagina_actual == $i ? 'active' : '';
                    echo '<a class="'.$active.'" href="'.enlace($i, $cantidad_por_pagina, $modelo_filtro, $nombre_usuario_filtro).'">'.$i.'</a>';
                }

                if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) echo '<span>...</span>';
                    echo '<a href="'.enlace($total_paginas, $cantidad_por_pagina, $modelo_filtro, $nombre_usuario_filtro).'">'.$total_paginas.'</a>';
                }

                if ($pagina_actual > 1) {
                    echo '<a href="'.enlace($pagina_actual - 1, $cantidad_por_pagina, $modelo_filtro, $nombre_usuario_filtro).'">Anterior</a>';
                }
                if ($pagina_actual < $total_paginas) {
                    echo '<a href="'.enlace($pagina_actual + 1, $cantidad_por_pagina, $modelo_filtro, $nombre_usuario_filtro).'">Siguiente</a>';
                }
                ?>
            </div>
        <?php else: ?>
            <p>No se encontraron resultados para tu búsqueda.</p>
        <?php endif; ?>
    </div>

    <script>
        function toggleAlertPanel() {
            const panel = document.getElementById('alertPanel');
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        }

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