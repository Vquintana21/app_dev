<?php
header('Content-type: text/html; charset=utf-8');
include("conexion.php");

$idCurso = isset($_GET['curso']) ? $_GET['curso'] : 0;

$CURSO = "SELECT spre_cursos.idCurso, spre_cursos.CodigoCurso, spre_ramos.nombreCurso, spre_cursos.seccion 
          FROM spre_cursos 
          INNER JOIN spre_ramos ON spre_cursos.codigoCurso = spre_ramos.codigoCurso
          WHERE idCurso='$idCurso'";
$CURSO_query = mysqli_query($conexion3, $CURSO);
$fila_curso = mysqli_fetch_assoc($CURSO_query);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asignación Masiva de Docentes</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <!-- CSS personalizado -->
    <link href="estilo2.css" rel="stylesheet">
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Información del curso -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <h4 class="card-title">
                    <?php echo $fila_curso['CodigoCurso']; ?> 
                    <?php echo utf8_encode($fila_curso['nombreCurso']); ?>
                </h4>
                <h5 class="text-muted">Sección <?php echo $fila_curso['seccion']; ?></h5>
                <div class="badge bg-info text-white">Asignación Masiva de Docentes</div>
            </div>
        </div>
		

        <!-- Filtros y selección -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filtros de búsqueda</h5>
            </div>
            <div class="card-body">
                <form id="filtroForm">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de actividad</label>
                            <select class="form-select" id="tipoActividad" name="tipoActividad">
                                <option value="">Todas las actividades</option>
                                <?php
                                // Consultar tipos de actividad disponibles en el curso
                                $tipos_query = "SELECT DISTINCT pcl_TipoSesion FROM planclases 
                                              WHERE cursos_idcursos='$idCurso' 
                                              AND pcl_TipoSesion != '' 
                                              ORDER BY pcl_TipoSesion ASC";
                                $result_tipos = mysqli_query($conn, $tipos_query);
                                while($tipo = mysqli_fetch_assoc($result_tipos)) {
                                    echo '<option value="'.htmlspecialchars($tipo['pcl_TipoSesion']).'">'.
                                        htmlspecialchars($tipo['pcl_TipoSesion']).'</option>';
                                }
                                ?>
                            </select>
                        </div>                        
                        <div class="col-md-4">
                            <label class="form-label">Subtipo</label>
                            <select class="form-select" id="subtipo" name="subtipo">
                                <option value="">Todos los subtipos</option>
                                <?php
                                // Consultar subtipos dependiente del tipo anterior.
                                $subtipos_query = "SELECT DISTINCT pcl_SubTipoSesion FROM planclases 
                                                 WHERE cursos_idcursos='$idCurso' 
                                                 AND pcl_SubTipoSesion != '' 
                                                 ORDER BY pcl_SubTipoSesion ASC";
                                $result_subtipos = mysqli_query($conn, $subtipos_query);
                                while($subtipo = mysqli_fetch_assoc($result_subtipos)) {
                                    echo '<option value="'.htmlspecialchars($subtipo['pcl_SubTipoSesion']).'">'.
                                        htmlspecialchars($subtipo['pcl_SubTipoSesion']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
						<div class="col-md-4">
                            <label class="form-label">Día de semana</label>
                            <select class="form-select" id="diaSemana" name="diaSemana">
                                <option value="">Todos los días</option>
                                <option value="Lunes">Lunes</option>
                                <option value="Martes">Martes</option>
                                <option value="Miércoles">Miércoles</option>
                                <option value="Jueves">Jueves</option>
                                <option value="Viernes">Viernes</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Fecha inicio</label>
                            <input type="date" class="form-control" id="fechaInicio" name="fechaInicio">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha término</label>
                            <input type="date" class="form-control" id="fechaTermino" name="fechaTermino">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hora inicio</label>
                            <select class="form-select" id="horaInicio" name="horaInicio">
                                <option value="">Todas</option>
                                <?php
                                for($h = 8; $h <= 21; $h++) {
                                    for($m = 0; $m < 60; $m += 30) {
                                        $hora = sprintf('%02d:%02d', $h, $m);
                                        echo "<option value=\"$hora\">$hora</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hora término</label>
                            <select class="form-select" id="horaTermino" name="horaTermino">
                                <option value="">Todas</option>
                                <?php
                                for($h = 8; $h <= 21; $h++) {
                                    for($m = 0; $m < 60; $m += 30) {
                                        $hora = sprintf('%02d:%02d', $h, $m);
                                        echo "<option value=\"$hora\">$hora</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <button type="button" id="btnVisualizar" class="btn btn-info px-4">
                                <i class="bi bi-search"></i> Buscar actividades
                            </button>
							<button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary px-4 ms-2">
								<i class="bi bi-arrow-counterclockwise"></i> Limpiar filtros
							</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Resultados: Actividades y Docentes -->
        <div class="row">
            <!-- Listado de actividades -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Actividades para asignación docente</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tablaActividades">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Hora inicio</th>
                                        <th>Hora Término</th>
                                        <th>Actividad</th>
                                        <th>Tipo de Actividad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Se llena dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <div class="alert alert-info d-none" id="sinResultados" role="alert">
                            No se encontraron actividades con los criterios seleccionados.
                        </div>
                        <div class="mb-3">
                            <button id="btnAsignarDocentes" class="btn btn-success" disabled>
                                <i class="bi bi-check-circle"></i> Asignar docentes
                            </button>
                            <button id="btnEliminarDocentes" class="btn btn-danger" disabled>
								<i class="bi bi-x-circle"></i> Desvincular docentes
							</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Selección de docentes -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Docentes</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="seleccionarTodos">
                                <label class="form-check-label" for="seleccionarTodos">
                                    Seleccionar todo
                                </label>
                            </div>
                        </div>
                        <div id="listaDocentes">
                            <?php
                            // Consultar el equipo docente del curso
                            $equipo_docente = "SELECT spre_profesorescurso.rut, spre_personas.Nombres, 
                                              spre_personas.Paterno, spre_personas.Materno,
                                              spre_tipoparticipacion.CargoTexto 
                                              FROM spre_profesorescurso
                                              INNER JOIN spre_personas ON spre_profesorescurso.rut = spre_personas.Rut
                                              INNER JOIN spre_tipoparticipacion ON spre_profesorescurso.idTipoParticipacion = spre_tipoparticipacion.idTipoParticipacion
                                              WHERE idcurso = '$idCurso'
                                              AND spre_profesorescurso.idTipoParticipacion NOT IN (8,10)
                                              AND Vigencia = 1
                                              ORDER BY Paterno ASC, Nombres ASC";
                            $result_docentes = mysqli_query($conexion3, $equipo_docente);
                            
                            function obtenerFoto($rut) {
                                $rut_def = ltrim($rut, "0");
                                $cad = substr($rut_def, 0, -1);
                                
                                $url = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/personas?rut='.$cad;
                                
                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $resultado = curl_exec($ch);
                                curl_close($ch);
                                
                                $array_cursos = json_decode($resultado);
                                
                                if($array_cursos != NULL) {
                                    return $array_cursos->i;
                                } else {
                                    return "../../undraw_profile.svg";
                                }
                            }
                            
                            while($docente = mysqli_fetch_assoc($result_docentes)) {
                                $foto = obtenerFoto($docente["rut"]);
                                $nombre_completo = utf8_encode($docente["Nombres"] . " " . $docente["Paterno"] . " " . $docente["Materno"]);
                                $cargo = utf8_encode($docente["CargoTexto"]);
                                echo '
                                <div class="row mb-3 docente-row">
                                    <div class="col-3">
                                        <img src="'.$foto.'" alt="Foto" class="rounded-circle img-fluid">
                                    </div>
                                    <div class="col-7">
                                        <p class="mb-0">'.$nombre_completo.'</p>
                                        <small class="text-muted">'.$cargo.'</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input docente-check" type="checkbox" 
                                                   data-rut="'.$docente["rut"].'">
                                        </div>
                                    </div>
                                </div>';
                            }
                            
                            if(mysqli_num_rows($result_docentes) == 0) {
                                echo '<div class="alert alert-warning">No hay docentes asignados al curso</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenedor para notificaciones -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    
    <script>
        // Variables globales
        let actividadesSeleccionadas = [];
        
        $(document).ready(function() {
            // Inicializar interfaz
            $('#btnVisualizar').click(buscarActividades);
            $('#seleccionarTodos').change(function() {
                $('.docente-check').prop('checked', $(this).is(':checked'));
            });
            
            // Botones de asignación y eliminación
            $('#btnAsignarDocentes').click(function() {
                gestionarDocentes('asignar');
            });
            
            $('#btnEliminarDocentes').click(function() {
                gestionarDocentes('eliminar');
            });
            
            // Verificar cambios en los checkboxes de docentes
            $(document).on('change', '.docente-check', function() {
                const todasSeleccionadas = $('.docente-check:checked').length === $('.docente-check').length;
                $('#seleccionarTodos').prop('checked', todasSeleccionadas);
                verificarSelecciones();
            });
			
			// Botón para limpiar filtros
				$('#btnLimpiarFiltros').click(function() {
					// Limpiar campos de filtro (código existente)
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
				});
        });
        
        function buscarActividades() {
            // Obtener valores de los filtros
            const tipoActividad = $('#tipoActividad').val();
            const diaSemana = $('#diaSemana').val();
            const subtipo = $('#subtipo').val();
            const fechaInicio = $('#fechaInicio').val();
            const fechaTermino = $('#fechaTermino').val();
            const horaInicio = $('#horaInicio').val();
            const horaTermino = $('#horaTermino').val();
            
            // Crear objeto con los filtros
            const filtros = {
                idcurso: <?php echo $idCurso; ?>,
                tipoActividad: tipoActividad,
                diaSemana: diaSemana,
                subtipo: subtipo,
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
							
							// Obtener docentes asignados a las actividades filtradas
							obtenerDocentesComunes(actividadesSeleccionadas);
						}
					} else {
						mostrarNotificacion(response.message || 'Error al buscar actividades', 'danger');
					}
				},
				error: function() {
					mostrarNotificacion('Error de comunicación con el servidor', 'danger');
                }
            });
        }
		
		function obtenerDocentesComunes(actividades) {
    if (!actividades || actividades.length === 0) return;
    
    // Desmarcar todos los docentes primero
    $('.docente-check').prop('checked', false);
    $('#seleccionarTodos').prop('checked', false);
    
    // Consultar los docentes asignados a todas las actividades
    $.ajax({
        url: 'get_docentes_actividades.php',
        type: 'POST',
        dataType: 'json',
        data: {
            actividades: actividades,
            idcurso: <?php echo $idCurso; ?>
        },
        success: function(response) {
            if (response.success && response.docentesComunes) {
                // Marcar docentes comunes
                response.docentesComunes.forEach(rut => {
                    $(`.docente-check[data-rut="${rut}"]`).prop('checked', true);
                });
                
                // Verificar si todos están seleccionados
                const todasSeleccionadas = $('.docente-check:checked').length === $('.docente-check').length;
                $('#seleccionarTodos').prop('checked', todasSeleccionadas);
                
                // Actualizar estado de los botones
                verificarSelecciones();
            }
        },
        error: function() {
            console.error('Error al obtener docentes comunes');
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
                
                // Crear fila
                const fila = `
                    <tr data-id="${act.idplanclases}">
                        <td>${fechaFormateada}</td>
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
                mostrarNotificacion('No hay actividades seleccionadas', 'warning');
                return;
            }
            
            // Obtener docentes seleccionados
            const docentesSeleccionados = [];
            $('.docente-check:checked').each(function() {
                docentesSeleccionados.push($(this).data('rut'));
            });
            
            if (docentesSeleccionados.length === 0) {
                mostrarNotificacion('No hay docentes seleccionados', 'warning');
                return;
            }
            
            // Confirmar la acción
				if (!confirm(`¿Está seguro que desea ${accion === 'asignar' ? 'asignar' : 'desvincular'} ${docentesSeleccionados.length} docente(s) ${accion === 'asignar' ? 'a' : 'de'} ${actividadesSeleccionadas.length} actividad(es)?`)) {
					return;
				}
            
            // Preparar datos para enviar
            const datos = {
                idcurso: <?php echo $idCurso; ?>,
                actividades: actividadesSeleccionadas,
                docentes: docentesSeleccionados,
                accion: accion
            };
            
            // Mostrar indicador de carga
            mostrarNotificacion(`Procesando... Por favor espere.`, 'info');
            
			console.log("Datos a enviar:", datos);
			const jsonData = JSON.stringify(datos);
			console.log("JSON a enviar:", jsonData);
			
            // Realizar solicitud AJAX
			$.ajax({
				url: 'procesar_asignacion_masiva.php',
				type: 'POST',
				dataType: 'json',
				data: JSON.stringify(datos),
				contentType: 'application/json',
				success: function(response) {
					if (response.success) {
						mostrarNotificacion(
							`${accion === 'asignar' ? 'Asignación' : 'Desvinculación'} completada correctamente. 
							${response.operaciones || 0} operaciones realizadas.`, 
							'success'
						);
					} else {
						mostrarNotificacion(response.message || 'Error al procesar la solicitud', 'danger');
					}
				},
				error: function(xhr, status, error) {
					console.error("Error AJAX:", xhr.responseText);
					mostrarNotificacion('Error de comunicación con el servidor: ' + (error || status), 'danger');
				}
			});
        }
        
        function mostrarNotificacion(mensaje, tipo = 'success') {
            // Crear toast
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-${tipo === 'success' ? 'check-circle' : tipo === 'danger' ? 'x-circle' : 'info-circle'} me-2"></i>
                            ${mensaje}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            // Añadir a contenedor
            $('.toast-container').append(toastHTML);
            
            // Mostrar toast
            const toastElement = new bootstrap.Toast(document.getElementById(toastId), {
                autohide: true,
                delay: 5000
            });
            toastElement.show();
        }
    </script>
</body>
</html>
