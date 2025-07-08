<?php
//get_actividad_info.php
header('Content-Type: application/json');
include("conexion.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'ID no válido'
    ]);
    exit;
}

$idplanclases = intval($_GET['id']);

$query = "SELECT 
    pcl_Fecha,
    pcl_Inicio,
    pcl_Termino,
    pcl_TipoSesion,
    pcl_SubTipoSesion
FROM planclases 
WHERE idplanclases = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idplanclases);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Formatear fecha
    $fecha = date('d-m', strtotime($row['pcl_Fecha']));
    
    // Formatear horas
    $horaInicio = date('H:i', strtotime($row['pcl_Inicio']));
    $horaTermino = date('H:i', strtotime($row['pcl_Termino']));
    $horario = $horaInicio . ' - ' . $horaTermino;
    
    // Tipo de sesión completo
    $tipoSesion = $row['pcl_TipoSesion'];
    if (!empty($row['pcl_SubTipoSesion'])) {
        $tipoSesion .= ' - ' . $row['pcl_SubTipoSesion'];
    }
    
    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'horario' => $horario,
        'fechaHora' => $fecha . ' ' . $horario,
        'tipoSesion' => $tipoSesion
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Actividad no encontrada'
    ]);
}

$stmt->close();
$conn->close();
?>