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

// ===== FUNCIONES AUXILIARES =====

function verificarReservaCompleta($conn, $conexion3, $idplanclases) {
    try {
        // Obtener datos de planclases_test para construir código-sección
        $queryPlanclases = "SELECT cursos_idcursos, pcl_Fecha, pcl_Inicio, pcl_Termino FROM planclases_test WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $idplanclases);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        $stmtPlanclases->close();
        
        if (!$dataPlanclases) {
            return ['encontrado' => false, 'metodo' => 'error', 'detalle' => 'No se encontraron datos de planclases'];
        }
        
        // Obtener datos del curso
        $queryCurso = "SELECT CodigoCurso, Seccion FROM spre_cursos WHERE idCurso = ?";
        $stmtCurso = $conexion3->prepare($queryCurso);
        $stmtCurso->bind_param("i", $dataPlanclases['cursos_idcursos']);
        $stmtCurso->execute();
        $resultCurso = $stmtCurso->get_result();
        $dataCurso = $resultCurso->fetch_assoc();
        $stmtCurso->close();
        
        if (!$dataCurso) {
            return ['encontrado' => false, 'metodo' => 'error', 'detalle' => 'No se encontraron datos del curso'];
        }
        
        $codigo_completo = $dataCurso['CodigoCurso'] . "-" . $dataCurso['Seccion'];
        
        // PASO 1: Buscar por re_idRepeticion (más directo)
        $queryPaso1 = "SELECT COUNT(*) as existe FROM reserva_2 WHERE re_idRepeticion = ?";
        $stmtPaso1 = $conn->prepare($queryPaso1);
        $stmtPaso1->bind_param("i", $idplanclases);
        $stmtPaso1->execute();
        $resultPaso1 = $stmtPaso1->get_result();
        $rowPaso1 = $resultPaso1->fetch_assoc();
        $stmtPaso1->close();
        
        if ($rowPaso1['existe'] > 0) {
            return ['encontrado' => true, 'metodo' => 'paso1', 'detalle' => 'Encontrado por ID repetición'];
        }
        
        // PASO 2: Buscar por código-sección, fecha y horarios
        $queryPaso2 = "SELECT COUNT(*) as existe FROM reserva_2 
                       WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                       AND re_FechaReserva = ? 
                       AND re_HoraReserva = ? 
                       AND re_HoraTermino = ?";
        
        $stmtPaso2 = $conn->prepare($queryPaso2);
        $codigoBusqueda = "%{$codigo_completo}%";
        $stmtPaso2->bind_param("sssss", 
            $codigoBusqueda, $codigoBusqueda, 
            $dataPlanclases['pcl_Fecha'], 
            $dataPlanclases['pcl_Inicio'], 
            $dataPlanclases['pcl_Termino']
        );
        $stmtPaso2->execute();
        $resultPaso2 = $stmtPaso2->get_result();
        $rowPaso2 = $resultPaso2->fetch_assoc();
        $stmtPaso2->close();
        
        if ($rowPaso2['existe'] > 0) {
            return ['encontrado' => true, 'metodo' => 'paso2', 'detalle' => 'Encontrado por código-sección y horario'];
        }
        
        // PASO 3: No se encontró - Inconsistencia
        return ['encontrado' => false, 'metodo' => 'ninguno', 'detalle' => 'No se encontró reserva por ningún método'];
        
    } catch (Exception $e) {
        return ['encontrado' => false, 'metodo' => 'error', 'detalle' => 'Error durante verificación: ' . $e->getMessage()];
    }
}

function liberarReservaCompleta($conn, $idplanclases, $idSala = null) {
    try {
        // PASO 1: Eliminar por re_idRepeticion y idSala específica
        if ($idSala) {
            $queryEliminar1 = "DELETE FROM reserva_2 WHERE re_idRepeticion = ? AND re_idSala = ?";
            $stmtEliminar1 = $conn->prepare($queryEliminar1);
            $stmtEliminar1->bind_param("is", $idplanclases, $idSala);
            $stmtEliminar1->execute();
            $eliminadas1 = $stmtEliminar1->affected_rows;
            $stmtEliminar1->close();
            
            if ($eliminadas1 > 0) {
                return ['success' => true, 'metodo' => 'paso1', 'eliminadas' => $eliminadas1];
            }
        } else {
            // Si no se especifica sala, eliminar todas las reservas del idRepeticion
            $queryEliminar1 = "DELETE FROM reserva_2 WHERE re_idRepeticion = ?";
            $stmtEliminar1 = $conn->prepare($queryEliminar1);
            $stmtEliminar1->bind_param("i", $idplanclases);
            $stmtEliminar1->execute();
            $eliminadas1 = $stmtEliminar1->affected_rows;
            $stmtEliminar1->close();
            
            if ($eliminadas1 > 0) {
                return ['success' => true, 'metodo' => 'paso1', 'eliminadas' => $eliminadas1];
            }
        }
        
        // PASO 2: Si no se eliminó nada en paso 1, buscar por otros métodos
        // (En este caso, sería más complejo y requeriría más datos de la actividad)
        return ['success' => true, 'metodo' => 'ninguno', 'eliminadas' => 0, 'mensaje' => 'No se encontraron reservas para eliminar'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

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
		
		  $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
        
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
            $seccionFinal = $dataCurso['Seccion']; // Marcar en la sección que son juntas
        }
        
        // ===== PASO 4: CALCULAR CAMPOS ADICIONALES =====
        $pcl_movilidadReducida = isset($data['movilidadReducida']) && $data['movilidadReducida'] == 'Si' ? 'S' : 'N';
        $pcl_Cercania = ($pcl_movilidadReducida == 'S') ? 'S' : 'N';
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
            NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 0, ?, NOW())";
        
        $stmtInsert = $conn->prepare($queryInsert);
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        
        // Preparar comentario para asignacion_piloto
        $comentarioAsignacion = date('Y-m-d H:i:s') . " - SOLICITUD CLÍNICA" . $comentarioExtra . ": " . $observaciones;
        
        // ===== PASO 7: RECURSIVIDAD - INSERTAR N REGISTROS =====
        for ($i = 0; $i < $nSalas; $i++) {
            $stmtInsert->bind_param(
                "iisssssisssssis",
                $idplanclases,              // idplanclases
                $numAlumnos,               // nAlumnos
                $dataPlanclases['pcl_TipoSesion'], // tipoSesion
                $campus,                   // campus
                $dataPlanclases['pcl_Fecha'],      // fecha
                $dataPlanclases['pcl_Inicio'],     // hora_inicio
                $dataPlanclases['pcl_Termino'],    // hora_termino
                $idCurso,                  // idCurso
                $dataCurso['CodigoCurso'], // CodigoCurso
                $seccionFinal,             // Seccion
                $nombreCurso,              // NombreCurso
                $comentarioAsignacion,     // Comentario
                $pcl_Cercania,             // cercania (0 o 1)
				$juntaSeccion,
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
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;


case 'modificar':
    try {
        $conn->begin_transaction();
		
		 $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
        
        // ✅ LÓGICA EXACTA DE salas2.php
        $requiereSala = 1; // Clínicos siempre requieren sala
        
        
        // Procesar movilidad reducida y cercanía (IGUAL QUE REGULARES)
        $movilidadReducida = isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'No';
        if ($movilidadReducida === 'Si') {
            $pcl_movilidadReducida = 'S';
            $pcl_Cercania = 'S';  // Salas deben estar cerca
        } else {
            $pcl_movilidadReducida = 'N';
            $pcl_Cercania = 'N';  // Sin restricción de cercanía
        }
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
        }
        
        // ✅ ACTUALIZAR planclases_test (MISMA ESTRUCTURA QUE a_planclases)
        $stmt = $conn->prepare("UPDATE planclases_test 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isssssi", 
            $data['nSalas'], 
            $data['campus'], 
            $pcl_movilidadReducida,  // 'S' o 'N'
            $pcl_Cercania,           // 'S' o 'N'
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        $stmt->execute();
        
        if ($requiereSala == 0) {
            // Si NO requiere sala, liberar asignaciones (IGUAL QUE REGULARES)
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
        
        // Obtener datos necesarios de planclases
        $queryPlanclases = "SELECT * FROM planclases_test WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$dataPlanclases) {
            throw new Exception("No se encontraron datos de planclases para ID: " . $data['idplanclases']);
        }
        
        // ✅ CALCULAR nAlumnos CORRECTAMENTE
        $nAlumnosReal = $dataPlanclases['pcl_alumnos']; // Por defecto
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $nAlumnosReal = (int)$data['alumnosTotales']; // Si junta secciones
        }
        
        // Obtener estado actual de asignaciones (IGUAL QUE REGULARES)
        $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(idEstado) as maxEstado 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? AND idEstado != 4");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentState = $result->fetch_assoc();
        
        // Preparar observaciones para asignacion_piloto
        $observacionesAsignacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesAsignacion = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
        }
        
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        if ($usuario === null || $usuario === '') {
            $usuario = 'sistema';
        }
        
        // ✅ ACTUALIZAR asignacion_piloto EXISTENTES (IGUAL QUE REGULARES)
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                              SET Comentario = CASE 
                                      WHEN COALESCE(Comentario, '') = '' THEN ?
                                      ELSE CONCAT(Comentario, '\n\n', ?)
                                  END,
                                  nAlumnos = ?,
                                  campus = ?,
                                  cercania = ?,
                                  junta_seccion = ?
                              WHERE idplanclases = ? AND idEstado = 0");
        $stmt->bind_param("ssissii", 
            $observacionesAsignacion, 
            $observacionesAsignacion,
            $nAlumnosReal,  // ✅ nAlumnos real calculado
            $data['campus'],
            $pcl_Cercania,  // 'S' o 'N'
            $juntaSeccion, 
            $data['idplanclases']
        );
        $stmt->execute();
        
        // Ajustar número de registros si cambió (IGUAL QUE REGULARES)
        $diff = $data['nSalas'] - $currentState['count'];
        
        if ($diff > 0) {
            // Necesitamos MÁS salas: agregar nuevas
            $queryInsert = "INSERT INTO asignacion_piloto (
                idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
            ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 0, ?, NOW())";
            
            $stmtInsert = $conn->prepare($queryInsert);
            
            // Obtener datos del curso y nombre
            $idCurso = $dataPlanclases['cursos_idcursos'];
            $queryCurso = "SELECT CodigoCurso, Seccion FROM spre_cursos WHERE idCurso = ?";
            $stmtCurso = $conexion3->prepare($queryCurso);
            $stmtCurso->bind_param("i", $idCurso);
            $stmtCurso->execute();
            $resultCurso = $stmtCurso->get_result();
            $dataCurso = $resultCurso->fetch_assoc();
            
            $queryNombre = "SELECT NombreCurso FROM spre_ramos WHERE CodigoCurso = ?";
            $stmtNombre = $conexion3->prepare($queryNombre);
            $codigoCursoTemp = $dataCurso['CodigoCurso'];
            $stmtNombre->bind_param("s", $codigoCursoTemp);
            $stmtNombre->execute();
            $resultNombre = $stmtNombre->get_result();
            $dataNombre = $resultNombre->fetch_assoc();
            $nombreCurso = $dataNombre ? $dataNombre['NombreCurso'] : 'Curso sin nombre';
            
            // Extraer variables para bind_param
            $tipoSesion = $dataPlanclases['pcl_TipoSesion'];
            $fecha = $dataPlanclases['pcl_Fecha'];
            $horaInicio = $dataPlanclases['pcl_Inicio'];
            $horaTermino = $dataPlanclases['pcl_Termino'];
            $codigoCurso = $dataCurso['CodigoCurso'];
            $seccionReal = (int)$dataCurso['Seccion']; // ✅ SECCIÓN REAL
            
            for ($i = 0; $i < $diff; $i++) {
                $stmtInsert->bind_param(
                    "iisssssisissis",  // 15 caracteres
                    $data['idplanclases'],  // 1.  int
                    $nAlumnosReal,          // 2.  int ✅ nAlumnos real
                    $tipoSesion,            // 3.  string
                    $data['campus'],        // 4.  string
                    $fecha,                 // 5.  string
                    $horaInicio,            // 6.  string
                    $horaTermino,           // 7.  string
                    $idCurso,               // 8.  int
                    $codigoCurso,           // 9.  string
                    $seccionReal,           // 10. int ✅ SECCIÓN REAL
                    $nombreCurso,           // 11. string
                    $observacionesAsignacion, // 12. string
                    $pcl_Cercania,          // 13. string
                    $juntaSeccion,          // 14. int
                    $usuario                // 15. string
                );
                $stmtInsert->execute();
            }
            
        } elseif ($diff < 0) {
            // Necesitamos MENOS salas: eliminar las sobrantes
            $limit = abs($diff);
            $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                  WHERE idplanclases = ? AND idEstado = 0 
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

case 'modificar_asignada':
    try {
        $conn->begin_transaction();
        
        // ✅ LÓGICA EXACTA DE salas2.php
        $movilidadReducida = isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'No';
        if ($movilidadReducida === 'Si') {
            $pcl_movilidadReducida = 'S';
            $pcl_Cercania = 'S';  // Salas deben estar cerca
        } else {
            $pcl_movilidadReducida = 'N';
            $pcl_Cercania = 'N';  // Sin restricción de cercanía
        }
        
        $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - MODIFICACIÓN DE ASIGNADA: " . $data['observaciones'];
        }
        
        // ✅ ACTUALIZAR planclases_test (MISMA ESTRUCTURA QUE a_planclases)
        $stmt = $conn->prepare("UPDATE planclases_test 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isssssi", 
            $data['nSalas'], 
            $data['campus'],
            $pcl_movilidadReducida,  // 'S' o 'N'
            $pcl_Cercania,           // 'S' o 'N'
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        $stmt->execute();
        
        // Obtener datos de planclases
        $queryPlanclases = "SELECT * FROM planclases_test WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        // ✅ CALCULAR nAlumnos CORRECTAMENTE
        $nAlumnosReal = $dataPlanclases['pcl_alumnos']; // Por defecto
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $nAlumnosReal = (int)$data['alumnosTotales']; // Si junta secciones
        }
        
        // ✅ CONTAR ASIGNADAS (IGUAL QUE REGULARES)
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentAssigned = $result->fetch_assoc()['count'];
        
        // Preparar observaciones para asignacion_piloto
        $observacionModificacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionModificacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN DE ASIGNADA: " . $data['observaciones'];
        }
        
        // ✅ CAMBIAR TODAS LAS ASIGNACIONES DE ESTADO 3 A ESTADO 1 (IGUAL QUE REGULARES)
        
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                              SET idEstado = 1,
                                  idSala = '',
                                  Comentario = CASE 
                                      WHEN COALESCE(Comentario, '') = '' THEN ?
                                      ELSE CONCAT(Comentario, '\n\n', ?)
                                  END,
                                  nAlumnos = ?,
                                  campus = ?,
                                  cercania = ?,
                                  junta_seccion = ?
                              WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("ssissii", 
            $observacionModificacion,
            $observacionModificacion,
            $nAlumnosReal,      // ✅ nAlumnos real calculado
            $data['campus'],
            $pcl_Cercania,      // 'S' o 'N'
            $juntaSeccion,
            $data['idplanclases']
        );
        $stmt->execute();
        
        // ✅ CALCULAR DIFERENCIA Y AJUSTAR (IGUAL QUE REGULARES)
        $diff = intval($data['nSalas']) - $currentAssigned;
        
        if ($diff > 0) {
            // Necesitamos MÁS salas: agregar nuevas (IGUAL QUE REGULARES)
            $queryInsert = "INSERT INTO asignacion_piloto (
                idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
            ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 1, ?, NOW())";
            
            $stmtInsert = $conn->prepare($queryInsert);
            $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
            
            $comentarioNuevo = date('Y-m-d H:i:s') . " - NUEVA SALA AGREGADA EN MODIFICACIÓN";
            if (!empty($observacionModificacion)) {
                $comentarioNuevo = $observacionModificacion . "\n" . $comentarioNuevo;
            }
            
            // Obtener datos del curso y nombre (IGUAL QUE EN case modificar)
            $idCurso = $dataPlanclases['cursos_idcursos'];
            $queryCurso = "SELECT CodigoCurso, Seccion FROM spre_cursos WHERE idCurso = ?";
            $stmtCurso = $conexion3->prepare($queryCurso);
            $stmtCurso->bind_param("i", $idCurso);
            $stmtCurso->execute();
            $resultCurso = $stmtCurso->get_result();
            $dataCurso = $resultCurso->fetch_assoc();
            
            $queryNombre = "SELECT NombreCurso FROM spre_ramos WHERE CodigoCurso = ?";
            $stmtNombre = $conexion3->prepare($queryNombre);
            $codigoCursoTemp = $dataCurso['CodigoCurso'];
            $stmtNombre->bind_param("s", $codigoCursoTemp);
            $stmtNombre->execute();
            $resultNombre = $stmtNombre->get_result();
            $dataNombre = $resultNombre->fetch_assoc();
            $nombreCurso = $dataNombre ? $dataNombre['NombreCurso'] : 'Curso sin nombre';
            
            // Extraer variables para bind_param
            $tipoSesion = $dataPlanclases['pcl_TipoSesion'];
            $fecha = $dataPlanclases['pcl_Fecha'];
            $horaInicio = $dataPlanclases['pcl_Inicio'];
            $horaTermino = $dataPlanclases['pcl_Termino'];
            $codigoCurso = $dataCurso['CodigoCurso'];
            $seccionReal = (int)$dataCurso['Seccion']; // ✅ SECCIÓN REAL
            
            for ($i = 0; $i < $diff; $i++) {
                $stmtInsert->bind_param(
                    "iisssssisissis",  // 15 caracteres
                    $data['idplanclases'],  // 1.  int
                    $nAlumnosReal,          // 2.  int ✅ nAlumnos real
                    $tipoSesion,            // 3.  string
                    $data['campus'],        // 4.  string
                    $fecha,                 // 5.  string
                    $horaInicio,            // 6.  string
                    $horaTermino,           // 7.  string
                    $idCurso,               // 8.  int
                    $codigoCurso,           // 9.  string
                    $seccionReal,           // 10. int ✅ SECCIÓN REAL
                    $nombreCurso,           // 11. string
                    $comentarioNuevo,       // 12. string
                    $pcl_Cercania,          // 13. string
                    $juntaSeccion,          // 14. int
                    $usuario                // 15. string
                );
                $stmtInsert->execute();
            }
            
        } elseif ($diff < 0) {
            // Necesitamos MENOS salas: eliminar las sobrantes (IGUAL QUE REGULARES)
            $limit = abs($diff);
            $stmt = $conn->prepare("DELETE FROM asignacion_piloto 
                                  WHERE idplanclases = ? AND idEstado = 1 
                                  ORDER BY idAsignacion DESC LIMIT ?");
            $stmt->bind_param("ii", $data['idplanclases'], $limit);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Mensaje descriptivo para el usuario (IGUAL QUE REGULARES)
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

// ===== NUEVO CASO: obtener_detalles_inconsistencia =====
case 'obtener_detalles_inconsistencia':
    try {
        $idplanclases = (int)$data['idplanclases'];
        
        // Obtener datos de planclases_test para clínicos
        $queryPlanclases = "SELECT * FROM planclases_test WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $idplanclases);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $datosPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$datosPlanclases) {
            throw new Exception('No se encontró la actividad con ID: ' . $idplanclases);
        }
        
        // Obtener salas en estado 3 (asignadas)
        $querySalasAsignadas = "SELECT idSala, Comentario, timestamp, Usuario 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? AND idEstado = 3";
        $stmtSalas = $conn->prepare($querySalasAsignadas);
        $stmtSalas->bind_param("i", $idplanclases);
        $stmtSalas->execute();
        $resultSalas = $stmtSalas->get_result();
        
        $detallesSalas = [];
        
        while ($sala = $resultSalas->fetch_assoc()) {
            $idSala = $sala['idSala'];
            
            // ✅ VERIFICACIÓN COMPLETA DE RESERVAS
            $verificacion = verificarReservaCompleta($conn, $conexion3, $idplanclases);
            $infoReserva = null;
            
            // Si se encontró la reserva, obtener detalles
            if ($verificacion['encontrado']) {
                $queryReserva = "SELECT re_idSala, re_FechaReserva, re_HoraReserva, re_HoraTermino, 
                                       re_labelCurso, re_Observacion, re_RegFecha 
                                FROM reserva_2 
                                WHERE re_idRepeticion = ? LIMIT 1";
                $stmtReserva = $conn->prepare($queryReserva);
                if ($stmtReserva) {
                    $stmtReserva->bind_param("i", $idplanclases);
                    $stmtReserva->execute();
                    $resultReserva = $stmtReserva->get_result();
                    if ($resultReserva->num_rows > 0) {
                        $infoReserva = $resultReserva->fetch_assoc();
                    }
                    $stmtReserva->close();
                }
            }
            
            $detallesSalas[] = [
                'idSala' => $idSala,
                'estado_asignacion' => 'Asignada (Estado 3)',
                'verificacion' => $verificacion,
                'info_reserva' => $infoReserva,
                'comentario_asignacion' => $sala['Comentario'],
                'fecha_asignacion' => $sala['timestamp'],
                'usuario_asignacion' => $sala['Usuario']
            ];
        }
        $stmtSalas->close();
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'actividad' => $datosPlanclases,
            'salas' => $detallesSalas
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error en obtener_detalles_inconsistencia: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    break;

            // obtener_datos_solicitud para clínicos
case 'obtener_datos_solicitud':
    try {
        // Usar campos correctos de planclases_test y asignacion_piloto
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
                
// ===== CASO MEJORADO: obtener_salas_asignadas =====
case 'obtener_salas_asignadas':
    try {
        $idplanclases = (int)$data['idPlanClase'];
        
        // Consulta mejorada que incluye verificación de reservas
        $stmt = $conn->prepare("SELECT idAsignacion, idSala, Comentario, timestamp, Usuario
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? 
                               AND idSala IS NOT NULL 
                               AND idEstado = 3");
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $salas = array();
        while ($row = $result->fetch_assoc()) {
            // Para cada sala, verificar si tiene reserva
            $verificacion = verificarReservaCompleta($conn, $conexion3, $idplanclases);
            
            $salas[] = [
                'idAsignacion' => $row['idAsignacion'],
                'idSala' => $row['idSala'],
                'comentario' => $row['Comentario'],
                'timestamp' => $row['timestamp'],
                'usuario' => $row['Usuario'],
                'tieneReserva' => $verificacion['encontrado'],
                'estadoReserva' => $verificacion['encontrado'] ? 
                    ($verificacion['metodo'] == 'paso1' ? 'confirmada' : 'encontrada_alt') : 
                    'sin_reserva',
                'detalleVerificacion' => $verificacion['detalle']
            ];
        }
        
        echo json_encode(['success' => true, 'salas' => $salas]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;
                
// ===== CASO MEJORADO: liberar =====
case 'liberar':
    try {
        $conn->begin_transaction();
        
        $idAsignacion = (int)$data['idAsignacion'];
        
        // Obtener información de la asignación antes de liberar
        $stmtInfo = $conn->prepare("SELECT idplanclases, idSala FROM asignacion_piloto WHERE idAsignacion = ?");
        $stmtInfo->bind_param("i", $idAsignacion);
        $stmtInfo->execute();
        $resultInfo = $stmtInfo->get_result();
        $infoAsignacion = $resultInfo->fetch_assoc();
        
        if (!$infoAsignacion) {
            throw new Exception('No se encontró la asignación especificada');
        }
        
        $idplanclases = $infoAsignacion['idplanclases'];
        $idSala = $infoAsignacion['idSala'];
        
        // ===== PASO 1: LIBERAR LA ASIGNACIÓN =====
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                               SET idSala = NULL, idEstado = 4,
                                   Comentario = CONCAT(IFNULL(Comentario, ''), '\n\n', ?, ' - SALA LIBERADA MANUALMENTE')
                               WHERE idAsignacion = ?");
        $timestampLiberacion = date('Y-m-d H:i:s');
        $stmt->bind_param("si", $timestampLiberacion, $idAsignacion);
        $stmt->execute();
        
        // ===== PASO 2: LIBERAR LA RESERVA CORRESPONDIENTE =====
        $resultadoReserva = liberarReservaCompleta($conn, $idplanclases, $idSala);
        
        // ===== PASO 3: ACTUALIZAR CONTADOR EN PLANCLASES_TEST =====
        $stmt = $conn->prepare("UPDATE planclases_test p
                              SET pcl_nSalas = (
                                  SELECT COUNT(*) 
                                  FROM asignacion_piloto 
                                  WHERE idplanclases = p.idplanclases 
                                  AND idEstado IN (0,1,3)
                              )
                              WHERE p.idplanclases = ?");
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        
        $conn->commit();
        
        // Respuesta con detalles de lo que se liberó
        echo json_encode([
            'success' => true,
            'message' => 'Sala liberada correctamente',
            'detalles' => [
                'sala_liberada' => $idSala,
                'asignacion_actualizada' => true,
                'reserva_procesada' => $resultadoReserva
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;
				
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

// ===== QUERY PRINCIPAL MEJORADA CON VERIFICACIÓN DE RESERVAS =====
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
    (SELECT GROUP_CONCAT(DISTINCT CONCAT(idSala, ':', idAsignacion))
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases AND idEstado != 4) AS salas_asignadas,
    (SELECT COUNT(*)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases AND idEstado = 3) AS salas_confirmadas,
    (SELECT COUNT(*)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases 
     AND idEstado = 0) AS salas_solicitadas,
    (SELECT COUNT(*)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases 
     AND idEstado = 1) AS salas_modificacion,
    (SELECT MAX(idEstado)
     FROM asignacion_piloto
     WHERE idplanclases = p.idplanclases 
     AND idEstado != 4) AS estado_maximo
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
    .badge-danger { background-color: #dc3545; }
    
    /* Estilos para estados de reserva */
    .estado-confirmada { background-color: #d1edff; }
    .estado-encontrada-alt { background-color: #fff3cd; }
    .estado-sin-reserva { background-color: #f8d7da; }
    .estado-inconsistente { background-color: #f8d7da; }
</style>

  <div class="container py-4"> 


<!-- ===== VERSIÓN SÚPER SIMPLE CON BOOTSTRAP ===== -->

<div class="accordion mb-4" id="accordionInstrucciones">
    <div class="accordion-item border-warning">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed bg-warning bg-opacity-10 text-warning fw-bold" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseInstrucciones"
                    aria-expanded="false" 
                    aria-controls="collapseInstrucciones">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Instrucciones Importantes para Uso de Salas
            </button>
        </h2>
        <div id="collapseInstrucciones" 
             class="accordion-collapse collapse" 
             data-bs-parent="#accordionInstrucciones">
            <div class="accordion-body">
                <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <i class="bi bi-clipboard-check text-primary me-2"></i>
                    Toda actividad tipo <strong>clase</strong> se solicitará automáticamente. El resto de las actividades los debe solicitar pinchando en <strong>"Solicitar"</strong>.
                </li>
                <li class="list-group-item">
                    <i class="bi bi-pencil-square text-success me-2"></i>
                    Si al enviar la solicitud cometió un error o si le asignaron salas y alguna no les sirve, o les falta otra sala, puede pinchar en <strong>"Modificar"</strong>.
                </li>
                <li class="list-group-item">
                    <i class="bi bi-people text-info me-2"></i>
                    Si el curso posee más de una sección y necesitan juntarlas para una evaluación u otra actividad, para cualquier sección puede pinchar en <strong>"Juntar todas las secciones"</strong> (se sumarán automáticamente el total de estudiantes). Si la actividad es tipo <strong>clase</strong>, pinche en <strong>"Modificar"</strong> y luego podrá pinchar en la misma opción.
                </li>            
                <li class="list-group-item">
                    <i class="bi bi-box-arrow-left text-danger me-2"></i>
                    Finalmente, si tiene asignada una o más salas y ya no la utilizará, debe pinchar en <strong>"Liberar"</strong>, y aparecerá una ventana para que elija cuál sala liberar.
                </li>
            </ul>
        </div>
    </div>
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
                        <th>Verificación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): 
                        $fecha = new DateTime($row['pcl_Fecha']);
                        
                        // ===== PROCESAMIENTO DE SALAS Y VERIFICACIÓN =====
                        $salasData = [];
                        $salasConReserva = 0;
                        $salasInconsistentes = 0;
                        
                        if (!empty($row['salas_asignadas'])) {
                            $salasAsignadas = explode(',', $row['salas_asignadas']);
                            foreach ($salasAsignadas as $salaInfo) {
                                $partes = explode(':', $salaInfo);
                                if (count($partes) >= 2) {
                                    $idSala = $partes[0];
                                    $idAsignacion = $partes[1];
                                    
                                    // Verificar reserva para esta actividad
                                    $verificacion = verificarReservaCompleta($conn, $conexion3, $row['idplanclases']);
                                    
                                    $salasData[] = [
                                        'idSala' => $idSala,
                                        'idAsignacion' => $idAsignacion,
                                        'verificacion' => $verificacion
                                    ];
                                    
                                    if ($verificacion['encontrado']) {
                                        $salasConReserva++;
                                    } else {
                                        $salasInconsistentes++;
                                    }
                                }
                            }
                        }
                        
                        // ===== DETERMINACIÓN DE ESTADOS =====
                        $tieneAsignaciones = !empty($salasData);
                        $tieneSolicitudes = $row['salas_solicitadas'] > 0;
                        $tieneModificaciones = $row['salas_modificacion'] > 0;
                        $todasConfirmadas = $row['salas_confirmadas'] == $row['pcl_nSalas'] && $row['pcl_nSalas'] > 0;
                        $tieneInconsistencias = $salasInconsistentes > 0;
                    ?>
                    <tr data-id="<?php echo $row['idplanclases']; ?>" data-alumnos="<?php echo $cupoCurso; ?>"
                        class="<?php echo $tieneInconsistencias ? 'estado-inconsistente' : 
                                     ($salasConReserva > 0 ? 'estado-confirmada' : ''); ?>">
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
                                    <?php foreach($salasData as $sala): ?>
                                        <li>
                                            <span class="badge bg-success"><?php echo $sala['idSala']; ?></span>
                                        </li>
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
                            <?php elseif($tieneModificaciones): ?>
                                <span class="badge bg-info">En modificación</span>
                            <?php elseif($tieneSolicitudes): ?>
                                <span class="badge bg-info">Solicitada</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($tieneAsignaciones): ?>
                                <?php if($salasConReserva > 0 && $salasInconsistentes == 0): ?>
                                    <span class="badge bg-success">✓ Reservas OK</span>
                                <?php elseif($salasConReserva > 0 && $salasInconsistentes > 0): ?>
                                    <span class="badge bg-warning">⚠ Parcial</span>
                                    <br><small><?php echo $salasConReserva; ?> OK, <?php echo $salasInconsistentes; ?> faltantes</small>
                                <?php else: ?>
                                    <span class="badge bg-danger">❌ Sin reservas</span>
                                <?php endif; ?>
                                <?php if($salasInconsistentes > 0): ?>
                                    <br><button type="button" class="btn btn-sm btn-outline-danger mt-1" 
                                            onclick="mostrarDetallesInconsistencia(<?php echo $row['idplanclases']; ?>)">
                                        <i class="bi bi-exclamation-triangle"></i> Ver detalles
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <?php if($tieneAsignaciones || $tieneSolicitudes || $tieneModificaciones): ?>
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
                            <td colspan="11" class="text-center">No hay actividades disponibles para gestionar salas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Modal para Solicitar/Modificar Sala (sin cambios) -->
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

<!-- Modal para Liberar Salas (mejorado) -->
<div class="modal fade" id="liberarSalaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Liberar Salas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    Seleccione las salas que desea liberar. Esta acción liberará tanto la asignación como la reserva correspondiente.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sala</th>
                                <th>Estado Reserva</th>
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

<!-- NUEVO: Modal para Detalles de Inconsistencias -->
<div class="modal fade" id="inconsistenciaModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-warning"></i>
                    Detalles de Inconsistencias de Reservas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <h6><i class="bi bi-exclamation-triangle"></i> ¿Qué significa una inconsistencia?</h6>
                    <p class="mb-2">Una inconsistencia ocurre cuando una sala aparece como <strong>"asignada"</strong> en el sistema de actividades pero <strong>no se encuentra la reserva correspondiente</strong> en el sistema de salas.</p>
                    <p class="mb-0">Esto puede suceder cuando el personal de salas modifica o elimina reservas directamente en su sistema sin notificar al sistema de actividades.</p>
                </div>
                
                <!-- Detalles de verificación -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-search"></i>
                            Resultados de Verificación por Sala
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="contenido-detalles-inconsistencia">
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando detalles...</span>
                                </div>
                                <p class="mt-2 text-muted">Analizando inconsistencias...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Métodos de búsqueda -->
                <div class="alert alert-secondary mt-3">
                    <h6><i class="bi bi-info-circle"></i> Métodos de Verificación Utilizados</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <h6>🎯 Paso 1 - ID Repetición:</h6>
                            <ul class="mb-0">
                                <li>Búsqueda directa por ID de actividad</li>
                                <li>Método más confiable</li>
                                <li>Indica reserva creada correctamente</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>🔍 Paso 2 - Código y Horario:</h6>
                            <ul class="mb-0">
                                <li>Búsqueda por código de curso</li>
                                <li>Fecha y horarios de la actividad</li>
                                <li>Puede indicar reserva manual</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>❌ Sin Encontrar:</h6>
                            <ul class="mb-0">
                                <li>No se encontró reserva</li>
                                <li>Posible eliminación manual</li>
                                <li>Requiere atención urgente</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Recomendaciones -->
                <div class="alert alert-info">
                    <h6><i class="bi bi-lightbulb"></i> Recomendaciones</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Para salas encontradas por método alternativo (🔍):</h6>
                            <ul class="mb-0">
                                <li>La reserva existe pero con parámetros diferentes</li>
                                <li>Posiblemente el personal de salas modificó datos</li>
                                <li>Contactar para actualizar el ID de repetición</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Para salas no encontradas (❌):</h6>
                            <ul class="mb-0">
                                <li>La reserva fue eliminada del sistema de salas</li>
                                <li>Contactar urgentemente al área de salas</li>
                                <li>Considerar liberar y volver a solicitar</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cerrar
                </button>
                <button type="button" class="btn btn-info" onclick="contactarAreaSalas()">
                    <i class="bi bi-telephone"></i> Contactar Área de Salas
                </button>
                <button type="button" class="btn btn-warning" onclick="modificarSalaDesdeInconsistencia()">
                    <i class="bi bi-pencil"></i> Modificar Actividad
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>