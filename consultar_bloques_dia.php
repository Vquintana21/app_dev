<?php
// consultar_bloques_dia.php
header('Content-Type: application/json');
header('Content-type: text/html; charset=utf-8');
include("conexion.php");

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos válidos');
    }
    
    $idcurso = intval($input['idcurso']);
    $fecha = $input['fecha']; 
    $idplanclase_actual = intval($input['idplanclase_actual']);
    
    // Validar datos requeridos
    if (!$idcurso || !$fecha || !$idplanclase_actual) {
        throw new Exception('Faltan parámetros requeridos');
    }
    
    // Consulta SQL validada
    $query = "SELECT 
        p.idplanclases,
        p.pcl_TipoSesion,
        p.pcl_Inicio,
        p.pcl_Termino,
        p.Bloque,
        p.pcl_Fecha,
        p.pcl_nSalas,
        CASE 
            WHEN COUNT(a.idplanclases) > 0 AND MIN(a.idEstado) = 3 AND MAX(a.idEstado) = 3 THEN 'Asignado'
            WHEN COUNT(a.idplanclases) > 0 AND MAX(a.idEstado) IN (0,1,2) THEN 'Solicitado'
            WHEN COUNT(a.idplanclases) > 0 AND MIN(a.idEstado) = 4 THEN 'Liberado'
            ELSE 'Sin solicitar'
        END as estado_sala,
        COUNT(a.idplanclases) as salas_solicitadas
    FROM planclases p
    LEFT JOIN asignacion a ON p.idplanclases = a.idplanclases
    WHERE p.cursos_idcursos = ? 
      AND p.pcl_Fecha = ?
      AND p.pcl_TipoSesion IN (
          SELECT tipo_sesion 
          FROM pcl_TipoSesion 
          WHERE pedir_sala = 1 
            AND tipo_activo = 1
            AND tipo_sesion != 'Clase'
      )
      AND p.idplanclases != ?
    GROUP BY p.idplanclases, p.pcl_TipoSesion, p.pcl_Inicio, p.pcl_Termino, p.Bloque, p.pcl_Fecha, p.pcl_nSalas
    ORDER BY p.pcl_Inicio";
    
    // Preparar y ejecutar consulta
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("isi", $idcurso, $fecha, $idplanclase_actual);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $actividades = array();
    
    while ($row = $result->fetch_assoc()) {
        // Formatear datos para mejor legibilidad
        $row['pcl_Inicio_formateado'] = substr($row['pcl_Inicio'], 0, 5); // HH:MM
        $row['pcl_Termino_formateado'] = substr($row['pcl_Termino'], 0, 5); // HH:MM
        $row['bloque_numero'] = $row['Bloque']; // Mantener el bloque original
        
        $actividades[] = $row;
    }
    
    $stmt->close();
    
    // Preparar respuesta
    $response = array(
        'success' => true,
        'actividades' => $actividades,
        'total_actividades' => count($actividades),
        'fecha_consultada' => $fecha,
        'idcurso' => $idcurso,
        'debug' => array(
            'query_ejecutada' => true,
            'parametros' => array(
                'idcurso' => $idcurso,
                'fecha' => $fecha,
                'idplanclase_actual' => $idplanclase_actual
            )
        )
    );
    
    echo json_encode($response);

} catch (Exception $e) {
    // Manejo de errores
    $error_response = array(
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => array(
            'input_recibido' => $input,
            'timestamp' => date('Y-m-d H:i:s')
        )
    );
    
    echo json_encode($error_response);
}

// Cerrar conexión
if (isset($conn)) {
    $conn->close();
}
?>