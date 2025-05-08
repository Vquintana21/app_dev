<?php
include("conexion.php");

if (!isset($_GET['idcurso'])) {
    exit('ID de curso no proporcionado');
}

$idcurso = $_GET['idcurso'];

$query = "SELECT p.*, pc.idTipoParticipacion, t.CargoTexto 
          FROM spre_profesorescurso pc
          INNER JOIN spre_personas p ON pc.rut = p.Rut 
          INNER JOIN spre_tipoparticipacion t ON pc.idTipoParticipacion = t.idTipoParticipacion 
          WHERE pc.idcurso = ? AND pc.Vigencia = '1' 
          AND pc.idTipoParticipacion NOT IN ('10') 
          ORDER BY pc.idTipoParticipacion, p.Nombres ASC";

$stmt = $conexion3->prepare($query);
$stmt->bind_param("i", $idcurso);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $state = ($row['idTipoParticipacion'] != 3 && $row['idTipoParticipacion'] != 1 && $row['idTipoParticipacion'] != 10) ? "" : "disabled";
    ?>
    <tr>
        <td><i class="bi bi-person text-primary"></i></td>
        <td><?php echo utf8_encode($row['Nombres'].' '.$row['Paterno'].' '.$row['Materno']); ?></td>
        <td><?php echo $row['EmailReal'] ?: $row['Email']; ?></td>
        <td>
            <?php if($row['unidad_academica_docente']): ?>
                <?php echo utf8_encode($row['unidad_academica_docente']); ?>
            <?php else: ?>
                <span class="text-muted">
                    <i class="bi bi-info-circle"></i> Sin unidad académica
                </span>
            <?php endif; ?>
        </td>
        <td>
            <select class="form-select form-select-sm" id="<?php echo $row['Rut']; ?>" 
                    name="funcion" onchange="guardarFuncion(this,<?php echo $row['idProfesoresCurso']; ?>)" 
                    <?php echo $state; ?>>
                <option value="<?php echo $row['idTipoParticipacion']; ?>">
                    <?php echo utf8_encode($row['CargoTexto']); ?>
                </option>
                <?php 
                $funcion_query = mysqli_query($conexion3,"SELECT * FROM spre_tipoparticipacion WHERE idTipoParticipacion NOT IN ('1','2','3','10')");
                while($fila_funcion = mysqli_fetch_assoc($funcion_query)): 
                ?>
                    <option value="<?php echo $fila_funcion['idTipoParticipacion']; ?>">
                        <?php echo utf8_encode($fila_funcion['CargoTexto']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-sm" 
                    onclick="eliminarDocente(<?php echo $row['idProfesoresCurso']; ?>)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
    <?php
}

$stmt->close();
$conexion3->close();
?>