<?php
session_start();
include 'db.php';
include 'funciones.php';

$insumosBajos = obtenerInsumosBajoStock($conn);
if ($insumosBajos !== false && !empty($insumosBajos)) {
    $_SESSION['alertas_stock'] = $insumosBajos;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Gestión de insumos médicos</div>
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
        <style>
            .container {
                background: rgba(255, 255, 255, 0.89); 
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0px 0px 10px rgba(70, 25, 25, 0.1);
                width: 100%;
                max-width: 700px;
                margin: 10px auto;
                margin-top: 200px;
            }           

            .botonera > * {
                display: flex;
                justify-content: center;
            }

            .btn,
            .btn-dashboard {
                background-color: #0066cc;
                color: #fff;
                border: none;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                transition: background-color .3s, transform .3s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                display: inline-flex;
                align-items: center;
                gap: 6px;
                text-align: center;
            }

            .btn:hover,
            .btn-dashboard:hover {
                background-color: #004c99;
                transform: translateY(-2px);
            }
    </style>
</head>
<body>
    <div class="container">
        <div class="botonera">
            <form action="agregarcomp.php" method="post">
                <button type="submit" class="btn">Agregar Insumos</button>
            </form>

            <button class="btn" onclick="window.location.href='bodega.php'">
                Control de bodega
            </button>

            <form action="exportar_excel.php" method="post">
                <button class="btn" type="submit">📤 Exportar a Excel</button>
            </form>

            <button class="btn" onclick="window.location.href='historiale.php'">
                Historial de Entrada
            </button>

                    <div style="position: relative; display: inline-block;">
                        <button type="button" class="btn-alertas" onclick="toggleAlertPanel()">
                            <i class="fas fa-exclamation-triangle"></i>
                            Alertas de Stock
                            <?php if (!empty($insumosBajos)): ?>
                                <span class="alert-badge"><?= count($insumosBajos) ?></span>
                            <?php endif; ?>
                        </button>
                
                        <div class="alert-panel" id="alertPanel">
                            <?php if (!empty($insumosBajos)): ?>
                                <h5 style="margin-top: 0; color: #721c24;">
                                    <i class="fas fa-boxes"></i> Insumos con Stock Bajo
                                </h5>
                                <ul style="padding-left: 20px; margin-bottom: 0;">
                                <?php foreach ($insumosBajos as $insumo): ?>
                                    <li style="margin-bottom: 8px;">
                                        <strong><?= htmlspecialchars($insumo['insumo']) ?></strong>
                                        <div style="font-size: 0.9em;">
                                            Stock: <?= $insumo['stock'] ?> | 
                                            Ubicación: <?= htmlspecialchars($insumo['ubicacion']) ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p style="margin-bottom: 0;">No hay alertas de stock</p>
                            <?php endif; ?>
                        </div>
                    </div>
            
            <button class="btn" onclick="window.location.href='historials.php'">
                Historial de Salida
            </button>
        </div>
    </div>
    <script>
        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
        function toggleAlertPanel() {
            const panel = document.getElementById('alertPanel');
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        }
        
        document.addEventListener('click', function(event) {
            const alertBtn = document.querySelector('.btn-alertas');
            const panel = document.getElementById('alertPanel');
            
            if (!alertBtn.contains(event.target) && event.target !== alertBtn) {
                panel.style.display = 'none';
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

        document.getElementById('dashboardBtn').addEventListener('click', function() {
            window.location.href = 'dashboard.php';
        });
    </script>
</body>
</html>
