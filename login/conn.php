<?php 
//$mysqli_local = new mysqli('localhost', 'root', '', 'planificacion');
$mysqli_local = new mysqli('localhost', 'dpimeduchile', 'gD5T4)N1FDj1', 'dpimeduc_planificacion');
mysqli_set_charset($mysqli_local,"utf8");
if ($mysqli_local->connect_errno) {
  echo "Error: Fallo al conectarse a MySQL debido a: \n";
    echo "Errno: " . $mysqli_local->connect_errno . "\n";
    echo "Error: " . $mysqli_local->connect_error . "\n";
    exit;

}
?>