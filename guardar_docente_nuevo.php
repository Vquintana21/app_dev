<?php
// guardar_docentes_nuevo.php
header('Content-type: text/html; charset=utf-8');

// Activar el reporte de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Función para enviar respuesta con debug
function sendResponse($success, $message = '', $debug = array()) {
    header('Content-Type: application/json');
    $response = array(
        'success' => $success,
        'message' => $message,
        'debug' => $debug
    );
    echo json_encode($response);
    exit;
}

try {
    // 1. Verificar inclusión de conexión
    if (!file_exists("conexion.php")) {
        sendResponse(false, "Archivo conexion.php no encontrado", array('file' => 'conexion.php'));
    }
    include("conexion.php");
    
    // 2. Verificar conexión a BD
    if (!isset($conexion3)) {
        sendResponse(false, "La variable de conexión no está definida", array('variable' => 'conexion3'));
    }
    
    // 3. Recoger datos
    $idcurso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $rut_docente = isset($_POST['rut_docente']) ? $_POST['rut_docente'] : '';
    $unidad_academica = isset($_POST['unidad_academica']) ? $_POST['unidad_academica'] : '';
    $unidad_externa = isset($_POST['unidad_externa']) ? $_POST['unidad_externa'] : '';
    $nombres = isset($_POST['nombres']) ? $_POST['nombres'] : '';
    $paterno = isset($_POST['paterno']) ? $_POST['paterno'] : '';
    $materno = isset($_POST['materno']) ? $_POST['materno'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $funcion = isset($_POST['funcion']) ? $_POST['funcion'] : '';
    
    // **CORRECCIÓN IMPORTANTE: Procesar el RUT correctamente**
    // Eliminar todos los caracteres no numéricos (guión, puntos, etc.)
    $rut_numerico = preg_replace('/[^0-9]/', '', $rut_docente);
    
    // Si el RUT tiene más de 9 dígitos (incluye dígito verificador), eliminarlo
    if (strlen($rut_numerico) > 9) {
        $rut_sin_dv = substr($rut_numerico, 0, -1);
    } else {
        $rut_sin_dv = $rut_numerico;
    }
    
    // Formatear el RUT con padding de ceros a la izquierda (total: 10 caracteres)
    $rut_formateado = str_pad($rut_sin_dv, 10, "0", STR_PAD_LEFT);
    
    // Debug: mostrar cómo se procesa el RUT
    $rut_debug = array(
        'rut_original' => $rut_docente,
        'rut_numerico' => $rut_numerico,
        'rut_sin_dv' => $rut_sin_dv,
        'rut_formateado' => $rut_formateado
    );
    
    // Construir el nombre completo del funcionario
    $funcionario = trim($nombres . ' ' . $paterno . ' ' . $materno);
    
    // Determinar el departamento
    $departamento = '';
    if ($unidad_academica == 'Unidad Externa' && !empty($unidad_externa)) {
        $departamento = $unidad_externa;
    } else {
        $departamento = $unidad_academica;
    }
    
    // Primero verificar si el docente ya existe en el banco de docentes
    $queryVerificar = "SELECT idBancoDocente, rut, Funcionario FROM spre_bancodocente WHERE rut = ?";
    
    $stmtVerificar = $conexion3->prepare($queryVerificar);
    
    if (!$stmtVerificar) {
        sendResponse(false, "Error al preparar consulta verificar", array(
            'error' => $conexion3->error,
            'query' => $queryVerificar
        ));
    }
    
    $stmtVerificar->bind_param("s", $rut_formateado);
    
    if (!$stmtVerificar->execute()) {
        sendResponse(false, "Error al ejecutar consulta verificar", array(
            'error' => $stmtVerificar->error,
            'rut' => $rut_formateado
        ));
    }
    
    $resultadoVerificar = $stmtVerificar->get_result();
    
    if ($resultadoVerificar->num_rows > 0) {
        // El docente ya existe en el banco de docentes
        $docenteExistente = $resultadoVerificar->fetch_assoc();
        
        sendResponse(false, "El docente ya existe en el banco de docentes", array(
            'rut_debug' => $rut_debug,
            'docente_existente' => array(
                'idBancoDocente' => $docenteExistente['idBancoDocente'],
                'rut' => $docenteExistente['rut'],
                'funcionario' => $docenteExistente['Funcionario']
            )
        ));
    }
    
    // Insertar en spre_bancodocente
    $queryInsertar = "INSERT INTO spre_bancodocente (rut, Funcionario, Departamento, UnidadExterna) 
                      VALUES (?, ?, ?, NULL)";
    
    $stmtInsertar = $conexion3->prepare($queryInsertar);
    
    if (!$stmtInsertar) {
        sendResponse(false, "Error al preparar consulta insertar", array(
            'error' => $conexion3->error,
            'query' => $queryInsertar
        ));
    }
    
    $stmtInsertar->bind_param("sss", $rut_formateado, $funcionario, $departamento);
    
    if (!$stmtInsertar->execute()) {
        sendResponse(false, "Error al insertar en banco docente", array(
            'error' => $stmtInsertar->error,
            'valores' => array(
                'rut' => $rut_formateado,
                'funcionario' => $funcionario,
                'departamento' => $departamento
            )
        ));
    }
    
    // Insertar exitosa
    $idBancoDocente = $conexion3->insert_id;
    
    // **DEBUGGING: Verificar el estado de la conexión antes de la consulta de persona**
    if (!$conexion3->ping()) {
        // Reconectar si la conexión se perdió
        $conexion3->close();
        include("conexion.php");
    }
    
    // Verificar si existe en spre_personas
    $queryVerificarPersona = "SELECT Rut FROM spre_personas WHERE Rut = ?";
    
    $stmtVerificarPersona = $conexion3->prepare($queryVerificarPersona);
    
    if (!$stmtVerificarPersona) {
        sendResponse(false, "Error al preparar consulta verificar persona", array(
            'error' => $conexion3->error,
            'errno' => $conexion3->errno,
            'query' => $queryVerificarPersona,
            'conexion_status' => $conexion3->stat()
        ));
    }
    
    $stmtVerificarPersona->bind_param("s", $rut_formateado);
    
    if (!$stmtVerificarPersona->execute()) {
        sendResponse(false, "Error al ejecutar consulta verificar persona", array(
            'error' => $stmtVerificarPersona->error
        ));
    }
    
    $resultadoPersona = $stmtVerificarPersona->get_result();
    
    if ($resultadoPersona->num_rows == 0) {
        // Si no existe en spre_personas, insertarlo
        $queryPersona = "INSERT INTO spre_personas (Rut, Funcionario, Nombres, Paterno , Materno, Email, EmailReal) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmtPersona = $conexion3->prepare($queryPersona);
        
        if (!$stmtPersona) {
            sendResponse(false, "Error al preparar consulta insertar persona", array(
                'error' => $conexion3->error,
                'query' => $queryPersona
            ));
        }
        
        $stmtPersona->bind_param("sssssss", $rut_formateado, $funcionario, $nombres, $paterno, $materno, $email, $email);
        
        if (!$stmtPersona->execute()) {
            sendResponse(false, "Error al insertar en spre_personas", array(
                'error' => $stmtPersona->error,
                'valores' => array(
                    'rut' => $rut_formateado,
                    'funcionario' => $funcionario,
                    'nombres' => $nombres,
                    'paterno' => $paterno,
                    'materno' => $materno,
                    'email' => $email
                )
            ));
        }
    }
    
    // Éxito total
    sendResponse(true, "Docente guardado correctamente", array(
        'idBancoDocente' => $idBancoDocente,
        'rut_debug' => $rut_debug,
        'nombre' => $funcionario
    ));
    
} catch (Exception $e) {
    // Capturar cualquier excepción
    sendResponse(false, "Excepción capturada", array(
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ));
}

// Cerrar conexión si existe
if (isset($conexion3) && $conexion3) {
    $conexion3->close();
}
?>