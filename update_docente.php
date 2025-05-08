<?php
include("conexion.php");

// Recibir datos
$data = json_decode(file_get_contents('php://input'), true);
$rut = $data['rut'];
$idplanclases = $data['idplanclases'];
$checked = $data['checked'];
$replicateAll = $data['replicateAll'];

try {
    $conn->begin_transaction();

    if($replicateAll) {
        // Obtener todas las actividades del mismo curso
        $query = "SELECT idplanclases FROM planclases WHERE cursos_idcursos = (SELECT cursos_idcursos FROM planclases WHERE idplanclases = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $idplanclases);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            if($checked) {
                // Insertar docente en cada actividad
                $insert = "INSERT IGNORE INTO planclases_docentes (idplanclases, rut) VALUES (?, ?)";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("is", $row['idplanclases'], $rut);
                $stmt->execute();
            } else {
                // Eliminar docente de cada actividad
                $delete = "DELETE FROM planclases_docentes WHERE idplanclases = ? AND rut = ?";
                $stmt = $conn->prepare($delete);
                $stmt->bind_param("is", $row['idplanclases'], $rut);
                $stmt->execute();
            }
        }
    } else {
        if($checked) {
            // Insertar solo para esta actividad
            $insert = "INSERT IGNORE INTO planclases_docentes (idplanclases, rut) VALUES (?, ?)";
            $stmt = $conn->prepare($insert);
            $stmt->bind_param("is", $idplanclases, $rut);
            $stmt->execute();
        } else {
            // Eliminar solo de esta actividad
            $delete = "DELETE FROM planclases_docentes WHERE idplanclases = ? AND rut = ?";
            $stmt = $conn->prepare($delete);
            $stmt->bind_param("is", $idplanclases, $rut);
            $stmt->execute();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?><?php
include("conexion.php");

$data = json_decode(file_get_contents('php://input'), true);
$rut = $data['rut'];
$idplanclases = $data['idplanclases'];
$checked = $data['checked'];

try {
    if($checked) {
        $query = "INSERT IGNORE INTO docenteclases (idPlanClases, rutDocente) VALUES (?, ?)";
    } else {
        $query = "DELETE FROM docenteclases WHERE idPlanClases = ? AND rutDocente = ?";
    }
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("is", $idplanclases, $rut);
    $success = $stmt->execute();
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$stmt->close();
$conexion->close();
?>