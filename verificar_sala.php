<?php
include("conexion.php");
header('Content-Type: application/json');

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar acción
if (!isset($_POST['action']) || $_POST['action'] !== 'verificar_cambio') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Obtener parámetros
$idplanclases = isset($_POST['idplanclases']) ? (int)$_POST['idplanclases'] : 0;
$tipoNuevo = isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '';

if (!$idplanclases || empty($tipoNuevo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    // Obtener el tipo actual
    $queryTipoActual = "SELECT pcl_TipoSesion FROM planclases WHERE idplanclases = ?";
    $stmtTipoActual = $conn->prepare($queryTipoActual);
    $stmtTipoActual->bind_param("i", $idplanclases);
    $stmtTipoActual->execute();
    $resultTipoActual = $stmtTipoActual->get_result();
    
    if ($resultTipoActual->num_rows === 0) {
        throw new Exception('No se encontró la actividad');
    }
    
    $tipoActual = $resultTipoActual->fetch_assoc()['pcl_TipoSesion'];
    $stmtTipoActual->close();
    
    // Si los tipos son iguales, no necesita confirmación
    if ($tipoActual === $tipoNuevo) {
        echo json_encode([
            'success' => true,
            'necesita_confirmacion' => false
        ]);
        exit;
    }
    
    // Obtener si requieren sala, tanto el tipo actual como el nuevo
    $queryTiposSala = "SELECT tipo_sesion, pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion IN (?, ?)";
    $stmtTiposSala = $conn->prepare($queryTiposSala);
    $stmtTiposSala->bind_param("ss", $tipoActual, $tipoNuevo);
    $stmtTiposSala->execute();
    $resultTiposSala = $stmtTiposSala->get_result();
    
    $tiposSala = [];
    while ($row = $resultTiposSala->fetch_assoc()) {
        $tiposSala[$row['tipo_sesion']] = $row['pedir_sala'];
    }
    $stmtTiposSala->close();
    
    // Verificar si hay asignaciones existentes
    $queryAsignaciones = "SELECT idEstado, COUNT(*) as cantidad 
                         FROM asignacion_piloto 
                         WHERE idplanclases = ? 
                         GROUP BY idEstado";
    $stmtAsignaciones = $conn->prepare($queryAsignaciones);
    $stmtAsignaciones->bind_param("i", $idplanclases);
    $stmtAsignaciones->execute();
    $resultAsignaciones = $stmtAsignaciones->get_result();
    
    $estados = [0 => 0, 1 => 0, 3 => 0, 4 => 0]; // Inicializar contadores
    while ($estado = $resultAsignaciones->fetch_assoc()) {
        $estados[$estado['idEstado']] = $estado['cantidad'];
    }
    $stmtAsignaciones->close();
    
    // Determinar si necesita confirmación y qué mensaje mostrar
    $necesitaConfirmacion = false;
    $mensajeConfirmacion = '';
    
    // Clasificar el cambio según la tabla de flujo
    if ($tipoActual === 'Clase') {
        if ($tipoNuevo === 'Clase') {
            // Caso 1: De Clase a Clase - Solo confirmar si hay asignaciones confirmadas
            if ($estados[3] > 0) {
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Este cambio solicitará una modificación de la sala asignada. La reserva actual será cancelada hasta que se asigne una nueva sala.";
            }
        } else if (isset($tiposSala[$tipoNuevo]) && $tiposSala[$tipoNuevo] == 1) {
            // Caso 2: De Clase a AG/TP/EV/EX - Siempre confirmar
            $necesitaConfirmacion = true;
            $mensajeConfirmacion = "Al cambiar de 'Clase' a este tipo de actividad, se eliminarán las asignaciones automáticas y <b>deberá solicitar sala manualmente</b> desde la pestaña 'Salas'.";
        } else {
            // Caso 3: De Clase a VT/SA/TA - Confirmar si hay asignaciones
            if ($estados[0] > 0 || $estados[1] > 0 || $estados[3] > 0) {
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Al cambiar a un tipo de actividad que <b>no requiere sala</b>, se eliminarán todas las asignaciones existentes.";
            }
        }
    } else if (isset($tiposSala[$tipoActual]) && $tiposSala[$tipoActual] == 1) {
        if ($tipoNuevo === 'Clase') {
            // Caso 4: De AG/TP/EV/EX a Clase - Confirmar si hay asignaciones
            if ($estados[0] > 0 || $estados[1] > 0 || $estados[3] > 0) {
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Al cambiar a tipo 'Clase', se liberarán las salas actuales y se creará una asignación automática.";
            }
        } else if (isset($tiposSala[$tipoNuevo]) && $tiposSala[$tipoNuevo] == 1) {
            // Caso 5: De AG/TP/EV/EX a AG/TP/EV/EX - No necesita confirmación
            $necesitaConfirmacion = false;
        } else {
            // Caso 6: De AG/TP/EV/EX a VT/SA/TA - Confirmar si hay asignaciones
            if ($estados[0] > 0 || $estados[1] > 0 || $estados[3] > 0) {
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Al cambiar a un tipo sin sala, se eliminarán todas las asignaciones existentes.";
            }
        }
    } else {
        if ($tipoNuevo === 'Clase') {
            // Caso 7: De VT/SA/TA a Clase - No necesita confirmación
            $necesitaConfirmacion = false;
        } else if (isset($tiposSala[$tipoNuevo]) && $tiposSala[$tipoNuevo] == 1) {
            // Caso 8: De VT/SA/TA a AG/TP/EV/EX - No necesita confirmación
            $necesitaConfirmacion = false;
        } else {
            // Caso 9: De VT/SA/TA a VT/SA/TA - No necesita confirmación
            $necesitaConfirmacion = false;
        }
    }
    
    echo json_encode([
        'success' => true,
        'necesita_confirmacion' => $necesitaConfirmacion,
        'mensaje_confirmacion' => $mensajeConfirmacion,
        'tipo_actual' => $tipoActual,
        'tipo_nuevo' => $tipoNuevo
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>