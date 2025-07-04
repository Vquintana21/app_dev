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

function procesarRUTCorrectamente($rut_docente) {
    $rut_limpio = str_replace(['.', ' '], '', trim($rut_docente));
    
    if (strpos($rut_limpio, '-') !== false) {
        $partes = explode('-', $rut_limpio);
        $parte_numerica = $partes[0];
        $digito_verificador = strtoupper($partes[1]);
    } else {
        $parte_numerica = substr($rut_limpio, 0, -1);
        $digito_verificador = strtoupper(substr($rut_limpio, -1));
    }
    
    if (!is_numeric($parte_numerica)) {
        throw new Exception("RUT inválido");
    }
    
    // ✅ 9 dígitos + DV = 10 caracteres total
    $parte_numerica_formateada = str_pad($parte_numerica, 9, "0", STR_PAD_LEFT);
    $rut_completo_10_digitos = $parte_numerica_formateada . $digito_verificador;
    
    return [
        'rut_formateado' => $rut_completo_10_digitos,
        'digito_verificador' => $digito_verificador,
        'parte_numerica' => $parte_numerica,
        'rut_original' => $rut_docente
    ];
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
    
    // ✅ PROCESAMIENTO CORRECTO DEL RUT - 10 DÍGITOS INCLUYENDO DV
try {
    $resultado_rut = procesarRUTCorrectamente($rut_docente);
    $rut_formateado = $resultado_rut['rut_formateado'];  // 10 caracteres incluyendo DV
    
    // Debug mejorado
    $rut_debug = $resultado_rut;
    
} catch (Exception $e) {
    sendResponse(false, "Error al procesar RUT: " . $e->getMessage(), array(
        'rut_original' => $rut_docente
    ));
}
    
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
    
 // insertar en spre_personas, agregar al curso
$queryAsignarCurso = "INSERT INTO spre_profesorescurso (idcurso, rut, idTipoParticipacion, Vigencia) 
                     VALUES (?, ?, ?, '1')";
$stmtAsignarCurso = $conexion3->prepare($queryAsignarCurso);

if (!$stmtAsignarCurso) {
    sendResponse(false, "Error al preparar asignación al curso", array(
        'error' => $conexion3->error
    ));
}

$stmtAsignarCurso->bind_param("ssi", $idcurso, $rut_formateado, $funcion);

if (!$stmtAsignarCurso->execute()) {
    sendResponse(false, "Error al asignar docente al curso", array(
        'error' => $stmtAsignarCurso->error
    ));
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