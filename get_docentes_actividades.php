<?php
// obtener_docentes_actividades.php
header('Content-Type: application/json');
include("conexion.php");

try {
    // Recibir datos
    $actividades = isset($_POST['actividades']) ? $_POST['actividades'] : [];
    $idCurso = isset($_POST['idcurso']) ? (int)$_POST['idcurso'] : 0;
    
    if (empty($actividades) || $idCurso <= 0) {
        throw new Exception('Parámetros incompletos');
    }
    
    // Preparar lista de IDs para consulta IN
    $actividadesIds = implode(',', array_map('intval', $actividades));
    
    // Obtener todos los docentes activos para cada actividad
    $query = "SELECT idPlanClases, rutDocente 
              FROM docenteclases 
              WHERE idPlanClases IN ($actividadesIds) 
              AND idCurso = $idCurso 
              AND vigencia = 1";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception('Error en la consulta: ' . mysqli_error($conn));
    }
    
    // Organizar docentes por actividad
    $docentesPorActividad = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $idPlanClase = $row['idPlanClases'];
        $rutDocente = $row['rutDocente'];
        
        if (!isset($docentesPorActividad[$idPlanClase])) {
            $docentesPorActividad[$idPlanClase] = [];
        }
        
        $docentesPorActividad[$idPlanClase][] = $rutDocente;
    }
    
    // Encontrar docentes comunes a todas las actividades
    $docentesComunes = [];
    
    if (count($docentesPorActividad) > 0) {
        // Inicializar con los docentes de la primera actividad
        $primeraActividad = reset($docentesPorActividad);
        $docentesComunes = $primeraActividad;
        
        // Intersectar con las demás actividades
        foreach ($docentesPorActividad as $idActividad => $docentes) {
            $docentesComunes = array_intersect($docentesComunes, $docentes);
        }
    }
    
    // Devolver resultado exitoso
    echo json_encode([
        'success' => true,
        'docentesComunes' => array_values($docentesComunes),
        'totalActividades' => count($docentesPorActividad)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Cerrar conexión
if (isset($conn)) {
    $conn->close();
}
?>