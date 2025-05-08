<!DOCTYPE html>
<html lang="en">
<?php
session_start(); 
include("conexion.php");
$rut = "0102388801";

$spre_personas = "SELECT * FROM spre_personas WHERE Rut ='$rut' ";
$spre_personasQ = mysqli_query($conexion3,$spre_personas);

$fila_personas = mysqli_fetch_assoc($spre_personasQ);

$nombreFuncionario = utf8_encode($fila_personas["Nombres"]." ".$fila_personas["Paterno"]." ".$fila_personas["Materno"]);
$nombreFuncionario2 = utf8_encode($fila_personas["Nombres"]." ".$fila_personas["Paterno"]);

function InfoDocenteUcampus($rut){
	
	$rut_def = ltrim($rut, "0");
	$cad = substr ($rut_def, 0, -1);

	$url = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/personas?rut='.$cad;

	//SE INICIA CURL
	$ch = curl_init($url);

	//PARÁMETROS
	$parametros = "rut=$rut";

	//MAXIMO TIEMPO DE ESPERA DE RESPUESTA DEL SERVIDOR
	curl_setopt($ch, CURLOPT_TIMEOUT, 20); 

	//RESPUESTA DEL SERVICIO WEB
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	//EJECUTAMOS LA PETICIÓN
	$resultado = curl_exec($ch);

	//CERRAR 
	curl_close($ch);
		
	$array_cursos = json_decode($resultado);

	if($array_cursos != NULL){

		$foto = $array_cursos->i;
			
	}else{
		
		$foto = "../../undraw_profile.svg"; 
	}

	return $foto; 


}

$foto_docente = InfoDocenteUcampus($rut);

?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Académico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	
	 <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
  
 <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
  <!-- CSS personalizado -->
  <link href="estilo.css" rel="stylesheet">
  
  <script src="docentes-handler.js"></script>
    
</head>
<body>

 <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
      <a href="planificacion.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="">
        <span class="d-none d-lg-block">Calendario Académico</span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>
    
    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item d-block d-lg-none">
          <a class="nav-link nav-icon search-bar-toggle " href="#">
            <i class="bi bi-search"></i>
          </a>
        </li>
        <li class="nav-item dropdown pe-3">
		<?php $foto = InfoDocenteUcampus($rut); ?>
          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="<?php echo $foto; ?>" alt="Profile" class="rounded-circle">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $funcionario; ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo $funcionario; ?></h6>
              <span>Editor </span>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center text-danger" href="#">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </nav>
  </header>
  
    <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link " href="inicio.php">
          <i class="bi bi-grid"></i>
          <span>Inicio</span>
        </a>
      </li>
	   
    </ul>
  </aside>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Data Ucampus</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="gestion.php">Home</a></li>
          <li class="breadcrumb-item">Data Ucampus</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
	<div class="card">
		<div class="card-body">
		<h5 class="card-title">Estudiantes del curso 
		 <span class="badge bg-info text-white"> <?php echo $_GET["codigo"]; ?>-<?php echo $_GET["seccion"]; ?></span> 
		 <span class="badge bg-secondary text-white"> <?php echo $_GET["periodo"]; ?></span>
		 </h5>
			<table class="table datatable">
				<thead>
				  <tr>
					<th><b>N°</b></th>
					<th></th>
					<th>Nombre</th>
					<th>Estado</th>
					<th>Nota final</th>
				  </tr>
				</thead>
				<tbody>
				<?php
					$rut_def = ltrim($rut, "0");
					$cad = substr ($rut_def, 0, -1);

					$url = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/cursos_dictados?rut='.$cad.'&periodo='.$_GET["periodo"].'';

					//SE INICIA CURL
					$ch = curl_init($url);

					//PARÁMETROS
					$parametros = "rut=$rut";

					//MAXIMO TIEMPO DE ESPERA DE RESPUESTA DEL SERVIDOR
					curl_setopt($ch, CURLOPT_TIMEOUT, 20); 

					//RESPUESTA DEL SERVICIO WEB
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

					//EJECUTAMOS LA PETICIÓN
					$resultado = curl_exec($ch);

					//CERRAR 
					curl_close($ch);

					$array_cursos = json_decode($resultado);

					
					  for($i=0; $i< count($array_cursos); $i++){
						  
						  if($array_cursos[$i]->codigo == $_GET["codigo"] && $array_cursos[$i]->seccion == $_GET["seccion"] && $array_cursos[$i]->id_periodo == $_GET["periodo"]){
							  
							   $id_curso = $array_cursos[$i]->id_curso;
							  
							   $url2 = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/cursos_inscritos?id_curso='.$id_curso.'';
								
									//SE INICIA CURL
									$ch2 = curl_init($url2);

									//MAXIMO TIEMPO DE ESPERA DE RESPUESTA DEL SERVIDOR
									curl_setopt($ch2, CURLOPT_TIMEOUT, 20); 

									//RESPUESTA DEL SERVICIO WEB
									curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

									//EJECUTAMOS LA PETICIÓN
									$resultado2 = curl_exec($ch2);

									//CERRAR 
									curl_close($ch2);

									$array_estudiantes = json_decode($resultado2);
									$suma_nota =0;
									$contador_alumnos = 0;
									for($j=0; $j< count($array_estudiantes); $j++){
										
										$rut_estudiante = $array_estudiantes[$j]->rut;
										
											$url3 = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/personas?rut='.$rut_estudiante.'';
										
											//SE INICIA CURL
											$ch3 = curl_init($url3);

											//MAXIMO TIEMPO DE ESPERA DE RESPUESTA DEL SERVIDOR
											curl_setopt($ch3, CURLOPT_TIMEOUT, 20); 

											//RESPUESTA DEL SERVICIO WEB
											curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);

											//EJECUTAMOS LA PETICIÓN
											$resultado3 = curl_exec($ch3);

											//CERRAR 
											curl_close($ch3);

											$array_personas = json_decode($resultado3);		

											$foto = $array_personas->i;
											$alias = $array_personas->alias;
											$estado_final = $array_estudiantes[$j]->estado_final;
											$nota_final = $array_estudiantes[$j]->nota_final;
											
											if($estado_final != "Eliminado" && $estado_final != "Inscrito"){
												
												$suma_nota = $suma_nota + $nota_final;
												$contador_alumnos++;
												
												if($estado_final == "Aprobado"){
													
													$cont_aprobados++;
													
												}else if($estado_final == "Reprobado"){
													
													$cont_reprobados++;
												}
											}
											
											
											
											
											echo "
											
											 <tr>
												<td>".($j+1)."</td>
												<td><img src=".$foto." class='rounded-circle' width='60' height='60' /></td>
												<td>".$alias."</td>
												<td>".$estado_final."</td>
												<td>".$nota_final."</td>
											  </tr>
											
											";
												
											
									}
								
						  }
						  
					  }
					  
					  $prom = round($suma_nota / $contador_alumnos,2); 
					  $porc_aprobados = round(($cont_aprobados * 100) / $contador_alumnos,2); 
					  $porc_reprobados = round(($cont_reprobados * 100) / $contador_alumnos,2); 
					  
					  
			    ?> 
				
			<!-- Left side columns -->
			 <section class="section dashboard">
			  <div class="row">
				<div class="col-lg-8">
				  <div class="row">		 
					<!-- Sales Card -->
					<div class="col-xxl-4 col-md-6">
					  <div class="card info-card sales-card">

						<div class="card-body">
						  <h5 class="card-title">Promedio notas <span>| del curso </span></h5>

						  <div class="d-flex align-items-center">
							<div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
							  <i class="bi bi-people"></i>
							</div>
							<div class="ps-3">
							  <h6><?php echo $prom; ?></h6>
							  <!--<span class="text-success small pt-1 fw-bold">12%</span> <span class="text-muted small pt-2 ps-1">increase</span>-->

							</div>
						  </div>
						</div>

					  </div>
					</div><!-- End Sales Card -->
					
					<!-- Sales Card -->
					<div class="col-xxl-4 col-md-6">
					  <div class="card info-card sales-card">

						<div class="card-body">
						  <h5 class="card-title">Aprobados <span>| del curso </span></h5>

						  <div class="d-flex align-items-center">
							<div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
							  <i class="bi bi-people text-success"></i>
							</div>
							<div class="ps-3">
							  <h6><?php echo $cont_aprobados; ?></h6>
							  <span class="text-success small pt-1 fw-bold"><?php echo $porc_aprobados; ?>%</span> <span class="text-muted small pt-2 ps-1">del total </span>

							</div>
						  </div>
						</div>

					  </div>
					</div><!-- End Sales Card -->
					
					<!-- Sales Card -->
					<div class="col-xxl-4 col-md-6">
					  <div class="card info-card sales-card">

						<div class="card-body">
						  <h5 class="card-title">Reprobados <span>| del curso </span></h5>

						  <div class="d-flex align-items-center">
							<div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
							  <i class="bi bi-people text-danger"></i>
							</div>
							<div class="ps-3">
							  <h6><?php echo $cont_reprobados; ?></h6>
							  <span class="text-success small pt-1 fw-bold"><?php echo $porc_reprobados; ?>%</span> <span class="text-muted small pt-2 ps-1">del curso</span>

							</div>
						  </div>
						</div>

					  </div>
					</div><!-- End Sales Card -->

					  </div>
					  </div>

					</div><!-- End Customers Card -->	  
			</section>		 
				</tbody>
			  </table>
	  </div>
	  </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; <strong><span>Facultad de Medicina - Universidad de Chile</span></strong>. Todos los derechos reservados
    </div>
    <div class="credits">
      <!-- All the links in the footer should remain intact. -->
      <!-- You can delete the links only if you purchased the pro version. -->
      <!-- Licensing information: https://bootstrapmade.com/license/ -->
      <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
      Diseñado por <a href="https://dpi.med.uchile.cl">DPI</a>
    </div>
  </footer><!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>