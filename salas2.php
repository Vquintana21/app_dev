<?php
// regulares
ob_start();
include_once("conexion.php");
require_once 'funciones_secciones.php';
include_once("log_salas.php"); 
$error_output = ob_get_clean();

session_start();
$ruti = $_SESSION['sesion_idLogin'];
$rut = str_pad($ruti, 10, "0", STR_PAD_LEFT);

// Si hay errores de inclusión, los registramos pero no los mostramos
if (!empty($error_output)) {
    error_log("Errores antes de JSON: " . $error_output);
}

// Asegurarnos de que se envíe el header de contenido correcto
header('Content-Type: application/json');

function distribuirAlumnosEntreSalas($data, $dataPlanclases) {
    // Obtener el total de alumnos usando la lógica existente
    $alumnosTotales = obtenerAlumnosReales($data, $dataPlanclases);
    
    // Obtener número de salas solicitadas
    $nSalas = isset($data['nSalas']) ? (int)$data['nSalas'] : 1;
    
    // Validación de seguridad
    if ($nSalas == 0) {
        $nSalas = 1;
    }
    
    // Calcular alumnos por sala (redondear hacia arriba para no dejar alumnos sin sala)
    $alumnosPorSala = (int)ceil($alumnosTotales / $nSalas);
    
    // Log para debugging
    error_log("DISTRIBUCION ALUMNOS: Total=$alumnosTotales, Salas=$nSalas, Por sala=$alumnosPorSala");
    
    return $alumnosPorSala;
}

function estaDisponibleFinal($reserva2, $idSala, $fecha, $horaInicio, $horaFin) {
    $queryReserva = "SELECT * FROM reserva 
                     WHERE re_idSala = ?
                     AND re_FechaReserva = ?
                     AND ((re_HoraReserva <= ? AND re_HoraTermino > ?) 
                          OR (re_HoraReserva < ? AND re_HoraTermino >= ?) 
                          OR (? <= re_HoraReserva AND ? >= re_HoraTermino))";
    
    $stmtReserva = $reserva2->prepare($queryReserva);
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
    global $conn, $conexion3;
    
    try {
        // PRIORIDAD 1: Si se está usando la función central
        if (isset($dataPlanclases['pcl_AulaDescripcion']) && $dataPlanclases['pcl_AulaDescripcion'] === 'S') {
            $alumnosCalculados = calcularAlumnosReales($dataPlanclases['idplanclases'], $conn, 'regular');
            if ($alumnosCalculados > 0) {
                error_log("obtenerAlumnosReales - Función central (SECCIONES JUNTAS): $alumnosCalculados");
                return $alumnosCalculados;
            }
        }
        
        // PRIORIDAD 2: Si se marcó "juntar secciones" 
        if (isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1') {
            $alumnosModal = isset($data['alumnosTotales']) ? (int)$data['alumnosTotales'] : 0;
            if ($alumnosModal > 0) {
                error_log("obtenerAlumnosReales - Modal juntar: $alumnosModal");
                return $alumnosModal;
            }
        }
        
        // ✅ NUEVA PRIORIDAD 3: Usar valor del modal si está disponible
        if (isset($data['alumnosTotales']) && (int)$data['alumnosTotales'] > 0) {
            $alumnosModal = (int)$data['alumnosTotales'];
            error_log("obtenerAlumnosReales - Modal directo: $alumnosModal");
            return $alumnosModal;
        }
        
        // PRIORIDAD 4: Usar cupo individual por defecto
        $alumnosIndividual = isset($dataPlanclases['pcl_alumnos']) ? (int)$dataPlanclases['pcl_alumnos'] : 0;
        error_log("obtenerAlumnosReales - Individual: $alumnosIndividual");
        return $alumnosIndividual;
        
    } catch (Exception $e) {
        error_log("Error en obtenerAlumnosReales: " . $e->getMessage());
        return isset($dataPlanclases['pcl_alumnos']) ? (int)$dataPlanclases['pcl_alumnos'] : 0;
    }
}

function verificarReservaCompleta($reserva2, $idplanclases, $codigo_curso, $seccion, $fecha, $hora_inicio, $hora_termino, $idSala = null) {
    $codigo_completo = $codigo_curso . "-" . $seccion;
    
    // PASO 1: Buscar por re_idRepeticion (más directo)
    $queryPaso1 = "SELECT COUNT(*) as existe, 'paso1' as metodo 
                   FROM reserva 
                   WHERE re_idRepeticion = ?";
    
    if ($idSala) {
        $queryPaso1 .= " AND re_idSala = ?";
        $stmtPaso1 = $reserva2->prepare($queryPaso1);
        $stmtPaso1->bind_param("is", $idplanclases, $idSala);
    } else {
        $stmtPaso1 = $reserva2->prepare($queryPaso1);
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
                   FROM reserva
                   WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                   AND re_FechaReserva = ? 
                   AND re_HoraReserva = ? 
                   AND re_HoraTermino = ?";
    
    if ($idSala) {
        $queryPaso2 .= " AND re_idSala = ?";
    }
    
    $stmtPaso2 = $reserva2->prepare($queryPaso2);
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
		// valor de juntarSecciones
        $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
		$juntaSeccionPlanclase = !empty($data['juntarSecciones']) ? 'S' : 'N'; // Para planclases
		// boleano para verificaciones
		$juntarSecciones = isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1';
        $actualizacionOk = actualizarPclAulaDescripcion($data['idplanclases'], $juntarSecciones, $conn, 'clinico');
        
        if (!$actualizacionOk) {
            throw new Exception('Error actualizando pcl_AulaDescripcion en clínico');
        }
        
        // Procesar movilidad reducida y cercanía
        $movilidadReducida = isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'No';
        if ($movilidadReducida === 'Si') {
            $pcl_movilidadReducida = 'S';
            $pcl_Cercania = 'S';
        } else {
            $pcl_movilidadReducida = 'N';
            $pcl_Cercania = 'N';
        }
		
		$alumnosTotales = obtenerAlumnosReales($data, $dataPlanclases); // Para planclases
		$nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases); // Para asignacion
		  error_log("🎮 nAlumnosReal calculado: " . $nAlumnosReal);
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
        }
        
        // ACTUALIZAR planclases
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_DeseaSala = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
								  pcl_alumnos = ?,
								  pcl_AulaDescripcion = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isississsi", 
            $data['nSalas'], 
            $data['campus'], 
            $requiereSala,
            $pcl_movilidadReducida,
            $pcl_Cercania,
			$alumnosTotales,
			$juntaSeccionPlanclase,
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error actualizando planclases: " . $stmt->error);
        }
		
		  error_log("se actualizo planclase con nalumnos saja junta.");
        
        if ($requiereSala == 0) {
            // Si NO requiere sala, liberar asignaciones
            $stmt = $conn->prepare("UPDATE asignacion 
                                   SET idEstado = 4, 
                                       Comentario = CONCAT(IFNULL(Comentario, ''), '\n\n', ?, ' - NO REQUIERE SALA') 
                                   WHERE idplanclases = ? AND idEstado != 4");
            $stmt->bind_param("si", date('Y-m-d H:i:s'), $data['idplanclases']);
            $stmt->execute();
            
            $conn->commit();
			
			 registrarLogSala2($conn, $data['idplanclases'], 'no_requiere');
			
            echo json_encode(['success' => true, 'message' => 'Actividad actualizada. No requiere sala.']);
            break;
        }
        
        // Obtener datos necesarios de planclases
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        if (!$dataPlanclases) {
            throw new Exception("No se encontraron datos de planclases para ID: " . $data['idplanclases']);
        }
        
        // Preparar observaciones para asignacion
        $observacionesAsignacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesAsignacion = date('Y-m-d H:i:s') . " - " . $data['observaciones'];
        }
        
        $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
        if ($usuario === null || $usuario === '') {
            $usuario = 'sistema';
        }
        
		
		
        error_log("🔍 Usuario configurado: '" . $usuario . "'");
        
        $queryInsert = "INSERT INTO asignacion (
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
            $result = $stmtInsert->bind_param(     // ✅ 15 caracteres
                "iisssssisisssis",                   // ✅ 15 caracteres
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
                $rut                             // 15. s (ya no NULL)
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
		
		registrarLogSala2($conn, $data['idplanclases'], 'solicitar');
        
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
        
        // ✅ LOG 1: INICIO - qué llega del frontend
        error_log("🚀 =========================");
        error_log("🚀 INICIO MODIFICAR - ID: " . $data['idplanclases']);
        error_log("📨 DATOS FRONTEND:");
        error_log("   campus: '" . (isset($data['campus']) ? $data['campus'] : 'NO_EXISTE') . "'");
        error_log("   movilidadReducida: '" . (isset($data['movilidadReducida']) ? $data['movilidadReducida'] : 'NO_EXISTE') . "'");
        error_log("   juntarSecciones: '" . (isset($data['juntarSecciones']) ? $data['juntarSecciones'] : 'NO_EXISTE') . "'");
        error_log("   nSalas: '" . (isset($data['nSalas']) ? $data['nSalas'] : 'NO_EXISTE') . "'");
        error_log("   requiereSala: '" . (isset($data['requiereSala']) ? $data['requiereSala'] : 'NO_EXISTE') . "'");
        
        // Verificar si requiere sala
        $requiereSala = isset($data['requiereSala']) ? (int)$data['requiereSala'] : 1;
        $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
        $juntaSeccionPlanclase = !empty($data['juntarSecciones']) ? 'S' : 'N'; // Para planclases
        
        $juntarSecciones = isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1';
        
        // ✅ CORREGIR: Cambiar 'clinico' por 'regular'
        $actualizacionOk = actualizarPclAulaDescripcion($data['idplanclases'], $juntarSecciones, $conn, 'regular');
        
        if (!$actualizacionOk) {
            throw new Exception('Error actualizando pcl_AulaDescripcion en regular');
        }
        
        // ✅ NUEVO: Forzar commit para confirmar pcl_AulaDescripcion
        error_log("💾 FORZANDO COMMIT después de actualizarPclAulaDescripcion");
        $conn->commit();
        $conn->begin_transaction();
        
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
        
        // ✅ LOG 2: DESPUÉS DE CALCULAR - valores procesados
        error_log("🧮 VALORES CALCULADOS:");
        error_log("   movilidadReducida: '" . $movilidadReducida . "'");
        error_log("   pcl_movilidadReducida: '" . $pcl_movilidadReducida . "'");
        error_log("   pcl_Cercania: '" . $pcl_Cercania . "'");
        error_log("   juntaSeccion: '" . $juntaSeccion . "'");
        error_log("   juntaSeccionPlanclase: '" . $juntaSeccionPlanclase . "'");
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
        }
        
        // ✅ NUEVA SECCIÓN: Obtener datos ACTUALIZADOS de planclases
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        $stmtPlanclases->close();
        
        // ✅ LOG 3: DATOS DE PLANCLASES
        error_log("📋 DATOS PLANCLASES:");
        error_log("   pcl_AulaDescripcion: '" . (isset($dataPlanclases['pcl_AulaDescripcion']) ? $dataPlanclases['pcl_AulaDescripcion'] : 'NO_EXISTE') . "'");
        error_log("   pcl_alumnos: '" . (isset($dataPlanclases['pcl_alumnos']) ? $dataPlanclases['pcl_alumnos'] : 'NO_EXISTE') . "'");
        error_log("   pcl_AsiNombre: '" . (isset($dataPlanclases['pcl_AsiNombre']) ? $dataPlanclases['pcl_AsiNombre'] : 'NO_EXISTE') . "'");
        error_log("   pcl_Inicio: '" . (isset($dataPlanclases['pcl_Inicio']) ? $dataPlanclases['pcl_Inicio'] : 'NO_EXISTE') . "'");
        error_log("   pcl_Termino: '" . (isset($dataPlanclases['pcl_Termino']) ? $dataPlanclases['pcl_Termino'] : 'NO_EXISTE') . "'");
        error_log("   pcl_campus: '" . (isset($dataPlanclases['pcl_campus']) ? $dataPlanclases['pcl_campus'] : 'NO_EXISTE') . "'");
        error_log("   pcl_Cercania: '" . (isset($dataPlanclases['pcl_Cercania']) ? $dataPlanclases['pcl_Cercania'] : 'NO_EXISTE') . "'");
        
        // ✅ CORREGIR: Agregar parámetro tipoCurso
        $alumnosTotales = obtenerAlumnosReales($data, $dataPlanclases, 'regular');
        
        // Log para debugging
        error_log("DEBUG MODIFICAR - alumnosTotales calculado: " . $alumnosTotales);
        
        // ✅ LOG 4: ANTES UPDATE PLANCLASES
        error_log("📝 ANTES UPDATE planclases:");
        error_log("   data[nSalas]: '" . $data['nSalas'] . "'");
        error_log("   data[campus]: '" . $data['campus'] . "'");
        error_log("   requiereSala: '" . $requiereSala . "'");
        error_log("   pcl_movilidadReducida: '" . $pcl_movilidadReducida . "'");
        error_log("   pcl_Cercania: '" . $pcl_Cercania . "'");
        error_log("   alumnosTotales: '" . $alumnosTotales . "'");
        error_log("   juntaSeccionPlanclase: '" . $juntaSeccionPlanclase . "'");
        
        // ACTUALIZADA: Incluir pcl_movilidadReducida y pcl_Cercania
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_DeseaSala = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_alumnos = ?,
                                  pcl_AulaDescripcion = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        
        // ✅ CORREGIR: String de tipos correcto (10 caracteres para 10 parámetros)
        $stmt->bind_param("issssisssi", 
            $data['nSalas'], 
            $data['campus'], 
            $requiereSala,
            $pcl_movilidadReducida,
            $pcl_Cercania,
            $alumnosTotales,           // ✅ NUEVA LÍNEA
            $juntaSeccionPlanclase,
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        $stmt->execute();
        
        error_log("✅ UPDATE planclases ejecutado");
        
        if ($requiereSala == 0) {
            // Si NO requiere sala, liberar todas las asignaciones
            $stmt = $conn->prepare("UPDATE asignacion 
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
                               FROM asignacion 
                               WHERE idplanclases = ?");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentState = $result->fetch_assoc();
        
        error_log("📊 ESTADO ASIGNACIONES: count=" . $currentState['count'] . ", maxEstado=" . $currentState['maxEstado']);
		
		// ✅ VERIFICAR SI TODAS LAS ASIGNACIONES ESTÁN LIBERADAS
$queryEstadosActivos = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN idEstado != 4 THEN 1 END) as activas,
    COUNT(CASE WHEN idEstado = 4 THEN 1 END) as liberadas
    FROM asignacion 
    WHERE idplanclases = ?";
$stmtActivos = $conn->prepare($queryEstadosActivos);
$stmtActivos->bind_param("i", $data['idplanclases']);
$stmtActivos->execute();
$resultActivos = $stmtActivos->get_result();
$estadosInfo = $resultActivos->fetch_assoc();
$stmtActivos->close();

error_log("📊 ANÁLISIS ESTADOS: Total={$estadosInfo['total']}, Activas={$estadosInfo['activas']}, Liberadas={$estadosInfo['liberadas']}");

// ✅ SI TODAS ESTÁN LIBERADAS: LIMPIAR Y CREAR NUEVAS
if ($estadosInfo['activas'] == 0 && $estadosInfo['liberadas'] > 0) {
    error_log("🧹 TODAS LAS ASIGNACIONES ESTÁN LIBERADAS - Limpiando y creando nuevas");
    
    // PASO 1: 🗑️ BORRAR todas las asignaciones liberadas
    $queryLimpiar = "DELETE FROM asignacion WHERE idplanclases = ? AND idEstado = 4";
    $stmtLimpiar = $conn->prepare($queryLimpiar);
    $stmtLimpiar->bind_param("i", $data['idplanclases']);
    $stmtLimpiar->execute();
    $eliminadas = $stmtLimpiar->affected_rows;
    $stmtLimpiar->close();
    
    error_log("🗑️ LIMPIEZA: Eliminadas {$eliminadas} asignaciones liberadas");
    
    // PASO 2: ✨ Obtener datos necesarios (usa tu código existente)
    $alumnosPorSala = distribuirAlumnosEntreSalas($data, $dataPlanclases);
    
    $idCurso = $dataPlanclases['cursos_idcursos'];
    $queryCurso = "SELECT CodigoCurso, Seccion FROM spre_cursos WHERE idCurso = ?";
    $stmtCurso = $conexion3->prepare($queryCurso);
    $stmtCurso->bind_param("i", $idCurso);
    $stmtCurso->execute();
    $resultCurso = $stmtCurso->get_result();
    $dataCurso = $resultCurso->fetch_assoc();
    $stmtCurso->close();
    
    $queryNombre = "SELECT NombreCurso FROM spre_ramos WHERE CodigoCurso = ?";
    $stmtNombre = $conexion3->prepare($queryNombre);
    $stmtNombre->bind_param("s", $dataCurso['CodigoCurso']);
    $stmtNombre->execute();
    $resultNombre = $stmtNombre->get_result();
    $dataNombre = $resultNombre->fetch_assoc();
    $stmtNombre->close();
    
    // PASO 3: ✨ Crear nuevas asignaciones limpias
    $queryInsert = "INSERT INTO asignacion (
        idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
        fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
        NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
    ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 0, ?, NOW())";
    
    $stmtInsert = $conn->prepare($queryInsert);
    $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
    
    $comentarioInicial = date('Y-m-d H:i:s') . " - NUEVA SOLICITUD (DESPUÉS DE LIMPIAR LIBERADAS)";
    if (!empty($data['observaciones'])) {
        $comentarioInicial .= "\n" . $data['observaciones'];
    }
    
    // Crear las asignaciones
    for ($i = 0; $i < (int)$data['nSalas']; $i++) {
        
 $stmtInsert->bind_param("iisssssisisssis",
        $data['idplanclases'],           // 1. i - idplanclases (bigint)
        $alumnosPorSala,                 // 2. i - nAlumnos (int)
        $dataPlanclases['pcl_TipoSesion'], // 3. s - tipoSesion (varchar)
        $data['campus'],                 // 4. s - campus (varchar)
        $dataPlanclases['pcl_Fecha'],    // 5. s - fecha (date)
        $dataPlanclases['pcl_Inicio'],   // 6. s - hora_inicio (time)
        $dataPlanclases['pcl_Termino'],  // 7. s - hora_termino (time)
        $idCurso,                        // 8. i - idCurso (int)
        $dataCurso['CodigoCurso'],       // 9. s - CodigoCurso (varchar)
        $dataCurso['Seccion'],           // 10. i - Seccion (int) ← CORREGIDO DE 's' A 'i'
        $dataNombre['NombreCurso'],      // 11. s - NombreCurso (varchar)
        $comentarioInicial,              // 12. s - Comentario (text)
        $pcl_Cercania,                   // 13. s - cercania (varchar)
        $juntaSeccion,                   // 14. i - junta_seccion (int)
        $rut                         // 15. s - Usuario (varchar)
    );
    $stmtInsert->execute();
    }
    $stmtInsert->close();
    
    error_log("✨ NUEVAS ASIGNACIONES: Creadas {$data['nSalas']} asignaciones limpias en estado 0");
    
    // PASO 4: 📊 Actualizar pcl_nSalas 
    $stmtUpdateSalas = $conn->prepare("UPDATE planclases SET pcl_nSalas = ? WHERE idplanclases = ?");
    $stmtUpdateSalas->bind_param("ii", $data['nSalas'], $data['idplanclases']);
    $stmtUpdateSalas->execute();
    $stmtUpdateSalas->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Requerimiento procesado correctamente',
        'detalles' => [
            'eliminadas' => $eliminadas,
            'nuevas_asignaciones' => (int)$data['nSalas'],
            'tipo_operacion' => 'limpiar_y_crear'
        ]
    ]);
    break; // ✅ SALIR DEL CASE AQUÍ
}

// ✅ Si hay asignaciones activas, continuar con flujo normal...
        
        // Solo modificar si están en estado 0 (solicitado)
        if ($currentState['maxEstado'] == 0) {
            // Obtener observaciones existentes de asignacion
            $queryObs = "SELECT Comentario FROM asignacion 
                         WHERE idplanclases = ? LIMIT 1";
            $stmtObs = $conn->prepare($queryObs);
            $stmtObs->bind_param("i", $data['idplanclases']);
            $stmtObs->execute();
            $resultObs = $stmtObs->get_result();
            $obsAnterior = "";
            
            if ($resultObs->num_rows > 0) {
                $obsAnterior = $resultObs->fetch_assoc()['Comentario'];
            }
            
            // Concatenar nueva observación para asignacion
            $nuevaObservacionAsignacion = $obsAnterior;
            if (isset($data['observaciones']) && !empty($data['observaciones'])) {
                if (!empty($obsAnterior)) {
                    $nuevaObservacionAsignacion .= "\n\n" . date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
                } else {
                    $nuevaObservacionAsignacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN: " . $data['observaciones'];
                }
            }
            
            $nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases); // Para asignacion
            
            // ✅ LOG 5: ANTES UPDATE asignacion
            error_log("📝 ANTES UPDATE asignacion:");
            error_log("   nuevaObservacionAsignacion: '" . $nuevaObservacionAsignacion . "'");
            error_log("   nAlumnosReal: '" . $nAlumnosReal . "'");
            error_log("   data[campus]: '" . $data['campus'] . "'");
            error_log("   pcl_Cercania: '" . $pcl_Cercania . "'");
            error_log("   juntaSeccion: '" . $juntaSeccion . "'");
            error_log("   data[idplanclases]: '" . $data['idplanclases'] . "'");
            
            // ACTUALIZADA: Incluir cercanía en la actualización de asignacion
            $stmt = $conn->prepare("UPDATE asignacion 
                                  SET Comentario = ?,
                                      nAlumnos = ?,
                                      campus = ?,
                                      cercania = ?,
                                      junta_seccion = ?
                                  WHERE idplanclases = ? AND idEstado = 0");
            
            // ✅ CORREGIR: String de tipos correcto
            $stmt->bind_param("sissii", 
                $nuevaObservacionAsignacion, 
                $nAlumnosReal,
                $data['campus'],
                $pcl_Cercania,  // ACTUALIZAR CERCANÍA
                $juntaSeccion,
                $data['idplanclases']
            );
            $stmt->execute();
            
            $filasAfectadas = $stmt->affected_rows;
            error_log("✅ UPDATE asignacion ejecutado - Filas afectadas: " . $filasAfectadas);
            
            // ✅ LOG 6: DESPUÉS UPDATE - verificar si las variables cambiaron
            error_log("🔄 DESPUÉS UPDATE asignacion:");
            error_log("   data[campus]: '" . $data['campus'] . "'");
            error_log("   pcl_Cercania: '" . $pcl_Cercania . "'");
            
            // Ajustar número de registros si cambió
            $diff = $data['nSalas'] - $currentState['count'];
            error_log("📊 DIFERENCIA SALAS: " . $diff . " (solicitadas: " . $data['nSalas'] . ", existentes: " . $currentState['count'] . ")");
            
            if ($diff > 0) {
                // ✅ LOG 7: ANTES INSERT
                error_log("📝 ANTES INSERT asignacion (diff=" . $diff . "):");
                error_log("   data[idplanclases]: '" . $data['idplanclases'] . "'");
                error_log("   nAlumnosReal: '" . $nAlumnosReal . "'");
                error_log("   pcl_TipoSesion: '" . $dataPlanclases['pcl_TipoSesion'] . "'");
                error_log("   data[campus]: '" . $data['campus'] . "'");
                error_log("   pcl_Fecha: '" . $dataPlanclases['pcl_Fecha'] . "'");
                error_log("   pcl_Inicio: '" . $dataPlanclases['pcl_Inicio'] . "'");
                error_log("   pcl_Termino: '" . $dataPlanclases['pcl_Termino'] . "'");
                error_log("   cursos_idcursos: '" . $dataPlanclases['cursos_idcursos'] . "'");
                error_log("   pcl_AsiCodigo: '" . $dataPlanclases['pcl_AsiCodigo'] . "'");
                error_log("   pcl_Seccion: '" . $dataPlanclases['pcl_Seccion'] . "'");
                error_log("   pcl_AsiNombre: '" . $dataPlanclases['pcl_AsiNombre'] . "'");
                error_log("   observacionesAsignacion: '" . (isset($observacionesAsignacion) ? $observacionesAsignacion : 'NO_DEFINIDO') . "'");
                error_log("   pcl_Cercania: '" . $pcl_Cercania . "'");
                error_log("   juntaSeccion: '" . $juntaSeccion . "'");
                
                $queryInsert = "INSERT INTO asignacion (
                    idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                    fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                    NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
                ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 0, ?, NOW())";
                
                $stmtInsert = $conn->prepare($queryInsert);
                $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
                
                // ✅ DEFINIR observacionesAsignacion para INSERT
                $observacionesAsignacion = $nuevaObservacionAsignacion;
                
                for ($i = 0; $i < $diff; $i++) {
                    error_log("🔄 INSERT iteración " . ($i+1) . "/" . $diff);
                    
                    $stmtInsert->bind_param(
                        "iisssssisssssis",  // 15 caracteres
                        $data['idplanclases'],               // 1
                        $nAlumnosReal,                       // 2
                        $dataPlanclases['pcl_TipoSesion'],   // 3
                        $data['campus'],                     // 4
                        $dataPlanclases['pcl_Fecha'],        // 5
                        $dataPlanclases['pcl_Inicio'],       // 6
                        $dataPlanclases['pcl_Termino'],      // 7
                        $dataPlanclases['cursos_idcursos'],  // 8
                        $dataPlanclases['pcl_AsiCodigo'],    // 9
                        $dataPlanclases['pcl_Seccion'],      // 10
                        $dataPlanclases['pcl_AsiNombre'],    // 11
                        $nuevaObservacionAsignacion,         // 12
                        $pcl_Cercania,                       // 13 (string)
                        $juntaSeccion,                       // 14
                        $rut                             // 15
                    );
                    
                    if (!$stmtInsert->execute()) {
                        error_log("❌ ERROR en INSERT iteración " . ($i+1) . ": " . $stmtInsert->error);
                        throw new Exception("Error en INSERT iteración " . ($i+1) . ": " . $stmtInsert->error);
                    } else {
                        error_log("✅ INSERT iteración " . ($i+1) . " exitoso");
                    }
                }
                
                $stmtInsert->close();
                
            } elseif ($diff < 0) {
                // Eliminar asignaciones sobrantes
                error_log("🗑️ ELIMINANDO " . abs($diff) . " asignaciones sobrantes");
                $stmt = $conn->prepare("DELETE FROM asignacion 
                                      WHERE idplanclases = ? AND idEstado = 0 
                                      LIMIT ?");
                $limit = abs($diff);
                $stmt->bind_param("ii", $data['idplanclases'], $limit);
                $stmt->execute();
                error_log("✅ ELIMINADAS " . $stmt->affected_rows . " asignaciones");
            }
        } else {
            error_log("⚠️ No se modifican asignaciones porque maxEstado != 0");
        }
        
        $conn->commit();
		
		 registrarLogSala2($conn, $data['idplanclases'], 'modificar');
		
        error_log("✅ COMMIT FINAL exitoso");
        error_log("🏁 FIN MODIFICAR - ID: " . $data['idplanclases']);
        error_log("🏁 =========================");
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("❌ ERROR en case 'modificar': " . $e->getMessage());
        error_log("❌ Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;

case 'modificar_asignada':
    try {
        $conn->begin_transaction();
		
		// Verificar que exista el parámetro requerido
        if (!isset($data['idplanclases']) || empty($data['idplanclases'])) {
            throw new Exception('ID de planclases no proporcionado');
        }
        
        $idplanclases = intval($data['idplanclases']);
        
        // Verificación: Si tiene computación asignada, borrarla toda
        $queryTieneComputacion = "SELECT COUNT(*) as count 
                                  FROM asignacion 
                                  WHERE idplanclases = ? 
                                  AND idEstado = 3 
                                  AND idSala IN ('computacion1', 'computacion2')";
        
        $stmtTieneComputacion = $conn->prepare($queryTieneComputacion);
        if (!$stmtTieneComputacion) {
            throw new Exception('Error preparando consulta de verificación: ' . $conn->error);
        }
        
        $stmtTieneComputacion->bind_param("i", $idplanclases);
        $stmtTieneComputacion->execute();
        $result = $stmtTieneComputacion->get_result();
        $tieneComputacion = $result->fetch_assoc()['count'] > 0;
        $stmtTieneComputacion->close();
        
         if ($tieneComputacion) {
            error_log("LIBERAR COMPUTACION - ID: " . $idplanclases);
            
            // ✅ 1. Borrar SOLO las reservas de computación
            $queryBorrarReservas = "DELETE FROM reserva 
                                    WHERE re_idRepeticion = ? 
                                    AND re_idSala IN ('computacion1', 'computacion2')";
            
            $stmtBorrarReservas = $reserva2->prepare($queryBorrarReservas);
            if ($stmtBorrarReservas) {
                $stmtBorrarReservas->bind_param("i", $idplanclases);
                $stmtBorrarReservas->execute();
                $reservasBorradas = $stmtBorrarReservas->affected_rows;
                $stmtBorrarReservas->close();
            } else {
                $reservasBorradas = 0;
            }
            
            // ✅ 2. CAMBIAR asignaciones de computación: estado 3→1, idSala=''
            $queryActualizarComputacion = "UPDATE asignacion 
                                           SET idEstado = 1, 
                                               idSala = '',
                                               Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Computación liberada automáticamente')
                                           WHERE idplanclases = ? 
                                           AND idSala IN ('computacion1', 'computacion2')";
            
            $stmtActualizarComputacion = $conn->prepare($queryActualizarComputacion);
            if ($stmtActualizarComputacion) {
                $stmtActualizarComputacion->bind_param("i", $idplanclases);
                $stmtActualizarComputacion->execute();
                $asignacionesActualizadas = $stmtActualizarComputacion->affected_rows;
                $stmtActualizarComputacion->close();
            } else {
                $asignacionesActualizadas = 0;
            }
            
            error_log("COMPUTACION LIBERADA - Reservas borradas: $reservasBorradas, Asignaciones actualizadas: $asignacionesActualizadas");
        }
		
		$juntarSecciones = isset($data['juntarSecciones']) && $data['juntarSecciones'] == '1';
        $actualizacionOk = actualizarPclAulaDescripcion($data['idplanclases'], $juntarSecciones, $conn, 'clinico');
        
        if (!$actualizacionOk) {
            throw new Exception('Error actualizando pcl_AulaDescripcion en clínico');
        }
        
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
		$juntaSeccionPlanclase = !empty($data['juntarSecciones']) ? 'S' : 'N'; // Para planclases
		
		// Log 
        $juntarSeccionesValue = isset($data['juntarSecciones']) ? $data['juntarSecciones'] : 'NO_ENVIADO';
        error_log("DEBUG - juntarSecciones recibido: " . var_export($juntarSeccionesValue, true));
        error_log("DEBUG - juntaSeccion calculado: " . $juntaSeccion);
        
        // Preparar observaciones para planclases
        $observacionesPlanclases = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionesPlanclases = date('Y-m-d H:i:s') . " - MODIFICACIÓN DE ASIGNADA: " . $data['observaciones'];
        }
		
		// ✅ NUEVA SECCIÓN: Calcular alumnos totales correctamente
        // Obtener datos de planclases
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        $alumnosTotales = obtenerAlumnosReales($data, $dataPlanclases);
        
        // Log para debugging
        error_log("DEBUG MODIFICAR_ASIGNADA - alumnosTotales calculado: " . $alumnosTotales);
        
        // ACTUALIZADA: Incluir pcl_movilidadReducida y pcl_Cercania
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?,
                                  pcl_alumnos = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
                                  pcl_AulaDescripcion = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isisssssi", 
            $data['nSalas'], 
            $data['campus'],
            $alumnosTotales,              // ✅ NUEVA LÍNEA
            $pcl_movilidadReducida,
            $pcl_Cercania,
            $juntaSeccionPlanclase,
            $observacionesPlanclases,
            $observacionesPlanclases,
            $data['idplanclases']
        );
        $stmt->execute();
        
        // Obtener datos de planclases
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
        $stmtPlanclases = $conn->prepare($queryPlanclases);
        $stmtPlanclases->bind_param("i", $data['idplanclases']);
        $stmtPlanclases->execute();
        $resultPlanclases = $stmtPlanclases->get_result();
        $dataPlanclases = $resultPlanclases->fetch_assoc();
        
        // 1. Contar cuántas salas están actualmente asignadas (estado 3)
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM asignacion 
                               WHERE idplanclases = ? AND idEstado = 3");
        $stmt->bind_param("i", $data['idplanclases']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentAssigned = $result->fetch_assoc()['count'];
        
        // 3. Preparar observaciones para asignacion
        $observacionModificacion = "";
        if (isset($data['observaciones']) && !empty($data['observaciones'])) {
            $observacionModificacion = date('Y-m-d H:i:s') . " - MODIFICACIÓN DE ASIGNADA: " . $data['observaciones'];
        }
		
		$nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases);
		
        
        // ACTUALIZADA: Cambiar TODAS las asignaciones de estado 3 a estado 1 e incluir cercanía
        $stmt = $conn->prepare("UPDATE asignacion 
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
            $queryInsert = "INSERT INTO asignacion (
                idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
                fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
                NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
            ) VALUES (?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 1, ?, NOW())";
            
            $stmtInsert = $conn->prepare($queryInsert);
            $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
			$nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases);
			$alumnosTotales = obtenerAlumnosReales($data, $dataPlanclases); // Para planclases
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
				$rut                             // 15
			);
                $stmtInsert->execute();
            }
            
        } elseif ($diff < 0) {
            // Necesitamos MENOS salas: eliminar las sobrantes
            $limit = abs($diff);
            $stmt = $conn->prepare("DELETE FROM asignacion 
                                  WHERE idplanclases = ? AND idEstado = 1 
                                  ORDER BY idAsignacion DESC LIMIT ?");
            $stmt->bind_param("ii", $data['idplanclases'], $limit);
            $stmt->execute();
        }
        
        $conn->commit();
		
		registrarLogSala2($conn, $data['idplanclases'], 'modificar_asignada', $salasAnteriores);
        
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
	
	case 'obtener_estado_juntar_secciones':
    try {
        $idPlanClase = isset($data['idPlanClase']) ? (int)$data['idPlanClase'] : 0;
        
        if ($idPlanClase <= 0) {
            throw new Exception('ID de planclase inválido');
        }
        
        $estado = obtenerEstadoJuntarSecciones($idPlanClase, $conn, 'regular');
        
        echo json_encode(array(
            'success' => true,
            'pcl_AulaDescripcion' => $estado ? 'S' : 'N',
            'juntarSecciones' => $estado
        ));
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    break;


case 'actualizar_pcl_aula_descripcion':
    try {
        $idPlanClase = isset($data['idPlanClase']) ? (int)$data['idPlanClase'] : 0;
        $juntarSecciones = isset($data['juntarSecciones']) ? (bool)$data['juntarSecciones'] : false;
        
        if ($idPlanClase <= 0) {
            throw new Exception('ID de planclase inválido');
        }
        
        $resultado = actualizarPclAulaDescripcion($idPlanClase, $juntarSecciones, $conn, 'regular');
        
        if ($resultado) {
            echo json_encode(array('success' => true));
        } else {
            throw new Exception('Error al actualizar pcl_AulaDescripcion');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    break;

case 'obtener_datos_solicitud':
    try {
        // ACTUALIZADA: Incluir pcl_movilidadReducida en la consulta
        $stmt = $conn->prepare("SELECT p.pcl_campus, p.pcl_nSalas, p.pcl_DeseaSala, 
                               p.pcl_observaciones, p.pcl_movilidadReducida, p.pcl_AulaDescripcion,
                               (SELECT COUNT(*) FROM asignacion 
                                WHERE idplanclases = p.idplanclases 
                                AND idEstado = 3) as salas_asignadas
                               FROM planclases p 
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
				'pcl_AulaDescripcion' => $datos['pcl_AulaDescripcion'],
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
                               FROM asignacion 
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
        
        // Obtener información de la asignación antes de liberar
        $stmtInfo = $conn->prepare("SELECT idplanclases, idSala 
                                   FROM asignacion 
                                   WHERE idAsignacion = ?");
        $stmtInfo->bind_param("i", $data['idAsignacion']);
        $stmtInfo->execute();
        $resultInfo = $stmtInfo->get_result();
        $infoAsignacion = $resultInfo->fetch_assoc();
        
        if (!$infoAsignacion) {
            throw new Exception('No se encontró la asignación especificada');
        }
        
        $idplanclases = $infoAsignacion['idplanclases'];
        $idSala = $infoAsignacion['idSala'];
        
        // PASO 1: Liberar la asignación (cambiar estado a 4)
        $stmt = $conn->prepare("UPDATE asignacion 
                               SET idSala = NULL, idEstado = 4,
                                   Comentario = CONCAT(IFNULL(Comentario, ''), '\n\n', ?, ' - SALA LIBERADA MANUALMENTE')
                               WHERE idAsignacion = ?");
        $timestampLiberacion = date('Y-m-d H:i:s');
        $stmt->bind_param("si", $timestampLiberacion, $data['idAsignacion']);
        $stmt->execute();
        
        // PASO 2: ✅ NUEVO - Borrar reserva DE CUALQUIER SALA (no solo computación)
        $reservasEliminadas = 0;
        if (!empty($idSala)) {
            $queryBorrarReserva = "DELETE FROM reserva 
                                  WHERE re_idRepeticion = ? 
                                  AND re_idSala = ?";
            $stmtBorrarReserva = $reserva2->prepare($queryBorrarReserva);
            $stmtBorrarReserva->bind_param("is", $idplanclases, $idSala);
            $stmtBorrarReserva->execute();
            $reservasEliminadas = $stmtBorrarReserva->affected_rows;
            $stmtBorrarReserva->close();
            
            error_log("🗑️ LIBERACIÓN: Eliminadas {$reservasEliminadas} reservas de {$idSala} (idplanclases: {$idplanclases})");
        } else {
            error_log("⚠️ LIBERACIÓN: No se pudo borrar reserva - idSala está vacío");
        }
        
        // PASO 3: Actualizar contador en planclases
        $stmt = $conn->prepare("UPDATE planclases p
                              SET pcl_nSalas = (
                                  SELECT COUNT(*) 
                                  FROM asignacion 
                                  WHERE idplanclases = p.idplanclases 
                                  AND idEstado IN (0,1,3)
                              )
                              WHERE p.idplanclases = ?");
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        
        $conn->commit();
		
		 registrarLogSala2($conn, $idplanclases, 'liberar', $idSala);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sala liberada correctamente',
            'detalles' => [
                'sala_liberada' => $idSala,
                'reservas_eliminadas' => $reservasEliminadas,
                'idplanclases' => $idplanclases
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("❌ ERROR EN LIBERAR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;
    
    // salas computacion
    
    case 'guardar_con_computacion':
    try {
       $juntaSeccion = !empty($data['juntarSecciones']) ? 1 : 0;
	   $juntaSeccionPlanclase = !empty($data['juntarSecciones']) ? 'S' : 'N'; // Para planclases
	   
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
		
		$rutDPI = 'DPIServer';
		
		// 🔥 NUEVA SECCIÓN: Liberar computación existente ANTES de asignar nueva
		$queryTieneComputacion = "SELECT COUNT(*) as count 
                                  FROM asignacion 
                                  WHERE idplanclases = ? 
                                  AND idEstado = 3 
                                  AND idSala IN ('computacion1', 'computacion2')";
        
        $stmtTieneComputacion = $conn->prepare($queryTieneComputacion);
        $stmtTieneComputacion->bind_param("i", $idplanclases);
        $stmtTieneComputacion->execute();
        $result = $stmtTieneComputacion->get_result();
        $tieneComputacion = $result->fetch_assoc()['count'] > 0;
        $stmtTieneComputacion->close();
        
        if ($tieneComputacion) {
            error_log("🔥 LIBERANDO COMPUTACIÓN EXISTENTE ANTES DE ASIGNAR NUEVA");
            
            // Borrar reservas de computación
            $queryBorrarReservas = "DELETE FROM reserva 
                                    WHERE re_idRepeticion = ? 
                                    AND re_idSala IN ('computacion1', 'computacion2')";
            $stmtBorrarReservas = $reserva2->prepare($queryBorrarReservas);
            $stmtBorrarReservas->bind_param("i", $idplanclases);
            $stmtBorrarReservas->execute();
            $stmtBorrarReservas->close();
            
            // Liberar asignaciones de computación
            $queryLiberarComputacion = "UPDATE asignacion 
                                        SET idEstado = 4, 
                                            idSala = '',
                                            Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Computación anterior liberada automáticamente')
                                        WHERE idplanclases = ? 
                                        AND idSala IN ('computacion1', 'computacion2')";
            $stmtLiberar = $conn->prepare($queryLiberarComputacion);
            $stmtLiberar->bind_param("i", $idplanclases);
            $stmtLiberar->execute();
            $stmtLiberar->close();
        }
        
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
        $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
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
        
        $stmt = $conn->prepare("UPDATE planclases 
                              SET pcl_nSalas = ?, 
                                  pcl_campus = ?, 
                                  pcl_DeseaSala = ?,
                                  pcl_movilidadReducida = ?,
                                  pcl_Cercania = ?,
								  pcl_AulaDescripcion = ?,
                                  pcl_observaciones = CASE 
                                      WHEN COALESCE(pcl_observaciones, '') = '' THEN ?
                                      ELSE CONCAT(pcl_observaciones, '\n\n', ?)
                                  END
                              WHERE idplanclases = ?");
        $stmt->bind_param("isisssssi", 
            $nSalasTotales, 
            $campus, 
            $requiereSala,
            $pcl_movilidadReducida,  // 'S' o 'N'
            $pcl_Cercania,           // 'S' o 'N'
			$juntaSeccionPlanclase,
            $observacionesPlanclases,
            $observacionesPlanclases,
            $idplanclases
        );
        $stmt->execute();
		
		

        // ✅ NUEVA LÓGICA: GESTIONAR ASIGNACIONES EXISTENTES
        $queryExistentes = "SELECT COUNT(*) as count FROM asignacion WHERE idplanclases = ? AND idEstado = 0";
        $stmtExistentes = $conn->prepare($queryExistentes);
        $stmtExistentes->bind_param("i", $idplanclases);
        $stmtExistentes->execute();
        $resultExistentes = $stmtExistentes->get_result();
        $asignacionesExistentes = $resultExistentes->fetch_assoc()['count'];
        $stmtExistentes->close();
        
        $salasComputacionPedidas = count($salasComputacion);
        $diferencia = $asignacionesExistentes - $nSalasTotales;
        
        error_log("🔍 GESTIÓN EXISTENTES: {$asignacionesExistentes} existentes, {$nSalasTotales} pedidas, diferencia: {$diferencia}");
        
        // Si hay asignaciones existentes, gestionarlas
        if ($asignacionesExistentes > 0) {
            
            // 1. Si hay SOBRANTES: eliminar las que sobran
            if ($diferencia > 0) {
                error_log("🗑️ ELIMINANDO {$diferencia} asignaciones sobrantes");
                $stmtEliminar = $conn->prepare("DELETE FROM asignacion 
                                               WHERE idplanclases = ? AND idEstado in  (0, 1 ,4)
                                               LIMIT ?");
                $stmtEliminar->bind_param("ii", $idplanclases, $diferencia);
                $stmtEliminar->execute();
                $eliminadas = $stmtEliminar->affected_rows;
                $stmtEliminar->close();
                error_log("✅ ELIMINADAS {$eliminadas} asignaciones sobrantes");
            }
            
            // 2. ACTUALIZAR las existentes restantes para computación
            $asignacionesParaComputacion = min($salasComputacionPedidas, $asignacionesExistentes - max(0, $diferencia));
            
            if ($asignacionesParaComputacion > 0) {
                error_log("🔄 ACTUALIZANDO {$asignacionesParaComputacion} asignaciones existentes para computación");
                
                // Preparar query de actualización
                $queryActualizar = "UPDATE asignacion 
                                   SET idEstado = 3, 
                                       idSala = ?, 
                                       capacidadSala = ?,
                                       nAlumnos = ?,
                                       campus = ?,
                                       cercania = ?,
                                       junta_seccion = ?,
                                       Comentario = CONCAT(IFNULL(Comentario, ''), '\n\n', ?)
                                   WHERE idplanclases = ? AND idEstado = 0 
                                   LIMIT 1";
                
                $stmtActualizar = $conn->prepare($queryActualizar);
                
                $indiceComputacion = 0;
                foreach ($salasComputacion as $idSala) {
                    if ($indiceComputacion >= $asignacionesParaComputacion) break;
                    
                    // Obtener capacidad de la sala
                    $queryCapacidad = "SELECT sa_Capacidad FROM sala_reserva WHERE idSala = ?";
                    $stmtCapacidad = $conn->prepare($queryCapacidad);
                    $stmtCapacidad->bind_param("s", $idSala);
                    $stmtCapacidad->execute();
                    $resultCapacidad = $stmtCapacidad->get_result();
                    $rowCapacidad = $resultCapacidad->fetch_assoc();
                    $capacidadSala = $rowCapacidad['sa_Capacidad'];
                    $stmtCapacidad->close();
                    
                    $nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases);
                    $comentarioActualizacion = date('Y-m-d H:i:s') . " - ACTUALIZADO A COMPUTACIÓN: " . $idSala;
                    
                    $stmtActualizar->bind_param("sisisssi", 
                        $idSala, $capacidadSala, $nAlumnosReal, $campus, 
                        $pcl_Cercania, $juntaSeccion, $comentarioActualizacion, $idplanclases
                    );
                    $stmtActualizar->execute();
                    
                    error_log("✅ ACTUALIZADA asignación existente con sala {$idSala}");
                    $indiceComputacion++;
                }
                $stmtActualizar->close();
                
                // Crear reservas para las salas de computación actualizadas
                // Crear reservas para las salas de computación actualizadas
foreach ($salasComputacion as $index => $idSala) {
    if ($index >= $asignacionesParaComputacion) break;
    
    // ✅ PASO 1: Obtener el idAsignacion de la asignación que acabamos de actualizar
    $queryObtenerIdAsignacion = "SELECT idAsignacion FROM asignacion 
                                WHERE idplanclases = ? AND idSala = ? AND idEstado = 3 
                                ORDER BY timestamp DESC LIMIT 1";
    $stmtObtenerIdAsignacion = $conn->prepare($queryObtenerIdAsignacion);
    $stmtObtenerIdAsignacion->bind_param("is", $idplanclases, $idSala);
    $stmtObtenerIdAsignacion->execute();
    $resultIdAsignacion = $stmtObtenerIdAsignacion->get_result();
    $rowIdAsignacion = $resultIdAsignacion->fetch_assoc();
    $idAsignacion = $rowIdAsignacion['idAsignacion'];
    $stmtObtenerIdAsignacion->close();
    
    // ✅ PASO 2: Insertar en tabla reserva CON idReserva = idAsignacion
    $queryReserva = "INSERT INTO reserva (
        idReserva,
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
    
    $stmtReserva = $reserva2->prepare($queryReserva);
    $labelCurso = $dataPlanclases['pcl_AsiNombre'] . " " . $dataPlanclases['pcl_AsiCodigo'] . "-" . $dataPlanclases['pcl_Seccion'];
    $observacionReserva = "RESERVA AUTOMATICA COMPUTACION - " . $dataPlanclases['pcl_AsiNombre'] . " - " . $dataPlanclases['pcl_tituloActividad'];
    $rutResponsable = $rut;
    
    // ✅ PASO 3: bind_param con 11 parámetros (agregamos idAsignacion al inicio)
    $stmtReserva->bind_param("sssssssssss", 
        $idAsignacion,  // ✅ NUEVO: idReserva = idAsignacion
        $idSala, 
        $dataPlanclases['pcl_Fecha'],
        $dataPlanclases['pcl_Inicio'],
        $dataPlanclases['pcl_Termino'],
        $labelCurso,
        $labelCurso,
        $observacionReserva,
        $idplanclases,
        $rutResponsable,
        $rutDPI
    );
    $stmtReserva->execute();
    $stmtReserva->close();
    
    error_log("✅ RESERVA CREADA para sala actualizada {$idSala} - idAsignacion: {$idAsignacion}");
}
                
                // Si se actualizaron todas las computación pedidas, SALTAR los INSERT originales
                if ($asignacionesParaComputacion >= $salasComputacionPedidas && $nSalasTotales == $salasComputacionPedidas) {
                    error_log("✅ GESTIÓN COMPLETA CON EXISTENTES - Saltando INSERT originales");
                    
                    $conn->commit();
                    
                    $mensajeExito = "Reserva exitosa (usando asignaciones existentes): " . implode(', ', array_map('ucfirst', $salasComputacion));
                    if ($movilidadReducida === 'Si') {
                        $mensajeExito .= " (Configurado para movilidad reducida - salas cercanas)";
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => $mensajeExito,
                        'salas_computacion_reservadas' => $salasComputacion,
                        'gestion_existentes' => true,
                        'eliminadas' => $diferencia > 0 ? $diferencia : 0,
                        'actualizadas' => $asignacionesParaComputacion
                    ]);
                    break; // ✅ SALIR DEL CASE
                }
            }
        }
        
        // ✅ Si llegamos aquí, necesitamos continuar con INSERT originales
        // para crear asignaciones faltantes o porque no había existentes
        error_log("🔄 CONTINUANDO con INSERT originales para asignaciones faltantes");
        
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
        
        // ACTUALIZADA: Usar valor dinámico de cercanía en asignacion
        $queryInsert = "INSERT INTO asignacion (
            idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
            fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
            NombreCurso, Comentario, cercania, junta_seccion, TipoAsignacion, idEstado, Usuario, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', 3, ?, NOW())";
        
        $stmtInsert = $conn->prepare($queryInsert);
        
        if (!$stmtInsert) {
            throw new Exception('Error preparando query asignacion: ' . $conn->error);
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
			
			$nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases);
            
            $capacidadSala = $rowCapacidad['sa_Capacidad'];
            $stmtCapacidad->close();
            
            // ACTUALIZADA: Insertar en asignacion con cercanía dinámica
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
                $rut
            );
            
            if (!$stmtInsert->execute()) {
                throw new Exception('Error insertando en asignacion: ' . $stmtInsert->error);
            }
            
           // ✅ PASO 2: Obtener el idAsignacion recién insertado
    $idAsignacionNueva = $conn->insert_id;
    
    // ✅ PASO 3: Insertar en tabla reserva CON idReserva = idAsignacion
    $queryReserva = "INSERT INTO reserva (
        idReserva,
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
    
    $stmtReserva = $reserva2->prepare($queryReserva);
    
    if (!$stmtReserva) {
        throw new Exception('Error preparando query reserva: ' . $reserva2->error);
    }
    
    // Preparar datos específicos para la reserva
    $labelCurso = $dataPlanclases['pcl_AsiNombre'] . " " . $dataPlanclases['pcl_AsiCodigo'] . "-" . $dataPlanclases['pcl_Seccion'];
    $observacionReserva = "RESERVA AUTOMATICA COMPUTACION - " . $dataPlanclases['pcl_AsiNombre'] . " - " . $dataPlanclases['pcl_tituloActividad'];
    
    // Obtener RUT de sesión para responsable
    $rutResponsable = $rut;
    
    // ✅ PASO 4: bind_param con 11 parámetros (agregamos idAsignacion al inicio)
    $stmtReserva->bind_param("sssssssssss", 
        $idAsignacionNueva,  // ✅ NUEVO: idReserva = idAsignacion
        $idSala, 
        $dataPlanclases['pcl_Fecha'],
        $dataPlanclases['pcl_Inicio'],
        $dataPlanclases['pcl_Termino'],
        $labelCurso,
        $labelCurso,
        $observacionReserva,
        $idplanclases,  // re_idRepeticion
        $rutResponsable, // re_idResponsable
        $rutDPI        // re_RegUsu
    );
    
    if (!$stmtReserva->execute()) {
        throw new Exception('Error insertando en reserva: ' . $stmtReserva->error);
    }
    
    $stmtReserva->close();
    
    error_log("✅ NUEVA ASIGNACIÓN CREADA - idAsignacion: {$idAsignacionNueva}, Sala: {$idSala}");
}
        
        // Si pidió más salas que las de computación reservadas, crear solicitudes normales
        $salasComputacionReservadas = count($salasComputacion);
        $salasRestantes = $nSalasTotales - $salasComputacionReservadas;
        $nAlumnosReal = distribuirAlumnosEntreSalas($data, $dataPlanclases);
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
                    throw new Exception('Error insertando sala adicional en asignacion: ' . $stmtInsert->error);
                }
            }
        }
        
        // Cerrar statement de insert
        $stmtInsert->close();
        
        $conn->commit();
		
		 foreach ($salasComputacion as $idSala) {
            registrarLogSala2($conn, $idplanclases, 'reservar_computacion', $idSala);
        }
        
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
                           FROM planclases 
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
                               FROM asignacion 
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
                $queryPaso1 = "SELECT COUNT(*) as existe FROM reserva WHERE re_idRepeticion = ? AND re_idSala = ?";
                $stmtPaso1 = $reserva2->prepare($queryPaso1);
                
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
                                        FROM reserva 
                                        WHERE re_idRepeticion = ? AND re_idSala = ? LIMIT 1";
                        $stmtReserva = $reserva2->prepare($queryReserva);
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
                        
                        $queryPaso2 = "SELECT COUNT(*) as existe FROM reserva 
                                       WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                                       AND re_FechaReserva = ? AND re_HoraReserva = ? 
                                       AND re_HoraTermino = ? AND re_idSala = ?";
                        $stmtPaso2 = $reserva2->prepare($queryPaso2);
                        
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
                                                FROM reserva 
                                                WHERE (re_idCurso LIKE ? OR re_labelCurso LIKE ?)
                                                AND re_FechaReserva = ? AND re_HoraReserva = ? 
                                                AND re_HoraTermino = ? AND re_idSala = ? LIMIT 1";
                                $stmtReserva = $reserva2->prepare($queryReserva);
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


$idCurso = $_GET['curso'];

// $query = "SELECT
//     p.idplanclases,
//     p.pcl_tituloActividad,
//     p.pcl_Fecha,
//     p.pcl_Inicio,
//     p.pcl_Termino,
//     p.pcl_TipoSesion,
//     p.pcl_SubTipoSesion,
//     p.pcl_campus,
//     p.pcl_alumnos,
//     p.pcl_nSalas,
//     p.pcl_DeseaSala,
//     p.pcl_observaciones,
//     COALESCE(t.pedir_sala, 0) as pedir_sala,
//     (SELECT GROUP_CONCAT(DISTINCT idSala)
//      FROM asignacion
//      WHERE idplanclases = p.idplanclases AND idEstado != 4) AS salas_asignadas,
//     (SELECT COUNT(*)
//      FROM asignacion
//      WHERE idplanclases = p.idplanclases AND idEstado = 3) AS salas_confirmadas,
//     (SELECT COUNT(*)
//      FROM asignacion
//      WHERE idplanclases = p.idplanclases 
//      AND idEstado = 0) AS salas_solicitadas
// FROM planclases p
// LEFT JOIN pcl_TipoSesion t ON p.pcl_TipoSesion = t.tipo_sesion 
//     AND p.pcl_SubTipoSesion = t.Sub_tipo_sesion
// WHERE p.cursos_idcursos = ? 
// AND p.pcl_tituloActividad != ''
// AND (t.tipo_activo = 1 OR p.pcl_DeseaSala = 0)
// AND t.pedir_sala = 1
// ORDER BY p.pcl_Fecha ASC, p.pcl_Inicio ASC";

$query = "
SELECT
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
    COALESCE(t.pedir_sala, 0) AS pedir_sala,
    COALESCE(a.salas_asignadas, NULL) AS salas_asignadas,
    COALESCE(a.salas_confirmadas, 0) AS salas_confirmadas,
    COALESCE(a.salas_solicitadas, 0) AS salas_solicitadas
FROM
    planclases p
LEFT JOIN pcl_TipoSesion t ON
    p.pcl_TipoSesion = t.tipo_sesion AND p.pcl_SubTipoSesion = t.Sub_tipo_sesion
LEFT JOIN(
    SELECT idplanclases,
        COALESCE(
            GROUP_CONCAT(
                DISTINCT CASE WHEN idEstado != 4 THEN idSala
            END
        ),
        ''
) AS salas_asignadas,
SUM(
    CASE WHEN idEstado = 3 THEN 1 ELSE 0
END
) AS salas_confirmadas,
SUM(
    CASE WHEN idEstado = 0 THEN 1 ELSE 0
END
) AS salas_solicitadas
FROM
    asignacion
GROUP BY
    idplanclases
) a
ON
    p.idplanclases = a.idplanclases
WHERE
    p.cursos_idcursos = ? AND p.pcl_tituloActividad != '' AND(
        t.tipo_activo = 1 OR p.pcl_DeseaSala = 0
    ) AND t.pedir_sala = 1
ORDER BY
    p.pcl_Fecha ASC,
    p.pcl_Inicio ASC;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idCurso);
$stmt->execute();
$result = $stmt->get_result();
?>



<div class="container-fluid py-4">
        <!-- Información del curso -->       
		
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
                Las actividades de tipo clase se solicitan automáticamente. Para las demás, haga clic en “Solicitar”.
            </li>
            <li class="list-group-item">
                <i class="bi bi-pencil-square text-success me-2"></i>
                Si cometió un error al enviar una solicitud, si le asignaron una sala que no sirve o necesita una adicional, haga clic en “Modificar”.
            </li>
            <li class="list-group-item">
                <i class="bi bi-people text-info me-2"></i>
                Si el curso tiene más de una sección y desea unirlas para una actividad (como una evaluación), solo puede hacerlo desde la sección 1. Marque la opción “Quiero juntar todas las secciones del curso” (el sistema sumará automáticamente el total de estudiantes). Si la actividad es de tipo clase, primero haga clic en “Modificar” y luego marque la misma opción.
            </li>
            <li class="list-group-item">
                <i class="bi bi-pc-display-horizontal text-dark me-2"></i>
                Para usar laboratorios de computación, primero indique si necesita una o ambas salas. Si hay disponibilidad, marque “¿Desea reservar sala(s) de computación para esta actividad?”. Al guardar la solicitud, la asignación será automática (siempre que no haya sido tomada segundos antes por otro curso).
            </li>
            <li class="list-group-item">
                <i class="bi bi-universal-access text-secondary me-2"></i>
                Si hay estudiantes con movilidad reducida, debe informarlo al CEA. Ellos lo contactarán para registrar el caso en el sistema de la Unidad de Aulas.
            </li>
            <li class="list-group-item">
                <i class="bi bi-box-arrow-left text-danger me-2"></i>
                Si tiene salas asignadas que no utilizará, haga clic en “Liberar” y elija cuál desea liberar.
            </li>
        </ul>
    </div>
</div>
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
                                FROM planclases 
                                WHERE idplanclases = ?";
        $stmtDatos = $conn->prepare($queryDatosPlanclases);
        $stmtDatos->bind_param("i", $row['idplanclases']);
        $stmtDatos->execute();
        $resultDatos = $stmtDatos->get_result();
        $datosPlanclases = $resultDatos->fetch_assoc();
        $stmtDatos->close();

        // ✅ OBTENER ESTADOS DE ASIGNACIONES
        $queryEstados = "SELECT idEstado, COUNT(*) as cantidad, GROUP_CONCAT(idSala) as salas 
                         FROM asignacion 
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
                    $reserva2, 
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
    $tituloCorto = mb_strlen($tituloCompleto, 'UTF-8') > 25 ? 
                  mb_substr($tituloCompleto, 0, 25, 'UTF-8') . '...' : 
                  $tituloCompleto;
    $needsTooltip = mb_strlen($tituloCompleto, 'UTF-8') > 25;
    ?>
    <?php if($needsTooltip): ?>
        <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($tituloCompleto, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($tituloCorto, ENT_QUOTES, 'UTF-8'); ?>
        </span>
    <?php else: ?>
        <?php echo htmlspecialchars($tituloCorto, ENT_QUOTES, 'UTF-8'); ?>
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
            
        <?php elseif(!$requiereSala): ?>
            <span class="badge bg-dark">Actividad no requiere sala</span>
            
        <?php else: ?>
            <span class="badge bg-secondary">Pendiente</span>
            
        <?php endif; ?>
        </td>

        <!-- ✅ COLUMNA ACCIONES CORREGIDA -->
        <td>
        <?php
        echo '<div class="btn-group-vertical btn-group-sm">';

        if (!$requiereSala) {
            echo '<span class="badge bg-info"><i class="bi bi-x-circle"></i> Sin Acciones</span>';
            
        } elseif ($tieneInconsistencias) {            
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <div class="d-flex align-items-center w-100">
                    <div class="flex-grow-1">
                        <h4 class="modal-title mb-1">
                            <i class="bi bi-building me-2"></i>
                            <span id="salaModalTitle">Gestionar Sala</span> 
                            <span id="sala-modal-idplanclases" class="badge bg-light text-primary ms-2"></span>
                        </h4>
                        <div class="d-flex gap-3 text-white-50">
                            <small>
                                <i class="bi bi-calendar-event me-1"></i> 
                                <span id="sala-modal-fecha-hora">Cargando...</span>
                            </small>
                            <small>
                                <i class="bi bi-tag me-1"></i> 
                                <span id="sala-modal-tipo-sesion">Cargando...</span>
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4">                

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
        <a href="salas_UGA.php" target="_blank" 
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i>
            Ver salas
        </a>
    </div>
</div>


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
								   placeholder="Ingrese cantidad" min="1" onchange="actualizarSalasDisponibles()" readonly>
							<!-- <button class="btn btn-outline-success" type="button" id="btnSalasDisponibles" 
									onclick="mostrarSalasDisponibles()" style="display: none;">
								<i class="bi bi-building"></i> 
								<span id="numeroSalasDisponibles">0</span> disponibles
							</button> -->
						</div>
						<div class="form-text">
							<small class="text-muted">Este valor se calcula automáticamente según el número total de alumnos y salas requeridas.</small>
						</div>
					</div>
					
					<div id="seccion-computacion" style="display: none;">
    <hr>
    <div class="mb-3">
        <label class="form-label fw-bold text-primary mb-0">
            <i class="bi bi-pc-display me-2"></i>
            Salas de Computación Disponibles
        </label>		
		
        
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
