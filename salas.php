<?php
include("conexion.php");

$idCurso = $_GET['curso'];

// Consulta SQL modificada
$query = "SELECT
    p.idplanclases,
    p.pcl_tituloActividad,
    p.pcl_Fecha,
    p.pcl_Inicio,
    p.pcl_Termino,
    p.pcl_TipoSesion,
    p.pcl_SubTipoSesion,
    p.pcl_campus,
    p.pcl_nSalas,
    t.pedir_sala,
    (
    SELECT
        GROUP_CONCAT(DISTINCT idSala)
    FROM
        asignacion
    WHERE
        idplanclases = p.idplanclases AND idEstado != 4
) AS salas_asignadas,
(
    SELECT
        COUNT(*)
    FROM
        asignacion
    WHERE
        idplanclases = p.idplanclases AND idEstado = 3
) AS salas_confirmadas
FROM
    planclases p
INNER JOIN pcl_TipoSesion t ON
    p.pcl_TipoSesion = t.tipo_sesion
WHERE
    p.cursos_idcursos = 8924 
	AND t.pedir_sala = 1 
	AND p.pcl_SubTipoSesion = t.Sub_tipo_sesion 
	AND p.pcl_tituloActividad != ''
ORDER BY
    p.pcl_Fecha ASC,
    p.pcl_Inicio ASC;";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idCurso);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="card">
  <div class="card-header">
    <h5 class="card-title">Gestión de Salas</h5>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Horario</th>
            <th>Actividad</th>
            <th>Tipo</th>
			<th>Campus</th>
			<th>N° Salas</th>
            <th>Sala</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): 
            $fecha = new DateTime($row['pcl_Fecha']);
            $esClase = $row['pcl_TipoSesion'] === 'Clase';
          ?>
          <tr>
            <td>
              <?php echo $row['dia'] . ' ' . $fecha->format('d/m/Y'); ?>
              <br>
              <small class="text-muted">Semana <?php echo $row['pcl_Semana']; ?></small>
            </td>
            <td><?php echo substr($row['pcl_Inicio'], 0, 5) . ' - ' . substr($row['pcl_Termino'], 0, 5); ?></td>
            <td><?php echo $row['pcl_tituloActividad']; ?></td>
            <td>
              <?php echo $row['pcl_TipoSesion']; ?>
              <?php if($row['pcl_SubTipoSesion']): ?>
                <br><small class="text-muted"><?php echo $row['pcl_SubTipoSesion']; ?></small>
              <?php endif; ?>
            </td>
			<td><?php echo $row['pcl_campus']; ?></td>
			<td><?php echo $row['pcl_nSalas']; ?></td>
            <td>
              <?php if($row['Sala']): ?>
                <span class="badge bg-success"><?php echo $row['Sala']; ?></span>
              <?php else: ?>
                <span class="badge bg-secondary">Sin sala</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($row['Sala']): ?>
                <span class="badge bg-success">Asignada</span>
              <?php elseif($esClase): ?>
                <span class="badge bg-info">Sala solicitada</span>
              <?php else: ?>
                <span class="badge bg-warning">Pendiente</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($esClase || $row['Sala']): ?>
                <button type="button" class="btn btn-sm btn-warning" onclick="modificarSala(<?php echo $row['idplanclases']; ?>)">
                  <i class="bi bi-pencil"></i> Modificar
                </button>
                <?php if($row['Sala']): ?>
                  <button type="button" class="btn btn-sm btn-danger" onclick="liberarSala(<?php echo $row['idplanclases']; ?>)">
                    <i class="bi bi-x-circle"></i> Liberar
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="solicitarSala(<?php echo $row['idplanclases']; ?>)">
                  <i class="bi bi-plus-circle"></i> Solicitar
                </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal para Solicitar/Modificar Sala -->
<div class="modal fade" id="salaModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="salaModalTitle">Gestionar Sala</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info" role="alert">
          <i class="bi bi-info-circle"></i>
          Con el objetivo de ayudarle con el envío de solicitudes a la unidad de aulas, en las actividades de tipo Clase teórica hemos dispuesto la función de asignación automática de salas. En esta versión todas las solicitudes de este tipo de actividad se cargan por defecto y puede modificarla solo en el caso de ser necesario.
        </div>

        <form id="salaForm">
          <input type="hidden" id="idplanclases" name="idplanclases">
          
          <div class="mb-3">
            <label class="form-label">¿Requiere sala para esta actividad?</label>
            <select class="form-select" id="requiereSala" name="requiereSala" required>
              <option value="Si">Si</option>
              <option value="No">No</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Campus</label>
            <select class="form-select" id="campus" name="campus" required>
              <option value="Norte">Norte</option>
              <option value="Sur">Sur</option>
              <option value="Centro">Centro</option>
            </select>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label">Información de salas</label>
              <a href="#" onclick="verSalas()">Ver salas <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">N° de salas requeridas para la actividad</label>
            <select class="form-select" id="nSalas" name="nSalas" required>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
              <option value="5">5</option>
            </select>
            <small class="text-muted">Importante: Si requiere más salas que las definidas en el listado, póngase en contacto con dpi.med@uchile.cl</small>
          </div>

          <div class="mb-3">
            <label class="form-label">N° de alumnos totales del curso</label>
            <input type="number" class="form-control" id="alumnosTotales" name="alumnosTotales" required>
          </div>

          <div class="mb-3">
            <label class="form-label">N° de alumnos por sala</label>
            <input type="number" class="form-control" id="alumnosPorSala" name="alumnosPorSala" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Movilidad reducida</label>
            <select class="form-select" id="movilidadReducida" name="movilidadReducida" required>
              <option value="No">No</option>
              <option value="Si">Si</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                      placeholder="Por favor, describa su requerimiento con el mayor nivel de detalle posible. Incluya información específica y relevante para asegurar que podamos entender y satisfacer completamente sus necesidades."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="guardarSala()">Guardar cambios</button>
      </div>
    </div>
  </div>
</div>

<?php
$stmt->close();
$conn->close();
?>

