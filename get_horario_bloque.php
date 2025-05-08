<?php
include("conexion.php");

$bloque = $_GET['bloque'];
$extendido = $_GET['extendido'];

$query = "SELECT inicio, termino, inicio_ext, termino_ext 
          FROM Bloques_ext 
          WHERE bloque = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $bloque);
$stmt->execute();
$result = $stmt->get_result();
$horario = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($horario);
?>