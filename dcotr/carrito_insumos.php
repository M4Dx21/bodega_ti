<?php
session_start();
include 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

if (isset($_POST['send_email'])) {
        // Validar que todos los campos estén completos
            $campos = ['rut_paciente', 'cirugia', 'cod_cirugia', 'pabellon', 'cirujano', 'equipo', 'responsable'];
            foreach ($campos as $campo) {
                if (empty($_POST[$campo])) {
                    echo "<script>alert('Por favor completa todos los campos del formulario.'); window.location.href=window.location.href;</script>";
                    exit();
                }
            }

            // Validar que el carrito tenga insumos
            if (empty($_SESSION['carrito'])) {
                echo "<script>alert('No se puede finalizar el pedido. El carrito está vacío.'); window.location.href=window.location.href;</script>";
                exit();
            }

    $paciente = $_POST['rut_paciente'];
    $cirugia = $_POST['cirugia'];
    $cod_cirugia = $_POST['cod_cirugia'];
    $pabellon = $_POST['pabellon'];
    $cirujano = $_POST['cirujano'];
    $equipo = $_POST['equipo'];
    $responsable = $_POST['responsable'];

    $insumos_usados = [];

    foreach ($_SESSION['carrito'] as $insumo => $cantidad) {
        // Restar stock del insumo
        $stmt = $conn->prepare("UPDATE componentes SET stock = stock - ? WHERE insumo = ?");
        $stmt->execute([$cantidad, $insumo]);

        $insumos_usados[] = "$insumo (x$cantidad)";
    }

    $insumos_str = implode(', ', $insumos_usados);

    // Guardar en base de datos
    $stmt = $conn->prepare("INSERT INTO cirugias (rut_paciente, cirugia, cod_cirugia, pabellon, cirujano, equipo, insumos, responsable) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$paciente, $cirugia, $cod_cirugia, $pabellon, $cirujano, $equipo, $insumos_str, $responsable]);

    // Obtener correos de usuarios válidos
    $result = $conn->query("SELECT correo FROM usuarios WHERE correo IS NOT NULL AND correo != ''");

    if ($result->num_rows === 0) {
        echo "<script>alert('Error: No hay usuarios con correo registrado.'); window.location.href=window.location.href;</script>";
        exit();
    }

    // Enviar correo a cada usuario
    while ($row = $result->fetch_assoc()) {
        $correo = $row['correo'];

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'valdiviaalejandro2001@gmail.com';
            $mail->Password = 'vhgg mzzf kqov npjx';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->setFrom('valdiviaalejandro2001@gmail.com', 'Hospital Clínico Félix Bulnes');
            $mail->addAddress($correo);

            $mail->Subject = 'Lista de Insumos para Cirugía';
            $mail->Body = "Se ha programado una cirugía con los siguientes detalles:\n\n";
            $mail->Body .= "Paciente: $paciente\n";
            $mail->Body .= "Cirugía: $cirugia\n";
            $mail->Body .= "Código de cirugía: $cod_cirugia\n";
            $mail->Body .= "Pabellón: $pabellon\n";
            $mail->Body .= "Cirujano: $cirujano\n";
            $mail->Body .= "Equipo médico: $equipo\n";
            $mail->Body .= "Insumos requeridos: $insumos_str\n";
            $mail->Body .= "Responsable del registro: $responsable\n\n";
            $mail->Body .= "Atentamente,\nSistema de Cirugías - Hospital Clínico Félix Bulnes";

            $mail->send();

        } catch (Exception $e) {
            echo "<script>alert('Error al enviar el correo a: $correo. Error: {$mail->ErrorInfo}'); window.location.href=window.location.href;</script>";
            exit();
        }
    }

    $_SESSION['carrito'] = [];
    echo "<script>alert('Pedido finalizado. Stock actualizado y correos enviados.');</script>";
    header("Location: ".$_SERVER['PHP_SELF']."?success=1");
    exit();
}

// Agregar o quitar insumo al carrito
if (isset($_POST['action'])) {
    $insumo = $_POST['insumo'];
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    switch ($_POST['action']) {
        case 'add':
            if (isset($_SESSION['carrito'][$insumo])) {
                $_SESSION['carrito'][$insumo] += $cantidad;
            } else {
                $_SESSION['carrito'][$insumo] = $cantidad;
            }
            break;

        case 'remove':
            unset($_SESSION['carrito'][$insumo]);
            break;

        case 'decrease':
            if (isset($_SESSION['carrito'][$insumo])) {
                $_SESSION['carrito'][$insumo]--;
                if ($_SESSION['carrito'][$insumo] <= 0) {
                    unset($_SESSION['carrito'][$insumo]);
                }
            }
            break;
    }

    echo json_encode($_SESSION['carrito']);
    exit;
}

// Mostrar Carrito de Compras
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos de Urologia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Solicitar insumos médicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='principal.php'">Volver</button>
        </div>
    </div>
    <style>
        .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .insumo-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .insumo-card:hover {
            transform: scale(1.05);
        }

        .insumo-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .insumo-card h3 {
            margin: 10px 0;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
    <script>carrito.js</script>
</head>
<body>
    <div class="container">
        <div class="selection-container">
            <h2>Carrito de Compras</h2>
                <ul id="carrito-items">
                    <?php if (!empty($_SESSION['carrito'])): ?>
                        <?php foreach ($_SESSION['carrito'] as $insumo => $cantidad): ?>
                            <li>
                                <span><?= htmlspecialchars($insumo) ?> (x<?= $cantidad ?>)</span>
                                <button class="decrease-qty" data-insumo="<?= htmlspecialchars($insumo) ?>">-</button>
                                <button class="increase-qty" data-insumo="<?= htmlspecialchars($insumo) ?>">+</button>
                                <button class="remove-from-cart" data-insumo="<?= htmlspecialchars($insumo) ?>">Eliminar</button>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>El carrito está vacío</li>
                    <?php endif; ?>
                </ul>
            <form method="post">
                <h3>Datos de cirugia</h3>
                <input type="text" name="rut_paciente" placeholder="Rut del paciente sin puntos ni guion" required>
                <input type="text" name="cirugia" placeholder="Tipo de cirugía" required>
                <input type="text" name="cod_cirugia" placeholder="Código de cirugía" required>
                <input type="text" name="pabellon" placeholder="Pabellón" required>
                <input type="text" name="cirujano" placeholder="Nombre del cirujano" required>
                <input type="text" name="equipo" placeholder="Equipo médico" required>
                <input type="text" name="responsable" placeholder="Responsable preparacion" required>
                <button type="submit" name="send_email">Finalizar Pedido</button>
            </form>
        </div>
    </div>
</body>
<style>
    .carrito-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .carrito-item {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #ddd;
        padding: 5px;
        border-radius: 5px;
    }

    .mini-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
    }
</style>
    <script src="carrito.js"></script>
    <script>
        document.addEventListener('click', function(event) {
            const insumo = event.target.dataset.insumo;

            if (event.target.classList.contains('remove-from-cart')) {
                actualizar('remove', insumo);
            }

            if (event.target.classList.contains('decrease-qty')) {
                actualizar('decrease', insumo);
            }

            if (event.target.classList.contains('increase-qty')) {
                actualizar('add', insumo);
            }
        });

        function actualizar(action, insumo) {
            fetch('carrito_insumos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action, insumo })
            })
            .then(response => response.json())
            .then(data => actualizarCarrito(data));
        }

        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }

        function actualizarCarrito(carrito) {
            const carritoItems = document.getElementById('carrito-items');
            carritoItems.innerHTML = '';

            if (Object.keys(carrito).length === 0) {
                carritoItems.innerHTML = '<li>El carrito está vacío</li>';
                return;
            }

            for (const insumo in carrito) {
                const cantidad = carrito[insumo];

                const li = document.createElement('li');
                li.innerHTML = `
                    <span>${insumo} (x${cantidad})</span>
                    <button class="decrease-qty" data-insumo="${insumo}">-</button>
                    <button class="increase-qty" data-insumo="${insumo}">+</button>
                    <button class="remove-from-cart" data-insumo="${insumo}">Eliminar</button>
                `;
                carritoItems.appendChild(li);
            }
        }
    </script>
</html>
<?php
exit();
}
?>