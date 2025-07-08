<?php
include("conexion.php");
function obtenerDatosMultiplesSecciones($codigoCurso, $periodo, $conn) {
	 global $conn, $conexion3;
    try {
        $stmt = $conexion3->prepare("SELECT COUNT(*) as total_secciones, 
                                           SUM(Cupo) as cupo_total,
                                           GROUP_CONCAT(CONCAT(Seccion, ':', Cupo) SEPARATOR '|') as secciones_detalle
                                   FROM spre_cursos 
                                   WHERE CodigoCurso = ? AND idperiodo = ?");
        
        if (!$stmt) {
            error_log("Error preparando consulta múltiples secciones: " . $conexion3->error);
            return array('total_secciones' => 1, 'cupo_total' => 0, 'secciones' => array());
        }
        
        $stmt->bind_param("ss", $codigoCurso, $periodo);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        // Validar datos
        $totalSecciones = isset($data['total_secciones']) ? (int)$data['total_secciones'] : 1;
        $cupoTotal = isset($data['cupo_total']) ? (int)$data['cupo_total'] : 0;
        
        // Procesar detalles de secciones
        $seccionesArray = array();
        if (isset($data['secciones_detalle']) && !empty($data['secciones_detalle'])) {
            $secciones = explode('|', $data['secciones_detalle']);
            foreach ($secciones as $seccion) {
                $partes = explode(':', $seccion);
                if (count($partes) == 2) {
                    $seccionesArray[] = array(
                        'seccion' => $partes[0],
                        'cupo' => (int)$partes[1]
                    );
                }
            }
        }
        
        return array(
            'total_secciones' => $totalSecciones,
            'cupo_total' => $cupoTotal,
            'secciones' => $seccionesArray
        );
        
    } catch (Exception $e) {
        error_log("Error en obtenerDatosMultiplesSecciones: " . $e->getMessage());
        return array('total_secciones' => 1, 'cupo_total' => 0, 'secciones' => array());
    }
}

/**
 * Calcula el número real de alumnos considerando si se juntan secciones
 * 
 * @param int $idplanclases - ID de la actividad
 * @param mysqli $conn - Conexión a base de datos principal
 * @param string $tipoCurso - 'regular' o 'clinico'
 * @return int - Número de alumnos calculado
 */
function calcularAlumnosReales($idplanclases, $conn, $tipoCurso = 'regular') {
    try {
        // Determinar tabla según tipo de curso
        $tablaPlanclases = ($tipoCurso === 'clinico') ? 'planclases' : 'planclases';
        
        // Obtener datos de planclases
        $stmt = $conn->prepare("SELECT pcl_AulaDescripcion, pcl_alumnos, cursos_idcursos, pcl_AsiCodigo, pcl_Periodo 
                               FROM $tablaPlanclases 
                               WHERE idplanclases = ?");
        
        if (!$stmt) {
            error_log("Error preparando consulta planclases: " . $conn->error);
            return 0;
        }
        
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        $result = $stmt->get_result();
        $planData = $result->fetch_assoc();
        $stmt->close();
        
        if (!$planData) {
            error_log("No se encontró planclase con ID: $idplanclases");
            return 0;
        }
        
        // Si NO junta secciones, usar cupo individual
        if (!isset($planData['pcl_AulaDescripcion']) || $planData['pcl_AulaDescripcion'] !== 'S') {
            return (int)$planData['pcl_alumnos'];
        }
        
        // Si junta secciones, calcular cupo total
        $codigoCurso = $planData['pcl_AsiCodigo'];
        $periodo = $planData['pcl_Periodo'];
        
        $datosMultiples = obtenerDatosMultiplesSecciones($codigoCurso, $periodo, $conn);
        
        // Log para debugging
        error_log("calcularAlumnosReales - ID: $idplanclases, Tipo: $tipoCurso, Junta: SÍ, Cupo individual: {$planData['pcl_alumnos']}, Cupo total: {$datosMultiples['cupo_total']}");
        
        return $datosMultiples['cupo_total'];
        
    } catch (Exception $e) {
        error_log("Error en calcularAlumnosReales: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtiene el período académico de un curso
 * 
 * @param int $idCurso - ID del curso
 * @param mysqli $conn - Conexión a base de datos
 * @return string - Período académico
 */
function obtenerPeriodoCurso($idCurso, $conexion3) {
    try {
        $stmt = $conexion3->prepare("SELECT idperiodo FROM spre_cursos WHERE idCurso = ?");
        
        if (!$stmt) {
            return '20251'; // Período por defecto
        }
        
        $stmt->bind_param("i", $idCurso);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return isset($data['idperiodo']) ? $data['idperiodo'] : '20251';
        
    } catch (Exception $e) {
        error_log("Error en obtenerPeriodoCurso: " . $e->getMessage());
        return '20251';
    }
}

/**
 * Verifica si un curso tiene múltiples secciones
 * 
 * @param string $codigoCurso - Código del curso
 * @param string $periodo - Período académico
 * @param mysqli $conn - Conexión a base de datos
 * @return bool - true si tiene múltiples secciones
 */
function tieneMultiplesSecciones($codigoCurso, $periodo, $conn) {
    $datos = obtenerDatosMultiplesSecciones($codigoCurso, $periodo, $conn);
    return $datos['total_secciones'] > 1;
}

/**
 * Calcula alumnos por sala cuando se juntan secciones
 * 
 * @param int $cupoTotal - Total de alumnos de todas las secciones
 * @param int $numeroSalas - Número de salas solicitadas
 * @return int - Alumnos por sala (redondeado hacia arriba)
 */
function calcularAlumnosPorSala($cupoTotal, $numeroSalas) {
    if ($numeroSalas <= 0) {
        return $cupoTotal;
    }
    
    return (int)ceil($cupoTotal / $numeroSalas);
}

/**
 * Actualiza el campo pcl_AulaDescripcion en planclases
 * 
 * @param int $idplanclases - ID de la actividad
 * @param bool $juntarSecciones - true para juntar, false para no juntar
 * @param mysqli $conn - Conexión a base de datos
 * @param string $tipoCurso - 'regular' o 'clinico'
 * @return bool - true si se actualizó correctamente
 */
function actualizarPclAulaDescripcion($idplanclases, $juntarSecciones, $conn, $tipoCurso = 'regular') {
    try {
        $tablaPlanclases = ($tipoCurso === 'clinico') ? 'planclases' : 'planclases';
        $valor = $juntarSecciones ? 'S' : 'N';
        
        $stmt = $conn->prepare("UPDATE $tablaPlanclases SET pcl_AulaDescripcion = ? WHERE idplanclases = ?");
        
        if (!$stmt) {
            error_log("Error preparando actualización pcl_AulaDescripcion: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("si", $valor, $idplanclases);
        $resultado = $stmt->execute();
        $stmt->close();
        
        // Log para debugging
        $accion = $juntarSecciones ? 'ACTIVAR' : 'DESACTIVAR';
        error_log("actualizarPclAulaDescripcion - ID: $idplanclases, Acción: $accion, Tipo: $tipoCurso, Resultado: " . ($resultado ? 'OK' : 'ERROR'));
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error en actualizarPclAulaDescripcion: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el estado actual de pcl_AulaDescripcion
 * 
 * @param int $idplanclases - ID de la actividad
 * @param mysqli $conn - Conexión a base de datos
 * @param string $tipoCurso - 'regular' o 'clinico'
 * @return bool - true si está marcado para juntar secciones
 */
function obtenerEstadoJuntarSecciones($idplanclases, $conn, $tipoCurso = 'regular') {
    try {
        $tablaPlanclases = ($tipoCurso === 'clinico') ? 'planclases' : 'planclases';
        
        $stmt = $conn->prepare("SELECT pcl_AulaDescripcion FROM $tablaPlanclases WHERE idplanclases = ?");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return isset($data['pcl_AulaDescripcion']) && $data['pcl_AulaDescripcion'] === 'S';
        
    } catch (Exception $e) {
        error_log("Error en obtenerEstadoJuntarSecciones: " . $e->getMessage());
        return false;
    }
}
?>