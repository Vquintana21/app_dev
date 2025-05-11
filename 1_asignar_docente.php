<?php 
//asignar docentes.php
header ('Content-type: text/html; charset=utf-8');
session_start(); 
error_reporting(0);
//include("conn.php");
include("conexion.php");
//$rut = $_SESSION['sesion_idLogin']; 
$rut = '162083015';
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);
//$rut_niv ='0192001269';
//if($rut_niv == '0185643530'){ $rut_niv='0192001269'; $_SESSION['sesion_idLogin'] = '0192001269';}
$consulta=mysqli_query($conexion3,"select EmailReal from spre_personas where rut ='$rut_niv'");
$estate = mysqli_fetch_assoc($consulta);
$mail=$estate['EmailReal'];
$usuariox = $_SESSION['sesion_usuario']; 
$usuario = utf8_decode($usuariox);

$CURSO = "SELECT spre_cursos.idCurso,spre_cursos.CodigoCurso,spre_ramos.nombreCurso,spre_cursos.seccion  FROM spre_cursos 
INNER JOIN spre_ramos ON spre_cursos.codigoCurso = spre_ramos.codigoCurso
WHERE idCurso='$_GET[idcurso]'";
$CURSO_query = mysqli_query($conexion3,$CURSO);

$fila_curso = mysqli_fetch_assoc($CURSO_query);

$PEC = "SELECT * FROM spre_personas WHERE Rut='$rut_niv' ";
$PEC_Query = mysqli_query($conexion3,$PEC);
$PEC_fila = mysqli_fetch_assoc($PEC_Query);

/* if($rut_niv == '0185643530'){    
    $rut="0192001269";
 $rut_niv="0192001269";
}*/

//Control Profesor (¿Es profesor encargado del curso?)

$ValidarProfe = "SELECT * FROM spre_profesorescurso WHERE idcurso='$_GET[idcurso]' AND rut='$rut_niv' AND vigencia='1' AND idTipoParticipacion IN ('1','2','3','8','10') "; 
$ValidarQuery = mysqli_query($conexion3,$ValidarProfe);
$control_profe = mysqli_num_rows($ValidarQuery);

if($rut!='' && $control_profe > 0){
?>

    

    <div class="container py-4">  
	 <div class="card mb-4">
            <div class="card-body text-center">
               <h4> <i class="bi bi-person-raised-hand"></i> Instrucciones</h4>
                
            </div>
        </div>
        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <select class="form-select" id="docente" data-live-search="true">
							<option value="" selected disabled>Escriba nombre o RUT para buscar</option>
						</select>
                    </div>
                    <div class="col-lg-2">
                        <button type="button" id="boton_agregar" class="btn btn-success w-100" disabled>
                            <i class="bi bi-plus-circle"></i> Asignar Docente
                        </button>						
                    </div>
					 <div class="col-lg-2">
                        <button type="button" id="nuevo-docente-btn" class="btn btn-primary w-100">
							<i class="bi bi-person-add"></i> Nuevo Docente
						</button>
											</div>
                </div>
            </div>
        </div>
        


        <!-- Faculty Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Docente</th>
                                <th>Correo</th>
                                <th>Participación</th>
								<th>Total Horas Directas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $profesores = "SELECT *,spre_tipoparticipacion.CargoTexto FROM spre_profesorescurso
               INNER JOIN spre_personas ON spre_profesorescurso.rut = spre_personas.Rut 
               INNER JOIN spre_tipoparticipacion ON spre_profesorescurso.idTipoParticipacion = spre_tipoparticipacion.idTipoParticipacion 
               WHERE idcurso='$_GET[idcurso]' AND Vigencia='1' AND spre_profesorescurso.idTipoParticipacion NOT IN ('10') 
               ORDER BY 
                   CASE 
                       WHEN spre_profesorescurso.idTipoParticipacion IN (1, 2, 3, 10) THEN 0 
                       ELSE 1 
                   END,
                   spre_personas.Nombres ASC, 
                   spre_personas.Paterno ASC, 
                   spre_personas.Materno ASC";
                            $profesores_query = mysqli_query($conexion3,$profesores);
                            while($fila_profesores = mysqli_fetch_assoc($profesores_query)){
								
								 // Consulta para obtener total de horas
									$query_horas = "SELECT sum(`horas`) as total_horas 
													FROM `docenteclases` 
													WHERE `idCurso` = $_GET[idcurso] 
													AND `rutDocente`='$fila_profesores[rut]' 
													AND vigencia=1";
									$result_horas = mysqli_query($conn, $query_horas);
									$total_horas = mysqli_fetch_assoc($result_horas);
									$horas_formateadas = $total_horas['total_horas'];
	
                            ?>
                            <tr>
                                <td><i class="bi bi-person text-primary"></i></td>
                                <td><?php echo utf8_encode($fila_profesores['Nombres'].' '.$fila_profesores['Paterno'].' '.$fila_profesores['Materno']); ?></td>
                                <td><?php echo $fila_profesores['EmailReal'] ?: $fila_profesores['Email']; ?></td>
                                
                                <td>
                                   
			   <?php
			  
				if($fila_profesores['idTipoParticipacion'] != 3 && $fila_profesores['idTipoParticipacion'] != 1 && $fila_profesores['idTipoParticipacion'] != 2 && $fila_profesores['idTipoParticipacion'] != 10){
					$state="";
				}else{
					$state="disabled";
				}
			  
			  ?>
				  <select class="form-select form-select-sm" 
        onchange="actualizarFuncion(this, <?php echo $fila_profesores['idProfesoresCurso']; ?>)" 
        <?php echo $state; ?>>
    <!-- Primero mostramos la opción actual del profesor -->
    <option value="<?php echo $fila_profesores['idTipoParticipacion']; ?>" selected>
        <?php echo utf8_encode($fila_profesores['CargoTexto']); ?>
    </option>
    
    <?php 
    // Solo si el select no está deshabilitado, mostramos las demás opciones
    if ($state != 'disabled') {
        $funcion = "SELECT idTipoParticipacion, CargoTexto 
                    FROM spre_tipoparticipacion 
                    WHERE idTipoParticipacion NOT IN ('1','2','3','10')
                    AND idTipoParticipacion != '{$fila_profesores['idTipoParticipacion']}'";
        $funcion_query = mysqli_query($conexion3, $funcion);
        while($fila_funcion = mysqli_fetch_assoc($funcion_query)) {
        ?>
            <option value="<?php echo $fila_funcion['idTipoParticipacion']; ?>">
                <?php echo utf8_encode($fila_funcion['CargoTexto']); ?>
            </option>
        <?php 
        }
    }
    ?>
</select>
			  </td>
                                </td>
								 <td class="text-center">
									<?php if($horas_formateadas > 0): ?>
										<span class="badge bg-primary"><?php echo $horas_formateadas; ?> hrs</span>
									<?php else: ?>
										<span class="badge bg-secondary">0 hrs</span>
									<?php endif; ?>
								</td>
                                <td>
			<?php if ($state<>'disabled'){ ?>
				<button type="button" 
					onclick="eliminarDocente(<?php echo $fila_profesores['idProfesoresCurso']; ?>)" 
					class="btn btn-outline-danger btn-sm"
					title="Remover docente del curso">
					<i class="bi bi-trash"></i>
				</button>
				<?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- Modal para nuevo docente -->
<div class="modal fade" id="nuevoDocenteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-add"></i> Agregar Nuevo Docente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="nuevoDocenteForm" class="needs-validation" novalidate>
                    <input type="hidden" id="curso" value="<?php echo $_GET['idcurso']; ?>">
                    <input type="hidden" id="flag" value="false">
                    
                    <!-- Campos del formulario existente -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">RUT *</label>
                            <input type="text" class="form-control" id="rut_docente" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombres *</label>
                            <input type="text" class="form-control" id="nombres" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Apellido Paterno *</label>
                            <input type="text" class="form-control" id="paterno" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Apellido Materno</label>
                            <input type="text" class="form-control" id="materno">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Unidad Académica *</label>
                            <select class="form-select" id="unidad_academica" required>
                                <option value="">Seleccione...</option>
                                <!-- Opciones de unidades -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad Externa</label>
                            <input type="text" class="form-control" id="unidad_externa" disabled>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Función *</label>
                            <select class="form-select" id="funcion" required>
                                <option value="">Seleccione...</option>
                                <!-- Opciones de funciones -->
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarDocente">
                    <i class="bi bi-save"></i> Guardar Docente
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Contenedor para notificaciones -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

 

<?php }else{ ?>


<div class="alert alert-danger" role="alert">
  <center><h2><strong>Acceso exclusivo para Profesores Encargados de Curso - <?php echo $rut; ?>- <?php echo $_GET[idcurso]; ?></strong></h2>
  <a class="btn btn-primary" href="http://dpi.med.uchile.cl/planificacion/" role="button">Volver</a></center>
</div>

<?php } ?>