 <?php
header ('Content-type: text/html; charset=utf-8');
error_reporting(0);
include_once 'dbconfig.php';
session_start();
$rut = $_SESSION['sesion_idLogin'];
$name = $_SESSION['sesion_usuario']; 
$viene= array("Ã¡","Ã©","Ã","Ã³","Ãº");
$queda= array("Á","É","Í","Ó","Ú");
$nombre = str_replace($viene, $queda, $name);
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);

$res_pregrado = mysqli_query($con_plan,"SELECT * FROM `pm_EstudianteCarrera` where `rutEstudiante`='$rut_niv' and idEstadoEstudiante in (3,15) and TipoCarrera='Pregrado' and rutEstudiante not in ('017517415K','016784781K')");
$numpregrado = mysqli_num_rows($res_pregrado);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administrador de Gestión - Login</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
  
   <!-- Custom styles for this template -->
  <link href="css/small-business.css" rel="stylesheet">
   <style> 
footer {
  background-color: black;
  position: fixed;
  bottom: 0;
  width: 100%;
  height: 50px;
  color: white;
}

</style>
  
  <style>
    :root {
      --primary-color: #1e3a8a;
      --secondary-color: #f8f9fa;
      --accent-color: #e63946;
    }
    
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .login-container {
      margin-top: 2%;
      margin-bottom: 5%;
    }
    
    .login-card {
      border: none;
      border-radius: 10px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .login-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, #2a4fa8 100%);
      color: white;
      padding: 25px;
      text-align: center;
      position: relative;
    }
    
    .login-header h2 {
      font-weight: 300;
    }
    
    .login-header p {
      opacity: 0.8;
      margin-bottom: 0;
    }
    
    .login-body {
      padding: 40px;
      background-color: white;
    }
    
    .btn-login {
      background-color: var(--primary-color);
      border: none;
      border-radius: 50px;
      padding: 12px 30px;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      transition: all 0.3s ease;
      margin-top: 15px;
      margin-bottom: 15px;
    }
    
    .btn-login:hover {
      background-color: #152c69;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(30, 58, 138, 0.3);
    }
    
    .help-links {
      margin-top: 25px;
    }
    
    .help-links a {
      color: var(--primary-color);
      transition: all 0.2s ease;
      text-decoration: none;
      font-weight: 500;
    }
    
    .help-links a:hover {
      color: var(--accent-color);
    }
    
    .login-logo {
      position: absolute;
      top: 15px;
      left: 15px;
      height: 40px;
    }
    
    .divider {
      border-top: 1px solid rgba(0,0,0,0.1);
      margin: 25px 0;
    }
    
    .uchile-footer {
      font-size: 0.9rem;
      color: #6c757d;
      text-align: center;
      margin-top: 20px;
    }
    
    .uchile-footer img {
      height: 60px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
    
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">

  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
      <a class="navbar-brand" href="#"><h5><i class="fas fa-chalkboard-teacher"></i> Calendario Académico</h5></a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarResponsive">
        <ul class="navbar-nav ml-auto">
          <?php 
        	if(empty($rut)){
		?>  
           <li class="nav-item active">
            <a class="nav-link" href="login/login.php">
              <span class="sr-only"></span>
             <i class="fas fa-key"></i> 
             ingresar
            </a>
          </li>
          <?php 
        	}elseif(!empty($rut) && $numpregrado==0){
		?> 
          <li class="nav-item active">
            <a class="nav-link" href="sugerencias" target="_blank">
              <span class="sr-only">(current)</span>
              <i class="fas fa-exclamation-triangle"></i>
              Reportar Problema
            </a>
          </li>
          <?php 
        	}
		?> 
           <li class="nav-item active">
            <a class="nav-link" href="login/close.php">
              <span class="sr-only">(current)</span>
              <i class="fas fa-door-open"></i>
              Salir
            </a>
          </li>
           
        </ul>
      </div>
    </div>
  </nav>
  <div class="container login-container">

  <?php   
  
  	if(empty($rut)){
  	 ?> 
    <div class="row justify-content-center">
      <div class="col-lg-6 col-md-8">
        <div class="login-card">
          <div class="login-header">
            <h2><i class="fas fa-chalkboard-teacher mr-2"></i> Calendario Académico.</h2>
            <p>Facultad de Medicina - Universidad de Chile</p>
          </div>
          
          <div class="login-body">
            <h4 class="text-center mb-4">Bienvenido(a) al Calendario Académico</h4>
            
            <div class="alert alert-warning" role="alert">
			  Este sistema es exclusivo para <b>Académicos/as</b>.</br>
			  Si eres <b>Estudiante</b>, ingresa a este <a href="https://dpi.med.uchile.cl/estudiantes/" class="alert-link">ENLACE</a>. 
			</div>
            
            <div class="text-center">
              <p class="mb-4">Para continuar, inicie sesión con su cuenta institucional</p>
              
              <a href="login/login.php" class="btn btn-login btn-primary shadow">
                <i class="fas fa-sign-in-alt mr-2"></i> Ingresar con Cuenta Pasaporte
              </a>
              
              <div class="help-links">
                <p>
                  <a href="https://cuenta.uchile.cl/" target="_blank">
                    <i class="fas fa-user-circle mr-1"></i> Administrar Cuenta Pasaporte
                  </a>
                </p>
                <p>
                  <a href="https://cuenta.uchile.cl/solicitar-recuperar-cuenta" target="_blank">
                    <i class="fas fa-key mr-1"></i> Recuperar Contraseña
                  </a>
                </p>
                <p>
                  
                    <i class="fas fa-envelope mr-1"></i> Reportar Problemas de Acceso a <a href="mailto:dpi.med@uchile.cl">dpi.med@uchile.cl</a>
                  
                </p>
              </div>
            </div>
            
            <div class="uchile-footer">
              <a href="https://www.medicina.uchile.cl/" target="_blank">
                <img src="login/medicina-logo.png" alt="Logo UChile" class="img-fluid">
              </a>
              <p>Dirección Académica &copy; DPI-2025</p>
            </div>
          </div>
        </div>
      </div>
    </div>
	
	  <?php }else{?>
    
     <?php
                    
        	if($numpregrado>0){
  	 ?> 
  	 
  	  	<script>
					
						Swal.fire({
						type: 'error',
						title: '¡Lo sentimos!',
						html: 'Estimado Estudiante, el acceso es solo para Dirección Académica.'
						}).then(function() {
    window.location = "https://dpi.med.uchile.cl/gestion/login/close.php";
});
						
					</script>

  	 <?php }else{ ?>
  	 <script>
  	     //window.location.replace("https://dpi.med.uchile.cl/gestion/controlador.php");
  	     window.location.replace("https://dpi.med.uchile.cl/test/calendarios/inicio.php");
  	 </script>
	
  </div>
       
   <?php  } }?> 
    </div>
   <br>
<br>
  <!-- Footer -->
  <footer class="bg-dark">
    <div class="container">
      <p class="m-0 text-center text-white"><a href="https://www.medicina.uchile.cl/" target="_blank" style='color: white'>Facultad de Medicina - Universidad de Chile </a>&copy;DPI-<?php echo date("Y"); ?><br> </p>
   </div>
   <br>
    <!-- /.container -->
  </footer> 

  <!-- JavaScript Dependencies -->
  <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</body>
</html>