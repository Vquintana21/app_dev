<?php
// eliminar_actividad_clinica.php

include("conexion.php");
header('Content-Type: application/json');

try {
    // Obtener datos como JSON
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['idplanclases']) || empty($data['idplanclases'])) {
        throw new Exception('ID no proporcionado');
    }
    
    $idplanclases = (int)$data['idplanclases'];
    
    // ✅ MEJORA: Iniciar transacción para integridad de datos
    $conn->begin_transaction();
    
    // Verificar primero si la actividad existe
    $checkQuery = "SELECT idplanclases FROM planclases_test WHERE idplanclases = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $idplanclases);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        throw new Exception('Actividad no encontrada');
    }
    $checkStmt->close();
    
    // ✅ MEJORA: ELIMINACIÓN EN CASCADA COMPLETA (Orden crítico)
    
    // 1. PRIMERO: Eliminar reservas de salas (liberar recursos físicos)
    $queryReservas = "DELETE FROM reserva_2 WHERE re_idRepeticion = ?";
    $stmtReservas = $conn->prepare($queryReservas);
    $stmtReservas->bind_param("i", $idplanclases);
    $stmtReservas->execute();
    $reservasEliminadas = $stmtReservas->affected_rows;
    $stmtReservas->close();
    
    // 2. SEGUNDO: Eliminar asignaciones de seguimiento
    $queryAsignaciones = "DELETE FROM asignacion_piloto WHERE idplanclases = ?";
    $stmtAsignaciones = $conn->prepare($queryAsignaciones);
    $stmtAsignaciones->bind_param("i", $idplanclases);
    $stmtAsignaciones->execute();
    $asignacionesEliminadas = $stmtAsignaciones->affected_rows;
    $stmtAsignaciones->close();
    
    // 3. TERCERO: Eliminar actividad principal
    $query = "DELETE FROM planclases_test WHERE idplanclases = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idplanclases);
    $stmt->execute();
    
    // Verificar si se eliminó correctamente
    if ($stmt->affected_rows > 0) {
        // ✅ MEJORA: Confirmar transacción
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Actividad eliminada correctamente con cascada completa',
            'idplanclases' => $idplanclases,
            'detalles' => [
                'reservas_eliminadas' => $reservasEliminadas,
                'asignaciones_eliminadas' => $asignacionesEliminadas,
                'actividad_eliminada' => true
            ]
        ]);
    } else {
        throw new Exception('Error al eliminar la actividad');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    // ✅ MEJORA: Revertir cambios en caso de error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>