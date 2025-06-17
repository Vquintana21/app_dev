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
// CASO MODIFICADO: 'solicitar' - ahora maneja juntar secciones
case 'solicitar':
    try {
        $conn->begin_transaction();
        
        // Verificar campos requeridos
        if (!isset($data['nSalas']) || !isset($data['campus']) || !isset($data['idplanclases'])) {
            throw new Exception('Faltan campos requeridos para la solicitud');
        }
        
        $idplanclases = (int)$data['idplanclases'];
        $nSalas = (int)$data['nSalas'];
        $campus = $data['campus'];
        
        // ===== PASO 1: OBTENER DATOS DE PLANCLASES_TEST =====
        $queryPlanclases = "SELECT * FROM planclases_test WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $idplanclases);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$dataPlanclases) {
            throw new Exception('No se encontró la actividad');
        }
        
        // ===== PASO 2: OBTENER DATOS DEL CURSO =====
        $idCurso = $dataPlanclases['cursos_idcursos'];
        $queryCurso = "SELECT CodigoCurso, Seccion, idperiodo FROM spre_cursos WHERE idCurso = ?";
        $stmtCurso = $conexion3->prepare($queryCurso);
        $stmtCurso->bind_param("i", $idCurso);
        $stmtCurso->execute();
        $resultCurso = $stmtCurso->get_result();
        $dataCurso = $resultCurso->fetch_assoc();
        
        if (!$dataCurso) {
            throw new Exception('No se encontró información del curso');
        }
        
        // Obtener nombre del curso
        $queryNombre = "SELECT NombreCurso FROM spre_ramos WHERE CodigoCurso = ?";
        $stmtNombre = $conexion3->prepare($queryNombre);
        $stmtNombre->bind_param("s", $dataCurso['CodigoCurso']);
        $stmtNombre->execute();
        $resultNombre = $stmtNombre->get_result();
        $dataNombre = $resultNombre->fetch_assoc();
        $nombreCurso = $dataNombre ? $dataNombre['NombreCurso'] : 'Curso sin nombre';
        
        // ===== PASO 3: PROCESAR JUNTAR SECCIONES =====
        $numAlumnos = $dataPlanclases['pcl_alumnos']; // Por defecto
        $comentarioExtra = '';
        $seccionFinal = $dataCurso['Seccion'];
        
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $numAlumnos = (int)$data['alumnosTotales'];
            $comentarioExtra = " - SECCIONES JUNTAS ({$data['totalSecciones']} secciones, {$data['cupoTotal']} alumnos)";
            $seccionFinal = "1-JUNTAS"; // Marcar en la sección que son juntas
        }
        
        // ===== PASO 4: CALCULAR CAMPOS ADICIONALES =====
        $pcl_movilidadReducida = isset($data['movilidadReducida']) && $data['movilidadReducida'] == 'Si' ? 'S' : 'N';
        $pcl_Cercania = ($pcl_movilidadReducida == 'S') ? 1 : 0;
        $observaciones = isset($data['observaciones']) ? $data['observaciones'] : '';
        
        // Preparar observaciones con timestamp para planclases_test
        $observacionesPlanclases = $observaciones;
        if (!empty($observaciones)) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - SOLICITUD CLÍNICA" . $comentarioExtra . ": " . $observaciones;
        }
        
        // ===== PASO 5: ACTUALIZAR PLANCLASES_TEST (COMO REGULARES) =====
        $stmt = $conn->prepare("UPDATE planclases_test 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_alumnos = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("issssi", 
            $nSalas, 
            $campus, 
            $numAlumnos,
            $observacionesPlanclases,
            $observacionesPlanclases,
            $idplanclases
        );
        $stmt->execute();
        
        // ===== PASO 6: INSERTAR EN ASIGNACION_PILOTO (COMO REGULARES) =====
        $queryInsert = "INSERT INTO asignacion_piloto (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'S', 0, ?, NOW())";
        
        $stmtInsert = $conn->prepare($queryInsert);
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        
        // Preparar comentario para asignacion_piloto
        $comentarioAsignacion = date('Y-m-d H:i:s') . " - SOLICITUD CLÍNICA" . $comentarioExtra . ": " . $observaciones;
        
        // ===== PASO 7: RECURSIVIDAD - INSERTAR N REGISTROS =====
        for ($i = 0; $i < $nSalas; $i++) {
            $stmtInsert->bind_param(
                "iisssssissssis",
                $idplanclases,              // idplanclases
                $numAlumnos,               // nAlumnos
                $dataPlanclases['pcl_TipoSesion'], // tipoSesion
                $campus,                   // campus
                $dataPlanclases['pcl_Fecha'],      // fecha
                $dataPlanclases['pcl_Inicio'],     // hora_inicio
                $dataPlanclases['pcl_Termino'],    // hora_termino
                $idCurso,                  // idCurso
                $dataCurso['CodigoCurso'], // CodigoCurso
                $seccionFinal,             // Seccion (normal o "1-JUNTAS")
                $nombreCurso,              // NombreCurso
                $comentarioAsignacion,     // Comentario
                $pcl_Cercania,             // cercania (0 o 1)
                $usuario                   // Usuario
            );
            
            if (!$stmtInsert->execute()) {
                throw new Exception('Error insertando registro ' . ($i+1) . ' en asignacion_piloto: ' . $stmtInsert->error);
            }
        }
        
        $conn->commit();
        
        $mensaje = "Solicitud realizada exitosamente - {$nSalas} sala(s) solicitada(s)";
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $mensaje .= " (Juntando {$data['totalSecciones']} secciones - {$data['cupoTotal']} alumnos)";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $mensaje,
            'debug' => [
                'registros_insertados' => $nSalas,
                'alumnos_por_registro' => $numAlumnos,
                'seccion_final' => $seccionFinal
            ]
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;

// CASO MODIFICAR - VERSIÓN COMPLETA (replicando salas2.php)
case 'modificar':
    try {
        $conn->begin_transaction();
        
        $idplanclases = (int)$data['idplanclases'];
        
        // Obtener datos actuales
        $queryPlanclases = "SELECT * FROM planclases_test WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $idplanclases);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$dataPlanclases) {
            throw new Exception('No se encontró la actividad');
        }
        
        // Si no requiere sala
        if (isset($data['nSalas']) && $data['nSalas'] == 0) {
            $stmt = $conn->prepare("UPDATE planclases_test SET pcl_nSalas = 0 WHERE idplanclases = ?");
            $stmt->bind_param("i", $idplanclases);
            $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE asignacion_piloto SET idEstado = 4 WHERE idplanclases = ? AND idEstado != 4");
            $stmt->bind_param("i", $idplanclases);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Actividad actualizada. No requiere sala.']);
            break;
        }
        
        // Verificar estado actual de asignaciones
        $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(idEstado) as maxEstado 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ?");
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentState = $result->fetch_assoc();
        
        // Solo modificar si están en estado 0 (solicitado)
        if ($currentState['maxEstado'] > 0) {
            throw new Exception('No se puede modificar: la solicitud ya fue procesada');
        }
        
        // Obtener datos del curso
        $idCurso = $dataPlanclases['cursos_idcursos'];
        $queryCurso = "SELECT CodigoCurso, Seccion FROM spre_cursos WHERE idCurso = ?";
        $stmtCurso = $conexion3->prepare($queryCurso);
        $stmtCurso->bind_param("i", $idCurso);
        $stmtCurso->execute();
        $resultCurso = $stmtCurso->get_result();
        $dataCurso = $resultCurso->fetch_assoc();
        
        // Procesar juntar secciones
        $numAlumnos = $dataPlanclases['pcl_alumnos'];
        $comentarioExtra = '';
        $seccionFinal = $dataCurso['Seccion'];
        
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $numAlumnos = (int)$data['alumnosTotales'];
            $comentarioExtra = " - SECCIONES JUNTAS ({$data['totalSecciones']} secciones, {$data['cupoTotal']} alumnos)";
            $seccionFinal = "1-JUNTAS";
        }
        
        // Actualizar planclases_test
        $observaciones = isset($data['observaciones']) ? $data['observaciones'] : '';
        $observacionesPlanclases = date('Y-m-d H:i:s') . " - MODIFICACIÓN CLÍNICA" . $comentarioExtra . ": " . $observaciones;
        
        $nSalas = isset($data['nSalas']) ? (int)$data['nSalas'] : $dataPlanclases['pcl_nSalas'];
        $campus = isset($data['campus']) ? $data['campus'] : $dataPlanclases['pcl_campus'];
        
        $stmt = $conn->prepare("UPDATE planclases_test 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_alumnos = ?,
                                  pcl_observaciones = CONCAT(COALESCE(pcl_observaciones, ''), '\n\n', ?)
                              WHERE idplanclases = ?");
        $stmt->bind_param("isisi", $nSalas, $campus, $numAlumnos, $observacionesPlanclases, $idplanclases);
        $stmt->execute();
        
        // Eliminar asignaciones existentes
        $stmt = $conn->prepare("DELETE FROM asignacion_piloto WHERE idplanclases = ? AND idEstado = 0");
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        
        // Insertar nuevas asignaciones (igual que en solicitar)
        $queryInsert = "INSERT INTO asignacion_piloto (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'S', 0, ?, NOW())";
        
        $stmtInsert = $conn->prepare($queryInsert);
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        
        // Obtener nombre del curso
        $queryNombre = "SELECT NombreCurso FROM spre_ramos WHERE CodigoCurso = ?";
        $stmtNombre = $conexion3->prepare($queryNombre);
        $stmtNombre->bind_param("s", $dataCurso['CodigoCurso']);
        $stmtNombre->execute();
        $resultNombre = $stmtNombre->get_result();
        $dataNombre = $resultNombre->fetch_assoc();
        $nombreCurso = $dataNombre ? $dataNombre['NombreCurso'] : 'Curso sin nombre';
        
        $comentarioAsignacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN CLÍNICA" . $comentarioExtra . ": " . $observaciones;
        
        // Insertar N registros
        for ($i = 0; $i < $nSalas; $i++) {
            $stmtInsert->bind_param(
                "iisssssissssis",
                $idplanclases,
                $numAlumnos,
                $dataPlanclases['pcl_TipoSesion'],
                $campus,
                $dataPlanclases['pcl_Fecha'],
                $dataPlanclases['pcl_Inicio'],
                $dataPlanclases['pcl_Termino'],
                $idCurso,
                $dataCurso['CodigoCurso'],
                $seccionFinal,
                $nombreCurso,
                $comentarioAsignacion,
                0, // cercania siempre 0 para clínicos
                $usuario
            );
            
            if (!$stmtInsert->execute()) {
                throw new Exception('Error insertando registro en modificación: ' . $stmtInsert->error);
            }
        }
        
        $conn->commit();
        
        $mensaje = "Solicitud modificada exitosamente - {$nSalas} sala(s)";
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $mensaje .= " (Juntando {$data['totalSecciones']} secciones)";
        }
        
        echo json_encode(['success' => true, 'message' => $mensaje]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
                
            // CASO ARREGLADO: obtener_datos_solicitud para clínicos
case 'obtener_datos_solicitud':
    try {
        // ARREGLADO: Usar campos correctos de planclases_test y asignacion_piloto
        $stmt = $conn->prepare("SELECT p.pcl_campus, p.pcl_nSalas, p.pcl_alumnos,
                               (SELECT COUNT(*) FROM asignacion_piloto 
                                WHERE idplanclases = p.idplanclases 
                                AND idEstado = 3) as salas_asignadas,
                               (SELECT Comentario FROM asignacion_piloto
                                WHERE idplanclases = p.idplanclases
                                ORDER BY timestamp DESC LIMIT 1) as comentarios
                               FROM planclases_test p 
                               WHERE p.idplanclases = ?");
        
        if (!$stmt) {
            throw new Exception('Error preparando consulta: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $data['idPlanClase']);
        if (!$stmt->execute()) {
            throw new Exception('Error ejecutando consulta: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $datos = $result->fetch_assoc();
        
        if ($datos) {
            echo json_encode([
                'success' => true,
                'pcl_campus' => $datos['pcl_campus'] ?: '',
                'pcl_nSalas' => $datos['pcl_nSalas'] ?: 1,
                'pcl_alumnos' => $datos['pcl_alumnos'] ?: 0,
                'observaciones' => $datos['comentarios'] ?: '',
                'estado' => $datos['salas_asignadas'] > 0 ? 3 : 0
            ]);
        } else {
            throw new Exception('No se encontraron datos para esta actividad');
        }
        
    } catch (Exception $e) {
        error_log("Error en obtener_datos_solicitud: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
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
				
				// Solo agregar este caso en el switch de salas_clinico.php, después de los casos existentes:

case 'verificar_secciones':
    try {
        // Obtener curso de la actividad
        $stmt = $conn->prepare("SELECT cursos_idcursos FROM planclases_test WHERE idplanclases = ?");
        $stmt->bind_param("i", $data['idPlanClase']);
        $stmt->execute();
        $result = $stmt->get_result();
        $planData = $result->fetch_assoc();
        
        if (!$planData) {
            throw new Exception('No se encontró la actividad');
        }
        
        // Obtener info del curso
        $stmtCurso = $conexion3->prepare("SELECT CodigoCurso, Seccion, idperiodo FROM spre_cursos WHERE idCurso = ?");
        $stmtCurso->bind_param("i", $planData['cursos_idcursos']);
        $stmtCurso->execute();
        $resultCurso = $stmtCurso->get_result();
        $cursoData = $resultCurso->fetch_assoc();
        
        if ($cursoData) {
            // Query exacta para contar secciones (la que ya tenían comentada)
            $stmtCount = $conexion3->prepare("SELECT COUNT(*) as total, SUM(Cupo) as cupo_total 
                                             FROM spre_cursos 
                                             WHERE CodigoCurso = ? AND idperiodo = ?");
            $stmtCount->bind_param("ss", $cursoData['CodigoCurso'], $cursoData['idperiodo']);
            $stmtCount->execute();
            $resultCount = $stmtCount->get_result();
            $countData = $resultCount->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'mostrarOpcion' => ($countData['total'] > 1),
                'totalSecciones' => $countData['total'],
                'cupoTotal' => $countData['cupo_total'],
                'seccionActual' => $cursoData['Seccion']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró información del curso']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
    p.pcl_DeseaSala,
    p.pcl_observaciones,
    p.pcl_condicion,
    p.dia,
    COALESCE(t.pedir_sala, 0) as pedir_sala,
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
LEFT JOIN pcl_TipoSesion t ON p.pcl_TipoSesion = t.tipo_sesion 
    AND p.pcl_SubTipoSesion = t.Sub_tipo_sesion
WHERE p.cursos_idcursos = ? 
AND p.pcl_tituloActividad != ''
AND t.tipo_activo = 1
AND t.pedir_sala = 1
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

  <div class="container py-4"> 
 <div class="card mb-4">
            <div class="card-body text-center">
               <h4> <i class="bi bi-person-raised-hand"></i> Instrucciones</h4>
                
            </div>
        </div>

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
                    
					  <!-- NUEVO: Opción para juntar secciones -->
                    <div id="opcionJuntarSecciones" class="mb-3" style="display: none;">
                        <div class="alert alert-warning">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="juntarSecciones" name="juntarSecciones" value="1" onchange="recalcularAlumnos()">
                                <label class="form-check-label" for="juntarSecciones">
                                    <strong><i class="bi bi-people-fill"></i> Juntar todas las secciones</strong>
                                </label>
                            </div>
                            <small id="infoSecciones" class="text-muted">
                                <!-- Se llenará dinámicamente -->
                            </small>
                        </div>
                    </div>
					
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
							<option value="No" selected>No</option>
							<option value="Si">Si</option>
						</select>
					</div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones y requerimientos especiales</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                placeholder="Detalles adicionales como: equipamiento especial requerido, disposición de la sala, etc." required></textarea>
                    </div>
					
					<div class="mb-3">
					  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#observacionesHistoricas" aria-expanded="false" aria-controls="observacionesHistoricas">
						Ver observaciones históricas
					  </button>
					  <div class="collapse mt-2" id="observacionesHistoricas">
						<div class="border rounded p-2 bg-light text-muted" style="max-height: 200px; overflow-y: auto;">
						  <pre id="textoObservacionesHistoricas" class="mb-0" style="white-space: pre-wrap;"></pre>
						</div>
					  </div>
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