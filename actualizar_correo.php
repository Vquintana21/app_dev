<?php
session_start();
include("conexion.php");

$rut = $_POST["rut"];
$correo = $_POST["correo"];
$periodo = date("Y");
$timestamp = date('Y-m-d H:i:s');

//Insertar
$insertar ="INSERT INTO correos_actualizados(id,rut,correo,periodo,timestamp) 
			VALUES (id,'$rut','$correo','$periodo','$timestamp')";	
											
$insertarQuery = mysqli_query($conn,$insertar);

header("location: https://dpi.med.uchile.cl/calendario/inicio.php");
					

?>