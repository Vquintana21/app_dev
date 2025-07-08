<?php

// guardar_horas_docente.php - Versión para cursos clínicos compatible con PHP 5.x
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("conexion.php");
header('Content-Type: application/json');

// Debug: Log de datos recibidos
error_log("POST data: " . print_r($_POST, true));

// Verificar parámetros requeridos
$requiredParams = array('idProfesoresCurso', 'rutDocente', 'idCurso', 'horas');
foreach ($requiredParams as $param) {
    if (!isset($_POST[$param])) {
        echo json_encode(array(
            'success' => false,
            'message' => "Falta el parámetro requerido: $param",
            'debug' => $_POST
        ));
        exit;
    }
}

$idProfesoresCurso = intval($_POST['idProfesoresCurso']);
$rutDocente = mysqli_real_escape_string($conn, $_POST['rutDocente']);
$idCurso = intval($_POST['idCurso']);
$horas = floatval($_POST['horas']);

if (isset($_POST['unidadAcademica'])) {
    $unidadAcademica = mysqli_real_escape_string($conn, $_POST['unidadAcademica']);
} else {
    $unidadAcademica = '';
}

// Validar que las horas sean un número positivo o cero
if ($horas < 0) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Las horas deben ser un valor positivo o cero'
    ));
    exit;
}

try {
    // Verificar conexión
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Iniciar transacción
    mysqli_autocommit($conn, false);
    
    // Obtener usuario de sesión o usar valor por defecto
    if (isset($_SESSION['Rut'])) {
        $usuario = $_SESSION['Rut'];
    } else {
        $usuario = 'sistema_clinico';
    }
    
    $timestamp = date("Y-m-d H:i:s");
    
    // Debug: Log de datos procesados
    error_log("Procesando datos: ID=$idProfesoresCurso, RUT=$rutDocente, Curso=$idCurso, Horas=$horas");
    
    // PASO 1: Desactivar todas las entradas existentes para este docente en este curso
    $queryDesactivar = "UPDATE docenteclases 
                       SET vigencia = 0, 
                           fechaModificacion = ?, 
                           usuarioModificacion = ?
                       WHERE idCurso = ? AND rutDocente = ? AND vigencia = 1";
    
    $stmtDesactivar = mysqli_prepare($conn, $queryDesactivar);
    if (!$stmtDesactivar) {
        throw new Exception('Error preparando consulta de desactivación: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmtDesactivar, "ssis", $timestamp, $usuario, $idCurso, $rutDocente);
    
    if (!mysqli_stmt_execute($stmtDesactivar)) {
        throw new Exception('Error al desactivar registros existentes: ' . mysqli_stmt_error($stmtDesactivar));
    }
    
    $registrosDesactivados = mysqli_stmt_affected_rows($stmtDesactivar);
    error_log("Registros desactivados: $registrosDesactivados");
    
    mysqli_stmt_close($stmtDesactivar);
    
    // PASO 2: Solo insertar nuevo registro si las horas son mayor a 0
    if ($horas > 0) {
        $queryInsertar = "INSERT INTO docenteclases 
                         (rutDocente, idPlanClases, idCurso, horas, unidadAcademica, vigencia, usuarioModificacion, fechaModificacion) 
                         VALUES (?, NULL, ?, ?, ?, 1, ?, ?)";
        
        $stmtInsertar = mysqli_prepare($conn, $queryInsertar);
        if (!$stmtInsertar) {
            throw new Exception('Error preparando consulta de inserción: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmtInsertar, "sidsis", $rutDocente, $idCurso, $horas, $unidadAcademica, $usuario, $timestamp);
        
        if (!mysqli_stmt_execute($stmtInsertar)) {
            throw new Exception('Error al insertar nuevo registro: ' . mysqli_stmt_error($stmtInsertar));
        }
        
        $nuevoId = mysqli_insert_id($conn);
        error_log("Nuevo registro insertado con ID: $nuevoId");
        
        mysqli_stmt_close($stmtInsertar);
    }
    
    // Confirmar transacción
    mysqli_commit($conn);
    
    $debugInfo = array(
        'registros_desactivados' => $registrosDesactivados
    );
    
    if ($horas > 0) {
        if (isset($nuevoId)) {
            $debugInfo['nuevo_registro'] = $nuevoId;
        } else {
            $debugInfo['nuevo_registro'] = 'error';
        }
    } else {
        $debugInfo['nuevo_registro'] = 'no insertado';
    }
    
    echo json_encode(array(
        'success' => true,
        'message' => $horas > 0 ? 'Horas guardadas correctamente' : 'Horas eliminadas correctamente',
        'horas' => $horas,
        'debug' => $debugInfo
    ));
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    mysqli_rollback($conn);
    
    error_log("Error en guardar_horas_docente: " . $e->getMessage());
    
    echo json_encode(array(
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ));
}

mysqli_close($conn);
?>