<?php
// consultar_computacion.php
include("conexion.php");
header('Content-Type: application/json');



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'])) {
        throw new Exception('Acción no especificada');
    }
    
    switch ($data['action']) {
        case 'consultar_disponibilidad':
            consultarDisponibilidad($conn, $data);
            break;
            
        case 'validar_antes_guardar':
            validarAntesGuardar($conn, $data);
            break;
            
        default:
            throw new Exception('Acción no reconocida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function consultarDisponibilidad($conn, $data) {
    // Validar parámetros requeridos
    $requiredParams = ['campus', 'fecha', 'hora_inicio', 'hora_fin', 'n_salas', 'total_alumnos'];
    foreach ($requiredParams as $param) {
        if (!isset($data[$param]) || empty($data[$param])) {
            throw new Exception("Parámetro requerido faltante: $param");
        }
    }
    
    $campus = $data['campus'];
    $fecha = $data['fecha'];
    $horaInicio = $data['hora_inicio'];
    $horaFin = $data['hora_fin'];
    $nSalas = (int)$data['n_salas'];
    $totalAlumnos = (int)$data['total_alumnos'];
    
    // Validaciones básicas
    if ($campus !== 'Norte') {
        echo json_encode([
            'success' => true,
            'mostrar_seccion' => false,
            'mensaje' => 'Las salas de computación solo están disponibles en Campus Norte'
        ]);
        return;
    }
    
    if ($nSalas < 1 || $nSalas > 2) {
        echo json_encode([
            'success' => true,
            'mostrar_seccion' => false,
            'mensaje' => 'Las salas de computación solo se pueden reservar para 1 o 2 salas'
        ]);
        return;
    }
    
    if ($totalAlumnos <= 0) {
        throw new Exception('El número de alumnos debe ser mayor a 0');
    }
    
    // Obtener salas de computación
    $querySalas = "SELECT `idSala`, `sa_UbicCampus`, `sa_Capacidad` 
                   FROM `sala_reserva` 
                   WHERE `idSala` IN ('computacion1', 'computacion2') 
                   AND `sa_UbicCampus` = ?";
    
    $stmtSalas = $conn->prepare($querySalas);
    $stmtSalas->bind_param("s", $campus);
    $stmtSalas->execute();
    $resultSalas = $stmtSalas->get_result();
    
    $salasComputacion = [];
    while ($sala = $resultSalas->fetch_assoc()) {
        $salasComputacion[] = $sala;
    }
    $stmtSalas->close();
    
    if (empty($salasComputacion)) {
        echo json_encode([
            'success' => true,
            'mostrar_seccion' => false,
            'mensaje' => 'No hay salas de computación disponibles en este campus'
        ]);
        return;
    }
    
    // Verificar disponibilidad de cada sala
    $salasDisponibles = [];
    foreach ($salasComputacion as $sala) {
        if (estaDisponible($conn, $sala['idSala'], $fecha, $horaInicio, $horaFin)) {
            $salasDisponibles[] = $sala;
        }
    }
    
    if (empty($salasDisponibles)) {
        echo json_encode([
            'success' => true,
            'mostrar_seccion' => true,
            'opciones_disponibles' => false,
            'mensaje' => 'Las salas de computación no están disponibles para este horario'
        ]);
        return;
    }
    
    // Generar opciones según número de salas pedidas
    $opciones = generarOpciones($salasDisponibles, $nSalas, $totalAlumnos);
    
    echo json_encode([
        'success' => true,
        'mostrar_seccion' => true,
        'opciones_disponibles' => !empty($opciones),
        'opciones' => $opciones,
        'mensaje' => empty($opciones) ? 'Las salas de computación disponibles no tienen suficiente capacidad' : ''
    ]);
}

function validarAntesGuardar($conn, $data) {
    $salasSeleccionadas = $data['salas_seleccionadas'];
    $fecha = $data['fecha'];
    $horaInicio = $data['hora_inicio'];
    $horaFin = $data['hora_fin'];
    
    $salasNoDisponibles = [];
    
    foreach ($salasSeleccionadas as $idSala) {
        if (!estaDisponible($conn, $idSala, $fecha, $horaInicio, $horaFin)) {
            $salasNoDisponibles[] = $idSala;
        }
    }
    
    if (!empty($salasNoDisponibles)) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'Las siguientes salas ya no están disponibles: ' . implode(', ', $salasNoDisponibles),
            'salas_no_disponibles' => $salasNoDisponibles
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'mensaje' => 'Todas las salas seleccionadas están disponibles'
        ]);
    }
}

function estaDisponible($conn, $idSala, $fecha, $horaInicio, $horaFin) {
    $queryReserva = "SELECT * FROM reserva 
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

function generarOpciones($salasDisponibles, $nSalas, $totalAlumnos) {
    $opciones = [];
    
    if ($nSalas === 1) {
        // Para 1 sala: mostrar salas individuales que puedan albergar todos los alumnos
        foreach ($salasDisponibles as $sala) {
            if ($sala['sa_Capacidad'] >= $totalAlumnos) {
                $opciones[] = [
                    'tipo' => 'individual',
                    'id_sala' => $sala['idSala'],
                    'nombre' => ucfirst($sala['idSala']),
                    'capacidad' => $sala['sa_Capacidad'],
                    'alumnos_asignados' => $totalAlumnos,
                    'descripcion' => "Sala " . ucfirst($sala['idSala']) . " (Capacidad: {$sala['sa_Capacidad']} - Alumnos: {$totalAlumnos})"
                ];
            }
        }
    } elseif ($nSalas === 2) {
        // Para 2 salas: solo opción "ambas" si juntas pueden albergar todos los alumnos
        if (count($salasDisponibles) >= 2) {
            $capacidadTotal = array_sum(array_column($salasDisponibles, 'sa_Capacidad'));
            
            if ($capacidadTotal >= $totalAlumnos) {
                $nombresSalas = array_map(function($sala) {
                    return ucfirst($sala['idSala']);
                }, $salasDisponibles);
                
                $opciones[] = [
                    'tipo' => 'ambas',
                    'id_sala_multiple' => array_column($salasDisponibles, 'idSala'),
                    'nombre' => 'Ambas salas de computación',
                    'capacidad_total' => $capacidadTotal,
                    'alumnos_asignados' => $totalAlumnos,
                    'descripcion' => "Ambas salas (" . implode(' + ', $nombresSalas) . ") - Capacidad total: {$capacidadTotal} - Alumnos: {$totalAlumnos}",
                    'salas_detalle' => $salasDisponibles
                ];
            }
        }
    }
    
    return $opciones;
}

if (isset($conn)) {
    $conn->close();
}
?>