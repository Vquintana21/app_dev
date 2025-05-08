<?php
// guardar_actividad_clinica.php
include("conexion.php");
header('Content-Type: application/json');

// Función para registrar mensajes de depuración
function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log_message .= " - Data: " . (is_array($data) || is_object($data) ? json_encode($data) : $data);
    }
    
    // Guardar en archivo de log
    file_put_contents('debug_actividad.log', $log_message . PHP_EOL, FILE_APPEND);
    
    // También incluir en la respuesta
    return $log_message;
}

try {
    debug_log("Iniciando proceso de guardado de actividad clínica");
    debug_log("POST recibido", $_POST);
    
    // Validar que existan los campos mínimos necesarios
    $requiredFields = ['activity-title', 'type', 'date', 'start_time', 'end_time', 'cursos_idcursos', 'dia'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $errorMsg = "Campos requeridos faltantes: " . implode(", ", $missingFields);
        debug_log($errorMsg);
        throw new Exception($errorMsg);
    }
    
    debug_log("Validación de campos básicos completada");
    
    // Obtener y sanitizar los valores
    $idplanclases = isset($_POST['idplanclases']) && $_POST['idplanclases'] != '0' 
        ? (int)$_POST['idplanclases'] 
        : null;
    
    $cursos_idcursos = (int)$_POST['cursos_idcursos'];
    $titulo = mysqli_real_escape_string($conn, $_POST['activity-title']);
    $tipo = mysqli_real_escape_string($conn, $_POST['type']);
    $subtipo = isset($_POST['subtype']) ? mysqli_real_escape_string($conn, $_POST['subtype']) : null;
    $fecha = mysqli_real_escape_string($conn, $_POST['date']);
    $inicio = mysqli_real_escape_string($conn, $_POST['start_time']) . ':00';
    $termino = mysqli_real_escape_string($conn, $_POST['end_time']) . ':00';
    $dia = mysqli_real_escape_string($conn, $_POST['dia']);
    $obligatorio = $_POST['pcl_condicion'] === 'Obligatorio' ? 'Obligatorio' : 'Libre';
    $evaluacion = $_POST['pcl_ActividadConEvaluacion'] === 'S' ? 'S' : 'N';
    
    // Obtener el número de bloque
    $bloque = isset($_POST['Bloque']) ? (int)$_POST['Bloque'] : null;
    
    debug_log("Valores procesados", [
        'idplanclases' => $idplanclases,
        'cursos_idcursos' => $cursos_idcursos,
        'titulo' => $titulo,
        'tipo' => $tipo,
        'subtipo' => $subtipo,
        'fecha' => $fecha,
        'inicio' => $inicio,
        'termino' => $termino,
        'dia' => $dia,
        'obligatorio' => $obligatorio,
        'evaluacion' => $evaluacion,
        'bloque' => $bloque
    ]);
    
    // Calcular la duración en formato HH:MM:SS
    $time1 = strtotime($inicio);
    $time2 = strtotime($termino);
    $difference = $time2 - $time1;
    $horas = floor($difference / 3600);
    $minutos = floor(($difference % 3600) / 60);
    $segundos = $difference % 60;
    $duracion = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
    
    debug_log("Duración calculada: $duracion");
    
    // Determinar si es inserción o actualización
    if ($idplanclases) {
        debug_log("Modo actualización para idplanclases=$idplanclases");
        
        // Actualización
        $query = "UPDATE planclases_test SET 
                    pcl_tituloActividad = ?, 
                    pcl_TipoSesion = ?,
                    pcl_SubTipoSesion = ?,
                    pcl_Fecha = ?,
                    pcl_Inicio = ?,
                    pcl_Termino = ?,
                    dia = ?,
                    pcl_condicion = ?,
                    pcl_ActividadConEvaluacion = ?,
                    pcl_HorasPresenciales = ?,
                    Bloque = ?,
                    pcl_fechamodifica = NOW(),
                    pcl_usermodifica = 'EditorClinico'
                  WHERE idplanclases = ?";
        
        // Mostrar la consulta con valores reales para depuración
        $debug_query = str_replace([
            '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'
        ], [
            "'$titulo'", 
            "'$tipo'", 
            $subtipo ? "'$subtipo'" : "NULL",
            "'$fecha'",
            "'$inicio'",
            "'$termino'",
            "'$dia'",
            "'$obligatorio'",
            "'$evaluacion'",
            "'$duracion'",
            $bloque,
            $idplanclases
        ], $query);
        
        debug_log("Consulta SQL para depuración (UPDATE): $debug_query");
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $error = "Error en la preparación de la consulta: " . $conn->error;
            debug_log($error);
            throw new Exception($error);
        }
        
        debug_log("Definiendo parámetros para el UPDATE: ssssssssssii");
        
        $stmt->bind_param("ssssssssssii", 
            $titulo, 
            $tipo, 
            $subtipo,
            $fecha,
            $inicio,
            $termino,
            $dia,
            $obligatorio,
            $evaluacion,
            $duracion,
            $bloque,
            $idplanclases
        );
        
    } else {
        debug_log("Modo inserción (nueva actividad)");
        
        // Inserción - generar semana automáticamente
        $semana = date('W', strtotime($fecha)) - date('W', strtotime(date('Y') . '-01-01')) + 1;
        if ($semana < 1) $semana = 1;
        
        debug_log("Semana calculada: $semana");
        
        $query = "INSERT INTO planclases_test 
                 (cursos_idcursos, pcl_Periodo, pcl_tituloActividad, pcl_TipoSesion, pcl_SubTipoSesion, 
                  pcl_Fecha, pcl_Inicio, pcl_Termino, dia, pcl_condicion, pcl_ActividadConEvaluacion, 
                  pcl_HorasPresenciales, pcl_Semana, Bloque, pcl_fechamodifica, pcl_usermodifica, 
                  pcl_FechaCreacion, pcl_Modalidad) 
                 VALUES 
                 (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'EditorClinico', NOW(), 'Sincrónico')";
        
        $periodo = date('Y') . (date('n') > 6 ? '2' : '1'); // Determinar período basado en el mes actual
        
        debug_log("Periodo calculado: $periodo");
        
        // Contar marcadores de posición para verificar
        $placeholder_count = substr_count($query, '?');
        debug_log("Número de marcadores de posición en la consulta: $placeholder_count");
        
        // Mostrar la consulta con valores reales para depuración
        $debug_query = str_replace([
            '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'
        ], [
            $cursos_idcursos,
            "'$periodo'",
            "'$titulo'", 
            "'$tipo'", 
            $subtipo ? "'$subtipo'" : "NULL",
            "'$fecha'",
            "'$inicio'",
            "'$termino'",
            "'$dia'",
            "'$obligatorio'",
            "'$evaluacion'",
            "'$duracion'",
            $semana,
            $bloque ? $bloque : "NULL"
        ], $query);
        
        debug_log("Consulta SQL para depuración (INSERT): $debug_query");
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $error = "Error en la preparación de la consulta: " . $conn->error;
            debug_log($error);
            throw new Exception($error);
        }
        
        // Verificación extra: analizar los tipos de datos y contar parámetros
        $paramTypes = "issssssssssii"; // 13 parámetros
        $paramCount = strlen($paramTypes);
        debug_log("Tipos de parámetros: $paramTypes (total: $paramCount)");
        debug_log("Número total de parámetros a pasar: 14");
        
        // Corregir la cadena de tipos para que coincida con el número de parámetros
        $paramTypes = "issssssssssisi"; // 14 parámetros
        debug_log("Tipos de parámetros actualizados: $paramTypes");
        
        $stmt->bind_param($paramTypes, 
            $cursos_idcursos,
            $periodo,
            $titulo, 
            $tipo, 
            $subtipo,
            $fecha,
            $inicio,
            $termino,
            $dia,
            $obligatorio,
            $evaluacion,
            $duracion,
            $semana,
            $bloque
        );
    }
    
    debug_log("Ejecutando consulta...");
    
    if (!$stmt->execute()) {
        $error = "Error al ejecutar la consulta: " . $stmt->error;
        debug_log($error);
        throw new Exception($error);
    }
    
    debug_log("Consulta ejecutada exitosamente");
    
    $responseData = [
        'success' => true,
        'message' => $idplanclases ? 'Actividad actualizada exitosamente' : 'Actividad creada exitosamente',
        'idplanclases' => $idplanclases ?: $conn->insert_id
    ];
    
    debug_log("Respuesta a enviar", $responseData);
    
    echo json_encode($responseData);
    
    $stmt->close();
    
} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => debug_log("Excepción capturada: " . $e->getMessage())
    ];
    
    debug_log("Respondiendo con error", $errorResponse);
    echo json_encode($errorResponse);
}

debug_log("Finalizando script");
$conn->close();
?>