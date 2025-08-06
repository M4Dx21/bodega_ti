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
$mensaje = isset($_GET['mensaje']) ? urldecode($_GET['mensaje']) : '';
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
    $usuario  = $_SESSION['nombre'];
    $observaciones = isset($_POST['observaciones']) ? $_POST['observaciones'] : '';

    $stmt = $conn->prepare("SELECT stock, precio FROM componentes WHERE codigo = ?");
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
        $stock_actual = (int)$fila['stock'];
        $precio_actual = (float)$fila['precio'];

        $precio_unitario = $stock_actual > 0 ? $precio_actual / $stock_actual : 0;
        $precio_a_restar = $precio_unitario * $cantidad;

        $nuevo_stock = $stock_actual - $cantidad;
        $nuevo_precio = $precio_actual - $precio_a_restar;

        $stmt = $conn->prepare("UPDATE componentes SET stock = ?, precio = ? WHERE codigo = ?");
        $stmt->bind_param("ids", $nuevo_stock, $nuevo_precio, $codigo);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO salidas (num_serie, cantidad, fecha_salida, responsable, observaciones) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->bind_param("siss", $codigo, $cantidad, $usuario, $observaciones);
        $stmt->execute();
        $stmt->close();

        $mensaje_final = "Stock y precio actualizados. Salida registrada correctamente.";
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
    <h2><?= $editando ? 'Editar Insumos' : 'Retirar Insumos' ?></h2>
            <form id="form-agregar-insumo" onsubmit="return false;">
                <table border="1" cellpadding="10" cellspacing="0">
                    <tr>
                        <th>Número de Serie</th>
                        <th>Categoría</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Cantidad</th>
                        <th>Ubicación</th>
                        <th>Acción</th>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" id="codigo" placeholder="Número de serie" required>
                            <div id="sugerencias" style="position: absolute; background: white; border: 1px solid #ccc; z-index: 1000;"></div>
                        </td>
                        <td><input type="text" id="categoria" readonly></td>
                        <td><input type="text" id="marca" readonly></td>
                        <td><input type="text" id="insumo" readonly></td>
                        <td><input type="number" id="stock" min="1" required></td>
                        <td><input type="text" id="ubicacion" readonly></td>
                        <td><button type="button" onclick="agregarInsumo()">Agregar a lista</button></td>
                    </tr>
                </table>
            </form>

        <h3>Lista de insumos a retirar</h3>
        <form action="procesar_retiro_multiple.php" method="POST">
            <table border="1" cellpadding="10" cellspacing="0">
                <thead>
                    <tr>
                        <th>Número de Serie</th>
                        <th>Categoría</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Cantidad</th>
                        <th>Ubicación</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="body-carrito">
                </tbody>
            </table>
            <br>
            <button type="submit" class="btn-pequeno">Retirar Todos</button>
        </form>
    </div>
<script>
    let listaInsumos = [];
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

function buscarComponente(codigo) {
    if (!codigo.trim()) return;

    fetch("buscar_componente.php?codigo=" + encodeURIComponent(codigo))
        .then(res => res.json())
        .then(data => {
            if (data.encontrado) {
                document.getElementById("codigo").value = data.codigo;
                document.getElementById("categoria").value = data.categoria;
                document.getElementById("marca").value = data.marca;
                document.getElementById("insumo").value = data.insumo;
                document.getElementById("ubicacion").value = data.ubicacion;
                document.getElementById("stock").max = data.stock;
                document.getElementById("stock").setAttribute("data-max-stock", data.stock);
                document.getElementById("stock").focus();
            } else {
                alert("Insumo no encontrado.");
            }
        })
        .catch(error => alert("Error al buscar insumo: " + error));
}

document.getElementById("codigo").addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        e.preventDefault();
        buscarComponente(this.value);
    }
});

function agregarInsumo() {
    const codigo = document.getElementById("codigo").value.trim();
    const categoria = document.getElementById("categoria").value.trim();
    const marca = document.getElementById("marca").value.trim();
    const insumo = document.getElementById("insumo").value.trim();
    const cantidadInput = document.getElementById("stock");
    const cantidad = parseInt(cantidadInput.value.trim());
    const stockMax = parseInt(cantidadInput.getAttribute("data-max-stock"));
    const ubicacion = document.getElementById("ubicacion").value.trim();

    if (!codigo || !insumo || !marca || !categoria || !cantidad || cantidad < 1 || !ubicacion) {
        alert("Completa todos los campos antes de agregar.");
        return;
    }
    if (cantidad > stockMax) {
        alert("La cantidad excede el stock disponible (" + stockMax + ").");
        return;
    }

    if (listaInsumos.some(item => item.codigo === codigo)) {
        alert("Este insumo ya fue agregado.");
        return;
    }

    listaInsumos.push({ codigo });

    const tr = document.createElement("tr");
    tr.innerHTML = `

        <td><input type="hidden" name="codigo[]" value="${codigo}">${codigo}</td>
        <td><input type="hidden" name="categoria[]" value="${categoria}">${categoria}</td>
        <td><input type="hidden" name="marca[]" value="${marca}">${marca}</td>
        <td><input type="hidden" name="insumo[]" value="${insumo}">${insumo}</td>
        <td><input type="hidden" name="cantidad[]" value="${cantidad}">${cantidad}</td>
        <td><input type="hidden" name="ubicacion[]" value="${ubicacion}">${ubicacion}</td>
        <td><button type="button" onclick="eliminarInsumo(this, '${codigo}')">Eliminar</button></td>
    `;
    document.getElementById("body-carrito").appendChild(tr);

    document.getElementById("form-agregar-insumo").reset();
    document.getElementById("stock").removeAttribute("data-max-stock");
    document.getElementById("codigo").focus();
}

function eliminarInsumo(boton, codigo) {
    listaInsumos = listaInsumos.filter(item => item.codigo !== codigo);
    boton.closest("tr").remove();
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