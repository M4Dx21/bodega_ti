<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="header">
    <img src="asset/logo.png" alt="Logo">
    <div class="header-text">
        <div class="main-title">Gestión de insumos</div>
        <div class="sub-title">Hospital Clínico Félix Bulnes</div>
    </div>
    <button id="cuenta-btn" onclick="toggleAccountInfo()">
        <?php echo $_SESSION['nombre']; ?>
    </button>
    <div id="accountInfo" style="display: none;">
        <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
        <form action="logout.php" method="POST">
            <button type="submit" class="logout-btn">Salir</button>
        </form>
    </div>
    <div class="botonera">
    <form action="agregarcomp.php" method="post">
        <button type="submit" class="btn-small">🗄️ Agregar Insumos</button>
    </form>

    <button class="btn-small" onclick="window.location.href='bodega.php'">📦 Control de bodega</button>

    <form action="exportar_excel.php" method="post">
        <button class="btn-small" type="submit">📤 Exportar Excel</button>
    </form>

    <button class="btn-small" onclick="window.location.href='historiale.php'">📑 Historial Entrada</button>

    <button class="btn-small" onclick="window.location.href='historials.php'">📑 Historial Salida</button>
</div>
</div>