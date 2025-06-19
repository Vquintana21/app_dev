<?php
// get_docentes_table_regulares.php
// Archivo específico para mostrar tabla de docentes en CURSOS REGULARES

// Habilitar reporte de errores para debug
error_reporting(E_ALL);
//ini_set('display_errors', 1);

// Incluir conexión
include("conexion.php");

// Verificar parámetro
if (!isset($_GET['idcurso'])) {
    die('ID de curso no proporcionado');
}

$idcurso = intval($_GET['idcurso']);

// Verificar conexiones
if (!isset($conexion3)) {
    die('Error: conexion3 no disponible');
}

if (!isset($conn)) {
    die('Error: conn no disponible');
}

function decimalAHorasMinutos($decimal) {
    $horas = floor($decimal);
    $minutos = round(($decimal - $horas) * 60);
    
    // Ajustar si el redondeo hace que los minutos lleguen a 60
    if ($minutos >= 60) {
        $horas += 1;
        $minutos = 0;
    }
    
    return sprintf("%02d:%02d", $horas, $minutos);
}

?>

<!-- Formulario de búsqueda y asignación de docentes REGULARES -->
<div class="container py-4"> 

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="text-center">
                <i class="bi bi-person-raised-hand"></i> Instrucciones equipo docente
            </h4>
            
            <ul>
                <li>Si no encuentra al funcionario en el buscador, lo puede agregar en "Nuevo Docente".</li>
                <li>Si se requiere cambio de PEC o coordinador del curso, lo debe solicitar a la dirección de escuela.</li>
                <li><strong>Las horas se calculan automáticamente</strong> desde las actividades programadas.</li>
            </ul>
        </div>
    </div>
  
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8">
                    <select class="form-select" id="docente" data-live-search="true">
                        <option value="" selected disabled>🔍 Buscar Docente (escriba nombre o RUT)</option>
                        <?php 
                        $elegir = "SELECT * FROM spre_bancodocente ORDER BY Funcionario ASC";
                        $elegir_query = mysqli_query($conexion3, $elegir);
                        while($fila_elegir = mysqli_fetch_assoc($elegir_query)){
                        ?>
                        <option value="<?php echo $fila_elegir["rut"]; ?>">
                            <?php echo $fila_elegir["rut"]; ?>
                            - <?php echo utf8_encode($fila_elegir["Funcionario"]); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <div id="search-info" class="small text-muted mt-1" style="display: none;">
                        <i class="bi bi-info-circle"></i> 
                        <span id="search-results-count"></span>
                    </div>
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

    <!-- Tabla de docentes REGULARES -->
    <<div class="card">
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
													FROM `docenteclases_copy` 
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
										<span class="badge bg-primary"><?php echo decimalAHorasMinutos($horas_formateadas); ?> hrs</span>
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
					<i class="bi bi-trash"  ></i>
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

<script>
// Scripts específicos para tabla de docentes REGULARES
console.log('📊 Tabla de docentes regulares cargada');

// Función para eliminar docente en regulares
function eliminarDocente(idProfesorCurso, nombreDocente) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Confirmar eliminación?',
            text: `¿Está seguro de eliminar a "${nombreDocente}" del equipo docente?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarEliminacionDocente(idProfesorCurso, nombreDocente);
            }
        });
    } else {
        if (confirm(`¿Está seguro de eliminar a "${nombreDocente}" del equipo docente?`)) {
            ejecutarEliminacionDocente(idProfesorCurso, nombreDocente);
        }
    }
}

function ejecutarEliminacionDocente(idProfesorCurso, nombreDocente) {
    console.log('🗑️ Eliminando docente regular:', idProfesorCurso);
    
    const formData = new FormData();
    formData.append('idProfesoresCurso', idProfesorCurso);
    
    fetch('eliminar_docente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Docente eliminado exitosamente');
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: `${nombreDocente} ha sido eliminado del equipo docente`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
            
            // Recargar tabla
            setTimeout(() => {
                if (typeof reloadDocentesTableRegulares === 'function') {
                    reloadDocentesTableRegulares();
                } else if (typeof reloadDocentesTable === 'function') {
                    reloadDocentesTable();
                } else {
                    location.reload();
                }
            }, 1000);
            
        } else {
            console.error('❌ Error al eliminar docente:', data.message);
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudo eliminar el docente'
                });
            } else {
                alert('Error: ' + (data.message || 'No se pudo eliminar el docente'));
            }
        }
    })
    .catch(error => {
        console.error('❌ Error en petición de eliminación:', error);
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error de comunicación',
                text: 'No se pudo conectar con el servidor'
            });
        } else {
            alert('Error de comunicación con el servidor');
        }
    });
}

// Monitor de cambios para debug
console.log('📈 Monitoreo de horas calculadas en regulares activado');

// Inicializar tooltips si está disponible Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
</script>