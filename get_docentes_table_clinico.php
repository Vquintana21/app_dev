<?php

// Habilitar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
?>

<!-- Formulario de búsqueda y asignación de docentes -->
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

<!-- Tabla de docentes -->
<div class="card">
    <div class="card-body">
        <!-- Tabla de docentes organizada y cuadrada -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 5%"></th>
                <th style="width: 35%">Docente</th>
                <th style="width: 25%">Correo</th>
                <th style="width: 20%">Función</th>
                <th style="width: 10%" class="text-center">Total Horas Directas</th>
                <th style="width: 5%" class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody id="docentes-table-body">
            <?php
            try {
                // Consulta principal para obtener los docentes del curso
                $query = "SELECT p.*, pc.idProfesoresCurso, pc.idTipoParticipacion, 
                                 t.CargoTexto, pc.rut as rutDocente, pc.unidad_academica_docente
                          FROM spre_profesorescurso pc
                          INNER JOIN spre_personas p ON pc.rut = p.Rut 
                          INNER JOIN spre_tipoparticipacion t ON pc.idTipoParticipacion = t.idTipoParticipacion 
                          WHERE pc.idcurso = ? AND pc.Vigencia = '1' 
                          AND pc.idTipoParticipacion NOT IN ('10') 
                          ORDER BY pc.idTipoParticipacion, p.Nombres ASC";

                $stmt = $conexion3->prepare($query);
                if (!$stmt) {
                    throw new Exception('Error preparando consulta: ' . $conexion3->error);
                }

                $stmt->bind_param("i", $idcurso);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 0) {
                    echo '<tr><td colspan="6" class="text-center">No hay docentes asignados a este curso</td></tr>';
                } else {
                    while ($row = $result->fetch_assoc()) {
                        // Obtener las horas clínicas reales desde docenteclases
                        $horas_formateadas = 0;
                        
                        try {
                            $queryHoras = "SELECT SUM(horas) as total_horas 
                                           FROM docenteclases 
                                           WHERE rutDocente = ? AND idCurso = ? AND vigencia = 1";
                            $stmtHoras = $conn->prepare($queryHoras);
                            
                            if ($stmtHoras) {
                                $stmtHoras->bind_param("si", $row['rutDocente'], $idcurso);
                                $stmtHoras->execute();
                                $resultHoras = $stmtHoras->get_result();
                                $horasData = $resultHoras->fetch_assoc();
                                
                                if (isset($horasData['total_horas']) && $horasData['total_horas'] !== null) {
                                    $horas_formateadas = floatval($horasData['total_horas']);
                                } else {
                                    $horas_formateadas = 0;
                                }
                                
                                $stmtHoras->close();
                            }
                        } catch (Exception $e) {
                            $horas_formateadas = 0;
                        }
                        
                        $state = ($row['idTipoParticipacion'] != 3 && $row['idTipoParticipacion'] != 1 && $row['idTipoParticipacion'] != 2 && $row['idTipoParticipacion'] != 10) ? "" : "disabled";
                        
                        // Valores seguros
                        $idProfesoresCurso = isset($row['idProfesoresCurso']) ? $row['idProfesoresCurso'] : 0;
                        $rutDocente = isset($row['rutDocente']) ? $row['rutDocente'] : '';
                        $unidadAcademica = isset($row['unidad_academica_docente']) ? $row['unidad_academica_docente'] : '';
                        $unidadAcademica = str_replace(array('�', '�'), array('ó', 'é'), $unidadAcademica);
                        ?>
                        <tr data-debug="docente-<?php echo $idProfesoresCurso; ?>">
                            <!-- Icono -->
                            <td class="text-center">
                                <i class="bi bi-person text-primary"></i>
                            </td>
                            
                            <!-- Docente -->
                            <td>
                                <div>
                                    <div class="fw-bold">
                                        <?php echo utf8_encode($row['Nombres'].' '.$row['Paterno'].' '.$row['Materno']); ?>
                                    </div>
                                    <small class="text-muted">RUT: <?php echo $rutDocente; ?></small>
                                </div>
                            </td>
                            
                            <!-- Correo -->
                            <td>
                                <?php echo $row['EmailReal'] ? $row['EmailReal'] : $row['Email']; ?>
                            </td>
                            
                            <!-- Función -->
                            <td>
                                <select class="form-select form-select-sm" 
                                        id="funcion_<?php echo $idProfesoresCurso; ?>" 
                                        name="funcion" 
                                        onchange="actualizarFuncion(this,<?php echo $idProfesoresCurso; ?>)" 
                                        <?php echo $state; ?>>
                                    <option value="<?php echo $row['idTipoParticipacion']; ?>">
                                        <?php echo utf8_encode($row['CargoTexto']); ?>
                                    </option>
                                    <?php 
                                    if ($state != 'disabled') {
                                        $funcion_query = mysqli_query($conexion3,"SELECT * FROM spre_tipoparticipacion WHERE idTipoParticipacion NOT IN ('1','2','3','10')");
                                        while($fila_funcion = mysqli_fetch_assoc($funcion_query)): 
                                        ?>
                                            <option value="<?php echo $fila_funcion['idTipoParticipacion']; ?>">
                                                <?php echo utf8_encode($fila_funcion['CargoTexto']); ?>
                                            </option>
                                        <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </td>
                            
                            <!-- Horas -->
                            <td class="text-center">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                    <div class="input-group input-group-sm" style="width: 120px;">
                                        <input type="number" 
                                               class="form-control text-center hours-input" 
                                               id="horas_<?php echo $idProfesoresCurso; ?>"
                                               value="<?php echo number_format($horas_formateadas, 1); ?>" 
                                               min="0" 
                                               step="0.5"
                                               data-id-profesor="<?php echo $idProfesoresCurso; ?>"
                                               data-rut="<?php echo $rutDocente; ?>"
                                               data-unidad-academica="<?php echo $unidadAcademica; ?>"
                                               data-original-value="<?php echo $horas_formateadas; ?>"
                                               placeholder="0">
                                        <span class="input-group-text">hrs</span>
                                    </div>
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm" 
                                            onclick="guardarHorasProfesor(<?php echo $idProfesoresCurso; ?>)"
                                            title="Guardar horas"
                                            style="padding: 2px 6px;">
                                        <i class="bi bi-floppy"></i>
                                    </button>
                                </div>
                            </td>
                            
                            <!-- Acciones -->
                            <td class="text-center">
                                <?php if ($state != 'disabled'): ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm" 
                                            onclick="eliminarDocente(<?php echo $idProfesoresCurso; ?>)"
                                            title="Eliminar docente">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted" title="No se puede eliminar">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                echo '<tr><td colspan="6" class="text-center text-danger">Error: ' . $e->getMessage() . '</td></tr>';
            }
            ?>
        </tbody>
    </table>
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
        placeholder: '🔍 Buscar Docente',
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
    
    // Habilitar/deshabilitar botón según la selección
    $('#docente').on('change', function() {
        $('#boton_agregar').prop('disabled', !$(this).val());
    });
    
    // Configurar botón de agregar docente
    $('#boton_agregar').on('click', function() {
        let rut_docente = $('#docente').val();
        if (rut_docente) {
            $.ajax({
                url: 'asignar_docente.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    rut_docente: rut_docente,
                    idcurso: <?php echo $idcurso; ?>,
                    funcion: 5 // Función por defecto (Colaborador)
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar notificación
                        showNotification('Docente asignado correctamente', 'success');
                        
                        // Recargar tabla de docentes
                        reloadDocentesTableWithHours();
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
    
    // Configurar botón nuevo docente
    $('#nuevo-docente-btn').on('click', function() {
        window.location.href = "2_crear_docente.php?idcurso=<?php echo $idcurso; ?>";
    });
});

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

// Función para guardar las horas docentes (versión clínica - edición manual)
function guardarHorasDocente(idProfesoresCurso, horas) {
    // Validar que horas sea un número válido
    if (isNaN(horas) || horas < 0) {
        showNotification('Por favor ingrese un número válido de horas', 'danger');
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
            showNotification('Error de comunicación con el servidor', 'danger');
        }
    });
}

// Función para actualizar función del docente
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
                showNotification('Función actualizada exitosamente', 'success');
            } else {
                showNotification('Error al actualizar la función', 'danger');
            }
        },
        error: function() {
            showNotification('Error de comunicación con el servidor', 'danger');
        }
    });
}

// Función para eliminar docente
function eliminarDocente(id) {
    if(!id) return;
    
    if(confirm('¿Está seguro que desea eliminar este docente del equipo?')) {
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
                showNotification('Error de comunicación con el servidor', 'danger');
            }
        });
    }
}

// Script de debug para verificar que los datos se cargan correctamente
console.log('🔄 Tabla de docentes cargada. Verificando inputs de horas...');
setTimeout(function() {
    $('.hours-input').each(function(index, input) {
        console.log('Input ' + (index + 1) + ':', {
            id: input.id,
            value: input.value,
            'data-id-profesor': $(input).attr('data-id-profesor'),
            'data-rut': $(input).attr('data-rut'),
            'data-unidad-academica': $(input).attr('data-unidad-academica'),
            'data-original-value': $(input).attr('data-original-value')
        });
    });
}, 100);
</script>