<?php
// buscar_docentes_ajax.php mejorado
try {
    include("conexion.php");
    
    header('Content-Type: application/json; charset=utf-8');
    
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    if (strlen($search) < 3) {
        echo json_encode([
            'items' => [], 
            'pagination' => ['more' => false],
            'message' => 'Escriba al menos 3 caracteres'
        ]);
        exit;
    }
    
    // Dividir el término de búsqueda en palabras
    $searchTerms = explode(' ', $search);
    $searchTerms = array_filter($searchTerms); // Eliminar espacios vacíos
    
    // Construir condiciones WHERE dinámicamente
    $whereConditions = [];
    $params = [];
    $types = "";
    
    // Si es un RUT (solo números)
    if (preg_match('/^\d+$/', $search)) {
        $whereConditions[] = "rut LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    } else {
        // Búsqueda por nombre - cada término debe estar presente
        foreach ($searchTerms as $term) {
            $whereConditions[] = "Funcionario LIKE ?";
            $params[] = "%$term%";
            $types .= "s";
        }
    }
    
    // Construir query con todas las condiciones
    $whereClause = implode(' AND ', $whereConditions);
    
    // Contar total
    $countQuery = "SELECT COUNT(*) as total FROM spre_bancodocente WHERE $whereClause";
    $stmtCount = $conexion3->prepare($countQuery);
    if (!$stmtCount) {
        throw new Exception("Error preparando consulta de conteo: " . $conexion3->error);
    }
    
    // Bind dinámico de parámetros
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $total = $resultCount->fetch_assoc()['total'];
    $stmtCount->close();
    
    // Consulta principal con orden por relevancia
    $query = "SELECT rut, Funcionario, Departamento 
              FROM spre_bancodocente 
              WHERE $whereClause
              ORDER BY 
                CASE 
                  WHEN Funcionario LIKE ? THEN 1
                  ELSE 2
                END,
                Funcionario ASC 
              LIMIT ? OFFSET ?";
    
    $stmt = $conexion3->prepare($query);
    if (!$stmt) {
        throw new Exception("Error preparando consulta principal: " . $conexion3->error);
    }
    
    // Agregar parámetros para ORDER BY
    $fullSearchTerm = "%$search%";
    $allParams = array_merge($params, [$fullSearchTerm], [$limit, $offset]);
    $allTypes = $types . "sii";
    
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Resaltar términos encontrados
        $funcionario = $row['Funcionario'];
        $highlightedFuncionario = $funcionario;
        
        // Resaltar cada término de búsqueda
        foreach ($searchTerms as $term) {
            $highlightedFuncionario = preg_replace(
                "/(" . preg_quote($term, '/') . ")/i", 
                "<strong>$1</strong>", 
                $highlightedFuncionario
            );
        }
        
        $items[] = [
            'id' => $row['rut'],
            'text' => $row['rut'] . ' - ' . utf8_encode($row['Funcionario']),
            'html' => $row['rut'] . ' - ' . utf8_encode($highlightedFuncionario) . 
                      ($row['Departamento'] ? ' (' . utf8_encode($row['Departamento']) . ')' : '')
        ];
    }
    $stmt->close();
    
    $hasMore = ($offset + $limit) < $total;
    
    $response = [
        'items' => $items,
        'pagination' => [
            'more' => $hasMore
        ],
        'total_count' => $total
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error en buscar_docentes_ajax.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error en el servidor',
        'debug' => $e->getMessage()
    ]);
}

if (isset($conexion3)) {
    $conexion3->close();
}
?>