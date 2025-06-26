<?php

//asignar docentes clinico.php
header ('Content-type: text/html; charset=utf-8');
session_start(); 
error_reporting(0);
include("conexion.php");
$rut = '162083015';
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);
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

//Control Profesor (驴Es profesor encargado del curso?)
$ValidarProfe = "SELECT * FROM spre_profesorescurso WHERE idcurso='$_GET[idcurso]' AND rut='$rut_niv' AND vigencia='1' AND idTipoParticipacion IN ('1','2','3','8','10') "; 
$ValidarQuery = mysqli_query($conexion3,$ValidarProfe);
$control_profe = mysqli_num_rows($ValidarQuery);

if($rut!='' && $control_profe > 0){
?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipo Docente - Cursos Clinicos</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
	
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- CSS personalizado -->
    <link href="estilo.css" rel="stylesheet">
</head>
<body class="bg-light">
    
    <div class="container-fluid py-4">      

        <!-- Course Info 
        <div class="card mb-4">
            <div class="card-body text-center">
                <h4 class="card-title">
                    <?php // echo $fila_curso['CodigoCurso']; ?> 
                    <?php // echo utf8_encode($fila_curso['nombreCurso']); ?>
                </h4>
                <h5 class="text-muted">Sección <?php // echo $fila_curso['seccion']; ?></h5>
                <div class="badge bg-primary">Curso Clínico</div>
            </div>
        </div>
		-->

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
                        <tbody id="docentes-table-body">
                            <?php 
                            $profesores = "SELECT spre_profesorescurso.idProfesoresCurso, spre_profesorescurso.rut, 
                                          spre_personas.Nombres, spre_personas.Paterno, spre_personas.Materno, 
                                          spre_personas.EmailReal, spre_personas.Email, 
                                          spre_profesorescurso.unidad_academica_docente, 
                                          spre_profesorescurso.idTipoParticipacion, 
                                          spre_tipoparticipacion.CargoTexto,
                                          '0' as horas_clinicas
                                   FROM spre_profesorescurso
                                   INNER JOIN spre_personas ON spre_profesorescurso.rut = spre_personas.Rut 
                                   INNER JOIN spre_tipoparticipacion ON spre_profesorescurso.idTipoParticipacion = spre_tipoparticipacion.idTipoParticipacion 
                                   WHERE idcurso='$_GET[idcurso]' AND Vigencia='1' AND spre_profesorescurso.idTipoParticipacion NOT IN ('10') 
                                   ORDER BY spre_tipoparticipacion.idTipoParticipacion, Nombres ASC";
                            $profesores_query = mysqli_query($conexion3,$profesores);
                            while($fila_profesores = mysqli_fetch_assoc($profesores_query)){
                                // Obtener las horas cl铆nicas (ingresadas manualmente)
                                $horas_formateadas = $fila_profesores['horas_clinicas'] ?: 0;
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
                                        onchange="guardarFuncion(this, <?php echo $fila_profesores['idProfesoresCurso']; ?>)" 
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
                                <td class="text-center">
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control form-control-sm hours-input" 
                                               value="<?php echo $horas_formateadas; ?>" 
                                               min="0" step="0.5" style="max-width: 70px;">
                                        <span class="input-group-text">hrs</span>
                                        <!-- <button class="btn btn-outline-primary btn-sm save-hours-btn" 
                                                onclick="guardarHorasDocente(<?php // echo $fila_profesores['idProfesoresCurso']; ?>, 
                                                    this.closest('td').querySelector('.hours-input').value)">
                                           <i class="bi bi-floppy-fill"></i>-->
                                        </button>
                                    </div>
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
    $(document).ready(function() {
        // Inicializar Select2
        $('#docente').select2({
            theme: 'bootstrap-5',
            placeholder: '馃攳 Buscar Docente',
            allowClear: true,
            language: {
                noResults: function() {
                    return "No se encontraron docentes";
                },
                searching: function() {
                    return "Buscando...";
                }
            },
            width: '100%',
            dropdownParent: $('#docente').parent()
        });
        
        // Habilitar/deshabilitar bot贸n seg煤n la selecci贸n
        $('#docente').on('change', function() {
            $('#boton_agregar').prop('disabled', !$(this).val());
        });
        
        // Configurar bot贸n de agregar docente
       $('#boton_agregar').on('click', function() {
    let rut_docente = $('#docente').val();
    if (rut_docente) {
        $.ajax({
            url: 'asignar_docente.php',
            type: 'POST',
            dataType: 'json',
            data: {
                rut_docente: rut_docente,
                idcurso: <?php echo $_GET['idcurso']; ?>,
                funcion: 5 // Función por defecto (Colaborador)
            },
            success: function(response) {
                if (response.success) {
                    // Mostrar notificación
                    showNotification('Docente asignado correctamente', 'success');
                    
                    // Recargar tabla de docentes
                    reloadDocentesTable();
                    
                    // Asegurarse de que estamos en la pestaña correcta
                    const docenteTab = document.getElementById('docente-tab');
                    if (docenteTab) {
                        // Activar la pestaña usando Bootstrap
                        const tabInstance = new bootstrap.Tab(docenteTab);
                        tabInstance.show();
                    }
                } else {
                    showNotification(response.message || 'Error al asignar docente', 'danger');
                }
            },
            error: function() {
                showNotification('Error de comunicación con el servidor', 'danger');
            }
        });
    }
});
        
        // Configurar bot贸n nuevo docente
        $('#nuevo-docente-btn').on('click', function() {
            window.location.href = "2_crear_docente.php?idcurso=<?php echo $_GET['idcurso']; ?>";
        });
        
        // Cargar tabla de docentes inicialmente
        reloadDocentesTable();
    });

// Reemplaza la función reloadDocentesTable en 1_asignar_docente_clinico.php

function reloadDocentesTable() {
    // Asegurarse de que estamos actualizando el elemento correcto
    const tablaDocentesBody = document.querySelector('#bordered-justified-docente #docentes-table-body');
    
    if (!tablaDocentesBody) {
        console.error('No se encontró el elemento donde colocar la tabla de docentes');
        return;
    }

    $.ajax({
        url: 'get_docentes_table_clinico.php', // ✅ CAMBIO AQUÍ
        type: 'GET',
        data: {
            idcurso: <?php echo $_GET['idcurso']; ?>
        },
        success: function(html) {
            // Actualizar la tabla en la pestaña correcta
            $(tablaDocentesBody).html(html);
            
            // ✅ AGREGAR: Reinicializar las horas después de recargar
            setTimeout(() => {
                if (typeof setupHorasDirectasClinico === 'function') {
                    setupHorasDirectasClinico();
                }
            }, 200);
        },
        error: function() {
            $(tablaDocentesBody).html('<tr><td colspan="6" class="text-center text-danger">Error al cargar los datos</td></tr>');
        }
    });
}

    function showNotification(message, type = 'success') {
        const toast = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        $('.toast-container').append(toast);
        const toastElement = new bootstrap.Toast($('.toast').last());
        toastElement.show();
    }

    // Funci贸n para guardar las horas docentes (versi贸n cl铆nica - edici贸n manual)
    function guardarHorasDocente(idProfesoresCurso, horas) {
        // Validar que horas sea un n煤mero v谩lido
        if (isNaN(horas) || horas < 0) {
            showNotification('Por favor ingrese un n煤mero v谩lido de horas', 'danger');
            return;
        }
        
        $.ajax({
            url: 'guardar_horas_docente.php',
            type: 'POST',
            dataType: 'json',
            data: {
                idProfesoresCurso: idProfesoresCurso,
                horas: horas
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Horas guardadas correctamente', 'success');
                } else {
                    showNotification(response.message || 'Error al guardar las horas', 'danger');
                }
            },
            error: function() {
                showNotification('Error de comunicaci贸n con el servidor', 'danger');
            }
        });
    }

    // Funci贸n para actualizar funci贸n del docente
    function guardarFuncion(selectElement, idProfesoresCurso) {
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
                    showNotification('Funci贸n actualizada exitosamente', 'success');
                } else {
                    showNotification('Error al actualizar la funci贸n', 'danger');
                }
            },
            error: function() {
                showNotification('Error de comunicaci贸n con el servidor', 'danger');
            }
        });
    }

    // Funci贸n para eliminar docente
    function eliminarDocente(id) {
        if(!id) return;
        
        if(confirm('驴Est谩 seguro que desea eliminar este docente del equipo?')) {
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

                        showNotification('Docente removido exitosamente', 'success');
                    } else {
                        showNotification('Error al eliminar el docente', 'danger');
                    }
                },
                error: function() {
                    showNotification('Error de comunicaci贸n con el servidor', 'danger');
                }
            });
        }
    }
    </script>
</body>
</html>
<?php } else { ?>
<div class="alert alert-danger" role="alert">
  <center><h2><strong>Acceso exclusivo para Profesores Encargados de Curso - <?php echo $rut; ?>- <?php echo $_GET[idcurso]; ?></strong></h2>
  <a class="btn btn-primary" href="http://dpi.med.uchile.cl/planificacion/" role="button">Volver</a></center>
</div>
<?php } ?>