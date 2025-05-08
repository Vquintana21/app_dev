<?php
include("conexion.php");
header('Content-Type: application/json');

if(isset($_POST['idProfesoresCurso'])) {
    $id = (int)$_POST['idProfesoresCurso'];
    $query = "UPDATE spre_profesorescurso SET Vigencia = '0' WHERE idProfesoresCurso = $id";
    
    if(mysqli_query($conexion3, $query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    
} else {
    echo json_encode(['status' => 'error']);
}
?>