<?php
include("conexion.php");
include("login/control_sesion.php"); 
session_start();
$rut = $_SESSION['sesion_idLogin'];
$name = $_SESSION['sesion_usuario']; 
$viene= array("Ã¡","Ã©","Ã","Ã³","Ãº");
$queda= array("Á","É","Í","Ó","Ú");
$nombre = str_replace($viene, $queda, $name);
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);

if (empty($_SESSION['sesion_idLogin'])) {
    header("Location: login/close.php");
    exit; 
}else{



//idcurso
//8858
//8924

//Consulta Funcionario
$spre_personas = "SELECT * FROM spre_personas WHERE Rut='$rut_niv' ";
$spre_personasQ = mysqli_query($conexion3,$spre_personas);
$fila_personas = mysqli_fetch_assoc($spre_personasQ);

$funcionario = utf8_encode($fila_personas["Funcionario"]);

function InfoDocenteUcampus($rut_niv){
	
	$rut_niv_def = ltrim($rut_niv, "0");
	$cad = substr ($rut_niv_def, 0, -1);

	$url = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/personas?rut='.$cad;

	//SE INICIA CURL
	$ch = curl_init($url);

	//PARÁMETROS
	$parametros = "rut=$rut_niv";

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

$foto_docente = InfoDocenteUcampus($rut_niv);

?>
	<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario académico - Facultad de Medicina</title>
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
  
	<style>
	/* Posicionamiento del submenú */
.dropdown-submenu {
  position: relative;
}

.dropdown-submenu > .dropdown-menu {
  top: 0;
  left: 100%;
  margin-top: -1px;
}

/* Opcional: mostrar submenú al pasar el mouse */
.dropdown-submenu:hover > .dropdown-menu {
  display: block;
}
	</style>
    
</head>
<body >

 <!-- ======= Header ======= -->
  <?php include 'nav_superior.php'; ?>
  
    <!-- ======= Sidebar ======= -->
 <?php include 'nav_lateral.php'; ?>

  <main id="main" class="main">

    <div class="pagetitle">
      <h1><i class="bi bi-house-door"></i> Gestión de docencia - pregrado 2025.2</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Inicio</a></li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
		<!--<div class="col-xl-12">

          <div class="card">
            <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
			
              
              <h2> <i class="bi bi-person-raised-hand"></i> Instrucciones</h2>
              
            </div>
          </div> 

        </div>-->

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
                  <p class="h6">
				  La plataforma de calendario es la bitácora institucional oficial y única vía de solicitud de salas de las asignaturas asociadas a los diferentes planes de estudios de pregrado. Además, permite organizar los contenidos del curso en función de las fechas correspondiente al semestre/año en que se imparte y el docente responsable en cada una de las actividades calendarizadas. Por último, incluye el detalle de las horas directas efectuadas por cada docente.
				   <a href="https://dpi.med.uchile.cl/test/calendarios/pages-faq.php"> Te invitamos a leer una guía sobre el uso de calendario. </a>
				  </p>

                  <h5 class="card-title">Tutorial de uso de plataforma</h5>
				  <!--<iframe width="50%" height="500" src="https://www.youtube.com/embed/p7U5yRgQ93A?si=hk9LyudYrBlD6a54" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>-->
           
                </div>

                <div class="tab-pane fade show active profile-edit pt-2" id="profile-edit">

					<section class="section">
					  <div class="row">
						<div class="col-lg-12">

						  <div class="card">
							<div class="card-body">
							 <h5 class="card-title">Cursos activos 
							 <span class="badge bg-success text-white"><i class="bi bi-check-circle me-1"></i> 2025.2 </span>
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
									<th>Participación</th>
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
												AND rut='$rut_niv' 
												AND spre_profesorescurso.Vigencia='1' 
												AND (spre_periodo_calendario.activo= 2 OR spre_periodo_calendario.anterior IN (1))
												GROUP BY idcurso  
												ORDER BY NombreCurso ASC";
									  $cursosQuery = mysqli_query($conexion3,$cursos);
									  $num_cursos = mysqli_num_rows($cursosQuery);
									  
									  while($fila_cursos = mysqli_fetch_assoc($cursosQuery)){
									  // Separar el periodo en año y semestre
										$periodo_parts = explode('.', $fila_cursos["idperiodo"]);
										$anio = $periodo_parts[0];
										$semestre = $periodo_parts[1];
										
										// Construir la URL de U-Cursos
										$url_ucursos = "https://www.u-cursos.cl/medicina/{$anio}/{$semestre}/{$fila_cursos["codigoCurso"]}/1/historial";
?> 
								
										  <tr>
											<td><?php echo utf8_encode($fila_cursos["codigoCurso"]); ?>-<?php echo $fila_cursos["seccion"]; ?>
											<?php echo utf8_encode($fila_cursos["NombreCurso"]); ?>
											<?php if($fila_cursos["VersionCalendario"] == 1){ echo "<button type='button' class='btn btn-sm btn-warning' disabled><small>Clínico</small></button>";}  ?>
											</td>
											<td><?php echo $fila_cursos["idcurso"]; ?></td>
											<td><?php echo $fila_cursos["idperiodo"]; ?></td>
											<td><span class="badge bg-secondary text-white"><i class="bi bi-star me-1"></i> <?php echo $fila_cursos["CargoTexto"]; ?> </span></td>
											<td>
												<a type="button" class="btn btn-outline-primary btn-sm" target="" href="<?php echo ($fila_cursos["VersionCalendario"] == 1) ? 'index_clinico.php' : 'index.php'; ?>?curso=<?php echo $fila_cursos["idcurso"]; ?>"><i class="ri ri-calendar-check-fill"></i> Calendario</a>
												
												<?php if($fila_cursos["activo"] == 1){ 

														$link_programa = "https://dpi.med.uchile.cl/programa/nuevo_programa.php?nik=$fila_cursos[codigoCurso]";
														$icon_programa = " bx bx-link-external"; 
														
													}else{ 

														$link_programa = "https://dpi.med.uchile.cl/programa/print.php?nik=$fila_cursos[codigoCurso]";
														$icon_programa = " ri ri-arrow-go-back-fill"; 
													
													}?> <!--Periodo activo-->
												
												<a type="button" class="btn btn-outline-success btn-sm " target="_blank" href="<?php echo $link_programa; ?>" > <i class="<?php echo $icon_programa; ?>"></i> Programa</a>
												<a type="button" class="btn btn-outline-danger btn-sm" target="_blank" href="<?php echo $url_ucursos; ?>" > <i class="bx bx-link-external"></i> U cursos</a>
												<!-- <a type="button" class="btn btn-outline-info btn-sm" href="data.php?codigo=<?php echo utf8_encode($fila_cursos["codigoCurso"]); ?>&seccion=<?php echo $fila_cursos["seccion"]; ?>&periodo=<?php echo $fila_cursos["idperiodo"]; ?>"> <i class="ri ri-map-pin-user-fill"></i> Estudiantes </a>-->
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
				    
					Queremos responderte lo más rápido posible. Te invitamos a leer las <a href="pages-faq.php" target='_blank'> <b>preguntas frecuentes</b></a>
				   
					<h5 class="card-title">¿No encontraste lo que buscabas?</h5>
					
					Informanos sobre tu problema <a target="_blank" href="https://dpi.med.uchile.cl/gestion/sugerencias/"> aquí </a> o escríbenos directamente a dpi.med@uchile.cl 

					<h5 class="card-title">¿Necesitas ayuda de aulas docentes?</h5>
					
					informa sobre tu problema a correo de aulas <a target="_blank" href="mailto:felpilla@gmail.com"> gestionaulas.med@uchile.cl </a>


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
      &copy; <b>2025 Facultad de Medicina Universidad de Chile</b>
    </div>
    <div class="credits">
      Diseñado por <b><a target="_blank" href="https://dpi.med.uchile.cl">DPI</b></a>
    </div>
  </footer>

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
<?php
}
?>