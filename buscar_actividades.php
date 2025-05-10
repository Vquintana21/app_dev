<?php
header('Content-Type: application/json');
include("conexion.php");

try {
    // Obtener parámetros
    $idCurso = isset($_POST['idcurso']) ? (int)$_POST['idcurso'] : 0;
    $tipoActividad = isset($_POST['tipoActividad']) ? $_POST['tipoActividad'] : '';
    $diaSemana = isset($_POST['diaSemana']) ? $_POST['diaSemana'] : '';
    $subtipo = isset($_POST['subtipo']) ? $_POST['subtipo'] : '';
    $fechaInicio = isset($_POST['fechaInicio']) ? $_POST['fechaInicio'] : '';
    $fechaTermino = isset($_POST['fechaTermino']) ? $_POST['fechaTermino'] : '';
    $horaInicio = isset($_POST['horaInicio']) ? $_POST['horaInicio'] : '';
    $horaTermino = isset($_POST['horaTermino']) ? $_POST['horaTermino'] : '';
    
    // Validar ID del curso
    if ($idCurso <= 0) {
        throw new Exception('Se requiere un ID de curso válido');
    }
    
    // Construir consulta base
    $query = "SELECT idplanclases, pcl_tituloActividad, pcl_Fecha, DAYNAME(pcl_Fecha) AS dia_semana, pcl_Inicio, pcl_Termino, 
             pcl_TipoSesion, pcl_SubTipoSesion, dia
             FROM planclases 
             WHERE cursos_idcursos = ? 
             AND pcl_TipoSesion != '' 
             AND pcl_TipoSesion != 'Autoaprendizaje'
			 AND pcl_tituloActividad IS NOT NULL AND pcl_tituloActividad != ''";
    
    // Arreglo para parámetros
    $params = [$idCurso];
    $tipos = "i"; // i = integer (para idCurso)
    
    // Agregar filtros según corresponda
    if (!empty($tipoActividad)) {
        $query .= " AND pcl_TipoSesion = ?";
        $params[] = $tipoActividad;
        $tipos .= "s"; // s = string
    }
    
    if (!empty($diaSemana)) {
        $query .= " AND dia = ?";
        $params[] = $diaSemana;
        $tipos .= "s";
    }    
   
    
    if (!empty($fechaInicio)) {
        $query .= " AND pcl_Fecha >= ?";
        $params[] = $fechaInicio;
        $tipos .= "s";
    }
    
    if (!empty($fechaTermino)) {
        $query .= " AND pcl_Fecha <= ?";
        $params[] = $fechaTermino;
        $tipos .= "s";
    }
    
    if (!empty($horaInicio)) {
        $query .= " AND pcl_Inicio >= ?";
        $params[] = $horaInicio;
        $tipos .= "s";
    }
    
    if (!empty($horaTermino)) {
        $query .= " AND pcl_Termino <= ?";
        $params[] = $horaTermino;
        $tipos .= "s";
    }
    
    // Ordenar resultados
    $query .= " ORDER BY pcl_Fecha ASC, pcl_Inicio ASC";
    
    // Preparar y ejecutar consulta
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }
    
    // Bind parameters dinámicamente
    if (count($params) > 0) {
        $bindParams = array($tipos);
        for ($i = 0; $i < count($params); $i++) {
            $bindParams[] = &$params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    }
    
    // Ejecutar consulta
    if (!$stmt->execute()) {
        throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
    }
    
    // Obtener resultados
    $resultado = $stmt->get_result();
    $actividades = [];
    
    while ($fila = $resultado->fetch_assoc()) {
        $actividades[] = $fila;
    }
    
    // Devolver resultado exitoso
    echo json_encode([
        'success' => true,
        'actividades' => $actividades,
        'total' => count($actividades)
    ]);
    
} catch (Exception $e) {
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