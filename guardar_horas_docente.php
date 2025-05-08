<?php
// guardar_horas_docente.php
include("conexion.php");
header('Content-Type: application/json');

if(!isset($_POST['idProfesoresCurso']) || !isset($_POST['horas'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan parámetros requeridos'
    ]);
    exit;
}

$idProfesoresCurso = (int)$_POST['idProfesoresCurso'];
$horas = (float)$_POST['horas'];

// Validar que las horas sean un número positivo
if($horas < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Las horas deben ser un valor positivo'
    ]);
    exit;
}

try {
    // Preparar la consulta
    $query = "UPDATE spre_profesorescurso 
              SET horas_clinicas = ? 
              WHERE idProfesoresCurso = ?";
    
    $stmt = $conexion3->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error en la preparación de la consulta: ' . $conexion3->error);
    }
    
    $stmt->bind_param("di", $horas, $idProfesoresCurso);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Horas guardadas correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conexion3->close();
?>