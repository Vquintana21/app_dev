<?php
include_once("conexion.php");
include_once 'login/control_sesion.php';
session_start();
$ruti = $_SESSION['sesion_idLogin'];
$rut = str_pad($ruti, 10, "0", STR_PAD_LEFT);

$idCurso = $_GET['curso']; 

// Consulta SQL
$query = "SELECT `idplanclases`, pcl_tituloActividad, `pcl_Fecha`, `pcl_Inicio`, `pcl_Termino`, 
          `pcl_nSalas`, `pcl_Seccion`, `pcl_TipoSesion`, `pcl_SubTipoSesion`, 
          `pcl_Semana`, `pcl_AsiCodigo`, `pcl_AsiNombre`, `Sala`, `Bloque`, `dia`, `pcl_condicion`, `pcl_ActividadConEvaluacion`, pcl_BloqueExtendido, cursos_idcursos
          FROM `planclases` 
          WHERE `cursos_idcursos` = ?
		  order by pcl_Fecha, pcl_Inicio asc";

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

// ========== NUEVA FUNCIONALIDAD: JUNTAR SECCIONES ==========

// Consultar el cupo del curso individual
$stmtCupo = $conexion3->prepare("SELECT Cupo FROM spre_cursos WHERE idCurso = ?");
$stmtCupo->bind_param("i", $idCurso);
$stmtCupo->execute();
$resultCupo = $stmtCupo->get_result();
$cupoData = $resultCupo->fetch_assoc();
$cupoCurso = $cupoData ? $cupoData['Cupo'] : 0;

// Query para obtener total de alumnos en caso de juntar secciones
$stmtCupoTotal = $conexion3->prepare("SELECT SUM(Cupo) as cupo_total, COUNT(*) as num_secciones 
                                     FROM spre_cursos 
                                     WHERE CodigoCurso = ? AND idperiodo = ?");
$stmtCupoTotal->bind_param("ss", $codigo_curso, $idPeriodo);
$stmtCupoTotal->execute();
$resultCupoTotal = $stmtCupoTotal->get_result();
$cupoTotalData = $resultCupoTotal->fetch_assoc();
$cupoTotalSecciones = $cupoTotalData ? $cupoTotalData['cupo_total'] : $cupoCurso;
$numeroSecciones = $cupoTotalData ? $cupoTotalData['num_secciones'] : 1;

// Consultar todas las secciones del mismo curso para mostrar en interfaz
$stmtSecciones = $conexion3->prepare("SELECT idCurso, Seccion, Cupo 
                                     FROM spre_cursos 
                                     WHERE CodigoCurso = ? AND idperiodo = ? 
                                     ORDER BY Seccion");
$stmtSecciones->bind_param("ss", $codigo_curso, $idPeriodo);
$stmtSecciones->execute();
$resultSecciones = $stmtSecciones->get_result();
$seccionesDisponibles = [];
while ($row = $resultSecciones->fetch_assoc()) {
    $seccionesDisponibles[] = $row;
}

// ========== FIN NUEVA FUNCIONALIDAD ==========

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

// Consulta para obtener bloques de horario

$queryBloques = "SELECT `bloque`, `inicio`, `termino` FROM `Bloques_ext`";

$resultBloques = $conn->query($queryBloques);



// Convertir resultado a array

$bloques = [];

while ($row = $resultBloques->fetch_assoc()) {

    $bloques[] = $row;

}

// Convertir a JSON para usar en JavaScript

$bloquesJson = json_encode($bloques);

// Consulta para obtener los bloques específicos del curso
$queryBloquesDelCurso = "SELECT `Bloque` FROM `spre_horarioscurso` WHERE `idCurso` = ?";
$stmtBloquesDelCurso = $conexion3->prepare($queryBloquesDelCurso);
$stmtBloquesDelCurso->bind_param("i", $idCurso);
$stmtBloquesDelCurso->execute();
$resultBloquesDelCurso = $stmtBloquesDelCurso->get_result();

// Extraer números de bloque Y días en UN SOLO BUCLE
$numerosBloquesCurso = [];
$diasCurso = [];

while ($row = $resultBloquesDelCurso->fetch_assoc()) {
    $bloqueCompleto = $row['Bloque']; // ej: "L4"
    
    if (strlen($bloqueCompleto) >= 2) {
        // Extraer número de bloque (ej: de "L4" extraer "4")
        $numeroBloque = substr($bloqueCompleto, 1);
        if (is_numeric($numeroBloque)) {
            $numerosBloquesCurso[] = (int)$numeroBloque;
        }
    }
    
    if (strlen($bloqueCompleto) >= 1) {
        // Extraer día de la semana (ej: de "L4" extraer "L")
        $letraDia = substr($bloqueCompleto, 0, 1);
        if (!in_array($letraDia, $diasCurso)) {
            $diasCurso[] = $letraDia;
        }
    }
}

// Convertir a JSON para JavaScript
$numerosBloquesCursoJson = json_encode($numerosBloquesCurso);
$diasCursoJson = json_encode($diasCurso);

$stmtBloquesDelCurso->close();



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
	
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	  
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

 <!-- ======= Header ======= -->
  <?php include 'nav_superior.php'; ?>
  
    <!-- ======= Sidebar ======= -->
 <?php include 'nav_lateral.php'; ?>

 <main id="main" class="main">
        <div class="pagetitle">
            <h1><?php echo $codigo_curso."-".$seccion; ?> <?php echo $nombre_curso; ?></h1>
            <small style="float: right;">ID curso: <?php echo $idCurso; ?></small>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="inicio.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Actividades clínicas <?php echo $codigo_curso."-".$seccion; ?></li>
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
        <ul class="nav nav-tabs nav-tabs-bordered d-flex" id="borderedTabJustified" role="tablist">
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 active" id="home-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-home" type="button" role="tab" aria-controls="home" aria-selected="true"><i class="bi bi-calendar4-week"></i> Calendario </button>
            </li>		
			
			
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100" id="salas-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-salas" type="button" role="tab" aria-controls="salas" aria-selected="false"><i class="ri ri-map-pin-line"></i> Salas</button>
            </li>
			
			<li class="nav-item flex-fill" role="presentation">
				<button class="nav-link w-100" id="docente-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-docente" type="button" role="tab" aria-controls="docente" aria-selected="false"><i class="ri ri-user-settings-line"></i> Equipo docente</button>
			</li>
            
        </ul>
    </div>
	<div class="container-fluid py-4">  

	<div class="tab-content" id="borderedTabJustifiedContent">
        <!-- Tab Calendario (ya existente) -->
        <div class="tab-pane fade show active" id="bordered-justified-home" role="tabpanel" aria-labelledby="home-tab">
            <div class="card-body">
                              
                                	
                                 <!-- Botón para agregar actividad -->
								 <hr>
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#activityModal">
                                            <i class="fas fa-plus"></i> Ingresar actividad
                                        </button>
                                    </div>
                                </div>
                                 <hr>
                                <!-- Tabla de actividades -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Hora inicio</th>
                                                <th>Hora término</th>
                                                <th>Actividad</th>
                                                <th>Tipo de actividad</th>
                                                <th>Asistencia obligatoria</th>
                                                <th>Sesión con evaluación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($planclases as $actividad): 
                                                $fecha = new DateTime($actividad['pcl_Fecha']);
                                            ?>
                                            <tr>
                                                <td><?php echo $fecha->format('d-m-Y'); ?></td>
                                                <td><?php echo substr($actividad['pcl_Inicio'], 0, 5); ?></td>
                                                <td><?php echo substr($actividad['pcl_Termino'], 0, 5); ?></td>
                                                <td><?php echo $actividad['pcl_tituloActividad']; ?></td>
                                               <td><?php 
														echo $actividad['pcl_TipoSesion']; 
														// Mostrar subactividad entre paréntesis si existe
														if (!empty($actividad['pcl_SubTipoSesion'])) {
															echo " (" . $actividad['pcl_SubTipoSesion'] . ")";
														}
													?></td>
                                                <td><?php echo $actividad['pcl_condicion'] === 'Obligatorio' ? 'Sí' : 'No'; ?></td>
                                                <td><?php echo $actividad['pcl_ActividadConEvaluacion'] === 'S' ? 'Sí' : 'No'; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary"
                                                            onclick="editActivity(<?php echo $actividad['idplanclases']; ?>)">
                                                        Editar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="deleteActivity(<?php echo $actividad['idplanclases']; ?>)">
                                                        Borrar
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($planclases)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-3">No hay actividades registradas</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
        </div>
		
<!-- Tab Equipo docente -->
<div class="tab-pane fade" id="bordered-justified-docente" role="tabpanel" aria-labelledby="docente-tab">
    <div id="docentes-list">
        <!-- Aquí se cargará el contenido de docentes -->
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Cargando equipo docente...</p>
        </div>
    </div>
</div>

        <!-- Tab Salas (nuevo) -->
        <div class="tab-pane fade" id="bordered-justified-salas" role="tabpanel" aria-labelledby="salas-tab">
            <div id="salas-list">
                <!-- Aquí se cargará el contenido de salas -->
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando gestión de salas...</p>
                </div>
            </div>
        </div>

        <!-- Tab Otras acciones (ya existente) -->
        <div class="tab-pane fade" id="bordered-justified-contact" role="tabpanel" aria-labelledby="contact-tab">
            <!-- Contenido actual de otras acciones -->
            <!-- ... -->
        </div>
    </div>
</div>
 </div>
                            
                            
                        </div>
                    </div>
                </div>
            </div>

            

            <!-- Modal para agregar/editar actividad -->
            <div class="modal fade" id="activityModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="card-title" id="activityModalTitle">Ingresar nueva actividad</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="activityForm">
                                <input type="hidden" id="idplanclases" name="idplanclases" value="0">
                                <input type="hidden" id="cursos_idcursos" name="cursos_idcursos" value="<?php echo $idCurso; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Título de la actividad</label>
                                    <textarea class="form-control" id="activity-title" name="activity-title" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Tipo actividad</label>
                                    <select class="form-control" id="activity-type" name="type" onchange="updateSubTypes()">
                                        <option value="">Seleccione un tipo</option>
                                        <!-- Se llenará dinámicamente -->
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="subtype-container" style="display: none;">
                                    <label class="form-label">Sub Tipo actividad</label>
                                    <select class="form-control" id="activity-subtype" name="subtype">
                                        <!-- Se llenará dinámicamente -->
                                    </select>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Fecha</label>
                                        <input type="date" class="form-control" id="activity-date" name="date" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Bloques de horario</label>
                                    <div id="bloques-container" class="border rounded p-3">
                                        <!-- Se llenará dinámicamente con los bloques -->
                                    </div>
                                    
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="mandatory" name="mandatory">
                                        <label class="form-check-label">Asistencia obligatoria</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is-evaluation" name="is_evaluation">
                                        <label class="form-check-label">Esta actividad incluye una evaluación</label>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" class="btn btn-success" id="saveActivityBtn" onclick="saveActivity()">Guardar actividad</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de confirmación para borrar -->
            <div class="modal fade" id="deleteModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirmar eliminación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>¿Está seguro que desea eliminar esta actividad? Esta acción no se puede deshacer.</p>
                            <input type="hidden" id="delete-id" value="">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer id="footer" class="footer">
        <!-- Footer igual que en index.php -->
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
	 <script src="validarRUT.js"></script>
	  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script> 
   <!-- <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>-->
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  
  <script src="assets/js/main.js"></script>
    
    <script>
    // Tipos de sesión desde PHP
    let tiposSesion = <?php echo $tiposSesionJson; ?>;
    
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
	
	// Bloques horarios desde PHP

    let bloques = <?php echo $bloquesJson; ?>;

    // Actividades del plan de clases

    let planclases = <?php echo $planclasesJson; ?>;

    

    // Mapa para rastrear los IDs de las actividades existentes por bloque

    let actividadesPorBloque = new Map();
    
    function updateSubTypes() {
        const tipoSeleccionado = document.getElementById('activity-type').value;
        const subtypeContainer = document.getElementById('subtype-container');
        const selectSubtipo = document.getElementById('activity-subtype');
        
        // Encontrar el tipo seleccionado en el array
        const tipoInfo = tiposSesion.find(t => t.tipo_sesion === tipoSeleccionado);
        
        if (!tipoInfo) return;
        
        // Manejar subtipo
        if (tipoInfo.subtipo_activo === "1") {
            subtypeContainer.style.display = 'block';
			const label = subtypeContainer.querySelector('label');
if (label && !label.innerHTML.includes('*')) {
    label.innerHTML = label.innerHTML.replace('Sub Tipo actividad', 'Sub Tipo actividad <span style="color: red;">*</span>');
}
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
			const label = subtypeContainer.querySelector('label');
if (label) {
    label.innerHTML = label.innerHTML.replace(/ <span style="color: red;">\*<\/span>/, '');
}
        }
    }
	
let numerosBloquesCurso = <?php echo $numerosBloquesCursoJson; ?>;

// MODIFICAR la función loadBloques existente
function loadBloques(isEditing = false) {
    const bloquesContainer = document.getElementById('bloques-container');
    bloquesContainer.innerHTML = '';
    
    // Obtener datos comunes
    const dateStr = document.getElementById('activity-date').value;
    const idCurso = document.getElementById('cursos_idcursos').value;
    
    console.log('Cargando bloques para fecha:', dateStr, 'curso:', idCurso, 'modo edición:', isEditing);
    console.log('Bloques específicos del curso:', numerosBloquesCurso);
    
    // Determinar qué bloques mostrar
    let bloquesAMostrar = bloques; // Por defecto todos los bloques
    
    if (numerosBloquesCurso && numerosBloquesCurso.length > 0) {
        // Filtrar solo los bloques que corresponden al curso
        bloquesAMostrar = bloques.filter(bloque => {
            return numerosBloquesCurso.includes(parseInt(bloque.bloque));
        });
        console.log('Bloques filtrados para el curso:', bloquesAMostrar);
    }
    
    // Encontrar qué bloques ya están en uso para esta fecha y curso
    const bloquesUsados = new Map();
    
    if (dateStr && idCurso) {
        const idPrincipal = isEditing ? document.getElementById('idplanclases').value : '0';
        
        planclases.forEach(act => {
            const actFecha = act.pcl_Fecha ? act.pcl_Fecha.split(' ')[0] : '';
            
            if (actFecha === dateStr && 
                String(act.cursos_idcursos) === String(idCurso)) {
                
                if (isEditing && String(act.idplanclases) === String(idPrincipal)) {
                    return;
                }
                
                if (act.Bloque) {
                    bloquesUsados.set(String(act.Bloque), {
                        id: act.idplanclases,
                        titulo: act.pcl_tituloActividad || 'Sin título'
                    });
                }
            }
        });
    }

    if (isEditing) {
        // MODO EDICIÓN: Radio buttons
        const titleDiv = document.createElement('div');
        titleDiv.className = 'mb-2 fw-bold';
        titleDiv.textContent = 'Seleccione un bloque horario:';
        bloquesContainer.appendChild(titleDiv);
        
        const idPrincipal = document.getElementById('idplanclases').value;
        const bloqueActual = Array.from(actividadesPorBloque.keys())[0];
        
        bloquesAMostrar.forEach((bloque, index) => {
            const id = `bloque-${bloque.bloque}`;
            const radioDiv = document.createElement('div');
            radioDiv.className = 'form-check mb-2';
            
            const bloqueStr = String(bloque.bloque);
            const estaEnUso = bloquesUsados.has(bloqueStr);
            const esActual = bloqueStr === bloqueActual;
            const disabled = estaEnUso;
            
            let statusText = '';
            if (esActual) {
                statusText = ' <small class="text-success">(Selección actual)</small>';
            } else if (disabled) {
                const actInfo = bloquesUsados.get(bloqueStr);
                const actTitulo = actInfo && actInfo.titulo ? 
                    (actInfo.titulo.length > 25 ? actInfo.titulo.substring(0, 25) + '...' : actInfo.titulo) : '';
                statusText = ` <small class="text-danger">(En uso: "${actTitulo}")</small>`;
            }
            
            radioDiv.innerHTML = `
                <input class="form-check-input bloque-radio" type="radio" 
                       id="${id}" name="bloques" value="${bloqueStr}" 
                       data-inicio="${bloque.inicio}" data-termino="${bloque.termino}"
                       ${esActual ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                <label class="form-check-label ${disabled ? 'text-muted' : ''}" for="${id}">
                    Bloque ${bloque.bloque}: ${bloque.inicio.substring(0, 5)} - ${bloque.termino.substring(0, 5)}
                    ${statusText}
                </label>
                <input type="hidden" class="bloque-idplanclases" id="${id}-idplanclases" 
                       name="bloque_idplanclases" value="${esActual ? idPrincipal : '0'}">
            `;
            
            bloquesContainer.appendChild(radioDiv);
        });
        
    } else {
        // MODO INSERCIÓN: Checkboxes
        const titleDiv = document.createElement('div');
        titleDiv.className = 'mb-2 fw-bold';
        
        // Texto dinámico según si hay bloques específicos o no
        if (numerosBloquesCurso && numerosBloquesCurso.length > 0) {
            titleDiv.textContent = 'Seleccione uno o más bloques horarios del curso:';
        } else {
            titleDiv.textContent = 'Seleccione uno o más bloques horarios:';
        }
        bloquesContainer.appendChild(titleDiv);
        
        bloquesAMostrar.forEach((bloque, index) => {
            const id = `bloque-${bloque.bloque}`;
            const checkboxDiv = document.createElement('div');
            checkboxDiv.className = 'form-check mb-2';
            
            const bloqueStr = String(bloque.bloque);
            const estaEnUso = bloquesUsados.has(bloqueStr);
            const disabled = estaEnUso;
            
            let statusText = '';
            if (disabled) {
                const actInfo = bloquesUsados.get(bloqueStr);
                const actTitulo = actInfo && actInfo.titulo ? 
                    (actInfo.titulo.length > 25 ? actInfo.titulo.substring(0, 25) + '...' : actInfo.titulo) : '';
                statusText = ` <small class="text-danger">(En uso: "${actTitulo}")</small>`;
            }
            
            checkboxDiv.innerHTML = `
                <input class="form-check-input bloque-checkbox" type="checkbox" 
                       id="${id}" name="bloques[]" value="${bloque.bloque}" 
                       data-inicio="${bloque.inicio}" data-termino="${bloque.termino}"
                       ${disabled ? 'disabled' : ''}>
                <label class="form-check-label ${disabled ? 'text-muted' : ''}" for="${id}">
                    Bloque ${bloque.bloque}: ${bloque.inicio.substring(0, 5)} - ${bloque.termino.substring(0, 5)}
                    ${statusText}
                </label>
                <input type="hidden" class="bloque-idplanclases" id="${id}-idplanclases" 
                       name="bloque_idplanclases[]" value="0">
            `;
            
            bloquesContainer.appendChild(checkboxDiv);
        });
    }
    
    // Agregar nota explicativa
    const noteDiv = document.createElement('div');
    noteDiv.className = 'small text-muted mt-2';
    
    if (numerosBloquesCurso && numerosBloquesCurso.length > 0) {
        noteDiv.innerHTML = 'Nota: Solo se muestran los bloques correspondientes al horario oficial de este curso. Los bloques marcados como "En uso" ya están asignados a otras actividades.';
    } else {
        noteDiv.innerHTML = 'Nota: Los bloques marcados como "En uso" ya están asignados a otras actividades para este curso en este día.';
    }
    bloquesContainer.appendChild(noteDiv);
}


function updateBloquesOnDateChange() {
    const isEditing = document.getElementById('idplanclases').value !== '0';
    loadBloques(isEditing);
}
    
function resetForm() {

	
    document.getElementById('activityForm').reset();
    document.getElementById('idplanclases').value = '0';
    document.getElementById('activityModalTitle').textContent = 'Ingresar nueva actividad';
    document.getElementById('subtype-container').style.display = 'none';
    
    // Limpiar los IDs asociados a bloques
    actividadesPorBloque.clear();
    
    // Establecer fecha por defecto a hoy
// Establecer fecha por defecto a hoy
const today = new Date().toISOString().split('T')[0];
document.getElementById('activity-date').value = today;
document.getElementById('activity-date').min = today; // ✅ Solo evita fechas pasadas

aplicarFechaLimite();
    
    // Cargar bloques como checkboxes para modo inserción
    loadBloques(false);
    
   // Limpiar cualquier estado de validación del formulario
document.querySelectorAll('.is-invalid').forEach(el => {
    el.classList.remove('is-invalid');
    ocultarErrorCampo(el);
});

	// Asegurar que el listener de cambio de fecha esté configurado
const dateInput = document.getElementById('activity-date');
if (!dateInput.hasAttribute('data-has-change-listener')) {
    dateInput.addEventListener('change', updateBloquesOnDateChange);
    dateInput.setAttribute('data-has-change-listener', 'true');
}

}

// 🆕 NUEVA FUNCIÓN: Obtener y aplicar fecha límite del curso
async function aplicarFechaLimite() {
    try {
        const idCurso = document.getElementById('cursos_idcursos').value;
        
        const response = await fetch(`get_fecha_limite.php?idCurso=${idCurso}`);
        const data = await response.json();
        
        if (data.success && data.fecha_fin) {
            const dateInput = document.getElementById('activity-date');
            dateInput.max = data.fecha_fin;
            
            console.log(`📅 Fecha límite aplicada: ${data.fecha_fin}`);
        } else {
            console.warn('⚠️ No se pudo obtener fecha límite:', data.error);
        }
    } catch (error) {
        console.error('❌ Error obteniendo fecha límite:', error);
    }
}
    
    function editActivity(idplanclases) {
    // Cambiar título del modal
    document.getElementById('activityModalTitle').textContent = 'Editar actividad';
    
    // Mostrar indicador de carga
    mostrarToast('Cargando actividad...', 'info');
    
    // Cargar datos de la actividad
    fetch(`get_actividad_clinica.php?id=${idplanclases}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error de red: ${response.status}`);
            }
            return response.json();
        })
        .then(activity => {
            console.log('Datos de actividad cargados:', activity);
            
			
			
            document.getElementById('idplanclases').value = activity.idplanclases;
            document.getElementById('activity-title').value = activity.pcl_tituloActividad || '';
            document.getElementById('activity-type').value = activity.pcl_TipoSesion || '';
            updateSubTypes();
            
            if (activity.pcl_SubTipoSesion) {
                document.getElementById('activity-subtype').value = activity.pcl_SubTipoSesion;
            }
            
            // Extraer solo la parte de fecha (2025-03-20 00:00:00 → 2025-03-20)
            let fechaFormateada;
            if (activity.pcl_Fecha && activity.pcl_Fecha.includes(' ')) {
                fechaFormateada = activity.pcl_Fecha.split(' ')[0];
            } else {
                // Si no tiene el formato esperado, intentar crear un objeto Date
                const fecha = new Date(activity.pcl_Fecha);
                fechaFormateada = fecha.toISOString().split('T')[0];
            }
            
            console.log('Fecha extraída:', fechaFormateada);
            document.getElementById('activity-date').value = fechaFormateada;
			
			aplicarFechaLimite();
            
            // Limpiar cualquier selección anterior
            actividadesPorBloque.clear();
            
            // Guardar el bloque actual
            const bloqueActual = activity.Bloque;
            if (bloqueActual) {
                console.log('Registrando bloque actual:', bloqueActual);
                actividadesPorBloque.set(String(bloqueActual), activity.idplanclases);
            }
            
           if (!document.getElementById('activity-date').hasAttribute('data-has-change-listener')) {
				document.getElementById('activity-date').addEventListener('change', updateBloquesOnDateChange);
				document.getElementById('activity-date').setAttribute('data-has-change-listener', 'true');
			}
            
            // Checkbox para otras opciones
            document.getElementById('mandatory').checked = activity.pcl_condicion === 'Obligatorio';
            document.getElementById('is-evaluation').checked = activity.pcl_ActividadConEvaluacion === 'S';
            
            // Cargar bloques como radio buttons para modo edición
            loadBloques(true);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('activityModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error al cargar la actividad:', error);
            mostrarToast('Error al cargar los datos de la actividad: ' + error.message, 'danger');
        });
}
    
    function deleteActivity(idplanclases) {
        document.getElementById('delete-id').value = idplanclases;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
	
	const camposAmigables = {
    'activity-title': 'Título de la actividad',
    'type': 'Tipo de actividad',
    'subtype': 'Subtipo de actividad',
    'date': 'Fecha',
    'start_time': 'Hora de inicio',
    'end_time': 'Hora de término',
    'bloques': 'Bloques horarios',
    'mandatory': 'Asistencia obligatoria',
    'is_evaluation': 'Actividad con evaluación'
};

// Función mejorada para validar el formulario antes de guardar
function validarFormularioActividad() {
    const errores = [];
    
    // Validar título de actividad
    const titulo = document.getElementById('activity-title').value.trim();
    if (!titulo) {
        errores.push(camposAmigables['activity-title']);
    }
    
    // Validar tipo de actividad
    const tipo = document.getElementById('activity-type').value;
    if (!tipo) {
        errores.push(camposAmigables['type']);
    }
    
    // Validar fecha
    const fecha = document.getElementById('activity-date').value;
    if (!fecha) {
        errores.push(camposAmigables['date']);
    }
    
    // Validar selección de bloques
    const isEditing = document.getElementById('idplanclases').value !== '0';
    let bloquesSeleccionados = 0;
    
    if (isEditing) {
        // En edición, verificar radio button seleccionado
        const radioSelected = document.querySelector('.bloque-radio:checked');
        if (!radioSelected) {
            errores.push(camposAmigables['bloques']);
        }
    } else {
        // En inserción, verificar checkboxes seleccionados
        const checkboxesSelected = document.querySelectorAll('.bloque-checkbox:checked');
        if (checkboxesSelected.length === 0) {
            errores.push(camposAmigables['bloques']);
        }
    }
    
    // VALIDACIÓN CORREGIDA - Validar que la fecha no sea de días anteriores
    if (fecha) {
        // Obtener fecha de hoy en formato YYYY-MM-DD (mismo formato que el input)
        const hoy = new Date();
        const hoyStr = hoy.getFullYear() + '-' + 
                      String(hoy.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(hoy.getDate()).padStart(2, '0');
        
        console.log('Comparando fechas:', { fechaSeleccionada: fecha, fechaHoy: hoyStr });
        
        // Comparar como strings en formato YYYY-MM-DD
        if (fecha < hoyStr) {
            errores.push('La fecha no puede ser de días anteriores');
        }
		
		  const dateInput = document.getElementById('activity-date');
        const fechaLimite = dateInput.max;
        
        if (fechaLimite && fecha > fechaLimite) {
            errores.push(`La fecha no puede ser posterior al ${fechaLimite} (fin del período académico)`);
        }
		
    }
    
    return errores;
}
    
    function saveActivity() {
		
		const activityType = document.getElementById('activity-type').value;
const subtypeSelect = document.getElementById('activity-subtype');

// Buscar si este tipo requiere subtipo
const tipoInfo = tiposSesion.find(t => t.tipo_sesion === activityType);

if (tipoInfo && tipoInfo.subtipo_activo === "1") {
    const subtypeValue = subtypeSelect.value.trim();
    
    if (!subtypeValue) {
        mostrarToast('Debe seleccionar un subtipo de actividad', 'danger');
        subtypeSelect.focus();
        subtypeSelect.style.borderColor = '#dc3545';
        return; // DETENER GUARDADO
    }
}
		
    console.log('Iniciando proceso de guardado...');
    
    // Validar formulario con mensajes amigables
    const errores = validarFormularioActividad();
    
    if (errores.length > 0) {
        let mensaje = 'Por favor complete los siguientes campos:\n\n';
        errores.forEach((error, index) => {
            mensaje += `• ${error}\n`;
        });
        
        // Mostrar alerta con SweetAlert si está disponible, sino usar alert nativo
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                text: mensaje,
                confirmButtonText: 'Entendido'
            });
        } else {
            alert(mensaje);
        }
        return;
    }
    
    // Si llegamos aquí, la validación pasó, continuar con el guardado
    console.log('Validación exitosa, procediendo a guardar...');
    
    // Valores comunes
    const idPrincipal = document.getElementById('idplanclases').value;
    const isEditing = idPrincipal !== '0';
    
    // Verificar selección de bloques
    let selectedBloques;
    
    if (isEditing) {
        const radioSelected = document.querySelector('.bloque-radio:checked');
        selectedBloques = [radioSelected];
    } else {
        selectedBloques = document.querySelectorAll('.bloque-checkbox:checked');
    }
    
    // Obtener valores para el día
    const dateStr = document.getElementById('activity-date').value;
    const date = new Date(dateStr);
    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const dia = dayNames[date.getDay()];
    
    // Otros valores comunes
    const idCurso = document.getElementById('cursos_idcursos').value;
    const titulo = document.getElementById('activity-title').value.trim();
    const tipo = document.getElementById('activity-type').value;
    const subtipo = document.getElementById('activity-subtype') ? document.getElementById('activity-subtype').value : '';
    const fecha = dateStr;
    const obligatorio = document.getElementById('mandatory').checked ? 'Obligatorio' : 'Libre';
    const evaluacion = document.getElementById('is-evaluation').checked ? 'S' : 'N';
    
    // Mostrar indicador de carga
    mostrarToast('Guardando actividad...', 'info');
    
    // Array para almacenar promesas de guardado
    const savePromises = [];
    
    // Procesar cada bloque seleccionado
    selectedBloques.forEach(bloqueElement => {
        const bloque = bloqueElement.value;
        const inicio = bloqueElement.dataset.inicio;
        const termino = bloqueElement.dataset.termino;
        
        // Crear FormData para este bloque
        const formData = new FormData();
        
        // Si estamos en modo edición, incluir el ID
        if (isEditing) {
            formData.append('idplanclases', idPrincipal);
        }
        
        formData.append('activity-title', titulo);
        formData.append('type', tipo);
        formData.append('subtype', subtipo);
        formData.append('date', fecha);
        formData.append('start_time', inicio.substring(0, 5));
        formData.append('end_time', termino.substring(0, 5));
        formData.append('cursos_idcursos', idCurso);
        formData.append('dia', dia);
        formData.append('pcl_condicion', obligatorio);
        formData.append('pcl_ActividadConEvaluacion', evaluacion);
        formData.append('Bloque', bloque);
        
        // Guardar actividad
        const savePromise = fetch('guardar_actividad_clinica.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || `Error al ${isEditing ? 'actualizar' : 'guardar'} la actividad`);
            }
            return data;
        });
        
        savePromises.push(savePromise);
    });
    
    // Procesar todas las operaciones de guardado
    Promise.all(savePromises)
        .then(results => {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('activityModal'));
            modal.hide();
            
            // Mostrar notificación de éxito con SweetAlert si está disponible
            const mensaje = isEditing ? 'Actividad actualizada exitosamente' : 'Actividades creadas exitosamente';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: mensaje,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                mostrarToast(mensaje, 'success');
                setTimeout(() => location.reload(), 500);
            }
        })
        .catch(error => {
            console.error('Error al guardar:', error);
            
            // Mostrar error con SweetAlert si está disponible
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: error.message,
                    confirmButtonText: 'Entendido'
                });
            } else {
                mostrarToast('Error: ' + error.message, 'danger');
            }
        });
}
    
    // Simplificamos la función para eliminar solo la actividad específica
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    const idplanclases = document.getElementById('delete-id').value;
    
    // Eliminar directamente esta actividad individual
    eliminarActividadIndividual(idplanclases);
});


// Función auxiliar para validación en tiempo real (opcional)
function configurarValidacionTiempoReal() {
    // Validar título en tiempo real
    const titleInput = document.getElementById('activity-title');
    if (!titleInput.hasAttribute('data-validation-configured')) {
        titleInput.addEventListener('blur', function() {
            const titulo = this.value.trim();
            if (!titulo) {
                this.classList.add('is-invalid');
                mostrarErrorCampo(this, 'El título de la actividad es requerido');
            } else {
                this.classList.remove('is-invalid');
                ocultarErrorCampo(this);
            }
        });
        titleInput.setAttribute('data-validation-configured', 'true');
    }
    
    // Validar tipo de actividad
    const typeInput = document.getElementById('activity-type');
    if (!typeInput.hasAttribute('data-validation-configured')) {
        typeInput.addEventListener('change', function() {
            const tipo = this.value;
            if (!tipo) {
                this.classList.add('is-invalid');
                mostrarErrorCampo(this, 'Debe seleccionar un tipo de actividad');
            } else {
                this.classList.remove('is-invalid');
                ocultarErrorCampo(this);
            }
        });
        typeInput.setAttribute('data-validation-configured', 'true');
    }
    
    // Validar fecha - VERSIÓN MEJORADA SIN DUPLICADOS
    const dateInput = document.getElementById('activity-date');
    if (!dateInput.hasAttribute('data-validation-configured')) {
        dateInput.addEventListener('change', function() {
            // Limpiar cualquier estado de error previo INMEDIATAMENTE
            this.classList.remove('is-invalid');
            ocultarErrorCampo(this);
            
            const fecha = this.value;
            if (!fecha) {
                this.classList.add('is-invalid');
                mostrarErrorCampo(this, 'La fecha es requerida');
                return;
            }
            
            const dateInput = document.getElementById('activity-date');
    if (!dateInput.hasAttribute('data-validation-configured')) {
        dateInput.addEventListener('change', function() {
            // Limpiar cualquier estado de error previo INMEDIATAMENTE
            this.classList.remove('is-invalid');
            ocultarErrorCampo(this);
            
            const fecha = this.value;
            if (!fecha) {
                this.classList.add('is-invalid');
                mostrarErrorCampo(this, 'La fecha es requerida');
                return;
            }
            
            // VALIDACIÓN CORREGIDA - Permitir fecha de hoy
            const hoy = new Date();
            const hoyStr = hoy.getFullYear() + '-' + 
                          String(hoy.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(hoy.getDate()).padStart(2, '0');
            
            if (fecha < hoyStr) {
                this.classList.add('is-invalid');
                mostrarErrorCampo(this, 'La fecha no puede ser de días anteriores');
            }
			
			 const fechaLimite = this.max;
            if (fechaLimite && fecha > fechaLimite) {
                this.classList.add('is-invalid');
                mostrarErrorCampo(this, `La fecha no puede ser posterior al ${fechaLimite} (fin del período académico)`);
                return;
            }
			
        });
        dateInput.setAttribute('data-validation-configured', 'true');
    }
			
            // Si la fecha es válida, los errores ya se limpiaron al inicio
        });
        dateInput.setAttribute('data-validation-configured', 'true');
    }
	
	 
    //if (!dateInput.hasAttribute('data-dia-validation-configured')) {
    //    dateInput.addEventListener('change', validarDiaClase);
    //    dateInput.setAttribute('data-dia-validation-configured', 'true');
    //}
}

// Funciones auxiliares para mostrar/ocultar errores de campo
function mostrarErrorCampo(elemento, mensaje) {
    // Remover mensaje de error existente
    ocultarErrorCampo(elemento);
    
    // Crear nuevo mensaje de error
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = mensaje;
    errorDiv.setAttribute('data-error-for', elemento.id);
    
    // Insertar después del elemento
    elemento.parentNode.insertBefore(errorDiv, elemento.nextSibling);
}

function ocultarErrorCampo(elemento) {
    const errorExistente = document.querySelector(`[data-error-for="${elemento.id}"]`);
    if (errorExistente) {
        errorExistente.remove();
    }
}



function eliminarActividadIndividual(idplanclases) {
    fetch('eliminar_actividad_clinica.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ idplanclases: idplanclases })
    })
    .then(response => response.json())
    .then(data => {
        // Cerrar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
        modal.hide();
        
        if (data.success) {
            mostrarToast('Actividad eliminada exitosamente', 'success');
            // Recargar página después de un breve periodo
            setTimeout(() => location.reload(), 500);
        } else {
            throw new Error(data.message || 'Error al eliminar la actividad');
        }
    })
    .catch(error => {
        mostrarToast('Error: ' + error.message, 'danger');
    });
}
    
    // Función para mostrar notificaciones
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
        
        toastContainer.innerHTML = '';
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();
    }
    
// ==================== ÚNICO DOMContentLoaded - CONSOLIDADO ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando aplicación...');
    
    // ===== CONFIGURACIÓN PRINCIPAL =====
    loadActivityTypes();
    loadBloques(false); // Inicialmente cargar como checkboxes para inserción
    
    // Configurar modal para nueva actividad
    document.getElementById('activityModal').addEventListener('show.bs.modal', function (event) {
        if (!event.relatedTarget) return; // Si es edición, no resetear
        resetForm();
    });
    
    // Establecer fecha por defecto a hoy para nueva actividad
const today = new Date().toISOString().split('T')[0];
document.getElementById('activity-date').value = today;
document.getElementById('activity-date').min = today; // ✅ Solo evita fechas pasadas
    
    // ===== CONFIGURAR LISTENERS DE FECHA (SOLO UNA VEZ) =====
    const dateInputMain = document.getElementById('activity-date');
    if (!dateInputMain.hasAttribute('data-bloques-listener-configured')) {
        dateInputMain.addEventListener('change', function() {
            const isEditing = document.getElementById('idplanclases').value !== '0';
            if (isEditing) {
                loadBloques(true);
            }
        });
        dateInputMain.setAttribute('data-bloques-listener-configured', 'true');
    }
    
    // Configurar validación en tiempo real
    configurarValidacionTiempoReal();
    
    // ===== GESTIÓN DE SALAS =====
    const salasTab = document.getElementById('salas-tab');
    if (salasTab) {
        salasTab.addEventListener('click', function() {
            const salasList = document.getElementById('salas-list');
            
            if (!salasList.dataset.loaded) {
                // Mostrar indicador de carga
                salasList.innerHTML = `
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando gestión de salas...</p>
                    </div>
                `;
                
                // Obtener el ID del curso de la URL
                const urlParams = new URLSearchParams(window.location.search);
                const cursoId = urlParams.get('curso');
                
                // Realizar la petición AJAX
                fetch('salas_clinico.php?curso=' + cursoId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.text();
                    })
                    .then(html => {
                        salasList.innerHTML = html;
                        salasList.dataset.loaded = 'true';
                        
                        // Inicializar componentes después de cargar
                        const nSalasSelect = document.getElementById('nSalas');
                        if (nSalasSelect) {
                            nSalasSelect.addEventListener('change', calcularAlumnosPorSala);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        salasList.innerHTML = `
                            <div class="alert alert-danger m-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Error al cargar la gestión de salas: ${error.message}
                            </div>
                        `;
                    });
            }
        });
        
        // Si la pestaña está activa al cargar, cargar el contenido inmediatamente
        if (salasTab.classList.contains('active')) {
            salasTab.click();
        }
    }
    
    // ===== GESTIÓN DE DOCENTES =====
    const docenteTab = document.getElementById('docente-tab');
    const docentesList = document.getElementById('docentes-list');
    
    if (docenteTab && docentesList) {
        docenteTab.addEventListener('click', function() {
            // Evitar cargar múltiples veces
            if (docentesList.getAttribute('data-loaded') === 'true') {
                // Si ya está cargado, solo reinicializar las horas
                setTimeout(() => {
                    setupHorasDirectasClinico();
                }, 500);
                return;
            }
            
            // Mostrar indicador de carga
            docentesList.innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando equipo docente...</p>
                </div>
            `;
            
            const urlParams = new URLSearchParams(window.location.search);
            const idCurso = urlParams.get('curso');
            
            fetch('get_docentes_table_clinico.php?idcurso=' + idCurso)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.text();
                })
                .then(html => {
                    // Insertar el contenido
                    docentesList.innerHTML = html;
                    docentesList.setAttribute('data-loaded', 'true');
                    
                    // Inicializar Select2 después de cargar el contenido
                        setTimeout(() => {

// ===== FUNCIÓN AUXILIAR PARA CUANDO LA PESTAÑA ESTÁ ACTIVA =====
function recargarTablaConPestanaActiva() {
    console.log('🔍 Buscando contenedor con pestaña activa...');
    
    // Buscar selectores en orden de prioridad
    const selectores = [
        '#bordered-justified-docente #docentes-list',        // Más específico
        '#docentes-list',                                    // Directo
        '#bordered-justified-docente .table-responsive',     // Tabla dentro de la pestaña
        '#bordered-justified-docente',                       // Toda la pestaña
    ];
    
    let contenedor = null;
    
    for (let i = 0; i < selectores.length; i++) {
        contenedor = document.querySelector(selectores[i]);
        console.log(`🔍 Selector "${selectores[i]}":`, contenedor);
        if (contenedor) {
            console.log(`✅ Contenedor encontrado: ${selectores[i]}`);
            break;
        }
    }
    
    if (!contenedor) {
        console.error('❌ Ningún contenedor encontrado. Forzando recarga completa...');
        forzarRecargaCompleta();
        return;
    }
    
    // Mostrar spinner y recargar
    mostrarSpinnerYRecargar(contenedor);
}

// ===== FUNCIÓN PARA FORZAR RECARGA COMPLETA =====
function forzarRecargaCompleta() {
    console.log('🔄 Forzando recarga completa de la pestaña...');
    
    // Limpiar cualquier flag de carga
    const docentesList = document.getElementById('docentes-list');
    if (docentesList) {
        docentesList.removeAttribute('data-loaded');
        console.log('🗑️ Flag de carga eliminado');
    }
    
    // Forzar recarga haciendo click en la pestaña
    const docenteTab = document.getElementById('docente-tab');
    if (docenteTab) {
        setTimeout(() => {
            docenteTab.click();
            console.log('✅ Pestaña recargada por click forzado');
        }, 500);
    } else {
        console.error('❌ No se encontró la pestaña de docentes');
    }
}

// ===== FUNCIÓN PARA MOSTRAR SPINNER Y RECARGAR =====
function mostrarSpinnerYRecargar(contenedor) {
    if (!contenedor) return;
    
    console.log('🔄 Mostrando spinner y recargando contenido...');
    
    // Mostrar spinner
    contenedor.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Actualizando...</span>
            </div>
            <p class="mt-2">Actualizando equipo docente...</p>
        </div>
    `;
    
    // Obtener ID del curso
    const urlParams = new URLSearchParams(window.location.search);
    const idCurso = urlParams.get('curso');
    
    if (!idCurso) {
        console.error('❌ No se encontró ID del curso');
        contenedor.innerHTML = `
            <div class="alert alert-danger m-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Error: No se encontró el ID del curso
            </div>
        `;
        return;
    }
    
    // Hacer fetch
    fetch('get_docentes_table_clinico.php?idcurso=' + idCurso)
        .then(response => {
            console.log('📡 Respuesta recibida:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('✅ HTML recibido, longitud:', html.length);
            
            // Reemplazar contenido
            contenedor.innerHTML = html;
            
            // Reinicializar funcionalidades
            setTimeout(() => {
                reinicializarFuncionalidades();
            }, 500);
        })
        .catch(error => {
            console.error('❌ Error en fetch:', error);
            contenedor.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error al cargar: ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Recargar página
                    </button>
                </div>
            `;
        });
}

// ===== FUNCIÓN PARA REINICIALIZAR FUNCIONALIDADES =====
function reinicializarFuncionalidades() {
    console.log('🔧 Reinicializando funcionalidades...');
    
    const funciones = [
        { nombre: 'setupHorasDirectasClinico', func: window.setupHorasDirectasClinico },
        { nombre: 'inicializarBusquedaDocentesClinico', func: window.inicializarBusquedaDocentesClinico },
        { nombre: 'inicializarCrearDocente', func: window.inicializarCrearDocente },
        { nombre: 'inicializarEstilosHorasClinico', func: window.inicializarEstilosHorasClinico }
    ];
    
    funciones.forEach(({ nombre, func }) => {
        if (typeof func === 'function') {
            try {
                func();
                console.log(`✅ ${nombre} reinicializada`);
            } catch (error) {
                console.warn(`⚠️ Error reinicializando ${nombre}:`, error);
            }
        } else {
            console.warn(`⚠️ ${nombre} no disponible`);
        }
    });
}

function showSpinnerInElement(element) {
    element.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Actualizando...</span>
            </div>
            <p class="mt-2">Actualizando equipo docente...</p>
        </div>
    `;
}

function fetchAndUpdateTable(container) {
    const urlParams = new URLSearchParams(window.location.search);
    const idCurso = urlParams.get('curso');
    
    // ✅ USAR EL ARCHIVO CORREGIDO CON FUNCIONALIDAD DE CURSOS CLÍNICOS
    fetch('get_docentes_table_clinico.php?idcurso=' + idCurso)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('✅ Tabla de docentes clínicos actualizada');
            
            // Reemplazar el contenido de la tabla
            container.outerHTML = html;
            
            // ✅ REINICIALIZAR LA FUNCIONALIDAD DE HORAS DIRECTAS
            setTimeout(() => {
                if (typeof setupHorasDirectasClinico === 'function') {
                    setupHorasDirectasClinico();
                    console.log('✅ Funcionalidad de horas directas reinicializada');
                }

if (typeof inicializarBusquedaDocentesClinico === 'function') {
        inicializarBusquedaDocentesClinico();
    }
    
    if (typeof inicializarCrearDocente === 'function') {
        inicializarCrearDocente();
    }
    // ✅ NUEVO: Inicializar estilos de horas
    if (typeof inicializarEstilosHorasClinico === 'function') {
        inicializarEstilosHorasClinico();
    }
				else {
                    console.warn('⚠️ setupHorasDirectasClinico no está disponible');
                }
            }, 500);
        })
        .catch(error => {
            console.error('❌ Error al actualizar tabla:', error);
            container.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error al cargar los datos: ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Recargar página
                    </button>
                </div>
            `;
        });
}
			   
			   
                if (typeof setupHorasDirectasClinico === 'function') {
                    setupHorasDirectasClinico();
                }
                
                
            }, 500);
                })
                .catch(error => {
                    console.error('Error:', error);
                    docentesList.innerHTML = `
                        <div class="alert alert-danger m-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Error al cargar el equipo docente: ${error.message}
                        </div>
                    `;
                });
        });
        
        // Si la pestaña está activa al cargar la página, cargar inmediatamente
        if (docenteTab.classList.contains('active') || docenteTab.parentElement.classList.contains('active')) {
            docenteTab.click();
			 setTimeout(() => {
        if (typeof inicializarCrearDocente === 'function') {
            inicializarCrearDocente();
        }
    }, 500);
        }
    }
    
    console.log('✅ Inicialización completada');
});
	


/**
 * Esta función se usa para enviar solicitudes AJAX a salas_clinico.php
 * Devuelve una promesa con la respuesta JSON
 */
function enviarSolicitudSala(accion, datos) {
    return fetch('salas_clinico.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: accion,
            ...datos
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error en la respuesta del servidor: ${response.status}`);
        }
        return response.json();
    })
    .catch(error => {
        console.error('Error en la solicitud:', error);
        mostrarNotificacion(`Error: ${error.message}`, 'danger');
        throw error;
    });
}

/**
 * Función unificada para mostrar notificaciones
 */
function mostrarNotificacion(mensaje, tipo = 'success') {
    // Crear o utilizar un contenedor para las notificaciones
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    // Crear el toast
    const toastId = 'toast-' + Date.now();
    const icono = tipo === 'success' ? 'check-circle' : 
                  tipo === 'warning' ? 'exclamation-triangle' : 
                  'x-circle';
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${icono} me-2"></i> ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    // Añadir el toast al contenedor
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    // Inicializar y mostrar el toast
    const toastElement = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: 3000
    });
    toastElement.show();
    
    // Eliminar el toast del DOM después de ocultarse
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// Asegurar que calcularAlumnosPorSala esté definido globalmente
if (typeof calcularAlumnosPorSala !== 'function') {
    window.calcularAlumnosPorSala = function() {
        const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
        const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
        // Usar Math.ceil para redondear hacia arriba sin decimales
        const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
        document.getElementById('alumnosPorSala').value = alumnosPorSala;
    };
}

/**
 * Reescribimos las funciones principales para usar el método unificado de envío
 */


// Función mejorada de guardarSala() para depuración
async function guardarSala() {
    const form = document.getElementById('salaForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Recopilar datos del formulario
    const formData = new FormData(form);
    const datos = Object.fromEntries(formData.entries());
    
    // Mostrar un indicador de carga
    mostrarNotificacion('Procesando solicitud...', 'info');
    
    try {
        // Imprimir los datos a enviar para depuración
        console.log('Datos a enviar:', JSON.stringify(datos, null, 2));
        
        // Realizar la solicitud directamente sin usar enviarSolicitudSala
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(datos)
        });
        
        // Verificar el estado de la respuesta
        if (!response.ok) {
            const responseText = await response.text();
            console.error('Error en la respuesta:', responseText);
            throw new Error(`Error del servidor: ${response.status}. Detalles: ${responseText.substring(0, 200)}`);
        }
        
        // Si llegamos aquí, la respuesta fue exitosa, intentar parsearla como JSON
        const responseText = await response.text();
        console.log('Respuesta (texto):', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parseando la respuesta como JSON:', parseError);
            console.error('Respuesta recibida:', responseText);
            throw new Error('La respuesta no es un JSON válido');
        }
        
        if (data.success) {
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarNotificacion('Solicitud de sala procesada correctamente', 'success');
            
            // Recargar la página para ver los cambios
            setTimeout(() => {
    recargarTablaSalasClinico();
}, 500);
        } else {
            throw new Error(data.error || 'Error desconocido del servidor');
        }
    } catch (error) {
        console.error('Error completo:', error);
        mostrarNotificacion(`Error: ${error.message}`, 'danger');
    }
}

// Calcular alumnos por sala (función reutilizable)
//function calcularAlumnosPorSala() {
//    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
//    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
//    // Usar Math.ceil para redondear hacia arriba
//    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
//    document.getElementById('alumnosPorSala').value = alumnosPorSala;
//}
	
</script>



<script>

// Función para actualizar función de docente en cursos clínicos
window.actualizarFuncion = function(selectElement, idProfesoresCurso) {
    const nuevoTipo = selectElement.value;
    
    // Mostrar loading en el select
    const originalHtml = selectElement.innerHTML;
    selectElement.disabled = true;
    selectElement.innerHTML = '<option>Actualizando...</option>';
    
    $.ajax({
        url: 'guardarFuncion.php', // Usar el mismo archivo que cursos regulares
        type: 'POST',
        data: { 
            idProfesoresCurso: idProfesoresCurso,
            idTipoParticipacion: nuevoTipo
        },
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                // Restaurar el select
                selectElement.disabled = false;
                selectElement.innerHTML = originalHtml;
                selectElement.value = nuevoTipo;
                
                // Mostrar toast de éxito
                mostrarToast('Función actualizada exitosamente', 'success');
            } else {
                // Error: restaurar valor anterior
                selectElement.disabled = false;
                selectElement.innerHTML = originalHtml;
                mostrarToast(response.message || 'Error al actualizar la función', 'danger');
            }
        },
        error: function() {
            // Error: restaurar valor anterior
            selectElement.disabled = false;
            selectElement.innerHTML = originalHtml;
            mostrarToast('Error de comunicación con el servidor', 'danger');
        }
    });
};

</script>
<script>
// JavaScript mejorado con debug para manejo de horas directas en cursos clínicos
function setupHorasDirectasClinico() {
    console.log('🔧 Inicializando manejo de horas directas para cursos clínicos');
    
    // Remover event listeners existentes para evitar duplicados
    $(document).off('blur', '.hours-input');
    $(document).off('input', '.hours-input');
    
    // Verificar que existan inputs de horas
    const hoursInputs = $('.hours-input');
    console.log(`📊 Encontrados ${hoursInputs.length} inputs de horas`);
    
    if (hoursInputs.length === 0) {
        console.warn('⚠️ No se encontraron inputs de horas. Verificar que la tabla se haya cargado correctamente.');
        return;
    }
    
    // Debug: Verificar datos de cada input
    hoursInputs.each(function(index, input) {
        const $input = $(input);
        console.log(`🔍 Input ${index + 1}:`, {
            id: input.id,
            value: input.value,
            'data-id-profesor': $input.attr('data-id-profesor'),
            'data-rut': $input.attr('data-rut'),
            'data-unidad-academica': $input.attr('data-unidad-academica'),
            'data-original-value': $input.attr('data-original-value'),
            // También verificar con .data()
            'jQuery-data-id-profesor': $input.data('id-profesor'),
            'jQuery-data-rut': $input.data('rut')
        });
    });
    
    // Event listener para cuando el input pierde el foco (blur)
    $(document).on('blur', '.hours-input', function() {
        console.log('👁️ Evento blur disparado en input de horas');
        
        const input = this;
        const $input = $(input);
        
        // Obtener datos usando .attr() en lugar de .data() para debug
        const idProfesoresCurso = $input.attr('data-id-profesor');
        const rutDocente = $input.attr('data-rut');
        const unidadAcademica = $input.attr('data-unidad-academica') || '';
        const horas = parseFloat(input.value) || 0;
        const valorOriginal = parseFloat($input.attr('data-original-value')) || 0;
        
        // Debug: verificar datos del input
        console.log('📋 Blur event - datos del input:', {
            element: input,
            id: input.id,
            idProfesoresCurso: idProfesoresCurso,
            rutDocente: rutDocente,
            unidadAcademica: unidadAcademica,
            horas: horas,
            valorOriginal: valorOriginal,
            inputValue: input.value
        });
        
        // Verificar si los datos están presentes
        if (!idProfesoresCurso || !rutDocente) {
            console.error('❌ Faltan atributos data en el input:', {
                'data-id-profesor': idProfesoresCurso,
                'data-rut': rutDocente,
                'HTML del elemento': input.outerHTML
            });
            mostrarToast('Error: El input no tiene los datos necesarios. Verifica la consola.', 'danger');
            return;
        }
        
        // Validar que solo sean números
        if (input.value !== '' && !/^\d*\.?\d*$/.test(input.value)) {
            mostrarToast('Solo se permiten números', 'warning');
            input.value = valorOriginal;
            actualizarEstadoVisual(input, valorOriginal);
            return;
        }
        
        // Solo guardar si el valor cambió
        if (horas !== valorOriginal) {
            console.log('💾 Valor cambió, guardando...', { anterior: valorOriginal, nuevo: horas });
            guardarHorasDocenteClinico(idProfesoresCurso, rutDocente, horas, unidadAcademica, input);
        } else {
            console.log('➡️ Valor no cambió, solo actualizando estado visual');
            actualizarEstadoVisual(input, horas);
        }
    });
    
    // Event listener para cambios en tiempo real (solo visual)
    $(document).on('input', '.hours-input', function() {
        const horas = parseFloat(this.value) || 0;
        actualizarEstadoVisual(this, horas);
    });
    
    // Inicializar estados visuales para inputs existentes
    $('.hours-input').each(function() {
        const horas = parseFloat(this.value) || 0;
        actualizarEstadoVisual(this, horas);
    });
    
    console.log('✅ Setup de horas directas completado');
}

function actualizarEstadoVisual(input, horas) {
    $(input).removeClass('hours-zero hours-positive hours-saving hours-error');
    
    if (horas === 0) {
        $(input).addClass('hours-zero');      // ⚪ Gris para vacío
    } else if (horas > 0) {
        $(input).addClass('hours-positive');  // 🟢 Verde para valor
    }
}

function guardarHorasDocenteClinico(idProfesoresCurso, rutDocente, horas, unidadAcademica, inputElement) {
    console.log('💾 Iniciando guardado de horas...');
    
    // Obtener el ID del curso de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const idCurso = urlParams.get('curso');
    
    // Validar datos...
    if (!idProfesoresCurso || !rutDocente || !idCurso) {
        console.error('❌ Faltan datos obligatorios');
        mostrarToast('Error: Faltan datos del docente', 'danger');
        return;
    }
    
    // ✅ NUEVA FORMA: Agregar disquete como elemento real
    mostrarDisqueteGuardando(inputElement);
    
    $.ajax({
        url: 'guardar_horas_docente.php',
        type: 'POST',
        dataType: 'json',
        data: {
            idProfesoresCurso: idProfesoresCurso,
            rutDocente: rutDocente,
            idCurso: idCurso,
            horas: horas,
            unidadAcademica: unidadAcademica
        },
        success: function(response) {
            console.log('✅ Respuesta del servidor:', response);
            
            // ✅ QUITAR el disquete
            quitarDisqueteGuardando(inputElement);
            
            if (response.success) {
                // Actualizar valor original para futuras comparaciones
                $(inputElement).attr('data-original-value', horas);
                
                // ✅ APLICAR estado visual correcto
                actualizarEstadoVisual(inputElement, horas);
                
                // Toast discreto
                mostrarToastDiscretoClinico('💾 Horas guardadas', 'success');
                
            } else {
                $(inputElement).addClass('hours-error');
                console.error('❌ Error del servidor:', response);
                mostrarToast(response.message || 'Error al guardar las horas', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error AJAX:', error);
            
            // ✅ QUITAR disquete y mostrar error
            quitarDisqueteGuardando(inputElement);
            $(inputElement).addClass('hours-error');
            mostrarToast('Error de comunicación con el servidor', 'danger');
        }
    });
}

// ✅ NUEVAS FUNCIONES para manejar el disquete
function mostrarDisqueteGuardando(inputElement) {
    // Cambiar color del input a amarillo
    $(inputElement).removeClass('hours-zero hours-positive hours-error').addClass('hours-saving');
    
    // Remover disquete anterior si existe
    $(inputElement).siblings('.diskette-saving').remove();
    
    // Crear el disquete como elemento real
    const disquete = $(`
        <span class="diskette-saving" style="
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            z-index: 1000;
            pointer-events: none;
            color: #856404;
            animation: pulse 1s infinite;
        ">💾</span>
    `);
    
    // Asegurar que el contenedor tenga position relative
    const container = $(inputElement).closest('.input-group, td, .form-group');
    container.css('position', 'relative');
    
    // Agregar el disquete al contenedor
    container.append(disquete);
    
    console.log('💾 Disquete mostrado');
}

function quitarDisqueteGuardando(inputElement) {
    // Remover el disquete
    $(inputElement).siblings('.diskette-saving').remove();
    $(inputElement).closest('.input-group, td, .form-group').find('.diskette-saving').remove();
    
    // Quitar la clase de guardando
    $(inputElement).removeClass('hours-saving');
    
    console.log('💾 Disquete removido');
}



// Toast discreto para no molestar al usuario
function mostrarToastDiscretoClinico(mensaje, tipo = 'success') {
    // Solo mostrar si no hay otros toasts activos
    if ($('.toast-container .toast').length === 0) {
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
                        <i class="bi bi-check-circle me-2"></i>
                        ${mensaje}
                    </div>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(toastContainer.lastElementChild, {
            autohide: true,
            delay: 1500
        });
        toast.show();
        
        // Auto-limpiar
        setTimeout(() => {
            if (toastContainer.lastElementChild) {
                toastContainer.lastElementChild.remove();
            }
        }, 500);
    }
}

// Inicialización mejorada con debug
$(document).ready(function() {
    console.log('🌟 Documento listo, configurando manejo de docentes...');
    
    // Configurar el tab de docentes para inicializar las horas cuando se carga
    $('#docente-tab').on('shown.bs.tab', function() {
        console.log('📑 Pestaña de docentes mostrada, inicializando horas...');
        setTimeout(() => {
            setupHorasDirectasClinico();
        }, 500);
    });
    
    // También manejar el evento click para debug
    $('#docente-tab').on('click', function() {
        console.log('🖱️ Click en pestaña de docentes');
    });
    
    // Si ya estamos en la pestaña de docentes al cargar, inicializar
    if ($('#docente-tab').hasClass('active')) {
        console.log('📑 Pestaña de docentes ya activa al cargar');
        setTimeout(() => {
            setupHorasDirectasClinico();
        }, 500);
    }
	
	 setTimeout(function() {
        console.log('📊 Verificando inputs de horas después de cargar tabla...');
        $('.hours-input').each(function(index, input) {
            const $input = $(input);
            console.log(`🔍 Input ${index + 1}:`, {
                id: input.id,
                value: input.value,
                'data-id-profesor': $input.attr('data-id-profesor'),
                'data-rut': $input.attr('data-rut'),
                'data-unidad-academica': $input.attr('data-unidad-academica'),
                'data-original-value': $input.attr('data-original-value')
            });
            
            // Aplicar estilo inicial según el valor
            const horas = parseFloat(input.value) || 0;
            $input.removeClass('hours-zero hours-positive');
            if (horas === 0) {
                $input.addClass('hours-zero');
            } else if (horas > 0) {
                $input.addClass('hours-positive');
            }
        });
        
        console.log(`✅ ${$('.hours-input').length} inputs de horas inicializados`);
    }, 500);
});

// Función para recargar la tabla y reinicializar event listeners

function reloadDocentesTableWithHours() {
    console.log('🔄 Recargando tabla de docentes...');
    
    // ✅ CAMBIO AQUÍ: Usar el contenedor completo, no solo el tbody
    const docentesContainer = document.querySelector('#docentes-list');
    
    if (!docentesContainer) {
        console.error('❌ No se encontró el contenedor de docentes');
        return;
    }

    $.ajax({
        url: 'get_docentes_table_clinico.php',
        type: 'GET',
        data: {
            idcurso: new URLSearchParams(window.location.search).get('curso')
        },
        success: function(html) {
            console.log('✅ Tabla de docentes recargada exitosamente');
            
            // ✅ CAMBIO AQUÍ: Reemplazar todo el contenido
            $(docentesContainer).html(html);
            
            // Reinicializar event listeners después de recargar
            setTimeout(() => {
                setupHorasDirectasClinico();
                
                // ✅ AGREGAR: También reinicializar el buscador
                if (typeof inicializarBusquedaDocentesClinico === 'function') {
                    inicializarBusquedaDocentesClinico();
                }
            }, 500); // Un poco más de tiempo para que se renderice
        },
        error: function(xhr, status, error) {
            console.error('❌ Error al recargar tabla:', { status, error });
            $(docentesContainer).html(`
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error al cargar los datos: ${error}
                </div>
            `);
        }
    });
}
</script>

<script>
// Guardar el cupo del curso para acceder fácilmente
const cupoCurso = <?php echo $cupoCurso; ?>;
let datosSeccionesCache = null; // Para guardar info de secciones

// Función para calcular los alumnos por sala (redondea hacia arriba sin decimales)
function calcularAlumnosPorSala() {
    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
    // Usar Math.ceil para redondear hacia arriba sin decimales
    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
    document.getElementById('alumnosPorSala').value = alumnosPorSala;
    
    console.log(`📊 Cálculo: ${totalAlumnos} alumnos ÷ ${nSalas} salas = ${alumnosPorSala} por sala`);
}

async function solicitarSala(idPlanClase) {
    console.log('=== INICIANDO SOLICITAR SALA ===');
    console.log('ID Plan Clase:', idPlanClase);
    
    document.getElementById('salaForm').reset();
    document.getElementById('idplanclases').value = idPlanClase;
    document.getElementById('action').value = 'solicitar';
    document.getElementById('salaModalTitle').textContent = 'Solicitar Sala';
    
    // Establecer el número de alumnos totales según el cupo del curso
    document.getElementById('alumnosTotales').value = cupoCurso;
    document.getElementById('alumnosTotales').readOnly = true;
    
    // NUEVO: Verificar si debe mostrar opción de juntar secciones
    await verificarYMostrarOpciones(idPlanClase);
    
    // Calcular alumnos por sala inicialmente
    calcularAlumnosPorSala();
    
    // Agregar evento para recalcular cuando cambie el número de salas
    const nSalasSelect = document.getElementById('nSalas');
    nSalasSelect.addEventListener('change', calcularAlumnosPorSala);
    
    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
}

// FUNCIÓN MODIFICADA: modificarSala - ahora verifica secciones
async function modificarSala(idPlanClase) {
    console.log('=== INICIANDO MODIFICAR SALA ===');
    console.log('ID Plan Clase:', idPlanClase);
    
    document.getElementById('salaForm').reset();
    document.getElementById('idplanclases').value = idPlanClase;
    document.getElementById('salaModalTitle').textContent = 'Modificar Solicitud de Sala';
    
    // Establecer el número de alumnos totales según el cupo del curso
    document.getElementById('alumnosTotales').value = cupoCurso;
    document.getElementById('alumnosTotales').readOnly = true;
    
    try {
        // Obtener datos de la solicitud existente
        const response = await fetch('salas_clinico.php', {
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
    // Determinar la acción según el estado
    document.getElementById('action').value = datos.estado === 3 ? 'modificar_asignada' : 'modificar';
    
    // ✅ LLENAR CAMPOS BÁSICOS
    document.getElementById('campus').value = datos.pcl_campus || '';
    document.getElementById('nSalas').value = datos.pcl_nSalas || 1;
    document.getElementById('textoObservacionesHistoricas').textContent = datos.observaciones || 'Sin observaciones previas.';
    
    // ✅ CONFIGURAR MOVILIDAD REDUCIDA
    if (datos.movilidadReducida) {
        document.getElementById('movilidadReducida').value = datos.movilidadReducida;
    }
    
    // ✅ CONFIGURAR ALUMNOS CORRECTAMENTE
    document.getElementById('alumnosTotales').value = datos.pcl_alumnos || cupoCurso;
    document.getElementById('alumnosPorSala').value = datos.alumnosPorSala || datos.pcl_alumnos || cupoCurso;
    
    // ✅ VERIFICAR Y MOSTRAR OPCIONES DE SECCIONES
    await verificarYMostrarOpciones(idPlanClase);
    
    // ✅ CONFIGURAR CHECKBOX JUNTAR SECCIONES (después de verificar opciones)
    if (datos.juntarSecciones) {
        const checkboxJuntar = document.getElementById('juntarSecciones');
        if (checkboxJuntar) {
            checkboxJuntar.checked = true;
            
            // Disparar el evento change para que se ejecute recalcularAlumnos()
            const event = new Event('change', { bubbles: true });
            checkboxJuntar.dispatchEvent(event);
        }
    }
    
    console.log('✅ Datos cargados:', {
        campus: datos.pcl_campus,
        nSalas: datos.pcl_nSalas,
        alumnosTotales: datos.pcl_alumnos,
        alumnosPorSala: datos.alumnosPorSala,
        juntarSecciones: datos.juntarSecciones,
        movilidadReducida: datos.movilidadReducida
    });
}
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error al cargar los datos de la sala', 'danger');
    }
    
    // NUEVO: Verificar si debe mostrar opción de juntar secciones
    await verificarYMostrarOpciones(idPlanClase);
    
    // Calcular alumnos por sala inicialmente
    calcularAlumnosPorSala();
    
    // Agregar evento para recalcular cuando cambie el número de salas
    const nSalasSelect = document.getElementById('nSalas');
    nSalasSelect.addEventListener('change', calcularAlumnosPorSala);
    
    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
}

// NUEVA FUNCIÓN: Verificar si mostrar opción de juntar secciones
async function verificarYMostrarOpciones(idPlanClase) {
    try {
        console.log('🔍 Verificando opciones de secciones para:', idPlanClase);
        
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'verificar_secciones',
                idPlanClase: idPlanClase
            })
        });
        
        const datos = await response.json();
        console.log('📋 Respuesta verificar_secciones:', datos);
        
        datosSeccionesCache = datos; // Guardar para uso posterior
        
        const opcionDiv = document.getElementById('opcionJuntarSecciones');
        const infoSecciones = document.getElementById('infoSecciones');
        
        if (datos.success && datos.mostrarOpcion) {
            // Mostrar la opción porque es sección 1 con múltiples secciones
            infoSecciones.textContent = `${datos.totalSecciones} secciones disponibles - Total: ${datos.cupoTotal} alumnos`;
            opcionDiv.style.display = 'block';
            console.log('✅ Mostrando opción de juntar secciones');
        } else {
            // Ocultar la opción
            opcionDiv.style.display = 'none';
            console.log('❌ Ocultando opción de juntar secciones');
        }
        
    } catch (error) {
        console.error('❌ Error verificando secciones:', error);
        // En caso de error, ocultar la opción
        const opcionDiv = document.getElementById('opcionJuntarSecciones');
        if (opcionDiv) opcionDiv.style.display = 'none';
    }
}

// Función para recalcular alumnos cuando se cambia la opción de juntar secciones
function recalcularAlumnos() {
    console.log("🔄 Recalculando alumnos...");
    
    const juntarSecciones = document.getElementById('juntarSecciones').checked;
    const alumnosTotalesInput = document.getElementById('alumnosTotales');
    
    if (juntarSecciones && datosSeccionesCache) {
        // Si está marcado juntar secciones, usar el cupo total
        alumnosTotalesInput.value = datosSeccionesCache.cupoTotal;
        console.log(`✅ Juntando secciones: ${datosSeccionesCache.cupoTotal} alumnos totales`);
    } else {
        // Si no está marcado, usar el cupo individual del curso
        alumnosTotalesInput.value = cupoCurso;
        console.log(`✅ Sección individual: ${cupoCurso} alumnos totales`);
    }
    
    // Recalcular alumnos por sala
    calcularAlumnosPorSala();
}

// Función para calcular los alumnos por sala (redondea hacia arriba sin decimales)
function calcularAlumnosPorSala() {
    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
    // Usar Math.ceil para redondear hacia arriba sin decimales
    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
    document.getElementById('alumnosPorSala').value = alumnosPorSala;
    
    console.log(`📊 Cálculo: ${totalAlumnos} alumnos ÷ ${nSalas} salas = ${alumnosPorSala} por sala`);
}

// NUEVA FUNCIÓN: Recalcular alumnos según checkbox
//function recalcularAlumnos() {
//    const checkbox = document.getElementById('juntarSecciones');
//    const alumnosTotalesInput = document.getElementById('alumnosTotales');
//    
//    if (checkbox.checked && datosSeccionesCache && datosSeccionesCache.cupoTotal) {
//        // Usar cupo total de todas las secciones
//        alumnosTotalesInput.value = datosSeccionesCache.cupoTotal;
//        console.log(`🔄 Juntando secciones: ${datosSeccionesCache.cupoTotal} alumnos`);
//        mostrarNotificacion(`Juntando ${datosSeccionesCache.totalSecciones} secciones - Total: ${datosSeccionesCache.cupoTotal} alumnos`, 'info');
//    } else {
//        // Usar cupo individual del curso
//        alumnosTotalesInput.value = cupoCurso;
//        console.log(`🔄 Sección individual: ${cupoCurso} alumnos`);
//    }
//    
//    // Recalcular alumnos por sala
//    calcularAlumnosPorSala();
//}

async function mostrarModalLiberarSalas(idPlanClase) {
    try {
        // Obtener las salas asignadas
        const response = await fetch('salas_clinico.php', {
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
        
        if (datos.success && datos.salas && datos.salas.length > 0) {
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
            showNotification('No hay salas asignadas para liberar', 'warning');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al cargar las salas asignadas', 'danger');
    }
}

async function liberarSala(idAsignacion) {
    if (!confirm('¿Está seguro que desea liberar esta sala?')) {
        return;
    }
    
    try {
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'liberar',
                idAsignacion: idAsignacion
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Cerrar el modal
            const modalLiberar = bootstrap.Modal.getInstance(document.getElementById('liberarSalaModal'));
            if (modalLiberar) modalLiberar.hide();
            
            showNotification('Sala liberada correctamente', 'success');
            
setTimeout(() => {
                recargarTablaSalasClinico();
            }, 500);
			
        } else {
            showNotification('Error al liberar la sala', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'danger');
    }
}

// Función mejorada de guardarSala() para depuración
async function guardarSala() {
    const form = document.getElementById('salaForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Recopilar datos del formulario
    const formData = new FormData(form);
    const datos = Object.fromEntries(formData.entries());
    
    // NUEVO: Agregar información sobre juntar secciones
    const juntarCheckbox = document.getElementById('juntarSecciones');
    if (juntarCheckbox && juntarCheckbox.checked) {
        datos.juntarSecciones = '1';
        datos.alumnosTotales = document.getElementById('alumnosTotales').value;
        if (datosSeccionesCache) {
            datos.totalSecciones = datosSeccionesCache.totalSecciones;
            datos.cupoTotal = datosSeccionesCache.cupoTotal;
        }
    }
    
    // Mostrar un indicador de carga
    mostrarNotificacion('Procesando solicitud...', 'info');
    
    try {
        console.log('📤 Datos a enviar:', JSON.stringify(datos, null, 2));
        
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(datos)
        });
        
        if (!response.ok) {
            const responseText = await response.text();
            console.error('Error en la respuesta:', responseText);
            throw new Error(`Error del servidor: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('📥 Respuesta (texto):', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parseando JSON:', parseError);
            throw new Error('La respuesta no es un JSON válido');
        }
        
        if (data.success) {
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarNotificacion('Solicitud procesada correctamente', 'success');
            
            // Recargar la página para ver los cambios
           setTimeout(() => {
    recargarTablaSalasClinico();
}, 500);
        } else {
            throw new Error(data.error || 'Error desconocido del servidor');
        }
    } catch (error) {
        console.error('❌ Error completo:', error);
        mostrarNotificacion(`Error: ${error.message}`, 'danger');
    }
}

function showNotification(message, type = 'success') {
    // Crear o utilizar un contenedor para las notificaciones
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    // Crear el toast
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'x-circle'}"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    // Añadir el toast al contenedor
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    // Inicializar y mostrar el toast
    const toastElement = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: 3000
    });
    toastElement.show();
    
    // Eliminar el toast del DOM después de ocultarse
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

let diasCurso = <?php echo $diasCursoJson; ?>; // ej: ["L", "J"]

// Mapeo simple de letras a nombres
const nombresDias = {
    'L': 'Lunes',
    'M': 'Martes', 
    'X': 'Miércoles',
    'J': 'Jueves',
    'V': 'Viernes'
};

// Mapeo de letras a números (0=Domingo, 1=Lunes, etc.)
const numerosDias = {
    'L': 1, 'M': 2, 'X': 3, 'J': 4, 'V': 5
};

// Función simple para validar día
function validarDiaClase() {
    const dateInput = document.getElementById('activity-date');
    const fechaSeleccionada = dateInput.value;
    
    // Si no hay restricciones o no hay fecha, no validar
    if (!diasCurso || diasCurso.length === 0 || !fechaSeleccionada) {
        return;
    }
    
    const fechaObj = new Date(fechaSeleccionada);
    const diaSemana = fechaObj.getDay(); // 0=Domingo, 1=Lunes, etc.
    
    // Verificar si el día está permitido
    const diaPermitido = diasCurso.some(letra => numerosDias[letra] === diaSemana);
    
    if (!diaPermitido) {
        // Crear lista de días permitidos
        const diasPermitidos = diasCurso.map(letra => nombresDias[letra]).join(', ');
        
        // Mostrar advertencia simple
        mostrarToast(`⚠️ Este curso tiene clases los días: ${diasPermitidos}`, 'warning');
    }
}

// Función para eliminar docente
function eliminarDocente(id) {
    if(!id) return;
    
    // SweetAlert2 - Confirmación elegante
    Swal.fire({
        title: '¿Eliminar docente?',
        html: `
            <div class="text-center">
                <i class="bi bi-person-x text-danger" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p class="mb-0">Esta acción removerá al docente del equipo del curso.</p>
                <small class="text-muted">Esta acción no se puede deshacer.</small>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="bi bi-x-circle me-2"></i>Cancelar',
        buttonsStyling: true,
        customClass: {
            confirmButton: 'btn btn-danger px-4',
            cancelButton: 'btn btn-secondary px-4'
        },
        reverseButtons: true, // Pone "Cancelar" a la izquierda
        focusCancel: true // Focus en cancelar por seguridad
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading mientras se procesa
            Swal.fire({
                title: 'Eliminando docente...',
                html: '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Por favor espere...</p></div>',
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false
            });
            
            // Realizar la eliminación
            $.ajax({
                url: 'eliminar_docente.php',
                type: 'POST',
                data: { idProfesoresCurso: id },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        // Eliminar la fila con animación
                        var $btn = $(`button[onclick="eliminarDocente(${id})"]`);
                        var $row = $btn.closest('tr');
                        
                        // Éxito con SweetAlert2
                        Swal.fire({
                            title: '¡Eliminado!',
                            html: `
                                <div class="text-center">
                                    <i class="bi bi-check-circle text-success" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                    <p class="mb-0">El docente ha sido removido del equipo exitosamente.</p>
                                </div>
                            `,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                            timerProgressBar: true
                        });
                        
                        // Animar la eliminación de la fila
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                    } else {
                        // Error del servidor
                        Swal.fire({
                            title: 'Error al eliminar',
                            html: `
                                <div class="text-center">
                                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                    <p class="mb-0">${response.message || 'Ocurrió un error al eliminar el docente.'}</p>
                                    <small class="text-muted">Intente nuevamente o contacte al administrador.</small>
                                </div>
                            `,
                            icon: 'error',
                            confirmButtonText: '<i class="bi bi-arrow-clockwise me-2"></i>Entendido',
                            confirmButtonColor: '#0d6efd'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Error de comunicación
                    Swal.fire({
                        title: 'Error de conexión',
                        html: `
                            <div class="text-center">
                                <i class="bi bi-wifi-off text-danger" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                <p class="mb-0">No se pudo conectar con el servidor.</p>
                                <small class="text-muted">Verifique su conexión a internet e intente nuevamente.</small>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonText: '<i class="bi bi-arrow-clockwise me-2"></i>Reintentar',
                        confirmButtonColor: '#0d6efd'
                    });
                }
            });
        }
    });
}

function inicializarBusquedaDocentesClinico() {
    console.log('🔧 Inicializando búsqueda amigable de docentes clínicos...');
    
    // Verificar que el select existe
    if (!$('#docente').length) {
        console.warn('⚠️ Select #docente no encontrado');
        return;
    }
    
    // Limpiar inicializaciones previas
    if ($('#docente').hasClass('select2-hidden-accessible')) {
        $('#docente').select2('destroy');
    }
    
    // ===== FUNCIÓN MATCHER PERSONALIZADA =====
    function matcherAmigableClinico(params, data) {
        // Si no hay término de búsqueda, mostrar todo
        if ($.trim(params.term) === '') {
            return data;
        }
        
        // Si no hay texto para comparar, saltear
        if (typeof data.text === 'undefined') {
            return null;
        }
        
        // Normalizar el término de búsqueda (más permisivo que antes)
        const searchTerm = normalizarTextoClinico(params.term.trim());
        const searchWords = searchTerm.split(/\s+/).filter(word => word.length > 0);
        
        // Normalizar el texto del docente
        const docenteText = normalizarTextoClinico(data.text);
        
        // Debug para caso específico "antonio arias"
        if (searchTerm.includes('antonio') && searchTerm.includes('arias')) {
            console.log('🔍 DEBUG Antonio Arias:', {
                original: data.text,
                normalized: docenteText,
                searchWords: searchWords,
                searchTerm: searchTerm
            });
        }
        
        // Verificar si TODOS los términos están presentes (búsqueda Y)
        const todasLasPalabrasEncontradas = searchWords.every(word => {
            const found = docenteText.includes(word);
            
            // Debug adicional para términos específicos
            if ((word === 'antonio' || word === 'arias') && data.text.toLowerCase().includes('antonio')) {
                console.log(`   → Buscando "${word}" en "${docenteText}": ${found}`);
            }
            
            return found;
        });
        
        if (todasLasPalabrasEncontradas) {
            // Crear una copia modificada para resaltar
            const modifiedData = $.extend({}, data, true);
            
            // Resaltar términos encontrados
            let highlightedText = data.text;
            searchWords.forEach(word => {
                // Crear regex flexible para encontrar el término en el texto original
                const regex = new RegExp(`(${escapeRegExpClinico(word)})`, 'gi');
                highlightedText = highlightedText.replace(regex, '<strong>$1</strong>');
            });
            
            modifiedData.text = highlightedText;
            return modifiedData;
        }
        
        return null;
    }
    
    // ===== FUNCIÓN PARA NORMALIZAR TEXTO =====
    function normalizarTextoClinico(texto) {
        return texto
            .toLowerCase()
            .normalize('NFD')                    // Descomponer caracteres acentuados
            .replace(/[\u0300-\u036f]/g, '')     // Eliminar acentos
            .replace(/[^a-z0-9\s]/g, '')         // Solo letras, números y espacios
            .replace(/\s+/g, ' ')                // Espacios múltiples a uno solo
            .trim();                             // Eliminar espacios al inicio/final
    }
    
    // ===== FUNCIÓN HELPER PARA REGEX =====
    function escapeRegExpClinico(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // ===== CONFIGURACIÓN SELECT2 CON BÚSQUEDA AMIGABLE =====
    $('#docente').select2({
        theme: 'bootstrap-5',
        placeholder: '🔍 Escriba nombre o RUT para buscar',
        allowClear: true,
        minimumInputLength: 0,              // ⚡ Buscar desde el primer carácter
        matcher: matcherAmigableClinico,    // 🧠 Usar nuestro matcher inteligente
        
        language: {
            noResults: function() {
                return '<div class="text-center p-2"><i class="bi bi-search"></i><br>No se encontraron docentes</div>';
            },
            searching: function() {
                return '<div class="text-center p-2"><i class="bi bi-hourglass-split"></i><br>Buscando...</div>';
            }
        },
        
        width: '100%',
        dropdownParent: $('#docente').parent(),
        
        // ===== CONFIGURACIÓN DE TEMPLATES =====
        templateResult: function(docente) {
            if (docente.loading) {
                return docente.text;
            }
            
            // Permitir HTML para el resaltado
            return $('<div>').html(docente.text);
        },
        
        templateSelection: function(docente) {
            // Para la selección, mostrar sin HTML
            return docente.text ? docente.text.replace(/<[^>]*>/g, '') : docente.id;
        },
        
        escapeMarkup: function(markup) {
            return markup; // Permitir HTML para resaltado
        }
    });
    
    // ===== EVENT LISTENERS =====
    
    // Mostrar información de resultados
    $('#docente').on('select2:open', function() {
        console.log('📂 Dropdown abierto');
        setTimeout(() => {
            mostrarInfoResultadosClinico();
        }, 500);
    });
    
    // Actualizar info cuando se escriba
    $('#docente').on('keyup', function() {
        setTimeout(() => {
            mostrarInfoResultadosClinico();
        }, 500);
    });
    
    // Limpiar información al cerrar
    $('#docente').on('select2:close', function() {
        const searchInfo = document.getElementById('search-info');
        if (searchInfo) {
            searchInfo.style.display = 'none';
        }
    });
    
    // Habilitar/deshabilitar botón según la selección
    $('#docente').off('change.docenteClinico').on('change.docenteClinico', function() {
        const isSelected = $(this).val();
        $('#boton_agregar').prop('disabled', !isSelected);
        
        if (isSelected) {
            console.log('✅ Docente seleccionado:', isSelected);
        }
    });
    
    // ===== CONFIGURAR BOTÓN DE AGREGAR DOCENTE =====
    $('#boton_agregar').off('click.docenteClinico').on('click.docenteClinico', function() {
        console.log('🎯 Click en botón agregar docente');
        
        let rut_docente = $('#docente').val();
        if (!rut_docente) {
            console.warn('⚠️ No hay docente seleccionado');
            return;
        }
        
        console.log('📤 Asignando docente:', rut_docente);
        
        // Deshabilitar botón durante la operación
        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Asignando...');
        
        // Obtener el ID del curso de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const idCurso = urlParams.get('curso');
        
        $.ajax({
            url: 'asignar_docente.php',
            type: 'POST',
            dataType: 'json',
            data: {
                rut_docente: rut_docente,
                idcurso: idCurso,
                funcion: 5 // Función por defecto (Colaborador)
            },
            success: function(response) {
                console.log('📥 Respuesta asignar docente:', response);
                
                // Restaurar botón
                $('#boton_agregar').prop('disabled', false).html('<i class="bi bi-plus-circle"></i> Asignar Docente');
                
                if (response.success) {
                    // Limpiar selección
                    $('#docente').val(null).trigger('change');
                    const searchInfo = document.getElementById('search-info');
                    if (searchInfo) {
                        searchInfo.style.display = 'none';
                    }
                    
                    // Mostrar notificación
                    if (typeof mostrarToast === 'function') {
                        mostrarToast('Docente asignado correctamente', 'success');
                    } else if (typeof showNotification === 'function') {
                        showNotification('Docente asignado correctamente', 'success');
                    } else {
                        alert('Docente asignado correctamente');
                    }
                    
                    // Recargar tabla de docentes
                    if (typeof reloadDocentesTableWithHours === 'function') {
                        setTimeout(() => reloadDocentesTableWithHours(), 500);
                    } else {
                        setTimeout(() => location.reload(), 500);
                    }
					
					 setTimeout(() => {
        recargarSoloTablaDocentes();
    }, 500);
	
                } else {
                    const errorMsg = response.message || 'Error al asignar docente';
                    if (typeof mostrarToast === 'function') {
                        mostrarToast(errorMsg, 'danger');
                    } else if (typeof showNotification === 'function') {
                        showNotification(errorMsg, 'danger');
                    } else {
                        alert(errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Error asignando docente:', { status, error, responseText: xhr.responseText });
                
                // Restaurar botón
                $('#boton_agregar').prop('disabled', false).html('<i class="bi bi-plus-circle"></i> Asignar Docente');
                
                if (typeof mostrarToast === 'function') {
                    mostrarToast('Error de comunicación con el servidor', 'danger');
                } else if (typeof showNotification === 'function') {
                    showNotification('Error de comunicación con el servidor', 'danger');
                } else {
                    alert('Error de comunicación con el servidor');
                }
            }
        });
    });
    
    // ===== CONFIGURAR BOTÓN NUEVO DOCENTE =====
    $('#nuevo-docente-btn').off('click.docenteClinico').on('click.docenteClinico', function() {
        console.log('🆕 Click en nuevo docente');
        const urlParams = new URLSearchParams(window.location.search);
        const idCurso = urlParams.get('curso');
        //window.location.href = `2_crear_docente.php?idcurso=${idCurso}`;
    });
    
    console.log('✅ Búsqueda amigable de docentes clínicos inicializada completamente');
}

// ===== FUNCIÓN PARA MOSTRAR INFO DE RESULTADOS =====
function mostrarInfoResultadosClinico() {
    const searchInput = $('.select2-search__field').val();
    const resultados = $('.select2-results__option').not('.select2-results__option--no-results').length;
    
    const searchInfo = document.getElementById('search-info');
    const resultsCount = document.getElementById('search-results-count');
    
    if (searchInfo && resultsCount && searchInput && searchInput.length > 0 && resultados > 0) {
        resultsCount.textContent = `${resultados} docentes encontrados`;
        searchInfo.style.display = 'block';
    } else if (searchInfo) {
        searchInfo.style.display = 'none';
    }
}

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


// En la función guardar_docente(), cambiar esta sección:
success: function(respuesta) {
    // Restaurar botón
    $btnGuardar.prop('disabled', false).html(textoOriginal);
    
    if(respuesta.success) {
        console.log('✅ Docente guardado exitosamente');
        
        // Mostrar notificación de éxito
        mostrarToast('Docente agregado correctamente', 'success');
        
        // ===== CAMBIO AQUÍ: USAR EL MISMO MÉTODO QUE PARA ASIGNAR DOCENTE =====
        setTimeout(() => {
            console.log('🔄 Recargando tabla de docentes...');
            
            // 1. Primero intentar reloadDocentesTableWithHours
            if (typeof reloadDocentesTableWithHours === 'function') {
                reloadDocentesTableWithHours();
                console.log('✅ Usando reloadDocentesTableWithHours');
            } else {
                // 2. Fallback: usar recargarSoloTablaDocentes
                if (typeof recargarSoloTablaDocentes === 'function') {
                    recargarSoloTablaDocentes();
                    console.log('✅ Usando recargarSoloTablaDocentes');
                } else {
                    console.log('❌ Funciones no disponibles, recargando página');
                    location.reload();
                }
            }
        }, 500);
        
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

// ===== FUNCIONES PARA RECARGAR SOLO LA TABLA DE DOCENTES =====

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

function showSpinnerInElement(element) {
    element.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Actualizando...</span>
            </div>
            <p class="mt-2">Actualizando equipo docente...</p>
        </div>
    `;
}

//function fetchAndUpdateTable(container) {
//    const urlParams = new URLSearchParams(window.location.search);
//    const idCurso = urlParams.get('curso');
//    
//    // ✅ USAR EL ARCHIVO CORREGIDO CON FUNCIONALIDAD DE CURSOS CLÍNICOS
//    fetch('get_docentes_table_only.php?idcurso=' + idCurso)
//        .then(response => {
//            if (!response.ok) {
//                throw new Error(`HTTP error! status: ${response.status}`);
//            }
//            return response.text();
//        })
//        .then(html => {
//            console.log('✅ Tabla de docentes clínicos actualizada');
//            
//            // Reemplazar el contenido de la tabla
//            container.outerHTML = html;
//            
//            // ✅ REINICIALIZAR LA FUNCIONALIDAD DE HORAS DIRECTAS
//            setTimeout(() => {
//                if (typeof setupHorasDirectasClinico === 'function') {
//                    setupHorasDirectasClinico();
//                    console.log('✅ Funcionalidad de horas directas reinicializada');
//                } else {
//                    console.warn('⚠️ setupHorasDirectasClinico no está disponible');
//                }
//            }, 500);
//        })
//        .catch(error => {
//            console.error('❌ Error al actualizar tabla:', error);
//            container.innerHTML = `
//                <div class="alert alert-danger m-3">
//                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
//                    Error al cargar los datos: ${error.message}
//                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="location.reload()">
//                        <i class="bi bi-arrow-clockwise"></i> Recargar página
//                    </button>
//                </div>
//            `;
//        });
//}

// Hacer las funciones accesibles globalmente
window.recargarSoloTablaDocentes = recargarSoloTablaDocentes;


// ===== FUNCIÓN PARA INICIALIZAR ESTILOS DE INPUTS DE HORAS =====
function inicializarEstilosHorasClinico() {
    console.log('🎨 Inicializando estilos de inputs de horas clínicos...');
    
    $('.hours-input').each(function(index, input) {
        const $input = $(input);
        
        // Debug: verificar datos del input
        console.log(`🔍 Input ${index + 1}:`, {
            id: input.id,
            value: input.value,
            'data-id-profesor': $input.attr('data-id-profesor'),
            'data-rut': $input.attr('data-rut'),
            'data-unidad-academica': $input.attr('data-unidad-academica'),
            'data-original-value': $input.attr('data-original-value')
        });
        
        // Aplicar estilo inicial según el valor
        const horas = parseFloat(input.value) || 0;
        $input.removeClass('hours-zero hours-positive hours-saving hours-error');
        
        if (horas === 0) {
            $input.addClass('hours-zero');
        } else if (horas > 0) {
            $input.addClass('hours-positive');
        }
    });
    
    console.log(`✅ ${$('.hours-input').length} inputs de horas inicializados con estilos`);
}

// Hacer la función accesible globalmente
window.inicializarEstilosHorasClinico = inicializarEstilosHorasClinico;

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

// nuevas funcionalidades

// ===== VARIABLES GLOBALES =====

let actividadActualInconsistencia = null;

// ===== FUNCIONES PRINCIPALES =====

function solicitarSala(idPlanClase) {
    cargarDatosSolicitud(idPlanClase, 'solicitar');
}

//function modificarSala(idPlanClase) {
//    cargarDatosSolicitud(idPlanClase, 'modificar');
//}

async function cargarDatosSolicitud(idPlanClase, action) {
    try {
        // Resetear cache de secciones
        datosSeccionesCache = null;
        
        // Determinar la acción correcta según el estado
        let actionToUse = action;
        if (action === 'modificar') {
            const response = await fetch('salas_clinico.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'obtener_datos_solicitud',
                    idPlanClase: idPlanClase
                })
            });
            
            const data = await response.json();
            if (data.estado === 3) {
                actionToUse = 'modificar_asignada';
            }
        }
        
        // Configurar modal
        document.getElementById('idplanclases').value = idPlanClase;
        document.getElementById('action').value = actionToUse;
        document.getElementById('salaModalTitle').textContent = 
            actionToUse === 'solicitar' ? 'Solicitar Sala' : 'Modificar Sala';
        
        // Verificar secciones disponibles
        await verificarSecciones(idPlanClase);
        
        // Cargar datos existentes si es modificación
        if (actionToUse !== 'solicitar') {
            await cargarDatosExistentes(idPlanClase);
        } else {
            // Para solicitudes nuevas, cargar cupo del curso
            await cargarCupoCurso(idPlanClase);
        }
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('salaModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error al cargar los datos de la solicitud', 'danger');
    }
}

async function verificarSecciones(idPlanClase) {
    try {
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'verificar_secciones',
                idPlanClase: idPlanClase
            })
        });
        
        const data = await response.json();
        const opcionDiv = document.getElementById('opcionJuntarSecciones');
        
        if (data.mostrarOpcion) {
            datosSeccionesCache = {
                totalSecciones: data.totalSecciones,
                cupoTotal: data.cupoTotal,
                seccionActual: data.seccionActual
            };
            
            document.getElementById('infoSecciones').innerHTML = 
                `Este curso tiene <strong>${data.totalSecciones} secciones</strong> con un total de <strong>${data.cupoTotal} alumnos</strong>. 
                 Actualmente está viendo la sección <strong>${data.seccionActual}</strong>.`;
            
            opcionDiv.style.display = 'block';
        } else {
            opcionDiv.style.display = 'none';
        }
    } catch (error) {
        console.error('Error verificando secciones:', error);
        document.getElementById('opcionJuntarSecciones').style.display = 'none';
    }
}

async function cargarCupoCurso(idPlanClase) {
    try {
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'obtener_cupo_curso',
                idPlanClase: idPlanClase
            })
        });
        
        const data = await response.json();
        if (data.cupo) {
            document.getElementById('alumnosTotales').value = data.cupo;
            calcularAlumnosPorSala();
        }
    } catch (error) {
        console.error('Error cargando cupo:', error);
    }
}

async function cargarDatosExistentes(idPlanClase) {
    try {
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'obtener_datos_solicitud',
                idPlanClase: idPlanClase
            })
        });
        
        const data = await response.json();
        if (data.success) {
            document.getElementById('campus').value = data.pcl_campus || '';
            document.getElementById('nSalas').value = data.pcl_nSalas || 1;
            document.getElementById('alumnosTotales').value = data.pcl_alumnos || 0;
            
            // Mostrar observaciones históricas
            const textoObservaciones = document.getElementById('textoObservacionesHistoricas');
            if (data.observaciones && data.observaciones.trim() !== '') {
                textoObservaciones.textContent = data.observaciones;
            } else {
                textoObservaciones.textContent = 'No hay observaciones previas registradas.';
            }
            
            calcularAlumnosPorSala();
        }
    } catch (error) {
        console.error('Error cargando datos existentes:', error);
    }
}

//function recalcularAlumnos() {
//    const juntarCheckbox = document.getElementById('juntarSecciones');
//    const alumnosTotalesInput = document.getElementById('alumnosTotales');
//    
//    if (juntarCheckbox.checked && datosSeccionesCache) {
//        alumnosTotalesInput.value = datosSeccionesCache.cupoTotal;
//    } else {
//        // Recargar cupo original del curso
//        const idPlanClase = document.getElementById('idplanclases').value;
//        cargarCupoCurso(idPlanClase);
//    }
//    calcularAlumnosPorSala();
//}
//
//function calcularAlumnosPorSala() {
//    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
//    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
//    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
//    document.getElementById('alumnosPorSala').value = alumnosPorSala;
//}

async function guardarSala() {
    const form = document.getElementById('salaForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const datos = Object.fromEntries(formData.entries());
    
    // Agregar información sobre juntar secciones
    const juntarCheckbox = document.getElementById('juntarSecciones');
    if (juntarCheckbox && juntarCheckbox.checked) {
        datos.juntarSecciones = '1';
        datos.alumnosTotales = document.getElementById('alumnosTotales').value;
        if (datosSeccionesCache) {
            datos.totalSecciones = datosSeccionesCache.totalSecciones;
            datos.cupoTotal = datosSeccionesCache.cupoTotal;
        }
    }
    
    mostrarNotificacion('Procesando solicitud...', 'info');
    
    try {
        console.log('📤 Datos a enviar:', JSON.stringify(datos, null, 2));
        
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        
        if (!response.ok) {
            const responseText = await response.text();
            console.error('Error en la respuesta:', responseText);
            throw new Error(`Error del servidor: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('📥 Respuesta (texto):', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parseando JSON:', parseError);
            throw new Error(`Respuesta inválida del servidor. Detalles: ${responseText.substring(0, 200)}`);
        }
        
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarNotificacion(data.message || 'Solicitud procesada correctamente', 'success');
            setTimeout(() => {
    recargarTablaSalasClinico();
}, 500);
        } else {
            throw new Error(data.error || 'Error desconocido del servidor');
        }
    } catch (error) {
        console.error('Error completo:', error);
        mostrarNotificacion(`Error: ${error.message}`, 'danger');
    }
}

// ===== NUEVAS FUNCIONES PARA GESTIÓN DE RESERVAS =====

async function mostrarModalLiberarSalas(idPlanClase) {
    try {
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'obtener_salas_asignadas',
                idPlanClase: idPlanClase
            })
        });
        
        const datos = await response.json();
        
        if (datos.salas && datos.salas.length > 0) {
            const tbody = document.getElementById('listaSalasAsignadas');
            tbody.innerHTML = '';
            
            datos.salas.forEach(sala => {
                // Determinar icono y clase según estado de reserva
                let iconoReserva = '';
                let claseReserva = '';
                let textoReserva = '';
                
                switch(sala.estadoReserva) {
                    case 'confirmada':
                        iconoReserva = '✓';
                        claseReserva = 'text-success';
                        textoReserva = 'Reserva confirmada';
                        break;
                    case 'encontrada_alt':
                        iconoReserva = '🔍';
                        claseReserva = 'text-warning';
                        textoReserva = 'Reserva encontrada (alt)';
                        break;
                    case 'sin_reserva':
                    default:
                        iconoReserva = '❌';
                        claseReserva = 'text-danger';
                        textoReserva = 'Sin reserva';
                        break;
                }
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${sala.idSala}</strong></td>
                    <td>
                        <span class="${claseReserva}">${iconoReserva} ${textoReserva}</span>
                        <br><small class="text-muted">${sala.detalleVerificacion}</small>
                    </td>
                    <td>
                        <button class="btn btn-danger btn-sm" 
                                onclick="liberarSala(${sala.idAsignacion})">
                            <i class="bi bi-x-circle"></i> Liberar
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            const modal = new bootstrap.Modal(document.getElementById('liberarSalaModal'));
            modal.show();
        } else {
            mostrarNotificacion('No hay salas asignadas para liberar', 'warning');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error al cargar las salas asignadas', 'danger');
    }
}

async function liberarSala(idAsignacion) {
    if (!confirm('¿Está seguro que desea liberar esta sala? Esta acción también eliminará la reserva correspondiente.')) {
        return;
    }
    
    try {
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'liberar',
                idAsignacion: idAsignacion
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const modalLiberar = bootstrap.Modal.getInstance(document.getElementById('liberarSalaModal'));
            if (modalLiberar) modalLiberar.hide();
            
            mostrarNotificacion(data.message || 'Sala liberada correctamente', 'success');
           setTimeout(() => {
                recargarTablaSalasClinico();
            }, 500);
        } else {
            mostrarNotificacion(data.error || 'Error al liberar la sala', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error al procesar la solicitud', 'danger');
    }
}

async function mostrarDetallesInconsistencia(idplanclases) {
    try {
        actividadActualInconsistencia = idplanclases;
        
        // Mostrar modal con loading
        const modal = new bootstrap.Modal(document.getElementById('inconsistenciaModal'));
        modal.show();
        
        // Hacer la consulta
        const response = await fetch('salas_clinico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'obtener_detalles_inconsistencia',
                idplanclases: idplanclases
            })
        });
        
        if (!response.ok) {
            throw new Error(`Error del servidor: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            mostrarResultadosInconsistencia(data.salas);
        } else {
            throw new Error(data.error || 'Error obteniendo detalles');
        }
        
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('contenido-detalles-inconsistencia').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Error:</strong> ${error.message}
            </div>
        `;
    }
}

function mostrarResultadosInconsistencia(salas) {
    let htmlSalas = `
        <div class="table-responsive">
            <table class="table table-bordered">
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
                        <strong>Fecha:</strong> ${sala.fecha_asignacion ? 
                            new Date(sala.fecha_asignacion).toLocaleString('es-CL') : 'N/A'}
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
    
    document.getElementById('contenido-detalles-inconsistencia').innerHTML = htmlSalas;
}

function contactarAreaSalas() {
    // Función para contactar al área de salas
    const mensaje = `Estimado equipo de Gestión de Salas,

Se ha detectado una inconsistencia en las reservas para la actividad ID: ${actividadActualInconsistencia || 'N/A'}.

Algunas salas aparecen como asignadas en el sistema de actividades pero no se encuentran las reservas correspondientes en el sistema de salas.

Por favor, verificar y coordinar la sincronización de ambos sistemas.

Gracias por su atención.`;

    const mailtoLink = `mailto:salas@uchile.cl?subject=Inconsistencia en Reservas - Actividad ${actividadActualInconsistencia}&body=${encodeURIComponent(mensaje)}`;
    window.open(mailtoLink);
}

function modificarSalaDesdeInconsistencia() {
    if (actividadActualInconsistencia) {
        // Cerrar modal de inconsistencias
        const modalInconsistencia = bootstrap.Modal.getInstance(document.getElementById('inconsistenciaModal'));
        if (modalInconsistencia) modalInconsistencia.hide();
        
        // Abrir modal de modificación
        setTimeout(() => {
            modificarSala(actividadActualInconsistencia);
        }, 500);
    }
}

// ===== FUNCIÓN DE NOTIFICACIONES =====
function mostrarNotificacion(mensaje, tipo = 'info') {
    // Crear o reutilizar contenedor de notificaciones
    let contenedor = document.getElementById('notificaciones-container');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'notificaciones-container';
        contenedor.style.position = 'fixed';
        contenedor.style.top = '20px';
        contenedor.style.right = '20px';
        contenedor.style.zIndex = '9999';
        contenedor.style.maxWidth = '400px';
        document.body.appendChild(contenedor);
    }
    
    // Crear notificación
    const notificacion = document.createElement('div');
    notificacion.className = `alert alert-${tipo} alert-dismissible fade show`;
    notificacion.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    contenedor.appendChild(notificacion);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (notificacion && notificacion.parentNode) {
            notificacion.remove();
        }
    }, 500);
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Salas Clínico - Sistema de reservas cargado correctamente');
});

function recargarTablaSalasClinico() {
    console.log('🔄 Recargando tabla de salas clínicas...');
    
    const cursoId = new URLSearchParams(window.location.search).get('curso');
    const salasList = document.getElementById('salas-list');
    
    if (!salasList) {
        console.error('❌ No se encontró el contenedor salas-list');
        return;
    }
    
    // Mostrar indicador de carga
    salasList.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Actualizando...</span>
            </div>
            <p class="mt-2">Actualizando gestión de salas...</p>
        </div>
    `;
    
    // Realizar la petición AJAX
    fetch('salas_clinico.php?curso=' + cursoId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(html => {
            salasList.innerHTML = html;
            console.log('✅ Tabla de salas recargada exitosamente');
            
            // Reinicializar componentes después de cargar
            setTimeout(() => {
                if (typeof reinicializarFuncionalidades === 'function') {
                    reinicializarFuncionalidades();
                }
            }, 500);
        })
        .catch(error => {
            console.error('❌ Error al recargar tabla de salas:', error);
            salasList.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error al actualizar: ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Recargar página
                    </button>
                </div>
            `;
        });
}

function toggleManual() {
    const elemento = document.getElementById('testManual');
    if (elemento.classList.contains('show')) {
        elemento.classList.remove('show');
        console.log('Cerrado manualmente');
    } else {
        elemento.classList.add('show');
        console.log('Abierto manualmente');
    }
}

// Diagnóstico avanzado
function diagnosticoAvanzado() {
    const resultado = document.getElementById('resultadoDiagnostico');
    let html = '<h6>Análisis:</h6><ul>';
    
    // 1. Verificar event listeners múltiples
    const botones = document.querySelectorAll('[data-bs-toggle="collapse"]');
    html += `<li>Botones con data-bs-toggle: ${botones.length}</li>`;
    
    // 2. Verificar CSS transitions
    const elemento = document.getElementById('testConLog');
    const style = window.getComputedStyle(elemento);
    html += `<li>Transition: ${style.transition}</li>`;
    
    // 3. Verificar si hay CSS que interfiere
    if (style.display === 'none' && !elemento.classList.contains('show')) {
        html += '<li class="text-success">✅ Estados CSS correctos</li>';
    } else {
        html += '<li class="text-danger">❌ Estados CSS incorrectos</li>';
    }
    
    // 4. Verificar JavaScript errors
    html += '<li>Revisa la consola (F12) por errores JavaScript</li>';
    
    // 5. Test de event listeners
    html += '<li>Agregando event listener de prueba...</li>';
    
    // Agregar listener manual para detectar problemas
    botones.forEach((boton, index) => {
        boton.addEventListener('click', function(e) {
            console.log(`Click en botón ${index}:`, e.target);
            console.log('Target:', this.getAttribute('data-bs-target'));
        });
    });
    
    html += '</ul>';
    
    // SOLUCIÓN TEMPORAL
    html += `
    <div class="alert alert-warning mt-3">
        <h6>🔧 SOLUCIÓN TEMPORAL:</h6>
        <p>Agrega este CSS a tu página:</p>
        <code>
        .collapse.show { display: block !important; }<br>
        .collapse:not(.show) { display: none !important; }
        </code>
    </div>
    `;
    
    resultado.innerHTML = html;
}

// Log de eventos Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    const collapses = document.querySelectorAll('.collapse');
    collapses.forEach(collapse => {
        collapse.addEventListener('show.bs.collapse', function() {
            console.log('Bootstrap evento: MOSTRANDO', this.id);
        });
        collapse.addEventListener('shown.bs.collapse', function() {
            console.log('Bootstrap evento: MOSTRADO', this.id);
        });
        collapse.addEventListener('hide.bs.collapse', function() {
            console.log('Bootstrap evento: OCULTANDO', this.id);
        });
        collapse.addEventListener('hidden.bs.collapse', function() {
            console.log('Bootstrap evento: OCULTADO', this.id);
        });
    });
});

// ✅ LIMPIAR ERROR CUANDO SELECCIONA SUBTIPO
document.addEventListener('DOMContentLoaded', function() {
    const subtypeSelect = document.getElementById('activity-subtype');
    if (subtypeSelect) {
        subtypeSelect.addEventListener('change', function() {
            if (this.value.trim()) {
                this.style.borderColor = '';
            }
        });
    }
});

</script>

<!-- Justo antes del cierre del body -->

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="docentes_helper_clinico.js"></script>


<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 11"></div>
</body>
</html>