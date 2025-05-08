<?php
include("conexion.php");
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Validación de datos de entrada
    if (!isset($_POST['idplanclases']) || !isset($_POST['idcurso']) || !isset($_POST['horas']) || !isset($_POST['docentes'])) {
        throw new Exception('Faltan datos requeridos');
    }

    $idplanclases = intval($_POST['idplanclases']);
    $idcurso = intval($_POST['idcurso']);
    $horasActividad = floatval($_POST['horas']);
    $docentesSeleccionados = json_decode($_POST['docentes'], true);

    if (!is_array($docentesSeleccionados)) {
        throw new Exception('Formato inválido de docentes');
    }

    // Primero desactivar todos los docentes existentes
    $queryDesactivar = "UPDATE docenteclases SET vigencia = 0 WHERE idPlanClases = ?";
    $stmtDesactivar = $conn->prepare($queryDesactivar);
    if (!$stmtDesactivar) {
        throw new Exception('Error preparando query de desactivación: ' . $conn->error);
    }
    $stmtDesactivar->bind_param("i", $idplanclases);
    $stmtDesactivar->execute();

    // Procesar cada docente
    foreach ($docentesSeleccionados as $rutDocente) {
        // Verificar si ya existe
        $queryVerificar = "SELECT idDocenteClases FROM docenteclases WHERE rutDocente = ? AND idPlanClases = ?";
        $stmtVerificar = $conn->prepare($queryVerificar);
        if (!$stmtVerificar) {
            throw new Exception('Error preparando query de verificación: ' . $conn->error);
        }
        $stmtVerificar->bind_param("si", $rutDocente, $idplanclases);
        $stmtVerificar->execute();
        $resultadoVerificar = $stmtVerificar->get_result();

        // Obtener unidad académica
        $queryUnidad = "SELECT unidad_academica_docente FROM spre_profesorescurso 
                       WHERE rut = ? AND idcurso = ? AND vigencia = 1 LIMIT 1";
        $stmtUnidad = $conexion3->prepare($queryUnidad);
        if (!$stmtUnidad) {
            throw new Exception('Error preparando query de unidad académica: ' . $conexion3->error);
        }
        $stmtUnidad->bind_param("si", $rutDocente, $idcurso);
        $stmtUnidad->execute();
        $resultadoUnidad = $stmtUnidad->get_result();
        $unidadAcademica = '';
        if ($rowUnidad = $resultadoUnidad->fetch_assoc()) {
            $unidadAcademica = $rowUnidad['unidad_academica_docente'];
        }

        if ($resultadoVerificar->num_rows > 0) {
            // Actualizar registro existente
            $row = $resultadoVerificar->fetch_assoc();
            $queryActualizar = "UPDATE docenteclases SET 
                               vigencia = 1,
                               horas = ?,
                               unidadAcademica = ?,
                               usuarioModificacion = 'sistemadpi',
                               fechaModificacion = NOW()
                               WHERE idDocenteClases = ?";
            $stmtActualizar = $conn->prepare($queryActualizar);
            if (!$stmtActualizar) {
                throw new Exception('Error preparando query de actualización: ' . $conn->error);
            }
            $stmtActualizar->bind_param("dsi", $horasActividad, $unidadAcademica, $row['idDocenteClases']);
            $stmtActualizar->execute();
        } else {
            // Insertar nuevo registro
            $queryInsertar = "INSERT INTO docenteclases 
                            (rutDocente, idPlanClases, idCurso, horas, unidadAcademica, vigencia, 
                             usuarioModificacion, fechaModificacion)
                            VALUES (?, ?, ?, ?, ?, 1, 'sistemadpi', NOW())";
            $stmtInsertar = $conn->prepare($queryInsertar);
            if (!$stmtInsertar) {
                throw new Exception('Error preparando query de inserción: ' . $conn->error);
            }
            $stmtInsertar->bind_param("siids", $rutDocente, $idplanclases, $idcurso, $horasActividad, $unidadAcademica);
            $stmtInsertar->execute();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Docentes actualizados exitosamente',
        'debug' => [
            'docentes_procesados' => count($docentesSeleccionados)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(200); // Cambiar a 200 para evitar error 500
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'post_data' => $_POST,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>