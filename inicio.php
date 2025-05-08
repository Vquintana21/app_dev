<!DOCTYPE html>
<html lang="en">
<?php
session_start(); 
include("conexion.php");
$rut = "016784781K";

//idcurso
//8858
//8924

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
      <h1>Gestión de docencia 2025.1</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Home</a></li>
          <li class="breadcrumb-item">Inicio</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
		<div class="col-xl-12">

          <div class="card">
            <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
			
              <img src="<?php echo $foto_docente; ?>" alt="Profile" class="rounded-circle">
              <h2><?php echo $nombreFuncionario; ?></h2>
              <h3>Académico/a</h3>
            </div>
          </div> 

        </div>

      <div class="row">
        
        <div class="col-xl-12">

          <div class="card">
            <div class="card-body pt-3">
              <!-- Bordered Tabs -->
              <ul class="nav nav-tabs nav-tabs-bordered">

                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-overview">Resumen</button>
                </li>

                <li class="nav-item">
                  <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-edit">Mis cursos</button>
                </li>

               <!-- <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-settings">NoTengoIdea</button>
                </li> -->

                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password">Solicitud de ayuda</button>
                </li>

              </ul>
              <div class="tab-content pt-2">

                <div class="tab-pane fade profile-overview" id="profile-overview">
                  <h5 class="card-title">¿Cómo usar la plataforma?</h5>
                  <p class="small fst-italic">Sunt est soluta temporibus accusantium neque nam maiores cumque temporibus. Tempora libero non est unde veniam est qui dolor. Ut sunt iure rerum quae quisquam autem eveniet perspiciatis odit. Fuga sequi sed ea saepe at unde.</p>

                  <h5 class="card-title">Tutorial de uso de plataforma</h5>
				  <iframe width="50%" height="500" src="https://www.youtube.com/embed/p7U5yRgQ93A?si=hk9LyudYrBlD6a54" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
           
                </div>

                <div class="tab-pane fade show active profile-edit pt-2" id="profile-edit">

					<section class="section">
					  <div class="row">
						<div class="col-lg-12">

						  <div class="card">
							<div class="card-body">
							 <h5 class="card-title">Cursos activos 
							 <span class="badge bg-success text-white"><i class="bi bi-check-circle me-1"></i> 2024.2 </span> y<span class="badge bg-light"><i class="bi bi-check-circle me-1"></i> 2024.1 </span>
							 </h5>

							  <p>A continuación podrá revisar los cursos del periodo activo en los que usted participa. </p>

							  <!-- Table with stripped rows -->
							  <small>
							  <table class="table datatable">
								<thead>
								  <tr>
									<th>
									  <b>Nombre
									</th>
									<th>ID</th>
									<th>Periodo</th>
									<th>Tipo curso</th>
									<th>Participación</th>
									<th>Duración semanas</th>
									<th>Acciones</th>
								  </tr>
								</thead>
								<tbody>
								<?php
									
									  $cursos = "SELECT spre_profesorescurso.idcurso, spre_cursos.codigoCurso,spre_cursos.seccion ,spre_ramos.NombreCurso,spre_cursos.idperiodo,spre_periodo_calendario.activo,CargoTexto,Semanas,VersionCalendario
												FROM spre_profesorescurso 
												INNER JOIN spre_cursos ON spre_profesorescurso.idcurso = spre_cursos.idcurso
												INNER JOIN spre_ramos ON spre_cursos.codigoCurso = spre_ramos.codigoCurso
												INNER JOIN spre_periodo_calendario ON spre_periodo_calendario.periodo = spre_cursos.idperiodo
												INNER JOIN spre_tipoparticipacion ON spre_tipoparticipacion.idTipoParticipacion = spre_profesorescurso.idTipoParticipacion
												WHERE spre_profesorescurso.idTipoParticipacion IN ('1','2','3','8','10') 
												AND rut='$rut' 
												AND spre_profesorescurso.Vigencia='1' 
												AND (spre_periodo_calendario.activo= 2 OR spre_periodo_calendario.anterior IN (1))
												GROUP BY idcurso  
												ORDER BY NombreCurso ASC";
									  $cursosQuery = mysqli_query($conexion3,$cursos);
									  $num_cursos = mysqli_num_rows($cursosQuery);
									  
									  while($fila_cursos = mysqli_fetch_assoc($cursosQuery)){
									  ?> 
								
										  <tr>
											<td><?php echo utf8_encode($fila_cursos["codigoCurso"]); ?>-<?php echo $fila_cursos["seccion"]; ?> <?php echo utf8_encode($fila_cursos["NombreCurso"]); ?></td>
											<td><?php echo $fila_cursos["idcurso"]; ?></td>
											<td><?php echo $fila_cursos["idperiodo"]; ?></td>
											<td><?php if($fila_cursos["VersionCalendario"] == 0){ echo "Curso regular"; }else{ echo "Curso clínico"; } ?></td>
											<td><span class="badge bg-secondary text-white"><i class="bi bi-star me-1"></i> <?php echo $fila_cursos["CargoTexto"]; ?> </span></td>
											<td><?php echo $fila_cursos["Semanas"]; ?></td>
											<td>
												<a type="button" class="btn btn-outline-primary btn-sm" target="" href="<?php echo ($fila_cursos["VersionCalendario"] == 1) ? 'index_clinico.php' : 'index.php'; ?>?curso=<?php echo $fila_cursos["idcurso"]; ?>"><i class="ri ri-calendar-check-fill"></i> Calendario</a>
												
												<?php if($fila_cursos["activo"] == 1){ 

														$link_programa = "https://dpi.med.uchile.cl/programa/nuevo_programa.php?nik=$fila_cursos[codigoCurso]";
														$icon_programa = " bx bx-link-external"; 
														
													}else{ 

														$link_programa = "https://dpi.med.uchile.cl/programa/api/pdf.php?curso=$fila_cursos[codigoCurso]&periodof=$fila_cursos[idperiodo]";
														$icon_programa = " ri ri-arrow-go-back-fill"; 
													
													}?> <!--Periodo activo-->
												
												<a type="button" class="btn btn-outline-success btn-sm " target="_blank" href="<?php echo $link_programa; ?>" > <i class="<?php echo $icon_programa; ?>"></i> Programa</a>
												<button type="button" class="btn btn-outline-danger btn-sm"> <i class="bx bx-link-external"></i> U cursos</button>
												<a type="button" class="btn btn-outline-info btn-sm" href="data.php?codigo=<?php echo utf8_encode($fila_cursos["codigoCurso"]); ?>&seccion=<?php echo $fila_cursos["seccion"]; ?>&periodo=<?php echo $fila_cursos["idperiodo"]; ?>"> <i class="ri ri-map-pin-user-fill"></i> Estudiantes </a>
											</td>
										  </tr>
									  <?php } ?> 
								 
								</tbody>
							  </table>
							  </small>
							  <!-- End Table with stripped rows -->

							</div>
						  </div>

						</div>
					  </div>
					</section>

                </div>

                <div class="tab-pane fade pt-3" id="profile-settings">

                </div>

                <div class="tab-pane fade pt-3" id="profile-change-password">
                   
				   <h5 class="card-title">¿Necesitas ayuda?</h5>
				    
					Queremos responderte lo más rápido posible. Te invitamos a leer las <a href="https://dpi.med.uchile.cl/calendario/pages-faq.html"> <b>preguntas frecuentes</b></a>
				   
					<h5 class="card-title">¿No encontraste lo que buscabas?</h5>
					
					Informanos sobre tu problema <a target="_blank" href="https://dpi.med.uchile.cl/gestion/sugerencias/"> aquí </a> o escríbenos directamente a dpi.med@uchile.cl

                </div>

              </div><!-- End Bordered Tabs -->

            </div>
          </div>

        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>NiceAdmin</span></strong>. All Rights Reserved
    </div>
    <div class="credits">
      <!-- All the links in the footer should remain intact. -->
      <!-- You can delete the links only if you purchased the pro version. -->
      <!-- Licensing information: https://bootstrapmade.com/license/ -->
      <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
      Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>
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