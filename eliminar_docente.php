<?php
include("conexion.php");
header('Content-Type: application/json');

if(isset($_POST['idProfesoresCurso'])) {
    $id = (int)$_POST['idProfesoresCurso'];
    
    // Primero obtenemos el RUT del docente y el idCurso
    $queryInfo = "SELECT rut, idcurso FROM spre_profesorescurso WHERE idProfesoresCurso = $id";
    $resultInfo = mysqli_query($conexion3, $queryInfo);
    
    if($row = mysqli_fetch_assoc($resultInfo)) {
        $rut = $row['rut'];
        $idCurso = $row['idcurso'];
        
        // Iniciamos transacción para asegurar integridad
        mysqli_begin_transaction($conexion3);
        
        try {
            // 1. Actualizar vigencia en spre_profesorescurso
            $query1 = "UPDATE spre_profesorescurso SET Vigencia = '0' WHERE idProfesoresCurso = $id";
            mysqli_query($conexion3, $query1);
            
            // 2. Actualizar vigencia en docenteclases para todas las actividades del curso
            $query2 = "DELETE FROM docenteclases
						   WHERE rutDocente = '$rut' 
						   AND idCurso = '$idCurso'";
            mysqli_query($conn, $query2);
            
            // Confirmar transacción
            mysqli_commit($conexion3);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Docente eliminado correctamente del curso y todas sus actividades'
            ]);
        } catch (Exception $e) {
            // Revertir en caso de error
            mysqli_rollback($conexion3);
            echo json_encode([
                'status' => 'error',
                'message' => 'Error al eliminar docente: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró el docente']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
}
?>