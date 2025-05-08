<?php
include("conexion.php");

if (!isset($_GET['idcurso'])) {
    exit('ID de curso no proporcionado');
}

$idcurso = $_GET['idcurso'];

$query = "SELECT p.*, pc.idProfesoresCurso, pc.idTipoParticipacion, 
                 pc.horas_clinicas, t.CargoTexto 
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
    $state = ($row['idTipoParticipacion'] != 3 && $row['idTipoParticipacion'] != 1 && $row['idTipoParticipacion'] != 2 && $row['idTipoParticipacion'] != 10) ? "" : "disabled";
    $horas_formateadas = $row['horas_clinicas'] ?: 0;
    ?>
    <tr>
        <td><i class="bi bi-person text-primary"></i></td>
        <td><?php echo utf8_encode($row['Nombres'].' '.$row['Paterno'].' '.$row['Materno']); ?></td>
        <td><?php echo $row['EmailReal'] ?: $row['Email']; ?></td>
        <td>
            <select class="form-select form-select-sm" 
                    id="<?php echo $row['Rut']; ?>" 
                    name="funcion" 
                    onchange="guardarFuncion(this,<?php echo $row['idProfesoresCurso']; ?>)" 
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
        <td class="text-center">
            <div class="input-group input-group-sm">
                <input type="number" class="form-control form-control-sm hours-input" 
                       value="<?php echo $horas_formateadas; ?>" 
                       min="0" step="0.5" style="max-width: 70px;">
                <span class="input-group-text">hrs</span>
                <button class="btn btn-outline-primary btn-sm save-hours-btn" 
                        onclick="guardarHorasDocente(<?php echo $row['idProfesoresCurso']; ?>, 
                            this.closest('td').querySelector('.hours-input').value)">
                    <i class="bi bi-save"></i>
                </button>
            </div>
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