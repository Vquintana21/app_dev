<?php
include("conexion.php");

if (!isset($_GET['idcurso'])) {
    exit('ID de curso no proporcionado');
}

$idcurso = $_GET['idcurso'];

$query = "SELECT
    *,
    t.CargoTexto,
    a.idProfesoresCurso,
    a.idTipoParticipacion AS tipo_part
FROM
    spre_profesorescurso a
INNER JOIN spre_personas p ON
    a.rut = p.Rut
INNER JOIN spre_tipoparticipacion t ON
    a.idTipoParticipacion = t.idTipoParticipacion
WHERE
    a.idcurso = ? AND a.Vigencia = '1' AND a.idTipoParticipacion NOT IN('10')
ORDER BY CASE WHEN
    a.idTipoParticipacion IN(1, 2, 3) THEN 0 ELSE 1
END,
p.Nombres ASC,
p.Paterno ASC,
p.Materno ASC;";

$stmt = $conexion3->prepare($query);
$stmt->bind_param("i", $idcurso);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Consulta para obtener total de horas
    $query_horas = "SELECT sum(`horas`) as total_horas 
                   FROM `docenteclases` 
                   WHERE `idCurso` = ? 
                   AND `rutDocente` = ? 
                   AND vigencia = 1";
    $stmt_horas = $conn->prepare($query_horas);
    $stmt_horas->bind_param("is", $idcurso, $row['rut']);
    $stmt_horas->execute();
    $result_horas = $stmt_horas->get_result();
    $total_horas = $result_horas->fetch_assoc();
    $horas_formateadas = $total_horas['total_horas'];
    
    // Determinar el estado del select basado en el tipo de participación
    if($row['tipo_part'] != 3 && $row['tipo_part'] != 1 && 
       $row['tipo_part'] != 2 && $row['tipo_part'] != 10) {
        $state = "";
    } else {
        $state = "disabled";
    }
?>
    <tr>
        <td><i class="bi bi-person text-primary"></i></td>
        <td><?php echo utf8_encode($row['Nombres'].' '.$row['Paterno'].' '.$row['Materno']); ?></td>
        <td><?php echo $row['EmailReal'] ?: $row['Email']; ?></td>
        <td>
            <select class="form-select form-select-sm" 
        onchange="actualizarFuncion(this, <?php echo $row['idProfesoresCurso']; ?>)" 
        <?php echo $state; ?>>
    <!-- Primero mostramos la opción actual del profesor -->
    <option value="<?php echo $row['tipo_part']; ?>" selected>
        <?php echo utf8_encode($row['CargoTexto']); ?>
    </option>
    
    <?php 
    // Solo si el select no está deshabilitado, mostramos las demás opciones
    if ($state != 'disabled') {
        $funcion_query = "SELECT idTipoParticipacion, CargoTexto 
                         FROM spre_tipoparticipacion 
                         WHERE idTipoParticipacion NOT IN ('1','2','3','10')
                         AND idTipoParticipacion != '{$row['tipo_part']}'";
        $funcion_result = mysqli_query($conexion3, $funcion_query);
        while($fila_funcion = mysqli_fetch_assoc($funcion_result)): 
        ?>
            <option value="<?php echo $fila_funcion['idTipoParticipacion']; ?>">
                <?php echo utf8_encode($fila_funcion['CargoTexto']); ?>
            </option>
        <?php 
        endwhile;
    }
    ?>
</select>
        </td>
        <td class="text-center">
            <?php if($horas_formateadas > 0): ?>
                <span class="badge bg-primary"><?php echo $horas_formateadas; ?> hrs</span>
            <?php else: ?>
                <span class="badge bg-secondary">0 hrs</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($state != 'disabled'): ?>
            <button type="button" 
                    onclick="eliminarDocente(<?php echo $row['idProfesoresCurso']; ?>)" 
                    class="btn btn-outline-danger btn-sm"
                    title="Remover docente del curso">
                <i class="bi bi-trash"></i>
            </button>
            <?php endif; ?>
        </td>
    </tr>
<?php
}

$stmt->close();
$conexion3->close();
?>