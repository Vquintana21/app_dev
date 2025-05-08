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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipo Docente</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
	
	  <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
	<!-- CSS personalizado -->
	<link href="estilo2.css" rel="stylesheet">

</head>
<body class="bg-light">
    

    <div class="container py-4">      

        <!-- Course Info -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <h4 class="card-title">
                    <?php echo $fila_curso['CodigoCurso']; ?> 
                    <?php echo utf8_encode($fila_curso['nombreCurso']); ?>
                </h4>
                <h5 class="text-muted">Sección <?php echo $fila_curso['seccion']; ?></h5>
            </div>
        </div>

        <!-- Faculty Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <select class="form-select" id="docente" data-live-search="true">
                            <option value="" selected disabled>🔍 Buscar Docente</option>
                            <?php 
                            $elegir = "SELECT * FROM spre_bancodocente ORDER BY Funcionario ASC";
                            $elegir_query = mysqli_query($conexion3,$elegir);
                            while($fila_elegir = mysqli_fetch_assoc($elegir_query)){
                            ?>
                            <option value="<?php echo $fila_elegir["rut"]; ?>">
                                <?php echo $fila_elegir["rut"]; ?>
                                - <?php echo utf8_encode($fila_elegir["Funcionario"]); ?>
                            </option>
                            <?php } ?>
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
        
<script>
//$(document).ready(function() {
//    $('#docente').select2({
//        theme: 'bootstrap-5',
//        placeholder: '🔍 Buscar Docente',
//        allowClear: true,
//        language: {
//            noResults: function() {
//                return "No se encontraron docentes";
//            },
//            searching: function() {
//                return "Buscando...";
//            }
//        },
//        width: '100%',
//        dropdownParent: $('#docente').parent()
//    });
//});
</script>

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
                                <th>Función</th>
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
                            ORDER BY spre_tipoparticipacion.idTipoParticipacion, Nombres ASC";
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
						<option value="<?php echo $fila_profesores['idTipoParticipacion']; ?>">
							<?php echo utf8_encode($fila_profesores['CargoTexto']); ?>
						</option>
						<?php 
						$funcion = "SELECT idTipoParticipacion, CargoTexto 
									FROM spre_tipoparticipacion 
									WHERE idTipoParticipacion NOT IN ('1','2','3','10')";
						$funcion_query = mysqli_query($conexion3,$funcion);
						while($fila_funcion = mysqli_fetch_assoc($funcion_query)) {
						?>
							<option value="<?php echo $fila_funcion['idTipoParticipacion']; ?>">
								<?php echo utf8_encode($fila_funcion['CargoTexto']); ?>
							</option>
						<?php } ?>
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
                                    <button type="button" 
        onclick="eliminarDocente(<?php echo $fila_profesores['idProfesoresCurso']; ?>)" 
        class="btn btn-outline-danger btn-sm"
        title="Remover docente">
    <i class="bi bi-trash"></i>
</button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor para notificaciones -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

 

    <script>
 //   $(document).ready(function() {
 //       // Initialize Select2
 //       $('#docente').select2({
 //           theme: 'bootstrap-5',
 //           placeholder: 'Buscar docente...',
 //           allowClear: true
 //       });
 //
 //       // Enable/disable add button based on selection
 //       $('#docente').on('change', function() {
 //           $('#boton_agregar').prop('disabled', !$(this).val());
 //       });
 //
 //       // Initialize all tooltips
 //       const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
 //       tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
 //   });
 //
 // function showNotification(message, type = 'success') {
 //     const toast = `
 //         <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
 //             <div class="d-flex">
 //                 <div class="toast-body">
 //                     ${message}
 //                 </div>
 //                 <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
 //             </div>
 //         </div>
 //     `;
 //     
 //     $('.toast-container').append(toast);
 //     const toastElement = new bootstrap.Toast($('.toast').last());
 //     toastElement.show();
 // }
 
 
    </script>



</body>
</html>
<?php }else{ ?>


<div class="alert alert-danger" role="alert">
  <center><h2><strong>Acceso exclusivo para Profesores Encargados de Curso - <?php echo $rut; ?>- <?php echo $_GET[idcurso]; ?></strong></h2>
  <a class="btn btn-primary" href="http://dpi.med.uchile.cl/planificacion/" role="button">Volver</a></center>
</div>

<?php } ?>