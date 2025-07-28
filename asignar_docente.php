<?php
// asignar_docente.php - Versión con corrección de encoding

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para limpiar caracteres problemáticos
function limpiarTexto($texto) {
    if ($texto === null) return null;
    
    // Convertir a UTF-8 y eliminar caracteres problemáticos
    $texto = utf8_encode(utf8_decode($texto)); // Normalizar UTF-8
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto); // Eliminar caracteres de control
    
    return trim($texto);
}

function jsonSeguro($data) {
    // Limpiar recursivamente todos los strings en el array
    array_walk_recursive($data, function(&$item) {
        if (is_string($item)) {
            $item = limpiarTexto($item);
        }
    });
    
    // Para PHP 5.6 solo usar JSON_UNESCAPED_UNICODE (disponible desde PHP 5.4)
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        // json_last_error_msg() no siempre está en PHP 5.6, usar código de error
        $error_code = json_last_error();
        error_log("ERROR JSON - Código: " . $error_code);
        error_log("Datos problemáticos: " . print_r($data, true));
        
        // Fallback simple
        return json_encode(array(
            'success' => isset($data['success']) ? $data['success'] : false,
            'message' => 'Error de codificacion',
            'error' => 'Problema con caracteres especiales'
        ));
    }
    
    return $json;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("ERROR FATAL en asignar_docente.php: " . print_r($error, true));
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo jsonSeguro([
                'success' => false,
                'message' => 'Error fatal del servidor'
            ]);
        }
    }
});

function logDebug($message, $data = null) {
    $log = "[" . date('Y-m-d H:i:s') . "] ASIGNAR_DOCENTE: " . $message;
    if ($data !== null) {
        $log .= " | Data: " . print_r($data, true);
    }
    error_log($log);
}

function sendResponse($success, $message, $data = null) {
    logDebug("Enviando respuesta", ['success' => $success, 'message' => $message]);
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    $response = [
        'success' => $success,
        'message' => limpiarTexto($message),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    $jsonResponse = jsonSeguro($response);
    logDebug("JSON generado", ['length' => strlen($jsonResponse), 'content' => substr($jsonResponse, 0, 200)]);
    
    echo $jsonResponse;
    exit;
}

try {
    logDebug("=== INICIO SCRIPT ===");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Método no permitido');
    }
    
    logDebug("Incluyendo conexion.php");
    ob_start();
    require_once("conexion.php");
    $conexion_output = ob_get_clean();
    
    if (!empty($conexion_output)) {
        logDebug("Output de conexion.php", $conexion_output);
    }
    
    if (!isset($conexion3) || !$conexion3 || !mysqli_ping($conexion3)) {
        sendResponse(false, "Error de conexión a la base de datos");
    }
    
    logDebug("Conexión BD OK");
    
    $rut_docente = isset($_POST['rut_docente']) ? trim($_POST['rut_docente']) : null;
    $idcurso = isset($_POST['idcurso']) ? (int)$_POST['idcurso'] : null;
    $funcion = isset($_POST['funcion']) ? (int)$_POST['funcion'] : null;
    
    logDebug("Parámetros recibidos", [
        'rut_docente' => $rut_docente,
        'idcurso' => $idcurso,
        'funcion' => $funcion
    ]);
    
    if (!$rut_docente || !$idcurso || !$funcion) {
        sendResponse(false, "Datos incompletos - RUT: $rut_docente, Curso: $idcurso, Función: $funcion");
    }
    
    // Buscar departamento con encoding seguro
    logDebug("Buscando departamento del docente");
    $docente_query = "SELECT Departamento FROM spre_bancodocente WHERE rut = ?";
    
    if (!($stmt = $conexion3->prepare($docente_query))) {
        sendResponse(false, "Error en preparar consulta de docente: " . $conexion3->error);
    }
    
    $stmt->bind_param("s", $rut_docente);
    
    if (!$stmt->execute()) {
        $stmt->close();
        sendResponse(false, "Error al ejecutar consulta de docente: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $departamento = null;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $departamento = limpiarTexto($row['Departamento']); // LIMPIAR AQUÍ
        logDebug("Departamento encontrado (limpio)", $departamento);
    } else {
        logDebug("No se encontró departamento para el docente");
    }
    $stmt->close();
    
    // Verificar si ya existe
    $check_query = "SELECT idProfesoresCurso FROM spre_profesorescurso 
                    WHERE rut = ? AND idcurso = ? AND Vigencia = '1' AND idTipoParticipacion NOT IN (8,10)";
    
    if (!($stmt = $conexion3->prepare($check_query))) {
        sendResponse(false, "Error en preparar consulta de verificación: " . $conexion3->error);
    }
    
    $stmt->bind_param("si", $rut_docente, $idcurso);
    
    if (!$stmt->execute()) {
        $stmt->close();
        sendResponse(false, "Error al ejecutar verificación: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        sendResponse(false, "El docente ya está asignado a este curso");
    }
    $stmt->close();
    
    // Insertar docente
    logDebug("Iniciando inserción de docente");
    $conexion3->autocommit(false);
    
    try {
        $query = "INSERT INTO spre_profesorescurso 
                  (rut, idcurso, idTipoParticipacion, Vigencia, FechaValidacion, 
                   UsuarioValidacion, activo, unidad_academica_docente) 
                  VALUES (?, ?, ?, '1', NOW(), 'sistema', '1', ?)";
        
        if (!($stmt = $conexion3->prepare($query))) {
            throw new Exception("Error preparando inserción: " . $conexion3->error);
        }
        
        $stmt->bind_param("siis", $rut_docente, $idcurso, $funcion, $departamento);
        
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando inserción: " . $stmt->error);
        }
        
        $insertId = $conexion3->insert_id;
        $stmt->close();
        
        $conexion3->commit();
        $conexion3->autocommit(true);
        
        logDebug("Docente insertado exitosamente", ['id' => $insertId]);
        
        // Respuesta exitosa con datos limpios
        sendResponse(true, 'Docente asignado correctamente', [
            'id' => $insertId,
            'rut' => $rut_docente,
            'curso' => $idcurso,
            'funcion' => $funcion,
            'unidad_academica' => $departamento // Ya está limpio
        ]);
        
    } catch (Exception $e) {
        $conexion3->rollback();
        $conexion3->autocommit(true);
        sendResponse(false, "Error al insertar docente: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    logDebug("ERROR GENERAL", $e->getMessage());
    sendResponse(false, "Error del servidor: " . $e->getMessage());
    
} catch (Error $e) {
    logDebug("ERROR FATAL", $e->getMessage());
    sendResponse(false, "Error crítico del servidor");
    
} finally {
    logDebug("=== FIN SCRIPT ===");
    if (isset($conexion3) && $conexion3) {
        $conexion3->close();
    }
}

sendResponse(false, "Error: El script no envió respuesta válida");
?>