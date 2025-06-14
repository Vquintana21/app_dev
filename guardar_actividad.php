<?php

include("conexion.php");
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
   if (!isset($_POST['idplanclases'])) {
       throw new Exception('ID no proporcionado');
   }

   $idplanclases = (int)$_POST['idplanclases'];

   // Obtener y sanitizar los valores
   $titulo = isset($_POST['activity-title']) ? mysqli_real_escape_string($conn, $_POST['activity-title']) : '';
   $tipo = isset($_POST['type']) ? mysqli_real_escape_string($conn, $_POST['type']) : '';
   $subtipo = isset($_POST['subtype']) ? mysqli_real_escape_string($conn, $_POST['subtype']) : '';
   $inicio = isset($_POST['start_time']) ? mysqli_real_escape_string($conn, $_POST['start_time']) : '';
   $termino = isset($_POST['end_time']) ? mysqli_real_escape_string($conn, $_POST['end_time']) : '';
   $condicion = isset($_POST['mandatory']) && $_POST['mandatory'] === 'true' ? "Obligatorio" : "Libre";
   $evaluacion = isset($_POST['is_evaluation']) && $_POST['is_evaluation'] === 'true' ? "S" : "N";

   // Calcular duración
   $time1 = strtotime($inicio);
   $time2 = strtotime($termino);
   $difference = $time2 - $time1;

   $horas = floor($difference / 3600);
   $minutos = floor(($difference % 3600) / 60);
   $segundos = $difference % 60;

   $duracion = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);

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
    * CÁLCULO DINÁMICO DE pcl_nSalas SEGÚN TIPO DE ACTIVIDAD
    * 
    * Reglas específicas para el campo pcl_nSalas según el tipo de actividad:
    * - CLASE: siempre pcl_nSalas = 1, pcl_DeseaSala=1
    * - Actividades que requieren sala (AG/TP/EV/EX): mantener si > 1, sino = 1, pcl_DeseaSala=1
    * - Actividades que NO requieren sala (PC/SA/TA/VT): nsalas = 0, pcl_DeseaSala=0
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

   // Aplicar reglas específicas de negocio para pcl_nSalas
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
               pcl_Inicio = ?,
               pcl_Termino = ?,
               pcl_condicion = ?,
               pcl_ActividadConEvaluacion = ?,
               pcl_HorasPresenciales = ?,
               pcl_nSalas = ?
             WHERE idplanclases = ?";

   if (!$stmt = $conn->prepare($query)) {
       throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
   }

   if (!$stmt->bind_param("ssssssssii",
       $titulo,
       $tipo,
       $subtipo,
       $inicio,
       $termino,
       $condicion,
       $evaluacion,
       $duracion,
       $nsalasCalculado,
       $idplanclases
   )) {
       throw new Exception('Error en el bind_param: ' . $stmt->error);
   }

   if (!$stmt->execute()) {
       throw new Exception('Error en la ejecución: ' . $stmt->error);
   }

   // Verificar si necesitamos actualizar vigencia de docentes
   if ($tipoAnterior !== $tipo) {
    // Obtener si tipo anterior permitía docentes
    $permiteDocentesAnterior = 1;
    $stmtDocentesAnt = $conn->prepare("SELECT docentes FROM pcl_TipoSesion WHERE tipo_sesion = ? AND tipo_activo = 1");
    $stmtDocentesAnt->bind_param("s", $tipoAnterior);
    $stmtDocentesAnt->execute();
    $resAnt = $stmtDocentesAnt->get_result();
    if ($rowAnt = $resAnt->fetch_assoc()) {
        $permiteDocentesAnterior = (int)$rowAnt['docentes'];
    }
    $stmtDocentesAnt->close();

    // Obtener si tipo nuevo permite docentes
    $permiteDocentesNuevo = 1;
    $stmtDocentesNuevo = $conn->prepare("SELECT docentes FROM pcl_TipoSesion WHERE tipo_sesion = ? AND tipo_activo = 1");
    $stmtDocentesNuevo->bind_param("s", $tipo);
    $stmtDocentesNuevo->execute();
    $resNuevo = $stmtDocentesNuevo->get_result();
    if ($rowNuevo = $resNuevo->fetch_assoc()) {
        $permiteDocentesNuevo = (int)$rowNuevo['docentes'];
    }
    $stmtDocentesNuevo->close();

    // Solo si el tipo anterior permitía docentes y el nuevo NO
    if ($permiteDocentesAnterior === 1 && $permiteDocentesNuevo === 0) {
        $queryUpdateVigencia = "UPDATE docenteclases 
                                SET vigencia = 0, 
                                    fechaModificacion = NOW(),
                                    usuarioModificacion = 'sistema'
                                WHERE idPlanClases = ? AND vigencia = 1";
        $stmtUpdateVigencia = $conn->prepare($queryUpdateVigencia);
        $stmtUpdateVigencia->bind_param("i", $idplanclases);
        $stmtUpdateVigencia->execute();
        $stmtUpdateVigencia->close();
    }
}

   
   /* 
    * GESTIÓN DE SALAS SEGÚN TIPO DE ACTIVIDAD
    * Maneja la lógica de salas y asignaciones según el tipo de actividad.
    * Se actualiza pcl_DeseaSala y se aplican los 9 casos de transición entre tipos.
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
                   FROM asignacion_piloto 
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
    * IMPLEMENTACIÓN DE LOS 9 CASOS DE TRANSICIÓN ENTRE TIPOS DE ACTIVIDAD
    * 
    * Los 9 casos están documentados según los requerimientos del negocio:
    * - Casos 1, 5, 9: Sin cambio de tipo
    * - Casos 2, 3, 4, 6, 7, 8: Con cambio de tipo
    */
   
   if ($tipoAnterior === $tipo) {
       // SIN CAMBIO DE TIPO (casos 1, 5, 9)
       if ($tipo === 'Clase') {
           /* CASO 1: CLASE → CLASE (actualización de parámetros de la misma clase) */
           
           // Si hay asignaciones confirmadas, cambiarlas a estado "modificada"
           if ($asignacionesConfirmadas > 0) {
               $queryModificar = "UPDATE asignacion_piloto 
                               SET idEstado = 1, 
                                   Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Datos de actividad modificados')
                               WHERE idplanclases = ? AND idEstado = 3";
               $stmtModificar = $conn->prepare($queryModificar);
               $stmtModificar->bind_param("i", $idplanclases);
               $stmtModificar->execute();
               $stmtModificar->close();
               
               $mensajeUsuario = 'Se ha solicitado modificación de la sala asignada';
               $necesitaConfirmacion = true;
               $mensajeConfirmacion = "Este cambio solicitará una modificación de la sala asignada. La reserva actual será cancelada hasta que se asigne una nueva sala. ¿Desea continuar?";
           } else {
               $mensajeUsuario = 'Actividad actualizada - CRON gestionará asignación';
           }
       } else if ($requiereSala) {
           /* CASO 5: ACTIVIDAD GRUPAL/TP/EV/EX → ACTIVIDAD GRUPAL/TP/EV/EX */
           
           // Actualizar tipo en asignaciones existentes sin cambiar estado
           $queryActualizarTipo = "UPDATE asignacion_piloto 
                               SET tipoSesion = ?
                               WHERE idplanclases = ? AND idEstado IN (0, 1, 3)";
           $stmtActualizarTipo = $conn->prepare($queryActualizarTipo);
           $stmtActualizarTipo->bind_param("si", $tipo, $idplanclases);
           $stmtActualizarTipo->execute();
           $stmtActualizarTipo->close();
           
           $mensajeUsuario = 'Tipo de sesión actualizado en asignaciones existentes';
       } else {
           /* CASO 9: ACTIVIDAD SIN SALA → ACTIVIDAD SIN SALA */
           $mensajeUsuario = 'Actividad actualizada - Sin sala requerida';
       }
   } else {
       // CON CAMBIO DE TIPO (casos 2, 3, 4, 6, 7, 8)
       if ($tipo === 'Clase') {
           if ($anteriorRequiereSala) {
               /* CASO 4: ACTIVIDAD GRUPAL/TP/EV/EX → CLASE */
               
               // Actualizar tipo a 'Clase' en asignaciones existentes
               if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                   $queryActualizarTipo = "UPDATE asignacion_piloto 
                                          SET tipoSesion = 'Clase',
                                              Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Tipo cambiado a Clase')
                                          WHERE idplanclases = ? AND idEstado IN (0, 1, 3)";
                   $stmtActualizarTipo = $conn->prepare($queryActualizarTipo);
                   $stmtActualizarTipo->bind_param("i", $idplanclases);
                   $stmtActualizarTipo->execute();
                   $stmtActualizarTipo->close();
               }
               
               // Si hay salas asignadas, necesitamos confirmación
               if ($asignacionesConfirmadas > 0) {
                   $necesitaConfirmacion = true;
                   $mensajeConfirmacion = "Al cambiar a tipo 'Clase', se liberarán las salas actuales y el CRON creará una asignación automática. ¿Desea continuar?";
               }
               
               // Liberar asignaciones existentes (reservas) si hay
               if ($asignacionesConfirmadas > 0) {
                   $queryLiberarExistentes = "UPDATE asignacion_piloto 
                                           SET idEstado = 4, 
                                               Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Liberada por cambio a tipo Clase')
                                           WHERE idplanclases = ? AND idEstado = 3";
                   $stmtLiberarExistentes = $conn->prepare($queryLiberarExistentes);
                   $stmtLiberarExistentes->bind_param("i", $idplanclases);
                   $stmtLiberarExistentes->execute();
                   $stmtLiberarExistentes->close();
               }
               
               // Eliminar asignaciones en proceso
               $queryEliminarEnProceso = "DELETE FROM asignacion_piloto 
                                       WHERE idplanclases = ? AND idEstado IN (0, 1)";
               $stmtEliminarEnProceso = $conn->prepare($queryEliminarEnProceso);
               $stmtEliminarEnProceso->bind_param("i", $idplanclases);
               $stmtEliminarEnProceso->execute();
               $stmtEliminarEnProceso->close();
               
               $mensajeUsuario = 'Cambiado a Clase - CRON gestionará asignación automática';
               
           } else {
               /* CASO 7: ACTIVIDAD SIN SALA → CLASE */
               $mensajeUsuario = 'Cambiado a Clase - CRON gestionará asignación automática';
           }
       } else if ($requiereSala) {
           if ($tipoAnterior === 'Clase') {
               /* CASO 2: CLASE → ACTIVIDAD GRUPAL/TP/EV/EX */
               
               // Actualizar tipo en asignaciones existentes (NO eliminar)
               if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                   $queryActualizarTipo = "UPDATE asignacion_piloto 
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
               
               // Eliminar todas las asignaciones (incluidas reservas)
               $queryEliminarTodas = "DELETE FROM asignacion_piloto WHERE idplanclases = ?";
               $stmtEliminarTodas = $conn->prepare($queryEliminarTodas);
               $stmtEliminarTodas->bind_param("i", $idplanclases);
               $stmtEliminarTodas->execute();
               $stmtEliminarTodas->close();
               
               $mensajeUsuario = 'Actividad no requiere sala - Asignaciones eliminadas';
               
           } else {
               /* CASO 6: ACTIVIDAD GRUPAL/TP/EV/EX → ACTIVIDAD SIN SALA */
               
               // Si hay salas asignadas, necesitamos confirmación
               if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                   $necesitaConfirmacion = true;
                   $mensajeConfirmacion = "Al cambiar a un tipo sin sala, se eliminarán todas las asignaciones existentes. ¿Desea continuar?";
               }
               
               // Eliminar todas las asignaciones (incluidas reservas)
               $queryEliminarTodas = "DELETE FROM asignacion_piloto WHERE idplanclases = ?";
               $stmtEliminarTodas = $conn->prepare($queryEliminarTodas);
               $stmtEliminarTodas->bind_param("i", $idplanclases);
               $stmtEliminarTodas->execute();
               $stmtEliminarTodas->close();
               
               $mensajeUsuario = 'Actividad no requiere sala - Asignaciones eliminadas';
           }
       }
   }

   $conn->commit();

   echo json_encode([
       'success' => true,
       'message' => 'Actividad actualizada exitosamente',
       'mensaje_sala' => $mensajeUsuario,
       'requiere_sala' => $requiereSala,
       'es_clase' => ($tipo === 'Clase'),
       'necesita_confirmacion' => $necesitaConfirmacion,
       'mensaje_confirmacion' => $mensajeConfirmacion
   ]);

   $stmt->close();
   $conn->close();

} catch (Exception $e) {
   if (isset($conn) && $conn->ping()) {
       $conn->rollback();
   }

   echo json_encode([
       'success' => false,
       'message' => $e->getMessage()
   ]);

   if (isset($conn)) {
       $conn->close();
   }
}
?>