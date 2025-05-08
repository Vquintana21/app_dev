<?php
// guardar_actividad_clinica_bloques.php
include("conexion.php");
header('Content-Type: application/json');

try {
    // Validar que existan los campos mínimos necesarios
    $requiredFields = ['activity-title', 'type', 'date', 'dia', 'cursos_idcursos'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // Validar que haya al menos un bloque seleccionado
    if (!isset($_POST['bloques']) || empty($_POST['bloques'])) {
        throw new Exception("Debe seleccionar al menos un bloque horario");
    }
    
    // Obtener y sanitizar los valores comunes
    $idplanclases = isset($_POST['idplanclases']) && $_POST['idplanclases'] != '0' 
        ? (int)$_POST['idplanclases'] 
        : null;
    
    $cursos_idcursos = (int)$_POST['cursos_idcursos'];
    $titulo = mysqli_real_escape_string($conn, $_POST['activity-title']);
    $tipo = mysqli_real_escape_string($conn, $_POST['type']);
    $subtipo = isset($_POST['subtype']) ? mysqli_real_escape_string($conn, $_POST['subtype']) : null;
    $fecha = mysqli_real_escape_string($conn, $_POST['date']);
    $dia = mysqli_real_escape_string($conn, $_POST['dia']);
    $obligatorio = $_POST['pcl_condicion'] === 'Obligatorio' ? 'Obligatorio' : 'Libre';
    $evaluacion = $_POST['pcl_ActividadConEvaluacion'] === 'S' ? 'S' : 'N';
    
    // Obtener información de bloques horarios
    $bloquesSeleccionados = $_POST['bloques'];
    $bloquesExistentes = isset($_POST['bloquesActividad']) ? json_decode($_POST['bloquesActividad'], true) : [];
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Generar identificador de grupo si es una actividad nueva
    $grupoActividad = null;
    if ($idplanclases === null) {
        $grupoActividad = uniqid('grupo_', true);
    } else {
        // Obtener el grupo de la actividad principal (si existe)
        $queryGrupo = "SELECT pcl_grupoactividad FROM planclases_test WHERE idplanclases = ?";
        $stmt = $conn->prepare($queryGrupo);
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        $resultGrupo = $stmt->get_result();
        if ($resultGrupo->num_rows > 0) {
            $grupoActividad = $resultGrupo->fetch_assoc()['pcl_grupoactividad'];
            if (empty($grupoActividad)) {
                $grupoActividad = uniqid('grupo_', true);
                
                // Actualizar el grupo en la actividad principal
                $updateGrupo = "UPDATE planclases_test SET pcl_grupoactividad = ? WHERE idplanclases = ?";
                $stmt = $conn->prepare($updateGrupo);
                $stmt->bind_param("si", $grupoActividad, $idplanclases);
                $stmt->execute();
            }
        }
    }
    
    // Procesar cada bloque seleccionado
    $insertedCount = 0;
    $updatedCount = 0;
    
    foreach ($bloquesSeleccionados as $idBloque) {
        // Obtener horarios del bloque
        $queryBloque = "SELECT inicio, termino FROM Bloques_ext WHERE bloque = ?";
        $stmt = $conn->prepare($queryBloque);
        $stmt->bind_param("i", $idBloque);
        $stmt->execute();
        $resultBloque = $stmt->get_result();
        
        if ($resultBloque->num_rows === 0) {
            throw new Exception("Bloque no encontrado: $idBloque");
        }
        
        $bloque = $resultBloque->fetch_assoc();
        $inicio = $bloque['inicio'];
        $termino = $bloque['termino'];
        
        // Calcular duración en formato HH:MM:SS
        $time1 = strtotime($inicio);
        $time2 = strtotime($termino);
        $difference = $time2 - $time1;
        $horas = floor($difference / 3600);
        $minutos = floor(($difference % 3600) / 60);
        $segundos = $difference % 60;
        $duracion = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
        
        // Verificar si este bloque ya existe para esta actividad
        $bloquePlanClasesId = isset($bloquesExistentes[$idBloque]) ? $bloquesExistentes[$idBloque] : null;
        
        if ($bloquePlanClasesId) {
            // Actualizar bloque existente
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
                      pcl_grupoactividad = ?,
                      pcl_fechamodifica = NOW(),
                      pcl_usermodifica = 'EditorClinico'
                    WHERE idplanclases = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssssssssi", 
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
                $grupoActividad,
                $bloquePlanClasesId
            );
            $stmt->execute();
            $updatedCount++;
            
            // Quitar este ID del array de bloques existentes
            if (isset($bloquesExistentes[$idBloque])) {
                unset($bloquesExistentes[$idBloque]);
            }
        } else {
            // Insertar nuevo bloque
            // Generar semana automáticamente
            $semana = date('W', strtotime($fecha)) - date('W', strtotime(date('Y') . '-01-01')) + 1;
            if ($semana < 1) $semana = 1;
            
            $query = "INSERT INTO planclases_test 
                     (cursos_idcursos, pcl_Periodo, pcl_tituloActividad, pcl_TipoSesion, pcl_SubTipoSesion, 
                      pcl_Fecha, pcl_Inicio, pcl_Termino, dia, pcl_condicion, pcl_ActividadConEvaluacion, 
                      pcl_HorasPresenciales, pcl_Semana, pcl_fechamodifica, pcl_usermodifica, 
                      pcl_FechaCreacion, pcl_Modalidad, pcl_grupoactividad, Bloque) 
                     VALUES 
                     (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'EditorClinico', NOW(), 'Sincrónico', ?, ?)";
                     
            $periodo = date('Y') . (date('n') > 6 ? '2' : '1'); // Determinar período basado en el mes actual
            $bloqueStr = 'B' . $idBloque; // Formato: B1, B2, etc.
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issssssssssisss", 
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
                $grupoActividad,
                $bloqueStr
            );
            $stmt->execute();
            $insertedCount++;
        }
    }
    
    // Eliminar bloques que ya no están seleccionados
    foreach ($bloquesExistentes as $bloque => $idActividad) {
        $query = "DELETE FROM planclases_test WHERE idplanclases = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $idActividad);
        $stmt->execute();
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Actividad guardada exitosamente',
        'stats' => [
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'deleted' => count($bloquesExistentes)
        ]
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    http_response_code(200); // Usar 200 para que el cliente pueda procesar la respuesta
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if ($conn) {
    $conn->close();
}
?>