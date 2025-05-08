<?php
include("conexion.php");
header('Content-Type: application/json');

if(isset($_POST['idProfesoresCurso']) && isset($_POST['idTipoParticipacion'])) {
    $idProfesoresCurso = (int)$_POST['idProfesoresCurso'];
    $idTipoParticipacion = (int)$_POST['idTipoParticipacion'];
    
    $query = "UPDATE spre_profesorescurso 
              SET idTipoParticipacion = $idTipoParticipacion 
              WHERE idProfesoresCurso = $idProfesoresCurso";
    
    if(mysqli_query($conexion3, $query)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Función actualizada exitosamente'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error al actualizar la función'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Datos incompletos'
    ]);
}
?>