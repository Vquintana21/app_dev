<?php
// eliminar_actividad.php

include("conexion.php");
header('Content-Type: application/json');
try {
    // Obtener datos como JSON
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['idplanclases']) || empty($data['idplanclases'])) {
        throw new Exception('ID no proporcionado');
    }
    
    $idplanclases = (int)$data['idplanclases'];
    
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
    
    // Realizar eliminación
    $query = "DELETE FROM planclases_test WHERE idplanclases = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idplanclases);
    $stmt->execute();
    
    // Verificar si se eliminó correctamente
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Actividad eliminada correctamente',
            'idplanclases' => $idplanclases
        ]);
    } else {
        throw new Exception('Error al eliminar la actividad');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
$conn->close();
?>