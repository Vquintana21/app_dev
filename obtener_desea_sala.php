<?php

include("conexion.php");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'])) {
        throw new Exception('Acción no especificada');
    }
    
    switch ($data['action']) {
        case 'obtener_desea_sala':
            if (!isset($data['idPlanClase'])) {
                throw new Exception('ID de plan de clase no proporcionado');
            }
            
            $idPlanClase = (int)$data['idPlanClase'];
            
            // Obtener datos básicos de planclases
            $query = "SELECT pcl_DeseaSala, pcl_campus, pcl_nSalas 
                     FROM planclases 
                     WHERE idplanclases = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $idPlanClase);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('No se encontró la actividad');
            }
            
            $datos = $result->fetch_assoc();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'pcl_DeseaSala' => $datos['pcl_DeseaSala'] ?? '1', // Default 1 si es null
                'pcl_campus' => $datos['pcl_campus'],
                'pcl_nSalas' => $datos['pcl_nSalas']
            ]);
            break;
            
        case 'obtener_datos_solicitud_completos':
            if (!isset($data['idPlanClase'])) {
                throw new Exception('ID de plan de clase no proporcionado');
            }
            
            $idPlanClase = (int)$data['idPlanClase'];
            
            // Obtener datos básicos
            $queryBasicos = "SELECT p.pcl_campus, p.pcl_nSalas, p.pcl_DeseaSala,
                           (SELECT COUNT(*) FROM asignacion 
                            WHERE idplanclases = p.idplanclases 
                            AND idEstado = 3) as salas_asignadas
                           FROM planclases p 
                           WHERE p.idplanclases = ?";
            
            $stmtBasicos = $conn->prepare($queryBasicos);
            $stmtBasicos->bind_param("i", $idPlanClase);
            $stmtBasicos->execute();
            $resultBasicos = $stmtBasicos->get_result();
            
            if ($resultBasicos->num_rows === 0) {
                throw new Exception('No se encontró la actividad');
            }
            
            $datosBasicos = $resultBasicos->fetch_assoc();
            $stmtBasicos->close();
            
            // Obtener historial de observaciones (separado por timestamps)
            $queryHistorial = "SELECT Comentario, timestamp 
                             FROM asignacion 
                             WHERE idplanclases = ? 
                             AND Comentario IS NOT NULL 
                             AND Comentario != ''
                             ORDER BY timestamp DESC";
            
            $stmtHistorial = $conn->prepare($queryHistorial);
            $stmtHistorial->bind_param("i", $idPlanClase);
            $stmtHistorial->execute();
            $resultHistorial = $stmtHistorial->get_result();
            
            $historial = [];
            while ($row = $resultHistorial->fetch_assoc()) {
                // Separar comentarios si están concatenados con fechas
                $comentarios = explode("\n", $row['Comentario']);
                foreach ($comentarios as $comentario) {
                    $comentario = trim($comentario);
                    if (!empty($comentario)) {
                        // Verificar si el comentario tiene formato de fecha al inicio
                        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(.*)/', $comentario, $matches)) {
                            $historial[] = [
                                'fecha' => $matches[1],
                                'comentario' => trim($matches[2], ' -')
                            ];
                        } else {
                            // Si no tiene fecha, usar la del timestamp de la fila
                            $historial[] = [
                                'fecha' => $row['timestamp'] ?: 'Fecha no disponible',
                                'comentario' => $comentario
                            ];
                        }
                    }
                }
            }
            $stmtHistorial->close();
            
            // Remover duplicados y ordenar por fecha descendente
            $historialUnico = [];
            $comentariosVistos = [];
            
            foreach ($historial as $item) {
                $clave = $item['comentario'];
                if (!in_array($clave, $comentariosVistos)) {
                    $comentariosVistos[] = $clave;
                    $historialUnico[] = $item;
                }
            }
            
            // Ordenar por fecha descendente (más reciente primero)
            usort($historialUnico, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });
            
            echo json_encode([
                'success' => true,
                'pcl_campus' => $datosBasicos['pcl_campus'],
                'pcl_nSalas' => $datosBasicos['pcl_nSalas'],
                'pcl_DeseaSala' => $datosBasicos['pcl_DeseaSala'] ?? '1',
                'estado' => $datosBasicos['salas_asignadas'] > 0 ? 3 : 0,
                'historial_observaciones' => $historialUnico
            ]);
            break;
            
        default:
            throw new Exception('Acción no reconocida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>