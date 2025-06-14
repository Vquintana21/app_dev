<?php
// consultar_salas_disponibles.php
header('Content-Type: application/json');
header('Content-type: text/html; charset=utf-8');
include("conexion.php");

try {
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos válidos');
    }
    
    $alumnosPorSala = intval($input['alumnos_por_sala']);
    $campus = $input['campus'];
    $fecha = $input['fecha'];
    $horaInicio = $input['hora_inicio'];
    $horaTermino = $input['hora_termino'];
    
    // Validar datos requeridos
    if (!$alumnosPorSala || !$campus || !$fecha || !$horaInicio || !$horaTermino) {
        throw new Exception('Faltan parámetros requeridos');
    }
    
    // Consulta SQL para salas disponibles
    $query = "SELECT 
        s.idSala,
        s.sa_Nombre,
        s.sa_Capacidad,
        s.sa_UbicEdificio
    FROM sala_reserva s
    WHERE s.sa_Capacidad >= ?
      AND s.sa_UbicCampus = ?
      AND s.idSala NOT IN (
          SELECT DISTINCT r.re_idSala 
          FROM reserva r 
          WHERE r.re_FechaReserva = ?
            AND (
                (r.re_HoraReserva <= ? AND r.re_HoraTermino > ?) OR
                (r.re_HoraReserva < ? AND r.re_HoraTermino >= ?)
            )
      )
    ORDER BY s.sa_Capacidad ASC, s.sa_Nombre ASC";
    
    // Preparar y ejecutar consulta
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("issssss", $alumnosPorSala, $campus, $fecha, $horaInicio, $horaInicio, $horaTermino, $horaTermino);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $salas = array();
    
    while ($row = $result->fetch_assoc()) {
        $salas[] = array(
            'idSala' => $row['idSala'],
            'nombre' => $row['sa_Nombre'],
            'capacidad' => intval($row['sa_Capacidad'])
        );
    }
    
    $stmt->close();
    
    // Preparar respuesta
    echo json_encode(array(
        'success' => true,
        'salas' => $salas,
        'total_salas' => count($salas),
        'parametros' => array(
            'alumnos_por_sala' => $alumnosPorSala,
            'campus' => $campus,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_termino' => $horaTermino
        )
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

if (isset($conn)) {
    $conn->close();
}
?>