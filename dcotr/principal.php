<?php
include 'db.php';
session_start();

$nombre_usuario_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';
$sql_base = "FROM componentes WHERE 1";

if (!empty($nombre_usuario_filtro)) {
    $sql_base .= " AND (codigo LIKE '%$nombre_usuario_filtro%' OR insumo LIKE '%$nombre_usuario_filtro%')";
}

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

$editando = false;
$componente_edit = null;
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$consulta = "SELECT * $sql_base ORDER BY fecha_ingreso DESC LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $consulta);
$personas_dentro = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$total_resultado = mysqli_query($conn, "SELECT COUNT(*) AS total $sql_base");
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

function obtenerValoresEnum($conn, $tabla, $columna) {
    $query = "SHOW COLUMNS FROM $tabla LIKE '$columna'";
    $resultado = mysqli_query($conn, $query);
    $fila = mysqli_fetch_assoc($resultado);
    
    if (preg_match("/^enum\(\'(.*)\'\)$/", $fila['Type'], $matches)) {
        $valores = explode("','", $matches[1]);
        return $valores;
    }
    return [];
}
$enum_especialidades = obtenerValoresEnum($conn, 'componentes', 'especialidad');
$enum_formatos = obtenerValoresEnum($conn, 'componentes', 'formato');
$enum_ubicaciones = obtenerValoresEnum($conn, 'componentes', 'ubicacion');

if (isset($_POST['agregar'])) {
    $nombre = $_POST["insumo"];
    $codigo = $_POST["codigo"];
    $stock = $_POST["stock"];
    $especialidad = $_POST["especialidad"];
    $formato = $_POST["formato"];
    $ubicacion = $_POST["ubicacion"];
    $fecha_ingreso = date('Y-m-d H:i:s');

    $consulta_existente = "SELECT * FROM componentes WHERE codigo = '$codigo'";
    $resultado_existente = mysqli_query($conn, $consulta_existente);

    if (mysqli_num_rows($resultado_existente) > 0) {
        $update = "UPDATE componentes SET 
                    insumo = '$nombre', 
                    stock = stock + $stock, 
                    especialidad = '$especialidad', 
                    formato = '$formato', 
                    ubicacion = '$ubicacion', 
                    fecha_ingreso = '$fecha_ingreso'
                   WHERE codigo = '$codigo'";
        mysqli_query($conn, $update);
        $mensaje = "Insumo actualizado correctamente.";
    } else {
        $insert = "INSERT INTO componentes (codigo, insumo, stock, especialidad, formato, ubicacion, fecha_ingreso) 
                   VALUES ('$codigo', '$nombre', '$stock', '$especialidad', '$formato', '$ubicacion', '$fecha_ingreso')";
        mysqli_query($conn, $insert);
        $mensaje = "Insumo agregado correctamente.";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
    exit();
}

$sql = "SELECT * FROM componentes";
$result = $conn->query($sql);
$solicitudes_result = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_result[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administracion de insumos del Hospital Clinico Félix Bulnes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Agregar componentes</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="filters">
            <form method="GET" action="">
                <label for="codigo">Insumo:</label>
                <div class="input-sugerencias-wrapper">
                    <input type="text" id="filtro_codigo" name="filtro_codigo" autocomplete="off"
                        placeholder="Escribe el insumo para buscar..."
                        value="<?php echo htmlspecialchars($nombre_usuario_filtro); ?>">
                    <div id="sugerencias" class="sugerencias-box"></div>
                </div>
                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn" onclick="window.location='agregarcomp.php'">Limpiar Filtros</button>
                </div>
            </form>
        </div>
        <div id="mensaje-container">
            <?php if (isset($mensaje)) echo $mensaje; ?>
        </div>
        <h2><?= $editando ? 'Editar Insumos' : 'Buscar Componentes' ?></h2>
     <!--   <button type="button" onclick="toggleExcelForm()">📂 Importar desde Excel</button>
        <div id="excelFormContainer" style="display: none; margin-top: 10px;">
            <form action="importar_excel.php" method="post" enctype="multipart/form-data">
                <label for="archivo_excel">Subir Excel:</label>
                <input type="file" name="archivo_excel" accept=".xlsx, .xls">
                <button type="submit">Importar</button>
                <button type="button" onclick="toggleExcelForm()">Cancelar</button>  
            </form>
        </div>-->
     <!--   <button type="button" onclick="abrirEscaner()">📷 Escanear Código</button>
        <div id="escaneo-container" style="display:none;"> 
            <div id="lector" style="width: 100%; max-width: 400px; margin: 10px auto;"></div>
            <button type="button" onclick="cerrarEscaner()">❌ Finalizar Escaneo</button> 
        </div> -->

        <form action="" method="post">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= $componente_edit['id'] ?>">
            <?php endif; ?>
            <input type="text" id="codigo" name="codigo" placeholder="Código" required
                value="<?= $editando ? $componente_edit['codigo'] : '' ?>" autofocus>
            <!-- Insumo -->
            <input type="text" name="insumo_mostrado" placeholder="Insumo"
                value="<?= $editando ? $componente_edit['insumo'] : '' ?>" readonly>
            <input type="hidden" name="insumo" value="<?= $editando ? $componente_edit['insumo'] : '' ?>">

            <!-- Especialidad -->
            <input type="text" name="especialidad_mostrada" placeholder="Especialidad"
                value="<?= $editando ? ucfirst($componente_edit['especialidad']) : '' ?>" readonly>
            <input type="hidden" name="especialidad" value="<?= $editando ? $componente_edit['especialidad'] : '' ?>">

            <!-- Formato -->
            <input type="text" name="formato_mostrado" placeholder="Formato"
                value="<?= $editando ? ucfirst($componente_edit['formato']) : '' ?>" readonly>
            <input type="hidden" name="formato" value="<?= $editando ? $componente_edit['formato'] : '' ?>">

            <!-- Ubicación -->
            <input type="text" name="ubicacion_mostrada" placeholder="Ubicación"
                value="<?= $editando ? ucfirst($componente_edit['ubicacion']) : '' ?>" readonly>
            <input type="hidden" name="ubicacion" value="<?= $editando ? $componente_edit['ubicacion'] : '' ?>">

            <!-- Botones -->
            <?php if ($editando): ?>
                <button type="submit" name="guardar_cambios">Guardar Cambios</button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>">Cancelar</a>
            <?php else: ?>
                <button type="submit" name="agregar">Agregar</button>
            <?php endif; ?>
        </form>
        <?php if (!empty($personas_dentro)): ?>
            <h2>Lista de Insumos</h2>
            <table>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Stock</th>
                <th>Especialidad</th>
                <th>Formato</th>
                <th>Ubicacion</th>
                <th>Fecha</th>
                <th>Acciones</th>
         <!--   <th>QR</th> -->   
            </tr>
            <?php foreach ($personas_dentro as $componente): ?>
                <tr>
                    <td><?= htmlspecialchars($componente['codigo']) ?></td>
                    <td><?= htmlspecialchars($componente['insumo']) ?></td>
                    <td><?= htmlspecialchars($componente['especialidad']) ?></td>
                    <td><?= htmlspecialchars($componente['formato']) ?></td>
                    <td><?= htmlspecialchars($componente['ubicacion']) ?></td>
                    <td><?= date('d-m-y H:i', strtotime($componente['fecha_ingreso'])) ?></td>
                    <td>
                        <a href="?editar=<?= $componente['id'] ?>" class="btn">Editar</a>
                        <a href="?eliminar=<?= $componente['id'] ?>" class="btn" onclick="return confirm('¿Estás seguro de eliminar este componente?');">Eliminar</a>
                    </td>
        <!--        <td>
                    <button onclick="generarCodigoBarras('<?= htmlspecialchars($componente['codigo']) ?>', 'barcode_<?= $componente['id'] ?>')">Generar Código</button>
                    <svg id="barcode_<?= $componente['id'] ?>" style="margin-top:5px;"></svg>
                    </td>  -->   
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
        <?php endif; ?>
                <?php if (isset($_GET['importado'])): ?>
            <div id="success-msg">¡Archivo importado correctamente!</div>
        <?php endif; ?>
    </div>
<script>
    document.getElementById('codigo').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        buscarComponente(this.value);
    }
    });
    function abrirEscaner() {
        document.getElementById("escaneo-container").style.display = "block";

        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#lector'),
                constraints: {
                    facingMode: "environment"
                },
            },
            decoder: {
                readers: ["code_128_reader", "ean_reader", "ean_8_reader", "upc_reader"] // tipos de código de barras
            }
        }, function(err) {
            if (err) {
                console.error(err);
                alert("Error al iniciar el escáner: " + err);
                return;
            }
            Quagga.start();
        });

        Quagga.onDetected(function(result) {
            if (result && result.codeResult && result.codeResult.code) {
                let codigo = result.codeResult.code;
                const inputCodigo = document.getElementById("codigo");
                inputCodigo.value = codigo;
                inputCodigo.focus();
                cerrarEscaner();
            }
        });
    }

    function cerrarEscaner() {
        Quagga.stop();
        document.getElementById("escaneo-container").style.display = "none";
    }

    const inputCodigo = document.getElementById('codigo');
    inputCodigo.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            console.log("Código ingresado manualmente:", inputCodigo.value);
        }
    });

    function generarCodigoBarras(codigo, elementoId) {
        JsBarcode("#" + elementoId, codigo, {
            format: "code128",
            lineColor: "#0aa",
            width: 2,
            height: 40,
            displayValue: true
        });
    }

    function toggleExcelForm() {
        const form = document.getElementById("excelFormContainer");
        form.style.display = form.style.display === "none" ? "block" : "none";
    }



    function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }

    inputCodigo.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
        e.preventDefault();
        console.log("Código escaneado:", inputCodigo.value);
        }
    });
    
    function buscarComponente(codigo) {
    if (codigo.trim() === "") return;

    fetch("buscar_componente.php?codigo=" + encodeURIComponent(codigo))
        .then(response => response.json())
        .then(data => {
            if (data.encontrado) {
                document.querySelector('input[name="codigo"]').value = data.codigo;
                document.querySelector('input[name="insumo"]').value = data.insumo;
                document.querySelector('input[name="stock"]').value = data.stock;
                document.querySelector('select[name="especialidad"]').value = data.especialidad;
                document.querySelector('select[name="formato"]').value = data.formato;
                document.querySelector('select[name="ubicacion"]').value = data.ubicacion;
                alert("Insumo detectado: " + data.insumo);
            } else {
                alert("El insumo no se encuentra en la base de datos.");
            }
        })
        .catch(error => {
            alert("Error al buscar el insumo: " + error);
        });
    }

    document.getElementById('codigoInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        const codigo = this.value;
        buscarComponente(codigo);
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

                fetch(`agregarcomp.php?query=${encodeURIComponent(query)}`)
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
</script>
</body>
</html>
<?php
$conn->close();
?>