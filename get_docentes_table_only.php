<?php
// get_docentes_table_only.php - VERSIÓN CORREGIDA CON FUNCIONALIDAD DE CURSOS CLÍNICOS
// Solo retorna la tabla con la funcionalidad original de edición de horas

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("conexion.php");

if (!isset($_GET['idcurso'])) {
    die('ID de curso no proporcionado');
}

$idcurso = intval($_GET['idcurso']);

if (!isset($conexion3)) {
    die('Error: conexion3 no disponible');
}

if (!isset($conn)) {
    die('Error: conn no disponible');
}
?>

<!-- ===== SOLO LA TABLA CON FUNCIONALIDAD ORIGINAL DE CURSOS CLÍNICOS ===== -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 5%"></th>
                <th style="width: 35%">Docente</th>
                <th style="width: 25%">Correo</th>
                <th style="width: 20%">Función</th>
                <th style="width: 10%" class="text-center">Total Horas Directas</th>
                <th style="width: 5%" class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody id="docentes-table-body">
            <?php
            try {
                // Query original de cursos clínicos
                $query = "SELECT 
                    pc.idProfesoresCurso,
                    pc.rut,
                    p.Nombres,
                    p.Paterno, 
                    p.Materno,
                    p.EmailReal,
                    p.Email,
                    pc.unidad_academica_docente,
                    pc.idTipoParticipacion,
                    tp.CargoTexto,
                    COALESCE(hd.horas_directas, 0) as horas_directas
                FROM spre_profesorescurso pc
                INNER JOIN spre_personas p ON pc.rut = p.Rut
                INNER JOIN spre_tipoparticipacion tp ON pc.idTipoParticipacion = tp.idTipoParticipacion
                LEFT JOIN (
                    SELECT rut_docente, SUM(horas_directas) as horas_directas 
                    FROM spre_horasdirectas 
                    WHERE idcurso = ? 
                    GROUP BY rut_docente
                ) hd ON p.rut = hd.rut_docente
                WHERE pc.idcurso = ? 
                AND pc.Vigencia = '1' 
                AND pc.idTipoParticipacion NOT IN ('10')
                ORDER BY tp.idTipoParticipacion, p.Nombres ASC";

                $stmt = $conexion3->prepare($query);
                $stmt->bind_param("ii", $idcurso, $idcurso);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($fila_profesores = $result->fetch_assoc()) {
                        $idProfesoresCurso = $fila_profesores['idProfesoresCurso'];
                        $rutDocente = $fila_profesores['rut'];
                        $nombreCompleto = utf8_encode($fila_profesores['Nombres'].' '.$fila_profesores['Paterno'].' '.$fila_profesores['Materno']);
                        $email = $fila_profesores['EmailReal'] ?: $fila_profesores['Email'];
                        $unidadAcademica = $fila_profesores['unidad_academica_docente'] ?: '';
                        $idTipoParticipacion = $fila_profesores['idTipoParticipacion'];
                        $cargoTexto = utf8_encode($fila_profesores['CargoTexto']);
                        $horas_formateadas = floatval($fila_profesores['horas_directas']);
                        
                        // Determinar si está deshabilitado para edición
                        $state = ($idTipoParticipacion == 1 || $idTipoParticipacion == 2 || $idTipoParticipacion == 3) ? 'disabled' : '';
            ?>
                        <tr>
                            <td>
                                <i class="bi bi-person-circle text-primary fs-4"></i>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo $nombreCompleto; ?></strong><br>
                                    <small class="text-muted">RUT: <?php echo $rutDocente; ?></small>
                                </div>
                            </td>
                            <td><?php echo $email; ?></td>
                            <td>
                                <?php if ($state != 'disabled'): ?>
                                    <select class="form-select form-select-sm" 
                                            onchange="actualizarFuncion(this, <?php echo $idProfesoresCurso; ?>)">
                                        <?php
                                        $funciones_query = "SELECT * FROM spre_tipoparticipacion WHERE idTipoParticipacion NOT IN ('1','2','3','10') ORDER BY idTipoParticipacion ASC";
                                        $funciones_result = mysqli_query($conexion3, $funciones_query);
                                        while($funcion_option = mysqli_fetch_assoc($funciones_result)) {
                                            $selected = ($funcion_option['idTipoParticipacion'] == $idTipoParticipacion) ? 'selected' : '';
                                            echo '<option value="' . $funcion_option['idTipoParticipacion'] . '" ' . $selected . '>' . 
                                                 utf8_encode($funcion_option['CargoTexto']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo $cargoTexto; ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- ===== HORAS DIRECTAS CON FUNCIONALIDAD ORIGINAL DE CURSOS CLÍNICOS ===== -->
                            <td class="text-center">
                                <div class="input-group input-group-sm" style="width: 120px; margin: 0 auto;">
                                    <input type="number" 
                                           class="form-control text-center hours-input" 
                                           id="horas_<?php echo $idProfesoresCurso; ?>"
                                           value="<?php echo number_format($horas_formateadas, 1); ?>" 
                                           min="0" 
                                           step="0.5"
                                           data-id-profesor="<?php echo $idProfesoresCurso; ?>"
                                           data-rut="<?php echo $rutDocente; ?>"
                                           data-unidad-academica="<?php echo htmlspecialchars($unidadAcademica); ?>"
                                           data-original-value="<?php echo $horas_formateadas; ?>"
                                           placeholder="0">
                                    <span class="input-group-text">hrs</span>
                                </div>
                            </td>
                            
                            <td class="text-center">
                                <?php if ($state != 'disabled'): ?>
                                    <button type="button" 
                                            onclick="eliminarDocente(<?php echo $idProfesoresCurso; ?>)" 
                                            class="btn btn-outline-danger btn-sm"
                                            title="Remover docente del curso">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
            <?php
                    }
                } else {
                    echo '<tr><td colspan="6" class="text-center py-4">No hay docentes asignados a este curso</td></tr>';
                }
                
                $stmt->close();
            } catch (Exception $e) {
                echo '<tr><td colspan="6" class="text-center text-danger">Error: ' . $e->getMessage() . '</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>