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
    
    if ($accion === 'asignar') {
        // PASO 1: Primero poner vigencia=0 a TODOS los docentes de las actividades seleccionadas
       // foreach ($actividades as $idplanclases) {
       //     $idplanclases = (int)$idplanclases;
       //     
       //     $queryDesactivar = "UPDATE docenteclases_copy 
       //                        SET vigencia = 0,
       //                            fechaModificacion = NOW(),
       //                            usuarioModificacion = 'asignacion_masiva'
       //                        WHERE idPlanClases = $idplanclases
       //                        AND idCurso = $idCurso";
       //     
       //     if (!mysqli_query($conn, $queryDesactivar)) {
       //         throw new Exception("Error al desactivar docentes existentes: " . mysqli_error($conn));
       //     }
       // }
        
        // PASO 2: Ahora asignar SOLO los docentes seleccionados
        foreach ($actividades as $idplanclases) {
            foreach ($docentes as $rutDocente) {
                $idplanclases = (int)$idplanclases;
                $rutDocente = mysqli_real_escape_string($conn, $rutDocente);
                
                // Verificar si ya existe el registro
                $verificarExistencia = "SELECT idDocenteClases FROM docenteclases_copy 
                                       WHERE idPlanClases = $idplanclases 
                                       AND rutDocente = '$rutDocente'
                                       AND idCurso = $idCurso";
                $resultVerificacion = mysqli_query($conn, $verificarExistencia);
                
                if (mysqli_num_rows($resultVerificacion) > 0) {
                    // El registro existe, actualizarlo a vigencia=1
                    $filaExistente = mysqli_fetch_assoc($resultVerificacion);
                    $query = "UPDATE docenteclases_copy 
                             SET vigencia = 1, 
                                 fechaModificacion = NOW(), 
                                 usuarioModificacion = 'asignacion_masiva' 
                             WHERE idDocenteClases = " . $filaExistente['idDocenteClases'];
                } else {
                    // El registro no existe, crearlo con vigencia=1
                    // Obtener la duración de la actividad
                    $queryHoras = "SELECT 
                                     TIME_TO_SEC(TIMEDIFF(pcl_Termino, pcl_Inicio))/3600 as duracion_horas
                                  FROM a_planclases 
                                  WHERE idplanclases = $idplanclases";
                    $resultHoras = mysqli_query($conn, $queryHoras);
                    $filaHoras = mysqli_fetch_assoc($resultHoras);
                    $horas = $filaHoras ? $filaHoras['duracion_horas'] : 0;
                    
                    $query = "INSERT INTO docenteclases_copy 
                             (rutDocente, idPlanClases, idCurso, horas, vigencia, 
                             fechaModificacion, usuarioModificacion, unidadAcademica)
                             VALUES ('$rutDocente', $idplanclases, $idCurso, $horas, 1, 
                             NOW(), 'asignacion_masiva', '')";
                }
                
                if (mysqli_query($conn, $query)) {
                    $operacionesRealizadas++;
                } else {
                    throw new Exception("Error al asignar docente: " . mysqli_error($conn));
                }
            }
        }
        
    } else if ($accion === 'eliminar') {
        // Para eliminar, solo poner vigencia=0 a los docentes seleccionados
        foreach ($actividades as $idplanclases) {
            foreach ($docentes as $rutDocente) {
                $idplanclases = (int)$idplanclases;
                $rutDocente = mysqli_real_escape_string($conn, $rutDocente);
                
                $query = "UPDATE docenteclases_copy 
                         SET vigencia = 0, 
                             fechaModificacion = NOW(), 
                             usuarioModificacion = 'asignacion_masiva' 
                         WHERE idPlanClases = $idplanclases 
                         AND rutDocente = '$rutDocente'
                         AND idCurso = $idCurso";
                
                if (mysqli_query($conn, $query)) {
                    $operacionesRealizadas++;
                } else {
                    throw new Exception("Error al eliminar docente: " . mysqli_error($conn));
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