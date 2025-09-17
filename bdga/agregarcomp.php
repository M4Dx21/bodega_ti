<?php
include 'db.php';
session_start();
date_default_timezone_set('America/Santiago');

$nombre_usuario_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';
$sql_base = "FROM componentes WHERE 1";
$editando = false;
$componente_edit = null;

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

function agregarValorEnumSiNoExiste($conn, $tabla, $columna, $nuevo_valor) {
    $query = "SHOW COLUMNS FROM `$tabla` LIKE '$columna'";
    $resultado = mysqli_query($conn, $query);
    $fila = mysqli_fetch_assoc($resultado);
    if (!$fila) return false;

    if (preg_match("/^enum\('(.*)'\)$/", $fila['Type'], $matches)) {
        $valores = explode("','", $matches[1]);
        if (in_array($nuevo_valor, $valores)) {
            return true;
        }

        $valores[] = $nuevo_valor;

        $valores_escapados = array_map(function($v) {
            return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
        }, $valores);
        $enum_nuevo = "ENUM(" . implode(",", $valores_escapados) . ")";

        $sql_alter = "ALTER TABLE `$tabla` MODIFY `$columna` $enum_nuevo NOT NULL";
        if (mysqli_query($conn, $sql_alter)) {
            return true;
        } else {
            return false;
        }
    }
    return false;
}

$enum_formatos = obtenerValoresEnum($conn, 'componentes', 'estado');
$enum_ubicaciones = obtenerValoresEnum($conn, 'componentes', 'ubicacion');

if (isset($_POST['agregar'])) {
    $archivo_nombre = null;
    $mensaje_archivo = '';

    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $permitidos = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'jpg', 'png'];
        $nombre_original = $_FILES['comprobante']['name'];
        $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));

        if (in_array($ext, $permitidos)) {
            $destino_carpeta = __DIR__ . '/comprobantes/';
            if (!is_dir($destino_carpeta)) mkdir($destino_carpeta, 0775, true);

            $archivo_nombre = uniqid('comprobante_') . '.' . $ext;
            if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $destino_carpeta . $archivo_nombre)) {
                $mensaje_archivo = 'Error al mover el archivo.';
                $archivo_nombre = null;
            }
        } else {
            $mensaje_archivo = 'Tipo de archivo no permitido.';
        }
    }

    $nombre         = $_POST['insumo'];
    $codigo         = $_POST['codigo'];
    $stock          = (int)$_POST["stock"];
    $especialidad   = $_POST['categoria'];
    $formato        = $_POST['marca'];
    $estado         = $_POST['estado'];
    $ubicacion      = $_POST['ubicacion_select'];
    $nro_orden      = $_POST['nro_orden'];
    $provedor      = $_POST['provedor'];

    if ($ubicacion === 'otro' && !empty($_POST['otra_ubicacion'])) {
        $ubicacion = trim($_POST['otra_ubicacion']);
        agregarValorEnumSiNoExiste($conn, 'componentes', 'ubicacion', $ubicacion);
    }

    $caracteristicas  = $_POST['caracteristicas'];
    $observaciones    = $_POST['observaciones'];
    $precio_unitario  = (int)$_POST["precio"];
    $precio_total     = $precio_unitario * $stock;
    $garantia         = $_POST['garantia'];
    $fecha_ingreso    = date('Y-m-d H:i:s');

    $consulta_existente = "SELECT id, stock, precio FROM componentes WHERE codigo = ?";
    $stmt = $conn->prepare($consulta_existente);
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $res = $stmt->get_result();
    $existe = $res->num_rows > 0;
    $fila_existente = $res->fetch_assoc();
    $stmt->close();

    if ($existe) {
        $nuevo_stock = $fila_existente['stock'] + $stock;
        $nuevo_precio = $fila_existente['precio'] + $precio_total;

        $sql = "UPDATE componentes SET 
                    insumo=?, stock=?, categoria=?, marca=?, estado=?, ubicacion=?, 
                    observaciones=?, caracteristicas=?, precio=?, garantia=?, nro_orden=?, provedor=?, fecha_ingreso=?"
                    . ($archivo_nombre ? ", comprobante=?" : "") . 
                " WHERE codigo=?";

        if ($archivo_nombre) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sissssssssssss",
                $nombre, $nuevo_stock, $especialidad, $formato, $estado, $ubicacion,
                $observaciones, $caracteristicas, $nuevo_precio, $garantia,
                $nro_orden, $provedor, $fecha_ingreso,
                $archivo_nombre, $codigo
            );
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sisssssssssss",
                $nombre, $nuevo_stock, $especialidad, $formato, $estado, $ubicacion,
                $observaciones, $caracteristicas, $nuevo_precio, $garantia,
                $nro_orden, $provedor, $fecha_ingreso,
                $codigo
            );
        }
        $stmt->execute();
        $stmt->close();

        $mensaje = 'Insumo actualizado correctamente.';
    } else {
        $sql = "INSERT INTO componentes 
                (codigo, insumo, stock, categoria, marca, estado, ubicacion, observaciones, 
                fecha_ingreso, caracteristicas, precio, garantia, comprobante, nro_orden, provedor) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssissssssssssss",
            $codigo, $nombre, $stock, $especialidad, $formato, $estado, $ubicacion,
            $observaciones, $fecha_ingreso, $caracteristicas, $precio_total, $garantia,
            $archivo_nombre, $nro_orden, $provedor
        );
        $stmt->execute();
        $stmt->close();

        $stmt_hist = $conn->prepare("INSERT INTO historial (num_serie, cantidad, fecha) VALUES (?, ?, ?)");
        $stmt_hist->bind_param("sis", $codigo, $stock, $fecha_ingreso);
        $stmt_hist->execute();
        $stmt_hist->close();

        $mensaje = 'Insumo agregado correctamente.';
    }

    $mensaje_final = $mensaje_archivo ? $mensaje_archivo : $mensaje;
    header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje_final));
    exit();
}

if (isset($_GET['editar'])) {
    $editando = true;
    $id = (int)$_GET['editar'];

    $stmt = $conn->prepare("SELECT * FROM componentes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $componente_edit = $resultado->fetch_assoc();
    $stmt->close();
}

if (isset($_POST['guardar_cambios'])) {
    $id            = $_POST["id"];
    $nombre        = $_POST["insumo"];
    $codigo        = $_POST["codigo"];
    $stock         = (int)$_POST["stock"];
    $especialidad  = $_POST["categoria"];
    $formato       = $_POST["marca"];
    $estado        = $_POST["estado"];
    $ubicacion     = $_POST['ubicacion_select'];
    $nro_orden     = $_POST["nro_orden"];
    $provedor     = $_POST["provedor"];

    if ($ubicacion === 'otro' && !empty($_POST['otra_ubicacion'])) {
        $ubicacion = trim($_POST['otra_ubicacion']);
        agregarValorEnumSiNoExiste($conn, 'componentes', 'ubicacion', $ubicacion);
    }

    $observaciones   = $_POST["observaciones"];
    $precio_unitario = (int)$_POST["precio"];
    $precio_total    = $precio_unitario * $stock;
    $garantia        = $_POST["garantia"];
    $fecha_ingreso   = date('Y-m-d H:i:s');

    $archivo_nombre = null;
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $permitidos = ['pdf','doc','docx','xlsx','xls','jpg','png'];
        $nombre_original = $_FILES['comprobante']['name'];
        $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        if (in_array($ext, $permitidos)) {
            $destino_carpeta = __DIR__ . '/comprobantes/';
            if (!is_dir($destino_carpeta)) mkdir($destino_carpeta, 0775, true);
            $archivo_nombre = uniqid('comprobante_') . '.' . $ext;
            move_uploaded_file($_FILES['comprobante']['tmp_name'], $destino_carpeta . $archivo_nombre);
        }
    }

    if ($archivo_nombre) {
        $sql = "UPDATE componentes SET 
                    insumo=?, stock=?, categoria=?, marca=?, estado=?, ubicacion=?, 
                    observaciones=?, fecha_ingreso=?, garantia=?, comprobante=?, precio=?, nro_orden=?, provedor=? 
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sissssssssissi",
            $nombre, $stock, $especialidad, $formato, $estado, $ubicacion,
            $observaciones, $fecha_ingreso, $garantia, $archivo_nombre, $precio_total,
            $nro_orden, $provedor, $id
        );
    } else {
        $sql = "UPDATE componentes SET 
                    insumo=?, stock=?, categoria=?, marca=?, estado=?, ubicacion=?, 
                    observaciones=?, fecha_ingreso=?, garantia=?, precio=?, nro_orden=?, provedor=? 
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sisssssssiisi",
            $nombre, $stock, $especialidad, $formato, $estado, $ubicacion,
            $observaciones, $fecha_ingreso, $garantia, $precio_total,
            $nro_orden, $provedor, $id
        );
    }

    $stmt->execute();
    $stmt->close();

    header("Location: agregarcomp.php?mensaje=editado");
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
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="container">

        <div class="botonera">
            <button onclick="window.location.href='bodega.php'">📦 Control de bodega</button>
            <button onclick="window.location.href='historiale.php'">📑 Historial Entrada</button>
            <button onclick="window.location.href='historials.php'">📑 Historial Salida</button>
            <button class="btn-alerta" onclick="window.location.href='alertas.php'">🚨 Alertas de Stock</button>
        </div>

        <div id="mensaje-container">
            <?php if (isset($mensaje)) echo $mensaje; ?>
        </div>
        <h2><?= $editando ? 'Editar Insumos' : 'Agregar Insumos' ?></h2>
        <button type="button" class="btn-pequeno" onclick="toggleExcelForm()">📂 Importar desde Excel</button>
        <div id="excelFormContainer" style="display: none; margin-top: 10px;">
            <form action="importar_excel.php" method="post" enctype="multipart/form-data">
                <label for="archivo_excel">Subir Excel:</label>
                <input type="file" name="archivo_excel" accept=".xlsx, .xls">
                <button type="submit">Importar</button>
                <button type="button" onclick="toggleExcelForm()">Cancelar</button>
            </form>
        </div>
            <form action="" method="post" enctype="multipart/form-data">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $componente_edit['id'] ?>">
                <?php endif; ?>
                
                <input type="text" id="codigo" name="codigo" placeholder="Número de serie"
                    value="<?= $editando ? htmlspecialchars($componente_edit['codigo']) : '' ?>" autofocus>

                <input type="text" name="categoria" placeholder="Categoria" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['categoria']) : '' ?>" required>

                <input type="text" name="marca" placeholder="Marca" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['marca']) : '' ?>" required>

                <input type="text" name="insumo" placeholder="Modelo" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['insumo']) : '' ?>" required>

                <input type="number" name="stock" placeholder="Cantidad" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['stock']) : '' ?>" required>

                <input type="text" name="caracteristicas" placeholder="Caracteristicas del equipo" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['caracteristicas']) : '' ?>">
                
                <input type="text" id="precio" name="precio" placeholder="Precio de producto (Unitario)"
                    value="<?= $editando ? htmlspecialchars($componente_edit['precio']) : '' ?>" required>

                <select name="ubicacion_select" id="ubicacion_select" required>
                    <option value="">Seleccione ubicación</option>
                    <?php foreach ($enum_ubicaciones as $valor): ?>
                        <option value="<?= $valor ?>" <?= $editando && $componente_edit['ubicacion'] == $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($valor)) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="otro">Otro</option>
                </select>

                <div id="otra_ubicacion_div" style="display: none; margin-top: 10px;">
                    <input type="text" name="otra_ubicacion" id="otra_ubicacion" placeholder="Especifique nueva ubicación">
                </div>

                <select name="estado" required>
                    <option value="">Seleccione Estado</option>
                    <?php foreach ($enum_formatos as $valor): ?>
                        <option value="<?= $valor ?>" <?= $editando && $componente_edit['estado'] == $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($valor)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                    <label for="garantia">Fecha de término de garantía:</label>
                    <input type="date" id="garantia" name="garantia"
                    value="<?= $editando ? htmlspecialchars($componente_edit['garantia']) : '' ?>">
                <div class="file-upload-wrapper">
                    <label for="comprobante" class="file-upload-label">
                        <i class="fas fa-file-upload"></i> Adjuntar Comprobante (PDF, Word, etc.)
                    </label>
                    <input type="file" name="comprobante" id="comprobante" class="file-upload-input" accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.png">
                    <span id="file-name" class="file-name-placeholder">Ningún archivo seleccionado</span>
                </div>
                
                <input type="text" id="nro_orden" name="nro_orden" placeholder="Numero de orden"
                    value="<?= $editando ? htmlspecialchars($componente_edit['nro_orden']) : '' ?>">
                
                <input type="text" id="provedor" name="provedor" placeholder="Origen Insumo"
                    value="<?= $editando ? htmlspecialchars($componente_edit['provedor']) : '' ?>">

                <input type="text" id="observaciones" name="observaciones" placeholder="Observaciones"
                    value="<?= $editando ? htmlspecialchars($componente_edit['observaciones']) : '' ?>">

                <?php if ($editando): ?>
                    <button type="submit" name="guardar_cambios">Guardar Cambios</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>">Cancelar</a>
                <?php else: ?>
                    <button type="submit" class="btn-pequeno" name="agregar">Agregar Insumos</button>
                    <button type="reset" class="btn-pequeno">Limpiar</button>
                <?php endif; ?>
            </form>
                <?php if (isset($_GET['importado'])): ?>
            <div id="success-msg">¡Archivo importado correctamente!</div>
                <?php endif; ?>
                <?php if (isset($_GET['mensaje'])): ?>
                    <div id="alerta-exito"><?= htmlspecialchars($_GET['mensaje']) ?></div>
                <?php endif; ?>

    </div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('input[type="text"]').forEach(function (input) {
        input.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
        });
    });
    });
    document.getElementById('codigo').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        buscarComponente(this.value);
    }
    });

    const inputCodigo = document.getElementById('codigo');
    inputCodigo.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            console.log("Código ingresado manualmente:", inputCodigo.value);
        }
    });

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

    function setValue(selector, value) {
        const input = document.querySelector(selector);
        if (input) {
            input.value = value ?? '';
        }
    }

    function buscarComponente(codigo) {
        if (codigo.trim() === "") return;

        fetch("buscar_componente.php?codigo=" + encodeURIComponent(codigo))
            .then(response => response.json())
            .then(data => {
                if (data.encontrado) {
                    setValue('input[name="codigo"]', data.codigo);
                    setValue('input[name="insumo"]', data.insumo);
                    setValue('input[name="stock"]', data.stock);
                    setValue('input[name="caracteristicas"]', data.caracteristicas);
                    setValue('input[name="precio"]', data.precio);
                    setValue('input[name="marca"]', data.marca);
                    setValue('input[name="categoria"]', data.categoria);
                    setValue('select[name="ubicacion"]', data.ubicacion);
                    setValue('select[name="estado"]', data.estado);
                    setValue('input[name="provedor"]', data.provedor);
                    setValue('input[name="nro_orden"]', data.nro_orden);
                    setValue('input[name="observaciones"]', data.observaciones);

                    alert("Insumo detectado: " + data.insumo);
                } else {
                    alert("El insumo no se encuentra en la base de datos.");
                }
            })
            .catch(error => {
                alert("Error al buscar el insumo: " + error);
            });
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
    
    document.getElementById('comprobante').addEventListener('change', function() {
        const fileName = this.files.length > 0 ? this.files[0].name : 'Ningún archivo seleccionado';
        document.getElementById('file-name').textContent = fileName;
    });

    document.getElementById('ubicacion_select').addEventListener('change', function() {
        const otraDiv = document.getElementById('otra_ubicacion_div');
        if (this.value === 'otro') {
            otraDiv.style.display = 'block';
            document.getElementById('otra_ubicacion').required = true;
        } else {
            otraDiv.style.display = 'none';
            document.getElementById('otra_ubicacion').required = false;
        }
    });

</script>
<style>
    #alerta-exito {
        position: fixed;
        bottom: 75px;
        left: 50%;
        transform: translateX(-50%);
        background-color: rgb(28, 192, 66);
        color: white;
        max-width: 300px;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: fadeOut 2s forwards;
        text-align: center;
    }

    @keyframes fadeOut {
        0% { opacity: 1; }
        90% { opacity: 1; }
        100% { opacity: 0; display: none; }
    }

    .btn-pequeno {
        padding: 12px 20px;
        font-size: 14px;
        border-radius: 10px;
        background-color:#0056b3;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
        width: 20%;
    }

    .btn-pequeno:hover {
        background-color:rgb(4, 65, 129);
    }

</style>
</body>
</html>
<?php
$conn->close();
?>