<?php

include("conexion.php");
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once 'login/control_sesion.php';
$ruti = $_SESSION['sesion_idLogin'];
$rut = str_pad($ruti, 10, "0", STR_PAD_LEFT);

$cursos_idcursos = isset($_POST['cursos_idcursos']) ? (int)$_POST['cursos_idcursos'] :9090;

try {
    // Verificar si es una actualización o nueva creación
    $esActualizacion = isset($_POST['idplanclases']) && $_POST['idplanclases'] > 0;
    

if ($esActualizacion) {
    // MODO ACTUALIZACIÓN - Aplicar las 9 reglas adaptadas para clínicos
    $idplanclases = (int)$_POST['idplanclases'];

    // Obtener y sanitizar los valores
    $titulo = isset($_POST['activity-title']) ? trim($_POST['activity-title']) : '';
    $tipo = isset($_POST['type']) ? mysqli_real_escape_string($conn, $_POST['type']) : '';
    $subtipo = isset($_POST['subtype']) ? mysqli_real_escape_string($conn, $_POST['subtype']) : '';
    $fecha = isset($_POST['date']) ? mysqli_real_escape_string($conn, $_POST['date']) : '';
    $inicio = isset($_POST['start_time']) ? mysqli_real_escape_string($conn, $_POST['start_time']) : '';
    $termino = isset($_POST['end_time']) ? mysqli_real_escape_string($conn, $_POST['end_time']) : '';
    $dia = isset($_POST['dia']) ? mysqli_real_escape_string($conn, $_POST['dia']) : '';
    $condicion = isset($_POST['pcl_condicion']) && $_POST['pcl_condicion'] === 'Obligatorio' ? "Obligatorio" : "Libre";
	$evaluacion = isset($_POST['pcl_ActividadConEvaluacion']) && $_POST['pcl_ActividadConEvaluacion'] === 'S' ? "S" : "N";
	
	if (!empty($tipo)) {
    $stmt = $conn->prepare("SELECT subtipo_activo FROM pcl_TipoSesion WHERE tipo_sesion = ? AND tipo_activo = 1 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $tipo);
        $stmt->execute();
        $result = $stmt->get_result();
        $tipoInfo = $result->fetch_assoc();
        $stmt->close();
        
        // Si requiere subtipo y no hay subtipo
        if ($tipoInfo && $tipoInfo['subtipo_activo'] == 1 && empty($subtipo)) {
            http_response_code(400);
            echo json_encode(array(
                'success' => false,
                'message' => 'Debe seleccionar un subtipo de actividad para el tipo seleccionado'
            ));
            exit;
        }
    }
}

    // Calcular semana basada en la nueva fecha
    $semana = date('W', strtotime($fecha)) - date('W', strtotime(date('Y') . '-01-01')) + 1;
    if ($semana < 1) $semana = 1;

    // CORRECCIÓN: Forzar subtipo correcto para tipo "Clase"
    if ($tipo === 'Clase') {
        $subtipo = 'Clase teórica o expositiva';
    }

    $conn->begin_transaction();

    // Obtener el tipo anterior ANTES de cualquier actualización
    $queryTipoAnterior = "SELECT pcl_TipoSesion FROM planclases WHERE idplanclases = ?";
    $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
    $stmtTipoAnterior->bind_param("i", $idplanclases);
    $stmtTipoAnterior->execute();
    $resultTipoAnterior = $stmtTipoAnterior->get_result();
    $tipoAnterior = $resultTipoAnterior->fetch_assoc()['pcl_TipoSesion'];
    $stmtTipoAnterior->close();

    /* 
     * CÁLCULO DINÁMICO DE pcl_nSalas SEGÚN TIPO DE ACTIVIDAD (CLÍNICOS)
     */
    $nsalasCalculado = 1;

    // Consultar si el tipo requiere sala dinámicamente desde pcl_TipoSesion
    $queryRequiereSala = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ?";
    $params = [$tipo];
    $paramTypes = "s";

    if ($tipo === 'Clase') {
        $queryRequiereSala .= " AND Sub_tipo_sesion = 'Clase teórica o expositiva'";
    } else if (!empty($subtipo)) {
        $queryRequiereSala .= " AND Sub_tipo_sesion = ?";
        $params[] = $subtipo;
        $paramTypes .= "s";
    } else {
        $queryRequiereSala .= " AND (Sub_tipo_sesion IS NULL OR Sub_tipo_sesion = '')";
    }
    $queryRequiereSala .= " AND tipo_activo = 1";

    $stmtRequiereSala = $conn->prepare($queryRequiereSala);
    $stmtRequiereSala->bind_param($paramTypes, ...$params);
    $stmtRequiereSala->execute();
    $resultRequiereSala = $stmtRequiereSala->get_result();
    $requiereSalaRow = $resultRequiereSala->fetch_assoc();
    $requiereSalaActual = $requiereSalaRow ? $requiereSalaRow['pedir_sala'] : 0;
    $stmtRequiereSala->close();

    // Aplicar reglas específicas de negocio para pcl_nSalas (CLÍNICOS)
    if ($tipo === 'Clase') {
        $nsalasCalculado = 1;
    } else if ($requiereSalaActual == 1) {
        $queryNsalasActual = "SELECT pcl_nSalas FROM planclases WHERE idplanclases = ?";
        $stmtNsalasActual = $conn->prepare($queryNsalasActual);
        $stmtNsalasActual->bind_param("i", $idplanclases);
        $stmtNsalasActual->execute();
        $resultNsalasActual = $stmtNsalasActual->get_result();
        $nsalasActual = $resultNsalasActual->fetch_assoc()['pcl_nSalas'];
        $stmtNsalasActual->close();
        
        $nsalasCalculado = ($nsalasActual > 1) ? $nsalasActual : 1;
    } else {
        $nsalasCalculado = 0;
    }

    // Actualizar planclases incluyendo pcl_nSalas
    $query = "UPDATE planclases SET 
                pcl_tituloActividad = ?, 
                pcl_TipoSesion = ?,
                pcl_SubTipoSesion = ?,
                pcl_Fecha = ?,
                pcl_Inicio = ?,
                pcl_Termino = ?,
                dia = ?,
                pcl_Semana = ?,
                pcl_condicion = ?,
                pcl_ActividadConEvaluacion = ?,
                pcl_nSalas = ?,
                pcl_fechamodifica = NOW(),
                pcl_usermodifica = ?
              WHERE idplanclases = ?";

    if (!$stmt = $conn->prepare($query)) {
        throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
    }

    if (!$stmt->bind_param("sssssssissisi",
        $titulo,
        $tipo,
        $subtipo,
        $fecha,
        $inicio,
        $termino,
        $dia,
        $semana,
        $condicion,
        $evaluacion,
        $nsalasCalculado,
		$rut,
        $idplanclases
    )) {
        throw new Exception('Error en el bind_param: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error en la ejecución: ' . $stmt->error);
    }

    // Verificar si necesitamos actualizar vigencia de docentes (CLÍNICOS)
//   if ($tipoAnterior !== $tipo) {
//       // Obtener si tipo anterior permitía docentes
//       $permiteDocentesAnterior = 1;
//       $stmtDocentesAnt = $conn->prepare("SELECT docentes FROM pcl_TipoSesion WHERE tipo_sesion = ? AND tipo_activo = 1");
//       $stmtDocentesAnt->bind_param("s", $tipoAnterior);
//       $stmtDocentesAnt->execute();
//       $resAnt = $stmtDocentesAnt->get_result();
//       if ($rowAnt = $resAnt->fetch_assoc()) {
//           $permiteDocentesAnterior = (int)$rowAnt['docentes'];
//       }
//       $stmtDocentesAnt->close();
//
//       // Obtener si tipo nuevo permite docentes
//       $permiteDocentesNuevo = 1;
//       $stmtDocentesNuevo = $conn->prepare("SELECT docentes FROM pcl_TipoSesion WHERE tipo_sesion = ? AND tipo_activo = 1");
//       $stmtDocentesNuevo->bind_param("s", $tipo);
//       $stmtDocentesNuevo->execute();
//       $resNuevo = $stmtDocentesNuevo->get_result();
//       if ($rowNuevo = $resNuevo->fetch_assoc()) {
//           $permiteDocentesNuevo = (int)$rowNuevo['docentes'];
//       }
//       $stmtDocentesNuevo->close();
//
//       // Solo si el tipo anterior permitía docentes y el nuevo NO
//       if ($permiteDocentesAnterior === 1 && $permiteDocentesNuevo === 0) {
//           $queryUpdateVigencia = "UPDATE docenteclases 
//                                   SET vigencia = 0, 
//                                       fechaModificacion = NOW(),
//                                       usuarioModificacion = 'sistema'
//                                   WHERE idPlanClases = ? AND vigencia = 1";
//           $stmtUpdateVigencia = $conn->prepare($queryUpdateVigencia);
//           $stmtUpdateVigencia->bind_param("i", $idplanclases);
//           $stmtUpdateVigencia->execute();
//           $stmtUpdateVigencia->close();
//       }
//   }

    /* 
     * GESTIÓN DE SALAS SEGÚN TIPO DE ACTIVIDAD (CLÍNICOS)
     */
    $requiereSala = false;
    $mensajeUsuario = '';
    $necesitaConfirmacion = false;
    $mensajeConfirmacion = '';

    // Consulta mejorada: Primero intentamos con tipo y subtipo (si existe)
    if (!empty($subtipo)) {
        $queryTipoSesion = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND Sub_tipo_sesion = ? AND tipo_activo = 1";
        $stmtTipo = $conn->prepare($queryTipoSesion);
        $stmtTipo->bind_param("ss", $tipo, $subtipo);
        $stmtTipo->execute();
        $resultTipo = $stmtTipo->get_result();

        if ($row = $resultTipo->fetch_assoc()) {
            $requiereSala = ($row['pedir_sala'] == 1);
        }
        $stmtTipo->close();
    }

    // Si no se encontró con subtipo o no hay subtipo, buscar solo por tipo
    if (!$requiereSala && empty($subtipo)) {
        $queryTipoSesion = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND (Sub_tipo_sesion IS NULL OR Sub_tipo_sesion = '') AND tipo_activo = 1";
        $stmtTipo = $conn->prepare($queryTipoSesion);
        $stmtTipo->bind_param("s", $tipo);
        $stmtTipo->execute();
        $resultTipo = $stmtTipo->get_result();

        if ($row = $resultTipo->fetch_assoc()) {
            $requiereSala = ($row['pedir_sala'] == 1);
        }
        $stmtTipo->close();
    }

    // Verificar si el tipo anterior requería sala
    $anteriorRequiereSala = false;
    $queryTipoAnterior = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND tipo_activo = 1";
    $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
    $stmtTipoAnterior->bind_param("s", $tipoAnterior);
    $stmtTipoAnterior->execute();
    $resultTipoAnterior = $stmtTipoAnterior->get_result();
    if ($rowAnterior = $resultTipoAnterior->fetch_assoc()) {
        $anteriorRequiereSala = ($rowAnterior['pedir_sala'] == 1);
    }
    $stmtTipoAnterior->close();

    // Actualizar explícitamente el campo pcl_DeseaSala en planclases
    $queryUpdateDeseaSala = "UPDATE planclases SET pcl_DeseaSala = ? WHERE idplanclases = ?";
    $stmtUpdateDeseaSala = $conn->prepare($queryUpdateDeseaSala);
    $valorDeseaSala = $requiereSala ? 1 : 0;
    $stmtUpdateDeseaSala->bind_param("ii", $valorDeseaSala, $idplanclases);
    $stmtUpdateDeseaSala->execute();
    $stmtUpdateDeseaSala->close();

    // Verificar estados actuales de las asignaciones
    $queryEstados = "SELECT idEstado, COUNT(*) as cantidad 
                    FROM asignacion 
                    WHERE idplanclases = ? 
                    GROUP BY idEstado";
    $stmtEstados = $conn->prepare($queryEstados);
    $stmtEstados->bind_param("i", $idplanclases);
    $stmtEstados->execute();
    $resultEstados = $stmtEstados->get_result();

    $estados = [0 => 0, 1 => 0, 3 => 0, 4 => 0];
    $totalAsignaciones = 0;

    while ($estado = $resultEstados->fetch_assoc()) {
        $estados[$estado['idEstado']] = $estado['cantidad'];
        $totalAsignaciones += $estado['cantidad'];
    }
    $stmtEstados->close();

    $asignacionesPendientes = $estados[0];
    $asignacionesModificadas = $estados[1];
    $asignacionesConfirmadas = $estados[3];
    $asignacionesLiberadas = $estados[4];

    /* 
     * IMPLEMENTACIÓN DE LOS 9 CASOS DE TRANSICIÓN ENTRE TIPOS DE ACTIVIDAD (CLÍNICOS)
     */
    
    if ($tipoAnterior === $tipo) {
        // SIN CAMBIO DE TIPO (casos 1, 5, 9)
        if ($tipo === 'Clase') {
            /* CASO 1: CLASE → CLASE (actualización de parámetros de la misma clase) */
            
            // Si hay asignaciones confirmadas, cambiarlas a estado "modificada"
            if ($asignacionesConfirmadas > 0) {
                //$queryModificar = "UPDATE asignacion 
                //                SET idEstado = 1, 
                //                    Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Datos de actividad clínica modificados')
                //                WHERE idplanclases = ? AND idEstado = 3";
                //$stmtModificar = $conn->prepare($queryModificar);
                //$stmtModificar->bind_param("i", $idplanclases);
                //$stmtModificar->execute();
                //$stmtModificar->close();
                
                $mensajeUsuario = 'Actividad clínica actualizada';
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Actividad actualizada";
            } else {
                $mensajeUsuario = 'Actividad clínica actualizada';
            }
        } else if ($requiereSala) {
            /* CASO 5: ACTIVIDAD GRUPAL/TP/EV/EX → ACTIVIDAD GRUPAL/TP/EV/EX */
            
            // Actualizar tipo en asignaciones existentes sin cambiar estado
            $queryActualizarTipo = "UPDATE asignacion 
                                SET tipoSesion = ?
                                WHERE idplanclases = ? AND idEstado IN (0, 1, 3)";
            $stmtActualizarTipo = $conn->prepare($queryActualizarTipo);
            $stmtActualizarTipo->bind_param("si", $tipo, $idplanclases);
            $stmtActualizarTipo->execute();
            $stmtActualizarTipo->close();
            
            $mensajeUsuario = 'Tipo de sesión actualizado en asignaciones existentes';
        } else {
            /* CASO 9: ACTIVIDAD SIN SALA → ACTIVIDAD SIN SALA */
            $mensajeUsuario = 'Actividad clínica actualizada - Sin sala requerida';
        }
    } else {
        // CON CAMBIO DE TIPO (casos 2, 3, 4, 6, 7, 8)
        if ($tipo === 'Clase') {
            if ($anteriorRequiereSala) {
                /* CASO 4: ACTIVIDAD GRUPAL/TP/EV/EX → CLASE */
                
                // Actualizar tipo a 'Clase' en asignaciones existentes
                if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                    $queryActualizarTipo = "UPDATE asignacion 
                                          SET tipoSesion = 'Clase',
                                              Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Tipo cambiado a Clase (clínico)')
                                          WHERE idplanclases = ? AND idEstado IN (0, 1, 3)";
                    $stmtActualizarTipo = $conn->prepare($queryActualizarTipo);
                    $stmtActualizarTipo->bind_param("i", $idplanclases);
                    $stmtActualizarTipo->execute();
                    $stmtActualizarTipo->close();
                }
                
                // Si hay salas asignadas, necesitamos confirmación
  //            if ($asignacionesConfirmadas > 0) {
  //                $necesitaConfirmacion = true;
  //                $mensajeConfirmacion = "Al cambiar a tipo 'Clase', se liberarán las salas actuales y el CRON creará una asignación automática. ¿Desea continuar?";
  //            }
  //            
  //            // Liberar asignaciones existentes (reservas) si hay
  //            if ($asignacionesConfirmadas > 0) {
  //                $queryLiberarExistentes = "UPDATE asignacion 
  //                                        SET idEstado = 4, 
  //                                            Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Liberada por cambio a tipo Clase (clínico)')
  //                                        WHERE idplanclases = ? AND idEstado = 3";
  //                $stmtLiberarExistentes = $conn->prepare($queryLiberarExistentes);
  //                $stmtLiberarExistentes->bind_param("i", $idplanclases);
  //                $stmtLiberarExistentes->execute();
  //                $stmtLiberarExistentes->close();
  //            }
  //            
  //            // Eliminar asignaciones en proceso
  //            $queryEliminarEnProceso = "DELETE FROM asignacion 
  //                                    WHERE idplanclases = ? AND idEstado IN (0, 1)";
  //            $stmtEliminarEnProceso = $conn->prepare($queryEliminarEnProceso);
  //            $stmtEliminarEnProceso->bind_param("i", $idplanclases);
  //            $stmtEliminarEnProceso->execute();
  //            $stmtEliminarEnProceso->close();
                
                $mensajeUsuario = 'Tipo de actividad actualizado';
                
            } else {
                /* CASO 7: ACTIVIDAD SIN SALA → CLASE */
                $mensajeUsuario = 'Cambiado a Clase - CRON gestionará asignación automática';
            }
        } else if ($requiereSala) {
            if ($tipoAnterior === 'Clase') {
                /* CASO 2: CLASE → ACTIVIDAD GRUPAL/TP/EV/EX */
                
                // Actualizar tipo en asignaciones existentes (NO eliminar)
                if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                    $queryActualizarTipo = "UPDATE asignacion 
                                          SET tipoSesion = ?, 
                                              Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Tipo cambiado de Clase a ', ?)
                                          WHERE idplanclases = ? AND idEstado IN (0, 1, 3)";
                    $stmtActualizarTipo = $conn->prepare($queryActualizarTipo);                   
                    $stmtActualizarTipo->bind_param("ssi", $tipo, $tipo, $idplanclases);
                    $stmtActualizarTipo->execute();
                    $stmtActualizarTipo->close();
                    
                    $necesitaConfirmacion = true;
                    $mensajeConfirmacion = "Al cambiar de 'Clase' a este tipo de actividad, se actualizarán las asignaciones automáticas. Deberá gestionar la sala manualmente desde la pestaña Salas. ¿Desea continuar?";
                }
                
                $mensajeUsuario = 'Tipo actualizado - Gestione sala desde pestaña Salas';
                
            } else {
                /* CASO 8: ACTIVIDAD SIN SALA → ACTIVIDAD GRUPAL/TP/EV/EX */
                $mensajeUsuario = 'Debe solicitar sala desde pestaña Salas';
            }
        } else {
            if ($tipoAnterior === 'Clase') {
                /* CASO 3: CLASE → ACTIVIDAD SIN SALA (PC/SA/TA/VT) */
                
                // Si hay salas asignadas, necesitamos confirmación
                if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                    $necesitaConfirmacion = true;
                    $mensajeConfirmacion = "Al cambiar a un tipo de actividad que no requiere sala, se eliminarán todas las asignaciones existentes. ¿Desea continuar?";
                }
                
                // ✅ CORRECCIÓN CRÍTICA: Eliminar PRIMERO las reservas físicas (igual que regulares)
                // 1. PRIMERO: Eliminar de reserva (liberar salas físicas)
                $queryBorrarReservas = "DELETE FROM reserva WHERE re_idRepeticion = ?";
                $stmtBorrarReservas = $reserva2->prepare($queryBorrarReservas);
                $stmtBorrarReservas->bind_param("i", $idplanclases);
                $stmtBorrarReservas->execute();
                $stmtBorrarReservas->close();

                // 2. SEGUNDO: Eliminar de asignacion (limpiar seguimiento)
                $queryEliminarTodas = "DELETE FROM asignacion WHERE idplanclases = ?";
                $stmtEliminarTodas = $conn->prepare($queryEliminarTodas);
                $stmtEliminarTodas->bind_param("i", $idplanclases);
                $stmtEliminarTodas->execute();
                $stmtEliminarTodas->close();
                
                // ✅ CORRECCIÓN: Mensaje consistente con regulares
                $mensajeUsuario = 'Actividad no requiere sala - Asignaciones y reservas eliminadas';
                
            } else {
                /* CASO 6: ACTIVIDAD GRUPAL/TP/EV/EX → ACTIVIDAD SIN SALA */
                
                // Si hay salas asignadas, necesitamos confirmación
                if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                    $necesitaConfirmacion = true;
                    $mensajeConfirmacion = "Al cambiar a un tipo sin sala, se eliminarán todas las asignaciones existentes. ¿Desea continuar?";
                }
                
                // ✅ CORRECCIÓN CRÍTICA: Eliminar PRIMERO las reservas físicas (igual que regulares)
                // 1. PRIMERO: Eliminar de reserva (liberar salas físicas)
                $queryBorrarReservas = "DELETE FROM reserva WHERE re_idRepeticion = ?";
                $stmtBorrarReservas = $reserva2->prepare($queryBorrarReservas);
                $stmtBorrarReservas->bind_param("i", $idplanclases);
                $stmtBorrarReservas->execute();
                $stmtBorrarReservas->close();

                // 2. SEGUNDO: Eliminar de asignacion (limpiar seguimiento)
                $queryEliminarTodas = "DELETE FROM asignacion WHERE idplanclases = ?";
                $stmtEliminarTodas = $conn->prepare($queryEliminarTodas);
                $stmtEliminarTodas->bind_param("i", $idplanclases);
                $stmtEliminarTodas->execute();
                $stmtEliminarTodas->close();

                // ✅ CORRECCIÓN: Mensaje consistente con regulares
                $mensajeUsuario = 'Actividad no requiere sala - Asignaciones y reservas eliminadas';
            }
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Actividad clínica actualizada exitosamente',
        'mensaje_sala' => $mensajeUsuario,
        'requiere_sala' => $requiereSala,
        'es_clase' => ($tipo === 'Clase'),
        'necesita_confirmacion' => $necesitaConfirmacion,
        'mensaje_confirmacion' => $mensajeConfirmacion,
        'debug_info' => [
            'tipo_guardado' => $tipo,
            'subtipo_guardado' => $subtipo,
            'nsalas_calculado' => $nsalasCalculado,
            'valor_desea_sala' => $valorDeseaSala
        ]
    ]);

    $stmt->close();
} else {
        // MODO CREACIÓN - Usar la lógica original que ya funciona
        
        // Validar que existan los campos mínimos necesarios
        $requiredFields = ['activity-title', 'type', 'date', 'start_time', 'end_time', 'cursos_idcursos', 'dia'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new Exception("Campos requeridos faltantes: " . implode(", ", $missingFields));
        }
        
        // Obtener y sanitizar los valores para nueva actividad
        $cursos_idcursos = (int)$_POST['cursos_idcursos'];
        $titulo = trim($_POST['activity-title']); // Sin mysqli_real_escape_string para evitar doble escaping
        $tipo = $_POST['type'];
        $subtipo = $_POST['subtype'];
        $fecha = mysqli_real_escape_string($conn, $_POST['date']);
        $inicio = mysqli_real_escape_string($conn, $_POST['start_time']) . ':00';
        $termino = mysqli_real_escape_string($conn, $_POST['end_time']) . ':00';
        $dia = mysqli_real_escape_string($conn, $_POST['dia']);
		$obligatorio = isset($_POST['pcl_condicion']) && $_POST['pcl_condicion'] === 'Obligatorio' ? "Obligatorio" : "Libre";
		$evaluacion = isset($_POST['pcl_ActividadConEvaluacion']) && $_POST['pcl_ActividadConEvaluacion'] === 'S' ? "S" : "N";
        $bloque = isset($_POST['Bloque']) ? $_POST['Bloque'] : null;
        
        // CORRECCIÓN: Forzar subtipo correcto para tipo "Clase"
        if ($tipo === 'Clase') {
            $subtipo = 'Clase teórica o expositiva';
        }
        
        // Calcular duración
        $time1 = strtotime($inicio);
        $time2 = strtotime($termino);
        $difference = $time2 - $time1;
        $horas = floor($difference / 3600);
        $minutos = floor(($difference % 3600) / 60);
        $segundos = $difference % 60;
        $duracion = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
        
        // Calcular valores por defecto para nuevas actividades clínicas
        $queryRequiereSala = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ?";
        $params = [$tipo];
        $paramTypes = "s";

        if ($tipo === 'Clase') {
            $queryRequiereSala .= " AND Sub_tipo_sesion = 'Clase teórica o expositiva'";
        } else if (!empty($subtipo)) {
            $queryRequiereSala .= " AND Sub_tipo_sesion = ?";
            $params[] = $subtipo;
            $paramTypes .= "s";
        } else {
            $queryRequiereSala .= " AND (Sub_tipo_sesion IS NULL OR Sub_tipo_sesion = '')";
        }
        $queryRequiereSala .= " AND tipo_activo = 1";

        $stmtRequiereSala = $conn->prepare($queryRequiereSala);
        $stmtRequiereSala->bind_param($paramTypes, ...$params);
        $stmtRequiereSala->execute();
        $resultRequiereSala = $stmtRequiereSala->get_result();
        $requiereSalaRow = $resultRequiereSala->fetch_assoc();
        $requiereSalaActual = $requiereSalaRow ? $requiereSalaRow['pedir_sala'] : 0;
        $stmtRequiereSala->close();

        // Para nuevas actividades clínicas, valores por defecto
        $nsalasCalculado = ($requiereSalaActual == 1) ? 1 : 0;
        $valorDeseaSala = $requiereSalaActual;
        
        // Inserción con valores calculados hay que preguntar que hacer con la semana de clinicos.
        $semana = date('W', strtotime($fecha)) - date('W', strtotime(date('Y') . '-01-01')) + 1;
        if ($semana < 1) $semana = 1;
        
        
		

$cursos_idcursos = isset($_POST['cursos_idcursos']) ? (int)$_POST['cursos_idcursos'] :9090;

$sqlCurso = "SELECT spre_cursos.idCurso,spre_cursos.CodigoCurso,spre_cursos.Cupo,spre_cursos.idperiodo,spre_ramos.nombreCurso,spre_cursos.Seccion  FROM spre_cursos 
INNER JOIN spre_ramos ON spre_cursos.codigoCurso = spre_ramos.codigoCurso
WHERE idCurso= ?";
$stmtCurso = $conexion3->prepare($sqlCurso);
$stmtCurso->bind_param("i", $cursos_idcursos);
$stmtCurso->execute();
$resultCurso = $stmtCurso->get_result();

if ($resultCurso && $resultCurso->num_rows > 0) {
    $cursoRow = $resultCurso->fetch_assoc();
    $seccionCurso = $cursoRow['Seccion'];
    $codigoCurso = $cursoRow['CodigoCurso'];
	$nombreCurso = mb_convert_encoding($cursoRow['nombreCurso'], 'UTF-8', 'ISO-8859-1');
    $alumnos = $cursoRow['Cupo'];
    $periodo = str_replace('.', '', $cursoRow['idperiodo']);
} else {
    error_log("❌ No se encontraron datos para curso ID: $cursos_idcursos en spre_cursos");
}

$stmtCurso->close();


        
        $query = "INSERT INTO planclases 
                 (cursos_idcursos, pcl_Periodo, pcl_tituloActividad, pcl_TipoSesion, pcl_SubTipoSesion, 
                  pcl_Fecha, pcl_Inicio, pcl_Termino, dia, pcl_condicion, pcl_ActividadConEvaluacion, 
                  pcl_HorasPresenciales, pcl_nSalas, pcl_DeseaSala, pcl_Semana, pcl_Seccion, pcl_alumnos,
                  pcl_AsiCodigo, Bloque, pcl_AsiNombre, pcl_fechamodifica, pcl_usermodifica, pcl_FechaCreacion, pcl_Modalidad, pcl_campus) 
                 VALUES 
                 (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), 'Sincrónico', 'Norte')";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
        }
        
        //$paramTypes = "issssssssssiiiisisi";
        $paramTypes = "isssssssssssiiiiissss";
        
        // DEBUG: Verificar valores justo antes del bind_param INSERT
        error_log("DEBUG BIND_PARAM INSERT - Valores finales:");
        error_log("1-cursos_idcursos: " . $cursos_idcursos);
        error_log("2-periodo: '" . $periodo . "'");
        error_log("3-titulo: '" . $titulo . "'");
        error_log("4-tipo: '" . $tipo . "'");
        error_log("5-subtipo: '" . $subtipo . "'");
        error_log("6-fecha: '" . $fecha . "'");
        error_log("7-inicio: '" . $inicio . "'");
        error_log("8-termino: '" . $termino . "'");
        error_log("9-dia: '" . $dia . "'");
        error_log("10-obligatorio: '" . $obligatorio . "'");
        error_log("11-evaluacion: '" . $evaluacion . "'");
        error_log("12-duracion: '" . $duracion . "'");
        error_log("13-nsalasCalculado: " . $nsalasCalculado);
        error_log("14-valorDeseaSala: " . $valorDeseaSala);
        error_log("15-semana: " . $semana);
        error_log("16-seccionCurso: '" . $seccionCurso . "'");
        error_log("17-alumnos: " . $alumnos);
        error_log("18-codigoCurso: '" . $codigoCurso . "'");
        error_log("19-bloque: " . $bloque);
        
        $stmt->bind_param($paramTypes, 
            $cursos_idcursos,
            $periodo,
            $titulo, 
            $tipo, 
            $subtipo,
            $fecha,
            $inicio,
            $termino,
            $dia,
            $obligatorio,
            $evaluacion,
            $duracion,
            $nsalasCalculado,
            $valorDeseaSala,
            $semana,
            $seccionCurso,
            $alumnos,
            $codigoCurso,
            $bloque,
			$nombreCurso,
			$rut
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
        }
        
        $idplanclases = $conn->insert_id;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Actividad clínica creada exitosamente',
            'idplanclases' => $idplanclases,
            'debug_info' => [
                'tipo_guardado' => $tipo,
                'subtipo_guardado' => $subtipo,
                'nsalas_calculado' => $nsalasCalculado,
                'valor_desea_sala' => $valorDeseaSala
            ]
        ]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>