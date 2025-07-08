<?php
// get_activity.php
include("conexion.php");
header('Content-Type: application/json');

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID no proporcionado');
    }
    
    $idplanclases = (int)$_GET['id'];
    
    $query = "SELECT * FROM planclases WHERE idplanclases = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idplanclases);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Actividad no encontrada');
    }
    
    $actividad = $result->fetch_assoc();
    echo json_encode($actividad);
    
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