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
    
    // Función para verificar si un tipo requiere sala
    function requiereSala($conn, $tipoSesion) {
        $queryTipoSala = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? LIMIT 1";
        $stmtTipoSala = $conn->prepare($queryTipoSala);
        $stmtTipoSala->bind_param("s", $tipoSesion);
        $stmtTipoSala->execute();
        $resultTipoSala = $stmtTipoSala->get_result();
        
        if ($row = $resultTipoSala->fetch_assoc()) {
            $requiere = ($row['pedir_sala'] == 1);
        } else {
            $requiere = false;
        }
        
        $stmtTipoSala->close();
        return $requiere;
    }
    
    // Verificar si cada tipo requiere sala
    $tipoActualRequiereSala = requiereSala($conn, $tipoActual);
    $tipoNuevoRequiereSala = requiereSala($conn, $tipoNuevo);
    
    // Verificar si hay asignaciones existentes (cualquier estado excepto liberadas)
    $queryAsignaciones = "SELECT idEstado, COUNT(*) as cantidad 
                         FROM asignacion 
                         WHERE idplanclases = ? AND idEstado != 4
                         GROUP BY idEstado";
    $stmtAsignaciones = $conn->prepare($queryAsignaciones);
    $stmtAsignaciones->bind_param("i", $idplanclases);
    $stmtAsignaciones->execute();
    $resultAsignaciones = $stmtAsignaciones->get_result();
    
    $estados = [0 => 0, 1 => 0, 3 => 0]; // Inicializar contadores (sin incluir liberadas)
    $totalAsignacionesActivas = 0;
    
    while ($estado = $resultAsignaciones->fetch_assoc()) {
        $estados[$estado['idEstado']] = $estado['cantidad'];
        $totalAsignacionesActivas += $estado['cantidad'];
    }
    $stmtAsignaciones->close();
    
    // Determinar si necesita confirmación y qué mensaje mostrar
    $necesitaConfirmacion = false;
    $mensajeConfirmacion = '';
    
    // REGLA GENERAL: Si pasa de actividad que requiere sala a actividad que NO requiere sala
    // Y tiene asignaciones activas, debe alertar
    if ($tipoActualRequiereSala && !$tipoNuevoRequiereSala && $totalAsignacionesActivas > 0) {
        $necesitaConfirmacion = true;
        
        // Personalizar mensaje según el estado de las asignaciones
        if ($estados[3] > 0) { // Hay salas asignadas/reservadas
            $mensajeConfirmacion = "Al cambiar a un tipo de actividad que <b>no requiere sala</b>, se eliminarán todas las asignaciones existentes, incluyendo <b>" . $estados[3] . " sala(s) ya asignada(s)</b>.";
        } else if ($estados[1] > 0) { // Hay modificaciones pendientes
            $mensajeConfirmacion = "Al cambiar a un tipo de actividad que <b>no requiere sala</b>, se eliminarán todas las solicitudes de sala existentes, incluyendo <b>" . $estados[1] . " modificación(es) pendiente(s)</b>.";
        } else if ($estados[0] > 0) { // Solo hay solicitudes pendientes
            $mensajeConfirmacion = "Al cambiar a un tipo de actividad que <b>no requiere sala</b>, se eliminarán todas las solicitudes de sala pendientes (" . $estados[0] . " solicitud(es)).";
        }
    }
    // CASOS ESPECÍFICOS ADICIONALES
    else if ($tipoActual === 'Clase') {
        if ($tipoNuevo === 'Clase') {
            // Caso 1: De Clase a Clase - Solo confirmar si hay asignaciones confirmadas
            if ($estados[3] > 0) {
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Este cambio solicitará una modificación de la sala asignada. La reserva actual será cancelada hasta que se asigne una nueva sala.";
            }
        } else if ($tipoNuevoRequiereSala) {
            // Caso 2: De Clase a otra actividad que requiere sala
            if ($totalAsignacionesActivas > 0) {
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Al cambiar de 'Clase' a este tipo de actividad, se eliminarán las asignaciones automáticas y <b>deberá solicitar sala manualmente</b> desde la pestaña 'Salas'.";
            }
        }
        // Caso 3: De Clase a actividad sin sala ya se maneja arriba en la regla general
    }
    else if ($tipoActualRequiereSala && $tipoNuevo === 'Clase') {
        // Caso 4: De actividad con sala a Clase
        if ($totalAsignacionesActivas > 0) {
            $necesitaConfirmacion = true;
            if ($estados[3] > 0) {
                //$mensajeConfirmacion = "Al cambiar a tipo 'Clase', se liberarán las <b>" . $estados[3] . " sala(s) asignada(s)</b> y se creará una asignación automática.";
				$mensajeConfirmacion = "Actividad actualizada, no olvide revisar su solicitud de sala.";
            } else {
                //$mensajeConfirmacion = "Al cambiar a tipo 'Clase', se eliminarán las solicitudes de sala existentes y se creará una asignación automática.";
				$mensajeConfirmacion = "Actividad actualizada, no olvide revisar su solicitud de sala.";
            }
        }
    }
    else if ($tipoActualRequiereSala && $tipoNuevoRequiereSala) {
        // Caso 5: De actividad con sala a otra actividad con sala
        // Generalmente no necesita confirmación, pero puede alertar sobre cambios de contexto
        if ($estados[3] > 0) {
            $necesitaConfirmacion = true;
            $mensajeConfirmacion = "Al cambiar el tipo de actividad, las salas asignadas mantendrán su estado actual, pero puede ser necesario revisar si son apropiadas para el nuevo tipo de actividad.";
        }
    }
    
    // Debug info (opcional, remover en producción)
    $debugInfo = [
        'tipo_actual' => $tipoActual,
        'tipo_nuevo' => $tipoNuevo,
        'tipo_actual_requiere_sala' => $tipoActualRequiereSala,
        'tipo_nuevo_requiere_sala' => $tipoNuevoRequiereSala,
        'total_asignaciones_activas' => $totalAsignacionesActivas,
        'estados' => $estados
    ];
    
    echo json_encode([
        'success' => true,
        'necesita_confirmacion' => $necesitaConfirmacion,
        'mensaje_confirmacion' => $mensajeConfirmacion,
        'tipo_actual' => $tipoActual,
        'tipo_nuevo' => $tipoNuevo,
        'debug' => $debugInfo // Remover en producción
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>