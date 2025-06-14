<?php

// Capturar la salida de errores en lugar de mostrarla
ob_start();
include("conexion.php");
$error_output = ob_get_clean();

// Si hay errores de inclusión, los registramos pero no los mostramos
if (!empty($error_output)) {
    error_log("Errores antes de JSON: " . $error_output);
}

// Asegurarnos de que se envíe el header de contenido correcto.
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        
        // Verificar si el input está vacío
        if (empty($input)) {
            throw new Exception('No se recibieron datos en la solicitud');
        }
        
        // Intentar decodificar el JSON
        $data = json_decode($input, true);
        
        // Verificar si hubo error en la decodificación
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decodificando JSON: ' . json_last_error_msg() . '. Datos recibidos: ' . substr($input, 0, 200));
        }
        
        // Verificar si data es null o no es un array
        if ($data === null || !is_array($data)) {
            throw new Exception('Los datos JSON recibidos no son válidos');
        }
        
        // Verificar si existe el parámetro action
        if (!isset($data['action'])) {
            throw new Exception('Parámetro "action" requerido');
        }
        
        // Ahora procesamos según la acción
        switch ($data['action']) {
           // En el caso 'solicitar' en salas_clinico.php
case 'solicitar':
    try {
        $conn->begin_transaction();
        
        // Verificar que todos los campos necesarios estén presentes
        if (!isset($data['nSalas']) || !isset($data['campus']) || !isset($data['idplanclases'])) {
            throw new Exception('Faltan campos requeridos para la solicitud');
        }
        
        // Convertir idplanclases a entero para evitar problemas de tipo
        $idplanclases = (int)$data['idplanclases'];
        $nSalas = (int)$data['nSalas'];
        $campus = $data['campus'];
        
        // Obtener el comentario de las observaciones
        $comentario = isset($data['observaciones']) ? $data['observaciones'] : '';
        
        // Actualizar planclases_test para cursos clínicos
        $stmt = $conn->prepare("UPDATE planclases_test SET pcl_nSalas = ?, pcl_campus = ? WHERE idplanclases = ?");
        if ($stmt === false) {
            throw new Exception("Error preparando la consulta de actualización: " . $conn->error);
        }
        $stmt->bind_param("isi", $nSalas, $campus, $idplanclases);
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando la consulta de actualización: " . $stmt->error);
        }
        
        // Preparar la consulta de inserción con el nombre de columna correcto
        $insertStmt = $conn->prepare("INSERT INTO asignacion_piloto (idplanclases, idEstado, Comentario) VALUES (?, 0, ?)");
        if ($insertStmt === false) {
            throw new Exception("Error preparando la consulta de inserción: " . $conn->error);
        }
        
        // Insertar registros
        for ($i = 0; $i < $nSalas; $i++) {
            $insertStmt->bind_param("is", $idplanclases, $comentario);
            if (!$insertStmt->execute()) {
                throw new Exception("Error ejecutando la inserción: " . $insertStmt->error);
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en solicitar: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
                
            case 'modificar':
                try {
                    $conn->begin_transaction();
                    
                    // Verificar estado actual
                    $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(idEstado) as maxEstado 
                                           FROM asignacion_piloto 
                                           WHERE idplanclases = ?");
                    $stmt->bind_param("i", $data['idplanclases']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $currentState = $result->fetch_assoc();
                    
                    if ($currentState['maxEstado'] == 0) { // Solo modificar si están en estado 0
                        // Actualizar planclases_test
                        $stmt = $conn->prepare("UPDATE planclases_test 
                                              SET pcl_nSalas = ?, pcl_campus = ? 
                                              WHERE idplanclases = ?");
                        $stmt->bind_param("isi", $data['nSalas'], $data['campus'], $data['idplanclases']);
                        $stmt->execute();
                        
                        // Ajustar número de asignaciones
                        $diff = $data['nSalas'] - $currentState['count'];
                        
                        if ($diff > 0) {
                            // Agregar nuevas asignaciones
                            $stmt = $conn->prepare("INSERT INTO asignacion_piloto (idplanclases, idEstado, observaciones) VALUES (?, 0, ?)");
                            for ($i = 0; $i < $diff; $i++) {
                                $stmt->bind_param("is", $data['idplanclases'], $data['observaciones']);
                                $stmt->execute();
                            }
                        } elseif ($diff < 0) {
                            // Eliminar asignaciones sobrantes
                            $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                                  WHERE idplanclases = ? AND idEstado = 0 
                                                  ORDER BY idAsignacion DESC LIMIT ?");
                            $limit = abs($diff);
                            $stmt->bind_param("ii", $data['idplanclases'], $limit);
                            $stmt->execute();
                        }
                        
                        // Actualizar observaciones en todas las asignaciones
                        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                              SET observaciones = ? 
                                              WHERE idplanclases = ? AND idEstado = 0");
                        $stmt->bind_param("si", $data['observaciones'], $data['idplanclases']);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    echo json_encode(['success' => true]);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
                
            case 'modificar_asignada':
                try {
                    $conn->begin_transaction();
                    
                    // Obtener datos actuales
                    $stmt = $conn->prepare("SELECT COUNT(*) as count 
                                           FROM asignacion_piloto 
                                           WHERE idplanclases = ? AND idEstado = 3");
                    $stmt->bind_param("i", $data['idplanclases']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $currentAssigned = $result->fetch_assoc()['count'];
                    
                    // Actualizar planclases primero
                    $stmt = $conn->prepare("UPDATE planclases_test 
                                          SET pcl_nSalas = ? 
                                          WHERE idplanclases = ?");
                    $stmt->bind_param("ii", $data['nSalas'], $data['idplanclases']);
                    $stmt->execute();
                    
                    // Cambiar todas las asignaciones existentes a estado 1
                    $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                          SET idEstado = 1, observaciones = ?
                                          WHERE idplanclases = ? AND idEstado = 3");
                    $stmt->bind_param("si", $data['observaciones'], $data['idplanclases']);
                    $stmt->execute();
                    
                    // Calcular diferencia
                    $diff = intval($data['nSalas']) - $currentAssigned;
                    
                    if ($diff > 0) {
                        // Agregar nuevas asignaciones
                        $stmt = $conn->prepare("INSERT INTO asignacion_piloto 
                                              (idplanclases, idEstado, observaciones) VALUES (?, 1, ?)");
                        for ($i = 0; $i < $diff; $i++) {
                            $stmt->bind_param("is", $data['idplanclases'], $data['observaciones']);
                            $stmt->execute();
                        }
                    } elseif ($diff < 0) {
                        // Eliminar asignaciones sobrantes
                        $limit = abs($diff);
                        $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                              WHERE idplanclases = ? AND idEstado = 1 
                                              ORDER BY idAsignacion DESC LIMIT ?");
                        $stmt->bind_param("ii", $data['idplanclases'], $limit);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    echo json_encode(['success' => true]);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
                
            case 'obtener_datos_solicitud':
                try {
                    // Obtener datos básicos
                    $stmt = $conn->prepare("SELECT p.pcl_campus, p.pcl_nSalas,
                                           (SELECT COUNT(*) FROM asignacion_piloto 
                                            WHERE idplanclases = p.idplanclases 
                                            AND idEstado = 3) as salas_asignadas,
                                           (SELECT observaciones FROM asignacion_piloto
                                            WHERE idplanclases = p.idplanclases
                                            LIMIT 1) as observaciones
                                           FROM planclases_test p 
                                           WHERE p.idplanclases = ?");
                    
                    $stmt->bind_param("i", $data['idPlanClase']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $datos = $result->fetch_assoc();
                    
                    if ($datos) {
                        echo json_encode([
                            'success' => true,
                            'pcl_campus' => $datos['pcl_campus'],
                            'pcl_nSalas' => $datos['pcl_nSalas'],
                            'observaciones' => $datos['observaciones'],
                            'estado' => $datos['salas_asignadas'] > 0 ? 3 : 0
                        ]);
                    } else {
                        throw new Exception('No se encontraron datos para esta actividad');
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
            
            case 'obtener_cupo_curso':
                try {
                    // Obtener el ID del curso a partir del ID de plan de clases
                    $stmt = $conn->prepare("SELECT cursos_idcursos FROM planclases_test WHERE idplanclases = ?");
                    $stmt->bind_param("i", $data['idPlanClase']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    if ($row) {
                        $idCurso = $row['cursos_idcursos'];
                        
                        // Consultar el cupo del curso
                        $stmtCupo = $conexion3->prepare("SELECT Cupo FROM spre_cursos WHERE idCurso = ?");
                        $stmtCupo->bind_param("i", $idCurso);
                        $stmtCupo->execute();
                        $resultCupo = $stmtCupo->get_result();
                        $cupoData = $resultCupo->fetch_assoc();
                        
                        if ($cupoData) {
                            echo json_encode([
                                'success' => true,
                                'cupo' => $cupoData['Cupo']
                            ]);
                        } else {
                            throw new Exception('No se encontró información de cupo para este curso');
                        }
                    } else {
                        throw new Exception('No se encontró el curso para esta actividad');
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
                
            case 'obtener_salas_asignadas':
                try {
                    $stmt = $conn->prepare("SELECT idAsignacion, idSala 
                                           FROM asignacion_piloto 
                                           WHERE idplanclases = ? 
                                           AND idSala IS NOT NULL 
                                           AND idEstado = 3");
                    $stmt->bind_param("i", $data['idPlanClase']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $salas = array();
                    while ($row = $result->fetch_assoc()) {
                        $salas[] = $row;
                    }
                    
                    echo json_encode(['success' => true, 'salas' => $salas]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
                
            case 'liberar':
                try {
                    $conn->begin_transaction();
                    
                    // Obtener el idplanclases
                    $stmt = $conn->prepare("SELECT idplanclases FROM asignacion_piloto WHERE idAsignacion = ?");
                    $stmt->bind_param("i", $data['idAsignacion']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $idplanclases = $result->fetch_assoc()['idplanclases'];
                    
                    // Liberar la sala
                    $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                           SET idSala = NULL, idEstado = 4 
                                           WHERE idAsignacion = ?");
                    $stmt->bind_param("i", $data['idAsignacion']);
                    $stmt->execute();
                    
                    // Actualizar el número de salas
                    $stmt = $conn->prepare("UPDATE planclases_test 
                                          SET pcl_nSalas = pcl_nSalas - 1 
                                          WHERE idplanclases = ? AND pcl_nSalas > 0");
                    $stmt->bind_param("i", $idplanclases);
                    $stmt->execute();
                    
                    $conn->commit();
                    echo json_encode(['success' => true]);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
         default:
                throw new Exception('Acción no reconocida: ' . $data['action']);
        }
        
        exit; // Terminar después de procesar exitosamente
        
    } catch (Exception $e) {
        // Manejar cualquier excepción que haya ocurrido
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

$idCurso = isset($_GET['curso']) ? $_GET['curso'] : 0;

// Consultar el cupo del curso
$stmtCupo = $conexion3->prepare("SELECT Cupo FROM spre_cursos WHERE idCurso = ?");
$stmtCupo->bind_param("i", $idCurso);
$stmtCupo->execute();
$resultCupo = $stmtCupo->get_result();
$cupoData = $resultCupo->fetch_assoc();
$cupoCurso = $cupoData ? $cupoData['Cupo'] : 0;

$query = "SELECT
    p.idplanclases,
    p.pcl_tituloActividad,
    p.pcl_Fecha,
    p.pcl_Inicio,
    p.pcl_Termino,
    p.pcl_TipoSesion,
    p.pcl_SubTipoSesion,
    p.pcl_campus,
    p.pcl_alumnos,
    p.pcl_nSalas,
    p.pcl_condicion,
    p.dia,
    (SELECT GROUP_CONCAT(DISTINCT idSala)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases AND idEstado != 4) AS salas_asignadas,
    (SELECT COUNT(*)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases AND idEstado = 3) AS salas_confirmadas,
    (SELECT COUNT(*)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases 
     AND idEstado = 0) AS salas_solicitadas
FROM planclases_test p
WHERE p.cursos_idcursos = ? 
AND p.pcl_tituloActividad != ''
ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idCurso);
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Estilos específicos para salas -->
<style>
    .badge-secondary { background-color: #6c757d; }
    .badge-info { background-color: #0dcaf0; }
    .badge-success { background-color: #198754; }
    .badge-warning { background-color: #ffc107; }
</style>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Gestión de Salas para Curso Clínico</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i> 
            La gestión de salas para cursos clínicos permitirá solicitar espacios físicos para las actividades planificadas.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Actividad</th>
                        <th>Tipo</th>
                        <th>Campus</th>
                        <th>N° Salas</th>
                        <th>Sala</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): 
                        $fecha = new DateTime($row['pcl_Fecha']);
                        $tieneAsignaciones = !empty($row['salas_asignadas']);
                        $tieneSolicitudes = $row['salas_solicitadas'] > 0;
                        $todasConfirmadas = $row['salas_confirmadas'] == $row['pcl_nSalas'] && $row['pcl_nSalas'] > 0;
                    ?>
                    <tr data-id="<?php echo $row['idplanclases']; ?>" data-alumnos="<?php echo $cupoCurso; ?>">
                        <td><?php echo $row['idplanclases']; ?></td>
                        <td><?php echo $fecha->format('d/m/Y'); ?></td>
                        <td><?php echo substr($row['pcl_Inicio'], 0, 5) . ' - ' . substr($row['pcl_Termino'], 0, 5); ?></td>
                        <td><?php echo $row['pcl_tituloActividad']; ?></td>
                        <td>
                            <?php echo $row['pcl_TipoSesion']; ?>
                            <?php if($row['pcl_SubTipoSesion']): ?>
                                <br><small class="text-muted"><?php echo $row['pcl_SubTipoSesion']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['pcl_campus'] ?: 'No definido'; ?></td>
                        <td><?php echo $row['pcl_nSalas'] ?: '0'; ?></td>
                        <td>
                            <?php if($tieneAsignaciones): ?>
                                <ul class="list-unstyled m-0">
                                    <?php 
                                    $salas = explode(',', $row['salas_asignadas']);
                                    foreach($salas as $sala): 
                                    ?>
                                        <li><span class="badge bg-success"><?php echo $sala; ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="badge bg-secondary">Sin sala</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($tieneAsignaciones): ?>
                                <?php if($todasConfirmadas): ?>
                                    <span class="badge bg-success">Asignada</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Parcialmente asignada</span>
                                <?php endif; ?>
                            <?php elseif($tieneSolicitudes): ?>
                                <span class="badge bg-info">Solicitada</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <?php if($tieneAsignaciones || $tieneSolicitudes): ?>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            onclick="modificarSala(<?php echo $row['idplanclases']; ?>)">
                                        <i class="bi bi-pencil"></i> Modificar
                                    </button>
                                    <?php if($tieneAsignaciones): ?>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                              onclick="mostrarModalLiberarSalas(<?php echo $row['idplanclases']; ?>)">
                                          <i class="bi bi-x-circle"></i> Liberar
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="solicitarSala(<?php echo $row['idplanclases']; ?>)">
                                        <i class="bi bi-plus-circle"></i> Solicitar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center">No hay actividades disponibles para gestionar salas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Solicitar/Modificar Sala -->
<div class="modal fade" id="salaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="salaModalTitle">Gestionar Sala</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i>
                    Complete la información requerida para solicitar o modificar la asignación de salas para esta actividad.
                </div>

                <form id="salaForm">
                    <input type="hidden" id="idplanclases" name="idplanclases">
                    <input type="hidden" id="action" name="action">
                    
                    <div class="mb-3">
                        <label class="form-label">Campus</label>
                        <select class="form-select" id="campus" name="campus" required>
                            <option value="">Seleccione un campus</option>
                            <option value="Norte">Norte</option>
                            <option value="Sur">Sur</option>
                            <option value="Occidente">Occidente</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">N° de salas requeridas para la actividad</label>
                        <select class="form-select" id="nSalas" name="nSalas" required onchange="calcularAlumnosPorSala()">
                            <?php for($i = 1; $i <= 15; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <small class="text-muted">Si requiere más de 15 salas, contactar directamente a dpi.med@uchile.cl</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">N° de alumnos totales</label>
                        <input type="number" class="form-control" id="alumnosTotales" name="alumnosTotales" readonly>
                        <small class="text-muted">Este valor se obtiene automáticamente del cupo del curso</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">N° de alumnos por sala</label>
                        <input type="number" class="form-control" id="alumnosPorSala" name="alumnosPorSala" readonly>
                        <small class="text-muted">Este valor se calcula automáticamente</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">¿Requiere accesibilidad para personas con movilidad reducida?</label>
                        <select class="form-select" id="movilidadReducida" name="movilidadReducida" required>
                            <option value="No">No</option>
                            <option value="Si">Si</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones y requerimientos especiales</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                placeholder="Detalles adicionales como: equipamiento especial requerido, disposición de la sala, etc." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="guardarSala()">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Liberar Salas -->
<div class="modal fade" id="liberarSalaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Liberar Sala</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    Seleccione las salas que desea liberar. Esta acción no se puede deshacer.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sala</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="listaSalasAsignadas">
                            <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>



</body>
</html>