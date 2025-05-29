<?php
include("conexion.php");
header('Content-Type: application/json');

// Activar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Array para almacenar mensajes de depuración
$debug_messages = [];

try {
    // Agregar mensaje de depuración
    $debug_messages[] = "Iniciando proceso de guardado de actividad";

    // Verificar que existe el ID
    if (!isset($_POST['idplanclases'])) {
        throw new Exception('ID no proporcionado');
    }

    $idplanclases = (int)$_POST['idplanclases'];
    $debug_messages[] = "ID de planclases: $idplanclases";

    // Obtener y sanitizar los valores
    $titulo = isset($_POST['activity-title']) ? mysqli_real_escape_string($conn, $_POST['activity-title']) : '';
    $tipo = isset($_POST['type']) ? mysqli_real_escape_string($conn, $_POST['type']) : '';
    $subtipo = isset($_POST['subtype']) ? mysqli_real_escape_string($conn, $_POST['subtype']) : '';
    $inicio = isset($_POST['start_time']) ? mysqli_real_escape_string($conn, $_POST['start_time']) : '';
    $termino = isset($_POST['end_time']) ? mysqli_real_escape_string($conn, $_POST['end_time']) : '';
    $condicion = isset($_POST['mandatory']) && $_POST['mandatory'] === 'true' ? "Obligatorio" : "Libre";
    $evaluacion = isset($_POST['is_evaluation']) && $_POST['is_evaluation'] === 'true' ? "S" : "N";

    $debug_messages[] = "Datos recibidos: tipo='$tipo', subtipo='$subtipo'";

    // Calcular duración
    $time1 = strtotime($inicio);
    $time2 = strtotime($termino);
    $difference = $time2 - $time1;

    // Convertir a formato HH:MM:SS
    $horas = floor($difference / 3600);
    $minutos = floor(($difference % 3600) / 60);
    $segundos = $difference % 60;

    $duracion = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
    $debug_messages[] = "Duración calculada: $duracion";

    // Iniciar transacción
    $conn->begin_transaction();
    $debug_messages[] = "Transacción iniciada";

    // PRIMERO: Obtener el tipo anterior ANTES de cualquier actualización
    $queryTipoAnterior = "SELECT pcl_TipoSesion FROM planclases WHERE idplanclases = ?";
    $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
    $stmtTipoAnterior->bind_param("i", $idplanclases);
    $stmtTipoAnterior->execute();
    $resultTipoAnterior = $stmtTipoAnterior->get_result();
    $tipoAnterior = $resultTipoAnterior->fetch_assoc()['pcl_TipoSesion'];
    $stmtTipoAnterior->close();

    $debug_messages[] = "Tipo anterior (antes del UPDATE): " . $tipoAnterior;
    $debug_messages[] = "Tipo nuevo (a guardar): " . $tipo;

    // SEGUNDO: Hacer el UPDATE de planclases
    $query = "UPDATE planclases SET 
                pcl_tituloActividad = ?, 
                pcl_TipoSesion = ?,
                pcl_SubTipoSesion = ?,
                pcl_Inicio = ?,
                pcl_Termino = ?,
                pcl_condicion = ?,
                pcl_ActividadConEvaluacion = ?,
                pcl_HorasPresenciales = ?
              WHERE idplanclases = ?";

    if (!$stmt = $conn->prepare($query)) {
        throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
    }

    if (!$stmt->bind_param("ssssssssi",
        $titulo,
        $tipo,
        $subtipo,
        $inicio,
        $termino,
        $condicion,
        $evaluacion,
        $duracion,
        $idplanclases
    )) {
        throw new Exception('Error en el bind_param: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error en la ejecución: ' . $stmt->error);
    }

    $debug_messages[] = "Actividad actualizada correctamente en planclases";

    // TERCERO: Después del UPDATE, verificar si necesitamos actualizar vigencia
    if ($tipoAnterior != $tipo) {
        // Verificar si el nuevo tipo permite docentes
        $queryPermiteDocentes = "SELECT docentes FROM pcl_TipoSesion WHERE tipo_sesion = ?";
        $stmtPermiteDocentes = $conn->prepare($queryPermiteDocentes);
        $stmtPermiteDocentes->bind_param("s", $tipo);
        $stmtPermiteDocentes->execute();
        $resultPermiteDocentes = $stmtPermiteDocentes->get_result();
        $permiteDocentes = $resultPermiteDocentes->fetch_assoc()['docentes'];
        $stmtPermiteDocentes->close();

        $debug_messages[] = "Tipo cambió de '$tipoAnterior' a '$tipo'";
        $debug_messages[] = "Nuevo tipo permite docentes: " . ($permiteDocentes ? "SÍ" : "NO");

        // Si cambiamos a un tipo que NO permite docentes
        if ($permiteDocentes == 0) {
            $queryUpdateVigencia = "UPDATE docenteclases 
                                   SET vigencia = 0, 
                                       fechaModificacion = NOW(),
                                       usuarioModificacion = 'sistema'
                                   WHERE idPlanClases = ? AND vigencia = 1";
            $stmtUpdateVigencia = $conn->prepare($queryUpdateVigencia);
            $stmtUpdateVigencia->bind_param("i", $idplanclases);

            $debug_messages[] = "Ejecutando UPDATE en docenteclases: $queryUpdateVigencia con idPlanClases=$idplanclases";

            if (!$stmtUpdateVigencia->execute()) {
                $debug_messages[] = "Error al actualizar vigencia de docentes: " . $stmtUpdateVigencia->error;
            } else {
                $docentesAfectados = $conn->affected_rows;
                $debug_messages[] = "Docentes desactivados: " . $docentesAfectados;
            }
            $stmtUpdateVigencia->close();
        } else {
            $debug_messages[] = "Cambio a tipo que permite docentes - Panel disponible para asignación";
        }
    } else {
        $debug_messages[] = "Tipo no cambió - No se modifica vigencia de docentes";
    }
	
    // ## SALAS ##
    // Verificar si el tipo de actividad requiere sala consultando la tabla pcl_TipoSesion
    $requiereSala = false;
    $mensajeUsuario = ''; // Variable para almacenar mensaje específico para el usuario
    $necesitaConfirmacion = false; // Para determinar si se necesita confirmar el cambio
    $mensajeConfirmacion = ''; // Mensaje para SweetAlert

    // Primero intentamos con tipo y subtipo (si existe)
    if (!empty($subtipo)) {
        $queryTipoSesion = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND Sub_tipo_sesion = ?";
        $stmtTipo = $conn->prepare($queryTipoSesion);
        $stmtTipo->bind_param("ss", $tipo, $subtipo);
        $stmtTipo->execute();
        $resultTipo = $stmtTipo->get_result();

        if ($row = $resultTipo->fetch_assoc()) {
            $requiereSala = ($row['pedir_sala'] == 1);
            $debug_messages[] = "Consulta con tipo y subtipo - pedir_sala: " . $row['pedir_sala'];
        } else {
            $debug_messages[] = "No se encontró coincidencia con tipo='$tipo' y subtipo='$subtipo'";
        }
        $stmtTipo->close();
    }

    // Si no se encontró con subtipo o no hay subtipo, buscar solo por tipo
    if (!$requiereSala && empty($subtipo)) {
        $queryTipoSesion = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ? AND (Sub_tipo_sesion IS NULL OR Sub_tipo_sesion = '')";
        $stmtTipo = $conn->prepare($queryTipoSesion);
        $stmtTipo->bind_param("s", $tipo);
        $stmtTipo->execute();
        $resultTipo = $stmtTipo->get_result();

        if ($row = $resultTipo->fetch_assoc()) {
            $requiereSala = ($row['pedir_sala'] == 1);
            $debug_messages[] = "Consulta solo con tipo - pedir_sala: " . $row['pedir_sala'];
        } else {
            $debug_messages[] = "No se encontró coincidencia con tipo='$tipo' sin subtipo";
        }
        $stmtTipo->close();
    }

    // Verificar si el tipo anterior requería sala
    $anteriorRequiereSala = false;
    $queryTipoAnterior = "SELECT pedir_sala FROM pcl_TipoSesion WHERE tipo_sesion = ?";
    $stmtTipoAnterior = $conn->prepare($queryTipoAnterior);
    $stmtTipoAnterior->bind_param("s", $tipoAnterior);
    $stmtTipoAnterior->execute();
    $resultTipoAnterior = $stmtTipoAnterior->get_result();
    if ($rowAnterior = $resultTipoAnterior->fetch_assoc()) {
        $anteriorRequiereSala = ($rowAnterior['pedir_sala'] == 1);
    }
    $stmtTipoAnterior->close();

    $debug_messages[] = "¿Requiere sala? " . ($requiereSala ? "SÍ" : "NO");
    $debug_messages[] = "Tipo de actividad: " . $tipo;
    $debug_messages[] = "Tipo anterior: " . $tipoAnterior;
    $debug_messages[] = "¿Tipo anterior requería sala? " . ($anteriorRequiereSala ? "SÍ" : "NO");

    // Actualizar explícitamente el campo pcl_DeseaSala en planclases
    $queryUpdateDeseaSala = "UPDATE planclases SET pcl_DeseaSala = ? WHERE idplanclases = ?";
    $stmtUpdateDeseaSala = $conn->prepare($queryUpdateDeseaSala);
    $valorDeseaSala = $requiereSala ? 1 : 0;
    $stmtUpdateDeseaSala->bind_param("ii", $valorDeseaSala, $idplanclases);
    $stmtUpdateDeseaSala->execute();
    $stmtUpdateDeseaSala->close();
    $debug_messages[] = "Actualizado pcl_DeseaSala = $valorDeseaSala en planclases";

    // Verificar estados actuales de las asignaciones
    $queryEstados = "SELECT idEstado, COUNT(*) as cantidad 
                    FROM asignacion_piloto 
                    WHERE idplanclases = ? 
                    GROUP BY idEstado";
    $stmtEstados = $conn->prepare($queryEstados);
    $stmtEstados->bind_param("i", $idplanclases);
    $stmtEstados->execute();
    $resultEstados = $stmtEstados->get_result();

    $estados = [0 => 0, 1 => 0, 3 => 0, 4 => 0]; // Inicializar contadores por estado
    $totalAsignaciones = 0;

    while ($estado = $resultEstados->fetch_assoc()) {
        $estados[$estado['idEstado']] = $estado['cantidad'];
        $totalAsignaciones += $estado['cantidad'];
    }
    $stmtEstados->close();

    $asignacionesPendientes = $estados[0]; // Solicitadas
    $asignacionesModificadas = $estados[1]; // En modificación
    $asignacionesConfirmadas = $estados[3]; // Asignadas/Reservadas
    $asignacionesLiberadas = $estados[4]; // Liberadas

    $debug_messages[] = "Estado actual: Pendientes=$asignacionesPendientes, Modificadas=$asignacionesModificadas, Confirmadas=$asignacionesConfirmadas, Liberadas=$asignacionesLiberadas";

    // MANEJO DE LOS 9 CASOS SEGÚN TABLA 1
    if ($tipoAnterior === $tipo) {
        // Sin cambio de tipo (casos 1, 5, 9)
        if ($tipo === 'Clase') {
            // CASO 1: De Clase a Clase
            $debug_messages[] = "CASO 1: De Clase a Clase";
            
            // Si hay asignaciones confirmadas, cambiarlas a estado "modificada"
            if ($asignacionesConfirmadas > 0) {
                $queryModificar = "UPDATE asignacion_piloto 
                                SET idEstado = 1, 
                                    Comentario = CONCAT(IFNULL(Comentario, ''), '\n', NOW(), ' - Datos de actividad modificados')
                                WHERE idplanclases = ? AND idEstado = 3";
                $stmtModificar = $conn->prepare($queryModificar);
                $stmtModificar->bind_param("i", $idplanclases);
                $stmtModificar->execute();
                $filasModificadas = $stmtModificar->affected_rows;
                $stmtModificar->close();
                
                $debug_messages[] = "Asignaciones confirmadas cambiadas a estado 'modificada': $filasModificadas";
                $mensajeUsuario = 'Se ha solicitado modificación de la sala asignada';
                
                // Se requiere confirmación ya que afecta salas asignadas
                $necesitaConfirmacion = true;
                $mensajeConfirmacion = "Este cambio solicitará una modificación de la sala asignada. La reserva actual será cancelada hasta que se asigne una nueva sala. ¿Desea continuar?";
            } else if ($totalAsignaciones == 0) {
                // Sin asignaciones, crear una automáticamente
                crearAsignacionAutomatica($conn, $idplanclases, $debug_messages);
                $mensajeUsuario = 'Asignación automática';
            } else {
                $debug_messages[] = "Se mantienen asignaciones existentes en estado actual";
            }
        } else if ($requiereSala) {
            // CASO 5: De AG/TP/EV/EX a AG/TP/EV/EX
            $debug_messages[] = "CASO 5: De otro tipo con sala a otro tipo con sala";
            
            // Actualizar tipo en asignaciones existentes sin cambiar estado
            $queryActualizarTipo = "UPDATE asignacion_piloto 
                                SET tipoSesion = ?
                                WHERE idplanclases = ? AND idEstado IN (0, 1, 3)";
            $stmtActualizarTipo = $conn->prepare($queryActualizarTipo);
            $stmtActualizarTipo->bind_param("si", $tipo, $idplanclases);
            $stmtActualizarTipo->execute();
            $filasActualizadas = $stmtActualizarTipo->affected_rows;
            $stmtActualizarTipo->close();
            
            $debug_messages[] = "Asignaciones actualizadas con nuevo tipo: $filasActualizadas";
        } else {
            // CASO 9: De VT/SA/TA a VT/SA/TA
            $debug_messages[] = "CASO 9: De tipo sin sala a tipo sin sala";
            // No se requiere acción, solo confirmar que no hay sala
            $mensajeUsuario = 'Sin sala';
        }
    } else {
        // Con cambio de tipo
        if ($tipo === 'Clase') {
            if ($anteriorRequiereSala) {
                // CASO 4: De AG/TP/EV/EX a Clase
                $debug_messages[] = "CASO 4: De otro tipo con sala a Clase";
                
                // Si hay salas asignadas, necesitamos confirmación
                if ($asignacionesConfirmadas > 0) {
                    $necesitaConfirmacion = true;
                    $mensajeConfirmacion = "Al cambiar a tipo 'Clase', se liberarán las salas actuales y se creará una asignación automática. ¿Desea continuar?";
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
                    $liberadas = $stmtLiberarExistentes->affected_rows;
                    $stmtLiberarExistentes->close();
                    $debug_messages[] = "Reservas liberadas por cambio a tipo Clase: $liberadas";
                }
                
                // Eliminar asignaciones en proceso
                $queryEliminarEnProceso = "DELETE FROM asignacion_piloto 
                                        WHERE idplanclases = ? AND idEstado IN (0, 1)";
                $stmtEliminarEnProceso = $conn->prepare($queryEliminarEnProceso);
                $stmtEliminarEnProceso->bind_param("i", $idplanclases);
                $stmtEliminarEnProceso->execute();
                $eliminadas = $stmtEliminarEnProceso->affected_rows;
                $stmtEliminarEnProceso->close();
                $debug_messages[] = "Asignaciones en proceso eliminadas: $eliminadas";
                
                // Crear nueva asignación automática
                crearAsignacionAutomatica($conn, $idplanclases, $debug_messages);
                $mensajeUsuario = 'Asignación automática';
                
            } else {
                // CASO 7: De VT/SA/TA a Clase
                $debug_messages[] = "CASO 7: De tipo sin sala a Clase";
                
                // Crear nueva asignación automática
                crearAsignacionAutomatica($conn, $idplanclases, $debug_messages);
                $mensajeUsuario = 'Asignación automática';
            }
        } else if ($requiereSala) {
            if ($tipoAnterior === 'Clase') {
                // CASO 2: De Clase a AG/TP/EV/EX
                $debug_messages[] = "CASO 2: De Clase a otro tipo con sala";
                
                // Si hay salas asignadas/reservadas, necesitamos confirmación
                if ($asignacionesConfirmadas > 0 || $asignacionesPendientes > 0 || $asignacionesModificadas > 0) {
                    $necesitaConfirmacion = true;
                    $mensajeConfirmacion = "Al cambiar de 'Clase' a otro tipo de actividad, se eliminarán las asignaciones automáticas y deberá solicitar sala manualmente. ¿Desea continuar?";
                }
                
                // Eliminar todas las asignaciones (incluidas reservas)
                $queryEliminarTodas = "DELETE FROM asignacion_piloto WHERE idplanclases = ?";
                $stmtEliminarTodas = $conn->prepare($queryEliminarTodas);
                $stmtEliminarTodas->bind_param("i", $idplanclases);
                $stmtEliminarTodas->execute();
                $eliminadas = $stmtEliminarTodas->affected_rows;
                $stmtEliminarTodas->close();
                $debug_messages[] = "Asignaciones eliminadas por cambio de Clase a tipo con sala: $eliminadas";
                
                $mensajeUsuario = 'Debe solicitar sala manualmente';
                
            } else {
                // CASO 8: De VT/SA/TA a AG/TP/EV/EX
                $debug_messages[] = "CASO 8: De tipo sin sala a otro tipo con sala";
                
                // No crear asignación automática, solo informar
                $mensajeUsuario = 'Debe solicitar sala';
            }
        } else {
            if ($tipoAnterior === 'Clase') {
                // CASO 3: De Clase a VT/SA/TA
                $debug_messages[] = "CASO 3: De Clase a tipo sin sala";
                
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
                $eliminadas = $stmtEliminarTodas->affected_rows;
                $stmtEliminarTodas->close();
                $debug_messages[] = "Asignaciones eliminadas por cambio de Clase a tipo sin sala: $eliminadas";
                
                $mensajeUsuario = 'Sin sala';
                
            } else {
                // CASO 6: De AG/TP/EV/EX a VT/SA/TA
                $debug_messages[] = "CASO 6: De otro tipo con sala a tipo sin sala";
                
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
                $eliminadas = $stmtEliminarTodas->affected_rows;
                $stmtEliminarTodas->close();
                $debug_messages[] = "Asignaciones eliminadas por cambio a tipo sin sala: $eliminadas";
                
                $mensajeUsuario = 'Sin sala';
            }
        }
    }

    $conn->commit();
    $debug_messages[] = "Transacción confirmada con éxito";

    echo json_encode([
        'success' => true,
        'message' => 'Actividad actualizada exitosamente',
        'mensaje_sala' => $mensajeUsuario, // Mensaje específico sobre sala
        'requiere_sala' => $requiereSala,
        'es_clase' => ($tipo === 'Clase'),
        'necesita_confirmacion' => $necesitaConfirmacion,
        'mensaje_confirmacion' => $mensajeConfirmacion,
        'debug' => $debug_messages
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Agregar mensaje de error
    $debug_messages[] = "ERROR: " . $e->getMessage();

    // Revertir cambios en caso de error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
        $debug_messages[] = "Transacción revertida";
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debug_messages
    ]);

    if (isset($conn)) {
        $conn->close();
    }
}

// Función auxiliar para crear asignación automática
function crearAsignacionAutomatica($conn, $idplanclases, &$debug_messages) {
    // Obtener datos completos de planclases
    $queryPlanclases = "SELECT * FROM planclases WHERE idplanclases = ?";
    $stmtPlanclases = $conn->prepare($queryPlanclases);
    $stmtPlanclases->bind_param("i", $idplanclases);
    $stmtPlanclases->execute();
    $resultPlanclases = $stmtPlanclases->get_result();

    if ($resultPlanclases->num_rows == 0) {
        $debug_messages[] = "ERROR: No se encontraron datos en planclases para ID $idplanclases";
        return false;
    }

    $dataPlanclases = $resultPlanclases->fetch_assoc();
    $stmtPlanclases->close();

    // Obtener usuario actual o usar valor por defecto
    $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'sistema';
    $fecha_actualizacion = date('Y-m-d H:i:s');

    // Crear registro en asignacion_piloto
    $queryInsertAsignacion = "INSERT INTO asignacion_piloto (
        idplanclases, idSala, capacidadSala, nAlumnos, tipoSesion, campus,
        fecha, hora_inicio, hora_termino, idCurso, CodigoCurso, Seccion,
        NombreCurso, Comentario, cercania, TipoAsignacion, idEstado, Usuario, timestamp
    ) VALUES (
        ?, '', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        'Solicitud generada automáticamente', 0, 'M', 0, ?, ?
    )";

    $stmtInsertAsignacion = $conn->prepare($queryInsertAsignacion);
    if (!$stmtInsertAsignacion) {
        $debug_messages[] = "ERROR preparando consulta de inserción: " . $conn->error;
        return false;
    }

    $stmtInsertAsignacion->bind_param(
        "iisssssssisss",
        $idplanclases,
        $dataPlanclases['pcl_alumnos'],
        $dataPlanclases['pcl_TipoSesion'],
        $dataPlanclases['pcl_campus'],
        $dataPlanclases['pcl_Fecha'],
        $dataPlanclases['pcl_Inicio'],
        $dataPlanclases['pcl_Termino'],
        $dataPlanclases['cursos_idcursos'],
        $dataPlanclases['pcl_AsiCodigo'],
        $dataPlanclases['pcl_Seccion'],
        $dataPlanclases['pcl_AsiNombre'],
        $usuario,
        $fecha_actualizacion
    );

    if (!$stmtInsertAsignacion->execute()) {
        $debug_messages[] = "ERROR ejecutando inserción: " . $stmtInsertAsignacion->error;
        return false;
    }

    $debug_messages[] = "Asignación creada automáticamente";
    $stmtInsertAsignacion->close();
    return true;
}
?>