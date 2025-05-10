<?php
header('Content-Type: application/json');
include("conexion.php");

try {
    // Recibir datos JSON del cuerpo de la solicitud
    $datos = json_decode(file_get_contents('php://input'), true);
    
    // Debug
    error_log("Datos recibidos: " . json_encode($datos));
    
    // Verificar que todos los campos necesarios estén presentes
    if (!isset($datos['idcurso']) || !isset($datos['actividades']) || !isset($datos['docentes']) || !isset($datos['accion'])) {
        throw new Exception('Faltan parámetros requeridos');
    }
    
    $idCurso = (int)$datos['idcurso'];
    $actividades = $datos['actividades'];
    $docentes = $datos['docentes'];
    $accion = $datos['accion']; // 'asignar' o 'eliminar'
    
    // Validar que las listas no estén vacías
    if (empty($actividades) || empty($docentes)) {
        throw new Exception('Se requiere al menos una actividad y un docente');
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Contador de operaciones realizadas
    $operacionesRealizadas = 0;
    
    // Recorrer todas las combinaciones de actividades y docentes
    foreach ($actividades as $idplanclases) {
        foreach ($docentes as $rutDocente) {
            // Sanitizar los datos
            $idplanclases = (int)$idplanclases;
            $rutDocente = mysqli_real_escape_string($conn, $rutDocente);
            
            // Verificar relación existente entre actividad y docente
            $verificarExistencia = "SELECT idDocenteClases, vigencia FROM docenteclases 
                                   WHERE idPlanClases = $idplanclases 
                                   AND rutDocente = '$rutDocente'
                                   AND idCurso = $idCurso";
            $resultVerificacion = mysqli_query($conn, $verificarExistencia);
            
            if (!$resultVerificacion) {
                throw new Exception("Error al verificar existencia: " . mysqli_error($conn));
            }
            
            if ($accion === 'asignar') {
                if (mysqli_num_rows($resultVerificacion) > 0) {
                    // La relación ya existe, actualizamos vigencia = 1
                    $filaExistente = mysqli_fetch_assoc($resultVerificacion);
                    $query = "UPDATE docenteclases 
                             SET vigencia = 1, 
                                 fechaModificacion = NOW(), 
                                 usuarioModificacion = 'asignacion_masiva' 
                             WHERE idDocenteClases = " . $filaExistente['idDocenteClases'];
                    
                    if (mysqli_query($conn, $query)) {
                        $operacionesRealizadas++;
                    } else {
                        throw new Exception("Error al actualizar: " . mysqli_error($conn));
                    }
                    
                } else {
                    // La relación no existe, la creamos
                    
                    // Obtener la duración de la actividad
                    $queryHoras = "SELECT 
                                     TIME_TO_SEC(TIMEDIFF(pcl_Termino, pcl_Inicio))/3600 as duracion_horas
                                  FROM planclases 
                                  WHERE idplanclases = $idplanclases";
                    $resultHoras = mysqli_query($conn, $queryHoras);
                    $filaHoras = mysqli_fetch_assoc($resultHoras);
                    $horas = $filaHoras ? $filaHoras['duracion_horas'] : 0;
                    
                    $query = "INSERT INTO docenteclases 
                             (rutDocente, idPlanClases, idCurso, horas, vigencia, 
                             fechaModificacion, usuarioModificacion, unidadAcademica)
                             VALUES ('$rutDocente', $idplanclases, $idCurso, $horas, 1, 
                             NOW(), 'asignacion_masiva', '')";
                    
                    if (mysqli_query($conn, $query)) {
                        $operacionesRealizadas++;
                    } else {
                        throw new Exception("Error al insertar: " . mysqli_error($conn));
                    }
                }
                
            } else if ($accion === 'eliminar') {
                if (mysqli_num_rows($resultVerificacion) > 0) {
                    // La relación existe, actualizamos vigencia = 0
                    $filaExistente = mysqli_fetch_assoc($resultVerificacion);
                    $query = "UPDATE docenteclases 
                             SET vigencia = 0, 
                                 fechaModificacion = NOW(), 
                                 usuarioModificacion = 'asignacion_masiva' 
                             WHERE idDocenteClases = " . $filaExistente['idDocenteClases'];
                    
                    if (mysqli_query($conn, $query)) {
                        $operacionesRealizadas++;
                    } else {
                        throw new Exception("Error al actualizar: " . mysqli_error($conn));
                    }
                }
            }
        }
    }
    
    // Confirmar la transacción
    $conn->commit();
    
    // Devolver resultado exitoso
    echo json_encode([
        'success' => true,
        'message' => ($accion === 'asignar' ? 'Asignación' : 'Eliminación') . ' masiva completada correctamente',
        'operaciones' => $operacionesRealizadas
    ]);
    
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Devolver respuesta de error
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// Cerrar conexión
if (isset($conn)) {
    $conn->close();
}
?>