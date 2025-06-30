<?php
require 'vendor/autoload.php';
include 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 300);  // hasta 5 minutos
ini_set('memory_limit', '512M');     // suficiente RAM

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    $archivo = $_FILES['archivo_excel']['tmp_name'];

    if (is_uploaded_file($archivo)) {
        try {
            // Leer el Excel
            $reader = IOFactory::createReaderForFile($archivo);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($archivo);
            $hoja = $spreadsheet->getActiveSheet();

            $fila = 2;                 // Fila 1 = encabezados
            $filas_procesadas = 0;

            while (true) {
                // === 1. Obtener valores ===
                $codigo          = trim($hoja->getCell("A$fila")->getValue());
                $insumo          = trim($hoja->getCell("B$fila")->getValue());
                $stock           = (int) trim($hoja->getCell("C$fila")->getValue());
                $categoria       = trim($hoja->getCell("D$fila")->getValue());
                $marca           = trim($hoja->getCell("E$fila")->getValue());
                $estado          = trim($hoja->getCell("F$fila")->getValue());
                $ubicacion       = trim($hoja->getCell("G$fila")->getValue());
                $observaciones   = trim($hoja->getCell("H$fila")->getValue());
                $caracteristicas = trim($hoja->getCell("I$fila")->getValue());
                $garantia        = trim($hoja->getCell("J$fila")->getValue());
                $comprobante     = trim($hoja->getCell("K$fila")->getValue());

                // Fin si la fila está vacía
                if ($codigo === '' && $insumo === '') break;

                $fecha_ingreso = date('Y-m-d H:i:s');

                // === 2. Verificar si el código existe ===
                $stmtCheck = $conn->prepare("SELECT 1 FROM componentes WHERE codigo = ?");
                $stmtCheck->bind_param('s', $codigo);
                $stmtCheck->execute();
                $existe = $stmtCheck->get_result()->num_rows > 0;
                $stmtCheck->close();

                if ($existe) {
                    // === 2a. Actualizar stock y demás campos ===
                    $stmtUpdate = $conn->prepare(
                        "UPDATE componentes SET
                            insumo = ?,
                            stock = stock + ?,          -- suma
                            categoria = ?,
                            marca = ?,
                            estado = ?,
                            ubicacion = ?,
                            observaciones = ?,
                            caracteristicas = ?,
                            garantia = ?,
                            comprobante = ?,
                            fecha_ingreso = ?
                         WHERE codigo = ?"
                    );
                    $stmtUpdate->bind_param(
                        'sissssssssss',
                        $insumo, $stock, $categoria, $marca, $estado, $ubicacion,
                        $observaciones, $caracteristicas, $garantia, $comprobante,
                        $fecha_ingreso, $codigo
                    );
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                } else {
                    // === 2b. Insertar nuevo registro ===
                    $stmtInsert = $conn->prepare(
                        "INSERT INTO componentes (
                            codigo, insumo, stock, categoria, marca, estado, ubicacion,
                            observaciones, fecha_ingreso, caracteristicas, garantia, comprobante
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    $stmtInsert->bind_param(
                        'ssisssssssss',
                        $codigo, $insumo, $stock, $categoria, $marca, $estado, $ubicacion,
                        $observaciones, $fecha_ingreso, $caracteristicas, $garantia, $comprobante
                    );
                    $stmtInsert->execute();
                    $stmtInsert->close();
                }

                $filas_procesadas++;
                $fila++;
            }

            // Redirigir mostrando el total procesado
            header("Location: agregarcomp.php?importado=$filas_procesadas");
            exit;

        } catch (Exception $e) {
            echo "Error al procesar el archivo: " . $e->getMessage();
        }
    } else {
        echo "Error al subir el archivo.";
    }
} else {
    echo "Acceso no autorizado.";
}
?>
