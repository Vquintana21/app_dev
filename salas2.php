<?php

ob_start();
include("conexion.php");
$error_output = ob_get_clean();

// Si hay errores de inclusión, los registramos pero no los mostramos
if (!empty($error_output)) {
    error_log("Errores antes de JSON: " . $error_output);
}

// Asegurarnos de que se envíe el header de contenido correcto
header('Content-Type: application/json');

function estaDisponibleFinal($conn, $idSala, $fecha, $horaInicio, $horaFin) {
    $queryReserva = "SELECT * FROM reserva_2 
                     WHERE re_idSala = ?
                     AND re_FechaReserva = ?
                     AND ((re_HoraReserva <= ? AND re_HoraTermino > ?) 
                          OR (re_HoraReserva < ? AND re_HoraTermino >= ?) 
                          OR (? <= re_HoraReserva AND ? >= re_HoraTermino))";
    
    $stmtReserva = $conn->prepare($queryReserva);
    $stmtReserva->bind_param("ssssssss", 
        $idSala, $fecha, 
        $horaInicio, $horaFin,
        $horaInicio, $horaFin,
        $horaInicio, $horaFin
    );
    $stmtReserva->execute();
    $resultReserva = $stmtReserva->get_result();
    
    $disponible = $resultReserva->num_rows === 0;
    $stmtReserva->close();
    
    return $disponible;
}


function obtenerAlumnosReales($data, $dataPlanclases) {
    // ✅ DEBUG EXHAUSTIVO
    error_log("🔍 === DEBUG obtenerAlumnosReales ===");
    error_log("🔍 data recibido: " . json_encode($data));
    error_log("🔍 dataPlanclases['pcl_alumnos']: " . $dataPlanclases['pcl_alumnos']);
    
    // Verificar cada campo individualmente
    $tieneAlumnosPorSala = isset($data['alumnosPorSala']);
    $alumnosPorSalaValue = $tieneAlumnosPorSala ? $data['alumnosPorSala'] : 'NO_EXISTE';
    $estaVacio = empty($data['alumnosPorSala']);
    
    error_log("🔍 isset(data['alumnosPorSala']): " . ($tieneAlumnosPorSala ? 'TRUE' : 'FALSE'));
    error_log("🔍 data['alumnosPorSala'] value: " . $alumnosPorSalaValue);
    error_log("🔍 empty(data['alumnosPorSala']): " . ($estaVacio ? 'TRUE' : 'FALSE'));
    
    // Aplicar la lógica paso a paso
    if ($tieneAlumnosPorSala && !$estaVacio) {
        $nAlumnosPorSala = (int)$data['alumnosPorSala'];
        error_log("🔍 USANDO FRONTEND: " . $nAlumnosPorSala);
    } else {
        $nAlumnosPorSala = $dataPlanclases['pcl_alumnos'];
        error_log("🔍 USANDO BD (fallback): " . $nAlumnosPorSala);
    }
    
    // Debug final
    error_log("🔍 RESULTADO FINAL: " . $nAlumnosPorSala);
    error_log("🔍 === FIN DEBUG ===");
    
    return $nAlumnosPorSala;
}

function verificarReservaCompleta($conn, $idplanclases, $codigo_curso, $seccion, $fecha, $hora_inicio, $hora_termino, $idSala = null) {
    $codigo_completo = $codigo_curso . "-" . $seccion;
    
    // PASO 1: Buscar por re_idRepeticion (más directo)
    $queryPaso1 = "SELECT COUNT(*) as existe, 'paso1' as metodo 
                   FROM reserva_2 
                   WHERE re_idRepeticion = ?";
    
    if ($idSala) {
        $queryPaso1 .= " AND re_idSala = ?";
        $stmtPaso1 = $conn->prepare($queryPaso1);
        $stmtPaso1->bind_param("is", $idplanclases, $idSala);
    } else {
        $stmtPaso1 = $conn->prepare($queryPaso1);
        $stmtPaso1->bind_param("i", $idplanclases);
    }
    
    $stmtPaso1->execute();
    $resultPaso1 = $stmtPaso1->get_result();
    $rowPaso1 = $resultPaso1->fetch_assoc();
    $stmtPaso1->close();
    
    if ($rowPaso1['existe'] > 0) {
        return ['encontrado' => true, 'metodo' => 'paso1', 'detalle' => 'Encontrado por ID repetición'];
    }
    
    // PASO 2: Buscar por código-sección, fecha y horarios
    $queryPaso2 = "SELECT COUNT(*) as existe, 'paso2' as metodo 
                   FROM reserva_2 
                   WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                   AND re_FechaReserva = ? 
                   AND re_HoraReserva = ? 
                   AND re_HoraTermino = ?";
    
    if ($idSala) {
        $queryPaso2 .= " AND re_idSala = ?";
    }
    
    $stmtPaso2 = $conn->prepare($queryPaso2);
    $codigoBusqueda = "%{$codigo_completo}%";
    
    if ($idSala) {
        $stmtPaso2->bind_param("ssssss", $codigoBusqueda, $codigoBusqueda, $fecha, $hora_inicio, $hora_termino, $idSala);
    } else {
        $stmtPaso2->bind_param("sssss", $codigoBusqueda, $codigoBusqueda, $fecha, $hora_inicio, $hora_termino);
    }
    
    $stmtPaso2->execute();
    $resultPaso2 = $stmtPaso2->get_result();
    $rowPaso2 = $resultPaso2->fetch_assoc();
    $stmtPaso2->close();
    
    if ($rowPaso2['existe'] > 0) {
        return ['encontrado' => true, 'metodo' => 'paso2', 'detalle' => 'Encontrado por código-sección y horario'];
    }
    
    // PASO 3: No se encontró - Inconsistencia
    return ['encontrado' => false, 'metodo' => 'ninguno', 'detalle' => 'No se encontró reserva por ningún método'];
}

// Función para manejo de errores fatales
function shutdown_handler() {
    $last_error = error_get_last();
    if ($last_error['type'] === E_ERROR) {
        // Limpiar cualquier salida anterior
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Devolver JSON de error
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error fatal del servidor',
            'debug_info' => [
                'message' => $last_error['message'],
                'file' => basename($last_error['file']),
                'line' => $last_error['line']
            ]
        ]);
    }
}

// Registrar el manejador de errores fatales
register_shutdown_function('shutdown_handler');

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
            throw new Exception('Error decodificando JSON: ' . json_last_error_msg());
        }
        
        // Verificar si existe el parámetro action
        if (!isset($data['action'])) {
            throw new Exception('Parámetro "action" requerido');
        }
        
        // Log de la acción recibida
        error_log("🔄 Procesando acción: " . $data['action']);
        
        switch ($data['action']) {
    
 case 'solicitar':
 
    try {
        $conn->begin_transaction();
        
        // Verificar si requiere sala
        $requiereSala = isset($data['requiereSala']) ? (int)$data['requiereSala'] : 1;
        $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
        
        // Procesar movilidad reducida y cercanía
        $movilidadReducida = isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'No';
        if ($movilidadReducida === 'Si') {
            $pcl_movilidadReducida = 'S';
            $pcl_Cercania = 'S';
        } else {
            $pcl_movilidadReducida = 'N';
            $pcl_Cercania = 'N';
        }
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
        }
        
        // ACTUALIZAR planclases
        $stmt = $conn->prepare("UPDATE a_planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_DeseaSala = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isissisi", 
            $data['nSalas'], 
            $data['campus'], 
            $requiereSala,
            $pcl_movilidadReducida,
            $pcl_Cercania,
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error actualizando planclases: " . $stmt->error);
        }
        
        if ($requiereSala == 0) {
            // Si NO requiere sala, liberar asignaciones
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
        $queryPlanclases = "SELECT * FROM a_planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$dataPlanclases) {
            throw new Exception("No se encontraron datos de planclases para ID: " . $data['idplanclases']);
        }
        
        // Preparar observaciones para asignacion_piloto
        $observacionesAsignacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesAsignacion = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
        }
        
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        if ($usuario === null || $usuario === '') {
            $usuario = 'sistema';
        }
        
		$nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
		  error_log("🎮 nAlumnosReal calculado: " . $nAlumnosReal);
		
        error_log("🔍 Usuario configurado: '" . $usuario . "'");
        
        $queryInsert = "INSERT INTO asignacion_piloto (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 0, ?, NOW())";
        
        $stmtInsert = $conn->prepare($queryInsert);
        
        if (!$stmtInsert) {
            throw new Exception("Error preparando INSERT: " . $conn->error);
        }
        
        // Crear múltiples registros según el número de salas
        for ($i = 0; $i < $data['nSalas']; $i++) {
            
            // ✅ CADENA CORREGIDA: 15 caracteres para 15 parámetros
            $result = $stmtInsert->bind_param(
                "iisssssississis",                   // ✅ 15 caracteres
                // i-i-s-s-s-s-s-i-s-i-s-s-s-i-s
                $data['idplanclases'],               // 1.  i (convertir string a int)
                $nAlumnosReal,      // 2.  i
                $dataPlanclases['pcl_TipoSesion'],   // 3.  s
                $data['campus'],                     // 4.  s
                $dataPlanclases['pcl_Fecha'],        // 5.  s
                $dataPlanclases['pcl_Inicio'],       // 6.  s
                $dataPlanclases['pcl_Termino'],      // 7.  s
                $dataPlanclases['cursos_idcursos'],  // 8.  i
                $dataPlanclases['pcl_AsiCodigo'],    // 9.  s
                $dataPlanclases['pcl_Seccion'],      // 10. i
                $dataPlanclases['pcl_AsiNombre'],    // 11. s
                $observacionesAsignacion,            // 12. s
                $pcl_Cercania,                       // 13. s
                $juntaSeccion,                       // 14. i
                $usuario                             // 15. s (ya no NULL)
            );
            
            if (!$result) {
                error_log("❌ bind_param falló: " . $stmtInsert->error);
                throw new Exception("Error en bind_param iteración $i: " . $stmtInsert->error);
            }
            
            if (!$stmtInsert->execute()) {
                error_log("❌ execute falló: " . $stmtInsert->error);
                throw new Exception("Error ejecutando INSERT iteración $i: " . $stmtInsert->error);
            }
            
            error_log("✅ INSERT exitoso - iteración $i, idplanclases: " . $data['idplanclases']);
        }
        
        $stmtInsert->close();
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Solicitud creada exitosamente para {$data['nSalas']} sala(s)"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("❌ Error en case 'solicitar': " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    break;

case 'modificar':
    try {
        $conn->begin_transaction();
        
        // Verificar si requiere sala
        $requiereSala = isset($data['requiereSala']) ? (int)$data['requiereSala'] : 1;
        $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
        
	// Log 
        $juntarSeccionesValue = isset($data['juntarSecciones']) ? $data['juntarSecciones'] : 'NO_ENVIADO';
        error_log("DEBUG - juntarSecciones recibido: " . var_export($juntarSeccionesValue, true));
        error_log("DEBUG - juntaSeccion calculado: " . $juntaSeccion);

        // NUEVA LÓGICA: Procesar movilidad reducida y cercanía
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
        
        // ACTUALIZADA: Incluir pcl_movilidadReducida y pcl_Cercania
        $stmt = $conn->prepare("UPDATE a_planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_DeseaSala = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isissisi", 
            $data['nSalas'], 
            $data['campus'], 
            $requiereSala,
            $pcl_movilidadReducida,  // 'S' o 'N'
            $pcl_Cercania,           // 1 o 0
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
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
            $queryPlanclases = "SELECT * FROM a_planclases WHERE idplanclases = ?";
            $stmtPlanclases = $conn->prepare($queryPlanclases);
            $stmtPlanclases->bind_param("i", $data['idplanclases']);
            $stmtPlanclases->execute();
            $resultPlanclases = $stmtPlanclases->get_result();
            $dataPlanclases = $resultPlanclases->fetch_assoc();
            
            // Obtener observaciones existentes de asignacion_piloto
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
            
            // Concatenar nueva observación para asignacion_piloto
            $nuevaObservacionAsignacion = $obsAnterior;
            if (isset($data['observaciones']) && !empty($data['observaciones'])) {
                if (!empty($obsAnterior)) {
                    $nuevaObservacionAsignacion .= "\n\n" . date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
                } else {
                    $nuevaObservacionAsignacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
                }
            }
			
			$nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
            
            // ACTUALIZADA: Incluir cercanía en la actualización de asignacion_piloto
            $stmt = $conn->prepare("UPDATE asignacion_piloto 
                                  SET Comentario = ?,
                                      nAlumnos = ?,
                                      campus = ?,
                                      cercania = ?,
                                      junta_seccion = ?
                                  WHERE idplanclases = ? AND idEstado = 0");
            $stmt->bind_param("ssiiii", 
                $nuevaObservacionAsignacion, 
                $nAlumnosReal,
                $data['campus'],
                $pcl_Cercania,  // ACTUALIZAR CERCANÍA
                $juntaSeccion,
                $data['idplanclases']
            );
            $stmt->execute();
            
            // Ajustar número de registros si cambió
            $diff = $data['nSalas'] - $currentState['count'];
            
            if ($diff > 0) {
            $queryInsert = "INSERT INTO asignacion_piloto (
                idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
            ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 0, ?, NOW())";
            
            $stmtInsert = $conn->prepare($queryInsert);
            $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
            $nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
            
            // ===== DEBUG ANTES DEL BIND_PARAM =====
            error_log("🔍 === DEBUG CASE MODIFICAR - BIND_PARAM ===");
            
            // Extraer valores a variables individuales
            $param1 = $data['idplanclases'];                // int
            $param2 = $nAlumnosReal;                         // int  
            $param3 = $dataPlanclases['pcl_TipoSesion'];     // string
            $param4 = $data['campus'];                       // string
            $param5 = $dataPlanclases['pcl_Fecha'];          // string
            $param6 = $dataPlanclases['pcl_Inicio'];         // string
            $param7 = $dataPlanclases['pcl_Termino'];        // string
            $param8 = $dataPlanclases['cursos_idcursos'];    // int
            $param9 = $dataPlanclases['pcl_AsiCodigo'];      // string
            $param10 = $dataPlanclases['pcl_Seccion'];       // int
            $param11 = $dataPlanclases['pcl_AsiNombre'];     // string
            $param12 = $observacionesAsignacion;            // string
            $param13 = $pcl_Cercania;                       // string
            $param14 = $juntaSeccion;                       // int
            $param15 = $usuario;                            // string
            
            // Debug de tipos y valores
            error_log("🔍 Parámetro 1 (idplanclases): " . var_export($param1, true) . " - Tipo: " . gettype($param1));
            error_log("🔍 Parámetro 2 (nAlumnosReal): " . var_export($param2, true) . " - Tipo: " . gettype($param2));
            error_log("🔍 Parámetro 3 (pcl_TipoSesion): " . var_export($param3, true) . " - Tipo: " . gettype($param3));
            error_log("🔍 Parámetro 4 (campus): " . var_export($param4, true) . " - Tipo: " . gettype($param4));
            error_log("🔍 Parámetro 5 (pcl_Fecha): " . var_export($param5, true) . " - Tipo: " . gettype($param5));
            error_log("🔍 Parámetro 6 (pcl_Inicio): " . var_export($param6, true) . " - Tipo: " . gettype($param6));
            error_log("🔍 Parámetro 7 (pcl_Termino): " . var_export($param7, true) . " - Tipo: " . gettype($param7));
            error_log("🔍 Parámetro 8 (cursos_idcursos): " . var_export($param8, true) . " - Tipo: " . gettype($param8));
            error_log("🔍 Parámetro 9 (pcl_AsiCodigo): " . var_export($param9, true) . " - Tipo: " . gettype($param9));
            error_log("🔍 Parámetro 10 (pcl_Seccion): " . var_export($param10, true) . " - Tipo: " . gettype($param10));
            error_log("🔍 Parámetro 11 (pcl_AsiNombre): " . var_export($param11, true) . " - Tipo: " . gettype($param11));
            error_log("🔍 Parámetro 12 (observacionesAsignacion): " . var_export($param12, true) . " - Tipo: " . gettype($param12));
            error_log("🔍 Parámetro 13 (pcl_Cercania): " . var_export($param13, true) . " - Tipo: " . gettype($param13));
            error_log("🔍 Parámetro 14 (juntaSeccion): " . var_export($param14, true) . " - Tipo: " . gettype($param14));
            error_log("🔍 Parámetro 15 (usuario): " . var_export($param15, true) . " - Tipo: " . gettype($param15));
            
            // Verificar cadena de tipos
            $tiposString = "iissssississis";
            error_log("🔍 Cadena de tipos: '$tiposString' - Longitud: " . strlen($tiposString));
            error_log("🔍 Total parámetros: 15");
            
            // Verificar que ningún parámetro sea null
            $parametros = [$param1, $param2, $param3, $param4, $param5, $param6, $param7, $param8, $param9, $param10, $param11, $param12, $param13, $param14, $param15];
            foreach ($parametros as $i => $param) {
                if ($param === null) {
                    error_log("❌ PARÁMETRO " . ($i + 1) . " ES NULL!");
                }
                if (!isset($param)) {
                    error_log("❌ PARÁMETRO " . ($i + 1) . " NO ESTÁ DEFINIDO!");
                }
            }
            
            for ($i = 0; $i < $diff; $i++) {
                error_log("🔍 Intentando bind_param iteración $i");
                
                $result = $stmtInsert->bind_param(
                    "iissssississis",  // 15 caracteres
                    $param1,   // 1 - idplanclases (int)
                    $param2,   // 2 - nAlumnosReal (int)
                    $param3,   // 3 - pcl_TipoSesion (string)
                    $param4,   // 4 - campus (string)
                    $param5,   // 5 - pcl_Fecha (string)
                    $param6,   // 6 - pcl_Inicio (string)
                    $param7,   // 7 - pcl_Termino (string)
                    $param8,   // 8 - cursos_idcursos (int)
                    $param9,   // 9 - pcl_AsiCodigo (string)
                    $param10,  // 10 - pcl_Seccion (int)
                    $param11,  // 11 - pcl_AsiNombre (string)
                    $param12,  // 12 - observacionesAsignacion (string)
                    $param13,  // 13 - pcl_Cercania (string)
                    $param14,  // 14 - juntaSeccion (int)
                    $param15   // 15 - usuario (string)
                );
                
                if (!$result) {
                    error_log("❌ bind_param falló en iteración $i: " . $stmtInsert->error);
                    throw new Exception("Error en bind_param iteración $i: " . $stmtInsert->error);
                }
                
                if (!$stmtInsert->execute()) {
                    error_log("❌ execute falló en iteración $i: " . $stmtInsert->error);
                    throw new Exception("Error ejecutando INSERT iteración $i: " . $stmtInsert->error);
                }
                
                error_log("✅ bind_param y execute exitosos en iteración $i");
            }
            error_log("🔍 === FIN DEBUG CASE MODIFICAR ===");
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
        
        //  Procesar movilidad reducida y cercanía
        $movilidadReducida = isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'No';
        if ($movilidadReducida === 'Si') {
            $pcl_movilidadReducida = 'S';
            $pcl_Cercania = 'S';  // Salas deben estar cerca
        } else {
            $pcl_movilidadReducida = 'N';
            $pcl_Cercania = 'N';  // Sin restricción de cercanía
        }
        
        $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
		
		// Log 
        $juntarSeccionesValue = isset($data['juntarSecciones']) ? $data['juntarSecciones'] : 'NO_ENVIADO';
        error_log("DEBUG - juntarSecciones recibido: " . var_export($juntarSeccionesValue, true));
        error_log("DEBUG - juntaSeccion calculado: " . $juntaSeccion);
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - MODIFICACIÓN DE ASIGNADA: " . $data['observaciones'];
        }
        
        // ACTUALIZADA: Incluir pcl_movilidadReducida y pcl_Cercania
        $stmt = $conn->prepare("UPDATE a_planclases 
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
            $pcl_Cercania,           // 1 o 0
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        $stmt->execute();
        
        // Obtener datos de planclases
        $queryPlanclases = "SELECT * FROM a_planclases WHERE idplanclases = ?";
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
        
        // 3. Preparar observaciones para asignacion_piloto
        $observacionModificacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionModificacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN DE ASIGNADA: " . $data['observaciones'];
        }
		
		$nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
        
        // ACTUALIZADA: Cambiar TODAS las asignaciones de estado 3 a estado 1 e incluir cercanía
        $stmt = $conn->prepare("UPDATE asignacion_piloto 
                              SET idEstado = 1,
                                  Comentario = CASE 
                                      WHEN COALESCE(Comentario, '') = '' THEN ?
                                      ELSE CONCAT(Comentario, '\n\n', ?)
                                  END,
                                  nAlumnos = ?,
                                  campus = ?,
                                  cercania = ?,
                                  junta_seccion = ?
                              WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("sssssii", 
            $observacionModificacion,
            $observacionModificacion,
            $nAlumnosReal,
            $data['campus'],
            $pcl_Cercania,  // ACTUALIZAR CERCANÍA
            $juntaSeccion,
            $data['idplanclases']
        );
        $stmt->execute();
        
        // 5. Calcular la diferencia
        $diff = intval($data['nSalas']) - $currentAssigned;
        
        if ($diff > 0) {
            $queryInsert = "INSERT INTO asignacion_piloto (
                idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
            ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 1, ?, NOW())";
            
            $stmtInsert = $conn->prepare($queryInsert);
            $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
			$nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
            $comentarioNuevo = date('Y-m-d H:i:s') . " - NUEVA SALA AGREGADA EN MODIFICACIÓN";
            if (!empty($observacionModificacion)) {
                $comentarioNuevo = $observacionModificacion . "\n" . $comentarioNuevo;
            }
            
            for ($i = 0; $i < $diff; $i++) {
                $stmtInsert->bind_param(
				"iissssississis",  // 15 caracteres
				$data['idplanclases'],               // 1
				$nAlumnosReal,      // 2
				$dataPlanclases['pcl_TipoSesion'],   // 3
				$data['campus'],                     // 4
				$dataPlanclases['pcl_Fecha'],        // 5
				$dataPlanclases['pcl_Inicio'],       // 6
				$dataPlanclases['pcl_Termino'],      // 7
				$dataPlanclases['cursos_idcursos'],  // 8
				$dataPlanclases['pcl_AsiCodigo'],    // 9
				$dataPlanclases['pcl_Seccion'],      // 10
				$dataPlanclases['pcl_AsiNombre'],    // 11
				$observacionesAsignacion,            // 12
				$pcl_Cercania,                       // 13 (string)
				$juntaSeccion,                       // 14
				$usuario                             // 15
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
        // ACTUALIZADA: Incluir pcl_movilidadReducida en la consulta
        $stmt = $conn->prepare("SELECT p.pcl_campus, p.pcl_nSalas, p.pcl_DeseaSala, 
                               p.pcl_observaciones, p.pcl_movilidadReducida,
                               (SELECT COUNT(*) FROM asignacion_piloto 
                                WHERE idplanclases = p.idplanclases 
                                AND idEstado = 3) as salas_asignadas
                               FROM a_planclases p 
                               WHERE p.idplanclases = ?");
        
        $stmt->bind_param("i", $data['idPlanClase']);
        $stmt->execute();
        $result = $stmt->get_result();
        $datos = $result->fetch_assoc();
        
        if ($datos) {
            // Preparar mensaje para mostrar observaciones anteriores
            $mensajeAnterior = '';
            if (!empty($datos['pcl_observaciones'])) {
                $mensajeAnterior = "=== MENSAJES ANTERIORES ===\n" . $datos['pcl_observaciones'] . "\n\n=== NUEVO MENSAJE ===\n";
            }
            
            // NUEVA LÓGICA: Convertir S/N a Si/No para el frontend
            $movilidadReducidaFrontend = ($datos['pcl_movilidadReducida'] === 'S') ? 'Si' : 'No';
            
            echo json_encode([
                'success' => true,
                'pcl_campus' => $datos['pcl_campus'],
                'pcl_nSalas' => $datos['pcl_nSalas'],
                'pcl_DeseaSala' => $datos['pcl_DeseaSala'],
                'pcl_movilidadReducida' => $movilidadReducidaFrontend,  // NUEVO CAMPO
                'observaciones' => $datos['pcl_observaciones'],
                'mensajeAnterior' => $mensajeAnterior,
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
        $stmt = $conn->prepare("UPDATE a_planclases p
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
    
    // salas computacion
    
    case 'guardar_con_computacion':
    try {
       $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
	   
	// Log 
        $juntarSeccionesValue = isset($data['juntarSecciones']) ? $data['juntarSecciones'] : 'NO_ENVIADO';
        error_log("DEBUG - juntarSecciones recibido: " . var_export($juntarSeccionesValue, true));
        error_log("DEBUG - juntaSeccion calculado: " . $juntaSeccion);

        $conn->begin_transaction();
        
        // Validar parámetros
        if (!isset($data['idplanclases']) || !isset($data['salas_computacion'])) {
            throw new Exception('Parámetros faltantes para reserva de computación');
        }
        
        $idplanclases = (int)$data['idplanclases'];
        $salasComputacion = $data['salas_computacion'];
        $observaciones = isset($data['observaciones']) ? $data['observaciones'] : '';
        $requiereSala = isset($data['requiereSala']) ? (int)$data['requiereSala'] : 1;
        $nSalasTotales = (int)$data['nSalas'];
        $campus = $data['campus'];
        
        // NUEVA LÓGICA: Procesar movilidad reducida (viene del frontend)
        $movilidadReducida = isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'No';
        if ($movilidadReducida === 'Si') {
            $pcl_movilidadReducida = 'S';
            $pcl_Cercania = 'S';  // Salas deben estar cerca
        } else {
            $pcl_movilidadReducida = 'N';
            $pcl_Cercania = 'N';  // Sin restricción de cercanía
        }
        
        // Obtener datos de planclases
        $queryPlanclases = "SELECT * FROM a_planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $idplanclases);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$dataPlanclases) {
            throw new Exception('No se encontró la actividad');
        }
        
        // Validar disponibilidad nuevamente antes de guardar
        $salasNoDisponibles = [];
        foreach ($salasComputacion as $idSala) {
            if (!estaDisponibleFinal($conn, $idSala, $dataPlanclases['pcl_Fecha'], 
                                    $dataPlanclases['pcl_Inicio'], $dataPlanclases['pcl_Termino'])) {
                $salasNoDisponibles[] = $idSala;
            }
        }
        
        if (!empty($salasNoDisponibles)) {
            throw new Exception('Las siguientes salas ya no están disponibles: ' . implode(', ', $salasNoDisponibles));
        }
        
        // ACTUALIZADA: Incluir pcl_movilidadReducida y pcl_Cercania en planclases
        $observacionesPlanclases = "";
        if (!empty($observaciones)) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - " . $observaciones;
        }
        
        $stmt = $conn->prepare("UPDATE a_planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_DeseaSala = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isissisi", 
            $nSalasTotales, 
            $campus, 
            $requiereSala,
            $pcl_movilidadReducida,  // 'S' o 'N'
            $pcl_Cercania,           // 1 o 0
            $observacionesPlanclases,
            $observacionesPlanclases,
            $idplanclases
        );
        $stmt->execute();
        
        // Crear comentario automático
        $nombresSalas = array_map('ucfirst', $salasComputacion);
        $comentarioAuto = date('Y-m-d H:i:s') . " - SISTEMA: Reserva automática de sala(s) de computación: " . implode(', ', $nombresSalas);
        
        if (!empty($observaciones)) {
            $comentarioCompleto = $observaciones . "\n\n" . $comentarioAuto;
        } else {
            $comentarioCompleto = $comentarioAuto;
        }
        
        // Usuario para el registro (usar sesión o valor por defecto)
        $usuario = isset($_SESSION['Rut']) ? $_SESSION['Rut'] : '016784781K';
        
        // ACTUALIZADA: Usar valor dinámico de cercanía en asignacion_piloto
        $queryInsert = "INSERT INTO asignacion_piloto (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'C', 3, ?, NOW())";
        
        $stmtInsert = $conn->prepare($queryInsert);
        
        if (!$stmtInsert) {
            throw new Exception('Error preparando query asignacion_piloto: ' . $conn->error);
        }
        
        foreach ($salasComputacion as $idSala) {
            // Obtener capacidad de la sala
            $queryCapacidad = "SELECT sa_Capacidad FROM sala_reserva WHERE idSala = ?";
            $stmtCapacidad = $conn->prepare($queryCapacidad);
            
            if (!$stmtCapacidad) {
                throw new Exception('Error preparando query capacidad: ' . $conn->error);
            }
            
            $stmtCapacidad->bind_param("s", $idSala);
            $stmtCapacidad->execute();
            $resultCapacidad = $stmtCapacidad->get_result();
            $rowCapacidad = $resultCapacidad->fetch_assoc();
            
            if (!$rowCapacidad) {
                throw new Exception('No se encontró la sala de computación: ' . $idSala);
            }
			
			$nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
            
            $capacidadSala = $rowCapacidad['sa_Capacidad'];
            $stmtCapacidad->close();
            
            // ACTUALIZADA: Insertar en asignacion_piloto con cercanía dinámica
            $stmtInsert->bind_param(
                "isiisssssisssssis",
                $idplanclases,
                $idSala,
                $capacidadSala,
                $nAlumnosReal,
                $dataPlanclases['pcl_TipoSesion'],
                $campus,
                $dataPlanclases['pcl_Fecha'],
                $dataPlanclases['pcl_Inicio'],
                $dataPlanclases['pcl_Termino'],
                $dataPlanclases['cursos_idcursos'],
                $dataPlanclases['pcl_AsiCodigo'],
                $dataPlanclases['pcl_Seccion'],
                $dataPlanclases['pcl_AsiNombre'],
                $comentarioCompleto,
                $pcl_Cercania,  // USAR VALOR DINÁMICO EN LUGAR DE 0
                $juntaSeccion,
                $usuario
            );
            
            if (!$stmtInsert->execute()) {
                throw new Exception('Error insertando en asignacion_piloto: ' . $stmtInsert->error);
            }
            
            // Insertar en tabla reserva_2 para bloquear la sala (sin cambios aquí)
            $queryReserva = "INSERT INTO reserva_2 (
                re_idSala, 
                re_FechaReserva, 
                re_HoraReserva, 
                re_HoraTermino, 
                re_idCurso,
                re_labelCurso,
                re_Observacion,
                re_FechaRealizacion,
                re_idRepeticion,
                re_idResponsable,
                re_RegUsu, 
                re_RegFecha
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
            
            $stmtReserva = $conn->prepare($queryReserva);
            
            if (!$stmtReserva) {
                throw new Exception('Error preparando query reserva_2: ' . $conn->error);
            }
            
            // Preparar datos específicos para la reserva
            $labelCurso = $dataPlanclases['pcl_AsiCodigo'] . "-" . $dataPlanclases['pcl_Seccion'];
            $observacionReserva = "RESERVA AUTOMÁTICA COMPUTACIÓN - " . $dataPlanclases['pcl_AsiNombre'] . " - " . $dataPlanclases['pcl_tituloActividad'];
            
            // Obtener RUT de sesión para responsable
            $rutResponsable = isset($_SESSION['Rut']) ? $_SESSION['Rut'] : $usuario;
            
            $stmtReserva->bind_param("ssssssssss", 
                $idSala, 
                $dataPlanclases['pcl_Fecha'],
                $dataPlanclases['pcl_Inicio'],
                $dataPlanclases['pcl_Termino'],
                $dataPlanclases['cursos_idcursos'],
                $labelCurso,
                $observacionReserva,
                $idplanclases,  // re_idRepeticion
                $rutResponsable, // re_idResponsable
                $usuario        // re_RegUsu
            );
            
            if (!$stmtReserva->execute()) {
                throw new Exception('Error insertando en reserva_2: ' . $stmtReserva->error);
            }
            
            $stmtReserva->close();
        }
        
        // Si pidió más salas que las de computación reservadas, crear solicitudes normales
        $salasComputacionReservadas = count($salasComputacion);
        $salasRestantes = $nSalasTotales - $salasComputacionReservadas;
        $nAlumnosReal = obtenerAlumnosReales($data, $dataPlanclases);
        if ($salasRestantes > 0) {
            $comentarioSalasNormales = $observaciones . "\n\n" . 
                                     date('Y-m-d H:i:s') . " - SISTEMA: Solicitud de {$salasRestantes} sala(s) adicional(es) - Ya reservadas {$salasComputacionReservadas} sala(s) de computación";
            
            // ACTUALIZADA: Usar valor dinámico de cercanía para salas adicionales
            for ($i = 0; $i < $salasRestantes; $i++) {
                $stmtInsert->bind_param(
                    "isiisssssissssiis",
                    $idplanclases,
                    '', // Sin sala específica
                    0,  // Sin capacidad específica
                    $nAlumnosReal,
                    $dataPlanclases['pcl_TipoSesion'],
                    $campus,
                    $dataPlanclases['pcl_Fecha'],
                    $dataPlanclases['pcl_Inicio'],
                    $dataPlanclases['pcl_Termino'],
                    $dataPlanclases['cursos_idcursos'],
                    $dataPlanclases['pcl_AsiCodigo'],
                    $dataPlanclases['pcl_Seccion'],
                    $dataPlanclases['pcl_AsiNombre'],
                    $comentarioSalasNormales,
                    $pcl_Cercania,  // USAR VALOR DINÁMICO
                    $juntaSeccion,
                    $usuario
                );
                
                if (!$stmtInsert->execute()) {
                    throw new Exception('Error insertando sala adicional en asignacion_piloto: ' . $stmtInsert->error);
                }
            }
        }
        
        // Cerrar statement de insert
        $stmtInsert->close();
        
        $conn->commit();
        
        $mensajeExito = "Reserva exitosa: " . implode(', ', $nombresSalas);
        if ($salasRestantes > 0) {
            $mensajeExito .= " + {$salasRestantes} sala(s) adicional(es) solicitada(s)";
        }
        
        // Agregar información de movilidad reducida al mensaje
        if ($movilidadReducida === 'Si') {
            $mensajeExito .= " (Configurado para movilidad reducida - salas cercanas)";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensajeExito,
            'salas_computacion_reservadas' => $salasComputacion,
            'salas_normales_solicitadas' => $salasRestantes,
            'movilidad_reducida' => $movilidadReducida,
            'cercania' => $pcl_Cercania
        ]);
        
    } catch (Exception $e) {
        if ($conn && $conn->ping()) {
            $conn->rollback();
        }
        
        // Log del error completo
        error_log("Error en guardar_con_computacion: " . $e->getMessage());
        error_log("Línea: " . $e->getLine());
        error_log("Archivo: " . $e->getFile());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => [
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ]
        ]);
    }
    break;
    
    // fin salas compu
	
	case 'obtener_detalles_inconsistencia':
    try {
        // Log inicial para debugging
        // ✅ PHP 5.6
error_log("🔍 Iniciando obtener_detalles_inconsistencia para ID: " . (isset($data['idplanclases']) ? $data['idplanclases'] : 'NO_DEFINIDO'));
        
        // Verificar parámetros obligatorios
        if (!isset($data['idplanclases']) || empty($data['idplanclases'])) {
            throw new Exception('ID de planclases no proporcionado');
        }
        
        $idplanclases = (int)$data['idplanclases'];
        
        // Obtener datos de planclases
        $queryPlanclases = "SELECT pcl_AsiCodigo, pcl_Seccion, pcl_Fecha, pcl_Inicio, pcl_Termino, pcl_tituloActividad
                           FROM a_planclases 
                           WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        
        if (!$stmtPlanclases) {
            throw new Exception('Error preparando consulta planclases: ' . $conn->error);
        }
        
        $stmtPlanclases->bind_param("i", $idplanclases);
        
        if (!$stmtPlanclases->execute()) {
            throw new Exception('Error ejecutando consulta planclases: ' . $stmtPlanclases->error);
        }
        
        $resultPlanclases = $stmtPlanclases->get_result();
        $datosPlanclases = $resultPlanclases->fetch_assoc();
        $stmtPlanclases->close();
        
        if (!$datosPlanclases) {
            throw new Exception('No se encontró la actividad con ID: ' . $idplanclases);
        }
        
        error_log("✅ Datos planclases obtenidos correctamente");
        
        // Obtener salas en estado 3 (asignadas)
        $querySalasAsignadas = "SELECT idSala, Comentario, timestamp, Usuario 
                               FROM asignacion_piloto 
                               WHERE idplanclases = ? AND idEstado = 3";
        $stmtSalas = $conn->prepare($querySalasAsignadas);
        
        if (!$stmtSalas) {
            throw new Exception('Error preparando consulta salas: ' . $conn->error);
        }
        
        $stmtSalas->bind_param("i", $idplanclases);
        
        if (!$stmtSalas->execute()) {
            throw new Exception('Error ejecutando consulta salas: ' . $stmtSalas->error);
        }
        
        $resultSalas = $stmtSalas->get_result();
        
        $detallesSalas = [];
        
        while ($sala = $resultSalas->fetch_assoc()) {
            $idSala = $sala['idSala'];
            
            error_log("🔍 Verificando sala: " . $idSala);
            
            // ✅ VERIFICACIÓN MANUAL EN 3 PASOS
            $verificacion = ['encontrado' => false, 'metodo' => 'ninguno', 'detalle' => 'No se encontró reserva por ningún método'];
            $infoReserva = null;
            
            try {
                // PASO 1: Buscar por re_idRepeticion
                $queryPaso1 = "SELECT COUNT(*) as existe FROM reserva_2 WHERE re_idRepeticion = ? AND re_idSala = ?";
                $stmtPaso1 = $conn->prepare($queryPaso1);
                
                if ($stmtPaso1) {
                    $stmtPaso1->bind_param("is", $idplanclases, $idSala);
                    $stmtPaso1->execute();
                    $resultPaso1 = $stmtPaso1->get_result();
                    $rowPaso1 = $resultPaso1->fetch_assoc();
                    $stmtPaso1->close();
                    
                    if ($rowPaso1['existe'] > 0) {
                        $verificacion = ['encontrado' => true, 'metodo' => 'paso1', 'detalle' => 'Encontrado por ID repetición'];
                        
                        // Obtener detalles de la reserva
                        $queryReserva = "SELECT re_idSala, re_FechaReserva, re_HoraReserva, re_HoraTermino, 
                                               re_labelCurso, re_Observacion, re_RegFecha 
                                        FROM reserva_2 
                                        WHERE re_idRepeticion = ? AND re_idSala = ? LIMIT 1";
                        $stmtReserva = $conn->prepare($queryReserva);
                        if ($stmtReserva) {
                            $stmtReserva->bind_param("is", $idplanclases, $idSala);
                            $stmtReserva->execute();
                            $resultReserva = $stmtReserva->get_result();
                            if ($resultReserva->num_rows > 0) {
                                $infoReserva = $resultReserva->fetch_assoc();
                            }
                            $stmtReserva->close();
                        }
                    } else {
                        // PASO 2: Buscar por código-sección y horarios
                        $codigo_completo = $datosPlanclases['pcl_AsiCodigo'] . "-" . $datosPlanclases['pcl_Seccion'];
                        $codigoBusqueda = "%{$codigo_completo}%";
                        
                        $queryPaso2 = "SELECT COUNT(*) as existe FROM reserva_2 
                                       WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                                       AND re_FechaReserva = ? AND re_HoraReserva = ? 
                                       AND re_HoraTermino = ? AND re_idSala = ?";
                        $stmtPaso2 = $conn->prepare($queryPaso2);
                        
                        if ($stmtPaso2) {
                            $stmtPaso2->bind_param("ssssss", 
                                $codigoBusqueda, $codigoBusqueda, 
                                $datosPlanclases['pcl_Fecha'], 
                                $datosPlanclases['pcl_Inicio'], 
                                $datosPlanclases['pcl_Termino'], 
                                $idSala
                            );
                            $stmtPaso2->execute();
                            $resultPaso2 = $stmtPaso2->get_result();
                            $rowPaso2 = $resultPaso2->fetch_assoc();
                            $stmtPaso2->close();
                            
                            if ($rowPaso2['existe'] > 0) {
                                $verificacion = ['encontrado' => true, 'metodo' => 'paso2', 'detalle' => 'Encontrado por código-sección y horario'];
                                
                                // Obtener detalles de la reserva
                                $queryReserva = "SELECT re_idSala, re_FechaReserva, re_HoraReserva, re_HoraTermino, 
                                                       re_labelCurso, re_Observacion, re_RegFecha 
                                                FROM reserva_2 
                                                WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                                                AND re_FechaReserva = ? AND re_HoraReserva = ? 
                                                AND re_HoraTermino = ? AND re_idSala = ? LIMIT 1";
                                $stmtReserva = $conn->prepare($queryReserva);
                                if ($stmtReserva) {
                                    $stmtReserva->bind_param("ssssss", 
                                        $codigoBusqueda, $codigoBusqueda, 
                                        $datosPlanclases['pcl_Fecha'], 
                                        $datosPlanclases['pcl_Inicio'], 
                                        $datosPlanclases['pcl_Termino'], 
                                        $idSala
                                    );
                                    $stmtReserva->execute();
                                    $resultReserva = $stmtReserva->get_result();
                                    if ($resultReserva->num_rows > 0) {
                                        $infoReserva = $resultReserva->fetch_assoc();
                                    }
                                    $stmtReserva->close();
                                }
                            }
                        }
                    }
                }
            } catch (Exception $verifError) {
                error_log("❌ Error verificando sala {$idSala}: " . $verifError->getMessage());
                $verificacion = ['encontrado' => false, 'metodo' => 'error', 'detalle' => 'Error durante verificación: ' . $verifError->getMessage()];
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
        
        error_log("✅ Verificación completada para " . count($detallesSalas) . " salas");
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'actividad' => $datosPlanclases,
            'salas' => $detallesSalas
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error en obtener_detalles_inconsistencia: " . $e->getMessage());
        error_log("❌ Stack trace: " . $e->getTraceAsString());
        
        // Siempre devolver JSON, incluso en caso de error
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => [
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
                // ✅ PHP 5.6  
'idplanclases' => isset($data['idplanclases']) ? $data['idplanclases'] : 'NO_DEFINIDO'
            ]
        ]);
    }
    break;
        default:
                throw new Exception('Acción no reconocida: ' . $data['action']);
        }
        
    } catch (Exception $e) {
        error_log("❌ Error general en procesamiento: " . $e->getMessage());
        
        // Asegurar que se devuelva JSON incluso en errores generales
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}



// Resto del código HTML permanece igual...
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
    p.pcl_alumnos,
    p.pcl_nSalas,
    p.pcl_DeseaSala,
    p.pcl_observaciones,
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
FROM a_planclases p
LEFT JOIN pcl_TipoSesion t ON p.pcl_TipoSesion = t.tipo_sesion 
    AND p.pcl_SubTipoSesion = t.Sub_tipo_sesion
WHERE p.cursos_idcursos = ? 
AND p.pcl_tituloActividad != ''
AND (t.tipo_activo = 1 OR p.pcl_DeseaSala = 0)
AND t.pedir_sala = 1
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
        $requiereSala = ($row['pedir_sala'] == 1);
        $esClase = ($row['pcl_TipoSesion'] === 'Clase');
        $fecha = new DateTime($row['pcl_Fecha']);
        
        // ✅ OBTENER DATOS PARA VERIFICACIÓN
        $queryDatosPlanclases = "SELECT pcl_AsiCodigo, pcl_Seccion, pcl_Fecha, pcl_Inicio, pcl_Termino 
                                FROM a_planclases 
                                WHERE idplanclases = ?";
        $stmtDatos = $conn->prepare($queryDatosPlanclases);
        $stmtDatos->bind_param("i", $row['idplanclases']);
        $stmtDatos->execute();
        $resultDatos = $stmtDatos->get_result();
        $datosPlanclases = $resultDatos->fetch_assoc();
        $stmtDatos->close();

        // ✅ OBTENER ESTADOS DE ASIGNACIONES
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
        $stmtEstados->close();

        // Determinar cantidades por estado
        $estado0 = isset($estados[0]) ? $estados[0] : 0;
        $estado1 = isset($estados[1]) ? $estados[1] : 0;
        $estado3 = isset($estados[3]) ? $estados[3] : 0;
        $estado4 = isset($estados[4]) ? $estados[4] : 0;

        // Total de asignaciones
        $totalAsignaciones = array_sum($estados);
        $totalActivas = $totalAsignaciones - $estado4;

        // ✅ VERIFICACIÓN EN 3 PASOS
        $salasConReserva = [];
        $salasInconsistentes = [];
        $detallesVerificacion = [];

        if (!empty($salasAsignadas) && $datosPlanclases) {
            foreach ($salasAsignadas as $sala) {
                $verificacion = verificarReservaCompleta(
                    $conn, 
                    $row['idplanclases'], 
                    $datosPlanclases['pcl_AsiCodigo'], 
                    $datosPlanclases['pcl_Seccion'], 
                    $datosPlanclases['pcl_Fecha'], 
                    $datosPlanclases['pcl_Inicio'], 
                    $datosPlanclases['pcl_Termino'], 
                    $sala
                );
                
                if ($verificacion['encontrado']) {
                    $salasConReserva[] = $sala;
                    $detallesVerificacion[$sala] = [
                        'estado' => 'encontrada',
                        'metodo' => $verificacion['metodo'],
                        'detalle' => $verificacion['detalle']
                    ];
                } else {
                    $salasInconsistentes[] = $sala;
                    $detallesVerificacion[$sala] = [
                        'estado' => 'inconsistente',
                        'metodo' => $verificacion['metodo'],
                        'detalle' => $verificacion['detalle']
                    ];
                }
            }
        }

        // ✅ LÓGICA DE ESTADOS ACTUALIZADA
        $countSalasConReserva = count($salasConReserva);
        $tieneAsignaciones = !empty($salasConReserva);
        $tieneSolicitudes = $row['salas_solicitadas'] > 0;
        $todasLiberadas = ($totalAsignaciones > 0 && $estado4 == $totalAsignaciones);
        $todasAsignadas = ($totalActivas > 0 && $countSalasConReserva == $row['pcl_nSalas'] && $countSalasConReserva > 0);
        $parcialmenteAsignadas = ($countSalasConReserva > 0 && ($estado0 > 0 || $estado1 > 0 || $countSalasConReserva < $row['pcl_nSalas']));
        $enModificacion = ($estado1 > 0 && $countSalasConReserva == 0);
        $solicitadas = ($estado0 == $totalActivas && $totalActivas > 0);
        $pendiente = ($totalActivas == 0);
        $tieneInconsistencias = !empty($salasInconsistentes);
        $todasConfirmadas = $todasAsignadas;
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
                <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($tituloCompleto, ENT_QUOTES, 'utf-8'); ?>">
                    <?php echo htmlspecialchars($tituloCorto, ENT_QUOTES, 'utf-8'); ?>
                </span>
            <?php else: ?>
                <?php echo htmlspecialchars($tituloCorto, ENT_QUOTES, 'utf-8'); ?>
            <?php endif; ?>
        </td>
        <td>
            <?php echo $row['pcl_TipoSesion']; ?>
            <?php if($row['pcl_SubTipoSesion']): ?>
                <br><small class="text-muted"><?php echo $row['pcl_SubTipoSesion']; ?></small>
            <?php endif; ?>
        </td>
        <td><?php echo $row['pcl_campus']; ?></td>
        <td><?php echo $row['pcl_nSalas']; ?></td>
        
        <!-- ✅ COLUMNA SALA CORREGIDA -->
        <td>
            <?php if(!empty($salasConReserva)): ?>
                <ul class="list-unstyled m-0">
                    <?php foreach($salasConReserva as $sala): ?>
                        <?php 
                        $detalle = $detallesVerificacion[$sala];
                        $iconoMetodo = '';
                        $colorBadge = 'bg-success';
                        $tooltip = 'Reserva confirmada';
                        
                        switch($detalle['metodo']) {
                            case 'paso1':
                                $iconoMetodo = '🎯'; // Encontrada directamente
                                $tooltip = 'Reserva encontrada por ID de repetición';
                                break;
                            case 'paso2':
                                $iconoMetodo = '🔍'; // Encontrada por búsqueda
                                $colorBadge = 'bg-success';
                                $tooltip = 'Reserva encontrada por código-sección y horario';
                                break;
                        }
                        ?>
                        <li>
                            <span class="badge <?php echo $colorBadge; ?>" 
                                  data-bs-toggle="tooltip" 
                                  title="<?php echo $tooltip; ?>">
                                <?php echo $iconoMetodo; ?> <?php echo $sala; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if(!empty($salasInconsistentes)): ?>
                <ul class="list-unstyled m-0 mt-1">
                    <?php foreach($salasInconsistentes as $sala): ?>
                        <li>
                            <span class="badge bg-danger text-white" 
                                  data-bs-toggle="tooltip" 
                                  title="❌ <?php echo $detallesVerificacion[$sala]['detalle']; ?>">
                                ❌ <?php echo $sala; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if(empty($salasConReserva) && empty($salasInconsistentes)): ?>
                <span class="badge bg-secondary">Sin sala</span>
            <?php endif; ?>
        </td>

        <!-- ✅ COLUMNA ESTADO CORREGIDA -->
        <td>
        <?php 
        if($row['pcl_DeseaSala'] == 0): ?>
            <span class="badge bg-dark">Actividad no requiere sala</span>
            
        <?php elseif($tieneInconsistencias): ?>
            <span class="badge bg-danger" 
                  data-bs-toggle="tooltip" 
                  title="Se detectaron salas asignadas sin reserva confirmada">
                ❌ Inconsistencia detectada
            </span>
            
        <?php elseif($todasLiberadas): ?>
            <span class="badge bg-dark">Liberada</span>
            
        <?php elseif($todasAsignadas): ?>
            <span class="badge bg-success">
                ✅ Asignada 
                <?php if(count($salasConReserva) > 0): ?>
                    <small>(<?php echo count($salasConReserva); ?>/<?php echo $row['pcl_nSalas']; ?>)</small>
                <?php endif; ?>
            </span>
            
        <?php elseif($parcialmenteAsignadas): ?>
            <span class="badge bg-warning">
                ⚠️ Parcialmente asignada 
                <small>(<?php echo count($salasConReserva); ?>/<?php echo $row['pcl_nSalas']; ?>)</small>
            </span>
            
        <?php elseif($enModificacion): ?>
            <span class="badge bg-primary">En modificación</span>
            
        <?php elseif($solicitadas): ?>
            <span class="badge bg-info">Solicitada</span>
            
        <?php elseif($row['pedir_sala'] == 0): ?>
            <span class="badge bg-dark">Actividad no requiere sala</span>
            
        <?php else: ?>
            <span class="badge bg-secondary">Pendiente</span>
            
        <?php endif; ?>
        </td>

        <!-- ✅ COLUMNA ACCIONES CORREGIDA -->
        <td>
        <?php
        echo '<div class="btn-group-vertical btn-group-sm">';

        if ($row['pcl_DeseaSala'] == 0) {
            echo '<span class="badge bg-info"><i class="bi bi-x-circle"></i> Sin Acciones</span>';
            
        } elseif ($tieneInconsistencias) {
            echo '<button type="button" class="btn btn-danger btn-sm mb-1" 
                          onclick="mostrarDetallesInconsistencia('.$row['idplanclases'].')" 
                          title="Ver detalles de inconsistencias">
                    <i class="bi bi-exclamation-triangle"></i> Ver Inconsistencias
                  </button>';
            echo '<button type="button" class="btn btn-warning btn-sm" 
                          onclick="modificarSala('.$row['idplanclases'].')" 
                          title="Modificar solicitud">
                    <i class="bi bi-pencil"></i> Modificar
                  </button>';
                  
        } elseif ($row['pedir_sala'] == 0) {
            echo '<span class="text-muted">Actividad sin sala</span>';
            
        } else {
            if ($esClase) {
                echo '<button type="button" class="btn btn-warning btn-sm mb-1" onclick="modificarSala('.$row['idplanclases'].')">
                        <i class="bi bi-pencil"></i> Modificar
                      </button>';
            } else {
                if(!$tieneAsignaciones && !$tieneSolicitudes) {
                    echo '<button type="button" class="btn btn-primary btn-sm mb-1" onclick="solicitarSala('.$row['idplanclases'].')">
                            <i class="bi bi-plus-circle"></i> Solicitar
                          </button>';
                } else {
                    echo '<button type="button" class="btn btn-warning btn-sm mb-1" onclick="modificarSala('.$row['idplanclases'].')">
                            <i class="bi bi-pencil"></i> Modificar
                          </button>';
                }
            }
            
            if($countSalasConReserva > 0) {
                echo '<button type="button" class="btn btn-danger btn-sm" onclick="mostrarModalLiberarSalas('.$row['idplanclases'].')">
                        <i class="bi bi-x-circle"></i> Liberar
                      </button>';
            }
        }
        echo '</div>';
        ?>
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
                            <option value="Occidente">Occidente </option>
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


<div id="juntarSeccionesDiv" class="mb-3 alert alert-info">
    <i class="fa fa-info-circle"></i> <strong>Múltiples secciones detectadas</strong>
    <br>
    <label class="form-check-label">
        <input type="checkbox" id="juntarSecciones" name="juntarSecciones" class="form-check-input" />
		&nbsp; &nbsp; Quiero juntar todas las secciones del curso
    </label>
	<br>
	<small>Al activar esta opción se sumarán los alumnos de todas las secciones del curso.</small>
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
						<label for="alumnosPorSala" class="form-label">N° de alumnos por sala</label>
						<div class="input-group">
							<input type="number" class="form-control" id="alumnosPorSala" name="alumnosPorSala" 
								   placeholder="Ingrese cantidad" min="1" onchange="actualizarSalasDisponibles()">
							<button class="btn btn-outline-success" type="button" id="btnSalasDisponibles" 
									onclick="mostrarSalasDisponibles()" style="display: none;">
								<i class="bi bi-building"></i> 
								<span id="numeroSalasDisponibles">0</span> disponibles
							</button>
						</div>
						<div class="form-text">
							<small class="text-muted">Este valor se calcula automáticamente según el número total de alumnos y salas requeridas.</small>
						</div>
					</div>
					
					<div id="seccion-computacion" style="display: none;">
    <hr>
    <div class="mb-3">
        <h6 class="text-primary">
            <i class="bi bi-pc-display me-2"></i>
            Salas de Computación Disponibles
        </h6>
        
        <div class="alert alert-info alert-sm">
            <i class="bi bi-info-circle me-1"></i>
            <small>
                Las salas de computación son recursos limitados. Solo se asignan si toda la sección puede usar el recurso de manera efectiva.
            </small>
        </div>
        
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="deseaComputacion">
            <label class="form-check-label fw-bold" for="deseaComputacion">
                ¿Desea reservar sala(s) de computación para esta actividad?
            </label>
        </div>
        
        <div id="opciones-computacion" style="display: none;">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Opciones Disponibles
                    </h6>
                    <div id="lista-opciones-computacion">
                        <!-- Se llenará dinámicamente con JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <div id="mensaje-sin-opciones" style="display: none;">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span id="texto-mensaje-sin-opciones"></span>
            </div>
        </div>
    </div>
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

<!-- Modal para mostrar salas disponibles  -->
<div class="modal fade" id="modalSalasDisponibles" tabindex="-1" aria-labelledby="modalSalasDisponiblesLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modalSalasDisponiblesLabel">
                    <i class="bi bi-building"></i> Salas Disponibles
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="info-consulta-salas" class="mb-2">
                    <small class="text-muted">
                        <strong>Criterios:</strong> <span id="criterios-busqueda"></span>
                    </small>
                </div>
                
                <!-- BOTÓN CERRAR SUPERIOR -->
                <div class="d-grid mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Cerrar listado
                    </button>
                </div>
                
                <div id="lista-salas-disponibles">
                    <!-- Se carga dinámicamente -->
                </div>
                
                <div id="loading-salas" class="text-center" style="display: none;">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <small class="text-muted ms-2">Consultando salas...</small>
                </div>
                
                <div id="error-salas" class="alert alert-warning" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span id="mensaje-error-salas"></span>
                </div>
                
                <!-- BOTÓN CERRAR INFERIOR -->
                <div class="d-grid mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Cerrar listado
                    </button>
                </div>
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

<div class="modal fade" id="modalDetallesInconsistencia" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                    Análisis Detallado de Inconsistencias
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Información de la actividad -->
                <div class="alert alert-info mb-3">
                    <h6><i class="bi bi-info-circle"></i> Información de la Actividad</h6>
                    <div id="info-actividad">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>
                
                <!-- Explicación del problema -->
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
                    <h6><i class="bi bi-search"></i> Métodos de Verificación Utilizados</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-primary me-2">🎯</span>
                                <div>
                                    <strong>Paso 1:</strong> Búsqueda directa por ID de repetición
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-info me-2">🔍</span>
                                <div>
                                    <strong>Paso 2:</strong> Búsqueda por código-sección y horario
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-danger me-2">❌</span>
                                <div>
                                    <strong>Paso 3:</strong> No encontrado (inconsistencia)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recomendaciones -->
                <div class="alert alert-warning mt-3">
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


</div>
