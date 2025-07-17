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
    $ubicacion = $_POST['ubicacion_select'];
    if ($ubicacion === 'otro' && !empty($_POST['otra_ubicacion'])) {
        $ubicacion = trim($_POST['otra_ubicacion']);

        agregarValorEnumSiNoExiste($conn, 'componentes', 'ubicacion', $ubicacion);
    }
    $caracteristicas= $_POST['caracteristicas'];
    $observaciones  = $_POST['observaciones'];
    $precio_unitario = $_POST["precio"];
    $precio_total = $precio_unitario * $stock;
    $garantia       = $_POST['garantia'];
    $fecha_ingreso  = date('Y-m-d H:i:s');

    $consulta_existente = "SELECT 1 FROM componentes WHERE codigo = ?";
    $stmt = mysqli_prepare($conn, $consulta_existente);
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);

    if ($existe) {
        $consulta_datos = "SELECT stock, precio FROM componentes WHERE codigo = ?";
        $stmt_datos = mysqli_prepare($conn, $consulta_datos);
        mysqli_stmt_bind_param($stmt_datos, 's', $codigo);
        mysqli_stmt_execute($stmt_datos);
        $resultado_datos = mysqli_stmt_get_result($stmt_datos);
        $fila_datos = mysqli_fetch_assoc($resultado_datos);
        mysqli_stmt_close($stmt_datos);

        $stock_actual = (int)$fila_datos['stock'];
        $precio_actual = (int)$fila_datos['precio'];

        $precio_unitario_nuevo = (int)$_POST['precio'];
        $stock_nuevo = (int)$_POST['stock'];
        $precio_total_nuevo = $precio_unitario_nuevo * $stock_nuevo;

        $nuevo_stock_total = $stock_actual + $stock_nuevo;
        $nuevo_precio_total = $precio_actual + $precio_total_nuevo;

        $sql = "UPDATE componentes 
                SET insumo = ?, stock = ?, categoria = ?, marca = ?, estado = ?,
                    ubicacion = ?, observaciones = ?, precio = ?, caracteristicas = ?, fecha_ingreso = ?, garantia = ?"
                . ($archivo_nombre ? ", comprobante = ?" : "")
            . " WHERE codigo = ?";

        $stmt = mysqli_prepare($conn, $sql);

        if ($archivo_nombre) {
            mysqli_stmt_bind_param(
                $stmt,
                'sisssssssssss',
                $nombre, $nuevo_stock_total, $especialidad, $formato, $estado,
                $ubicacion, $observaciones, $nuevo_precio_total, $caracteristicas, $fecha_ingreso, $garantia,
                $archivo_nombre,
                $codigo
            );
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'sissssssssss',
                $nombre, $nuevo_stock_total, $especialidad, $formato, $estado,
                $ubicacion, $observaciones, $nuevo_precio_total, $caracteristicas, $fecha_ingreso, $garantia,
                $codigo
            );
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = 'Insumo actualizado correctamente.';
    } else {
        $sql = "INSERT INTO componentes
                (codigo, insumo, stock, categoria, marca, estado, ubicacion, observaciones,
                fecha_ingreso, caracteristicas, precio, garantia, comprobante)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'ssissssssssss',
            $codigo, $nombre, $stock, $especialidad, $formato, $estado, $ubicacion,
            $observaciones, $fecha_ingreso, $caracteristicas, $precio_total, $garantia, $archivo_nombre
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = 'Insumo agregado correctamente.';
    }

    $mensaje_final = $mensaje_archivo ? $mensaje_archivo : $mensaje;
    header('Location: ' . $_SERVER['PHP_SELF'] . '?mensaje=' . urlencode($mensaje_final));
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
    $id             = $_POST["id"];
    $nombre         = $_POST["insumo"];
    $codigo         = $_POST["codigo"];
    $cantidad       = $_POST["stock"];
    $especialidad   = $_POST["categoria"];
    $formato        = $_POST["marca"];
    $estado         = $_POST["estado"];
    $ubicacion = $_POST['ubicacion_select'];
    if ($ubicacion === 'otro' && !empty($_POST['otra_ubicacion'])) {
        $ubicacion = trim($_POST['otra_ubicacion']);

        agregarValorEnumSiNoExiste($conn, 'componentes', 'ubicacion', $ubicacion);
    }
    $observaciones  = $_POST["observaciones"];
    $precio         = $_POST["precio"];
    $garantia       = $_POST["garantia"];
    $fecha_ingreso  = date('Y-m-d H:i:s');

    $archivo_nombre = null;

    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $permitidos = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'jpg', 'png'];
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
        $stmt = $conn->prepare("UPDATE componentes SET
            insumo = ?, stock = ?, categoria = ?, marca = ?, estado = ?, ubicacion = ?,
            observaciones = ?, fecha_ingreso = ?, garantia = ?, comprobante = ?, precio = ?
            WHERE id = ?");
        $stmt->bind_param(
            "sissssssssi",
            $nombre, $cantidad, $especialidad, $formato, $estado, $ubicacion,
            $observaciones, $fecha_ingreso, $garantia, $archivo_nombre, $id, $precio_total
        );
    } else {
        $stmt = $conn->prepare("UPDATE componentes SET
            insumo = ?, stock = ?, categoria = ?, marca = ?, estado = ?, ubicacion = ?,
            observaciones = ?, fecha_ingreso = ?, garantia = ?, precio = ?
            WHERE id = ?");
        $stmt->bind_param(
            "sisssssssis",
            $nombre, $cantidad, $especialidad, $formato, $estado, $ubicacion,
            $observaciones, $fecha_ingreso, $garantia, $id, $precio_total
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
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
                <button type="button" class="volver-btn" onclick="window.history.go(-2);">Volver</button>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="container">
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
                    value="<?= $editando ? htmlspecialchars($componente_edit['categoria']) : '' ?>">

                <input type="text" name="marca" placeholder="Marca" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['marca']) : '' ?>">

                <input type="text" name="insumo" placeholder="Modelo" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['insumo']) : '' ?>">

                <input type="number" name="stock" placeholder="Cantidad" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['stock']) : '' ?>">

                <input type="text" name="caracteristicas" placeholder="Caracteristicas del equipo" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['caracteristicas']) : '' ?>">

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
                    <input type="date" id="garantia" name="garantia" required
                    value="<?= $editando ? htmlspecialchars($componente_edit['garantia']) : '' ?>">
                <div class="file-upload-wrapper">
                    <label for="comprobante" class="file-upload-label">
                        <i class="fas fa-file-upload"></i> Adjuntar Comprobante (PDF, Word, etc.)
                    </label>
                    <input type="file" name="comprobante" id="comprobante" class="file-upload-input" accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.png">
                    <span id="file-name" class="file-name-placeholder">Ningún archivo seleccionado</span>
                </div>

                <input type="text" id="observaciones" name="observaciones" placeholder="Observaciones"
                    value="<?= $editando ? htmlspecialchars($componente_edit['observaciones']) : '' ?>">

                <input type="text" id="precio" name="precio" placeholder="Precio de producto (Unitario)"
                    value="<?= $editando ? htmlspecialchars($componente_edit['precio']) : '' ?>">

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
    
    function buscarComponente(codigo) {
    if (codigo.trim() === "") return;

    fetch("buscar_componente.php?codigo=" + encodeURIComponent(codigo))
        .then(response => response.json())
        .then(data => {
            if (data.encontrado) {
                document.querySelector('input[name="codigo"]').value = data.codigo;
                document.querySelector('input[name="insumo"]').value = data.insumo;
                document.querySelector('input[name="stock"]').value = data.stock ?? '';
                document.querySelector('input[name="marca"]').value = data.marca;
                document.querySelector('input[name="categoria"]').value = data.categoria;
                document.querySelector('select[name="ubicacion"]').value = data.ubicacion;
                document.querySelector('select[name="estado"]').value = data.estado;
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

    .btn-acciones-group {
        display: flex;
        flex-direction: row;
        gap: 3px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
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