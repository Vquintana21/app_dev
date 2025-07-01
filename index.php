<?php

//index.php 99677 ultimo profesor
header('Content-type: text/html; charset=utf-8');
include("conexion.php");
include_once 'login/control_sesion.php';
// Obtener el ID del curso desde la URL
$idCurso = $_GET['curso']; 
//$idCurso = 8942; // 8158
//$rut = "016784781K";
$ruti = $_SESSION['sesion_idLogin'];
$rut = str_pad($ruti, 10, "0", STR_PAD_LEFT);
//$ano = 2024; 
// Consulta SQL
$query = "SELECT `idplanclases`, pcl_tituloActividad, pcl_Periodo, `pcl_Fecha`, `pcl_Inicio`, `pcl_Termino`, 
          `pcl_nSalas`, `pcl_Seccion`, `pcl_TipoSesion`, `pcl_SubTipoSesion`, 
          `pcl_Semana`, `pcl_AsiCodigo`, `pcl_AsiNombre`, `Sala`, `Bloque`, `dia`, `pcl_condicion`, `pcl_ActividadConEvaluacion`, pcl_BloqueExtendido
          FROM `a_planclases` 
          WHERE `cursos_idcursos` = ?
		  AND pcl_Semana >= 1";

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
$idperiodo = $FilaCurso["idperiodo"];

$esSecciones1 = ($seccion == "1");
$tieneMultiplesSecciones = false;
$cupoTotalSecciones = 0;

// MEJORADO: Consulta más robusta para detectar múltiples secciones
if ($esSecciones1) {
    // Consultar si tiene múltiples secciones con validación adicional
    $queryMultiples = "SELECT COUNT(DISTINCT Seccion) as total_secciones_distintas,
                              COUNT(*) as total_registros,
                              SUM(Cupo) as cupo_total,
                              GROUP_CONCAT(DISTINCT Seccion ORDER BY Seccion) as secciones_existentes
                       FROM spre_cursos 
                       WHERE CodigoCurso = ? 
                       AND idperiodo = ?
                       AND Cupo > 0";  // Solo contar secciones con alumnos
    
    $stmtMultiples = $conexion3->prepare($queryMultiples);
    $periodo = $FilaCurso["idperiodo"];
    $stmtMultiples->bind_param("ss", $codigo_curso, $periodo);
    $stmtMultiples->execute();
    $resultMultiples = $stmtMultiples->get_result();
    $dataMultiples = $resultMultiples->fetch_assoc();
    
    // LOGGING MEJORADO para debug
    error_log("DEBUG - Consulta múltiples secciones:");
    error_log("Código curso: $codigo_curso");
    error_log("Período: $periodo");
    error_log("Resultado: " . json_encode($dataMultiples));
    
    // Verificación más estricta
    $tieneMultiplesSecciones = ($dataMultiples['total_secciones_distintas'] > 1);
    $cupoTotalSecciones = $dataMultiples['cupo_total'] ?: 0;
    
    // Debug adicional
    if ($tieneMultiplesSecciones) {
        error_log("✅ Múltiples secciones detectadas para curso $codigo_curso:");
        error_log("Secciones: " . $dataMultiples['secciones_existentes']);
        error_log("Total secciones: " . $dataMultiples['total_secciones_distintas']);
        error_log("Cupo total: $cupoTotalSecciones");
    } else {
        error_log("❌ Solo una sección para curso $codigo_curso");
    }
    
    $stmtMultiples->close();
} else {
    error_log("❌ No es sección 1 (sección actual: $seccion), no evaluar múltiples secciones");
}

// MEJORADO: Variables para funcionalidad de juntar secciones con más información
$datosJuntarSecciones = array(
    'esSecciones1' => $esSecciones1,
    'tieneMultiplesSecciones' => $tieneMultiplesSecciones,
    'cupoTotalSecciones' => $cupoTotalSecciones,
    'cupoSeccionActual' => $FilaCurso["Cupo"],
    'seccionActual' => $seccion,
    'codigoCurso' => $codigo_curso,
    'periodo' => $FilaCurso["idperiodo"],
    'debug' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'consulta_ejecutada' => $esSecciones1,
        'datos_multiples' => $esSecciones1 ? $dataMultiples : null
    ]
);

// CRITICO: Asegurar que el JSON se genere correctamente
$datosJuntarSeccionesJson = json_encode($datosJuntarSecciones, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Verificar que el JSON sea válido
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("❌ Error al generar JSON datosJuntarSecciones: " . json_last_error_msg());
    // Fallback con datos mínimos
    $datosJuntarSeccionesJson = json_encode([
        'esSecciones1' => false,
        'tieneMultiplesSecciones' => false,
        'cupoTotalSecciones' => 0,
        'cupoSeccionActual' => $FilaCurso["Cupo"] ?: 0,
        'error' => 'Error al generar datos completos'
    ]);
} else {
    error_log("✅ JSON datosJuntarSecciones generado correctamente");
}

// NUEVO: Agregar logs adicionales para debugging en producción
error_log("=== DATOS JUNTAR SECCIONES ===");
error_log("Es sección 1: " . ($esSecciones1 ? 'SÍ' : 'NO'));
error_log("Tiene múltiples secciones: " . ($tieneMultiplesSecciones ? 'SÍ' : 'NO'));
error_log("Cupo total: $cupoTotalSecciones");
error_log("Cupo sección actual: " . $FilaCurso["Cupo"]);
error_log("JSON length: " . strlen($datosJuntarSeccionesJson));

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
$queryHoras = "SELECT
    C.idcurso,
    A.`HNPSemanales`,
    FLOOR(A.HNPSemanales) AS horas,
    ROUND(
        (
            A.HNPSemanales - FLOOR(A.HNPSemanales)
        ) * 60
    ) AS minutos,
    CONCAT(
        FLOOR(HNPSemanales),
        ':',
        LPAD(
            ROUND(
                (
                    HNPSemanales - FLOOR(HNPSemanales)
                ) * 60
            ),
            2,
            '0'
        )
    ) AS tiempo
FROM
    `spre_maestropresencialidad` A
JOIN spre_ramosperiodo B ON
    A.SCT = B.SCT AND A.Semanas = B.NroSemanas AND A.idTipoBloque = B.idTipoBloque
JOIN spre_cursos C ON
    B.CodigoCurso = C.CodigoCurso
WHERE
    C.idcurso = ? AND B.idPeriodo = C.idperiodo;";

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
    <title>Calendario académico - Facultad de Medicina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">	
	  
	 <!-- Vendor CSS Files   -->
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
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
</head>
<body class="toggle-sidebar">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
 <!-- ======= Script Replica ======= -->
 	<script>
	
	$(document).ready(function() {
		
		var idCurso = <?php echo $idCurso; ?>;
		
		if(idCurso != ""){
			
			$.ajax({ 
				dataType: "",
				data: {'idCurso':idCurso
				},
				url: 'https://dpi.med.uchile.cl/test/calendarios/replica/validar_reglas.php',
				type: 'POST',
				beforeSend: function() {
					//Lo que se hace antes de enviar el formulario
				},
				success: function(respuesta) {
					
					if(respuesta == 1){
						$("#modal_replica").modal("show");
					}
				},
				error: function(xhr, err) {
					alert("readyState: " + xhr.readyState + "\nstatus: " + xhr.status + "\n \n responseText: " + xhr.responseText);
				}
			});
			
		}
		

	});
	
	function ejecutar_replica(accion){
		
		var idCurso = $("#idCurso").val();
		
		
		$.ajax({
				dataType: "",
				data: {'idCurso': idCurso, 'accion': accion
				},
				url: 'https://dpi.med.uchile.cl/test/calendarios/replica/ejecutar_replica.php',
				type: 'POST',
				beforeSend: function() {
					//Lo que se hace antes de enviar el formulario
					if(accion == "replicar"){
						$('#spinner').show();  
						$('#btn_ejecutar').prop("disabled",true);
						$('#btn_nuevo').prop("disabled",true);						
						$('#mensaje_tiempo').prop("hidden",false);  
					}else{
						$('#btn_nuevo').prop("disabled",true); 
						$('#btn_ejecutar').prop("disabled",true); 			 			
					}
					
					
				},
				success: function(respuesta) {
					
				
					if(accion == "replicar"){
						alert(respuesta);
					}
					
					window.location.href = 'https://dpi.med.uchile.cl/test/calendarios/index.php?curso='+idCurso;
				},
				error: function(xhr, err) {
					alert("readyState: " + xhr.readyState + "\nstatus: " + xhr.status + "\n \n responseText: " + xhr.responseText);
				}
			});
	}

	
	</script>
 <!-- ======= Script Replica ======= -->
 
 
 
 <!-- ======= Header ======= -->
  <?php include 'nav_superior.php'; ?>
  
    <!-- ======= Sidebar ======= -->
 <?php include 'nav_lateral.php'; ?>

 <main id="main" class="main">
    <div class="pagetitle">
        <h1><?php echo $codigo_curso."-".$seccion; ?> <?php echo $nombre_curso; ?> <?php echo $idperiodo ; ?></h1>
        <small style="float: right;">ID curso: <?php echo $idCurso; ?></small>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Inicio</a></li>
                <li class="breadcrumb-item active">Plan de clases </li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">    
       <div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
				<h5 class="card-title"><i class="bi bi-pencil"></i> Editar información </h5>
				 <div class="card mb-4">
            <?php //  include 'estadisticas_regulares.php'; ?>
        </div>
		
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
                           
                            </br>
                            <nav>
                                
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
        <div class="modal fade" id="activityModal" data-bs-backdrop="true" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                     <div class="modal-header">
                        <div>
                            <h4 class="card-title">Detalle de la actividad <span id="modal-idplanclases"></span></h4>
                            <p class="mb-0 text-muted" id="modal-fecha-hora"></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <form id="activityForm">
                                            <input type="hidden" id="idplanclases" name="idplanclases">
											<input type="hidden" id="tipo-anterior" name="tipo_anterior">
											<span id="modal-tipo-actividad" style="display: none;"></span>
												</br>
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
	<label class="col-sm-2 col-form-label">
	Sub Tipo actividad <span style="color: #dc3545; font-weight: bold;">*</span>
	</label>
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
									</br>
                                    <div class="col-4 border" id="docentes-container" style="overflow: scroll; max-height: 600px;">
									</br>
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
						<small class="text-muted">Tiempo máximo semanal autorizado por pregrado: <b><?php echo $horasData['horas']; ?></b> Hora <b><?php echo $horasData['minutos']; ?></b> Minutos.</small>
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
      &copy; <b>2025 Facultad de Medicina Universidad de Chile</b>
    </div>
    <div class="credits">
      Diseñado por <b><a target="_blank" href="https://dpi.med.uchile.cl">DPI</b></a>
    </div>
  </footer>


  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	   
	 <script src="validarRUT.js"></script>
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
 <!--  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>-->
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  
  <script src="assets/js/main.js"></script>
	
	<!-- FUNCIONES DE ACTIVIDADES Y DONCETES-->
   <script>
   
   // ========================================
// JAVASCRIPT COMPLETO RESTAURADO Y ORGANIZADO PARA INDEX.PHP
// Mantiene TODA la funcionalidad original + organización
// ========================================

// ===========================================
// 1. VARIABLES GLOBALES Y CONFIGURACIÓN INICIAL
// ===========================================

let planClases = <?php echo $planclasesJson; ?>;
let tiposSesion = <?php echo $tiposSesionJson; ?>;
const horasSemanales = <?php echo $horasSemanalesJson; ?>;

// Variables globales para el asignador masivo
let actividadesSeleccionadas = [];

// Variables para el sistema de juntar secciones
let juntarSeccionesConfigurado = false;

// Variables para validación de datos de juntar secciones
console.log('🔧 Cargando datos juntar secciones...');

// Verificar que PHP generó los datos
<?php if (!empty($datosJuntarSeccionesJson)): ?>
    let datosJuntarSecciones = <?php echo $datosJuntarSeccionesJson; ?>;
    
    // Validar que se cargaron correctamente
    if (datosJuntarSecciones && typeof datosJuntarSecciones === 'object') {
        console.log('✅ Datos juntar secciones cargados correctamente:', datosJuntarSecciones);
        window.datosJuntarSeccionesDebug = datosJuntarSecciones;
        
        if (datosJuntarSecciones.esSecciones1 && datosJuntarSecciones.tieneMultiplesSecciones) {
            console.log('🎯 Condiciones cumplidas para mostrar checkbox juntar secciones');
            console.log(`📊 Cupo total: ${datosJuntarSecciones.cupoTotalSecciones}, Cupo actual: ${datosJuntarSecciones.cupoSeccionActual}`);
        } else {
            console.log('❌ Condiciones NO cumplidas:', {
                esSecciones1: datosJuntarSecciones.esSecciones1,
                tieneMultiplesSecciones: datosJuntarSecciones.tieneMultiplesSecciones
            });
        }
    } else {
        console.error('❌ Error: datosJuntarSecciones no es un objeto válido:', datosJuntarSecciones);
    }
<?php else: ?>
    console.error('❌ PHP no generó datosJuntarSeccionesJson');
    let datosJuntarSecciones = {
        esSecciones1: false,
        tieneMultiplesSecciones: false,
        cupoTotalSecciones: 0,
        cupoSeccionActual: 0,
        error: 'No se pudieron cargar los datos desde PHP'
    };
    console.warn('⚠️ Usando datos de fallback para juntar secciones');
<?php endif; ?>

// ===========================================
// 2. FUNCIONES AUXILIARES Y VALIDACIÓN
// ===========================================

function validateAutoTime(hours, minutes) {
    const totalHours = hours + (minutes / 60);
    return totalHours <= horasSemanales;
}

/**
 * Limpia completamente el modal de salas antes de configurarlo
 * Elimina elementos dinámicos y resetea variables
 */
function limpiarModalSalaCompleto() {
    console.log('🧹 Iniciando limpieza completa del modal');
    
    // 1. Ocultar elementos de juntar secciones (NO eliminar)
    const existingJuntar = document.querySelector('#juntarSeccionesDiv');
if (existingJuntar) {
    existingJuntar.style.display = 'none';
    const formulario = document.querySelector('#formularioSalasModal');
    if (formulario) {
        formulario.appendChild(existingJuntar);
        console.log("✅ Elemento juntar secciones ocultado y reubicado temporalmente");
    }
}
    
    // 2. Limpiar cualquier checkbox con name="juntarSecciones" (por si queda alguno huérfano)
    const checkboxesJuntar = document.querySelectorAll('input[name="juntarSecciones"]');
    checkboxesJuntar.forEach(checkbox => {
        const parentDiv = checkbox.closest('.mb-3');
        if (parentDiv && parentDiv.id !== 'juntarSeccionesDiv') {
            // Solo eliminar si no es el div principal (por seguridad)
            parentDiv.remove();
        }
    });
    
    // 3. Limpiar sección de computación
    const seccionComputacion = document.getElementById('seccion-computacion');
    if (seccionComputacion) {
        seccionComputacion.style.display = 'none';
        seccionComputacion.innerHTML = '';
        console.log('✅ Sección de computación limpiada');
    }
    
    // 4. Limpiar alertas de bloques
    limpiarAlertasBloques();
    
    // 5. Resetear variables globales
    juntarSeccionesConfigurado = false;
    
    // 6. Limpiar datos de salas disponibles
    if (window.salasDisponiblesData) {
        window.salasDisponiblesData = null;
        console.log('✅ Datos de salas disponibles limpiados');
    }
    
    // 7. Ocultar badge de salas disponibles
    ocultarBadgeSalas();
    
    console.log('✅ Limpieza completa del modal finalizada');
}


function limpiarAlertasBloques() {
    var alertaExistente = document.getElementById('alerta-bloques');
    if (alertaExistente) {
        alertaExistente.remove();
        console.log('🧹 Alerta de bloques eliminada');
    }
}

function validarDatosJuntarSecciones() {
    if (typeof datosJuntarSecciones === 'undefined') {
        return false;
    }
    
    if (!datosJuntarSecciones || typeof datosJuntarSecciones !== 'object') {
        return false;
    }
    
    const propiedadesRequeridas = ['esSecciones1', 'tieneMultiplesSecciones', 'cupoTotalSecciones', 'cupoSeccionActual'];
    for (const prop of propiedadesRequeridas) {
        if (!(prop in datosJuntarSecciones)) {
            return false;
        }
    }
    
    return true;
}

function debugTablaSalas() {
    console.log('🔍 Debug - Estado de la tabla de salas:');
    
    var filas = document.querySelectorAll('#salas-list table tbody tr');
    console.log('Total de filas encontradas:', filas.length);
    
    filas.forEach(function(fila, index) {
        var celdas = fila.cells;
        if (celdas.length > 1) {
            console.log('Fila ' + index + ':', {
                id: fila.dataset.id,
                fecha: celdas[1] ? celdas[1].textContent.trim() : 'N/A',
                horario: celdas[2] ? celdas[2].textContent.trim() : 'N/A',
                tipo: celdas[3] ? celdas[3].textContent.trim() : 'N/A'
            });
        }
    });
}

function verificarDependencias() {
    var dependencias = [
        'verificarBloquesMismoDia',
        'procesarBloquesMismoDia',
        'mostrarAlertaBloquesRelacionados',
        'parsearFechaParaConsulta'
    ];
    
    var faltantes = [];
    
    dependencias.forEach(function(func) {
        if (typeof window[func] !== 'function') {
            faltantes.push(func);
        }
    });
    
    if (faltantes.length > 0) {
        console.error('❌ Faltan las siguientes funciones:', faltantes);
        return false;
    } else {
        console.log('✅ Todas las dependencias están disponibles');
        return true;
    }
}

// Función auxiliar para truncar texto
function truncateText(text, maxLength) {
    if (text.length <= maxLength) {
        return text;
    }
    return text.substring(0, maxLength) + '...';
}

// ===========================================
// 3. FUNCIONES DE TOAST Y NOTIFICACIONES
// ===========================================

function mostrarToast(mensaje, tipo = 'success', duracion = 3000) {
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    const icono = tipo === 'success' ? 'check-circle' : 'x-circle';
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
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toast = new bootstrap.Toast(toastContainer.lastElementChild, {
        autohide: true,
        delay: duracion
    });
    toast.show();
}

function mostrarToastCarga(mensaje) {
    const toastHTML = `
        <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    ${mensaje}
                </div>
            </div>
        </div>
    `;
    
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.innerHTML = ''; // Limpiar toasts anteriores
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toast = new bootstrap.Toast(toastContainer.firstElementChild, {
        autohide: false
    });
    toast.show();
}

function mostrarToastSalas(mensaje, tipo = 'success') {
    let toastContainerSalas = document.querySelector('.toast-container-salas');
    if (!toastContainerSalas) {
        toastContainerSalas = document.createElement('div');
        toastContainerSalas.className = 'toast-container-salas position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainerSalas);
    }

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
    
    toastContainerSalas.innerHTML = '';
    toastContainerSalas.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = new bootstrap.Toast(toastContainerSalas.querySelector('.toast'), {
        autohide: true,
        delay: 3000
    });
    toastElement.show();
}

// ===========================================
// 4. FUNCIONES DEL CALENDARIO Y ACTIVIDADES
// ===========================================

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
        
        // ✅ NUEVO: Agregar evento para limpiar errores cuando se selecciona un subtipo
        selectSubtipo.removeEventListener('change', limpiarErrorSubtipo); // Evitar duplicados
        selectSubtipo.addEventListener('change', limpiarErrorSubtipo);
        
    } else {
        subtypeContainer.style.display = 'none';
        selectSubtipo.innerHTML = '<option value="">No aplica</option>';
        // Limpiar errores cuando se oculta
        selectSubtipo.classList.remove('campo-error-subtipo');
    }
}

// ✅ NUEVA FUNCIÓN: Para limpiar errores del subtipo
function limpiarErrorSubtipo() {
    const subtypeSelect = document.getElementById('activity-subtype');
    if (subtypeSelect && subtypeSelect.value.trim()) {
        subtypeSelect.classList.remove('campo-error-subtipo');
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
        button.className = 'btn btn-lg activity-button btn-light feriado';
        button.innerHTML = `<div class="activity-title">Feriado</div>`;
        // No agregamos data-bs-toggle ni onclick para que no sea clickeable
        return button;
    }
	
	// NUEVA CONDICIÓN: Verificar si es un Bloque Protegido
    if (activity.pcl_TipoSesion === 'Bloque Protegido') {
        button.className = 'btn btn-lg activity-button btn-info bloque-protegido';
        
        // Formatear la fecha
        const fecha = new Date(activity.pcl_Fecha);
        const day = fecha.getDate().toString().padStart(2, '0');
        const month = (fecha.getMonth() + 1).toString().padStart(2, '0');
        const fechaFormateada = `${day}-${month}`;
        
        let content = `
            <div class="class-date"><i class="fas fa-calendar-days me-1"></i>${fechaFormateada}</div>
            <div class="class-time"><i class="fas fa-clock me-1"></i>${activity.pcl_Inicio.substring(0,5)} - ${activity.pcl_Termino.substring(0,5)}</div>
            <div class="activity-title"><i class="fas fa-shield-alt me-1"></i>Bloque Protegido</div>
        `;
        
        button.innerHTML = content;
        // NO agregamos onclick ni data-bs-toggle para que no sea clickeable
        return button;
    }
    
    if (activity.pcl_TipoSesion === 'Autoaprendizaje') {
        button.className = 'btn btn-lg activity-button autoaprendizaje';
        button.setAttribute('data-bs-toggle', 'modal');
        button.setAttribute('data-bs-target', '#autoaprendizajeModal');
        
        let content = '';
        if (activity.pcl_tituloActividad) {
            content = `<div class="activity-title">${truncateText(activity.pcl_tituloActividad, 20)}</div>`;
        } else {
            content = `<div class="activity-title"><i class="fas fa-plus"></i> Autoaprendizaje </div>`;
        }
        button.innerHTML = content;
        button.onclick = () => loadAutoActivityData(activity);
        
        // Agregar tooltip si el título es largo
        if (activity.pcl_tituloActividad && activity.pcl_tituloActividad.length > 25) {
            
            button.setAttribute('title', activity.pcl_tituloActividad);
        }
    } else {
        const isCompleted = activity.estado === 'completed';
        button.className = `btn btn-lg activity-button ${isCompleted ? 'completed' : 'default'}`;
        button.setAttribute('data-bs-toggle', 'modal');
        button.setAttribute('data-bs-target', '#activityModal');
        
       // Formatear la fecha
		const fecha = new Date(activity.pcl_Fecha);
        const day = fecha.getDate().toString().padStart(2, '0');
        const month = (fecha.getMonth() + 1).toString().padStart(2, '0');
        const fechaFormateada = `${day}-${month}`;
        
        let content = '';
        if (activity.pcl_tituloActividad) {
            content = `
                <div class="class-date"><i class="fas fa-calendar-days me-1"></i>${fechaFormateada}</div>
                <div class="class-time"><i class="fas fa-clock me-1"></i>${activity.pcl_Inicio.substring(0,5)} - ${activity.pcl_Termino.substring(0,5)}</div>
				<div class="activity-type-sesion"><i class="fas fa-pen-to-square me-1"></i>${truncateText(activity.pcl_TipoSesion, 25)}</div>
                <div class="activity-title"><i class="fas fa-book me-1"></i>${truncateText(activity.pcl_tituloActividad, 25)}</div>
            `;
            
            // Agregar tooltip si el título es largo
            if (activity.pcl_tituloActividad.length > 25) {
                button.setAttribute('title', activity.pcl_tituloActividad);
            }
        } else {
            content = `
                <div class="class-date"><i class="fas fa-calendar-days me-1"></i>${fechaFormateada}</div>
                <div class="class-time"><i class="fas fa-clock me-1"></i>${activity.pcl_Inicio.substring(0,5)} - ${activity.pcl_Termino.substring(0,5)}</div>
                <div class="activity-title" style="color: #ffc107;">
                    <i class="fas fa-plus"></i> Agregar actividad
                </div>
            `;
        }
        button.innerHTML = content;
        button.onclick = () => loadActivityData(activity);
    }
    
    // Inicializar tooltips de Bootstrap
    if (button.getAttribute('title')) {
        setTimeout(() => {
            new bootstrap.Tooltip(button);
        }, 500);
    }
    
    return button;
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
    
    // Formatear las fechas manualmente para asegurar el formato dd-mm
    const formatDate = (date) => {
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        return `${day}-${month}`;
    };
    
    return {
        inicio: formatDate(lunes),
        fin: formatDate(viernes)
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
        weekCell.innerHTML = `Semana ${week.weekNum}<br><small class="text-muted">${weekDates.inicio} al ${weekDates.fin}</small>`;
        weekRow.appendChild(weekCell);
        
     // Celdas de días
        ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'].forEach(day => {
            const dayCell = document.createElement('td');
            dayCell.className = 'calendar-cell';
            
            // Actividades para este día que pertenecen a este mes
            const dayActivities = week.activities
                .filter(activity => {
                    if (activity.dia !== day && !(day === 'Miércoles' && activity.dia === 'Miercoles')) return false;
                    
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
	
	console.log('🔍 DEBUG: Todas las actividades cargadas:', planClases);
    const autoaprendizajeActivities = planClases.filter(a => a.pcl_TipoSesion === 'Autoaprendizaje');
    console.log('📚 Actividades de autoaprendizaje encontradas:', autoaprendizajeActivities);
    
	
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

// ===========================================
// 5. FUNCIONES DE ACTIVIDADES Y CARGA DE DATOS
// ===========================================

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
           } else {
               // Si no hay horas asignadas, precargar con los valores máximos permitidos
               const horasMax = <?php echo $horasData['horas']; ?>;
               const minutosMax = <?php echo $horasData['minutos']; ?>;
               document.getElementById('auto-hours').value = horasMax;
               document.getElementById('auto-minutes').value = minutosMax;
           }
       } else {
           // Si no hay horas asignadas, precargar con los valores máximos permitidos
           const horasMax = <?php echo $horasData['horas']; ?>;
           const minutosMax = <?php echo $horasData['minutos']; ?>;
           document.getElementById('auto-hours').value = horasMax;
           document.getElementById('auto-minutes').value = minutosMax;
       }
   }
   
   // Añadir validación para el título
   const saveButton = document.getElementById('save-auto-btn');
   const titleField = document.getElementById('auto-activity-title');
   
   // Verificar el estado inicial del título
   if (titleField.value.trim() === '') {
       saveButton.disabled = true;
   }
   
   // Agregar event listener para validar el título en tiempo real
   titleField.addEventListener('input', function() {
       saveButton.disabled = this.value.trim() === '';
   });
}

// Modificar la función loadActivityData existente
function loadActivityData(activity) {
	
	 console.log('Datos de actividad recibidos:', activity); // Para debug
    console.log('ID específico:', activity.idplanclases); // Para debug
    console.log('Horarios de esta actividad:', {
        inicio: activity.pcl_Inicio,
        termino: activity.pcl_Termino
    }); 
	
	  console.log('Datos de actividad recibidos:', activity); // Para debug
    // Actualizar los campos del modal
    document.getElementById('modal-idplanclases').textContent = activity.idplanclases;
    document.getElementById('idplanclases').value = activity.idplanclases;
    
	 document.getElementById('tipo-anterior').value = activity.pcl_TipoSesion;
	 document.getElementById('modal-tipo-actividad').textContent = activity.pcl_TipoSesion;
	
	const fecha = new Date(activity.pcl_Fecha);
const day = fecha.getDate().toString().padStart(2, '0');
const month = (fecha.getMonth() + 1).toString().padStart(2, '0');
const fechaFormateada = `${day}-${month}`;

const horaInicio = activity.pcl_Inicio.substring(0,5);
const horaTermino = activity.pcl_Termino.substring(0,5);

document.getElementById('modal-fecha-hora').textContent = 
    `Día ${fechaFormateada} desde las ${horaInicio} a las ${horaTermino}`;
	
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
    
    // desabilita los termino anteriores al inicio.
// Reemplazar el event listener existente en generateTimeOptions
startSelect.addEventListener('change', function() {
    Array.from(endSelect.options).forEach(option => {
        option.disabled = option.value <= this.value;
    });
    
    if (endSelect.value <= this.value) {
        // Buscar la siguiente opción válida
        for (let option of endSelect.options) {
            if (option.value > this.value) {
                endSelect.value = option.value;
                break;
            }
        }
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
            // Ordenar inicialmente
            reordenarDocentes();
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
        // Reordenar después de seleccionar/deseleccionar todos
        reordenarDocentes();
    });

    // Event listener para checkboxes individuales
    document.getElementById('docentes-container').addEventListener('change', function(e) {
        if (e.target.classList.contains('docente-check')) {
            const docenteCheckboxes = document.querySelectorAll('.docente-check');
            const allChecked = Array.from(docenteCheckboxes).every(checkbox => checkbox.checked);
            selectAllCheckbox.checked = allChecked;
            
            // Reordenar cuando se selecciona/deselecciona un docente
            reordenarDocentes();
        }
    });
}

// Nueva función para reordenar docentes
function reordenarDocentes() {
    const container = document.getElementById('docentes-container');
    const docenteRows = Array.from(container.querySelectorAll('.docente-row'));
    
    // Separar en dos grupos
    const selected = [];
    const notSelected = [];
    
    docenteRows.forEach(row => {
        const checkbox = row.querySelector('.docente-check');
        if (checkbox.checked) {
            selected.push(row);
        } else {
            notSelected.push(row);
        }
    });
    
    // Ordenar los no seleccionados alfabéticamente por nombre del docente
    notSelected.sort((a, b) => {
        const nameA = a.querySelector('p.mt-3').textContent.toLowerCase();
        const nameB = b.querySelector('p.mt-3').textContent.toLowerCase();
        return nameA.localeCompare(nameB);
    });
    
    // Remover todos los rows del contenedor
    docenteRows.forEach(row => row.remove());
    
    // Agregar primero los seleccionados, luego los no seleccionados
    selected.forEach(row => container.appendChild(row));
    notSelected.forEach(row => container.appendChild(row));
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

// ===========================================
// 6. FUNCIONES DE GUARDADO Y PROCESAMIENTO
// ===========================================

function saveActivity() {
    // Validar título
    const activityTitle = document.getElementById('activity-title').value.trim();
    if (activityTitle === '') {
        mostrarToast('El título de la actividad no puede estar vacío', 'danger');
        return;
    }
	
	 // ✅ NUEVA VALIDACIÓN: Verificar subtipo si está visible
    const subtypeContainer = document.getElementById('subtype-container');
    const subtypeSelect = document.getElementById('activity-subtype');
    
    if (subtypeContainer && subtypeContainer.style.display !== 'none' && subtypeContainer.style.display !== '') {
        const subtipoValue = subtypeSelect ? subtypeSelect.value.trim() : '';
        if (!subtipoValue) {
            mostrarToast('Debe seleccionar un subtipo de actividad', 'danger');
            if (subtypeSelect) {
                subtypeSelect.focus();
                subtypeSelect.classList.add('campo-error-subtipo');
            }
            return;
        }
    }
    
    // Obtener datos necesarios para verificación
    const idplanclases = document.getElementById('idplanclases').value;
    const tipoNuevo = document.getElementById('activity-type').value;
    const tipoActual = document.querySelector('#modal-tipo-actividad').textContent || '';
    
    // Verificar si hay cambio de tipo que pueda afectar salas
    if (tipoActual && tipoActual !== tipoNuevo) {
        // Preparar datos para verificación
        const verificacionData = new FormData();
        verificacionData.append('action', 'verificar_cambio');
        verificacionData.append('idplanclases', idplanclases);
        verificacionData.append('tipo_nuevo', tipoNuevo);
        verificacionData.append('tipo_actual', tipoActual);
        
        // Mostrar toast de verificación
        mostrarToastCarga('Verificando cambios...');
        
        // Realizar consulta para verificar impacto del cambio
        fetch('verificar_sala.php', {
            method: 'POST',
            body: verificacionData
        })
        .then(response => response.json())
        .then(data => {
            // Ocultar toast de verificación
            document.querySelector('.toast-container').innerHTML = '';
            
            if (data.necesita_confirmacion) {
                // Mostrar SweetAlert de confirmación
                Swal.fire({
                    title: '¡Importante!',
                    html: data.mensaje_confirmacion,
                    icon: 'info',                    
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'continuar',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Si confirma, proceder con el guardado
                        procesarGuardado();
                    }
                });
            } else {
                // Si no requiere confirmación, proceder directamente
                procesarGuardado();
            }
        })
        .catch(error => {
            console.error('Error al verificar cambios:', error);
            // Mostrar advertencia y permitir continuar
            Swal.fire({
                title: 'Advertencia',
                text: 'No se pudo verificar el impacto en las salas. El cambio podría afectar asignaciones existentes. ¿Desea continuar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    procesarGuardado();
                }
            });
        });
    } else {
        // Si no hay cambio de tipo, proceder directamente
        procesarGuardado();
    }
}

// REEMPLAZAR TODA la función procesarGuardado() en index.php:
function procesarGuardado() {
	
	
    
    const subtypeContainer = document.getElementById('subtype-container');
    const subtypeSelect = document.getElementById('activity-subtype');
    const activityType = document.getElementById('activity-type').value;
    
   
    
    // ✅ LÓGICA CORREGIDA DE VISIBILIDAD
    if (subtypeContainer && subtypeSelect) {
        // Verificar si está visible usando getComputedStyle (más preciso)
        const computedStyle = window.getComputedStyle(subtypeContainer);
        const isVisible = computedStyle.display !== 'none';
        
        // También verificar si el style directo no dice 'none'
        const isVisibleByStyle = subtypeContainer.style.display !== 'none';
        
        const hasOptions = subtypeSelect.options.length > 1;
        const subtipoValue = subtypeSelect.value.trim();
        
       
        
        // ✅ NUEVA CONDICIÓN: Si está visible Y tiene opciones Y no hay valor seleccionado
        if (isVisible && hasOptions && !subtipoValue) {           
            mostrarToast('Debe seleccionar un subtipo de actividad', 'danger');
            subtypeSelect.focus();
            subtypeSelect.classList.add('campo-error-subtipo');
            
            // Rehabilitar botón
            const saveButton = document.querySelector('button[onclick="saveActivity()"]');
            if (saveButton) saveButton.disabled = false;
            
            return; // DETENER AQUÍ
        }
    }    
   
    
   
    
  
	
    // Mostrar toast de carga
    mostrarToastCarga('Guardando cambios...');
    
    // Deshabilitar el botón de guardar para evitar múltiples clicks
    const saveButton = document.querySelector('button[onclick="saveActivity()"]');
    saveButton.disabled = true;
    
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
    
    // ✅ NUEVA LÓGICA: Verificar si hay cambio de tipo y docentes seleccionados
    const tipoActual = document.querySelector('#modal-tipo-actividad').textContent.trim() || '';
    const tipoNuevo = tipoActividad;
    const huboChangioTipo = tipoActual !== tipoNuevo;
    
    console.log('🔍 Verificando cambio de tipo:', {
        tipoActual: tipoActual,
        tipoNuevo: tipoNuevo,
        huboChangeio: huboChangioTipo
    });
    
    // Si hubo cambio de tipo, incluir tipo anterior para referencia
    if (huboChangioTipo && tipoActual) {
       formData.append('tipo_anterior', tipoActual);
    }

    // Verificar si la actividad requiere docentes
    const tipoInfo = tiposSesion.find(t => t.tipo_sesion === tipoActividad);
    const requiereDocentes = tipoInfo && tipoInfo.docentes === "1";

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
    
    console.log('👥 Docentes seleccionados:', docentesSeleccionados);
    
    // Verificar si el nuevo tipo permite docentes
    const infoTipoActividad = tiposSesion.find(t => t.tipo_sesion === tipoNuevo);
    const tipoPermiteDocentes = infoTipoActividad && infoTipoActividad.docentes === "1";
    
    console.log('📋 Info del tipo:', {
        infoTipoActividad: infoTipoActividad,
        permiteDocentes: tipoPermiteDocentes
    });
    
    // ✅ SI HAY CAMBIO DE TIPO Y EL NUEVO PERMITE DOCENTES, ENVIAR DOCENTES
    if (huboChangioTipo && tipoPermiteDocentes && docentesSeleccionados.length > 0) {
        formData.append('docentes_seleccionados', JSON.stringify(docentesSeleccionados));
        console.log('✅ Enviando docentes seleccionados por cambio de tipo');
    }
    
    // ✅ TAMBIÉN ENVIAR SI NO HAY CAMBIO DE TIPO PERO SÍ HAY DOCENTES SELECCIONADOS
    else if (!huboChangioTipo && tipoPermiteDocentes && docentesSeleccionados.length > 0) {
        formData.append('docentes_seleccionados', JSON.stringify(docentesSeleccionados));
        console.log('✅ Enviando docentes seleccionados (sin cambio de tipo)');
    }

    // Primero guardar la actividad
    fetch('guardar_actividad.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Error al guardar la actividad');
        }
        
        // ===== LÓGICA ESPECIAL PARA CASO "DEBE SOLICITAR SALA" =====
        const esCasoSolicitudSala = data.mensaje_sala && 
                                    data.mensaje_sala.includes('Debe solicitar sala desde pestaña Salas') ||
									data.mensaje_sala.includes('Actividad Solicitada')
        
        if (esCasoSolicitudSala) {
            // CERRAR MODAL PRIMERO
            const modalElement = document.getElementById('activityModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
            
            // OCULTAR TOAST DE CARGA
            document.querySelector('.toast-container').innerHTML = '';
			
			let mensajeHTML = '';

			if (data.mensaje_sala.includes('Debe solicitar sala')) {
				mensajeHTML = `
					<div class="text-start">
						<p class="mb-3">
							<i class="bi bi-check-circle text-success me-2"></i>
							<strong>Su actividad ha sido actualizada exitosamente.</strong>
						</p>
						<div class="alert alert-info mb-3">
							<i class="bi bi-info-circle me-2"></i>
							Este tipo de actividad requiere gestión manual de sala.
						</div>
						<p class="text-primary mb-0">
							<i class="bi bi-arrow-right me-2"></i>
							<strong>Próximo paso:</strong> Solicite una sala desde la pestaña "Salas".
						</p>
					</div>
				`;
			} else {
				mensajeHTML = `
					<div class="text-start">
						<p class="mb-3">
							<i class="bi bi-check-circle text-success me-2"></i>
							<strong>Tipo de actividad cambiado a Clase.</strong>
						</p>
						<div class="alert alert-info mb-3">
							<i class="bi bi-info-circle me-2"></i>
							Te ayudaremos a gestionar la solicitud de sala(s) para esta actividad, si deseas modificar el requerimiento debes hacerlo en la sección salas.
						</div>
						<p class="text-muted mb-0">
							<i class="bi bi-clock-history me-2"></i>
							En la próxima hora ingresaremos automáticamente tu solicitud de salas para su revisión a la unidad de aulas docentes. 
						</p>
					</div>
				`;
			}

            
            // CASO ESPECIAL: SweetAlert sin recarga automática
            Swal.fire({
					icon: 'info',
					title: '¡Actividad actualizada!',
					html: mensajeHTML,
					confirmButtonText: '<i class="bi bi-check me-2"></i>Entendido',
					confirmButtonColor: '#0d6efd',
					allowOutsideClick: false,
					allowEscapeKey: false,
					customClass: {
						popup: 'swal-wide'
					}
				}).then(() => {
					location.reload();
				});

            
            // ⚠️ CRÍTICO: Rehabilitar botón y NO continuar con el resto del código
            if (saveButton) saveButton.disabled = false;
            return Promise.resolve({ 
                json: () => Promise.resolve({ 
                    success: true, 
                    skipDocentes: true // Flag para saltar guardado de docentes
                }) 
            });
        }
        
        // ===== CASOS NORMALES: Mostrar mensaje si existe =====
        if (data.mensaje_sala) {
            mostrarToast(data.mensaje_sala, 'info', 5000);
        }

        // Continuar con docentes para casos normales
        if (requiereDocentes && docentesSeleccionados.length > 0) {
            const docentesData = new FormData();
            const idplanclases = document.getElementById('idplanclases').value;
            const idcurso = new URLSearchParams(window.location.search).get('curso');
            
            docentesData.append('idplanclases', idplanclases);
            docentesData.append('idcurso', idcurso);
            docentesData.append('horas', horasActividad);
            docentesData.append('docentes', JSON.stringify(docentesSeleccionados));

            return fetch('guardar_docentes.php', {
                method: 'POST',
                body: docentesData
            });
        } else {
            return Promise.resolve({ 
                json: () => Promise.resolve({ 
                    success: true, 
                    message: requiereDocentes ? 'No se seleccionaron docentes' : 'Actividad guardada sin docentes'
                }) 
            });
        }
    })
    .then(response => response.json())
    .then(data => {
        // Si es el caso especial, no ejecutar este bloque
        if (data.skipDocentes) {
            return;
        }
        
        if (data.success) {
            // Cerrar modal
            const modalElement = document.getElementById('activityModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
            
            // Ocultar toast de carga
            document.querySelector('.toast-container').innerHTML = '';
            
            // Toast de éxito
            let mensaje = 'Actividad guardada correctamente';
            if (huboChangioTipo && docentesSeleccionados.length > 0) {
                mensaje += ` (${docentesSeleccionados.length} docentes asignados)`;
            }
            mostrarToast(mensaje, 'success');
            
            // Recargar página después de un breve retraso
            setTimeout(() => location.reload(), 500);
            
        } else {
            throw new Error(data.message || 'Error al guardar los cambios');
        }
    })
    .catch(error => {
        console.error('Error completo:', error);
        
        // Ocultar toast de carga
        const loadingContainer = document.querySelector('.toast-container');
        if (loadingContainer) {
            loadingContainer.innerHTML = '';
        }
        
        // Mostrar error
        mostrarToast('Error al guardar los cambios: ' + error.message, 'danger');
        
        // Rehabilitar el botón de guardar
        if (saveButton) saveButton.disabled = false;
    });
}

function saveAutoActivity() {
    const form = document.getElementById('autoaprendizajeForm');
    const formData = new FormData();
    
    // Obtener los datos del formulario
    const idplanclases = document.getElementById('auto-idplanclases').value;
    const activityTitle = document.getElementById('auto-activity-title').value.trim();
    
    // Validar título
    if (activityTitle === '') {
        mostrarToast('Debe ingresar un título para la actividad de autoaprendizaje', 'danger');
        return;
    }
    
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
            setTimeout(() => location.reload(), 500);
        } else {
            throw new Error(data.message || 'Error al guardar el autoaprendizaje');
        }
    })
    .catch(error => {
        mostrarToast('Error: ' + error.message, 'danger');
    });
}

// ===========================================
// 7. SISTEMA DE SALAS COMPLETO
// ===========================================

async function configurarHeaderModalSala(idPlanClase, accion) {
    // Configurar título de la acción
    const tituloAccion = {
        'solicitar': 'Solicitar Sala',
        'modificar': 'Modificar Solicitud de Sala', 
        'modificar_asignada': 'Modificar Sala Asignada'
    };
    
    document.getElementById('salaModalTitle').textContent = tituloAccion[accion] || 'Gestionar Sala';
    document.getElementById('sala-modal-idplanclases').textContent = idPlanClase;
    
    // Valores por defecto mientras carga
    document.getElementById('sala-modal-fecha-hora').textContent = 'Cargando...';
    document.getElementById('sala-modal-tipo-sesion').textContent = 'Cargando...';
    
    try {
        // ✅ OBTENER DATOS DESDE LA BD
        const response = await fetch(`get_actividad_info.php?id=${idPlanClase}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('sala-modal-fecha-hora').textContent = data.fechaHora;
            document.getElementById('sala-modal-tipo-sesion').textContent = data.tipoSesion;
        } else {
            document.getElementById('sala-modal-fecha-hora').textContent = 'Información no disponible';
            document.getElementById('sala-modal-tipo-sesion').textContent = 'Información no disponible';
        }
    } catch (error) {
        console.error('Error al obtener información de la actividad:', error);
        document.getElementById('sala-modal-fecha-hora').textContent = 'Error al cargar';
        document.getElementById('sala-modal-tipo-sesion').textContent = 'Error al cargar';
    }
}


// FUNCIÓN PRINCIPAL: solicitarSala
async function solicitarSala(idPlanClase) {
    console.log('=== INICIANDO SOLICITAR SALA ===');
    console.log('ID Plan Clase:', idPlanClase);
    
     limpiarModalSalaCompleto();
    
    document.getElementById('salaForm').reset();
    document.getElementById('idplanclases').value = idPlanClase;
    document.getElementById('action').value = 'solicitar';
    configurarHeaderModalSala(idPlanClase, 'solicitar');
    
    // Obtener datos para prellenar el formulario
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

        const data = await response.json();
        if (data.success) {
            // Prellenar campos con datos existentes
            document.getElementById('campus').value = data.pcl_campus || 'Norte';
            document.getElementById('nSalas').value = data.pcl_nSalas || 1;
            document.getElementById('requiereSala').value = data.pcl_DeseaSala || 1;
            //document.getElementById('observaciones').value = data.observaciones || '';
			document.getElementById('textoObservacionesHistoricas').textContent = data.observaciones || 'Sin observaciones previas.';
            
            // NUEVA LÍNEA: Prellenar movilidad reducida
            document.getElementById('movilidadReducida').value = data.pcl_movilidadReducida || 'No';
            
            console.log('Datos precargados:', {
                campus: data.pcl_campus || 'Norte',
                nSalas: data.pcl_nSalas || 1,
                requiereSala: data.pcl_DeseaSala || 1,
                movilidadReducida: data.pcl_movilidadReducida || 'No'
            });
			
			const juntarCheckbox = document.getElementById('juntarSecciones');
            if (juntarCheckbox) {
                juntarCheckbox.checked = (data.pcl_AulaDescripcion === 'S');
            }
        }
    } catch (error) {
        console.error('Error al obtener datos:', error);
        // Valores por defecto si falla la carga
        document.getElementById('campus').value = 'Norte';
        document.getElementById('nSalas').value = 1;
        document.getElementById('requiereSala').value = 1;
        document.getElementById('movilidadReducida').value = 'No';  // NUEVO VALOR POR DEFECTO
    }
    
// Obtener el número de alumnos del elemento de la tabla
    const tr = document.querySelector(`tr[data-id="${idPlanClase}"]`);
    if (tr) {
    const alumnosTotales = tr.dataset.alumnos;
    document.getElementById('alumnosTotales').value = alumnosTotales || 0;
    console.log('👥 Alumnos totales configurados:', alumnosTotales);
    
    // NUEVO: Verificar bloques relacionados del mismo día
    const fechaCell = tr.cells[1] ? tr.cells[1].textContent.trim() : '';
    if (fechaCell) {
        const fechaParsed = parsearFechaParaConsulta(fechaCell);
        if (fechaParsed) {
            const urlParams = new URLSearchParams(window.location.search);
            const idCurso = urlParams.get('curso');
            
            console.log('🔍 Iniciando verificación de bloques relacionados:', {
                idCurso: idCurso,
                fecha: fechaParsed,
                idPlanClase: idPlanClase,
                fechaOriginal: fechaCell
            });
            
            // Verificar bloques relacionados después de un breve delay
            setTimeout(function() {
                verificarBloquesMismoDia(parseInt(idCurso), fechaParsed, parseInt(idPlanClase));
            }, 500);
        } else {
            console.warn('⚠️ No se pudo parsear la fecha:', fechaCell);
        }
    } else {
        console.warn('⚠️ No se encontró la celda de fecha');
    }
    
    // Calcular alumnos por sala (código existente)
    setTimeout(() => {
        console.log('⚡ Ejecutando cálculo inmediato en solicitarSala');
        calcularAlumnosPorSala();
        
        // Configurar juntar secciones si los datos están disponibles
        if (typeof datosJuntarSecciones !== 'undefined' && datosJuntarSecciones && !juntarSeccionesConfigurado) {
            configurarJuntarSecciones();
        }
    }, 500);
}
    
    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
    
    // Configurar listeners Y ejecutar verificación inicial
    setupModalListeners();
    
    console.log('=== SOLICITAR SALA COMPLETADO ===');
}

async function modificarSala(idPlanClase) {
    console.log('=== INICIANDO MODIFICAR SALA ===');
    console.log('ID Plan Clase:', idPlanClase);
    
     limpiarModalSalaCompleto();
    
    document.getElementById('salaForm').reset();
    document.getElementById('idplanclases').value = idPlanClase;
    configurarHeaderModalSala(idPlanClase, 'modificar');
    
    // Obtener el elemento de la tabla
    const tr = document.querySelector(`tr[data-id="${idPlanClase}"]`);
    if (!tr) {
        console.error('No se encontró la fila');
        return;
    }

    // Verificar el estado directamente desde la columna de estado
    const estadoCell = tr.querySelector('td:nth-child(9)');
    const estadoBadge = estadoCell.querySelector('.badge');
    const estadoTexto = estadoBadge ? estadoBadge.textContent.trim() : '';

    console.log('Estado detectado:', estadoTexto);

    // Determinar si está asignada (estado 3)
    const esAsignada = estadoTexto === 'Asignada';

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
            // Establecer valores del formulario
            document.getElementById('action').value = esAsignada || datos.estado === 3 ? 'modificar_asignada' : 'modificar';
            document.getElementById('campus').value = datos.pcl_campus || 'Norte';
            document.getElementById('nSalas').value = datos.pcl_nSalas || '1';
            document.getElementById('requiereSala').value = datos.pcl_DeseaSala || 1;
            
            // NUEVA LÍNEA: Precargar movilidad reducida
            document.getElementById('movilidadReducida').value = datos.pcl_movilidadReducida || 'No';
            
            console.log('Datos modificación precargados:', {
                campus: datos.pcl_campus || 'Norte',
                nSalas: datos.pcl_nSalas || '1',
                requiereSala: datos.pcl_DeseaSala || 1,
                movilidadReducida: datos.pcl_movilidadReducida || 'No'
            });
            
            // Mostrar mensajes anteriores en el campo observaciones
            //if (datos.mensajeAnterior) {
            //    document.getElementById('observaciones').value = datos.mensajeAnterior;
            //    document.getElementById('observaciones').placeholder = 'Escriba aquí su nuevo mensaje...';
            //} else {
            //    document.getElementById('observaciones').value = '';
            //    document.getElementById('observaciones').placeholder = 'Por favor, describa su requerimiento con el mayor nivel de detalle posible...';
            //}
			
			document.getElementById('observaciones').value = '';
document.getElementById('observaciones').placeholder = 'Por favor, describa su requerimiento con el mayor nivel de detalle posible...';
document.getElementById('textoObservacionesHistoricas').textContent = datos.mensajeAnterior || 'Sin observaciones previas.';

            
             document.getElementById('alumnosTotales').value = tr.dataset.alumnos;
            document.getElementById('alumnosTotales').readOnly = true;
            
            console.log('👥 Alumnos totales configurados:', tr.dataset.alumnos);
            
            // CRÍTICO: Calcular inmediatamente alumnos por sala
            setTimeout(() => {
                console.log('⚡ Ejecutando cálculo inmediato en modificarSala');
                calcularAlumnosPorSala();
                
                // Configurar juntar secciones si los datos están disponibles
                if (typeof datosJuntarSecciones !== 'undefined' && datosJuntarSecciones && !juntarSeccionesConfigurado) {
                    configurarJuntarSecciones();
                }
            }, 500);
         // NUEVO: Verificar bloques relacionados del mismo día
		const fechaCell = tr.cells[1] ? tr.cells[1].textContent.trim() : '';
        if (fechaCell) {
            const fechaParsed = parsearFechaParaConsulta(fechaCell);
            if (fechaParsed) {
                const urlParams = new URLSearchParams(window.location.search);
                const idCurso = urlParams.get('curso');
                
                console.log('🔍 Iniciando verificación de bloques relacionados (modificar):', {
                    idCurso: idCurso,
                    fecha: fechaParsed,
                    idPlanClase: idPlanClase,
                    fechaOriginal: fechaCell
                });
                
                // Verificar bloques relacionados después de un breve delay
                setTimeout(function() {
                    verificarBloquesMismoDia(parseInt(idCurso), fechaParsed, parseInt(idPlanClase));
                }, 500);
            } else {
                console.warn('⚠️ No se pudo parsear la fecha:', fechaCell);
            }
        }
		
		const juntarCheckbox = document.getElementById('juntarSecciones');
            if (juntarCheckbox) {
                juntarCheckbox.checked = (datos.pcl_AulaDescripcion === 'S');
                console.log('Checkbox juntar secciones:', datos.pcl_AulaDescripcion === 'S');
            }
		
		}
    } catch (error) {
        console.error('Error:', error);
        mostrarToastSalas('Error al cargar los datos de la sala', 'danger');
    }

    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
    
    // Configurar listeners Y ejecutar verificación inicial
    setupModalListeners();
    
    console.log('=== MODIFICAR SALA COMPLETADO ===');
}

// FUNCIÓN: configurarJuntarSecciones
// FUNCIÓN ACTUALIZADA: configurarJuntarSecciones
function configurarJuntarSecciones() {
    console.log("🔧 Iniciando configuración de juntar secciones");

    // Verifica condiciones lógicas base
    if (!datosJuntarSecciones || !datosJuntarSecciones.esSecciones1 || !datosJuntarSecciones.tieneMultiplesSecciones) {
        console.log("❌ No aplica para juntar secciones");
        return;
    }

    let intentos = 0;
    const maxIntentos = 20;
    const intervalo = 200;

    const esperarRender = setInterval(() => {
        const campoNSalas = document.querySelector('#nSalas');
        const divCheckbox = document.querySelector('#juntarSeccionesDiv');

        if (campoNSalas && divCheckbox) {
            clearInterval(esperarRender);

            const contenedorNSalas = campoNSalas.closest('.mb-3');
            if (!contenedorNSalas) {
                console.warn("⚠️ No se encontró contenedor de #nSalas");
                return;
            }

            try {
                // Asegurar que no tenga estilos ocultos anteriores
                divCheckbox.style.removeProperty('display');
                divCheckbox.classList.remove('d-none');

                // Insertar el div antes del campo nSalas
                contenedorNSalas.parentNode.insertBefore(divCheckbox, contenedorNSalas);

                divCheckbox.style.removeProperty('display');
                divCheckbox.style.visibility = 'visible';
                divCheckbox.style.opacity = '1';
                divCheckbox.classList.remove('d-none');
                divCheckbox.style.height = 'auto';
                divCheckbox.style.marginBottom = '1rem';
                divCheckbox.style.padding = '1rem';
                divCheckbox.style.zIndex = '10';

                // 🆕 AGREGAR EVENT LISTENER PARA EL CHECKBOX
                const checkbox = document.getElementById('juntarSecciones');
                if (checkbox) {
                    // Remover listener anterior si existe para evitar duplicados
                    checkbox.removeEventListener('change', manejarCambioJuntarSecciones);
                    
                    // Agregar nuevo listener
                    checkbox.addEventListener('change', manejarCambioJuntarSecciones);
                    
                    console.log("✅ Event listener configurado para juntar secciones");
                } else {
                    console.warn("⚠️ No se encontró checkbox juntarSecciones");
                }

                juntarSeccionesConfigurado = true;
                console.log("✅ Checkbox juntarsecciones visible y movido correctamente");
                
            } catch (error) {
                console.error("❌ Error al insertar checkbox:", error);
            }
        } else {
            intentos++;
            if (intentos >= maxIntentos) {
                clearInterval(esperarRender);
                console.warn("❌ No se pudo configurar checkbox después de varios intentos");
            }
        }
    }, intervalo);
}

// 🆕 NUEVA FUNCIÓN: Manejar cambio de checkbox juntar secciones
function manejarCambioJuntarSecciones() {
    const checkbox = document.getElementById('juntarSecciones');
    const alumnosTotalesInput = document.getElementById('alumnosTotales');
    
    if (!checkbox || !alumnosTotalesInput) {
        console.error('❌ No se encontraron elementos necesarios');
        return;
    }
    
    console.log('🔄 Cambio en juntar secciones:', checkbox.checked);
    
    if (checkbox.checked) {
        // Usar el cupo total de todas las secciones
        const cupoTotal = datosJuntarSecciones.cupoTotalSecciones;
        alumnosTotalesInput.value = cupoTotal;
        
        console.log('✅ Actualizado a cupo total:', cupoTotal);
        
        // Mostrar mensaje informativo
        mostrarMensajeJuntarSecciones(true, cupoTotal);
        
    } else {
        // Volver al cupo de la sección actual
        const cupoSeccionActual = datosJuntarSecciones.cupoSeccionActual;
        alumnosTotalesInput.value = cupoSeccionActual;
        
        console.log('✅ Actualizado a cupo sección actual:', cupoSeccionActual);
        
        // Ocultar mensaje informativo
        mostrarMensajeJuntarSecciones(false);
    }
    
    // IMPORTANTE: Recalcular alumnos por sala
    calcularAlumnosPorSala();
    
    // IMPORTANTE: Actualizar consulta de salas disponibles
    actualizarSalasDisponibles();
    
    // IMPORTANTE: Verificar condiciones de computación con el nuevo número
    verificarCondicionesComputacion();
}

// 🆕 NUEVA FUNCIÓN: Mostrar mensaje cuando se juntan secciones
function mostrarMensajeJuntarSecciones(mostrar, cupoTotal = 0) {
    let mensajeDiv = document.getElementById('mensaje-juntar-secciones');
    
    if (mostrar) {
        if (!mensajeDiv) {
            // Crear el mensaje si no existe
            const alumnosTotalesDiv = document.getElementById('alumnosTotales').closest('.mb-3');
            mensajeDiv = document.createElement('div');
            mensajeDiv.id = 'mensaje-juntar-secciones';
            mensajeDiv.className = 'alert alert-light alert-sm mt-2';
            alumnosTotalesDiv.appendChild(mensajeDiv);
        }
        mensajeDiv.innerHTML = ``;
       
        mensajeDiv.style.display = 'none';
        
    } else {
        if (mensajeDiv) {
            mensajeDiv.style.display = 'none';
        }
    }
}

/**
 * Verificar si el checkbox sigue existiendo después de un tiempo
 */
function verificarPersistenciaCheckbox() {
    setTimeout(() => {
        const checkbox = document.querySelector('input[name="juntarSecciones"]');
        const divContainer = document.getElementById('juntarSeccionesDiv');
        
        console.log('🔍 === VERIFICACIÓN DE PERSISTENCIA (después de 2 segundos) ===');
        console.log('¿Existe el checkbox?', !!checkbox);
        console.log('¿Existe el div container?', !!divContainer);
        
        if (checkbox) {
            console.log('✅ Checkbox sigue existente, ID:', checkbox.id);
            console.log('✅ Checkbox visible?', checkbox.offsetParent !== null);
            
            // NUEVO: Analizar estilos CSS del checkbox
            const computedStyle = window.getComputedStyle(checkbox);
            console.log('🎨 Estilos CSS del checkbox:', {
                display: computedStyle.display,
                visibility: computedStyle.visibility,
                opacity: computedStyle.opacity,
                position: computedStyle.position,
                top: computedStyle.top,
                left: computedStyle.left,
                transform: computedStyle.transform
            });
            
            // NUEVO: Verificar clases CSS aplicadas
            console.log('📋 Clases CSS del checkbox:', checkbox.className);
            console.log('📋 Clases CSS del div padre:', checkbox.closest('.form-check')?.className);
            
        } else {
            console.log('❌ Checkbox NO existe - fue eliminado por algo');
        }
        
        if (divContainer) {
            console.log('✅ Div container sigue existente');
            console.log('✅ Div visible?', divContainer.offsetParent !== null);
            console.log('✅ Contenido del div:', divContainer.innerHTML.substring(0, 100) + '...');
        } else {
            console.log('❌ Div container NO existe - fue eliminado por algo');
        }
        
        console.log('🏁 === FIN VERIFICACIÓN DE PERSISTENCIA ===');
    }, 500);
}

// FUNCIÓN: actualizarAlumnosTotales
 
function actualizarAlumnosTotales() {
    const juntarSeccionesCheckbox = document.querySelector('input[name="juntarSecciones"]');
    const alumnosTotalesInput = document.getElementById('alumnosTotales');
    
    if (!alumnosTotalesInput) {
        console.error('❌ Campo alumnosTotales no encontrado');
        return;
    }
    
    if (typeof datosJuntarSecciones === 'undefined' || !datosJuntarSecciones) {
        console.error('❌ datosJuntarSecciones no disponible en actualizarAlumnosTotales');
        return;
    }
    
    let alumnosTotales;
    
    if (juntarSeccionesCheckbox && juntarSeccionesCheckbox.checked) {
        alumnosTotales = datosJuntarSecciones.cupoTotalSecciones;
        console.log('📊 Usando cupo total de secciones:', alumnosTotales);
    } else {
        alumnosTotales = datosJuntarSecciones.cupoSeccionActual;
        console.log('📊 Usando cupo sección actual:', alumnosTotales);
    }
    
    alumnosTotalesInput.value = alumnosTotales;
    calcularAlumnosPorSala();
}

var calculoAlumnosTimeout;

function calcularAlumnosPorSala() {
    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
    
    // Actualizar el campo de alumnos por sala
    const alumnosPorSalaInput = document.getElementById('alumnosPorSala');
	
	if (typeof actualizarSalasDisponibles === 'function') {
        actualizarSalasDisponibles();
    }
	
    if (alumnosPorSalaInput) {
        alumnosPorSalaInput.value = alumnosPorSala;
        
        console.log('✅ Cálculo alumnos por sala:', {
            totalAlumnos: totalAlumnos,
            nSalas: nSalas,
            alumnosPorSala: alumnosPorSala,
            formula: `Math.ceil(${totalAlumnos} ÷ ${nSalas}) = ${alumnosPorSala}`
        });
    }
}

// 2. NUEVA FUNCIÓN: Consultar disponibilidad de salas de computación
function consultarSalasComputacion(campus, nSalas, totalAlumnos) {
    const seccionComputacion = document.getElementById('seccion-computacion');
    if (!seccionComputacion) return;
    
    console.log('🔍 Consultando salas de computación (con validación previa):', {
        campus: campus,
        nSalas: nSalas,
        totalAlumnos: totalAlumnos
    });
    
    // Validaciones básicas - ocultar si no cumple criterios
    if (campus !== 'Norte' || nSalas > 2 || totalAlumnos <= 0) {
        console.log('❌ No cumple condiciones básicas, ocultando sección');
        ocultarSeccionComputacion();
        return;
    }
    
    // Obtener datos de la actividad actual
    const idplanclases = document.getElementById('idplanclases').value;
    
    // Buscar la fila correspondiente en la tabla para obtener fecha y horarios
    const fila = document.querySelector(`tr[data-id="${idplanclases}"]`);
    if (!fila) {
        console.error('No se encontró la fila de la actividad');
        ocultarSeccionComputacion();
        return;
    }
    
    // Extraer fecha y horarios de la fila
    const fechaCell = fila.cells[1].textContent.trim(); // Columna de fecha
    const horarioCell = fila.cells[2].textContent.trim(); // Columna de horario
    
    // Parsear fecha y horarios
    const fecha = parsearFecha(fechaCell);
    const horarios = parsearHorarios(horarioCell);
    
    if (!fecha || !horarios.inicio || !horarios.fin) {
        console.error('Error al parsear fecha u horarios:', {fecha, horarios});
        ocultarSeccionComputacion();
        return;
    }
    
    // Mostrar loading
    mostrarLoadingComputacion();
    
    console.log('🚀 Consultando disponibilidad real en servidor...');
    
    // Realizar consulta AJAX
    fetch('consultar_computacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'consultar_disponibilidad',
            campus: campus,
            fecha: fecha,
            hora_inicio: horarios.inicio,
            hora_fin: horarios.fin,
            n_salas: nSalas,
            total_alumnos: totalAlumnos
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('📋 Respuesta de disponibilidad:', data);
        if (data.success) {
            mostrarSeccionComputacion(data);
        } else {
            console.error('Error en consulta:', data.message);
            ocultarSeccionComputacion();
        }
    })
    .catch(error => {
        console.error('Error de red:', error);
        ocultarSeccionComputacion();
    });
}

// 3. FUNCIÓN: Mostrar loading mientras consulta
function mostrarLoadingComputacion() {
    const seccionComputacion = document.getElementById('seccion-computacion');
    seccionComputacion.style.display = 'block';
    seccionComputacion.innerHTML = `
        <hr>
        <div class="mb-3 text-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <small class="text-muted">Consultando disponibilidad de salas de computación...</small>
        </div>
    `;
}

// 4. FUNCIÓN: Mostrar u ocultar sección según resultado
function mostrarSeccionComputacion(data) {
    const seccionComputacion = document.getElementById('seccion-computacion');
    console.log('🟢 Mostrando sección de computación', data);
    
    if (data.mostrar_seccion) {
        // Restaurar HTML original de la sección
        restaurarHTMLSeccionComputacion();
        seccionComputacion.style.display = 'block';
        
        // NUEVA LÓGICA: Solo mostrar pregunta si HAY opciones disponibles
        if (data.opciones_disponibles && data.opciones.length > 0) {
            console.log('✅ Opciones disponibles:', data.opciones.length);
            
            // Mostrar la pregunta y las opciones
            mostrarPreguntaConOpciones(data.opciones);
            
        } else {
            console.log('⚠️ Sin opciones disponibles:', data.mensaje);
            
            // NO mostrar pregunta, solo mensaje informativo
            mostrarMensajeSinDisponibilidad(data.mensaje || 'Las salas de computación no están disponibles para este horario');
        }
    } else {
        console.log('❌ No mostrar sección:', data.mensaje);
        ocultarSeccionComputacion();
    }
}

// NUEVA FUNCIÓN: Mostrar pregunta CON opciones disponibles
function mostrarPreguntaConOpciones(opciones) {
    // Mostrar la pregunta
    const preguntaContainer = document.querySelector('.form-check');
    const opcionesContainer = document.getElementById('opciones-computacion');
    const mensajeSinOpciones = document.getElementById('mensaje-sin-opciones');
    
    if (preguntaContainer) preguntaContainer.style.display = 'block';
    if (mensajeSinOpciones) mensajeSinOpciones.style.display = 'none';
    
    // Solo mostrar opciones si hace clic en "Sí"
    setupEventListenersComputacion();
    
    // Llenar las opciones disponibles
    mostrarOpcionesComputacion(opciones);
}

// NUEVA FUNCIÓN: Mostrar mensaje SIN pregunta cuando no hay disponibilidad
function mostrarMensajeSinDisponibilidad(mensaje) {
    // Ocultar la pregunta
    const preguntaContainer = document.querySelector('.form-check');
    const opcionesContainer = document.getElementById('opciones-computacion');
    const mensajeSinOpciones = document.getElementById('mensaje-sin-opciones');
    const textoMensaje = document.getElementById('texto-mensaje-sin-opciones');
    
    if (preguntaContainer) preguntaContainer.style.display = 'none';
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    
    // Mostrar mensaje informativo mejorado
    if (mensajeSinOpciones && textoMensaje) {
        mensajeSinOpciones.style.display = 'block';
        
        // Crear mensaje más amigable
        const mensajeAmigable = `
            <strong>Salas de computación no disponibles</strong><br>
            <small>${mensaje}</small><br>
            <small class="text-muted">Las salas podrían estar ocupadas por otras actividades en este horario.</small>
        `;
        
        textoMensaje.innerHTML = mensajeAmigable;
    }
}

// NUEVA FUNCIÓN: Manejar cambio en checkbox de computación
function handleComputacionCheckboxChange() {
    const checkbox = document.getElementById('deseaComputacion');
    const opcionesContainer = document.getElementById('opciones-computacion');
    
    if (checkbox.checked) {
        console.log('✅ Usuario seleccionó reservar salas de computación');
        opcionesContainer.style.display = 'block';
    } else {
        console.log('❌ Usuario deseleccionó salas de computación');
        opcionesContainer.style.display = 'none';
        // Limpiar selecciones
        const radios = opcionesContainer.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => radio.checked = false);
    }
}

// 5. FUNCIÓN: Ocultar sección de computación
function ocultarSeccionComputacion() {
    const seccionComputacion = document.getElementById('seccion-computacion');
    console.log('🔴 Ocultando sección de computación');
    
    if (seccionComputacion) {
        seccionComputacion.style.display = 'none';
        
        // Limpiar selección si existe
        const checkbox = document.getElementById('deseaComputacion');
        if (checkbox) {
            checkbox.checked = false;
        }
        
        // Limpiar opciones si existen
        const opcionesContainer = document.getElementById('opciones-computacion');
        if (opcionesContainer) {
            opcionesContainer.style.display = 'none';
        }
        
        // Limpiar radios si existen
        const radios = document.querySelectorAll('input[name="opcion_computacion"]');
        radios.forEach(radio => radio.checked = false);
    }
}

// 6. FUNCIÓN: Restaurar HTML original de la sección
function restaurarHTMLSeccionComputacion() {
    const seccionComputacion = document.getElementById('seccion-computacion');
    seccionComputacion.innerHTML = `
        <hr>
        <div class="mb-3">
            <label class="form-label fw-bold text-primary mb-0">
                <i class="bi bi-pc-display me-2"></i>
                Salas de Computación
            </label>
            
            <div class="alert alert-info alert-sm">
                <i class="bi bi-info-circle me-1"></i>
                <small>
                    Las salas de computación son recursos limitados. Solo se asignan si toda la sección puede usar el recurso de manera efectiva.
                </small>
            </div>
            
            <!-- PREGUNTA - Se mostrará/ocultará dinámicamente -->
            <div class="form-check mb-3" style="display: none;">
                <input class="form-check-input border border-dark" type="checkbox" id="deseaComputacion">
                <label class="form-check-label fw-bold text-success" for="deseaComputacion">
                    <i class="bi bi-check-circle me-1"></i>
                    ¿Desea reservar sala(s) de computación para esta actividad?
                </label>
                <small class="d-block text-success mt-1">
                    <i class="bi bi-info-circle me-1"></i>
                    ¡Hay salas de computación disponibles para este horario!
                </small>
            </div>
            
            <!-- OPCIONES - Solo aparecen si selecciona "Sí" -->
            <div id="opciones-computacion" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Opciones Disponibles
                        </h6>
                        <div id="lista-opciones-computacion">
                            <!-- Se llenará dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- MENSAJE SIN DISPONIBILIDAD - Solo aparece si no hay salas -->
            <div id="mensaje-sin-opciones" style="display: none;">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span id="texto-mensaje-sin-opciones"></span>
                </div>
            </div>
        </div>
    `;
}

// 7. FUNCIÓN: Configurar event listeners para la sección de computación
function setupEventListenersComputacion() {
    const checkbox = document.getElementById('deseaComputacion');
    if (checkbox) {
        // Remover listener anterior para evitar duplicados
        checkbox.removeEventListener('change', handleComputacionCheckboxChange);
        // Agregar nuevo listener
        checkbox.addEventListener('change', handleComputacionCheckboxChange);
    }
}

// 8. FUNCIÓN: Mostrar opciones disponibles
function mostrarOpcionesComputacion(opciones) {
    const contenedorOpciones = document.getElementById('lista-opciones-computacion');
    const mensajeSinOpciones = document.getElementById('mensaje-sin-opciones');
    
    if (!contenedorOpciones) return;
    
    contenedorOpciones.innerHTML = '';
    mensajeSinOpciones.style.display = 'none';
    
    opciones.forEach((opcion, index) => {
        const div = document.createElement('div');
        div.className = 'form-check mb-2';
        
        const input = document.createElement('input');
        input.className = 'form-check-input border border-dark';
        input.type = 'radio';
        input.name = 'opcion_computacion';
        input.id = `opcion_computacion_${index}`;
        input.value = JSON.stringify(opcion);
        
        const label = document.createElement('label');
        label.className = 'form-check-label';
        label.htmlFor = `opcion_computacion_${index}`;
        
        // Crear descripción detallada
        const descripcion = document.createElement('div');
        descripcion.innerHTML = `
            <strong>${opcion.nombre}</strong><br>
            <small class="text-muted">${opcion.descripcion}</small>
        `;
        
        label.appendChild(descripcion);
        div.appendChild(input);
        div.appendChild(label);
        contenedorOpciones.appendChild(div);
    });
    
    // Configurar event listeners para los radios
    setupEventListenersComputacion();
}

// 9. FUNCIÓN: Mostrar mensaje cuando no hay opciones
function mostrarMensajeSinOpciones(mensaje) {
    const opcionesContainer = document.getElementById('opciones-computacion');
    const mensajeSinOpciones = document.getElementById('mensaje-sin-opciones');
    const textoMensaje = document.getElementById('texto-mensaje-sin-opciones');
    
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (mensajeSinOpciones) {
        mensajeSinOpciones.style.display = 'block';
        if (textoMensaje) textoMensaje.textContent = mensaje;
    }
}

// 10. FUNCIONES AUXILIARES: Parsear fecha y horarios
function parsearFecha(fechaTexto) {
    // Formato esperado: "18/06/2025" -> "2025-06-18"
    try {
        const partes = fechaTexto.split('/');
        if (partes.length === 3) {
            const dia = partes[0].padStart(2, '0');
            const mes = partes[1].padStart(2, '0');
            const anio = partes[2];
            return `${anio}-${mes}-${dia}`;
        }
        return null;
    } catch (error) {
        console.error('Error al parsear fecha:', error);
        return null;
    }
}

function parsearHorarios(horarioTexto) {
    // Formato esperado: "12:00 - 13:30" -> {inicio: "12:00:00", fin: "13:30:00"}
    try {
        const partes = horarioTexto.split(' - ');
        if (partes.length === 2) {
            return {
                inicio: partes[0].trim() + ':00',
                fin: partes[1].trim() + ':00'
            };
        }
        return {inicio: null, fin: null};
    } catch (error) {
        console.error('Error al parsear horarios:', error);
        return {inicio: null, fin: null};
    }
}

// 11. FUNCIÓN COMPLETA: guardarSala
async function guardarSala() {
    const form = document.getElementById('salaForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const datos = Object.fromEntries(formData.entries());
    
    // Agregar campos adicionales
    datos.requiereSala = document.getElementById('requiereSala').value;
    datos.observaciones = document.getElementById('observaciones').value;
    datos.movilidadReducida = document.getElementById('movilidadReducida').value;
    datos.alumnosPorSala = document.getElementById('alumnosPorSala').value;     // ✅ ESTE ES EL IMPORTANTE
    datos.alumnosTotales = document.getElementById('alumnosTotales').value;     // Para referencia
    
    // Verificar si hay selección de juntar secciones
    const juntarSeccionesCheckbox = document.querySelector('input[name="juntarSecciones"]');
    if (juntarSeccionesCheckbox && juntarSeccionesCheckbox.checked) {
        datos.juntarSecciones = 'on';
    }
    
    // ✅ Debug para verificar el cálculo
    console.log('📊 Cálculo de alumnos:', {
        alumnosTotales: datos.alumnosTotales,
        alumnosPorSala: datos.alumnosPorSala,
        nSalas: datos.nSalas,
        juntarSecciones: datos.juntarSecciones ? 'SÍ' : 'NO'
    });
    
    // NUEVA LÓGICA: Verificar si hay selección de computación
    const deseaComputacion = document.getElementById('deseaComputacion');
    const tieneComputacion = deseaComputacion && deseaComputacion.checked;
    
    if (tieneComputacion) {
        // Buscar qué opción de computación seleccionó
        const opcionSeleccionada = document.querySelector('input[name="opcion_computacion"]:checked');
        
        if (!opcionSeleccionada) {
            mostrarToastSalas('Debe seleccionar una opción de sala de computación', 'danger');
            return;
        }
        
        const opcion = JSON.parse(opcionSeleccionada.value);
        
        // Validar disponibilidad antes de guardar
        const salasAValidar = opcion.tipo === 'individual' ? 
                             [opcion.id_sala] : 
                             opcion.id_sala_multiple;
        
        // Obtener datos de la actividad para validación
        const idplanclases = document.getElementById('idplanclases').value;
        const fila = document.querySelector(`tr[data-id="${idplanclases}"]`);
        const fecha = parsearFecha(fila.cells[1].textContent.trim());
        const horarios = parsearHorarios(fila.cells[2].textContent.trim());
        
        try {
            const validacionResponse = await fetch('consultar_computacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'validar_antes_guardar',
                    salas_seleccionadas: salasAValidar,
                    fecha: fecha,
                    hora_inicio: horarios.inicio,
                    hora_fin: horarios.fin
                })
            });
            
            const validacionData = await validacionResponse.json();
            
            if (!validacionData.success) {
                mostrarToastSalas(validacionData.mensaje, 'danger');
                // Recargar opciones de computación
                calcularAlumnosPorSala();
                return;
            }
            
            // Si la validación es exitosa, proceder con guardado especial
            await guardarConComputacion(datos, opcion);
            
        } catch (error) {
            console.error('Error en validación:', error);
            mostrarToastSalas('Error al validar disponibilidad', 'danger');
            return;
        }
        
    } else {
        // Guardado normal sin computación
        await guardarSalaNormal(datos);
    }
}

// 12. NUEVA FUNCIÓN: Guardar con computación
async function guardarConComputacion(datos, opcionComputacion) {
    try {
        const salasComputacion = opcionComputacion.tipo === 'individual' ? 
                                [opcionComputacion.id_sala] : 
                                opcionComputacion.id_sala_multiple;
        
        const response = await fetch('salas2.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'guardar_con_computacion',
                idplanclases: datos.idplanclases,
                salas_computacion: salasComputacion,
                observaciones: datos.observaciones,
                requiereSala: datos.requiereSala,
                nSalas: datos.nSalas,
                campus: datos.campus,
				juntarSecciones: document.getElementById('juntarSecciones').checked ? 'on' : '',
				alumnosPorSala: document.getElementById('alumnosPorSala').value,
                alumnosTotales: document.getElementById('alumnosTotales').value,
                movilidadReducida: document.getElementById('movilidadReducida').value
            })
        });

        const responseData = await response.json();
        console.log('Server Response:', responseData);

        if (response.ok && responseData.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarToastSalas(responseData.message || 'Salas de computación reservadas correctamente');

            // Recargar tabla de salas
            recargarTablaSalas();
        } else {
            mostrarToastSalas(responseData.error || 'Error al reservar salas de computación', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarToastSalas('Error al procesar la reserva de computación', 'danger');
    }
}

// 13. FUNCIÓN: Guardar sin computación (lógica normal existente)
async function guardarSalaNormal(datos) {
    try {
        const response = await fetch('salas2.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(datos)
        });

        const responseData = await response.json();

        if (response.ok && responseData.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarToastSalas('Sala gestionada correctamente');
            recargarTablaSalas();
        } else {
            mostrarToastSalas(responseData.error || 'Error al guardar los cambios', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarToastSalas('Error al procesar la solicitud', 'danger');
    }
}

// 14. FUNCIÓN: Recargar tabla de salas
function recargarTablaSalas() {
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
}

function setupModalListeners() {
	
	const nSalasSelect = document.getElementById('nSalas');
    const campusSelect = document.getElementById('campus');
    const alumnosTotalesInput = document.getElementById('alumnosTotales');
	
	const requiereSalaSelect = document.getElementById('requiereSala');
if (requiereSalaSelect) {
    requiereSalaSelect.removeEventListener('change', manejarCambioRequiereSala);
    requiereSalaSelect.addEventListener('change', manejarCambioRequiereSala);
}
    
    
    // Event listener para número de salas
    if (nSalasSelect) {
        nSalasSelect.removeEventListener('change', manejarCambioSalas);
        nSalasSelect.addEventListener('change', manejarCambioSalas);
    }
    
    // Event listener para campus
    if (campusSelect) {
        campusSelect.removeEventListener('change', manejarCambioCampus);
        campusSelect.addEventListener('change', manejarCambioCampus);
    }
    
    // Event listener para alumnos
    if (alumnosTotalesInput) {
        alumnosTotalesInput.removeEventListener('change', manejarCambioAlumnos);
        alumnosTotalesInput.addEventListener('change', manejarCambioAlumnos);
    }
    
    // CRÍTICO: Ejecutar cálculo inicial Y consulta
    setTimeout(() => {
        console.log('⚡ Ejecutando cálculo inicial en setupModalListeners');
        calcularAlumnosPorSala();
        verificarCondicionesComputacion();
		 manejarCambioRequiereSala(); //
        
        actualizarSalasDisponibles();
    }, 500);
}

function manejarCambioRequiereSala() {
    console.log('🔄 Cambio en requiere sala detectado');
    
    const requiereSalaSelect = document.getElementById('requiereSala');
    if (!requiereSalaSelect) {
        console.warn('⚠️ Campo requiereSala no encontrado');
        return;
    }
    
    const requiere = requiereSalaSelect.value === '1';
    console.log('🎯 Requiere sala:', requiere);
    
    // Lista de campos a controlar (siguiendo patrón existente)
    const campos = ['campus', 'nSalas', 'movilidadReducida', 'observaciones'];
    
    // Habilitar/deshabilitar campos principales
    campos.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.disabled = !requiere;
            
            // Limpiar observaciones si no requiere sala
            if (id === 'observaciones' && !requiere) {
                campo.value = "";
                campo.placeholder = "No se requieren observaciones ya que no solicita sala";
            } else if (id === 'observaciones' && requiere) {
                campo.placeholder = "Por favor, describa su requerimiento con el mayor nivel de detalle posible...";
            }
        }
    });
    
    // Controlar secciones especiales (aprovechando lógica existente)
    const seccionComp = document.getElementById('seccion-computacion');
    if (seccionComp) {
        seccionComp.style.display = requiere ? 'block' : 'none';
        
        // Limpiar checkbox de computación
        if (!requiere) {
            const deseaComp = document.getElementById('deseaComputacion');
            if (deseaComp) deseaComp.checked = false;
        }
    }
    
    // Controlar sección juntar secciones
 //   const juntarSeccionesDiv = document.getElementById('juntarSeccionesDiv');
 //   if (juntarSeccionesDiv) {
 //       juntarSeccionesDiv.style.display = requiere ? 'block' : 'none';
 //       
 //       // Limpiar checkbox
 //       if (!requiere) {
 //           const juntarCheckbox = document.getElementById('juntarSecciones');
 //           if (juntarCheckbox) juntarCheckbox.checked = false;
 //       }
 //   }
    
    // Controlar botón observaciones históricas
    const btnObsHistoricas = document.querySelector('[data-bs-target="#observacionesHistoricas"]');
    if (btnObsHistoricas) {
        btnObsHistoricas.disabled = !requiere;
    }
    
    // Estilo visual para campos readonly (mantener readonly pero cambiar apariencia)
    const camposReadonly = ['alumnosTotales', 'alumnosPorSala'];
    camposReadonly.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            if (!requiere) {
                campo.style.opacity = '0.5';
                campo.style.backgroundColor = '#f8f9fa';
            } else {
                campo.style.opacity = '1';
                campo.style.backgroundColor = '';
            }
        }
    });
    
    // Recalcular alumnos por sala usando función existente
    if (requiere) {
        calcularAlumnosPorSala();
    } else {
        // Si no requiere sala, poner en 0
        const alumnosPorSala = document.getElementById('alumnosPorSala');
        if (alumnosPorSala) {
            alumnosPorSala.value = '0';
        }
    }
    
    // Llamar verificación de computación (aprovecha función existente)
    if (requiere) {
        verificarCondicionesComputacion();
    }
}

function manejarCambioSalas() {
    console.log('🔄 Cambio en número de salas detectado');
    calcularAlumnosPorSala();
    verificarCondicionesComputacion();
    // NUEVA LÍNEA CRÍTICA:
    actualizarSalasDisponibles();
}

function manejarCambioCampus() {
    console.log('🔄 Cambio de campus detectado');
    calcularAlumnosPorSala();
    verificarCondicionesComputacion();
    // NUEVA LÍNEA CRÍTICA:
    actualizarSalasDisponibles();
}

function manejarCambioAlumnos() {
    console.log('🔄 Cambio en número de alumnos detectado');
    calcularAlumnosPorSala();
    verificarCondicionesComputacion();
    // NUEVA LÍNEA CRÍTICA:
    actualizarSalasDisponibles();
}

function verificarCondicionesComputacion() {
     const alumnosTotalesEl = document.getElementById('alumnosTotales');
    const nSalasEl = document.getElementById('nSalas');
    const campusEl = document.getElementById('campus');
    
    if (!alumnosTotalesEl || !nSalasEl || !campusEl) {
        console.warn('⚠️ Elementos del modal no encontrados, saltando verificación de computación');
        return;
    }
    
    const totalAlumnos = parseInt(alumnosTotalesEl.value) || 0;
    const nSalas = parseInt(nSalasEl.value) || 1;
    const campus = campusEl.value;
    
    console.log('🔍 Verificando condiciones de computación:', {
        campus: campus,
        nSalas: nSalas,
        totalAlumnos: totalAlumnos,
        timestamp: new Date().toISOString()
    });
    
    // Validaciones específicas para salas de computación
    const esNorte = campus === 'Norte';
    const salasValidas = nSalas >= 1 && nSalas <= 2; // Solo 1 o 2 salas de computación
    const tieneAlumnos = totalAlumnos > 0;
    
    const cumpleCondiciones = esNorte && salasValidas && tieneAlumnos;
    
    console.log('📋 Detalle de validaciones:', {
        esNorte: esNorte,
        salasValidas: salasValidas,
        nSalasActual: nSalas,
        rangoPermitido: '1-2 salas',
        tieneAlumnos: tieneAlumnos,
        totalAlumnos: totalAlumnos,
        cumpleCondiciones: cumpleCondiciones
    });
    
    // Mostrar razón específica por la que no se cumple
    if (!cumpleCondiciones) {
        let razon = [];
        if (!esNorte) razon.push(`Campus ${campus} no es Norte`);
        if (!salasValidas) razon.push(`${nSalas} salas fuera de rango (1-2)`);
        if (!tieneAlumnos) razon.push(`Sin alumnos (${totalAlumnos})`);
        
        console.log('❌ No cumple condiciones:', razon.join(', '));
    }
    
    if (cumpleCondiciones) {
        console.log('✅ Cumple condiciones, consultando disponibilidad...');
        // Proceder con la consulta de disponibilidad
        consultarSalasComputacion(campus, nSalas, totalAlumnos);
    } else {
        console.log('❌ No cumple condiciones, ocultando sección');
        ocultarSeccionComputacion();
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




// ===========================================
// 8. SISTEMA DE SALAS DISPONIBLES
// ===========================================

function actualizarSalasDisponibles() {
    console.log('🔄 INICIANDO actualizarSalasDisponibles');
    
    // Obtener valores del formulario
    var alumnosPorSala = parseInt(document.getElementById('alumnosPorSala').value) || 0;
    var campus = document.getElementById('campus').value || '';
    
    // Obtener fecha y horarios de la actividad seleccionada
    var fecha = obtenerFechaActividad();
    var horarios = obtenerHorariosActividad();
    
    console.log('🔄 Datos para consulta:', {
        alumnosPorSala: alumnosPorSala,
        campus: campus,
        fecha: fecha,
        horarios: horarios
    });
    
    // Validar que tengamos los datos mínimos
    if (!alumnosPorSala || !campus || !fecha || !horarios.inicio || !horarios.termino) {
        console.log('❌ Faltan datos, ocultando badge');
        ocultarBadgeSalas();
        return;
    }
    
    // LÍNEA CRÍTICA CORREGIDA:
    consultarSalasDisponibles(alumnosPorSala, campus, fecha, horarios.inicio, horarios.termino);
}

/**
 * Realizar consulta AJAX para obtener salas disponibles
 */
function consultarSalasDisponibles(alumnosPorSala, campus, fecha, horaInicio, horaTermino) {
    console.log('🚀 EJECUTANDO consultarSalasDisponibles:', {
        alumnosPorSala: alumnosPorSala,
        campus: campus,
        fecha: fecha,
        horaInicio: horaInicio,
        horaTermino: horaTermino
    });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'consultar_salas_disponibles.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            console.log('📡 Respuesta recibida - Status:', xhr.status);
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    console.log('📋 Datos procesados:', data);
                    if (data.success) {
                        mostrarBadgeSalas(data.total_salas);
                        // Guardar datos para el modal
                        window.salasDisponiblesData = data;
                    } else {
                        console.error('❌ Error en consulta:', data.error);
                        ocultarBadgeSalas();
                    }
                } catch (error) {
                    console.error('❌ Error al procesar JSON:', error);
                    console.error('❌ Respuesta raw:', xhr.responseText);
                    ocultarBadgeSalas();
                }
            } else {
                console.error('❌ Error HTTP:', xhr.status, xhr.statusText);
                ocultarBadgeSalas();
            }
        }
    };
    
    var requestData = JSON.stringify({
        alumnos_por_sala: alumnosPorSala,
        campus: campus,
        fecha: fecha,
        hora_inicio: horaInicio,
        hora_termino: horaTermino
    });
    
    console.log('📤 Enviando request:', requestData);
    xhr.send(requestData);
}

/**
 * Mostrar el badge con el número de salas disponibles
 */
function mostrarBadgeSalas(numeroSalas) {
    var badge = document.getElementById('btnSalasDisponibles');
    var numero = document.getElementById('numeroSalasDisponibles');
    
    if (badge && numero) {
        numero.textContent = numeroSalas;
        badge.style.display = numeroSalas >= 0 ? 'block' : 'none';
        
        // Cambiar color según disponibilidad
        if (numeroSalas > 0) {
            badge.className = 'btn btn-outline-success';
        } else {
            badge.className = 'btn btn-outline-warning';
            numero.textContent = '0';
        }
            
        console.log('✅ Badge actualizado:', numeroSalas, 'salas disponibles');
    }
}

/**
 * Ocultar el badge de salas disponibles
 */
function ocultarBadgeSalas() {
    var badge = document.getElementById('btnSalasDisponibles');
    if (badge) {
        badge.style.display = 'none';
    }
}

/**
 * Mostrar el modal con la lista detallada de salas
 */
function mostrarSalasDisponibles() {
    if (!window.salasDisponiblesData || !window.salasDisponiblesData.salas) {
        console.warn('⚠️ No hay datos de salas disponibles');
        return;
    }
    
    var data = window.salasDisponiblesData;
    
    // Actualizar criterios de búsqueda
    actualizarCriteriosBusqueda(data.parametros);
    
    // Generar lista de salas
    generarListaSalas(data.salas);
    
    // Mostrar modal
    var modal = new bootstrap.Modal(document.getElementById('modalSalasDisponibles'));
    modal.show();
    
    console.log('📋 Modal de salas disponibles mostrado');
}

/**
 * Actualizar los criterios de búsqueda mostrados en el modal
 */
function actualizarCriteriosBusqueda(parametros) {
    var criterios = document.getElementById('criterios-busqueda');
    if (criterios) {
        criterios.innerHTML = 
            '<strong>Capacidad:</strong> ≥' + parametros.alumnos_por_sala + ' estudiantes | ' +
            '<strong>Campus:</strong> ' + parametros.campus + ' | ' +
            '<strong>Horario:</strong> ' + parametros.hora_inicio.substring(0,5) + '-' + parametros.hora_termino.substring(0,5);
    }
}

/**
 * Generar la lista HTML de salas disponibles
 */
function generarListaSalas(salas) {
    var container = document.getElementById('lista-salas-disponibles');
    
    if (!container) {
        console.error('❌ No se encontró el container de salas');
        return;
    }
    
    if (salas.length === 0) {
        container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No hay salas disponibles con estos criterios.</div>';
        return;
    }
    
    var html = '<div class="list-group list-group-flush">';
    
    for (var i = 0; i < salas.length; i++) {
        var sala = salas[i];
        html += '<div class="list-group-item d-flex justify-content-between align-items-center py-2">' +
                    '<div>' +
                        '<strong>' + sala.nombre + '</strong>' +
                        '<br><small class="text-muted">' + (sala.idSala) + '</small>' +
                    '</div>' +
                    '<span class="badge bg-primary rounded-pill">' + sala.capacidad + '</span>' +
                '</div>';
    }
    
    html += '</div>';
    html += '<div class="mt-2"><small class="text-muted"><strong>Total:</strong> ' + salas.length + ' salas disponibles</small></div>';
    
    container.innerHTML = html;
}

/**
 * Obtener la fecha de la actividad actual
 * AJUSTADO para salas2.php - obtiene la fecha de la fila seleccionada
 */
function obtenerFechaActividad() {
    // Obtener el ID de la actividad actual del modal
    var idPlanClase = document.getElementById('idplanclases').value;
    
    if (!idPlanClase) {
        console.warn('⚠️ No hay idplanclases en el modal');
        return null;
    }
    
    // Buscar la fila en la tabla con este ID
    var fila = document.querySelector('tr[data-id="' + idPlanClase + '"]');
    if (!fila) {
        console.warn('⚠️ No se encontró la fila para idplanclases:', idPlanClase);
        return null;
    }
    
    // La fecha está en la segunda celda (índice 1)
    var celdaFecha = fila.cells[1];
    if (!celdaFecha) {
        console.warn('⚠️ No se encontró la celda de fecha');
        return null;
    }
    
    var fechaTexto = celdaFecha.textContent.trim();
    console.log('📅 Fecha obtenida de la tabla:', fechaTexto);
    
    // Convertir DD/MM/YYYY a YYYY-MM-DD
    return parsearFechaParaConsulta(fechaTexto);
}

/**
 * Obtener los horarios de inicio y término de la actividad
 * AJUSTADO para salas2.php - obtiene los horarios de la fila seleccionada
 */
function obtenerHorariosActividad() {
    // Obtener el ID de la actividad actual del modal
    var idPlanClase = document.getElementById('idplanclases').value;
    
    if (!idPlanClase) {
        console.warn('⚠️ No hay idplanclases en el modal');
        return { inicio: null, termino: null };
    }
    
    // Buscar la fila en la tabla con este ID
    var fila = document.querySelector('tr[data-id="' + idPlanClase + '"]');
    if (!fila) {
        console.warn('⚠️ No se encontró la fila para idplanclases:', idPlanClase);
        return { inicio: null, termino: null };
    }
    
    // El horario está en la tercera celda (índice 2) con formato "HH:MM - HH:MM"
    var celdaHorario = fila.cells[2];
    if (!celdaHorario) {
        console.warn('⚠️ No se encontró la celda de horario');
        return { inicio: null, termino: null };
    }
    
    var horarioTexto = celdaHorario.textContent.trim();
    console.log('🕒 Horario obtenido de la tabla:', horarioTexto);
    
    // Parsear formato "15:00 - 16:30"
    var partes = horarioTexto.split(' - ');
    if (partes.length !== 2) {
        console.warn('⚠️ Formato de horario no válido:', horarioTexto);
        return { inicio: null, termino: null };
    }
    
    // Agregar segundos si no los tiene (HH:MM -> HH:MM:00)
    var inicio = partes[0].includes(':') && partes[0].split(':').length === 2 ? 
                 partes[0] + ':00' : partes[0];
    var termino = partes[1].includes(':') && partes[1].split(':').length === 2 ? 
                  partes[1] + ':00' : partes[1];
    
    return {
        inicio: inicio,
        termino: termino
    };
}

// ===========================================
// 9. FUNCIONES DE BLOQUES RELACIONADOS
// ===========================================

/**
 * Función principal para verificar bloques del mismo día
 * @param {number} idCurso - ID del curso
 * @param {string} fecha - Fecha en formato YYYY-MM-DD
 * @param {number} idPlanClaseActual - ID de la actividad actual
 */
function verificarBloquesMismoDia(idCurso, fecha, idPlanClaseActual) {
    console.log('🔍 Verificando bloques del mismo día:', {
        idCurso: idCurso,
        fecha: fecha,
        idPlanClaseActual: idPlanClaseActual
    });
    
    // Crear petición AJAX compatible con PHP 5.6
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'consultar_bloques_dia.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    console.log('📋 Respuesta del servidor:', data);
                    
                    if (data.success) {
                        procesarBloquesMismoDia(data.actividades, fecha, data.total_actividades);
                    } else {
                        console.error('❌ Error en consulta:', data.error);
                    }
                } catch (error) {
                    console.error('❌ Error al procesar respuesta JSON:', error);
                }
            } else {
                console.error('❌ Error en petición HTTP:', xhr.status, xhr.statusText);
            }
        }
    };
    
    // Preparar datos para enviar
    var requestData = JSON.stringify({
        idcurso: idCurso,
        fecha: fecha,
        idplanclase_actual: idPlanClaseActual
    });
    
    xhr.send(requestData);
}

/**
 * Procesar las actividades encontradas del mismo día
 * @param {Array} actividades - Array de actividades del mismo día
 * @param {string} fecha - Fecha consultada
 * @param {number} totalActividades - Total de actividades encontradas
 */
function procesarBloquesMismoDia(actividades, fecha, totalActividades) {
    console.log('🔄 Procesando bloques del mismo día:', {
        totalActividades: totalActividades,
        fecha: fecha
    });
    
    // Si no hay otras actividades, no mostrar alerta
    if (!actividades || actividades.length === 0) {
        console.log('✅ No hay otras actividades el mismo día');
        return;
    }
    
    // Analizar estados de las actividades
    var estadisticas = analizarEstadosActividades(actividades);
    
    console.log('📊 Estadísticas de actividades:', estadisticas);
    
    // Determinar si debe mostrar la alerta
    var debeAlertar = determinarSiAlertar(estadisticas, actividades.length);
    
    if (debeAlertar) {
        mostrarAlertaBloquesRelacionados(actividades, fecha);
    } else {
        console.log('ℹ️ No se requiere alerta para este escenario');
    }
}

/**
 * Analizar los estados de las actividades
 * @param {Array} actividades - Array de actividades
 * @returns {Object} Estadísticas de estados
 */
function analizarEstadosActividades(actividades) {
    var estadisticas = {
        asignadas: 0,
        solicitadas: 0,
        sinSolicitar: 0,
        liberadas: 0,
        total: actividades.length,
        hayMixEstados: false
    };
    
    // Contar estados
    for (var i = 0; i < actividades.length; i++) {
        var estado = actividades[i].estado_sala;
        
        switch (estado) {
            case 'Asignado':
                estadisticas.asignadas++;
                break;
            case 'Solicitado':
                estadisticas.solicitadas++;
                break;
            case 'Sin solicitar':
                estadisticas.sinSolicitar++;
                break;
            case 'Liberado':
                estadisticas.liberadas++;
                break;
        }
    }
    
    // Determinar si hay mix de estados (algunos asignados/solicitados y otros no)
    var tienenSala = estadisticas.asignadas + estadisticas.solicitadas;
    var noTienenSala = estadisticas.sinSolicitar;
    
    estadisticas.hayMixEstados = (tienenSala > 0 && noTienenSala > 0);
    
    return estadisticas;
}

/**
 * Determinar si debe mostrar la alerta
 * @param {Object} estadisticas - Estadísticas de estados
 * @param {number} totalActividades - Total de actividades
 * @returns {boolean} True si debe alertar
 */
function determinarSiAlertar(estadisticas, totalActividades) {
    // Criterios para alertar:
    // 1. Hay múltiples actividades el mismo día (2 o más)
    // 2. Hay mix de estados (algunas con sala, otras sin sala)
    
    var hayMultiplesActividades = totalActividades >= 1; // Contando la actividad actual
    var hayMixEstados = estadisticas.hayMixEstados;
    
    console.log('🎯 Criterios de alerta:', {
        hayMultiplesActividades: hayMultiplesActividades,
        hayMixEstados: hayMixEstados,
        totalActividades: totalActividades + 1, // +1 por la actividad actual
        estadisticas: estadisticas
    });
    
    return hayMultiplesActividades && (hayMixEstados || totalActividades >= 1);
}

/**
 * Mostrar la alerta visual de bloques relacionados
 * @param {Array} actividades - Array de actividades relacionadas
 * @param {string} fecha - Fecha de las actividades
 */
function mostrarAlertaBloquesRelacionados(actividades, fecha) {
    // Verificar si ya existe una alerta para evitar duplicados
    var alertaExistente = document.getElementById('alerta-bloques');
    if (alertaExistente) {
        alertaExistente.remove();
    }
    
    // Formatear la fecha para mostrar
    var fechaFormateada = formatearFechaParaMostrar(fecha);
    
    // Total de actividades (incluyendo la actual)
    var totalActividades = actividades.length + 1;
    
    // Crear HTML de la alerta
    var alertaHTML = crearHTMLAlerta(totalActividades, fechaFormateada, actividades);
    
    // Insertar la alerta al inicio del modal body
    var modalBody = document.querySelector('#salaModal .modal-body');
    if (modalBody) {
        modalBody.insertAdjacentHTML('afterbegin', alertaHTML);
        
        console.log('✅ Alerta de bloques relacionados mostrada');
        
        // Log para debugging
        logEstadoAlerta(actividades, fecha);
    } else {
        console.error('❌ No se encontró el modal body para insertar la alerta');
    }
}

/**
 * Crear el HTML de la alerta
 * @param {number} totalActividades - Total de actividades del día
 * @param {string} fechaFormateada - Fecha formateada para mostrar
 * @param {Array} actividades - Array de actividades relacionadas
 * @returns {string} HTML de la alerta
 */
function crearHTMLAlerta(totalActividades, fechaFormateada, actividades) {
    var alertaHTML = '<div class="alert alert-warning alert-dismissible fade show mb-3" role="alert" id="alerta-bloques">' +
        '<h6 class="alert-heading mb-2">' +
            '<i class="bi bi-exclamation-triangle me-2"></i>' +
            'Actividades relacionadas detectadas' +
        '</h6>' +
        '<p class="mb-2">' +
            'Este curso tiene <strong>' + totalActividades + ' actividades</strong> el mismo día (' + fechaFormateada + ').' +
        '</p>';
    
    // Agregar lista de actividades si hay más de 1
    if (actividades.length > 0) {
        alertaHTML += '<div class="mb-2">' +
            '<small class="text-muted">Otras actividades del día:</small>' +
            '<ul class="mb-2 mt-1">';
        
        for (var i = 0; i < actividades.length; i++) {
            var act = actividades[i];
            var icono = obtenerIconoEstado(act.estado_sala);
            var colorEstado = obtenerColorEstado(act.estado_sala);
            
            alertaHTML += '<li class="small">' +
                '<strong>' + act.bloque_numero + '</strong> ' +
                '(' + act.pcl_Inicio_formateado + '-' + act.pcl_Termino_formateado + ') - ' +
                act.pcl_TipoSesion + ' - ' +
                '<span class="' + colorEstado + '">' +
                    icono + ' ' + act.estado_sala +
                '</span>' +
                '</li>';
        }
        
        alertaHTML += '</ul></div>';
    }
    
    // Mensaje de recomendación
    alertaHTML += '<p class="mb-0">' +
        '<strong>💡 Recomendación:</strong> ' +
        '<span class="text-primary">No olvide solicitar sala para todas las actividades del día para asegurar cercanía en sus actividades.</span>' +
        '</p>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        '</div>';
    
    return alertaHTML;
}

/**
 * Obtener ícono según el estado de la sala
 * @param {string} estado - Estado de la sala
 * @returns {string} Ícono de Bootstrap
 */
function obtenerIconoEstado(estado) {
    switch (estado) {
        case 'Asignado':
            return '<i class="bi bi-check-circle"></i>';
        case 'Solicitado':
            return '<i class="bi bi-clock"></i>';
        case 'Sin solicitar':
            return '<i class="bi bi-x-circle"></i>';
        case 'Liberado':
            return '<i class="bi bi-arrow-counterclockwise"></i>';
        default:
            return '<i class="bi bi-question-circle"></i>';
    }
}

/**
 * Obtener color CSS según el estado
 * @param {string} estado - Estado de la sala
 * @returns {string} Clase CSS de color
 */
function obtenerColorEstado(estado) {
    switch (estado) {
        case 'Asignado':
            return 'text-success';
        case 'Solicitado':
            return 'text-info';
        case 'Sin solicitar':
            return 'text-danger';
        case 'Liberado':
            return 'text-secondary';
        default:
            return 'text-muted';
    }
}

/**
 * Formatear fecha para mostrar (YYYY-MM-DD -> DD/MM/YYYY)
 * @param {string} fecha - Fecha en formato YYYY-MM-DD
 * @returns {string} Fecha formateada
 */
function formatearFechaParaMostrar(fecha) {
    try {
        var partes = fecha.split('-');
        if (partes.length === 3) {
            return partes[2] + '/' + partes[1] + '/' + partes[0];
        }
        return fecha;
    } catch (error) {
        return fecha;
    }
}

/**
 * Función auxiliar para parsear fecha de la tabla (DD/MM/YYYY -> YYYY-MM-DD)
 * @param {string} fechaTexto - Fecha en formato DD/MM/YYYY
 * @returns {string} Fecha en formato YYYY-MM-DD
 */
function parsearFechaParaConsulta(fechaTexto) {
    try {
        // Limpiar espacios y caracteres extraños
        fechaTexto = fechaTexto.trim();
        
        // Intentar diferentes formatos de fecha
        var partes;
        
        // Formato DD/MM/YYYY
        if (fechaTexto.includes('/')) {
            partes = fechaTexto.split('/');
            if (partes.length === 3) {
                var dia = partes[0].padStart(2, '0');
                var mes = partes[1].padStart(2, '0');
                var anio = partes[2];
                return anio + '-' + mes + '-' + dia;
            }
        }
        
        // Formato DD-MM-YYYY
        if (fechaTexto.includes('-') && fechaTexto.length === 10) {
            partes = fechaTexto.split('-');
            if (partes.length === 3 && partes[0].length === 2) {
                var dia = partes[0].padStart(2, '0');
                var mes = partes[1].padStart(2, '0');
                var anio = partes[2];
                return anio + '-' + mes + '-' + dia;
            }
        }
        
        // Si ya está en formato YYYY-MM-DD, devolverlo tal como está
        if (fechaTexto.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return fechaTexto;
        }
        
        console.warn('⚠️ Formato de fecha no reconocido:', fechaTexto);
        return null;
        
    } catch (error) {
        console.error('❌ Error al parsear fecha:', error);
        return null;
    }
}

/**
 * Log para debugging
 * @param {Array} actividades - Actividades encontradas
 * @param {string} fecha - Fecha consultada
 */
function logEstadoAlerta(actividades, fecha) {
    console.log('📝 Alerta mostrada:', {
        fecha: fecha,
        totalActividades: actividades.length + 1,
        actividadesEncontradas: actividades.map(function(act) {
            return {
                id: act.idplanclases,
                bloque: act.bloque_numero,
                tipo: act.pcl_TipoSesion,
                estado: act.estado_sala,
                horario: act.pcl_Inicio_formateado + '-' + act.pcl_Termino_formateado
            };
        }),
        timestamp: new Date().toISOString()
    });
}

// ===========================================
// 10. FUNCIONES GLOBALES DE DOCENTES
// ===========================================

// Función global para eliminar docentes
// Función global para eliminar docentes con modal de confirmación
window.eliminarDocente = function(id) {
    if(!id) return;
    
    // Crear el modal de confirmación
    const modalHTML = `
        <div class="modal fade" id="confirmarEliminarModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle text-danger"></i> 
                            Confirmar eliminación
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Está seguro que desea eliminar este docente del curso?</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i>
                            <strong>Importante:</strong> Esta acción también eliminará al docente de todas las actividades asignadas en este curso.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger" id="confirmarEliminar">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Si ya existe el modal, eliminarlo
    $('#confirmarEliminarModal').remove();
    
    // Agregar el modal al body
    $('body').append(modalHTML);
    
    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('confirmarEliminarModal'));
    modal.show();
    
    // Handler para el botón de confirmar
    $('#confirmarEliminar').off('click').on('click', function() {
        // Cerrar el modal
        modal.hide();
        
        // Proceder con la eliminación
        $.ajax({
            url: 'eliminar_docente.php',
            type: 'POST',
            data: { idProfesoresCurso: id },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    // Eliminar la fila de la tabla
                    var $btn = $(`button[onclick="eliminarDocente(${id})"]`);
                    var $row = $btn.closest('tr');
                    
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });

                    // Crear y mostrar toast de éxito
                    const toastHTML = `
                        <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="bi bi-check-circle me-2"></i>
                                    ${response.message || 'Docente removido exitosamente'}
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    `;
                    
                    // Asegurar que existe el contenedor de toasts
                    if ($('.toast-container').length === 0) {
                        $('body').append('<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
                    }
                    
                    $('.toast-container').append(toastHTML);
                    const toastElement = new bootstrap.Toast($('.toast').last(), {
                        autohide: true,
                        delay: 3000
                    });
                    toastElement.show();
                } else {
                    mostrarToast(response.message || 'Error al eliminar docente', 'danger');
                }
            },
            error: function() {
                mostrarToast('Error de comunicación con el servidor', 'danger');
            }
        });
    });
    
    // Limpiar el modal cuando se cierre
    $('#confirmarEliminarModal').on('hidden.bs.modal', function () {
        $(this).remove();
    });
};

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

// ===========================================
// 11. ASIGNADOR MASIVO DE DOCENTES
// ===========================================

// Funciones del asignador masivo
function inicializarAsignadorMasivo() {
    // Inicializar interfaz
    $('#btnVisualizar').off('click').on('click', buscarActividades);
    
    $('#seleccionarTodos').off('change').on('change', function() {
        $('.docente-check').prop('checked', $(this).is(':checked'));
        reordenarDocentesMasivo(); // AGREGAR ESTA LÍNEA
        verificarSelecciones();
    });
    
    // Botones de asignación y eliminación
    $('#btnAsignarDocentes').off('click').on('click', function() {
        gestionarDocentes('asignar');
    });
    
    $('#btnEliminarDocentes').off('click').on('click', function() {
        gestionarDocentes('eliminar');
    });
    
    // Verificar cambios en los checkboxes de docentes
    $(document).off('change', '.docente-check').on('change', '.docente-check', function() {
        const todasSeleccionadas = $('.docente-check:checked').length === $('.docente-check').length;
        $('#seleccionarTodos').prop('checked', todasSeleccionadas);
        reordenarDocentesMasivo(); // AGREGAR ESTA LÍNEA
        verificarSelecciones();
    });
    
    $('#btnLimpiarFiltros').off('click').on('click', function() {
        // Limpiar campos de filtro
        $('#tipoActividad').val('');
        $('#diaSemana').val('');
        $('#subtipo').val('');
        $('#fechaInicio').val('');
        $('#fechaTermino').val('');
        $('#horaInicio').val('');
        $('#horaTermino').val('');
        
        // Ocultar mensaje de sin resultados
        $('#sinResultados').addClass('d-none');
        
        // Limpiar tabla de actividades
        $('#tablaActividades tbody').empty();
        
        // Deshabilitar botones
        $('#btnAsignarDocentes').prop('disabled', true);
        $('#btnEliminarDocentes').prop('disabled', true);
        
        // Reiniciar lista de actividades seleccionadas
        actividadesSeleccionadas = [];
        
        // Desmarcar todos los profesores
        $('.docente-check').prop('checked', false);
        $('#seleccionarTodos').prop('checked', false);
        
        // Reordenar después de limpiar
        reordenarDocentesMasivo(); // AGREGAR ESTA LÍNEA
        
        verificarFiltros();
    });
    
    // Agregar evento para validar filtros en tiempo real
    $('#tipoActividad, #diaSemana, #fechaInicio, #fechaTermino, #horaInicio, #horaTermino').on('change', function() {
        verificarFiltros();
    });
    
    // Verificar filtros al inicio
    verificarFiltros();
}

function verificarFiltros() {
    const tipoActividad = $('#tipoActividad').val();
    const diaSemana = $('#diaSemana').val();
    const fechaInicio = $('#fechaInicio').val();
    const fechaTermino = $('#fechaTermino').val();
    const horaInicio = $('#horaInicio').val();
    const horaTermino = $('#horaTermino').val();
    
    // Habilitar/deshabilitar el botón según si hay algún filtro
    const hayFiltro = tipoActividad || diaSemana || fechaInicio || fechaTermino || horaInicio || horaTermino;
    $('#btnVisualizar').prop('disabled', !hayFiltro);
}

function buscarActividades() {
    // Obtener valores de los filtros
    const tipoActividad = $('#tipoActividad').val();
    const diaSemana = $('#diaSemana').val();
    const fechaInicio = $('#fechaInicio').val();
    const fechaTermino = $('#fechaTermino').val();
    const horaInicio = $('#horaInicio').val();
    const horaTermino = $('#horaTermino').val();
    
    // Validar que al menos un filtro esté presente
    if (!tipoActividad && !diaSemana && !fechaInicio && !fechaTermino && !horaInicio && !horaTermino) {
        mostrarNotificacionMasiva('Debe seleccionar al menos un filtro para buscar actividades', 'warning');
        return;
    }
	
	   mostrarNotificacionMasiva('Buscando actividades...', 'info', 1000);
    
    // Obtener el ID del curso actual
    const urlParams = new URLSearchParams(window.location.search);
    const idCurso = urlParams.get('curso');
    
    // Crear objeto con los filtros
    const filtros = {
        idcurso: idCurso,
        tipoActividad: tipoActividad,
        diaSemana: diaSemana,
        subtipo: '', // Ya no usas subtipo en los filtros actuales
        fechaInicio: fechaInicio,
        fechaTermino: fechaTermino,
        horaInicio: horaInicio,
        horaTermino: horaTermino
    };
    
    // Realizar solicitud AJAX
       $.ajax({
        url: 'buscar_actividades.php',
        type: 'POST',
        dataType: 'json',
        data: filtros,
        success: function(response) {
            if (response.success) {
                mostrarActividades(response.actividades);
                actividadesSeleccionadas = response.actividades.map(act => act.idplanclases);
                
                // Habilitar/deshabilitar botones según resultados
                const hayActividades = response.actividades.length > 0;
                $('#btnAsignarDocentes').prop('disabled', !hayActividades);
                $('#btnEliminarDocentes').prop('disabled', !hayActividades);
                
                if (!hayActividades) {
                    $('#sinResultados').removeClass('d-none');
                } else {
                    $('#sinResultados').addClass('d-none');
                    
                    // IMPORTANTE: Siempre desmarcar todos los docentes al buscar
                    $('.docente-check').prop('checked', false);
                    $('#seleccionarTodos').prop('checked', false);
                    reordenarDocentesMasivo();
                    verificarSelecciones();
                }
            } else {
                mostrarNotificacionMasiva(response.message || 'Error al buscar actividades', 'danger');
            }
        },
        error: function() {
            mostrarNotificacionMasiva('Error de comunicación con el servidor', 'danger');
        }
    });
}

function mostrarActividades(actividades) {
    const tbody = $('#tablaActividades tbody');
    tbody.empty();
    
    if (actividades.length === 0) {
        return;
    }
    
    actividades.forEach(act => {
        // Formatear fecha
        const fecha = new Date(act.pcl_Fecha);
        const fechaFormateada = fecha.toLocaleDateString('es-ES');
        
        // Formatear horas
        const horaInicio = act.pcl_Inicio ? act.pcl_Inicio.substring(0, 5) : '';
        const horaTermino = act.pcl_Termino ? act.pcl_Termino.substring(0, 5) : '';
        
		// Día de la semana en español
    const diaSemana = fecha.toLocaleDateString('es-ES', { weekday: 'long' });
	
        // Crear fila
        const fila = `
            <tr data-id="${act.idplanclases}">
                <td>${fechaFormateada}</td>
				<td>${diaSemana.charAt(0).toUpperCase() + diaSemana.slice(1)}</td>
                <td>${horaInicio}</td>
                <td>${horaTermino}</td>
                <td>${act.pcl_tituloActividad || ''}</td>
                <td>${act.pcl_TipoSesion}${act.pcl_SubTipoSesion ? ' (' + act.pcl_SubTipoSesion + ')' : ''}</td>
            </tr>
        `;
        
        tbody.append(fila);
    });
}

function verificarSelecciones() {
    const docentesSeleccionados = $('.docente-check:checked').length > 0;
    const hayActividades = actividadesSeleccionadas.length > 0;
    
    $('#btnAsignarDocentes, #btnEliminarDocentes').prop('disabled', !hayActividades || !docentesSeleccionados);
}

function gestionarDocentes(accion) {
    // Verificar que haya actividades seleccionadas
    if (actividadesSeleccionadas.length === 0) {
        mostrarNotificacionMasiva('No hay actividades seleccionadas', 'warning');
        return;
    }
    
    // Obtener docentes seleccionados
    const docentesSeleccionados = [];
    $('.docente-check:checked').each(function() {
        docentesSeleccionados.push({
            rut: $(this).data('rut'),
            nombre: $(this).closest('.docente-row').find('p.mb-0').text(),
            cargo: $(this).closest('.docente-row').find('small.text-muted').text()
        });
    });
    
    if (docentesSeleccionados.length === 0) {
        mostrarNotificacionMasiva('No hay docentes seleccionados', 'warning');
        return;
    }
    
    // Llenar el modal con la información
    $('#accionTitulo').text(accion === 'asignar' ? 'Asignación' : 'Eliminación');
    $('#accionDescripcion').text(
        accion === 'asignar' ? 
        'Se asignarán los docentes seleccionados a todas las actividades listadas' : 
        'Se eliminarán los docentes seleccionados de todas las actividades listadas'
    );
    $('#numActividades').text(actividadesSeleccionadas.length);
    $('#numDocentes').text(docentesSeleccionados.length);
    
     const actividadesPreview = $('#actividadesPreview');
    actividadesPreview.empty();
    
    // Primero, encontrar las filas de las actividades seleccionadas
    $('#tablaActividades tbody tr').each(function() {
        const row = $(this);
        const idActividad = parseInt(row.data('id'));
        
        // Verificar si esta actividad está seleccionada
        if (actividadesSeleccionadas.includes(idActividad)) {
            const fecha = row.find('td:eq(0)').text();
			const dia = row.find('td:eq(1)').text();
            const horaInicio = row.find('td:eq(2)').text();
            const horaTermino = row.find('td:eq(3)').text();
            const titulo = row.find('td:eq(4)').text();
            
            
            // Crear la fila para el preview
            const previewRow = `
                <tr>
                    <td>${fecha}</td>
                    <td>${dia}</td>
                    <td>${horaInicio} a las ${horaTermino}</td>
                    <td>${titulo || 'Sin título'}</td>
                </tr>
            `;
            actividadesPreview.append(previewRow);
        }
    });
    
    // Si no encuentra actividades de esta forma, intentar otra aproximación
    if (actividadesPreview.find('tr').length === 0) {
        // Mensaje de debug para ver qué está pasando
        console.log('Actividades seleccionadas:', actividadesSeleccionadas);
        console.log('Filas encontradas:', $('#tablaActividades tbody tr').length);
        
        // Agregar una fila de aviso
        actividadesPreview.append(`
            <tr>
                <td colspan="4" class="text-center text-muted">
                    Error al cargar las actividades. IDs: ${actividadesSeleccionadas.join(', ')}
                </td>
            </tr>
        `);
    }
    
    // Llenar tabla de docentes
    const docentesPreview = $('#docentesPreview');
    docentesPreview.empty();
    
    docentesSeleccionados.forEach(docente => {
        const row = `
            <tr>
                <td>${docente.nombre}</td>
                <td>${docente.cargo}</td>
            </tr>
        `;
        docentesPreview.append(row);
    });
    
    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('previsualizacionModal'));
    modal.show();
    
    // Configurar el botón de confirmar
    $('#confirmarAccion').off('click').on('click', function() {
        modal.hide();
        procesarAsignacion(accion, docentesSeleccionados.map(d => d.rut));
    });
}

// En la función procesarAsignacion, asegúrate de que esté así:
function procesarAsignacion(accion, docentesRuts) {
    // Obtener el ID del curso actual
    const urlParams = new URLSearchParams(window.location.search);
    const idCurso = urlParams.get('curso');
    
    // Preparar datos para enviar
    const datos = {
        idcurso: idCurso,
        actividades: actividadesSeleccionadas,
        docentes: docentesRuts,
        accion: accion
    };
    
    // Debug
    console.log('Datos a enviar:', datos);
    
    // Mostrar indicador de carga y guardar la referencia
    let toastCarga = mostrarNotificacionMasiva('Procesando... Por favor espere.', 'info');
    
    // Deshabilitar botones para evitar múltiples clicks
    $('#confirmarAccion').prop('disabled', true);
    $('#btnAsignarDocentes').prop('disabled', true);
    $('#btnEliminarDocentes').prop('disabled', true);
    
    // Realizar solicitud AJAX
    $.ajax({
        url: 'procesar_asignacion_masiva.php',
        type: 'POST',
        dataType: 'json',
        data: JSON.stringify(datos),
        contentType: 'application/json',
        success: function(response) {
            console.log('Respuesta:', response);
            
            // Cerrar el toast de carga de forma segura
            if (toastCarga) {
                // Forzar el cierre del toast
                $(toastCarga).remove();
            }
            
            // Cerrar el modal de previsualización
            const modal = bootstrap.Modal.getInstance(document.getElementById('previsualizacionModal'));
            if (modal) modal.hide();
            
            // Mostrar resultado
            if (response.success) {
                mostrarNotificacionMasiva(
                    `${accion === 'asignar' ? 'Asignación' : 'Desvinculación'} completada correctamente. 
                    ${response.operaciones || 0} operaciones realizadas.`, 
                    'success'
                );
                
                // Actualizar la vista después de un breve retraso
                setTimeout(() => {
                    $('#btnVisualizar').click();
                }, 500);
            } else {
                mostrarNotificacionMasiva(response.message || 'Error al procesar la solicitud', 'danger');
            }
        },
        error: function(xhr, status, error) {
            // Cerrar el toast de carga
            if (toastCarga) {
                $(toastCarga).remove();
            }
            
            console.error("Error AJAX:", xhr.responseText);
            mostrarNotificacionMasiva('Error de comunicación con el servidor: ' + (error || status), 'danger');
        },
        complete: function() {
            // Asegurarse de que el toast de carga se cierre
            if (toastCarga) {
                $(toastCarga).remove();
            }
            
            // Rehabilitar los botones
            $('#confirmarAccion').prop('disabled', false);
            $('#btnAsignarDocentes').prop('disabled', false);
            $('#btnEliminarDocentes').prop('disabled', false);
        }
    });
}

function mostrarNotificacionMasiva(mensaje, tipo = 'success', duracion = 3000) {
    // Buscar o crear el contenedor específico para el asignador masivo
    let toastContainer = document.querySelector('.toast-container-masivo');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container-masivo position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1055';
        document.body.appendChild(toastContainer);
    }
    
    // Determinar si es un mensaje de carga
    const esCarga = tipo === 'info' && mensaje.toLowerCase().includes('procesando');
    
    // Determinar el ícono según el tipo
    let iconHtml = '';
    if (esCarga) {
        iconHtml = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Cargando...</span></div>';
    } else {
        const iconos = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        iconHtml = `<i class="bi bi-${iconos[tipo] || 'info-circle'} me-2"></i>`;
    }
    
    // Crear el toast
    const toastId = 'toast-masivo-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-tipo="${tipo}">
            <div class="d-flex">
                <div class="toast-body">
                    ${iconHtml}
                    ${mensaje}
                </div>
                ${!esCarga ? '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' : ''}
            </div>
        </div>
    `;
    
    // Añadir al contenedor
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    // Mostrar el toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: !esCarga,  // No auto-ocultar si es carga
        delay: duracion
    });
    toast.show();
    
    // Si NO es un toast de carga, eliminarlo después de ocultar
    if (!esCarga) {
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
    
    return toastElement;
}

function reordenarDocentesMasivo() {
    const container = document.getElementById('listaDocentes');
    if (!container) return;
    
    const docenteRows = Array.from(container.querySelectorAll('.docente-row'));
    
    // Separar en dos grupos
    const selected = [];
    const notSelected = [];
    
    docenteRows.forEach(row => {
        const checkbox = row.querySelector('.docente-check');
        if (checkbox && checkbox.checked) {
            selected.push(row);
        } else {
            notSelected.push(row);
        }
    });
    
    // Mantener el orden alfabético en los no seleccionados
    notSelected.sort((a, b) => {
        const nameA = a.querySelector('p.mb-0').textContent.toLowerCase();
        const nameB = b.querySelector('p.mb-0').textContent.toLowerCase();
        return nameA.localeCompare(nameB);
    });
    
    // Reconstruir el contenedor
    container.innerHTML = '';
    
    // Agregar primero los seleccionados, luego los no seleccionados
    selected.forEach(row => container.appendChild(row));
    notSelected.forEach(row => container.appendChild(row));
}

// ===========================================
// 12. FUNCIONES PARA CREAR DOCENTE
// ===========================================

function inicializarCrearDocente() {
	
	$('#nuevo-docente-btn').off('click').on('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('nuevoDocenteModal'));
    modal.show();
});
	
    // Event listener para el botón guardar
    $('#btnGuardarDocente').off('click').on('click', guardar_docente);
    
    // Event listener para validar RUT mientras se escribe
    $('#rut_docente').off('input').on('input', function() {
        checkRut(this);
    });
    
    // Event listener para habilitar/deshabilitar unidad externa
    $('#unidad_academica').off('change').on('change', function() {
        habilitar_unidad(this);
    });
}

function checkRut(rut) {
    // Despejar Puntos
    var valor = rut.value.replace('.','');
    // Despejar Guión
    valor = valor.replace('-','');
    
    // Aislar Cuerpo y Digito Verificador
    cuerpo = valor.slice(0,-1);
    dv = valor.slice(-1).toUpperCase();
    
    // Formatear RUN
    rut.value = cuerpo + '-'+ dv
    
    // Si no cumple con el minimo ej. (n.nnn.nnn)
    if(cuerpo.length < 7) { 
        rut.setCustomValidity("RUT Incompleto"); 
        $('#flag').val('false'); 
        return false;
    }
    
    // Calcular Digito Verificador
    suma = 0;
    multiplo = 2;
    
    // Para cada digito del Cuerpo
    for(i=1;i<=cuerpo.length;i++) {
        // Obtener su Producto con el Múltiplo Correspondiente
        index = multiplo * valor.charAt(cuerpo.length - i);
        
        // Sumar al Contador General
        suma = suma + index;
        
        // Consolidar Múltiplo dentro del rango [2,7]
        if(multiplo < 7) { multiplo = multiplo + 1; } else { multiplo = 2; }
    }
    
    // Calcular Digito Verificador en base al Módulo 11
    dvEsperado = 11 - (suma % 11);
    
    // Casos Especiales (0 y K)
    dv = (dv == 'K')?10:dv;
    dv = (dv == 0)?11:dv;
    
    // Validar que el Cuerpo coincide con su Digito Verificador
    if(dvEsperado != dv) { 
        rut.setCustomValidity("RUT Inválido"); 
        $('#flag').val('false'); 
        return false;
    }
    
    // Validar RUTs repetidos o inválidos
    if(cuerpo == '0000000000' || cuerpo == '00000000' ||
       cuerpo == '11111111' || cuerpo == '1111111' ||
       cuerpo == '22222222' || cuerpo == '2222222' ||
       cuerpo == '33333333' || cuerpo == '3333333' ||
       cuerpo == '44444444' || cuerpo == '4444444' ||
       cuerpo == '55555555' || cuerpo == '5555555' ||
       cuerpo == '66666666' || cuerpo == '6666666' ||
       cuerpo == '77777777' || cuerpo == '7777777' ||
       cuerpo == '88888888' || cuerpo == '8888888' ||
       cuerpo == '99999999' || cuerpo == '9999999') {
        rut.setCustomValidity("RUT Inválido"); 
        $('#flag').val('false'); 
        return false;
    }
    
    // Si todo sale bien, eliminar errores (decretar que es válido)
    rut.setCustomValidity('');
    $('#flag').val('true');
}

function habilitar_unidad(sel) {
    var depto = sel.value;
    
    if(depto == 'Unidad Externa') {
        document.getElementById("unidad_externa").disabled = false;
        document.getElementById("unidad_externa").required = true;
        document.getElementById("unidad_externa").placeholder = 'Unidad Externa *';
    } else {
        document.getElementById("unidad_externa").disabled = true;
        document.getElementById("unidad_externa").required = false;
        document.getElementById("unidad_externa").placeholder = 'Unidad Externa';
    }
}

function guardar_docente() {
    var curso = $("#curso").val(); 
    var rut = $("#rut_docente").val(); 
    var flag = $("#flag").val();
    var unidad = $("#unidad_academica").val(); 
    
    var largo_rut = rut.length;
    
    if($("#unidad_externa").val() != '') {
        var uE = $("#unidad_externa").val();
    } else {
        var uE = "Sin Unidad"; 
    }
    
    var nombres = $("#nombres").val(); 
    var paterno = $("#paterno").val(); 
    var materno = $("#materno").val(); 
    var email = $("#email").val(); 
    var funcion = $("#funcion").val();
    
    if(flag == 'true') {
        if(rut != '' && largo_rut >= 9 && unidad != '' && uE != '' && nombres != '' && paterno != '' && email != '' && funcion != '') {
            // Mostrar loading en el botón
            const $btnGuardar = $('#btnGuardarDocente');
            const textoOriginal = $btnGuardar.html();
            $btnGuardar.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Guardando...');
            
            $.ajax({
                dataType: "json",
                data: {
                    "curso": curso,
                    "rut_docente": rut,
                    "unidad_academica": unidad,
                    "unidad_externa": uE,
                    "nombres": nombres,
                    "paterno": paterno,
                    "materno": materno,
                    "email": email,
                    "funcion": funcion
                },
                url: 'guardar_docente_nuevo.php', 
                type: 'POST',
                // ✅ REEMPLAZA tu función success con esta versión corregida:

					success: function(respuesta) {
						// Restaurar botón
						$btnGuardar.prop('disabled', false).html(textoOriginal);
						
						if(respuesta.success) {
							// ✅ PRIMERO: Limpiar el formulario ANTES de cerrar el modal
							const form = document.getElementById('nuevoDocenteForm');
							if (form) {
								form.reset();
							}
							const unidadExterna = document.getElementById('unidad_externa');
							if (unidadExterna) {
								unidadExterna.disabled = true;
							}
							
							// SEGUNDO: Cerrar el modal de nuevo docente
							const modalElement = document.getElementById('nuevoDocenteModal');
							const modal = bootstrap.Modal.getInstance(modalElement);
							if (modal) modal.hide();
							
							// TERCERO: Mostrar notificación de éxito
							mostrarToast('Docente agregado correctamente', 'success');
							
							// CUARTO: Recargar la pestaña de docentes
							$('#docente-tab').click();
							
						} else {
							// Mostrar error
							mostrarToast(respuesta.message || 'Error al agregar docente', 'danger');
						}
					},
                error: function(xhr, status, error) {
                    // Restaurar botón
                    $btnGuardar.prop('disabled', false).html(textoOriginal);
                    
                    console.error('Error:', xhr.responseText);
                    mostrarToast('Error de comunicación con el servidor', 'danger');
                }
            });
        } else {
            mostrarToast('Por favor complete todos los campos obligatorios', 'warning');
        }
    } else {
        mostrarToast('El formato del RUT no es válido', 'warning');
    }
}

// ===========================================
// 13. INICIALIZACIÓN DEL DOCUMENTO
// ===========================================

document.addEventListener('DOMContentLoaded', () => {
    generateFullCalendar();
    loadActivityTypes();
    
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
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
	
	 // Nuevo: Limpiar alertas al cerrar modal de salas
    var salaModal = document.getElementById('salaModal');
    if (salaModal) {
        salaModal.addEventListener('hidden.bs.modal', function() {
            limpiarAlertasBloques();
        });
    }
	
	 setTimeout(function() {
        verificarDependencias();
    }, 500);
	
	 var alumnosPorSala = document.getElementById('alumnosPorSala');
    if (alumnosPorSala) {
        alumnosPorSala.addEventListener('input', function() {
            // Delay para evitar múltiples consultas
            clearTimeout(window.timeoutSalas);
            window.timeoutSalas = setTimeout(actualizarSalasDisponibles, 500);
        });
    }
    
    // Actualizar salas cuando cambie el campus
    var campus = document.getElementById('campus');
    if (campus) {
        campus.addEventListener('change', actualizarSalasDisponibles);
    }
    
    // Actualizar salas cuando se cargue el modal (si es necesario)
    var modalSalas = document.getElementById('salaModal');
    if (modalSalas) {
        modalSalas.addEventListener('shown.bs.modal', function() {
            // Delay para asegurar que todos los campos estén cargados
            setTimeout(actualizarSalasDisponibles, 500);
        });
    }
	
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

document.getElementById('docente-masivo-tab').addEventListener('click', function() {
    const docentesMasivoList = document.getElementById('docentes-masivo-list');
    
    // Mostrar spinner de carga
    docentesMasivoList.innerHTML = '<div class="text-center p-5"><i class="bi bi-arrow-repeat spinner"></i><p>Cargando...</p></div>';
    
    fetch('asignacion_masiva_docentes.php?curso=' + cursoId)
        .then(response => response.text())
        .then(html => {
            docentesMasivoList.innerHTML = html;
            // IMPORTANTE: Inicializar los event listeners después de cargar el contenido
            inicializarAsignadorMasivo();
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
    
    if (hoursInput) hoursInput.addEventListener('input', validateInputs);
    if (minutesInput) minutesInput.addEventListener('input', validateInputs);
	
	$('#autoaprendizajeModal').on('shown.bs.modal', function() {
        const saveButton = document.getElementById('save-auto-btn');
        const titleField = document.getElementById('auto-activity-title');
        
        // Verificar si el título está vacío
        saveButton.disabled = titleField.value.trim() === '';
        
        // Foco en el campo del título
        titleField.focus();
    });
	
});

// Asegurar que las variables de timeout estén disponibles globalmente
if (typeof calcularAlumnosTimeout === 'undefined') {
    window.calcularAlumnosTimeout = null;
}

console.log('✅ Sistema completo inicializado correctamente');

let idActividadActual = null;

function mostrarDetallesInconsistencia(idplanclases) {
    idActividadActual = idplanclases;
    
    console.log("🔍 Iniciando análisis de inconsistencias para ID:", idplanclases);
    
    // Usar Bootstrap JavaScript API en lugar de jQuery
    const modal = new bootstrap.Modal(document.getElementById('modalDetallesInconsistencia'));
    modal.show();
    
    // Limpiar contenido anterior
    document.getElementById('info-actividad').innerHTML = '';
    document.getElementById('contenido-detalles-inconsistencia').innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando detalles...</span>
            </div>
            <p class="mt-2 text-muted">Analizando inconsistencias...</p>
        </div>
    `;
    
    // Preparar datos para envío
    const requestData = {
        action: 'obtener_detalles_inconsistencia',
        idplanclases: idplanclases
    };
    
    console.log("📤 Enviando datos:", requestData);
    
    // Hacer petición AJAX con fetch API (JavaScript nativo)
    fetch('salas2.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log("📡 Respuesta recibida, status:", response.status);
        console.log("📡 Headers:", response.headers);
        
        // Verificar si la respuesta es exitosa
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Verificar el content-type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // Si no es JSON, obtener el texto para debugging
            return response.text().then(text => {
                console.error("❌ Respuesta no es JSON. Content-Type:", contentType);
                console.error("❌ Contenido recibido:", text.substring(0, 500));
                throw new Error(`Respuesta no es JSON. Content-Type: ${contentType}. Contenido: ${text.substring(0, 100)}...`);
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log("✅ Datos JSON parseados:", data);
        
        if (data.success) {
            mostrarDetallesCompletos(data);
        } else {
            mostrarErrorDetalles(data.error || 'Error desconocido del servidor', data.debug_info);
        }
    })
    .catch(error => {
        console.error('❌ Error completo:', error);
        console.error('❌ Stack trace:', error.stack);
        
        let mensajeError = 'Error de comunicación: ' + error.message;
        
        // Detectar errores comunes
        if (error.message.includes('Unexpected token')) {
            mensajeError += '\n\n🔍 Posibles causas:\n';
            mensajeError += '• Error fatal en PHP\n';
            mensajeError += '• Problemas de sintaxis en el servidor\n';
            mensajeError += '• Headers incorrectos\n';
            mensajeError += '\n📋 Revisa los logs del servidor para más detalles.';
        }
        
        mostrarErrorDetalles(mensajeError);
    });
}

function mostrarDetallesCompletos(data) {
    console.log("✅ Mostrando detalles completos:", data);
    
    const actividad = data.actividad;
    const salas = data.salas;
    
    // Mostrar información de la actividad
    document.getElementById('info-actividad').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <strong>Actividad:</strong> ${actividad.pcl_tituloActividad}<br>
                <strong>Código-Sección:</strong> ${actividad.pcl_AsiCodigo}-${actividad.pcl_Seccion}
            </div>
            <div class="col-md-6">
                <strong>Fecha:</strong> ${new Date(actividad.pcl_Fecha).toLocaleDateString('es-CL')}<br>
                <strong>Horario:</strong> ${actividad.pcl_Inicio.substring(0,5)} - ${actividad.pcl_Termino.substring(0,5)}
            </div>
        </div>
    `;
    
    // Mostrar detalles de cada sala
    let htmlSalas = `
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 10%">Sala</th>
                        <th style="width: 15%">Estado Asignación</th>
                        <th style="width: 20%">Resultado Verificación</th>
                        <th style="width: 25%">Detalles de Reserva</th>
                        <th style="width: 30%">Comentarios/Observaciones</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    salas.forEach(sala => {
        const verificacion = sala.verificacion;
        let badgeVerificacion = '';
        let detallesReserva = '';
        let estadoClass = '';
        
        if (verificacion.encontrado) {
            if (verificacion.metodo === 'paso1') {
                badgeVerificacion = '<span class="badge bg-success">🎯 Encontrada (ID Repetición)</span>';
                estadoClass = 'table-success';
            } else {
                badgeVerificacion = '<span class="badge bg-warning">🔍 Encontrada (Búsqueda Alternativa)</span>';
                estadoClass = 'table-warning';
            }
            
            if (sala.info_reserva) {
                const reserva = sala.info_reserva;
                detallesReserva = `
                    <small>
                        <strong>Fecha:</strong> ${reserva.re_FechaReserva}<br>
                        <strong>Horario:</strong> ${reserva.re_HoraReserva} - ${reserva.re_HoraTermino}<br>
                        <strong>Curso:</strong> ${reserva.re_labelCurso}<br>
                        <strong>Creada:</strong> ${new Date(reserva.re_RegFecha).toLocaleString('es-CL')}
                    </small>
                `;
            }
        } else {
            badgeVerificacion = '<span class="badge bg-danger">❌ NO ENCONTRADA</span>';
            estadoClass = 'table-danger';
            detallesReserva = '<span class="text-danger"><strong>Sin reserva confirmada</strong></span>';
        }
        
        htmlSalas += `
            <tr class="${estadoClass}">
                <td class="text-center">
                    <strong>${sala.idSala}</strong>
                </td>
                <td>
                    <span class="badge bg-info">Estado 3</span><br>
                    <small>Asignada</small>
                </td>
                <td>
                    ${badgeVerificacion}<br>
                    <small class="text-muted">${verificacion.detalle}</small>
                </td>
                <td>
                    ${detallesReserva}
                </td>
                <td>
                    ${sala.comentario_asignacion ? `
                        <strong>Asignación:</strong><br>
                        <small>${sala.comentario_asignacion.substring(0, 100)}${sala.comentario_asignacion.length > 100 ? '...' : ''}</small><br>
                    ` : ''}
                    <small class="text-muted">
                        <strong>Usuario:</strong> ${sala.usuario_asignacion || 'N/A'}<br>
                        <strong>Fecha:</strong> ${sala.fecha_asignacion ? new Date(sala.fecha_asignacion).toLocaleString('es-CL') : 'N/A'}
                    </small>
                </td>
            </tr>
        `;
    });
    
    htmlSalas += `
                </tbody>
            </table>
        </div>
    `;
    
    // Agregar resumen
    const totalSalas = salas.length;
    const salasEncontradas = salas.filter(s => s.verificacion.encontrado).length;
    const salasInconsistentes = totalSalas - salasEncontradas;
    
    htmlSalas += `
        <div class="alert alert-info mt-3">
            <h6><i class="bi bi-bar-chart"></i> Resumen</h6>
            <div class="row text-center">
                <div class="col-md-3">
                    <span class="badge bg-secondary fs-6">${totalSalas}</span><br>
                    <small>Total Salas</small>
                </div>
                <div class="col-md-3">
                    <span class="badge bg-success fs-6">${salasEncontradas}</span><br>
                    <small>Con Reserva</small>
                </div>
                <div class="col-md-3">
                    <span class="badge bg-danger fs-6">${salasInconsistentes}</span><br>
                    <small>Inconsistentes</small>
                </div>
                <div class="col-md-3">
                    <span class="badge bg-warning fs-6">${Math.round((salasInconsistentes/totalSalas)*100)}%</span><br>
                    <small>% Problemas</small>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('contenido-detalles-inconsistencia').innerHTML = htmlSalas;
}

function mostrarErrorDetalles(error, debugInfo = null) {
    console.error("❌ Mostrando error:", error, debugInfo);
    
    let htmlError = `
        <div class="alert alert-danger">
            <h6><i class="bi bi-exclamation-triangle"></i> Error al cargar detalles</h6>
            <p>${error}</p>
    `;
    
    if (debugInfo) {
        htmlError += `
            <hr>
            <h6>Información de debug:</h6>
            <small>
                <strong>Archivo:</strong> ${debugInfo.file || 'N/A'}<br>
                <strong>Línea:</strong> ${debugInfo.line || 'N/A'}<br>
                <strong>ID Actividad:</strong> ${debugInfo.idplanclases || idActividadActual || 'N/A'}
            </small>
        `;
    }
    
    htmlError += `
            <hr>
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-danger" onclick="mostrarDetallesInconsistencia(${idActividadActual})">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="mostrarLogsTecnicos()">
                    <i class="bi bi-info-circle"></i> Ver logs técnicos
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('contenido-detalles-inconsistencia').innerHTML = htmlError;
}

function mostrarLogsTecnicos() {
    // Mostrar información técnica para debugging
    const info = {
        userAgent: navigator.userAgent,
        url: window.location.href,
        timestamp: new Date().toISOString(),
        idActividad: idActividadActual
    };
    
    Swal.fire({
        title: 'Información Técnica',
        html: `
            <div class="text-start">
                <h6>Para reporte de error:</h6>
                <div class="bg-light p-2 small">
                    <strong>ID Actividad:</strong> ${info.idActividad}<br>
                    <strong>URL:</strong> ${info.url}<br>
                    <strong>Timestamp:</strong> ${info.timestamp}<br>
                    <strong>Navegador:</strong> ${info.userAgent}
                </div>
                <p class="mt-2 small text-muted">
                    Copia esta información al reportar el error a soporte técnico.
                </p>
            </div>
        `,
        width: '600px',
        confirmButtonText: 'Cerrar'
    });
}

function contactarAreaSalas() {
    Swal.fire({
        icon: 'info',
        title: 'Contactar Área de Salas',
        html: `
            <div class="text-start">
                <p><strong>Contactos recomendados:</strong></p>
                <ul>
                    <li><strong>Email:</strong> salas@uchile.cl</li>
                    <li><strong>Teléfono:</strong> +56 2 2978 6000</li>
                    <li><strong>Oficina:</strong> Edificio Institucional, Piso 3</li>
                </ul>
                <p class="mt-3"><strong>Información a proporcionar:</strong></p>
                <ul>
                    <li>ID Actividad: ${idActividadActual}</li>
                    <li>Salas inconsistentes detectadas</li>
                    <li>Solicitar verificación de reservas</li>
                </ul>
            </div>
        `,
        confirmButtonText: 'Entendido',
        width: '500px'
    });
}

function modificarSalaDesdeInconsistencia() {
    // Cerrar modal actual
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalDetallesInconsistencia'));
    modal.hide();
    
    if (idActividadActual) {
        // Esperar a que se cierre el modal anterior
        setTimeout(() => {
            modificarSala(idActividadActual);
        }, 500);
    }
}

// ✅ INICIALIZAR TOOLTIPS CUANDO SE ABRE EL MODAL (JavaScript nativo)
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('modalDetallesInconsistencia');
    if (modalElement) {
        modalElement.addEventListener('shown.bs.modal', function () {
            // Inicializar tooltips para el contenido dinámico
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    }
});

function recargarSoloTablaDocentes() {
    console.log('🔄 Recargando solo la tabla de docentes clínicos...');
    
    // Buscar específicamente el contenedor de la tabla
    const tablaContainer = document.querySelector('#docentes-list .table-responsive');
    
    if (!tablaContainer) {
        console.log('📋 Contenedor específico no encontrado, usando fallback de pestaña completa');
        // Fallback: recargar toda la pestaña
        const docentesList = document.getElementById('docentes-list');
        const docenteTab = document.getElementById('docente-tab');
        
        if (docentesList) {
            docentesList.removeAttribute('data-loaded');
        }
        
        if (docenteTab) {
            docenteTab.click();
        }
        return;
    }
    
    showSpinnerInElement(tablaContainer);
    fetchAndUpdateTable(tablaContainer);
}

function volverYRecargarTabla() {
    console.log('🔄 Cerrando modal...');
    
    // 1. Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('nuevoDocenteModal'));
    if (modal) {
        modal.hide();
    }
    
    // 2. Limpiar formulario (si existe)
    const form = document.querySelector('#nuevoDocenteModal form');
    if (form) {
        form.reset();
    }
    
    // 3. Estrategia simple: esperar un poco y recargar
    setTimeout(() => {
        console.log('🔄 Ejecutando recarga...');
        recargarSoloTablaDocentes();
    }, 500); // Más tiempo para que se cierre el modal
}


</script>

	<!-- Modal Replicacion-->
	<div class="modal fade" id="modal_replica" tabindex="-1" aria-labelledby="exampleModalLabel"  data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
	 
	  <div class="modal-dialog  modal-xl">
		<div class="modal-content">
		  <div class="modal-header">
			<h1 class="modal-title fs-5" id="exampleModalLabel"><i class="fas fa-clone text-primary"></i> IMPORTANTE: ¿Quieres replicar el calendario desde periodo anterior? </h1>
			<input type="text" name="idCurso" id="idCurso" value="<?php echo $idCurso; ?>" hidden />
		  </div>
		  <div class="modal-body">
			<div class="alert alert-primary justify" role="alert">
				<h6><i class="fas fa-lightbulb text-warning"></i> ¡Queremos hacerte la vida un poco más fácil!</h6>
				<br>
				Sabemos que el llenado del calendario puede ser una tarea extensa y demandante. Por eso, hemos desarrollado una herramienta que te permitirá pre-cargar automáticamente información del último curso ejecutado.
				
				Si decides utilizar esta opción, el sistema buscará el curso anterior y traerá los detalles de actividades, tipos de sesión y docentes asociados. Así tendrás una <b>base inicial</b> desde la cual podrás ajustar y completar la programación actual.
				<br>
				<br>
				<b>Recuerda que, de todos modos, la revisión y validación final de la programación sigue siendo tu responsabilidad, asegurando que refleje fielmente la planificación de este curso.</b>
				<br><br>
				Atte. Unidad de Diseño de Procesos Internos (DPI)
			</div>
			
			<div class="card">
			  <div class="card-body">
				<h5 class="card-title">
				  <i class="fas fa-check-square text-success"></i> Este curso cumple con las condiciones para replicar el calendario anterior
				</h5>
				<br>
				<h6 class="card-subtitle mb-2 text-body-secondary">
				  1. Los cursos tienen la misma duración (en número de semanas) <i class="fas fa-check-square text-success"></i>
				</h6>

				<h6 class="card-subtitle mb-2 text-body-secondary">
				  2. Los cursos mantienen el mismo número de actividades semanales <i class="fas fa-check-square text-success"></i>
				</h6>

				<h6 class="card-subtitle mb-2 text-body-secondary">
				  3. No corresponde a un curso clínico <i class="fas fa-check-square text-success"></i>
				</h6>

			  </div>
			</div>
			
			<div class="card mt-2">
			  <div class="card-body">
				<h5 class="card-title">
				  <i class="fas fa-info-circle text-primary"></i> ¿Qué replicaremos?
				</h5>
				<br>
				<h6 class="card-subtitle mb-2 text-body-secondary">
				  1. El título de las actividades que usaste la última vez.
				</h6>
				<h6 class="card-subtitle mb-2 text-body-secondary">
				  2. Los docentes asociados a las actividades del calendario anterior y que continuen en el equipo docente en el periodo actual. 
				</h6>
				<h6 class="card-subtitle mb-2 text-body-secondary">
				  3. El tipo de actividad y la asistencia (obligatoria o libre) de las actividades.
				</h6>
			  </div>
			</div>
			
			

		  </div>
		  <div class="modal-footer">
			<button id="btn_nuevo" value="no_replicar" type="button" onclick="ejecutar_replica('no_replicar')" class="btn btn-primary">Deseo empezar desde cero</button>
			<button id="btn_ejecutar" value="replicar" type="button" onclick="ejecutar_replica('replicar')" class="btn btn-success">Usar calendario anterior <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="display: none;" id="spinner"></span></button>
			<small id="mensaje_tiempo" class="text-danger" hidden>* Esta acción puede demorar un poco. Te pedimos paciencia y que no presiones ni recargues la página hasta que estemos listos. </small>
		  </div>
		</div>
	  </div>
	</div>



<!-- Justo antes del cierre del body -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="docentes_helper_regular.js"></script>


<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 11"></div>
</body>
</html>