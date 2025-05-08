<?php
//index.php 99677 ultimo profesor
include("conexion.php");

// Obtener el ID del curso desde la URL
$idCurso = $_GET['curso']; 
//$idCurso = 8942; // 8158
$rut = "0167847811";
$ano = 2024; 
// Consulta SQL
$query = "SELECT `idplanclases`, pcl_tituloActividad, `pcl_Fecha`, `pcl_Inicio`, `pcl_Termino`, 
          `pcl_nSalas`, `pcl_Seccion`, `pcl_TipoSesion`, `pcl_SubTipoSesion`, 
          `pcl_Semana`, `pcl_AsiCodigo`, `pcl_AsiNombre`, `Sala`, `Bloque`, `dia`, `pcl_condicion`, `pcl_ActividadConEvaluacion`, pcl_BloqueExtendido
          FROM `planclases_test` 
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
    <title>Calendario Curso Clínico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="estilo.css" rel="stylesheet">
</head>
<body>
    <!-- Header y navegación igual que en index.php -->
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
        <a class="nav-link " href="index.php?curso=<?php echo $idCurso; ?>">
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
                           <div class="card">
    <div class="card-header">
        <h5 class="card-title">Editar información</h5>
        <!-- Bordered Tabs Justified -->
        <ul class="nav nav-tabs nav-tabs-bordered d-flex" id="borderedTabJustified" role="tablist">
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 active" id="home-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-home" type="button" role="tab" aria-controls="home" aria-selected="true"><i class="bi bi-calendar4-week"></i> Calendario </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100" id="salas-tab" data-bs-toggle="tab" data-bs-target="#bordered-justified-salas" type="button" role="tab" aria-controls="salas" aria-selected="false"><i class="ri ri-map-pin-line"></i> Salas</button>
            </li>
            
        </ul>
    </div>
	
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
                                                <td><?php echo $actividad['pcl_TipoSesion']; ?></td>
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

            <!-- Modal para agregar/editar actividad -->
            <!-- Modificación del modal de actividad para usar checkboxes de bloques -->
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
                    
                    <!-- Nuevo bloque para selección de bloques horarios -->
                    <div class="mb-3">
                        <label class="form-label">Bloques horarios</label>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Seleccione los bloques horarios para esta actividad
                        </div>
                        <div id="bloques-container" class="row">
                            <!-- Se llenará dinámicamente con los bloques desde la base de datos -->
                            <?php
                            // Consultar bloques disponibles
                            $queryBloques = "SELECT `bloque`, `inicio`, `termino` FROM `Bloques_ext` ORDER BY bloque ASC";
                            $resultBloques = $conn->query($queryBloques);
                            
                            while ($bloque = $resultBloques->fetch_assoc()) {
                                $idBloque = $bloque['bloque'];
                                $inicio = substr($bloque['inicio'], 0, 5);
                                $termino = substr($bloque['termino'], 0, 5);
                            ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input bloque-checkbox" type="checkbox" 
                                           name="bloques[]" value="<?php echo $idBloque; ?>" 
                                           id="bloque-<?php echo $idBloque; ?>"
                                           data-inicio="<?php echo $inicio; ?>"
                                           data-termino="<?php echo $termino; ?>">
                                    <label class="form-check-label" for="bloque-<?php echo $idBloque; ?>">
                                        Bloque <?php echo $idBloque; ?>: <?php echo $inicio; ?> - <?php echo $termino; ?>
                                    </label>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <!-- Contenedor para mostrar los bloques que serán eliminados al editar -->
                        <div id="bloques-a-eliminar" class="d-none">
                            <!-- Se llenará dinámicamente al editar -->
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
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
    
    function resetForm() {
        document.getElementById('activityForm').reset();
        document.getElementById('idplanclases').value = '0';
        document.getElementById('activityModalTitle').textContent = 'Ingresar nueva actividad';
        document.getElementById('subtype-container').style.display = 'none';
        
        // Establecer fecha por defecto a hoy
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('activity-date').value = today;
    }
    
    function editActivity(idplanclases) {
        // Cambiar título del modal
        document.getElementById('activityModalTitle').textContent = 'Editar actividad';
        
        // Cargar datos de la actividad
        fetch(`get_actividad clinica.php?id=${idplanclases}`)
            .then(response => response.json())
            .then(activity => {
                document.getElementById('idplanclases').value = activity.idplanclases;
                document.getElementById('activity-title').value = activity.pcl_tituloActividad || '';
                document.getElementById('activity-type').value = activity.pcl_TipoSesion || '';
                updateSubTypes();
                
                if (activity.pcl_SubTipoSesion) {
                    document.getElementById('activity-subtype').value = activity.pcl_SubTipoSesion;
                }
                
                // Formato de fecha para input date (YYYY-MM-DD)
                const fecha = new Date(activity.pcl_Fecha);
                const formattedDate = fecha.toISOString().split('T')[0];
                document.getElementById('activity-date').value = formattedDate;
                
                // Formatear horas para input time (HH:MM)
                document.getElementById('start-time').value = activity.pcl_Inicio.substring(0, 5);
                document.getElementById('end-time').value = activity.pcl_Termino.substring(0, 5);
                
                // Checkbox
                document.getElementById('mandatory').checked = activity.pcl_condicion === 'Obligatorio';
                document.getElementById('is-evaluation').checked = activity.pcl_ActividadConEvaluacion === 'S';
                
                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('activityModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error al cargar la actividad:', error);
                mostrarToast('Error al cargar los datos de la actividad', 'danger');
            });
    }
    
    function deleteActivity(idplanclases) {
        document.getElementById('delete-id').value = idplanclases;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
    function saveActivity() {
        const form = document.getElementById('activityForm');
        
        // Validación básica del formulario
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        
        // Valores adicionales
        const isNew = document.getElementById('idplanclases').value === '0';
        const idCurso = document.getElementById('cursos_idcursos').value;
        
        // Obtener los valores para calcular el día
        const dateStr = document.getElementById('activity-date').value;
        const date = new Date(dateStr);
        const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const dia = dayNames[date.getDay()];
        
        // Añadir campos adicionales
        formData.append('dia', dia);
        formData.append('cursos_idcursos', idCurso);
        formData.append('pcl_condicion', document.getElementById('mandatory').checked ? 'Obligatorio' : 'Libre');
        formData.append('pcl_ActividadConEvaluacion', document.getElementById('is-evaluation').checked ? 'S' : 'N');
        
        // Enviar datos al servidor
        fetch('guardar_actividad_clinica.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('activityModal'));
                modal.hide();
                
                // Mostrar notificación
                mostrarToast(isNew ? 'Actividad creada exitosamente' : 'Actividad actualizada exitosamente', 'success');
                
                // Recargar página después de un breve periodo
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(data.message || 'Error al guardar la actividad');
            }
        })
        .catch(error => {
            mostrarToast('Error: ' + error.message, 'danger');
        });
    }
    
    // Función para eliminar una actividad
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const idplanclases = document.getElementById('delete-id').value;
        
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
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(data.message || 'Error al eliminar la actividad');
            }
        })
        .catch(error => {
            mostrarToast('Error: ' + error.message, 'danger');
        });
    });
    
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
    
    // Inicializar al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        loadActivityTypes();
        
        // Configurar modal para nueva actividad
        document.getElementById('activityModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget) return; // Si es edición, no resetear
            resetForm();
        });
        
        // Establecer fecha por defecto a hoy para nueva actividad
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('activity-date').value = today;
    });


// Cargar contenido de salas al hacer clic en la pestaña
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('salas-tab').addEventListener('click', function() {
        const salasList = document.getElementById('salas-list');
        
        if (!salasList.dataset.loaded) {
            // Obtener el ID del curso de la URL
            const urlParams = new URLSearchParams(window.location.search);
            const cursoId = urlParams.get('curso');
            
            // Cargar el contenido de salas
            fetch('salas_clinico.php?curso=' + cursoId)
                .then(response => response.text())
                .then(html => {
                    salasList.innerHTML = html;
                    salasList.dataset.loaded = 'true';
                })
                .catch(error => {
                    salasList.innerHTML = '<div class="alert alert-danger m-4">Error al cargar la gestión de salas.</div>';
                    console.error('Error:', error);
                });
        }
    });
});


// Cargar el contenido de salas cuando se hace clic en la pestaña correspondiente
document.addEventListener('DOMContentLoaded', function() {
    // Función para cargar el contenido de salas
    function cargarSalas() {
        const salasList = document.getElementById('salas-list');
        
        // Evitar cargar múltiples veces el contenido
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
    }
    
    // Asociar evento al clic en la pestaña de salas
    const salasTab = document.getElementById('salas-tab');
    if (salasTab) {
        salasTab.addEventListener('click', cargarSalas);
        
        // Si la pestaña está activa al cargar, cargar el contenido inmediatamente
        if (salasTab.classList.contains('active')) {
            cargarSalas();
        }
    }
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

/**
 * Reescribimos las funciones principales para usar el método unificado de envío
 */
async function solicitarSala(idPlanClase) {
    document.getElementById('salaForm').reset();
    document.getElementById('idplanclases').value = idPlanClase;
    document.getElementById('action').value = 'solicitar';
    document.getElementById('salaModalTitle').textContent = 'Solicitar Sala';
    
    // Obtener el número de alumnos del elemento de la tabla
    const tr = document.querySelector(`tr[data-id="${idPlanClase}"]`);
    if (tr) {
        const alumnosTotales = tr.dataset.alumnos || 0;
        document.getElementById('alumnosTotales').value = alumnosTotales;
        calcularAlumnosPorSala();
    }
    
    const modal = new bootstrap.Modal(document.getElementById('salaModal'));
    modal.show();
}

async function guardarSala() {
    const form = document.getElementById('salaForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Recopilar datos del formulario
    const formData = new FormData(form);
    const datos = Object.fromEntries(formData.entries());
    
    try {
        const respuesta = await enviarSolicitudSala(datos.action, datos);
        
        if (respuesta.success) {
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
            modal.hide();
            
            mostrarNotificacion('Solicitud de sala procesada correctamente', 'success');
            
            // Recargar la página para ver los cambios
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarNotificacion(respuesta.error || 'Error al guardar los cambios', 'danger');
        }
    } catch (error) {
        // El error ya se muestra en enviarSolicitudSala
    }
}

// Calcular alumnos por sala (función reutilizable)
function calcularAlumnosPorSala() {
    const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
    const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
    // Usar Math.ceil para redondear hacia arriba
    const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
    document.getElementById('alumnosPorSala').value = alumnosPorSala;
}
</script>


<script>

// Funciones para manejar bloques horarios

/**
 * Objeto para mantener un registro de los IDs de planclases asociados a cada bloque
 * durante la edición, para poder determinar cuáles eliminar
 */
let bloquesActividad = {};

/**
 * Resetea el formulario y el registro de bloques
 */
function resetForm() {
    document.getElementById('activityForm').reset();
    document.getElementById('idplanclases').value = '0';
    document.getElementById('activityModalTitle').textContent = 'Ingresar nueva actividad';
    document.getElementById('subtype-container').style.display = 'none';
    
    // Limpiar selección de bloques
    document.querySelectorAll('.bloque-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Limpiar registro de bloques
    bloquesActividad = {};
    
    // Limpiar contenedor de bloques a eliminar
    document.getElementById('bloques-a-eliminar').innerHTML = '';
    
    // Establecer fecha por defecto a hoy
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('activity-date').value = today;
}

/**
 * Carga los datos de una actividad para editarla
 */
function editActivity(idplanclases) {
    // Cambiar título del modal
    document.getElementById('activityModalTitle').textContent = 'Editar actividad';
    
    // Resetear formulario antes de cargar nuevos datos
    resetForm();
    
    // Cargar datos de la actividad principal y sus bloques
    fetch(`get_actividad_clinica_bloques.php?id=${idplanclases}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const activity = data.activity;
                const bloques = data.bloques;
                
                // Cargar datos principales de la actividad
                document.getElementById('idplanclases').value = activity.idplanclases;
                document.getElementById('activity-title').value = activity.pcl_tituloActividad || '';
                document.getElementById('activity-type').value = activity.pcl_TipoSesion || '';
                updateSubTypes();
                
                if (activity.pcl_SubTipoSesion) {
                    document.getElementById('activity-subtype').value = activity.pcl_SubTipoSesion;
                }
                
                // Formato de fecha para input date (YYYY-MM-DD)
                const fecha = new Date(activity.pcl_Fecha);
                const formattedDate = fecha.toISOString().split('T')[0];
                document.getElementById('activity-date').value = formattedDate;
                
                // Checkbox
                document.getElementById('mandatory').checked = activity.pcl_condicion === 'Obligatorio';
                document.getElementById('is-evaluation').checked = activity.pcl_ActividadConEvaluacion === 'S';
                
                // Marcar bloques seleccionados
                bloques.forEach(bloque => {
                    const checkbox = document.getElementById(`bloque-${bloque.bloque}`);
                    if (checkbox) {
                        checkbox.checked = true;
                        // Guardar relación entre bloque y idplanclases
                        bloquesActividad[bloque.bloque] = bloque.idplanclases;
                    }
                });
                
                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('activityModal'));
                modal.show();
            } else {
                mostrarToast('Error al cargar los datos: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error al cargar la actividad:', error);
            mostrarToast('Error al cargar los datos de la actividad', 'danger');
        });
}

/**
 * Guarda una actividad con sus bloques seleccionados
 */
function saveActivity() {
    const form = document.getElementById('activityForm');
    
    // Validación básica del formulario
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Verificar que se haya seleccionado al menos un bloque
    const bloquesSeleccionados = document.querySelectorAll('.bloque-checkbox:checked');
    if (bloquesSeleccionados.length === 0) {
        mostrarToast('Debe seleccionar al menos un bloque horario', 'warning');
        return;
    }
    
    const formData = new FormData(form);
    
    // Obtener valores importantes
    const isNew = document.getElementById('idplanclases').value === '0';
    const idCurso = document.getElementById('cursos_idcursos').value;
    const idplanclases = document.getElementById('idplanclases').value;
    
    // Obtener los valores para calcular el día
    const dateStr = document.getElementById('activity-date').value;
    const date = new Date(dateStr);
    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const dia = dayNames[date.getDay()];
    
    // Añadir campos adicionales
    formData.append('dia', dia);
    formData.append('cursos_idcursos', idCurso);
    formData.append('pcl_condicion', document.getElementById('mandatory').checked ? 'Obligatorio' : 'Libre');
    formData.append('pcl_ActividadConEvaluacion', document.getElementById('is-evaluation').checked ? 'S' : 'N');
    
    // Añadir información de bloques ya existentes (para edición)
    if (!isNew) {
        formData.append('bloquesActividad', JSON.stringify(bloquesActividad));
    }
    
    // Enviar datos al servidor
    fetch('guardar_actividad_clinica_bloques.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('activityModal'));
            modal.hide();
            
            // Mostrar notificación
            mostrarToast(isNew ? 'Actividad creada exitosamente' : 'Actividad actualizada exitosamente', 'success');
            
            // Recargar página después de un breve periodo
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Error al guardar la actividad');
        }
    })
    .catch(error => {
        mostrarToast('Error: ' + error.message, 'danger');
    });
}

/**
 * Función para eliminar una actividad y todos sus bloques asociados
 */
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    const idplanclases = document.getElementById('delete-id').value;
    
    fetch('eliminar_actividad_clinica_bloques.php', {
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
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Error al eliminar la actividad');
        }
    })
    .catch(error => {
        mostrarToast('Error: ' + error.message, 'danger');
    });
});

</script>



	
</body>
</html>