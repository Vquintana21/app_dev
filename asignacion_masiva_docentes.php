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

    <div class="container py-4">
        <!-- Información del curso -->
        <div class="card mb-4">
            <div class="card-body text-center">
               <h4> <i class="bi bi-person-raised-hand"></i> Instrucciones</h4>
                
            </div>
        </div>
		

        <!-- Filtros y selección -->
        <div class="card mb-4">
            <div class="card-header">
                <h4  class="text-primary">Paso 1: Filtros de búsqueda</h4>
            </div>
            <div class="card-body">
                <form id="filtroForm">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tipo de actividad</label>
                            <select class="form-select" id="tipoActividad" name="tipoActividad">
                                <option value="">Todas las actividades</option>
                                <?php
                                // Consultar tipos de actividad disponibles en el curso
                                $tipos_query = "SELECT DISTINCT tipo_sesion FROM pcl_TipoSesion WHERE docentes = 1";
                                $result_tipos = mysqli_query($conn, $tipos_query);
                                while($tipo = mysqli_fetch_assoc($result_tipos)) {
                                    echo '<option value="'.htmlspecialchars($tipo['tipo_sesion']).'">'.
                                        htmlspecialchars($tipo['tipo_sesion']).'</option>';
                                }
                                ?>
                            </select>
                        </div>   

						<div class="col-md-4">
                            <label class="form-label fw-bold">Fecha inicio</label>
                            <input type="date" class="form-control" id="fechaInicio" name="fechaInicio">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fecha término</label>
                            <input type="date" class="form-control" id="fechaTermino" name="fechaTermino">
                        </div>						
                        
						</div>   
                    
                    <div class="row g-3 mb-4">
                        
						<div class="col-md-4">
                            <label class="form-label fw-bold">Día de semana</label>
                            <select class="form-select" id="diaSemana" name="diaSemana">
                                <option value="">Todos los días</option>
                                <?php
                                // Consultar tipos de actividad disponibles en el curso
                                $tipos_query = "SELECT DISTINCT dia FROM a_planclases WHERE dia NOT LIKE 'Domingo' AND cursos_idcursos = '$idCurso'";
                                $result_tipos = mysqli_query($conn, $tipos_query);
                                while($tipo = mysqli_fetch_assoc($result_tipos)) {
                                    echo '<option value="'.htmlspecialchars($tipo['dia']).'">'.
                                        htmlspecialchars($tipo['dia']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Hora inicio</label>
                            <select class="form-select" id="horaInicio" name="horaInicio">
                                <option value="">Todas</option>
                                <?php
                                // Consultar pcl_Inicio disponibles en el curso
                                $tipos_query = "SELECT DISTINCT pcl_Inicio FROM a_planclases WHERE dia NOT LIKE 'Domingo' AND cursos_idcursos = '$idCurso' order by pcl_Inicio asc;";
                                $result_tipos = mysqli_query($conn, $tipos_query);
                                while($tipo = mysqli_fetch_assoc($result_tipos)) {
                                    echo '<option value="'.htmlspecialchars($tipo['pcl_Inicio']).'">'.
                                        htmlspecialchars($tipo['pcl_Inicio']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Hora término</label>
                            <select class="form-select" id="horaTermino" name="horaTermino">
                                <option value="">Todas</option>
                                <?php
                                // Consultar pcl_Termino disponibles en el curso
                                $tipos_query = "SELECT DISTINCT pcl_Termino FROM a_planclases WHERE dia NOT LIKE 'Domingo' AND cursos_idcursos = '$idCurso' order by pcl_Inicio asc;";
                                $result_tipos = mysqli_query($conn, $tipos_query);
                                while($tipo = mysqli_fetch_assoc($result_tipos)) {
                                    echo '<option value="'.htmlspecialchars($tipo['pcl_Termino']).'">'.
                                        htmlspecialchars($tipo['pcl_Termino']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
						<div class="col-md-12 text-center">
							<button type="button" id="btnVisualizar" class="btn btn-info px-4">
								<i class="bi bi-search"></i> Visualizar Actividades
							</button>
							<button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary px-4 ms-2">
								<i class="bi bi-arrow-counterclockwise"></i> Limpiar filtros
							</button>
							<div class="mt-2">
							<button type="button" class="btn btn-sm btn-warning" disabled><small>* Debe seleccionar al menos un filtro para buscar</small></button>
								
							</div>
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
                        <h4  class="text-primary">Paso 2: Actividades para asignación docente</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tablaActividades">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
										<th>Dia</th>
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
                            <button id="btnEliminarDocentes" class="btn btn-danger" hidden>
								<i class="bi bi-x-circle"></i> Eliminar docentes
							</button>
                        </div>
                    </div>
                </div>
            </div>
			
			
			<!-- Modal de previsualización -->
<div class="modal fade" id="previsualizacionModal" tabindex="-1" aria-labelledby="previsualizacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previsualizacionModalLabel">
                    Confirmar <span id="accionTitulo"></span> de Docentes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Resumen:</strong>
                    <ul class="mb-0">
                        <li><span id="numActividades"></span> actividad(es) seleccionada(s)</li>
                        <li><span id="numDocentes"></span> docente(s) seleccionado(s)</li>
                        <li>Acción: <strong id="accionDescripcion"></strong></li>
                    </ul>
                </div>
                
                <h6>Actividades seleccionadas:</h6>
                <div class="table-responsive mb-3" style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Actividad</th>
                            </tr>
                        </thead>
                        <tbody id="actividadesPreview">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                </div>
                
                <h6>Docentes seleccionados:</h6>
                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Cargo</th>
                            </tr>
                        </thead>
                        <tbody id="docentesPreview">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmarAccion">Confirmar</button>
            </div>
        </div>
    </div>
</div>
            
            <!-- Selección de docentes -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4  class="text-primary">Paso 3: Docentes</h4>
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
                                               ORDER BY Nombres ASC, Paterno ASC, Materno ASC";
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
							// CAMBIAR EL ORDEN DE CONCATENACIÓN DEL NOMBRE
							$nombre_completo = utf8_encode($docente["Nombres"] . " " .$docente["Paterno"] . " " . $docente["Materno"]);
							$cargo = utf8_encode($docente["CargoTexto"]);
							echo '
							<div class="row mb-3 docente-row">
								<div class="col-3">
									<img width="70%" src="'.$foto.'" alt="Foto" class="rounded-circle img-fluid">
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
    
    
