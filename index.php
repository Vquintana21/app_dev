<?php
//index.php 99677 ultimo profesor
include("conexion.php");

// Obtener el ID del curso desde la URL
$idCurso = $_GET['curso']; 
//$idCurso = 8942; // 8158
$rut = "0167847811";
//$ano = 2024; 
// Consulta SQL
$query = "SELECT `idplanclases`, pcl_tituloActividad, `pcl_Fecha`, `pcl_Inicio`, `pcl_Termino`, 
          `pcl_nSalas`, `pcl_Seccion`, `pcl_TipoSesion`, `pcl_SubTipoSesion`, 
          `pcl_Semana`, `pcl_AsiCodigo`, `pcl_AsiNombre`, `Sala`, `Bloque`, `dia`, `pcl_condicion`, `pcl_ActividadConEvaluacion`, pcl_BloqueExtendido
          FROM `planclases` 
          WHERE `cursos_idcursos` = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idCurso);
$stmt->execute();
$result = $stmt->get_result();

// Convertir resultado a array
$planclases = [];
while ($row = $result->fetch_assoc()) {
    $planclases[] = $row;
}

// Convertir a JSON para usar en JavaScript
$planclasesJson = json_encode($planclases);

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

//Consulta Funcionario
$spre_personas = "SELECT * FROM spre_personas WHERE Rut='$rut' ";
$spre_personasQ = mysqli_query($conexion3,$spre_personas);
$fila_personas = mysqli_fetch_assoc($spre_personasQ);

$funcionario = utf8_encode($fila_personas["Funcionario"]);

// Consulta para obtener tipos de sesión
$queryTipos = "SELECT `id`, `tipo_sesion`, `Sub_tipo_sesion`, `tipo_activo`, `subtipo_activo`, `pedir_sala`, `docentes` FROM `pcl_TipoSesion`";
$resultTipos = $conn->query($queryTipos);

// Convertir resultado a array
$tiposSesion = [];
while ($row = $resultTipos->fetch_assoc()) {
    $tiposSesion[] = $row;
}

// Convertir a JSON para usar en JavaScript
$tiposSesionJson = json_encode($tiposSesion);

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

//Consulta para obtener horas no presenciales
$queryHoras = "SELECT C.idcurso, A.`HNPSemanales`, concat(FLOOR(HNPSemanales),':',LPAD(ROUND((HNPSemanales - FLOOR(HNPSemanales)) * 60),2,'0')) AS tiempo 
               FROM `spre_maestropresencialidad` A 
               JOIN spre_ramosperiodo B ON A.SCT = B.SCT AND A.Semanas = B.NroSemanas AND A.idTipoBloque = B.idTipoBloque 
               JOIN spre_cursos C ON B.CodigoCurso = C.CodigoCurso 
               WHERE C.idcurso = ? and B.idPeriodo=C.idperiodo";

$stmtHoras = $conexion3->prepare($queryHoras);
$stmtHoras->bind_param("i", $idCurso);
$stmtHoras->execute();
$resultHoras = $stmtHoras->get_result();
$horasData = $resultHoras->fetch_assoc();

// Convertir a minutos para facilitar cálculos
$horasSemanales = isset($horasData['HNPSemanales']) ? $horasData['HNPSemanales'] : 0;
$horasSemanalesJson = json_encode($horasSemanales);

// Cerrar conexión
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Académico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	
	 <!-- Vendor CSS Files 
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
  -->
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
    
</head>
<body>

 <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
      <a href="inicio.php" class="logo d-flex align-items-center">
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
	   <li class="nav-item">
        <a class="nav-link " href="index.php?curso=<?php echo $idCurso; ?>">+
		
		
		
          <i class="bi bi-grid"></i>
          <span>Calendario</span>
        </a>
      </li>
    </ul>
  </aside>

 <main id="main" class="main">
    <div class="pagetitle">
        <h1><?php echo $codigo_curso."-".$seccion; ?> <?php echo $nombre_curso; ?></h1>
        <small style="float: right;">ID curso: <?php echo $idCurso; ?></small>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Inicio</a></li>
                <li class="breadcrumb-item active">Plan de clases <?php echo $codigo_curso."-".$seccion; ?> <?php echo $nombre_curso; ?></li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">    
       <div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Editar información</h5>
                    <!-- Bordered Tabs Justified -->
                    <ul class="nav nav-tabs nav-tabs-bordered d-flex" id="borderedTabJustified" role="tablist">
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100 active" id="home-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-home" type="button" role="tab" aria-controls="home" aria-selected="true"><i class="bi bi-calendar4-week"></i> Calendario </button>
                        </li>
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100" id="docente-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-docente" type="button" role="tab" aria-controls="docente" aria-selected="false"><i class="ri ri-user-settings-line"></i> Equipo docente</button>
                        </li>
						<li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100" id="docente-masivo-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-docente-masivo" type="button" role="tab" aria-controls="docente-masivo" aria-selected="false"><i class="ri ri-user-settings-line"></i> Asignar docentes Masivo</button>
                        </li>
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100" id="salas-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-salas" type="button" role="tab" aria-controls="salas" aria-selected="false"><i class="ri ri-map-pin-line"></i> Salas</button>
                        </li>				
                    </ul>
                </div>
                
                <!-- Contenido de las pestañas -->
                <div class="tab-content" id="borderedTabJustifiedContent">
                    <!-- Tab Calendario -->
                    <div class="tab-pane fade show active" id="bordered-justified-home" role="tabpanel" aria-labelledby="home-tab">
                        <div class="card-body">
                            <h4 class="mb-0">Calendario Académico<span>/ Hoy es <?php echo date("d-m-Y"); ?></span></h4>
                            </br>
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><i class="bi bi-circle-fill text-primary"></i> <small>Actividad regular (Clase, Actividad Grupal, Trabajo Práctico, Evaluación, Examen)</small></li>
                                    <li class="breadcrumb-item"><i class="bi bi-circle-fill text-secondary"></i> <small>Actividad de autoaprendizaje</small></li>
                                </ol>
                            </nav>
                        </div>
                        <div class="card-body" id="calendar-container">
                            <!-- Aquí se generará el calendario -->
                        </div>
                    </div>

                    <!-- Tab Equipo docente -->
                    <div class="tab-pane fade" id="bordered-justified-docente" role="tabpanel" aria-labelledby="docente-tab">
                        <div id="docentes-list">
                            <!-- Aquí irá el contenido de docentes -->
                        </div>
                    </div>
					 <!-- Tab Equipo docente -->
                    <div class="tab-pane fade" id="bordered-justified-docente-masivo" role="tabpanel" aria-labelledby="docente-masivo-tab">
                        <div id="docentes-masivo-list">
                            <!-- Aquí irá el contenido de docentes -->
                        </div>
                    </div>

                    <!-- Tab Salas -->
                    <div class="tab-pane fade" id="bordered-justified-salas" role="tabpanel" aria-labelledby="salas-tab">
                        <div id="salas-list">
                            <!-- Aquí se cargará el contenido de salas -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Modal actividades -->
        <div class="modal fade" id="activityModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="card-title">Detalle de la actividad <span id="modal-idplanclases"></span></h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <form id="activityForm">
                                            <input type="hidden" id="idplanclases" name="idplanclases">
                                            <div class="row mb-3">
                                                <label class="col-sm-2 col-form-label">Título de la actividad</label>
                                                <div class="col-sm-10">  
                                                                <textarea class="form-control" id="activity-title" rows="3"  name="activity-title"  ></textarea>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
												<label class="col-sm-2 col-form-label">Tipo actividad</label>
												<div class="col-sm-10">
													<select class="form-control" id="activity-type" name="type" onchange="updateSubTypes()">
														<!-- Se llenará dinámicamente -->
													</select>
												</div>
											</div>

											<div class="row mb-3" id="subtype-container" style="display: none;">
												<label class="col-sm-2 col-form-label">Sub Tipo actividad</label>
												<div class="col-sm-10">
													<select class="form-control" id="activity-subtype" name="subtype">
														<!-- Se llenará dinámicamente -->
													</select>
												</div>
											</div>
                                            <div class="row mb-3">
												<label class="col-sm-2 col-form-label">Horario</label>
												<div class="col-sm-10">
													<div class="row">
														<div class="col-6">
															<label class="form-label">Inicio</label>
															<select class="form-select" id="start-time" name="start_time">
															</select>
														</div>
														<div class="col-6">
															<label class="form-label">Término</label>
															<select class="form-select" id="end-time" name="end_time">
															</select>
														</div>
													</div>
												</div>
											</div>
                                            <div class="row mb-3" hidden>
                                                <label class="col-sm-2 col-form-label">Sala</label>
                                                <div class="col-sm-10">
                                                    <input type="text" class="form-control" id="room" name="room" disabled>
                                                    <small><a href="#">Solicitar modificación de sala</a></small>
                                                </div>
                                            </div>
                                            <fieldset class="row mb-3">
                                                <legend class="col-form-label col-sm-2 pt-0">Config.</legend>
                                                <div class="col-sm-10">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="mandatory" name="mandatory">
                                                        <label class="form-check-label">Asistencia obligatoria</label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="is-evaluation" name="is_evaluation">
                                                        <label class="form-check-label">Esta actividad incluye una evaluación</label>
                                                    </div>
                                                </div>
                                            </fieldset>
                                        </form>
                                    </div>
                                    <div class="col-4 border" id="docentes-container" style="overflow: scroll; max-height: 600px;">
                                        <!-- El contenido de docentes se cargará dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-success" onclick="saveActivity()">Guardar cambios</button>
                    </div>
                </div>
            </div>
        </div>
		
		
<!-- Modal autoaprendizaje -->
<div class="modal fade" id="autoaprendizajeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="card-title">Actividad de Autoaprendizaje - Semana <span id="auto-week"></span></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
				<form id="autoaprendizajeForm">
					<input type="hidden" id="auto-idplanclases" name="idplanclases">
					<div class="mb-3">
						<label class="form-label">Título de la actividad</label>
						<textarea class="form-control" id="auto-activity-title" name="activity-title" rows="3"></textarea>
					</div>
					<div class="mb-3" id="auto-time-fields">
						<label class="form-label">Tiempo asignado</label>
						<div class="row">
							<div class="col-6">
								<div class="input-group">
									<input type="number" class="form-control" id="auto-hours" name="hours" min="0" max="23" placeholder="Horas">
									<span class="input-group-text">hrs</span>
								</div>
							</div>
							<div class="col-6">
								<div class="input-group">
									<input type="number" class="form-control" id="auto-minutes" name="minutes" min="0" max="59" placeholder="Minutos">
									<span class="input-group-text">min</span>
								</div>
							</div>
						</div>
						<small class="text-muted">Tiempo máximo semanal: <span id="max-hours"><?php echo $horasData['tiempo']; ?></span></small>
					</div>
					<div class="alert alert-warning" id="auto-no-hours-message" style="display: none;">
						Este curso no posee horas NO presenciales asignadas.
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-success" id="save-auto-btn" onclick="saveAutoActivity()">Guardar</button>
			</div>
        </div>
    </div>
</div>
       
    </section>
</main>
		 
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Facultad de Medicina Universidad de Chile</span></strong>. Todos los derechos reservados
    </div>
    <div class="credits">
      
      Desarrollado por <a href="https://dpi.med.uchile.cl">Diseño de Procesos Internos (DPI)</a>
    </div>
  </footer>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	 
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  
  <script src="assets/js/main.js"></script>
	
	<!-- FUNCIONES DE ACTIVIDADES Y DONCETES-->
   <script>
    let planClases = <?php echo $planclasesJson; ?>;

let tiposSesion = <?php echo $tiposSesionJson; ?>;

const horasSemanales = <?php echo $horasSemanalesJson; ?>;

function validateAutoTime(hours, minutes) {
    const totalHours = hours + (minutes / 60);
    return totalHours <= horasSemanales;
}

function loadActivityTypes() {
    const selectTipo = document.getElementById('activity-type');
    selectTipo.innerHTML = '<option value="">Seleccione un tipo</option>';
    
    // Crear un Set para almacenar tipos únicos
    const tiposUnicos = new Set();
    
    tiposSesion.forEach(tipo => {
        if (tipo.tipo_activo === "1" && !tiposUnicos.has(tipo.tipo_sesion)) {
            tiposUnicos.add(tipo.tipo_sesion);
            const option = new Option(tipo.tipo_sesion, tipo.tipo_sesion);
            selectTipo.add(option);
        }
    });
}

function updateSubTypes() {
    const tipoSeleccionado = document.getElementById('activity-type').value;
    const subtypeContainer = document.getElementById('subtype-container');
    const selectSubtipo = document.getElementById('activity-subtype');
    const docentesContainer = document.getElementById('docentes-container').closest('.col-4');
    
    // Encontrar el tipo seleccionado en el array
    const tipoInfo = tiposSesion.find(t => t.tipo_sesion === tipoSeleccionado);
    
    if (!tipoInfo) return;
    
    // Manejar visibilidad de docentes
    if (tipoInfo.docentes === "1") {
        docentesContainer.style.display = 'block';  // Volvemos a block para el contenedor de docentes
    } else {
        docentesContainer.style.display = 'none';
    }
    
    // Manejar subtipo
    if (tipoInfo.subtipo_activo === "1") {
        subtypeContainer.style.display = ''; // Removemos el display para que use el valor por defecto
        selectSubtipo.innerHTML = '<option value="">Seleccione un subtipo</option>';
        
        // Filtrar y agregar subtipos correspondientes
        tiposSesion
            .filter(t => t.tipo_sesion === tipoSeleccionado && t.Sub_tipo_sesion)
            .forEach(st => {
                const option = new Option(st.Sub_tipo_sesion, st.Sub_tipo_sesion);
                selectSubtipo.add(option);
            });
    } else {
        subtypeContainer.style.display = 'none';
    }
}

    function getMonthRange() {
        if (!planClases || planClases.length === 0) return [];
        
        const dates = planClases.map(activity => new Date(activity.pcl_Fecha));
        const firstDate = new Date(Math.min.apply(null, dates));
        const lastDate = new Date(Math.max.apply(null, dates));
        
        const months = [];
        const currentDate = new Date(firstDate);
        
        while (currentDate <= lastDate) {
            months.push(new Date(currentDate));
            currentDate.setMonth(currentDate.getMonth() + 1);
        }
        
        return months;
    }

     function createActivityButton(activity) {
    const button = document.createElement('button');
	
	 // Verificar primero si es un feriado
    if (activity.pcl_TipoSesion === 'Feriado') {
         button.className = 'btn btn-lg activity-button btn-light feriado';  // Estilo light de Bootstrap
        button.innerHTML = `<div class="activity-title">Feriado</div>`;
        // No agregamos data-bs-toggle ni onclick para que no sea clickeable
        return button;
    }
    
    if (activity.pcl_TipoSesion === 'Autoaprendizaje') {
        button.className = 'btn btn-lg activity-button autoaprendizaje';
        button.setAttribute('data-bs-toggle', 'modal');
        button.setAttribute('data-bs-target', '#autoaprendizajeModal');
        
        let content = '';
        if (activity.pcl_tituloActividad) {
            content = `<div class="activity-title">${activity.pcl_tituloActividad}</div>`;
        } else {
            content = `<div class="activity-title">Autoaprendizaje <i class="fas fa-plus"></i></div>`;
        }
        button.innerHTML = content;
        button.onclick = () => loadAutoActivityData(activity);
    } else {
        const isCompleted = activity.estado === 'completed';
        button.className = `btn btn-lg activity-button ${isCompleted ? 'completed' : 'default'}`;
        button.setAttribute('data-bs-toggle', 'modal');
        button.setAttribute('data-bs-target', '#activityModal');
        
        let content = '';
        if (activity.pcl_tituloActividad) {
            content = `
                <div class="class-time">${activity.pcl_Inicio.substring(0,5)} - ${activity.pcl_Termino.substring(0,5)}</div>
                <div class="activity-title">${activity.pcl_tituloActividad}</div>
            `;
        } else {
            content = `
                <div class="class-time">${activity.pcl_Inicio.substring(0,5)} - ${activity.pcl_Termino.substring(0,5)}</div>
                <div class="activity-title" style="color: #ffc107;">
                    <i class="fas fa-plus"></i> Agregar actividad
                </div>
            `;
        }
        button.innerHTML = content;
        button.onclick = () => loadActivityData(activity);
    }
    
    return button;
}

function loadAutoActivityData(activity) {
   document.getElementById('auto-idplanclases').value = activity.idplanclases;
   document.getElementById('auto-activity-title').value = activity.pcl_tituloActividad || '';
   document.getElementById('auto-week').textContent = activity.pcl_Semana;
   
   // Verificar si hay horas no presenciales disponibles
   if (horasSemanales <= 0) {
       // Ocultar campos de tiempo y mostrar mensaje
       document.getElementById('auto-time-fields').style.display = 'none';
       document.getElementById('auto-no-hours-message').style.display = 'block';
       document.getElementById('save-auto-btn').disabled = true;
   } else {
       // Mostrar campos de tiempo y ocultar mensaje
       document.getElementById('auto-time-fields').style.display = 'block';
       document.getElementById('auto-no-hours-message').style.display = 'none';
       document.getElementById('save-auto-btn').disabled = false;
       
       // Si ya tiene horas asignadas, mostrarlas en el formulario
       if (activity.pcl_HorasNoPresenciales) {
           const horasMatch = activity.pcl_HorasNoPresenciales.match(/(\d+):(\d+):(\d+)/);
           if (horasMatch) {
               document.getElementById('auto-hours').value = parseInt(horasMatch[1]);
               document.getElementById('auto-minutes').value = parseInt(horasMatch[2]);
           }
       }
   }
}
	
	 // Modificar la función loadActivityData existente
function loadActivityData(activity) {
	  console.log('Datos de actividad recibidos:', activity); // Para debug
    // Actualizar los campos del modal
    document.getElementById('modal-idplanclases').textContent = activity.idplanclases;
    document.getElementById('idplanclases').value = activity.idplanclases;
    
	 // Actualizar título y ajustar altura
    const titleTextarea = document.getElementById('activity-title');
    titleTextarea.value = activity.pcl_tituloActividad;
    
	document.getElementById('activity-type').value = activity.pcl_TipoSesion;
    updateSubTypes(); // Esto actualizará la visibilidad y opciones del subtipo
    
    if (document.getElementById('subtype-container').style.display !== 'none') {
        document.getElementById('activity-subtype').value = activity.pcl_SubTipoSesion;
    }
    document.getElementById('room').value = activity.Sala;
	
    document.getElementById('activity-type').value = activity.pcl_TipoSesion;
    document.getElementById('room').value = activity.Sala;

   // Verificar si tenemos un Bloque asignado
    if (!activity.Bloque) {
        console.log('Actividad sin bloque asignado - usando horarios directos');
        generateTimeOptions(activity.pcl_Inicio, activity.pcl_Termino, 
                          activity.pcl_Inicio, activity.pcl_Termino);
        return;
    }

    // Extraer el número de bloque usando expresión regular
    const bloqueMatch = activity.Bloque.match(/\d+/);
    if (!bloqueMatch) {
        console.log('Formato de bloque inválido:', activity.Bloque);
        generateTimeOptions(activity.pcl_Inicio, activity.pcl_Termino, 
                          activity.pcl_Inicio, activity.pcl_Termino);
        return;
    }

    const bloqueNumero = bloqueMatch[0];
   // Convertir pcl_BloqueExtendido a string para comparaciones consistentes
    const bloqueExtendido = String(activity.pcl_BloqueExtendido);
    
    console.log('Datos del bloque:', {
        bloqueOriginal: activity.Bloque,
        bloqueNumero: bloqueNumero,
        pcl_BloqueExtendido: activity.pcl_BloqueExtendido,
        esExtendido: bloqueExtendido === "1",
        horariosActuales: {
            inicio: activity.pcl_Inicio,
            termino: activity.pcl_Termino
        }
    });

    fetch(`get_horario_bloque.php?bloque=${bloqueNumero}&extendido=${bloqueExtendido}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error en la respuesta: ${response.status}`);
            }
            return response.json();
        })
        .then(horarioBloque => {
            if (!horarioBloque || (!horarioBloque.inicio && !horarioBloque.inicio_ext)) {
                throw new Error('Datos de horario incompletos');
            }

            // Usar la versión convertida a string para la comparación
            const inicio = bloqueExtendido === "1" ? 
                          horarioBloque.inicio_ext : 
                          horarioBloque.inicio;
            const termino = bloqueExtendido === "1" ? 
                           horarioBloque.termino_ext : 
                           horarioBloque.termino;

            console.log('Horarios del bloque obtenidos:', {
                inicio: inicio,
                termino: termino,
                esExtendido: bloqueExtendido === "1",
                valorBloqueExtendido: bloqueExtendido
            });

            generateTimeOptions(inicio, termino, activity.pcl_Inicio, activity.pcl_Termino);
        })
        .catch(error => {
            console.error('Error al procesar horarios:', error);
            generateTimeOptions(activity.pcl_Inicio, activity.pcl_Termino, 
                              activity.pcl_Inicio, activity.pcl_Termino);
        });
    
	  // Verificamos que existan los campos antes de usarlos
    if ('pcl_condicion' in activity) {
        const mandatoryCheckbox = document.getElementById('mandatory');
        mandatoryCheckbox.checked = activity.pcl_condicion === "Obligatorio";
    }
    
    if ('pcl_ActividadConEvaluacion' in activity) {
        const evaluationCheckbox = document.getElementById('is-evaluation');
        evaluationCheckbox.checked = activity.pcl_ActividadConEvaluacion === "S";
    }
	
    // Cargar los docentes
    loadDocentes(activity.idplanclases);
}

function generateTimeOptions(bloqueInicio, bloqueTermino, selectedStart, selectedEnd) {
    const startSelect = document.getElementById('start-time');
    const endSelect = document.getElementById('end-time');
    
    // Limpiar selects
    startSelect.innerHTML = '';
    endSelect.innerHTML = '';
    
    // Convertir strings de tiempo a objetos Date
    const rangoInicio = parseTimeString(bloqueInicio);
    const rangoFin = parseTimeString(bloqueTermino);
    
    // Generar opciones cada 5 minutos dentro del rango del bloque
    const currentTime = new Date(rangoInicio);
    
    while (currentTime <= rangoFin) {
        const timeString = formatTimeString(currentTime);
        
        // Agregar opción al select de inicio
        const startOption = new Option(timeString, timeString);
        startOption.selected = timeString === selectedStart;
        startSelect.add(startOption);
        
        // Agregar opción al select de término
        const endOption = new Option(timeString, timeString);
        endOption.selected = timeString === selectedEnd;
        endSelect.add(endOption);
        
        // Incrementar 5 minutos
        currentTime.setMinutes(currentTime.getMinutes() + 5);
    }
    
    // Mantener la lógica de validación existente
    startSelect.addEventListener('change', function() {
        if (endSelect.value < this.value) {
            endSelect.value = this.value;
        }
    });
    
    endSelect.addEventListener('change', function() {
        if (startSelect.value > this.value) {
            startSelect.value = this.value;
        }
    });
}

// Función auxiliar para convertir string de tiempo a objeto Date
function parseTimeString(timeString) {
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes), 0, 0);
    return date;
}

// Función auxiliar para formatear tiempo
function formatTimeString(date) {
    return date.toTimeString().substring(0, 8);
}


   function loadDocentes(idplanclases) {
    const docentesContainer = document.getElementById('docentes-container');
    docentesContainer.innerHTML = '<h5 class="card-title">Cargando docentes...</h5>';
    
    fetch(`get_docentes.php?idplanclases=${idplanclases}`)
        .then(response => response.text())
        .then(html => {
            docentesContainer.innerHTML = html;
            // Agregar el event listener después de cargar el contenido
            setupDocentesEvents();
        })
        .catch(error => {
            docentesContainer.innerHTML = '<div class="alert alert-danger">Error al cargar docentes</div>';
        });
}

function setupDocentesEvents() {
    const selectAllCheckbox = document.getElementById('selectAllDocentes');
    
    // Event listener para "Seleccionar todo"
    selectAllCheckbox.addEventListener('change', function() {
        const docenteCheckboxes = document.querySelectorAll('.docente-check');
        docenteCheckboxes.forEach(docente => {
            docente.checked = this.checked;
        });
    });

    // Event listener para checkboxes individuales
    document.getElementById('docentes-container').addEventListener('change', function(e) {
        if (e.target.classList.contains('docente-check')) {
            const docenteCheckboxes = document.querySelectorAll('.docente-check');
            const allChecked = Array.from(docenteCheckboxes).every(checkbox => checkbox.checked);
            selectAllCheckbox.checked = allChecked;
        }
    });
}
	
	function setupTimeRestrictions(startInput, endInput, originalStart, originalEnd) {
        function validateTime(value, min, max) {
            if (value < min) return min;
            if (value > max) return max;
            return value;
        }
        
        startInput.addEventListener('change', function() {
            this.value = validateTime(this.value, originalStart, originalEnd);
            if (this.value > endInput.value) {
                endInput.value = this.value;
            }
        });
        
        endInput.addEventListener('change', function() {
            this.value = validateTime(this.value, originalStart, originalEnd);
            if (this.value < startInput.value) {
                startInput.value = this.value;
            }
        });
    }

         

    // Función para guardar los cambios
  function saveActivity() {
    const form = document.getElementById('activityForm');
    const formData = new FormData();
    
    // Agregar campos al formData...
    formData.append('idplanclases', document.getElementById('idplanclases').value);
    formData.append('activity-title', document.getElementById('activity-title').value);
    formData.append('type', document.getElementById('activity-type').value);
    
    // Manejar el subtipo para Clase
    const tipoActividad = document.getElementById('activity-type').value;
    if (tipoActividad === 'Clase') {
        formData.append('subtype', 'Clase teórica o expositiva');
    } else {
        formData.append('subtype', document.getElementById('activity-subtype').value);
    }
	
    formData.append('start_time', document.getElementById('start-time').value);
    formData.append('end_time', document.getElementById('end-time').value);
    formData.append('mandatory', document.getElementById('mandatory').checked);
    formData.append('is_evaluation', document.getElementById('is-evaluation').checked);

    // Calcular las horas de la actividad
    const startTime = document.getElementById('start-time').value;
    const endTime = document.getElementById('end-time').value;
    const start = new Date(`2000-01-01 ${startTime}`);
    const end = new Date(`2000-01-01 ${endTime}`);
    const horasActividad = (end - start) / (1000 * 60 * 60);

    // Obtener docentes seleccionados
    const docentesSeleccionados = [];
    document.querySelectorAll('.docente-check:checked').forEach(checkbox => {
        docentesSeleccionados.push(checkbox.dataset.rut);
    });

    // Primero guardar la actividad
    fetch('guardar_actividad.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error('Error al guardar la actividad');
        }

        const docentesData = new FormData();
        const idplanclases = document.getElementById('idplanclases').value;
        const idcurso = new URLSearchParams(window.location.search).get('curso');
        
        docentesData.append('idplanclases', idplanclases);
        docentesData.append('idcurso', idcurso);
        docentesData.append('horas', horasActividad);
        docentesData.append('docentes', JSON.stringify(docentesSeleccionados));

        // Luego guardar los docentes
        return fetch('guardar_docentes.php', {
            method: 'POST',
            body: docentesData
        });
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modalElement = document.getElementById('activityModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
            
            // Crear contenedor de toast si no existe
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }

            // Limpiar toasts anteriores
            toastContainer.innerHTML = '';
            
            // Crear y mostrar el toast
            const toastHTML = `
                <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-check-circle me-2"></i>
                            Actividad guardada correctamente
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = new bootstrap.Toast(toastContainer.lastElementChild, {
                autohide: true,
                delay: 3000
            });
            toastElement.show();
            
            // Recargar página después de mostrar el toast
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(error => {
        mostrarToast('Error al guardar los cambios: ' + error.message, 'danger');
    });
}

// Función auxiliar para mostrar toasts
function mostrarToast(mensaje, tipo) {
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    const toastHTML = `
        <div class="toast align-items-center text-white bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${tipo === 'success' ? 'check-circle' : 'x-circle'} me-2"></i>
                    ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toast = new bootstrap.Toast(toastContainer.lastElementChild);
    toast.show();
}
	
	// Función auxiliar para obtener el rango de fechas de la semana
function getWeekDates(activity) {
    const fecha = new Date(activity.pcl_Fecha);
    const diaSemana = fecha.getDay();
    
    // Obtener el lunes de la semana
    const lunes = new Date(fecha);
    lunes.setDate(fecha.getDate() - (diaSemana - 1));
    
    // Obtener el viernes de la semana
    const viernes = new Date(lunes);
    viernes.setDate(lunes.getDate() + 4);
    
    return {
        inicio: lunes.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' }),
        fin: viernes.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' })
    };
}

function generateCalendar(activitiesForMonth, calendarBody, currentMonth) {
    const weeklyActivities = new Map();
    
    // Primero ordenamos las actividades por fecha
    activitiesForMonth.sort((a, b) => new Date(a.pcl_Fecha) - new Date(b.pcl_Fecha));
    
    // Agrupar por semana
    activitiesForMonth.forEach(activity => {
        const weekNumber = parseInt(activity.pcl_Semana);
        if (weekNumber === 0) return;
        
        const activityDate = new Date(activity.pcl_Fecha);
        const weekKey = `week-${weekNumber}`;
        
        if (!weeklyActivities.has(weekKey)) {
            weeklyActivities.set(weekKey, {
                weekNum: weekNumber,
                activities: [],
                startDate: activityDate
            });
        }
        
        weeklyActivities.get(weekKey).activities.push(activity);
    });

    // Convertir el Map a Array y ordenar por semana
    const sortedWeeks = Array.from(weeklyActivities.values())
        .filter(week => week.activities.length > 0)
        .sort((a, b) => a.weekNum - b.weekNum);

    if (sortedWeeks.length === 0) {
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = `
            <td colspan="7" class="text-center py-4">
                No hay actividades programadas para este mes
            </td>
        `;
        calendarBody.appendChild(emptyRow);
        return;
    }

    sortedWeeks.forEach(week => {
        const weekRow = document.createElement('tr');
        
        // Celda de semana
        const weekCell = document.createElement('td');
        weekCell.className = 'week-number';
        const weekDates = getWeekDates(week.activities[0]);
        weekCell.innerHTML = `Semana ${week.weekNum}<br>(${weekDates.inicio} - ${weekDates.fin})`;
        weekRow.appendChild(weekCell);
        
        // Celdas de días
        ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'].forEach(day => {
            const dayCell = document.createElement('td');
            dayCell.className = 'calendar-cell';
            
            // Actividades para este día que pertenecen a este mes
            const dayActivities = week.activities
                .filter(activity => {
                    if (activity.dia !== day) return false;
                    
                    const activityDate = new Date(activity.pcl_Fecha);
                    return activityDate.getMonth() === currentMonth;
                })
                .sort((a, b) => a.pcl_Inicio.localeCompare(b.pcl_Inicio));
            
            dayActivities.forEach(activity => {
                const button = createActivityButton(activity);
                dayCell.appendChild(button);
            });
            
            weekRow.appendChild(dayCell);
        });
        
        // Celda de autoaprendizaje
        const autoCell = document.createElement('td');
        autoCell.className = 'calendar-cell autoaprendizaje';
        
        // Solo mostrar autoaprendizaje
        const autoActivity = week.activities.find(activity => 
            activity.pcl_TipoSesion === 'Autoaprendizaje'
        );
        
        if (autoActivity) {
            const button = createActivityButton(autoActivity);
            autoCell.appendChild(button);
        }
        
        weekRow.appendChild(autoCell);
        calendarBody.appendChild(weekRow);
    });
}


function generateFullCalendar() {
    const months = getMonthRange();
    const container = document.getElementById('calendar-container');
    container.innerHTML = '';

    months.forEach(date => {
        const monthSection = document.createElement('div');
        monthSection.className = 'month-section mb-4';

        const monthHeader = document.createElement('h3');
        monthHeader.className = 'mb-3';
        monthHeader.textContent = date.toLocaleString('es-ES', { 
            month: 'long', 
            year: 'numeric' 
        }).charAt(0).toUpperCase() + date.toLocaleString('es-ES', { 
            month: 'long', 
            year: 'numeric' 
        }).slice(1);
        monthSection.appendChild(monthHeader);

        // Filtrar actividades para este mes
        const monthActivities = planClases.filter(activity => {
            const activityDate = new Date(activity.pcl_Fecha);
            
            // Para actividades regulares, solo mostrar si pertenecen a este mes
            if (activity.pcl_TipoSesion !== 'Autoaprendizaje') {
                return activityDate.getMonth() === date.getMonth() && 
                       activityDate.getFullYear() === date.getFullYear();
            }
            
            // Para actividades de autoaprendizaje
            if (activity.pcl_TipoSesion === 'Autoaprendizaje') {
                const weekNumber = parseInt(activity.pcl_Semana);
                
                // Encontrar todas las actividades regulares de esta semana
                const regularActivities = planClases.filter(a => 
                    parseInt(a.pcl_Semana) === weekNumber && 
                    a.pcl_TipoSesion !== 'Autoaprendizaje'
                );
                
                if (regularActivities.length === 0) return false;
                
                // Obtener el último día de la semana
                const lastDayOfWeek = regularActivities
                    .map(a => new Date(a.pcl_Fecha))
                    .sort((a, b) => b - a)[0];
                
                // El autoaprendizaje solo aparece en el mes que contiene el último día de la semana
                return lastDayOfWeek.getMonth() === date.getMonth() && 
                       lastDayOfWeek.getFullYear() === date.getFullYear();
            }
            
            return false;
        });

        const table = document.createElement('div');
        table.className = 'table-responsive';
        table.innerHTML = `
            <table class="table table-bordered">
                <thead class="calendar-header">
                    <tr>
                        <th style="width: 8%">Semana</th>
                        <th>Lunes</th>
                        <th>Martes</th>
                        <th>Miércoles</th>
                        <th>Jueves</th>
                        <th>Viernes</th>
                        <th style="width: 15%">Autoaprendizaje</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        `;
        
        generateCalendar(monthActivities, table.querySelector('tbody'), date.getMonth());
        monthSection.appendChild(table);
        container.appendChild(monthSection);
    });
}


    document.addEventListener('DOMContentLoaded', () => {
        generateFullCalendar();
		loadActivityTypes();
		
    // Obtener el ID del curso de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const cursoId = urlParams.get('curso');

    // Agregar evento para cargar salas
    document.getElementById('salas-tab').addEventListener('click', function() {
        fetch('salas2.php?curso=' + cursoId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('salas-list').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('salas-list').innerHTML = '<div class="alert alert-danger">Error al cargar la información de salas</div>';
            });
    });
	
	
	 // Agregar evento para cargar docente
    document.getElementById('docente-tab').addEventListener('click', function() {
    const docentesList = document.getElementById('docentes-list');
    
    // Mostrar spinner de carga
    docentesList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando...</p></div>';
    
    fetch('1_asignar_docente.php?idcurso=' + cursoId)
        .then(response => response.text())
        .then(html => {
            docentesList.innerHTML = html;
        })
        .catch(error => {
            docentesList.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
        });
});

 // Agregar evento para cargar docentes masivos
document.getElementById('docente-masivo-tab').addEventListener('click', function() {
    const docentesMasivoList = document.getElementById('docentes-masivo-list');
    
    // Mostrar spinner de carga
    docentesMasivoList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando...</p></div>';
    
    fetch('asignacion_masiva_docentes.php?curso=' + cursoId)
        .then(response => response.text())
        .then(html => {
            docentesMasivoList.innerHTML = html;
        })
        .catch(error => {
            docentesMasivoList.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
        });
});
	
 // Validación en tiempo real del tiempo asignado
    const hoursInput = document.getElementById('auto-hours');
    const minutesInput = document.getElementById('auto-minutes');
    
    function validateInputs() {
        const hours = parseInt(hoursInput.value) || 0;
        const minutes = parseInt(minutesInput.value) || 0;
        
        if (!validateAutoTime(hours, minutes)) {
            hoursInput.classList.add('is-invalid');
            minutesInput.classList.add('is-invalid');
        } else {
            hoursInput.classList.remove('is-invalid');
            minutesInput.classList.remove('is-invalid');
        }
    }
    
    hoursInput.addEventListener('input', validateInputs);
    minutesInput.addEventListener('input', validateInputs);
});
</script>

<script>
// Función global para eliminar docentes
window.eliminarDocente = function(id) {
    if(!id) return;
    
    $.ajax({
        url: 'eliminar_docente.php',
        type: 'POST',
        data: { idProfesoresCurso: id },
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                // Eliminar la fila
                var $btn = $(`button[onclick="eliminarDocente(${id})"]`);
                var $row = $btn.closest('tr');
                
                $row.fadeOut(300, function() {
                    $(this).remove();
                });

                // Crear y mostrar toast
                const toastHTML = `
                    <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi bi-check-circle me-2"></i>
                                Docente removido exitosamente
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                
                $('.toast-container').append(toastHTML);
                const toastElement = new bootstrap.Toast($('.toast').last(), {
                    autohide: true,
                    delay: 2000
                });
                toastElement.show();
            }
        }
    });
};
</script>
<script>
// Función global para actualizar función de docente
window.actualizarFuncion = function(selectElement, idProfesoresCurso) {
    const nuevoTipo = selectElement.value;
    
    $.ajax({
        url: 'guardarFuncion.php',
        type: 'POST',
        data: { 
            idProfesoresCurso: idProfesoresCurso,
            idTipoParticipacion: nuevoTipo
        },
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                // Mostrar toast de éxito
                const toastHTML = `
                    <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi bi-check-circle me-2"></i>
                                Función actualizada exitosamente
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                
                $('.toast-container').append(toastHTML);
                const toastElement = new bootstrap.Toast($('.toast').last(), {
                    autohide: true,
                    delay: 2000
                });
                toastElement.show();
            }
        },
        error: function() {
            // Mostrar toast de error
            const toastHTML = `
                <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-x-circle me-2"></i>
                            Error al actualizar la función
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            $('.toast-container').append(toastHTML);
            const toastElement = new bootstrap.Toast($('.toast').last(), {
                autohide: true,
                delay: 2000
            });
            toastElement.show();
        }
    });
};
</script>

<!-- FUNCIONES A REVISAR-->

<script>
// Funciones para manejar las acciones de sala
// function solicitarSala(id) {
//   document.getElementById('idplanclases').value = id;
//   document.getElementById('salaModalTitle').textContent = 'Solicitar Sala';
//   const modal = new bootstrap.Modal(document.getElementById('salaModal'));
//   modal.show();
// }
// 
// function modificarSala(id) {
//   document.getElementById('idplanclases').value = id;
//   document.getElementById('salaModalTitle').textContent = 'Modificar Sala';
//   const modal = new bootstrap.Modal(document.getElementById('salaModal'));
//   modal.show();
// }
// 
// function liberarSala(id) {
//   if(confirm('¿Está seguro que desea liberar esta sala?')) {
//     // Aquí iría el código para liberar la sala
//   }
// }
// 
// function guardarSala() {
//   // Aquí iría el código para guardar los cambios de sala
//   const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
//   modal.hide();
// }

// Función para cargar el contenido de salas en el tab
function loadSalas() {
  fetch('salas2.php')
    .then(response => response.text())
    .then(html => {
      document.getElementById('salas-list').innerHTML = html;
    });
}

// Agregar evento para cargar salas cuando se hace clic en la pestaña
document.getElementById('salas-tab').addEventListener('click', loadSalas);
</script>

<!-- FUNCIONAES DE SALAS -->
<script>
async function solicitarSala(idPlanClase) {
    document.getElementById('salaForm').reset();
    document.getElementById('idplanclases').value = idPlanClase;
    document.getElementById('action').value = 'solicitar';
    document.getElementById('salaModalTitle').textContent = 'Solicitar Sala';
    
    // Obtener el número de alumnos y salas del elemento de la tabla
    const tr = document.querySelector(`tr[data-id="${idPlanClase}"]`);
    if (tr) {
        // Obtener alumnos totales
        const alumnosTotales = tr.dataset.alumnos;
        document.getElementById('alumnosTotales').value = alumnosTotales;
        document.getElementById('alumnosTotales').readOnly = true;
        
        // Obtener número de salas
        const nSalasCell = tr.querySelector('td:nth-child(7)'); // Columna N° Salas
        const nSalas = nSalasCell ? nSalasCell.textContent.trim() : "1";
        document.getElementById('nSalas').value = nSalas;
        
        // Calcular alumnos por sala
        calcularAlumnosPorSala();
    }
    
    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
    setupModalListeners();
}

async function modificarSala(idPlanClase) {
    document.getElementById('salaForm').reset();
    const form = document.getElementById('salaForm');
    
    // Obtener el estado actual de la sala
    const tr = document.querySelector(`tr[data-id="${idPlanClase}"]`);
    if (!tr) {
        console.error('No se encontró la fila');
        return;
    }

    // Verificar el estado directamente desde la columna de estado
    const estadoCell = tr.querySelector('td:nth-child(9)'); // Columna de Estado
    const estadoBadge = estadoCell.querySelector('.badge');
    const estadoTexto = estadoBadge ? estadoBadge.textContent.trim() : '';

    console.log('Estado detectado:', estadoTexto); // Debug

    // Determinar si está asignada (estado 3)
    const esAsignada = estadoTexto === 'Asignada';

    // Establecer valores del formulario
    document.getElementById('idplanclases').value = idPlanClase;
    document.getElementById('action').value = esAsignada ? 'modificar_asignada' : 'modificar';

    console.log('Action seleccionado:', document.getElementById('action').value); // Debug

    // ... resto del código ...

    try {
        const response = await fetch('salas2.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'obtener_datos_solicitud',
                idPlanClase: idPlanClase
            })
        });

        const datos = await response.json();
        if (datos.success) {
            // También verificamos aquí el estado
            if (datos.estado === 3) {
                document.getElementById('action').value = 'modificar_asignada';
            }
            
            document.getElementById('campus').value = datos.pcl_campus || 'Norte';
            document.getElementById('nSalas').value = datos.pcl_nSalas || '1';
            document.getElementById('alumnosTotales').value = tr.dataset.alumnos;
            document.getElementById('alumnosTotales').readOnly = true;
            calcularAlumnosPorSala();
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarToastSalas('Error al cargar los datos de la sala', 'danger');
    }

    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
}

function calcularAlumnosPorSala() {
    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
    
    // Actualizar el campo de alumnos por sala
    const alumnosPorSalaInput = document.getElementById('alumnosPorSala');
    if (alumnosPorSalaInput) {
        alumnosPorSalaInput.value = alumnosPorSala;
    }
    
    // Debug para verificar los valores
    console.log('Cálculos:', {
        totalAlumnos,
        nSalas,
        alumnosPorSala
    });
}

function setupModalListeners() {
    const nSalasSelect = document.getElementById('nSalas');
    if (nSalasSelect) {
        // Remover listener existente para evitar duplicados
        nSalasSelect.removeEventListener('change', calcularAlumnosPorSala);
        // Agregar nuevo listener
        nSalasSelect.addEventListener('change', calcularAlumnosPorSala);
        
        // Trigger inicial para asegurar que se muestran los cálculos
        calcularAlumnosPorSala();
    }
}

async function mostrarModalLiberarSalas(idPlanClase) {
    try {
        // Obtener las salas asignadas
        const response = await fetch(`salas2.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'obtener_salas_asignadas',
                idPlanClase: idPlanClase
            })
        });
        
        const datos = await response.json();
        
        if (datos.salas && datos.salas.length > 0) {
            // Llenar la tabla con las salas
            const tbody = document.getElementById('listaSalasAsignadas');
            tbody.innerHTML = '';
            
            datos.salas.forEach(sala => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${sala.idSala}</td>
                    <td>
                        <button class="btn btn-danger btn-sm" 
                                onclick="liberarSala(${sala.idAsignacion})">
                            <i class="bi bi-x-circle"></i> Liberar
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('liberarSalaModal'));
            modal.show();
        } else {
            alert('No hay salas asignadas para liberar');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al cargar las salas asignadas');
    }
}

async function liberarSala(idAsignacion) {
    if (!confirm('¿Está seguro que desea liberar esta sala?')) {
        return;
    }
    
    try {
        const response = await fetch('salas2.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'liberar',
                idAsignacion: idAsignacion
            })
        });
        
        if (response.ok) {
            // Cerrar el modal de liberar salas
            const modalLiberar = bootstrap.Modal.getInstance(document.getElementById('liberarSalaModal'));
            if (modalLiberar) modalLiberar.hide();
            
            // Mostrar notificación
            mostrarToastSalas('Sala liberada correctamente');
            
            // Recargar solo la tabla de salas
            const cursoId = new URLSearchParams(window.location.search).get('curso');
            fetch('salas2.php?curso=' + cursoId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('salas-list').innerHTML = html;
                })
                .catch(error => {
                    mostrarToastSalas('Error al actualizar la tabla', 'danger');
                    console.error('Error al recargar la tabla:', error);
                });
        } else {
            mostrarToastSalas('Error al liberar la sala', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarToastSalas('Error al procesar la solicitud', 'danger');
    }
}

async function guardarSala() {
    const form = document.getElementById('salaForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Debug para ver qué datos estamos enviando
    console.log('Form Data:', {
        action: form.querySelector('[name="action"]').value,
        nSalas: form.querySelector('[name="nSalas"]').value,
        idplanclases: form.querySelector('[name="idplanclases"]').value
    });
    
    const formData = new FormData(form);
    const datos = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('salas2.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(datos)
        });

        // Debug para ver la respuesta del servidor
        const responseData = await response.json();
        console.log('Server Response:', responseData);

        if (response.ok && responseData.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarToastSalas('Sala gestionada correctamente');

            // Recargar solo la tabla de salas
            const cursoId = new URLSearchParams(window.location.search).get('curso');
            fetch('salas2.php?curso=' + cursoId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('salas-list').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToastSalas('Error al actualizar la tabla de salas', 'danger');
                });
        } else {
            mostrarToastSalas(responseData.error || 'Error al guardar los cambios', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarToastSalas('Error al procesar la solicitud', 'danger');
    }
}

function mostrarToastSalas(mensaje, tipo = 'success') {
    // Buscar o crear el contenedor de toast para salas
    let toastContainerSalas = document.querySelector('.toast-container-salas');
    if (!toastContainerSalas) {
        toastContainerSalas = document.createElement('div');
        toastContainerSalas.className = 'toast-container-salas position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainerSalas);
    }

    // Crear el toast con los iconos de Bootstrap
    const icono = tipo === 'success' ? 'check-circle' : 'exclamation-circle';
    const toastHTML = `
        <div class="toast align-items-center text-white bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${icono} me-2"></i>
                    ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Limpiar toasts anteriores
    toastContainerSalas.innerHTML = '';
    
    // Agregar el nuevo toast
    toastContainerSalas.insertAdjacentHTML('beforeend', toastHTML);
    
    // Mostrar el toast con opciones personalizadas
    const toastElement = new bootstrap.Toast(toastContainerSalas.querySelector('.toast'), {
        autohide: true,
        delay: 3000
    });
    toastElement.show();
}

function saveAutoActivity() {
    const form = document.getElementById('autoaprendizajeForm');
    const formData = new FormData();
    
    // Obtener los datos del formulario
    const idplanclases = document.getElementById('auto-idplanclases').value;
    const activityTitle = document.getElementById('auto-activity-title').value;
    const hours = parseInt(document.getElementById('auto-hours').value) || 0;
    const minutes = parseInt(document.getElementById('auto-minutes').value) || 0;
    
    // Validar horas y minutos
    if (!validateAutoTime(hours, minutes)) {
        mostrarToast(`Las horas asignadas exceden el máximo semanal (${horasSemanales} horas)`, 'danger');
        return;
    }
    
    // Formatear las horas en formato HH:MM:SS
    const horasNoPresenciales = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:00`;
    
    // Añadir datos al formData
    formData.append('idplanclases', idplanclases);
    formData.append('activity-title', activityTitle);
    formData.append('horasNoPresenciales', horasNoPresenciales);
    
    // Enviar datos al servidor
    fetch('guardar_autoaprendizaje.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modalElement = document.getElementById('autoaprendizajeModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
            
            // Mostrar notificación de éxito
            mostrarToast('Autoaprendizaje guardado correctamente', 'success');
            
            // Recargar la página después de un breve periodo
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(data.message || 'Error al guardar el autoaprendizaje');
        }
    })
    .catch(error => {
        mostrarToast('Error: ' + error.message, 'danger');
    });
}

</script>

<!-- Justo antes del cierre del body -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="docentes-handler.js"></script>


</body>
</html>