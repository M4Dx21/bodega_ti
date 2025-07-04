<?php
include 'db.php';
session_start();
date_default_timezone_set('America/Santiago');

$nombre_usuario_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';
$sql_base = "FROM componentes WHERE 1";

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

$enum_formatos = obtenerValoresEnum($conn, 'componentes', 'estado');
$enum_ubicaciones = obtenerValoresEnum($conn, 'componentes', 'ubicacion');

if (isset($_POST['retirar'])) {

    $codigo   = $_POST['codigo'];
    $cantidad = (int)$_POST['stock'];

    $stmt = $conn->prepare("SELECT stock FROM componentes WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        $mensaje_final = "El componente no está registrado.";
    } elseif ($cantidad > $fila['stock']) {
        $mensaje_final = "La cantidad solicitada excede el stock disponible.";
    } else {
        $stmt = $conn->prepare("UPDATE componentes SET stock = stock - ? WHERE codigo = ?");
        $stmt->bind_param("is", $cantidad, $codigo);
        $stmt->execute();
        $stmt->close();

        $mensaje_final = "Stock descontado correctamente.";
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?mensaje=' . urlencode($mensaje_final));
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
    <title>Retiro de insumos del Hospital Clinico Félix Bulnes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Retirar componentes</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
                <button type="button" class="volver-btn" onclick="window.location.href='eleccion.php'">Volver</button>
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
        <form action="" method="post" enctype="multipart/form-data">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= $componente_edit['id'] ?>">
            <?php endif; ?>

            <input type="text" id="codigo" name="codigo" placeholder="Número de serie"
                value="<?= $editando ? htmlspecialchars($componente_edit['codigo']) : '' ?>">

            <input type="text" name="categoria" placeholder="Categoría" 
                value="<?= $editando ? htmlspecialchars($componente_edit['categoria']) : '' ?>" readonly>

            <input type="text" name="marca" placeholder="Marca" 
                value="<?= $editando ? htmlspecialchars($componente_edit['marca']) : '' ?>" readonly>

            <input type="text" name="insumo" placeholder="Modelo" 
                value="<?= $editando ? htmlspecialchars($componente_edit['insumo']) : '' ?>" readonly>

            <input type="number" name="stock" placeholder="Cantidad" min="1"
                max="<?= $editando ? (int)$componente_edit['stock'] : '' ?>" 
                value="<?= $editando ? htmlspecialchars($componente_edit['stock']) : '' ?>" required>

            <input type="text" name="caracteristicas" placeholder="Características del equipo"
                value="<?= $editando ? htmlspecialchars($componente_edit['caracteristicas']) : '' ?>" readonly>

            <input type="text" name="estado" placeholder="Estado del equipo" 
                value="<?= $editando ? htmlspecialchars($componente_edit['estado']) : '' ?>" readonly>

            <input type="text" name="ubicacion" placeholder="Ubicación del equipo"
                value="<?= $editando ? htmlspecialchars($componente_edit['ubicacion']) : '' ?>" readonly>

            <input type="text" id="observaciones" name="observaciones" placeholder="Observaciones"
                value="<?= $editando ? htmlspecialchars($componente_edit['observaciones']) : '' ?>" readonly>

            <?php if ($editando): ?>
                <button type="submit" name="guardar_cambios">Guardar Cambios</button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>">Cancelar</a>
            <?php else: ?>
                <button type="submit" class="btn-pequeno" name="retirar">Retirar Insumo</button>
            <?php endif; ?>
        </form>
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
                document.querySelector('input[name="marca"]').value = data.marca;
                document.querySelector('input[name="categoria"]').value = data.categoria;
                document.querySelector('input[name="ubicacion"]').value = data.ubicacion;
                document.querySelector('input[name="estado"]').value = data.estado;
                document.querySelector('input[name="caracteristicas"]').value = data.caracteristicas;
                document.querySelector('input[name="observaciones"]').value = data.observaciones;
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

    if ($_POST['stock'] > $componente_edit['stock']) {
    die("La cantidad solicitada excede el stock disponible.");
    }

</script>
<style>
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