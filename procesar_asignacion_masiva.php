<?php

$input = file_get_contents('php://input');
error_log("Datos recibidos en procesar_asignacion_masiva.php: " . $input);
header('Content-Type: application/json');
include("conexion.php");

try {
    // Recibir datos JSON del cuerpo de la solicitud
    $datos = json_decode(file_get_contents('php://input'), true);
    
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
            
            // Validar que el docente pertenezca al curso
            $validarDocente = "SELECT COUNT(*) as existe FROM spre_profesorescurso 
                              WHERE rut = '$rutDocente' AND idcurso = $idCurso AND Vigencia = 1";
            $resultValidacion = mysqli_query($conn, $validarDocente);
            $filaValidacion = mysqli_fetch_assoc($resultValidacion);
            
            if ($filaValidacion['existe'] == 0) {
                continue; // El docente no pertenece al curso, pasamos al siguiente
            }
            
            // Verificar relación existente entre actividad y docente
            $verificarExistencia = "SELECT idDocenteClases, vigencia FROM docenteclases 
                                   WHERE idPlanClases = $idplanclases AND rutDocente = '$rutDocente'";
            $resultVerificacion = mysqli_query($conn, $verificarExistencia);
            
            if ($accion === 'asignar') {
                if (mysqli_num_rows($resultVerificacion) > 0) {
                    // La relación ya existe, actualizamos vigencia = 1 si es necesario
                    $filaExistente = mysqli_fetch_assoc($resultVerificacion);
                    if ($filaExistente['vigencia'] != 1) {
                        $query = "UPDATE docenteclases SET vigencia = 1, 
                                 fechaModificacion = NOW(), usuarioModificacion = 'asignacion_masiva' 
                                 WHERE idDocenteClases = " . $filaExistente['idDocenteClases'];
                        mysqli_query($conn, $query);
                        $operacionesRealizadas++;
                    }
                } else {
                    // La relación no existe, la creamos
                    
                    // Obtener la duración de la actividad para el campo horas
                    $queryHoras = "SELECT 
                                     TIME_TO_SEC(TIMEDIFF(pcl_Termino, pcl_Inicio))/3600 as duracion_horas
                                  FROM planclases 
                                  WHERE idplanclases = $idplanclases";
                    $resultHoras = mysqli_query($conn, $queryHoras);
                    $filaHoras = mysqli_fetch_assoc($resultHoras);
                    $horas = $filaHoras ? $filaHoras['duracion_horas'] : 0;
                    
                    // Obtener unidad académica del docente
                    $queryUnidad = "SELECT unidad_academica_docente FROM spre_profesorescurso 
                                   WHERE rut = '$rutDocente' AND idcurso = $idCurso AND Vigencia = 1 
                                   LIMIT 1";
                    $resultUnidad = mysqli_query($conexion3, $queryUnidad);
                    $unidadAcademica = '';
                    if ($rowUnidad = mysqli_fetch_assoc($resultUnidad)) {
                        $unidadAcademica = $rowUnidad['unidad_academica_docente'];
                    }
                    
                    $query = "INSERT INTO docenteclases 
                             (rutDocente, idPlanClases, idCurso, horas, unidadAcademica, vigencia, 
                             fechaModificacion, usuarioModificacion)
                             VALUES ('$rutDocente', $idplanclases, $idCurso, $horas, 
                             '$unidadAcademica', 1, NOW(), 'asignacion_masiva')";
                    mysqli_query($conn, $query);
                    $operacionesRealizadas++;
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
} else if ($accion === 'eliminar') {
                if (mysqli_num_rows($resultVerificacion) > 0) {
                    // La relación existe, la desactivamos (vigencia = 0)
                    $filaExistente = mysqli_fetch_assoc($resultVerificacion);
                    if ($filaExistente['vigencia'] == 1) {
                        $query = "UPDATE docenteclases SET vigencia = 0, 
                                 fechaModificacion = NOW(), usuarioModificacion = 'asignacion_masiva' 
                                 WHERE idDocenteClases = " . $filaExistente['idDocenteClases'];
                        mysqli_query($conn, $query);
                        $operacionesRealizadas++;
                    }
                }
}