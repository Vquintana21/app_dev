<!DOCTYPE html>
<html lang="en">
<?php 
include("conexion.php");

$idCurso = 8158;
$rut = "0185643530";
$ano = 2024; 

//Consulta curso spre_cursos
$buscarCurso = "SELECT * FROM `spre_cursos` WHERE idCurso='$idCurso'";
$buscarCursoQ = mysqli_query($conexion3,$buscarCurso);
$FilaCurso = mysqli_fetch_assoc($buscarCursoQ);

$codigo_curso = $FilaCurso["CodigoCurso"];
$seccion = $FilaCurso["Seccion"];

//Consulta Ramo
$nombre_ramo = "SELECT * FROM spre_ramos WHERE CodigoCurso='$codigo_curso' ";
$ramoQuery = mysqli_query($conexion3,$nombre_ramo);
$ramo_fila = mysqli_fetch_assoc($ramoQuery);

$nombre_curso = utf8_encode($ramo_fila["NombreCurso"]);

//Consulta Ramo
$spre_personas = "SELECT * FROM spre_personas WHERE Rut='$rut' ";
$spre_personasQ = mysqli_query($conexion3,$spre_personas);
$fila_personas = mysqli_fetch_assoc($spre_personasQ);

$funcionario = utf8_encode($fila_personas["Funcionario"]);



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

?>
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Calendario Académico 2024</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- =======================================================
  * Template Name: NiceAdmin
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Updated: Apr 20 2024 with Bootstrap v5.3.3
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
  
  <style>
/* Ocultar la columna "Sábado" */
th:nth-child(7),
td:nth-child(7) {
    display: none;
}
  
  </style>
</head>

<body>

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="planificacion.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="">
        <span class="d-none d-lg-block">Calendario FM</span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->
	<!--
    <div class="search-bar">
      <form class="search-form d-flex align-items-center" method="POST" action="#">
        <input type="text" name="query" placeholder="Buscar" title="Enter search keyword">
        <button type="submit" title="Search"><i class="bi bi-search"></i></button>
      </form>
    </div>-->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">

        <li class="nav-item d-block d-lg-none">
          <a class="nav-link nav-icon search-bar-toggle " href="#">
            <i class="bi bi-search"></i>
          </a>
        </li><!-- End Search Icon-->


        <li class="nav-item dropdown pe-3">
		<?php $foto = InfoDocenteUcampus($rut); ?>
          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="<?php echo $foto; ?>" alt="Profile" class="rounded-circle">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $funcionario; ?></span>
          </a><!-- End Profile Iamge Icon -->

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

          </ul><!-- End Profile Dropdown Items -->
        </li><!-- End Profile Nav -->

      </ul>
    </nav><!-- End Icons Navigation -->

  </header><!-- End Header -->

  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

      <li class="nav-item">
        <a class="nav-link " href="index.html">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li><!-- End Dashboard Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-menu-button-wide"></i><span>Components</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="components-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="components-alerts.html">
              <i class="bi bi-circle"></i><span>Alerts</span>
            </a>
          </li>
          <li>
            <a href="components-accordion.html">
              <i class="bi bi-circle"></i><span>Accordion</span>
            </a>
          </li>
          <li>
            <a href="components-badges.html">
              <i class="bi bi-circle"></i><span>Badges</span>
            </a>
          </li>
          <li>
            <a href="components-breadcrumbs.html">
              <i class="bi bi-circle"></i><span>Breadcrumbs</span>
            </a>
          </li>
          <li>
            <a href="components-buttons.html">
              <i class="bi bi-circle"></i><span>Buttons</span>
            </a>
          </li>
          <li>
            <a href="components-cards.html">
              <i class="bi bi-circle"></i><span>Cards</span>
            </a>
          </li>
          <li>
            <a href="components-carousel.html">
              <i class="bi bi-circle"></i><span>Carousel</span>
            </a>
          </li>
          <li>
            <a href="components-list-group.html">
              <i class="bi bi-circle"></i><span>List group</span>
            </a>
          </li>
          <li>
            <a href="components-modal.html">
              <i class="bi bi-circle"></i><span>Modal</span>
            </a>
          </li>
          <li>
            <a href="components-tabs.html">
              <i class="bi bi-circle"></i><span>Tabs</span>
            </a>
          </li>
          <li>
            <a href="components-pagination.html">
              <i class="bi bi-circle"></i><span>Pagination</span>
            </a>
          </li>
          <li>
            <a href="components-progress.html">
              <i class="bi bi-circle"></i><span>Progress</span>
            </a>
          </li>
          <li>
            <a href="components-spinners.html">
              <i class="bi bi-circle"></i><span>Spinners</span>
            </a>
          </li>
          <li>
            <a href="components-tooltips.html">
              <i class="bi bi-circle"></i><span>Tooltips</span>
            </a>
          </li>
        </ul>
      </li><!-- End Components Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#forms-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-journal-text"></i><span>Forms</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="forms-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="forms-elements.html">
              <i class="bi bi-circle"></i><span>Form Elements</span>
            </a>
          </li>
          <li>
            <a href="forms-layouts.html">
              <i class="bi bi-circle"></i><span>Form Layouts</span>
            </a>
          </li>
          <li>
            <a href="forms-editors.html">
              <i class="bi bi-circle"></i><span>Form Editors</span>
            </a>
          </li>
          <li>
            <a href="forms-validation.html">
              <i class="bi bi-circle"></i><span>Form Validation</span>
            </a>
          </li>
        </ul>
      </li><!-- End Forms Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-layout-text-window-reverse"></i><span>Tables</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="tables-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="tables-general.html">
              <i class="bi bi-circle"></i><span>General Tables</span>
            </a>
          </li>
          <li>
            <a href="tables-data.html">
              <i class="bi bi-circle"></i><span>Data Tables</span>
            </a>
          </li>
        </ul>
      </li><!-- End Tables Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#charts-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-bar-chart"></i><span>Charts</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="charts-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="charts-chartjs.html">
              <i class="bi bi-circle"></i><span>Chart.js</span>
            </a>
          </li>
          <li>
            <a href="charts-apexcharts.html">
              <i class="bi bi-circle"></i><span>ApexCharts</span>
            </a>
          </li>
          <li>
            <a href="charts-echarts.html">
              <i class="bi bi-circle"></i><span>ECharts</span>
            </a>
          </li>
        </ul>
      </li><!-- End Charts Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#icons-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-gem"></i><span>Icons</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="icons-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="icons-bootstrap.html">
              <i class="bi bi-circle"></i><span>Bootstrap Icons</span>
            </a>
          </li>
          <li>
            <a href="icons-remix.html">
              <i class="bi bi-circle"></i><span>Remix Icons</span>
            </a>
          </li>
          <li>
            <a href="icons-boxicons.html">
              <i class="bi bi-circle"></i><span>Boxicons</span>
            </a>
          </li>
        </ul>
      </li><!-- End Icons Nav -->

      <li class="nav-heading">Pages</li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="users-profile.html">
          <i class="bi bi-person"></i>
          <span>Profile</span>
        </a>
      </li><!-- End Profile Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="pages-faq.html">
          <i class="bi bi-question-circle"></i>
          <span>F.A.Q</span>
        </a>
      </li><!-- End F.A.Q Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="pages-contact.html">
          <i class="bi bi-envelope"></i>
          <span>Contact</span>
        </a>
      </li><!-- End Contact Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="pages-register.html">
          <i class="bi bi-card-list"></i>
          <span>Register</span>
        </a>
      </li><!-- End Register Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="pages-login.html">
          <i class="bi bi-box-arrow-in-right"></i>
          <span>Login</span>
        </a>
      </li><!-- End Login Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="pages-error-404.html">
          <i class="bi bi-dash-circle"></i>
          <span>Error 404</span>
        </a>
      </li><!-- End Error 404 Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="pages-blank.html">
          <i class="bi bi-file-earmark"></i>
          <span>Blank</span>
        </a>
      </li><!-- End Blank Page Nav -->

    </ul>

  </aside><!-- End Sidebar-->

  <main id="main" class="main">

    <div class="pagetitle">
      <h1><?php echo $codigo_curso."-".$seccion; ?> <?php echo $nombre_curso; ?></h1>
      <small style="float: right; " >ID curso: <?php echo $idCurso; ?></small>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Home</a></li>
          <li class="breadcrumb-item active">Plan de clases <?php echo $codigo_curso."-".$seccion; ?> <?php echo $nombre_curso; ?></li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Editar información</h5>

              <!-- Bordered Tabs Justified -->
              <ul class="nav nav-tabs nav-tabs-bordered d-flex" id="borderedTabJustified" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100 active" id="home-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-home" type="button" role="tab" aria-controls="home" aria-selected="true"><i class="bi bi-calendar4-week"></i> Calendario </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="profile-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-profile" type="button" role="tab" aria-controls="profile" aria-selected="false"><i class="ri ri-user-settings-line"></i> Equipo docente</button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="salas-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-salas" type="button" role="tab" aria-controls="salas" aria-selected="false"><i class="ri ri-map-pin-line"></i> Salas</button>
                </li>
				<li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="contact-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-contact" type="button" role="tab" aria-controls="contact" aria-selected="false"><i class="bi bi-calendar-plus"></i> Otras acciones</button>
                </li>
              </ul>
              <div class="tab-content pt-2" id="borderedTabJustifiedContent">
                <div class="tab-pane fade show active" id="bordered-justified-home" role="tabpanel" aria-labelledby="home-tab">
					<section class="section dashboard">
					  <div class="row">

						<!-- Left side columns -->
						<div class="col-lg-12">
						  <div class="row">
							<!-- Reports -->
							<div class="col-12">
							  <div class="card">
								<div class="card-body">
								  <h5 class="card-title"><i class="bi bi-calendar4-week"></i> Calendario <span>/ Hoy es <?php echo date("d-m-Y"); ?></span></h5>
									<nav>
									<ol class="breadcrumb">
									  <li class="breadcrumb-item"><i class="bi bi-circle-fill text-primary"></i> <small>Actividad regular (Clase, Actividad Grupal, Trabajo Práctico, Evaluación, Examen)</small></li>
									  <li class="breadcrumb-item"><i class="bi bi-circle-fill text-secondary"></i> <small>Actividad de autoaprendizaje</small></li>
									  <li class="breadcrumb-item"><i class="bi bi-circle-fill text-success"></i> <small>Actividad publicada (OK) compartida con integrantes del curso</small></li>
									</ol>
									</nav>
									<?php
									for ($j = 3; $j <= 7; $j++) {
										// Definir el mes y el año que quieres mostrar
										$mes = $j;
										$año = date('Y');
										
										// Crear una fecha del primer día del mes
										$primer_dia = mktime(0, 0, 0, $mes, 1, $año);

										// Obtener el número de días en el mes
										$num_dias = date('t', $primer_dia);

										// Obtener el día de la semana del primer día del mes (0 para domingo, 1 para lunes, ...)
										$primer_dia_semana = date('w', $primer_dia);

										// Crear un arreglo de los nombres de los días de la semana
										$dias_semana = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');

										// Crear un arreglo de los nombres de los meses
										$nombre_mes = array('Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');

										// Imprimir el encabezado del calendario
										echo "<div class='table-responsive'><table class='table table-bordered table-sm'>";
										echo "<th class='table-bordered table-info' colspan='6'><center><h4>" . $nombre_mes[$mes - 1] . " " . $año . "</h4></th>";
										echo "<tr>";

										// Imprimir los nombres de los días de la semana excepto Sábado
										foreach ($dias_semana as $dia) {
											if ($dia !== 'Sábado') {
												echo "<th class='table-primary h5'>$dia</th>";
											}
										}
										echo "</tr>";
										echo "<tr>";

										// Imprimir los espacios en blanco hasta llegar al primer día de la semana
										for ($i = 0; $i < $primer_dia_semana; $i++) {
											echo "<td></td>";
										}

										// Imprimir los números de los días
										$contador_dias = 1;
										while ($contador_dias <= $num_dias) {
											
											$fecha_actual = date("$año-$mes-$contador_dias");
											
											// Si el día de la semana es domingo, inicia una nueva fila
											if ($primer_dia_semana >= 7) {
												echo "</tr><tr>";
												$primer_dia_semana = 0;
											}

											// Imprimir el número del día
											echo "<td class='text-secondary'>";
											echo "<small>$contador_dias</small>";
											buscar_actividad($idCurso, $fecha_actual);
											echo "</td>";

											// Incrementar el contador de días
											$contador_dias++;
											$primer_dia_semana++;
										}

										// Imprimir los espacios en blanco para completar la última semana si es necesario
										while ($primer_dia_semana < 7) {
											echo "<td></td>";
											$primer_dia_semana++;
										}

										echo "</tr>";
										echo "</table></div>";
								}

								
											?>


									  <!--  [mes_1, semana_1, dia_L, L1]-->
									  <!--  [mes_1, semana_1, dia_L, L2]-->
									  <!--  [mes_1, semana_1, dia_L, L3]-->
								</div>
								</div> 

							  </div>
							</div><!-- End Reports -->



						  
						</div><!-- End Left side columns -->

						</div><!-- End Right side columns -->

					  </div>
					  
					</section>

					<?php 

					function buscar_actividad($idCurso, $fecha){
						
						include("conexion.php");
						
						//Consulta planclases
						$planclases = "SELECT idplanclases,
										CONCAT(SUBSTRING(pcl_tituloActividad, 1, 25),'...') AS actividad,
										pcl_Inicio,
										pcl_Termino,
										pcl_TipoSesion,
										pcl_HorasNoPresenciales,
										pcl_Publico
										FROM planclases 
						WHERE cursos_idcursos='$idCurso'
						AND pcl_Fecha = '$fecha'
						AND pcl_TipoSesion NOT IN ('')
						ORDER BY pcl_Inicio ASC"; 
						$planQuery = mysqli_query($conexion,$planclases);
						$num_rows = mysqli_num_rows($planQuery);
						
						$i = 0; 
						if($num_rows > 0){
							
							while($fila_plan = mysqli_fetch_assoc($planQuery)){
								
								$hora_inicio = date("H:i", strtotime($fila_plan["pcl_Inicio"]));
								$actividad = $fila_plan["actividad"];
								
								if($fila_plan["pcl_TipoSesion"] == "Autoaprendizaje"){
									
									$color="secondary";
									$actividad = $fila_plan["pcl_TipoSesion"];
									$fecha_tiempo = strtotime($fila_plan["pcl_HorasNoPresenciales"]);
									$hora_formateada = date("h:i", $fecha_tiempo);
									$hora_inicio = "<i class='bi bi-journal-bookmark-fill'></i> ".$hora_formateada; 
									
								}elseif($fila_plan["pcl_TipoSesion"] == "Vacaciones" || $fila_plan["pcl_TipoSesion"] == "Feriado"){
									
									$color="info";
									$actividad = $fila_plan["pcl_TipoSesion"];
									$hora_inicio = "";								
									
								}elseif($fila_plan["pcl_TipoSesion"] == "Bloque Protegido"){
									$color="danger";
									
									
								}else{
									
									if($fila_plan["pcl_Publico"] == 1){
										
										$color="success";
										$sala_vista = "<small class='m-2'><i class='bi bi-arrow-return-right text-secondary'></i> Auditorio Lorenzo Sazie</small>";
										
									}else{
										$sala_vista = "<small class='m-2'><i class='bi bi-arrow-return-right text-secondary'></i> Auditorio Lorenzo Sazie</small>";
										$color="primary";
									
									}
									
								}
								
								echo utf8_encode("<br><a class='h5' href='#'><span onclick='detalle_actividad()' class='badge bg-$color bg-gradient'>$hora_inicio $actividad</span></a>");
								echo utf8_encode("<br>$sala_vista");

							}		

						}

					}

					?>
				<script>
				
					function detalle_actividad(){
						
						$("#detalle_actividad").modal("show");
					}
				
				</script>

                </div>
                <div class="tab-pane fade" id="bordered-justified-profile" role="tabpanel" aria-labelledby="profile-tab">
                  Nesciunt totam et. Consequuntur magnam aliquid eos nulla dolor iure eos quia. Accusantium distinctio omnis et atque fugiat. Itaque doloremque aliquid sint quasi quia distinctio similique. Voluptate nihil recusandae mollitia dolores. Ut laboriosam voluptatum dicta.
                </div>
                <div class="tab-pane fade" id="bordered-justified-salas" role="tabpanel" aria-labelledby="salas-tab">
                  SALAS. Saepe animi et soluta ad odit soluta sunt. Nihil quos omnis animi debitis cumque. Accusantium quibusdam perspiciatis qui qui omnis magnam. Officiis accusamus impedit molestias nostrum veniam. Qui amet ipsum iure. Dignissimos fuga tempore dolor.
                </div>
				<div class="tab-pane fade" id="bordered-justified-contact" role="tabpanel" aria-labelledby="contact-tab">
                  - Consultar los integrantes del curso (ucampus)
				  - Publicar todas las actividades
                </div>
              </div><!-- End Bordered Tabs Justified -->

            </div>
          

			<div class="modal fade" id="detalle_actividad" tabindex="-1">
				<div class="modal-dialog modal-xl">
				  <div class="modal-content">
					<div class="modal-header">
					  <h4 class="card-title">Detalle de la actividad, Jueves 30/04 08:30h</h4>
					  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div class="card">
						<div class="card-body">
						<div class="row">
						
						<div class="col-8">
						  <form>
							<div class="row mb-1 mt-3">
							  <label for="inputEmail3" class="col-sm-2 col-form-label">Título de la actividad</label>
							  <div class="col-sm-10">
								  <div class="card">
									<div class="card-body mt-3">
									  <!-- Quill Editor Default -->
									  <div class="quill-editor-default">
										<p>Hello World!</p>
										<p>This is Quill <strong>default</strong> editor</p>
									  </div>
									  <!-- End Quill Editor Default -->
									</div>
								  </div>
							  </div>
							</div>
							<div class="row mb-3">
							  <label for="inputPassword3" class="col-sm-2 col-form-label">Tipo de actividad</label>
							  <div class="col-sm-10">
								<select type="text" class="form-control">
									<option value="" selected>Clase</option>
								</select>
							  </div>
							</div>
							<div class="row mb-3">
							  <label for="inputPassword3" class="col-sm-2 col-form-label">Modalidad</label>
							  <div class="col-sm-10">
								<select type="text" class="form-control">
									<option value="" selected>Sincrónico</option>
								</select>
							  </div>
							</div>
							<div class="row mb-3">
							  <label for="inputEmail3" class="col-sm-2 col-form-label">Horario (bloque 1)</label>
							  <div class="col-sm-10">
								<input type="time" class="form-control" id="inicio" value="08:30">
								<input type="time" class="form-control mt-1" id="termino" value="10:00">
							  </div>
							</div>
							<div class="row mb-3">
							  <label for="inputPassword3" class="col-sm-2 col-form-label">Sala</label>
							  <div class="col-sm-10">
								<input type="text" class="form-control" id="inputPassword" value="Auditorio 1" disabled>
								<small><a href="modificar_sala.php">Solicitar modificación de sala</a></small>
							  </div>
							</div>
							<fieldset class="row mb-3">
							  <legend class="col-form-label col-sm-2 pt-0">Config. </legend>
							  <div class="col-sm-10">
								<div class="form-check form-switch">
								  <input class="form-check-input" type="checkbox" id="flexSwitchCheckDefault" checked>
								  <label class="form-check-label" for="flexSwitchCheckDefault">Asistencia obligatoria</label>
								</div>
								<div class="form-check form-switch">
								  <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked">
								  <label class="form-check-label" for="flexSwitchCheckChecked">Esta actividad es una evaluación</label>
								</div>
								<div class="form-check form-switch">
								  <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked">
								  <label class="form-check-label" for="flexSwitchCheckChecked">Compartir actividad con los y las estudiantes del curso</label>
								</div>
								
							  </div>
							</fieldset>
						  </form>
						</div>
						
						<div class="col-4 border" style="overflow: scroll; max-height: 600px;  " >
						  <h5 class="card-title">Docentes en la actividad (A->Z)</h5>
						  <div class="alert alert-info alert-dismissible fade show" role="alert">
							<i class="bi bi-info-circle"></i> Si no encuentras a docente en el listado, debes agregarlo en el apartado <b>Equipo Docente</b>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						  </div>
							<div class="row">
								<!-- Columna 2: Texto -->
								<div class="col-md-8 ">
								  <p>Seleccionar todo </p>
								</div>
								<!-- Columna 3: Switch -->
								<div class="col-md-2 ">
								  <div class="form-check form-switch">
									  <input class="form-check-input" type="checkbox" id="docente_check" >
									  <label class="form-check-label" for="docente_check"></label>
									</div>
								</div>
							</div>
							<div class="row">
								<!-- Columna 2: Texto -->
								<div class="col-md-8 ">
								  <p>Replicar en todas las actividades </p>
								</div>
								<!-- Columna 3: Switch -->
								<div class="col-md-2 ">
								  <div class="form-check form-switch">
									  <input class="form-check-input" type="checkbox" id="docente_check" >
									  <label class="form-check-label" for="docente_check"></label>
									</div>
								</div>
							</div>						  
							
						  <?php 
						  
							$equipo_docente = "SELECT A.rut,B.Funcionario,A.idTipoParticipacion 
												FROM spre_profesorescurso A
												LEFT JOIN spre_personas B ON B.Rut = A.rut
												WHERE idcurso = '$idCurso'
												AND A.idTipoParticipacion NOT IN (8,10)
												AND Vigencia = 1
												GROUP BY A.rut
												ORDER BY Funcionario ASC";				
							$equpo_docenteQ = mysqli_query($conexion3,$equipo_docente);
							
							while($fila_equipo = mysqli_fetch_assoc($equpo_docenteQ)){
								
								$foto = InfoDocenteUcampus($fila_equipo["rut"]);
          
							
						  
						  ?>
							<div class="row">
								<!-- Columna 1: Imagen -->
								<div class="col-md-3 ">
								  <img width='70%' src='<?php echo $foto; ?>' alt='Profile' class='rounded-circle mt-2'>
								</div>
								<!-- Columna 2: Texto -->
								<div class="col-md-7 ">
								  <p><?php echo utf8_encode($fila_equipo["Funcionario"]); ?></p>
								</div>
								<!-- Columna 3: Switch -->
								<div class="col-md-2 ">
								  <div class="form-check form-switch">
									  <input class="form-check-input" type="checkbox" id="docente_check" checked>
									  <label class="form-check-label" for="docente_check"></label>
									</div>
								</div>
							</div>
							
							<?php 
							
							}
							  
							  ?>
						  </div>
						
						
						</div>
					  </div>
					</div>
					<div class="modal-footer">
					  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
					  <button type="button" class="btn btn-success">Guardar cambios</button>
					</div>
				  </div>
				</div>
			</div><!-- End Extra Large Modal-->

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Facultad de Medicina Universidad de Chile</span></strong>. Todos los derechos reservados
    </div>
    <div class="credits">
      <!-- All the links in the footer should remain intact. -->
      <!-- You can delete the links only if you purchased the pro version. -->
      <!-- Licensing information: https://bootstrapmade.com/license/ -->
      <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
      Desarrollado por <a href="https://dpi.med.uchile.cl">Diseño de Procesos Internos (DPI)</a>
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