<?php
// eliminar_actividad_clinica_bloques.php
include("conexion.php");
header('Content-Type: application/json');

try {
    // Obtener datos como JSON
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['idplanclases']) || empty($data['idplanclases'])) {
        throw new Exception('ID no proporcionado');
    }
    
    $idplanclases = (int)$data['idplanclases'];
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar primero si la actividad existe y obtener su grupo
    $checkQuery = "SELECT idplanclases, pcl_grupoactividad, pcl_tituloActividad, pcl_Fecha, pcl_TipoSesion 
                  FROM planclases 
                  WHERE idplanclases = ?";
    
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $idplanclases);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        throw new Exception('Actividad no encontrada');
    }
    
    $checkStmt->bind_result($id, $grupoActividad, $titulo, $fecha, $tipoSesion);
    $checkStmt->fetch();
    $checkStmt->close();
    
    // Definir criterios de eliminación dependiendo de si tiene grupo o no
    if ($grupoActividad) {
        // Eliminar todas las actividades del mismo grupo
        $query = "DELETE FROM planclases 
                  WHERE pcl_grupoactividad = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $grupoActividad);
    } else {
        // Si no tiene grupo, eliminar solo por coincidencia de título, fecha y tipo
        $query = "DELETE FROM planclases 
                  WHERE pcl_tituloActividad = ? 
                  AND pcl_Fecha = ? 
                  AND pcl_TipoSesion = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $titulo, $fecha, $tipoSesion);
    }
    
    // Ejecutar eliminación
    $stmt->execute();
    $deletedCount = $stmt->affected_rows;
    
    // Verificar si se eliminó correctamente
    if ($deletedCount === 0) {
        throw new Exception('No se pudo eliminar la actividad');
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Actividad eliminada correctamente',
        'count' => $deletedCount
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    
    http_response_code(200); // Usar 200 para que el cliente pueda procesar la respuesta
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if ($conn) {
    $conn->close();
}
?>