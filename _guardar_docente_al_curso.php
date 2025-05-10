<?php
// guardar_docente_al_curso.php
header('Content-Type: application/json');
include("conexion.php");

try {
    $idcurso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $rut_docente = isset($_POST['rut_docente']) ? $_POST['rut_docente'] : '';
    $funcion = isset($_POST['funcion']) ? $_POST['funcion'] : '';
    
    // Procesar RUT
    $rut_numerico = preg_replace('/[^0-9]/', '', $rut_docente);
    if (strlen($rut_numerico) > 9) {
        $rut_sin_dv = substr($rut_numerico, 0, -1);
    } else {
        $rut_sin_dv = $rut_numerico;
    }
    $rut_formateado = str_pad($rut_sin_dv, 10, "0", STR_PAD_LEFT);
    
    // Verificar si el docente ya está asignado al curso
    $queryVerificar = "SELECT * FROM spre_profesorescurso 
                      WHERE idcurso = ? AND rut = ? AND Vigencia = '1'";
    $stmtVerificar = $conexion3->prepare($queryVerificar);
    $stmtVerificar->bind_param("ss", $idcurso, $rut_formateado);
    $stmtVerificar->execute();
    $resultado = $stmtVerificar->get_result();
    
    if ($resultado->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'El docente ya está asignado a este curso'
        ]);
        exit;
    }
    
    // Insertar el docente en el curso
    $queryInsertar = "INSERT INTO spre_profesorescurso (idcurso, rut, idTipoParticipacion, Vigencia) 
                     VALUES (?, ?, ?, '1')";
    $stmtInsertar = $conexion3->prepare($queryInsertar);
    $stmtInsertar->bind_param("ssi", $idcurso, $rut_formateado, $funcion);
    
    if ($stmtInsertar->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Docente asignado al curso correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al asignar docente al curso'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>