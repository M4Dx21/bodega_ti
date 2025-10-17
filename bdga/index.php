<?php
session_start();
if (isset($_POST['role'])) {
    $_SESSION['role'] = $_POST['role'];

    if ($_POST['role'] == 'prestador') {
        header("Location: login.php?role=prestador");
        exit;
    } elseif ($_POST['role'] == 'admin') {
        header("Location: loginadmin.php?role=admin");
        exit;
    } elseif ($_POST['role'] == 'especial') {
        header("Location: ../dcotr/login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administracion de insumos del Hospital Clinico Félix Bulnes</title>
    <link rel="stylesheet" href="asset/styles.css">
    <style>
        .container form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            justify-items: center;
        }
        .role-button {
            display: flex;                /* activa flexbox */
            align-items: center;          /* centra verticalmente */
            justify-content: center;      /* centra horizontalmente */
            white-space: normal;          /* permite salto de línea */
            line-height: 1.2;
            text-align: center;
            padding: 12px 20px;
            width: 55%;
            box-sizing: border-box;
            text-decoration: none;        /* quita subrayado */
        }
    </style>
</head>
<body class="index-page">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Menu de insumos TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
    </div>
    <div class="container">
        <h2>Selecciona tu usuario</h2>
        <form method="post">
            <button type="submit" name="role" value="admin" class="role-button admin">Admin</button>
            <button type="submit" name="role" value="prestador" class="role-button prestador">Bodega</button>
            <button type="submit" name="role" value="especial" class="role-button admin">Solicitudes Bodega</button>
            <a href="/prestamos/index.php" class="role-button prestador">Préstamo de equipamiento</a>
        </form>
    </div>
</body>
</html>