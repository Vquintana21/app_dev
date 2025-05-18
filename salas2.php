<?php
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'solicitar':
    try {
        $conn->begin_transaction();
        
		 // Verificar si requiere sala
        $requiereSala = isset($data['requiereSala']) ? (int)$data['requiereSala'] : 1;
        
        // Actualizar planclases primero con pcl_DeseaSala
        $stmt = $conn->prepare("UPDATE planclases SET pcl_nSalas = ?, pcl_campus = ?, pcl_DeseaSala = ? WHERE idplanclases = ?");
        $stmt->bind_param("isii", $data['nSalas'], $data['campus'], $requiereSala, $data['idplanclases']);
        $stmt->execute();
        
        if ($requiereSala == 0) {
            // Si NO requiere sala, liberar todas las asignaciones existentes
            $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                   SET idEstado = 4, 
                                       Comentario = CONCAT(IFNULL(Comentario, ''), '\n\n', ?, ' - NO REQUIERE SALA') 
                                   WHERE idplanclases = ? AND idEstado != 4");
            $stmt->bind_param("si", date('Y-m-d H:i:s'), $data['idplanclases']);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Actividad actualizada. No requiere sala.']);
            break;
        }
		
        // Actualizar planclases primero
        $stmt = $conn->prepare("UPDATE planclases SET pcl_nSalas = ?, pcl_campus = ? WHERE idplanclases = ?");
        $stmt->bind_param("isi", $data['nSalas'], $data['campus'], $data['idplanclases']);
        $stmt->execute();
        
        // Obtener datos necesarios de planclases
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        // Preparar observaciones con timestamp
        $observaciones = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observaciones = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
        }
        
        // Insertar en asignacion_piloto con todos los datos necesarios
        $queryInsert = "INSERT INTO asignacion_piloto (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'M', 0, ?, NOW())";
        
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        
        $stmtInsert = $conn->prepare($queryInsert);
        
        // Crear múltiples registros según el número de salas
        for ($i = 0; $i < $data['nSalas']; $i++) {
            $stmtInsert->bind_param(
                "iisssssisssss",
                $data['idplanclases'],
                $dataPlanclases['pcl_alumnos'],
                $dataPlanclases['pcl_TipoSesion'],
                $data['campus'],
                $dataPlanclases['pcl_Fecha'],
                $dataPlanclases['pcl_Inicio'],
                $dataPlanclases['pcl_Termino'],
                $dataPlanclases['cursos_idcursos'],
                $dataPlanclases['pcl_AsiCodigo'],
                $dataPlanclases['pcl_Seccion'],
                $dataPlanclases['pcl_AsiNombre'],
                $observaciones,
                $usuario
            );
            $stmtInsert->execute();
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
		
		 // Verificar si requiere sala
        $requiereSala = isset($data['requiereSala']) ? (int)$data['requiereSala'] : 1;
        
        // Actualizar planclases con pcl_DeseaSala
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ?, pcl_campus = ?, pcl_DeseaSala = ? 
                              WHERE idplanclases = ?");
        $stmt->bind_param("isii", $data['nSalas'], $data['campus'], $requiereSala, $data['idplanclases']);
        $stmt->execute();
        
        if ($requiereSala == 0) {
            // Si NO requiere sala, liberar todas las asignaciones
            $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                   SET idEstado = 4, 
                                       Comentario = CONCAT(IFNULL(Comentario, ''), '\n\n', ?, ' - NO REQUIERE SALA') 
                                   WHERE idplanclases = ? AND idEstado != 4");
            $stmt->bind_param("si", date('Y-m-d H:i:s'), $data['idplanclases']);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Actividad actualizada. No requiere sala.']);
            break;
        }
        
        // Verificar estado actual de las asignaciones
        $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(idEstado) as maxEstado 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ?");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentState = $result->fetch_assoc();
        
        // Solo modificar si están en estado 0 (solicitado)
        if ($currentState['maxEstado'] == 0) {
            // Obtener datos de planclases
            $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
            $stmtPlanclases = $conn->prepare($queryPlanclases);
            $stmtPlanclases->bind_param("i", $data['idplanclases']);
            $stmtPlanclases->execute();
            $resultPlanclases = $stmtPlanclases->get_result();
            $dataPlanclases = $resultPlanclases->fetch_assoc();
            
            // Obtener observaciones existentes
            $queryObs = "SELECT Comentario FROM asignacion_piloto 
                         WHERE idplanclases = ? LIMIT 1";
            $stmtObs = $conn->prepare($queryObs);
            $stmtObs->bind_param("i", $data['idplanclases']);
            $stmtObs->execute();
            $resultObs = $stmtObs->get_result();
            $obsAnterior = "";
            
            if ($resultObs->num_rows > 0) {
                $obsAnterior = $resultObs->fetch_assoc()['Comentario'];
            }
            
            // Concatenar nueva observación
            $nuevaObservacion = $obsAnterior;
            if (isset($data['observaciones']) && !empty($data['observaciones'])) {
                if (!empty($obsAnterior)) {
                    $nuevaObservacion .= "\n\n" . date('Y-m-d H:i:s') . " - " . $data['observaciones'];
                } else {
                    $nuevaObservacion = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
                }
            }
            
            // Actualizar planclases
            $stmt = $conn->prepare("UPDATE planclases 
                                  SET pcl_nSalas = ?, pcl_campus = ? 
                                  WHERE idplanclases = ?");
            $stmt->bind_param("isi", $data['nSalas'], $data['campus'], $data['idplanclases']);
            $stmt->execute();
            
            // Actualizar asignacion_piloto (solo estado 0)
            $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                  SET Comentario = ?,
                                      nAlumnos = ?,
                                      campus = ?
                                  WHERE idplanclases = ? AND idEstado = 0");
            $stmt->bind_param("sisi", 
                $nuevaObservacion, 
                $dataPlanclases['pcl_alumnos'],
                $data['campus'],
                $data['idplanclases']
            );
            $stmt->execute();
            
            // Ajustar número de registros si cambió
            $diff = $data['nSalas'] - $currentState['count'];
            
            if ($diff > 0) {
                // Agregar nuevas asignaciones con todos los campos
                $queryInsert = "INSERT INTO asignacion_piloto (
                    idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                    fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                    NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
                ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'M', 0, ?, NOW())";
                
                $stmtInsert = $conn->prepare($queryInsert);
                $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
                
                for ($i = 0; $i < $diff; $i++) {
                    $stmtInsert->bind_param(
                        "iisssssisssss",
                        $data['idplanclases'],
                        $dataPlanclases['pcl_alumnos'],
                        $dataPlanclases['pcl_TipoSesion'],
                        $data['campus'],
                        $dataPlanclases['pcl_Fecha'],
                        $dataPlanclases['pcl_Inicio'],
                        $dataPlanclases['pcl_Termino'],
                        $dataPlanclases['cursos_idcursos'],
                        $dataPlanclases['pcl_AsiCodigo'],
                        $dataPlanclases['pcl_Seccion'],
                        $dataPlanclases['pcl_AsiNombre'],
                        $nuevaObservacion,
                        $usuario
                    );
                    $stmtInsert->execute();
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
        
        // Obtener datos de planclases primero
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        // 1. Contar cuántas salas están actualmente asignadas (estado 3)
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentAssigned = $result->fetch_assoc()['count'];
        
        // 2. Actualizar planclases con el nuevo número de salas
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ?, pcl_campus = ? 
                              WHERE idplanclases = ?");
        $stmt->bind_param("isi", $data['nSalas'], $data['campus'], $data['idplanclases']);
        $stmt->execute();
        
        // 3. Preparar observaciones
        $observacionModificacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionModificacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
        }
        
        // 4. Cambiar TODAS las asignaciones de estado 3 a estado 1
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                              SET idEstado = 1,
                                  Comentario = CASE 
                                      WHEN COALESCE(Comentario, '') = '' THEN ?
                                      ELSE CONCAT(Comentario, '\n\n', ?)
                                  END,
                                  nAlumnos = ?,
                                  campus = ?
                              WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("ssisi", 
            $observacionModificacion,
            $observacionModificacion,
            $dataPlanclases['pcl_alumnos'],
            $data['campus'],
            $data['idplanclases']
        );
        $stmt->execute();
        
        // 5. Calcular la diferencia
        $diff = intval($data['nSalas']) - $currentAssigned;
        
        if ($diff > 0) {
            // Necesitamos MÁS salas: agregar nuevas asignaciones en estado 1
            $queryInsert = "INSERT INTO asignacion_piloto (
                idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
            ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'M', 1, ?, NOW())";
            
            $stmtInsert = $conn->prepare($queryInsert);
            $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
            $comentarioNuevo = date('Y-m-d H:i:s') . " - NUEVA SALA AGREGADA EN MODIFICACIÓN";
            
            for ($i = 0; $i < $diff; $i++) {
                $stmtInsert->bind_param(
                    "iisssssisssss",
                    $data['idplanclases'],
                    $dataPlanclases['pcl_alumnos'],
                    $dataPlanclases['pcl_TipoSesion'],
                    $data['campus'],
                    $dataPlanclases['pcl_Fecha'],
                    $dataPlanclases['pcl_Inicio'],
                    $dataPlanclases['pcl_Termino'],
                    $dataPlanclases['cursos_idcursos'],
                    $dataPlanclases['pcl_AsiCodigo'],
                    $dataPlanclases['pcl_Seccion'],
                    $dataPlanclases['pcl_AsiNombre'],
                    $comentarioNuevo,
                    $usuario
                );
                $stmtInsert->execute();
            }
            
        } elseif ($diff < 0) {
            // Necesitamos MENOS salas: eliminar las sobrantes
            $limit = abs($diff);
            $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                  WHERE idplanclases = ? AND idEstado = 1 
                                  ORDER BY idAsignacion DESC LIMIT ?");
            $stmt->bind_param("ii", $data['idplanclases'], $limit);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Mensaje descriptivo para el usuario
        $mensaje = "Solicitud de modificación creada. ";
        if ($diff > 0) {
            $mensaje .= "Se han agregado $diff salas adicionales.";
        } elseif ($diff < 0) {
            $absNum = abs($diff);
            $mensaje .= "Se han reducido $absNum salas.";
        } else {
            $mensaje .= "Se mantiene el mismo número de salas.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'previousCount' => $currentAssigned,
            'newCount' => $data['nSalas'],
            'difference' => $diff
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en modificar_asignada: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;

case 'obtener_datos_solicitud':
    try {
        // Obtener datos básicos y observaciones
        $stmt = $conn->prepare("SELECT p.pcl_campus, p.pcl_nSalas,
                               (SELECT COUNT(*) FROM asignacion_piloto 
                                WHERE idplanclases = p.idplanclases 
                                AND idEstado = 3) as salas_asignadas,
                               (SELECT Comentario FROM asignacion_piloto 
                                WHERE idplanclases = p.idplanclases 
                                ORDER BY idAsignacion DESC LIMIT 1) as observaciones
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
        
        // Actualizar estado a 4 (liberada) en lugar de eliminar
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                               SET idSala = NULL, idEstado = 4 
                               WHERE idAsignacion = ?");
        $stmt->bind_param("i", $data['idAsignacion']);
        $stmt->execute();
        
        // Actualizar contador en planclases
        $stmt = $conn->prepare("UPDATE planclases p
                              SET pcl_nSalas = (
                                  SELECT COUNT(*) 
                                  FROM asignacion_piloto 
                                  WHERE idplanclases = p.idplanclases 
                                  AND idEstado IN (0,1,3)
                              )
                              WHERE p.idplanclases = (
                                  SELECT idplanclases 
                                  FROM asignacion_piloto 
                                  WHERE idAsignacion = ?
                              )");
        $stmt->bind_param("i", $data['idAsignacion']);
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
	p.pcl_DeseaSala,
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
                        <td>
                            <?php 
                            $tituloCompleto = $row['pcl_tituloActividad'];
                            $tituloCorto = strlen($tituloCompleto) > 25 ? 
                                          substr($tituloCompleto, 0, 25) . '...' : 
                                          $tituloCompleto;
                            $needsTooltip = strlen($tituloCompleto) > 25;
                            ?>
                            <?php if($needsTooltip): ?>
                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($tituloCompleto); ?>">
                                    <?php echo htmlspecialchars($tituloCorto); ?>
                                </span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($tituloCorto); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['pcl_TipoSesion']; ?>
                            <?php if($row['pcl_SubTipoSesion']): ?>
                                <br><small class="text-muted"><?php echo $row['pcl_SubTipoSesion']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['pcl_campus']; ?></td>
						<!-- mostrar cero salas si es activi distinta a clase en la pimera insercion de una actividad, tomar el # de asignacion-->
                        <td><?php echo $row['pcl_nSalas']; ?></td>
                       <?php 
// Primero, obtener todos los estados de las asignaciones para este idplanclases
$queryEstados = "SELECT idEstado, COUNT(*) as cantidad, GROUP_CONCAT(idSala) as salas 
                 FROM asignacion_piloto 
                 WHERE idplanclases = ? 
                 GROUP BY idEstado";
$stmtEstados = $conn->prepare($queryEstados);
$stmtEstados->bind_param("i", $row['idplanclases']);
$stmtEstados->execute();
$resultEstados = $stmtEstados->get_result();

$estados = [];
$salasAsignadas = [];
while($est = $resultEstados->fetch_assoc()) {
    $estados[$est['idEstado']] = $est['cantidad'];
    if($est['idEstado'] == 3 && $est['salas']) {
        $salasTemp = explode(',', $est['salas']);
        $salasAsignadas = array_merge($salasAsignadas, array_filter($salasTemp));
    }
}

// Determinar cantidades por estado
$estado0 = isset($estados[0]) ? $estados[0] : 0;
$estado1 = isset($estados[1]) ? $estados[1] : 0;
$estado3 = isset($estados[3]) ? $estados[3] : 0;
$estado4 = isset($estados[4]) ? $estados[4] : 0;

// Total de asignaciones (todos los estados)
$totalAsignaciones = array_sum($estados);

// Total de asignaciones ACTIVAS (excluyendo las liberadas)
$totalActivas = $totalAsignaciones - $estado4;

// Lógica de estados
$todasLiberadas = ($totalAsignaciones > 0 && $estado4 == $totalAsignaciones);
$todasAsignadas = ($totalActivas > 0 && $estado3 == $totalActivas);
$parcialmenteAsignadas = ($estado3 > 0 && ($estado0 > 0 || $estado1 > 0));
$enModificacion = ($estado1 > 0 && $estado3 == 0);
$solicitadas = ($estado0 == $totalActivas && $totalActivas > 0);
$pendiente = ($totalActivas == 0);

?>

<!-- Columna Sala -->
<td>
    <?php if(!empty($salasAsignadas)): ?>
        <ul class="list-unstyled m-0">
            <?php foreach($salasAsignadas as $sala): ?>
                <li><span class="badge bg-success"><?php echo $sala; ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <span class="badge bg-secondary">Sin sala</span>
    <?php endif; ?>
</td>

<!-- Columna Estado -->
<td>
    <?php if($todasLiberadas): ?>
        <span class="badge bg-dark">Liberada</span>
    <?php elseif($todasAsignadas): ?>
        <span class="badge bg-success">Asignada</span>
    <?php elseif($parcialmenteAsignadas): ?>
        <span class="badge bg-warning">Parcialmente asignada</span>
    <?php elseif($enModificacion): ?>
        <span class="badge bg-primary">En modificación</span>
    <?php elseif($solicitadas): ?>
        <span class="badge bg-info">Solicitada</span>
    <?php else: ?>
        <span class="badge bg-secondary">Pendiente</span>
    <?php endif; ?>
</td>

<!-- Columna Acciones -->
<td>
    <div class="btn-group">
        <?php if($todasLiberadas || $pendiente): ?>
            <!-- Si está liberada o pendiente, permitir nueva solicitud -->
            <button type="button" class="btn btn-sm btn-primary" 
                    onclick="solicitarSala(<?php echo $row['idplanclases']; ?>)">
                <i class="bi bi-plus-circle"></i> Solicitar
            </button>
        <?php else: ?>
            <!-- Si tiene cualquier otra asignación, permitir modificar -->
            <button type="button" class="btn btn-sm btn-warning" 
                    onclick="modificarSala(<?php echo $row['idplanclases']; ?>)">
                <i class="bi bi-pencil"></i> Modificar
            </button>
            
            <?php if($estado3 > 0): ?>
                <!-- Solo permitir liberar si hay salas asignadas (estado 3) -->
                <button type="button" class="btn btn-sm btn-danger" 
                        onclick="mostrarModalLiberarSalas(<?php echo $row['idplanclases']; ?>)">
                    <i class="bi bi-x-circle"></i> Liberar
                </button>
            <?php endif; ?>
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
                            <option value="1">Si</option>
                            <option value="0">No</option>
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
<hr>
                 <div class="mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <label class="form-label fw-bold text-primary mb-0">
            <i class="bi bi-building me-1"></i>
            Información de salas
        </label>
        <a href="https://dpi.med.uchile.cl/CALENDARIO/salas.php" target="_blank" 
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i>
            Ver salas
        </a>
    </div>
</div>


<hr>
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

</div>
