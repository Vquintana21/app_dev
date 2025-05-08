<?php
// guardar_autoaprendizaje.php
include("conexion.php");
header('Content-Type: application/json');

try {
    // Verificar que existe el ID
    if (!isset($_POST['idplanclases'])) {
        throw new Exception('ID no proporcionado');
    }

    $idplanclases = (int)$_POST['idplanclases'];
    $activityTitle = isset($_POST['activity-title']) ? mysqli_real_escape_string($conn, $_POST['activity-title']) : '';
    $horasNoPresenciales = isset($_POST['horasNoPresenciales']) ? mysqli_real_escape_string($conn, $_POST['horasNoPresenciales']) : '00:00:00';

    // Query de actualización
    $query = "UPDATE planclases SET 
                pcl_tituloActividad = ?, 
                pcl_HorasNoPresenciales = ?
              WHERE idplanclases = ?";

    if (!$stmt = $conn->prepare($query)) {
        throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
    }

    if (!$stmt->bind_param("ssi", 
        $activityTitle, 
        $horasNoPresenciales,
        $idplanclases
    )) {
        throw new Exception('Error en el bind_param: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error en la ejecución: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Autoaprendizaje actualizado exitosamente'
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>