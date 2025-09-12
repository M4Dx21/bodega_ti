<?php
require 'vendor/autoload.php';
include 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    $archivo = $_FILES['archivo_excel']['tmp_name'];

    if (is_uploaded_file($archivo)) {
        try {
            $reader = IOFactory::createReaderForFile($archivo);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($archivo);
            $hoja = $spreadsheet->getActiveSheet();

            $fila = 2;
            $filas_procesadas = 0;

            while (true) {
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
                $precio          = trim($hoja->getCell("L$fila")->getValue());
                $nro_orden       = trim($hoja->getCell("L$fila")->getValue());
                $provedor        = trim($hoja->getCell("L$fila")->getValue());

                if ($codigo === '' && $insumo === '') break;

                $fecha_ingreso = date('Y-m-d H:i:s');

                $stmtCheck = $conn->prepare("SELECT 1 FROM componentes WHERE codigo = ?");
                $stmtCheck->bind_param('s', $codigo);
                $stmtCheck->execute();
                $existe = $stmtCheck->get_result()->num_rows > 0;
                $stmtCheck->close();

                if ($existe) {
                    $stmtUpdate = $conn->prepare(
                        "UPDATE componentes SET
                            insumo = ?,
                            stock = ?,
                            categoria = ?,
                            marca = ?,
                            estado = ?,
                            ubicacion = ?,
                            observaciones = ?,
                            caracteristicas = ?,
                            garantia = ?,
                            comprobante = ?,
                            fecha_ingreso = ?,
                            precio = ?,
                            nro_orden = ?,
                            provedor = ?
                         WHERE codigo = ?"
                    );
                    $stmtUpdate->bind_param(
                        'sisssssssssssss',
                        $insumo, $stock, $categoria, $marca, $estado, $ubicacion,
                        $observaciones, $caracteristicas, $garantia, $comprobante,
                        $fecha_ingreso, $codigo, $precio, $nro_orden, $provedor
                    );
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                } else {
                    $stmtInsert = $conn->prepare(
                        "INSERT INTO componentes (
                            codigo, insumo, stock, categoria, marca, estado, ubicacion,
                            observaciones, fecha_ingreso, caracteristicas, garantia, comprobante, precio, nro_orden, provedor
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    $stmtInsert->bind_param(
                        'ssisssssssssss',
                        $codigo, $insumo, $stock, $categoria, $marca, $estado, $ubicacion,
                        $observaciones, $fecha_ingreso, $caracteristicas, $garantia, $comprobante, $precio, $nro_orden, $provedor
                    );
                    $stmtInsert->execute();
                    $stmtInsert->close();
                }

                $filas_procesadas++;
                $fila++;
            }

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
