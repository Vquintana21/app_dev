<?php
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'solicitar':
                try {
                    $conn->begin_transaction();
                    
                    // Actualizar planclases
                    $stmt = $conn->prepare("UPDATE planclases SET pcl_nSalas = ?, pcl_campus = ? WHERE idplanclases = ?");
                    $stmt->bind_param("isi", $data['nSalas'], $data['campus'], $data['idplanclases']);
                    $stmt->execute();
                    
                    // Actualizar asignaciones
                    if ($data['action'] === 'solicitar') {
                        // Eliminar asignaciones existentes si las hay
                        $stmt = $conn->prepare("DELETE FROM asignacion_piloto WHERE idplanclases = ?");
                        $stmt->bind_param("i", $data['idplanclases']);
                        $stmt->execute();
                        
                        // Crear nuevas asignaciones
                        $stmt = $conn->prepare("INSERT INTO asignacion_piloto (idplanclases, idEstado) VALUES (?, 0)");
                        for ($i = 0; $i < $data['nSalas']; $i++) {
                            $stmt->bind_param("i", $data['idplanclases']);
                            $stmt->execute();
                        }
                    }
                    
                    $conn->commit();
                    echo json_encode(['success' => true]);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;
				
				case 'modificar':
    try {
        $conn->begin_transaction();
        
        // Verificar estado actual de las asignaciones
        $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(idEstado) as maxEstado 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ?");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentState = $result->fetch_assoc();
        
        if ($currentState['maxEstado'] == 0) { // Solo modificar si están en estado 0
            // Actualizar planclases
            $stmt = $conn->prepare("UPDATE planclases 
                                  SET pcl_nSalas = ?, pcl_campus = ? 
                                  WHERE idplanclases = ?");
            $stmt->bind_param("isi", $data['nSalas'], $data['campus'], $data['idplanclases']);
            $stmt->execute();
            
            // Calcular diferencia de asignaciones necesarias
            $diff = $data['nSalas'] - $currentState['count'];
            
            if ($diff > 0) {
                // Agregar nuevas asignaciones
                $stmt = $conn->prepare("INSERT INTO asignacion_piloto (idplanclases, idEstado) VALUES (?, 0)");
                for ($i = 0; $i < $diff; $i++) {
                    $stmt->bind_param("i", $data['idplanclases']);
                    $stmt->execute();
                }
            } elseif ($diff < 0) {
                // Eliminar asignaciones sobrantes
                $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                      WHERE idplanclases = ? AND idEstado = 0 
                                      LIMIT ?");
                $limit = abs($diff);
                $stmt->bind_param("ii", $data['idplanclases'], $limit);
                $stmt->execute();
            }
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

        // Debug
        error_log("Iniciando modificar_asignada");
        error_log("Data recibida: " . json_encode($data));
        
        // 1. Obtener cantidad actual de salas asignadas
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentAssigned = $result->fetch_assoc()['count'];
        
        error_log("Salas actualmente asignadas: " . $currentAssigned);

        // 2. Actualizar planclases primero
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ? 
                              WHERE idplanclases = ?");
        $stmt->bind_param("ii", $data['nSalas'], $data['idplanclases']);
        $stmt->execute();
        error_log("planclases actualizado");

        // 3. Cambiar todas las asignaciones existentes a estado 1
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                              SET idEstado = 1
                              WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        error_log("asignaciones pasadas a estado 1");

        // 4. Calcular diferencia
        $diff = intval($data['nSalas']) - $currentAssigned;
        error_log("Diferencia calculada: " . $diff);

        if ($diff > 0) {
            // Agregar nuevas asignaciones
            $stmt = $conn->prepare("INSERT INTO asignacion_piloto 
                                  (idplanclases, idEstado) VALUES (?, 1)");
            for ($i = 0; $i < $diff; $i++) {
                $stmt->bind_param("i", $data['idplanclases']);
                $stmt->execute();
            }
            error_log("Agregadas " . $diff . " nuevas asignaciones");
        } elseif ($diff < 0) {
            // Eliminar asignaciones sobrantes
            $limit = abs($diff);
            $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                  WHERE idplanclases = ? AND idEstado = 1 
                                  ORDER BY idAsignacion DESC LIMIT ?");
            $stmt->bind_param("ii", $data['idplanclases'], $limit);
            $stmt->execute();
            error_log("Eliminadas " . abs($diff) . " asignaciones");
        }

        $conn->commit();
        error_log("Transacción completada con éxito");

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en modificar_asignada: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;
				
				case 'obtener_datos_solicitud':
    try {
        // Primero obtenemos los datos básicos
        $stmt = $conn->prepare("SELECT p.pcl_campus, p.pcl_nSalas,
                               (SELECT COUNT(*) FROM asignacion_piloto 
                                WHERE idplanclases = p.idplanclases 
                                AND idEstado = 3) as salas_asignadas
                               FROM planclases p 
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
        
        // Obtener el idplanclases antes de liberar la sala
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
        
        // Actualizar el número de salas en planclases
        $stmt = $conn->prepare("UPDATE planclases 
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
        }
        exit;
    }
}

$idCurso = $_GET['curso'];

$query = "SELECT
    p.idplanclases,
    p.pcl_tituloActividad,
    p.pcl_Fecha,
    p.pcl_Inicio,
    p.pcl_Termino,
    p.pcl_TipoSesion,
    p.pcl_SubTipoSesion,
    p.pcl_campus,
	pcl_alumnos,
    p.pcl_nSalas,
    t.pedir_sala,
    (SELECT GROUP_CONCAT(DISTINCT idSala)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases AND idEstado != 4) AS salas_asignadas,
    (SELECT COUNT(*)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases AND idEstado = 3) AS salas_confirmadas,
    (
        SELECT COUNT(*)
        FROM asignacion_piloto
        WHERE idplanclases = p.idplanclases 
        AND idEstado = 0
    ) AS salas_solicitadas
FROM planclases p
INNER JOIN pcl_TipoSesion t ON p.pcl_TipoSesion = t.tipo_sesion
WHERE p.cursos_idcursos = ? 
AND t.pedir_sala = 1 
AND p.pcl_SubTipoSesion = t.Sub_tipo_sesion 
AND p.pcl_tituloActividad != ''
ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idCurso);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Salas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Gestión de Salas</h5>
    </div>
    <div class="card-body">
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
						$todasConfirmadas = $row['salas_confirmadas'] == $row['pcl_nSalas'];
					?>
                    <tr data-id="<?php echo $row['idplanclases']; ?>" data-alumnos="<?php echo $row['pcl_alumnos']; ?>">
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
                        <td><?php echo $row['pcl_campus']; ?></td>
						<!-- mostrar cero salas si es activi distinta a clase en la pimera insercion de una actividad, tomar el # de asignacion-->
                        <td><?php echo $row['pcl_nSalas']; ?></td>
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
                    Con el objetivo de ayudarle con el envío de solicitudes a la unidad de aulas, en las actividades de tipo Clase teórica hemos dispuesto la función de asignación automática de salas. En esta versión todas las solicitudes de este tipo de actividad se cargan por defecto y puede modificarla solo en el caso de ser necesario.
                </div>

                <form id="salaForm">
                    <input type="hidden" id="idplanclases" name="idplanclases">
                    <input type="hidden" id="action" name="action">
                    
                    <div class="mb-3">
                        <label class="form-label">¿Requiere sala para esta actividad?</label>
                        <select class="form-select" id="requiereSala" name="requiereSala" required>
                            <option value="Si">Si</option>
                            <option value="No">No</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Campus</label>
                        <select class="form-select" id="campus" name="campus" required>
                            <option value="Norte">Norte</option>
                            <option value="Sur">Sur</option>
                            <option value="Centro">Centro</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label">Información de salas</label>
                            <a href="#" onclick="verSalas()">Ver salas <i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">N° de salas requeridas para la actividad</label>
                        <select class="form-select" id="nSalas" name="nSalas" required>
							<?php for($i = 1; $i <= 15; $i++): ?>
								<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
							<?php endfor; ?>
						</select>
                        <small class="text-muted">Importante: Si requiere más salas que las definidas en el listado, póngase en contacto con dpi.med@uchile.cl</small>
                    </div>

                    <div class="mb-3">
                         <label class="form-label">N° de alumnos totales del curso</label>
							<input type="number" class="form-control" id="alumnosTotales" name="alumnosTotales" readonly>
							<small class="text-muted">Este valor viene predefinido del curso</small>
						</div>

                    <div class="mb-3">
							<label class="form-label">N° de alumnos por sala</label>
							<input type="number" class="form-control" id="alumnosPorSala" name="alumnosPorSala" readonly>
							<small class="text-muted">Este valor se calcula automáticamente redondeando hacia arriba</small>
						
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Movilidad reducida</label>
                        <select class="form-select" id="movilidadReducida" name="movilidadReducida" required>
                            <option value="No">No</option>
                            <option value="Si">Si</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                placeholder="Por favor, describa su requerimiento con el mayor nivel de detalle posible. Incluya información específica y relevante para asegurar que podamos entender y satisfacer completamente sus necesidades." required></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// async function solicitarSala(idPlanClase) {
//     document.getElementById('salaForm').reset();
//     document.getElementById('idplanclases').value = idPlanClase;
//     document.getElementById('action').value = 'solicitar';
//     document.getElementById('salaModalTitle').textContent = 'Solicitar Sala';
//     
// 	 // Obtener el número de alumnos del elemento de la tabla
//     const tr = document.querySelector(`tr[data-id="${idPlanClase}"]`);
//     if (tr) {
//         const alumnosTotales = tr.dataset.alumnos;
//         document.getElementById('alumnosTotales').value = alumnosTotales;
//         document.getElementById('alumnosTotales').readOnly = true; // Hacerlo de solo lectura
//         calcularAlumnosPorSala(); // Calcular automáticamente alumnos por sala
//     }
// 	
//     const modal = new bootstrap.Modal(document.getElementById('salaModal'));
//     modal.show();
// }
// 
// function calcularAlumnosPorSala() {
//     const totalAlumnos = parseInt(document.getElementById('alumnosTotales').value) || 0;
//     const nSalas = parseInt(document.getElementById('nSalas').value) || 1;
//     // Usar Math.ceil para redondear hacia arriba
//     const alumnosPorSala = Math.ceil(totalAlumnos / nSalas);
//     document.getElementById('alumnosPorSala').value = alumnosPorSala;
// }
// 
// async function modificarSala(idPlanClase) {
//     document.getElementById('idplanclases').value = idPlanClase;
//     document.getElementById('action').value = 'modificar';
//     document.getElementById('salaModalTitle').textContent = 'Modificar Solicitud';
//     
//     try {
//         const response = await fetch(`salas2.php?idPlanClase=${idPlanClase}`);
//         const datos = await response.json();
//         
//         if (datos) {
//             document.getElementById('campus').value = datos.pcl_campus;
//             document.getElementById('nSalas').value = datos.pcl_nSalas;
//         }
//     } catch (error) {
//         console.error('Error:', error);
//     }
//     
//     const modal = new bootstrap.Modal(document.getElementById('salaModal'));
//     modal.show();
// }
// 
// async function mostrarModalLiberarSalas(idPlanClase) {
//     try {
//         // Obtener las salas asignadas
//         const response = await fetch(`salas2.php`, {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify({
//                 action: 'obtener_salas_asignadas',
//                 idPlanClase: idPlanClase
//             })
//         });
//         
//         const datos = await response.json();
//         
//         if (datos.salas && datos.salas.length > 0) {
//             // Llenar la tabla con las salas
//             const tbody = document.getElementById('listaSalasAsignadas');
//             tbody.innerHTML = '';
//             
//             datos.salas.forEach(sala => {
//                 const tr = document.createElement('tr');
//                 tr.innerHTML = `
//                     <td>${sala.idSala}</td>
//                     <td>
//                         <button class="btn btn-danger btn-sm" 
//                                 onclick="liberarSala(${sala.id})">
//                             <i class="bi bi-x-circle"></i> Liberar
//                         </button>
//                     </td>
//                 `;
//                 tbody.appendChild(tr);
//             });
//             
//             // Mostrar el modal
//             const modal = new bootstrap.Modal(document.getElementById('liberarSalaModal'));
//             modal.show();
//         } else {
//             alert('No hay salas asignadas para liberar');
//         }
//     } catch (error) {
//         console.error('Error:', error);
//         alert('Error al cargar las salas asignadas');
//     }
// }
// 
// async function liberarSala(idAsignacion) {
//     if (!confirm('¿Está seguro que desea liberar esta sala?')) {
//         return;
//     }
//     
//     try {
//         const response = await fetch('salas2.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify({
//                 action: 'liberar',
//                 idAsignacion: idAsignacion
//             })
//         });
//         
//         if (response.ok) {
//             location.reload();
//         } else {
//             alert('Error al liberar la sala');
//         }
//     } catch (error) {
//         console.error('Error:', error);
//         alert('Error al procesar la solicitud');
//     }
// }
// 
// async function guardarSala() {
//     const form = document.getElementById('salaForm');
//     if (!form.checkValidity()) {
//         form.reportValidity();
//         return;
//     }
//     
//     const formData = new FormData(form);
//     const datos = Object.fromEntries(formData.entries());
//     
//     try {
//         const response = await fetch('salas2.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify(datos)
//         });
//         
//         if (response.ok) {
//             const modal = bootstrap.Modal.getInstance(document.getElementById('salaModal'));
//             modal.hide();
//             location.reload();
//         } else {
//             alert('Error al guardar los cambios');
//         }
//     } catch (error) {
//         console.error('Error:', error);
//         alert('Error al procesar la solicitud');
//     }
// }
// 
// // Añadir justo después de las funciones
// document.addEventListener('DOMContentLoaded', function() {
//     // Agregar event listener para el cambio de número de salas
//     const nSalasSelect = document.getElementById('nSalas');
//     if (nSalasSelect) {
//         nSalasSelect.addEventListener('change', calcularAlumnosPorSala);
//     }
// });
</script>

</body>
</html>