<?php
include_once("conexion.php");
// get_fecha_limite.php
if (!isset($_GET['idCurso'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de curso requerido']);
    exit;
}

$idCurso = intval($_GET['idCurso']);

$query = "SELECT
    sc.idCurso,
    sc.Semanas,
    sc.fechaInicio              AS semana_inicio,                -- ‘2025‑W37’
    os.fecha_inicio             AS lunes_inicio,                 -- 2025‑09‑08
    DATE_FORMAT(                                            -- ‘2025‑W41’
        DATE_ADD(os.fecha_inicio, INTERVAL sc.Semanas WEEK),
        '%x-W%v'
    )                         AS semana_fin,
    DATE_ADD(                                                -- lunes de la semana fin
        os.fecha_inicio,
        INTERVAL sc.Semanas WEEK
    )                         AS lunes_fin,
    DATE_ADD(                                                -- viernes de la semana fin
        DATE_ADD(os.fecha_inicio, INTERVAL sc.Semanas WEEK),
        INTERVAL 4 DAY
    )                         AS fecha_fin
FROM spre_cursos sc
JOIN oferta_semanas os
      ON os.semana = sc.fechaInicio   -- enlazamos con la semana de inicio
WHERE sc.idCurso = ? ";

$stmt = $conexion3->prepare($query);
$stmt->bind_param("i", $idCurso);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'fecha_fin' => $row['fecha_fin']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo obtener la fecha límite'
    ]);
}

$stmt->close();
?>