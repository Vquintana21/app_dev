<?php
include("conexion.php");
header('Content-Type: application/json');

// Activar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Array para almacenar mensajes de depuración
$debug_messages = [];

try {
    // Agregar mensaje de depuración
    $debug_messages[] = "Iniciando proceso de guardado de actividad";

    // Verificar que existe el ID
    if (!isset($_POST['idplanclases'])) {
        throw new Exception('ID no proporcionado');
    }

    $idplanclases = (int)$_POST['idplanclases'];
    $debug_messages[] = "ID de planclases: $idplanclases";

    // Obtener y sanitizar los valores
    $titulo = isset($_POST['activity-title']) ? mysqli_real_escape_string($conn, $_POST['activity-title']) : '';
    $tipo = isset($_POST['type']) ? mysqli_real_escape_string($conn, $_POST['type']) : '';
    $subtipo = isset($_POST['subtype']) ? mysqli_real_escape_string($conn, $_POST['subtype']) : '';
    $inicio = isset($_POST['start_time']) ? mysqli_real_escape_string($conn, $_POST['start_time']) : '';
    $termino = isset($_POST['end_time']) ? mysqli_real_escape_string($conn, $_POST['end_time']) : '';
    $condicion = isset($_POST['mandatory']) && $_POST['mandatory'] === 'true' ? "Obligatorio" : "Libre";
    $evaluacion = isset($_POST['is_evaluation']) && $_POST['is_evaluation'] === 'true' ? "S" : "N";

    $debug_messages[] = "Datos recibidos: tipo='$tipo', subtipo='$subtipo'";

    // Calcular duración
    $time1 = strtotime($inicio);
    $time2 = strtotime($termino);
    $difference = $time2 - $time1;

    // Convertir a formato HH:MM:SS
    $horas = floor($difference / 3600);
    $minutos = floor(($difference % 3600) / 60);
    $segundos = $difference % 60;

    $duracion = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
    $debug_messages[] = "Duración calculada: $duracion";

    // Iniciar transacción
    $conn->begin_transaction();
    $debug_messages[] = "Transacción iniciada";

    // PRIMERO: Obtener el tipo anterior ANTES de cualquier actualización
    $queryTipoAnterior = "SELECT pcl_TipoSesion FROM planclases WHERE idplanclases = ?";
    $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
    $stmtTipoAnterior->bind_param("i", $idplanclases);
    $stmtTipoAnterior->execute();
    $resultTipoAnterior = $stmtTipoAnterior->get_result();
    $tipoAnterior = $resultTipoAnterior->fetch_assoc()['pcl_TipoSesion'];
    $stmtTipoAnterior->close();

    $debug_messages[] = "Tipo anterior (antes del UPDATE): " . $tipoAnterior;
    $debug_messages[] = "Tipo nuevo (a guardar): " . $tipo;

    // SEGUNDO: Hacer el UPDATE de planclases
    $query = "UPDATE planclases SET 
                pcl_tituloActividad = ?, 
                pcl_TipoSesion = ?,
                pcl_SubTipoSesion = ?,
                pcl_Inicio = ?,
                pcl_Termino = ?,
                pcl_condicion = ?,
                pcl_ActividadConEvaluacion = ?,
                pcl_HorasPresenciales = ?
              WHERE idplanclases = ?";

    if (!$stmt = $conn->prepare($query)) {
        throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
    }

    if (!$stmt->bind_param("ssssssssi",
        $titulo,
        $tipo,
        $subtipo,
        $inicio,
        $termino,
        $condicion,
        $evaluacion,
        $duracion,
        $idplanclases
    )) {
        throw new Exception('Error en el bind_param: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error en la ejecución: ' . $stmt->error);
    }

    $debug_messages[] = "Actividad actualizada correctamente en planclases";

    // TERCERO: Después del UPDATE, verificar si necesitamos actualizar vigencia
    if ($tipoAnterior != $tipo) {
        // Verificar si el nuevo tipo permite docentes
        $queryPermiteDocentes = "SELECT docentes FROM pcl_TipoSesion WHERE tipo_sesion = ?";
        $stmtPermiteDocentes = $conn->prepare($queryPermiteDocentes);
        $stmtPermiteDocentes->bind_param("s", $tipo);
        $stmtPermiteDocentes->execute();
        $resultPermiteDocentes = $stmtPermiteDocentes->get_result();
        $permiteDocentes = $resultPermiteDocentes->fetch_assoc()['docentes'];
        $stmtPermiteDocentes->close();

        $debug_messages[] = "Tipo cambió de '$tipoAnterior' a '$tipo'";
        $debug_messages[] = "Nuevo tipo permite docentes: " . ($permiteDocentes ? "SÍ" : "NO");

        // Si cambiamos a un tipo que NO permite docentes
        if ($permiteDocentes == 0) {
            $queryUpdateVigencia = "UPDATE docenteclases 
                                   SET vigencia = 0, 
                                       fechaModificacion = NOW(),
                                       usuarioModificacion = 'sistema'
                                   WHERE idPlanClases = ? AND vigencia = 1";
            $stmtUpdateVigencia = $conn->prepare($queryUpdateVigencia);
            $stmtUpdateVigencia->bind_param("i", $idplanclases);

            $debug_messages[] = "Ejecutando UPDATE en docenteclases: $queryUpdateVigencia con idPlanClases=$idplanclases";

            if (!$stmtUpdateVigencia->execute()) {
                $debug_messages[] = "Error al actualizar vigencia de docentes: " . $stmtUpdateVigencia->error;
            } else {
                $docentesAfectados = $conn->affected_rows;
                $debug_messages[] = "Docentes desactivados: " . $docentesAfectados;
            }
            $stmtUpdateVigencia->close();
        } else {
            $debug_messages[] = "Cambio a tipo que permite docentes - Panel disponible para asignación";
        }
    } else {
        $debug_messages[] = "Tipo no cambió - No se modifica vigencia de docentes";
    }
	
	// ## SALAS ##
// Verificar si el tipo de actividad requiere sala consultando la tabla pcl_TipoSesion
$requiereSala = false;

// Primero intentamos con tipo y subtipo (si existe)
if (!empty($subtipo)) {
    $queryTipoSesion = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND Sub_tipo_sesion = ?";
    $stmtTipo = $conn->prepare($queryTipoSesion);
    $stmtTipo->bind_param("ss", $tipo, $subtipo);
    $stmtTipo->execute();
    $resultTipo = $stmtTipo->get_result();

    if ($row = $resultTipo->fetch_assoc()) {
        $requiereSala = ($row['pedir_sala'] == 1);
        $debug_messages[] = "Consulta con tipo y subtipo - pedir_sala: " . $row['pedir_sala'];
    } else {
        $debug_messages[] = "No se encontró coincidencia con tipo='$tipo' y subtipo='$subtipo'";
    }
    $stmtTipo->close();
}

// Si no se encontró con subtipo o no hay subtipo, buscar solo por tipo
if (!$requiereSala && empty($subtipo)) {
    $queryTipoSesion = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND (Sub_tipo_sesion IS NULL OR Sub_tipo_sesion = '')";
    $stmtTipo = $conn->prepare($queryTipoSesion);
    $stmtTipo->bind_param("s", $tipo);
    $stmtTipo->execute();
    $resultTipo = $stmtTipo->get_result();

    if ($row = $resultTipo->fetch_assoc()) {
        $requiereSala = ($row['pedir_sala'] == 1);
        $debug_messages[] = "Consulta solo con tipo - pedir_sala: " . $row['pedir_sala'];
    } else {
        $debug_messages[] = "No se encontró coincidencia con tipo='$tipo' sin subtipo";
    }
    $stmtTipo->close();
}

$debug_messages[] = "¿Requiere sala? " . ($requiereSala ? "SÍ" : "NO");
$debug_messages[] = "Tipo de actividad: " . $tipo;
$debug_messages[] = "Tipo anterior: " . $tipoAnterior;

// MANEJO DE SALAS SEGÚN REQUERIMIENTO - MANEJO DE LOS 9 CASOS
if ($tipo === 'Clase') {
    // CASOS 1, 4, 7: Destino es Clase - siempre crear asignación si no existe
    $debug_messages[] = "Destino es Clase - verificando si necesita crear asignación";
    
    // Verificar si ya existe una solicitud de sala
    $queryAsignacion = "SELECT COUNT(*) as count FROM asignacion_piloto WHERE idplanclases = ?";
    $stmtAsignacion = $conn->prepare($queryAsignacion);
    $stmtAsignacion->bind_param("i", $idplanclases);
    $stmtAsignacion->execute();
    $resultAsignacion = $stmtAsignacion->get_result();
    $asignacionExistente = $resultAsignacion->fetch_assoc()['count'];
    $stmtAsignacion->close();

    $debug_messages[] = "Asignaciones existentes: $asignacionExistente";

    // Si no existe asignación, crearla con estado 0 (solicitada)
    if ($asignacionExistente == 0) {
        $debug_messages[] = "Creando nueva asignación para Clase";

        // Obtener datos completos de planclases
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $idplanclases);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();

        if ($resultPlanclases->num_rows == 0) {
            $debug_messages[] = "ERROR: No se encontraron datos en planclases para ID $idplanclases";
            throw new Exception("No se encontraron datos en planclases");
        }

        $dataPlanclases = $resultPlanclases->fetch_assoc();
        $stmtPlanclases->close();

        // Obtener usuario actual o usar valor por defecto
        $fecha_actualizacion = date('Y-m-d H:i:s');

        // Crear registro en asignacion_piloto
        $queryInsertAsignacion = "INSERT INTO asignacion_piloto (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (
            ?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            'Solicitud generada automáticamente', 0, 'M', 0, ?, ?
        )";

        $stmtInsertAsignacion = $conn->prepare($queryInsertAsignacion);
        if (!$stmtInsertAsignacion) {
            $debug_messages[] = "ERROR preparando consulta de inserción: " . $conn->error;
            throw new Exception("Error preparando consulta de inserción: " . $conn->error);
        }

        $stmtInsertAsignacion->bind_param(
            "iisssssssisss",
            $idplanclases,
            $dataPlanclases['pcl_alumnos'],
            $dataPlanclases['pcl_TipoSesion'],
            $dataPlanclases['pcl_campus'],
            $dataPlanclases['pcl_Fecha'],
            $dataPlanclases['pcl_Inicio'],
            $dataPlanclases['pcl_Termino'],
            $dataPlanclases['cursos_idcursos'],
            $dataPlanclases['pcl_AsiCodigo'],
            $dataPlanclases['pcl_Seccion'],
            $dataPlanclases['pcl_AsiNombre'],
            $usuario,
            $fecha_actualizacion
        );

        if (!$stmtInsertAsignacion->execute()) {
            $debug_messages[] = "ERROR ejecutando inserción: " . $stmtInsertAsignacion->error;
            throw new Exception("Error ejecutando inserción: " . $stmtInsertAsignacion->error);
        }

        $debug_messages[] = "Asignación creada automáticamente para actividad tipo Clase";
        $stmtInsertAsignacion->close();
    } else {
        $debug_messages[] = "Ya existe asignación para esta Clase - manteniendo asignación existente";
    }
} 
else if ($requiereSala) {
    // CASOS 2, 5, 8: Destino requiere sala pero no es Clase
    $debug_messages[] = "Destino requiere sala pero no es Clase";
    
    if ($tipoAnterior === 'Clase') {
        // CASO 2: De Clase a otro tipo que requiere sala - eliminar asignación automática
        $debug_messages[] = "CASO 2: Cambio de Clase a otro tipo que requiere sala - eliminando asignación automática";
        
        $queryEliminar = "DELETE FROM asignacion_piloto WHERE idplanclases = ?";
        $stmtEliminar = $conn->prepare($queryEliminar);
        if ($stmtEliminar) {
            $stmtEliminar->bind_param("i", $idplanclases);
            $stmtEliminar->execute();
            $filasEliminadas = $stmtEliminar->affected_rows;
            $debug_messages[] = "Asignación automática eliminada - Usuario debe solicitarla manualmente ($filasEliminadas filas eliminadas)";
            $stmtEliminar->close();
        }
    } else {
        // CASOS 5 y 8: Verificar si el tipo anterior requería sala
        $anteriorRequiereSala = false;
        
        // Obtener información del tipo anterior
        $queryTipoAnterior = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ?";
        $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
        $stmtTipoAnterior->bind_param("s", $tipoAnterior);
        $stmtTipoAnterior->execute();
        $resultTipoAnterior = $stmtTipoAnterior->get_result();
        
        if ($rowAnterior = $resultTipoAnterior->fetch_assoc()) {
            $anteriorRequiereSala = ($rowAnterior['pedir_sala'] == 1);
        }
        $stmtTipoAnterior->close();
        
        if ($anteriorRequiereSala) {
            // CASO 5: De otro con sala a otro con sala - mantener asignación existente
            $debug_messages[] = "CASO 5: Cambio entre tipos que requieren sala - manteniendo asignación existente";
        } else {
            // CASO 8: De sin sala a otro con sala - no crear asignación
            $debug_messages[] = "CASO 8: De tipo sin sala a tipo con sala - usuario debe solicitar manualmente";
        }
    }
} 
else {
    // CASOS 3, 6, 9: Destino no requiere sala - eliminar cualquier asignación existente
    $debug_messages[] = "Destino no requiere sala - eliminando asignaciones existentes";
    
    $queryEliminar = "DELETE FROM asignacion_piloto WHERE idplanclases = ?";
    $stmtEliminar = $conn->prepare($queryEliminar);
    if ($stmtEliminar) {
        $stmtEliminar->bind_param("i", $idplanclases);
        $stmtEliminar->execute();
        $filasEliminadas = $stmtEliminar->affected_rows;
        
        if ($filasEliminadas > 0) {
            $debug_messages[] = "Asignaciones eliminadas: $filasEliminadas";
            
            if ($tipoAnterior === 'Clase') {
                $debug_messages[] = "CASO 3: De Clase a tipo sin sala";
            } else {
                // Verificar si el tipo anterior requería sala
                $anteriorRequiereSala = false;
                $queryTipoAnterior = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ?";
                $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
                $stmtTipoAnterior->bind_param("s", $tipoAnterior);
                $stmtTipoAnterior->execute();
                $resultTipoAnterior = $stmtTipoAnterior->get_result();
                
                if ($rowAnterior = $resultTipoAnterior->fetch_assoc()) {
                    $anteriorRequiereSala = ($rowAnterior['pedir_sala'] == 1);
                }
                $stmtTipoAnterior->close();
                
                if ($anteriorRequiereSala) {
                    $debug_messages[] = "CASO 6: De otro con sala a tipo sin sala";
                }
            }
        } else {
            $debug_messages[] = "CASO 9: Entre tipos sin sala - no hay asignaciones que eliminar";
        }
        
        $stmtEliminar->close();
    }
}

$conn->commit();
$debug_messages[] = "Transacción confirmada con éxito";

    echo json_encode([
        'success' => true,
        'message' => 'Actividad actualizada exitosamente',
        'requiere_sala' => $requiereSala,
        'es_clase' => ($tipo === 'Clase'),
        'debug' => $debug_messages
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Agregar mensaje de error
    $debug_messages[] = "ERROR: " . $e->getMessage();

    // Revertir cambios en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
        $debug_messages[] = "Transacción revertida";
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debug_messages
    ]);

    if (isset($conn)) {
        $conn->close();
    }
}
?>