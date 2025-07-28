<?php
include_once("conexion.php");
include_once("login/control_sesion.php"); 
session_start();
include_once("log_ip.php");
registrarIP(); 
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
          <li class="breadcrumb-item"><a href="#">Inicio</a></li>
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
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-overview">Guía inicial</button>
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
				   <a href="https://dpi.med.uchile.cl/calendario/pages-faq.php"> Te invitamos a leer una guía sobre el uso de calendario. </a>
				  </p>

                  <h5 class="card-title">Tutoriales de uso de plataforma</h5>
					<div class="container my-4">
					  <div class="row">
						
						<!-- Card 1 -->
						<div class="col-md-6 col-lg-3 mb-4">
						  <div class="card h-100">
							<div class="card-body d-flex flex-column">
							  <h5 class="card-title">Ingreso al sistema </h5>
							  <p class="card-text">Tutorial sobre como ingresar a la plataforma. </p>
							  <a href="https://youtu.be/K5oKkuyZkdk?si=ze-L1Y_kwnzMyboI" target="_blank" class="btn btn-danger mt-auto">Ver en YouTube</a>
							</div>
						  </div>
						</div>

						<!-- Card 2 -->
						<div class="col-md-6 col-lg-3 mb-4">
						  <div class="card h-100">
							<div class="card-body d-flex flex-column">
							  <h5 class="card-title">Actividades</h5>
							  <p class="card-text">Tutorial sobre el ingreso de actividades en calendario. </p>
							  <a href="https://youtu.be/32wzVsjPT54?si=D1S-0_E_KaNp8VQE" target="_blank" class="btn btn-danger mt-auto">Ver en YouTube</a>
							</div>
						  </div>
						</div>

						<!-- Card 3 -->
						<div class="col-md-6 col-lg-3 mb-4">
						  <div class="card h-100">
							<div class="card-body d-flex flex-column">
							  <h5 class="card-title">Equipo docente</h5>
							  <p class="card-text">Tutorial de ingreso y mantención del equipo docente. </p>
							  <a href="https://youtu.be/jE0vfA_396U?si=Zid8RFFq_dPoyIPg" target="_blank" class="btn btn-danger mt-auto">Ver en YouTube</a>
							</div>
						  </div>
						</div>

						<!-- Card 4 -->
						<div class="col-md-6 col-lg-3 mb-4">
						  <div class="card h-100">
							<div class="card-body d-flex flex-column">
							  <h5 class="card-title">Salas</h5>
							  <p class="card-text">Tutorial sobre como solicitar, modificar y liberar espacios. </p>
							  <a href="https://youtu.be/2Bo0qeGDkDA?si=lWTxiRP_0iJx0kuO" target="_blank" class="btn btn-danger mt-auto">Ver en YouTube</a>
							</div>
						  </div>
						</div>

					  </div>
					</div>
           
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
												#AND (spre_periodo_calendario.activo= 1 OR spre_periodo_calendario.anterior IN (2))
												AND spre_periodo_calendario.anterior IN (2)
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
												
												<?php if ($fila_cursos["idperiodo"] >= "2025.2" || $fila_cursos["activo"] == 1) { 
															$link_programa = "https://dpi.med.uchile.cl/programa/controlador.php?nik=" . $fila_cursos["codigoCurso"];
															$icon_programa = "bx bx-link-external"; 
														} else { 
															$link_programa = "https://dpi.med.uchile.cl/programa/pdf_old.php?nik=" . $fila_cursos["codigoCurso"];
															$icon_programa = "ri ri-arrow-go-back-fill"; 
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
					
					Informa sobre tu problema a correo de aulas <a target="_blank" href="mailto:felpilla@gmail.com"> gestionaulas.med@uchile.cl </a>


                </div>

              </div><!-- End Bordered Tabs -->

            </div>
          </div>

        </div>
      </div>
    </section>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Modal -->
<?php

	$p_correo = date("Y");	
	
	$correos_act = "SELECT * 
					FROM correos_actualizados
					WHERE rut = '$rut_niv'
					AND periodo='$p_correo' ";
	$correos_actQ = mysqli_query($conn,$correos_act);
	$n_correos_act = mysqli_num_rows($correos_actQ);
	
	if ($n_correos_act == 0) {
		echo $rut_niv; 
		echo '<script>$(document).ready(function(){ 
	  
			$("#modal_correo").modal("show");
		  
		});</script>'; 
	}
?>
  


<!-- Modal -->
<div class="modal fade" id="modal_correo"  tabindex="-1" aria-labelledby="exampleModalLabel"  data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"> 
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="h5" id="modal_correo"><i class="far fa-envelope text-danger"></i> Bienvenido(a), antes de empezar, actualice su correo institucional. </h5>
      </div>
	  <form action="actualizar_correo.php" method="POST" id="compareForm">
	  <input type="text" name="rut" value="<?php echo $rut_niv; ?>"  hidden />
			<div class="modal-body">
			  <div class="alert alert-primary">
				<i class="fas fa-info-circle"></i> <b>Importante:</b> A este correo le enviaremos la confirmación y recordatorios de salas asignadas.
			  </div>
			  <?php
			  
				$correo_usuario = "SELECT * FROM spre_personas WHERE Rut = '$rut_niv' ";
				$correo_usuarioQ = mysqli_query($conexion3,$correo_usuario);
				$fila_correo = mysqli_fetch_assoc($correo_usuarioQ);
			  
			  ?>
				<div class="input-group mb-3">
				  <span class="input-group-text" id="basic-addon1"><i class="far fa-envelope"></i></span>
				  <input type="email" name="correo" id="correo" class="form-control" placeholder="Ingrese correo" aria-label="Correo" aria-describedby="basic-addon1" value="<?php echo $fila_correo["EmailReal"]; ?>">
				</div>
				
				<b>Confirme su correo: </b>
				
				<div class="input-group mb-3 mt-2">
				  <span class="input-group-text" id="basic-addon1"><i class="far fa-envelope"></i></span>
				  <input type="email" name="correo2" id="correo2"  onkeyup="comparar_correo()" class="form-control" placeholder="Ingrese correo" aria-label="Correo" aria-describedby="basic-addon1" value="<?php echo $fila_correo["EmailReal"]; ?>">
				</div>
				
				<div id="message" class="mt-3"></div>
			</div>
		  <div class="modal-footer">
			<button type="submit" id="submitBtn" class="btn btn-primary">Guardar cambios</button>
		  </div>
	  </form>
    </div>
  </div>
</div>
  <script>
  
  function comparar_correo(){
	  
	var input1 = $('#correo').val();
	var input2 = $('#correo2').val();
	var message = $('#message');
	var submitBtn = $('#submitBtn');

		if (input1 && input2) {
			if (input1 === input2) {
				message.text('Correos coinciden.').css('color', 'green');
				submitBtn.prop('disabled', false);
			} else {
				message.text('Correos no coinciden.').css('color', 'red');
				submitBtn.prop('disabled', true);
			}
		} else {
			message.text('');
			submitBtn.prop('disabled', true);
		}
    }
  
  
  </script>

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