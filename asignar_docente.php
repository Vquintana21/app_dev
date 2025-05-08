<?php
header('Content-Type: application/json; charset=UTF-8');
mb_internal_encoding('UTF-8');

try {
    require_once("conexion.php");

    if (!isset($conexion3)) {
        throw new Exception("No hay conexión a la base de datos");
    }

    $rut_docente = isset($_POST['rut_docente']) ? $_POST['rut_docente'] : null;
    $idcurso = isset($_POST['idcurso']) ? (int)$_POST['idcurso'] : null;
    $funcion = isset($_POST['funcion']) ? (int)$_POST['funcion'] : null;

    if (!$rut_docente || !$idcurso || !$funcion) {
        throw new Exception("Datos incompletos - RUT: $rut_docente, Curso: $idcurso, Función: $funcion");
    }

    // Obtener el departamento del docente desde spre_bancodocente
    $docente_query = "SELECT Departamento FROM spre_bancodocente WHERE rut = ?";
    $stmt = $conexion3->prepare($docente_query);
    if ($stmt === false) {
        throw new Exception("Error en preparar consulta de docente: " . $conexion3->error);
    }

    $stmt->bind_param("s", $rut_docente);
    $stmt->execute();
    $result = $stmt->get_result();
    $departamento = null;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $departamento = $row['Departamento'];
    }
    $stmt->close();

    // Verificar si ya existe el docente en el curso
    $check_query = "SELECT idProfesoresCurso FROM spre_profesorescurso 
                    WHERE rut = ? AND idcurso = ? AND Vigencia = '1' AND idTipoParticipacion NOT IN (8,10)";
    $stmt = $conexion3->prepare($check_query);
    if ($stmt === false) {
        throw new Exception("Error en preparar consulta de verificación: " . $conexion3->error);
    }

    $stmt->bind_param("si", $rut_docente, $idcurso);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        throw new Exception("El docente ya está asignado a este curso");
    }
    $stmt->close();

    // Insertar en spre_profesorescurso con la unidad académica
    $query = "INSERT INTO spre_profesorescurso 
              (rut, idcurso, idTipoParticipacion, Vigencia, FechaValidacion, 
               UsuarioValidacion, activo, unidad_academica_docente) 
              VALUES (?, ?, ?, '1', NOW(), 'sistema', '1', ?)";
              
    $stmt = $conexion3->prepare($query);
    if ($stmt === false) {
        throw new Exception("Error en preparar consulta de inserción: " . $conexion3->error);
    }
    
    $stmt->bind_param("siis", $rut_docente, $idcurso, $funcion, $departamento);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al insertar: " . $stmt->error);
    }

    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Docente asignado correctamente',
        'data' => [
            'rut' => $rut_docente,
            'curso' => $idcurso,
            'funcion' => $funcion,
            'unidad_academica' => $departamento
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en asignar_docente.php: " . $e->getMessage());
    
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'post_data' => $_POST
    ]);
}

if (isset($conexion3)) {
    $conexion3->close();
}
?>
