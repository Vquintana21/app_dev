<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include("conn.php");
require("acceso.php");
$rut = login_medicina();// GENERA LOGIN DE RICARDO
$rut = $_SESSION['sesion_idLogin']; 
if($rut!='')
{
	
	
		header ("location:https://dpi.med.uchile.cl/calendario/login.php");
	
}else{ 
?> 	
<html>
  <head>
   <meta charset="UTF-8">
      
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>
  </head>
  <body>
   
	<script>
						Swal.fire({
						icon: 'error',
						html:
    'Accceso denegado, por favor verifique el usuario y/o contraseña.<br>' +
    'Puedes recuperar tu cuenta pasaporte <a href="https://cuenta.uchile.cl/solicitar-recuperar-cuenta" target="_blank">AQUÍ</a> '
						}).then(function() {
    window.location = "close.php";
});
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
  </body>
</html>
<?php } ?>

