<?php
// get_actividad_clinica_bloques.php
include("conexion.php");
header('Content-Type: application/json');

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID no proporcionado');
    }
    
    $idplanclases = (int)$_GET['id'];
    
    // Obtener la actividad principal
    $query = "SELECT * FROM planclases_test WHERE idplanclases = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idplanclases);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Actividad no encontrada');
    }
    
    $activity = $result->fetch_assoc();
    
    // Obtener todos los bloques asociados a esta actividad (mismo titulo, fecha y tipo)
    $queryBloques = "
        SELECT p.idplanclases, p.pcl_Inicio, p.pcl_Termino, b.bloque 
        FROM planclases_test p
        LEFT JOIN Bloques_ext b ON TIME(p.pcl_Inicio) = TIME(b.inicio) AND TIME(p.pcl_Termino) = TIME(b.termino)
        WHERE 
            p.pcl_tituloActividad = ? AND 
            p.pcl_Fecha = ? AND 
            p.pcl_TipoSesion = ? AND
            (p.idplanclases = ? OR (
                p.pcl_grupoactividad IS NOT NULL AND 
                p.pcl_grupoactividad = (SELECT pcl_grupoactividad FROM planclases_test WHERE idplanclases = ?)
            ))
    ";
    
    $stmt = $conn->prepare($queryBloques);
    $stmt->bind_param("sssii", 
        $activity['pcl_tituloActividad'], 
        $activity['pcl_Fecha'],
        $activity['pcl_TipoSesion'],
        $idplanclases,
        $idplanclases
    );
    $stmt->execute();
    $resultBloques = $stmt->get_result();
    
    $bloques = [];
    while ($bloque = $resultBloques->fetch_assoc()) {
        $bloques[] = $bloque;
    }
    
    echo json_encode([
        'success' => true,
        'activity' => $activity,
        'bloques' => $bloques
    ]);
    
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