<?php

$con = new mysqli('localhost', 'dpimeduchile', 'gD5T4)N1FDj1', 'dpimeduc_calendario');
mysqli_query ($con ,"SET NAMES 'utf8'");

function getWeekStartDate($date) {
    $dayOfWeek = date('N', strtotime($date));
    return date('Y-m-d', strtotime($date . ' -' . ($dayOfWeek - 1) . ' days'));
}

$today = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$weekStart = getWeekStartDate($today);
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +4 days'));

$bloques = [
    "08:00:00 - 10:00:00",
    "10:15:00 - 11:45:00",
    "12:00:00 - 13:30:00",
    "15:00:00 - 16:30:00",
    "16:45:00 - 18:15:00"
];

 $sqlcs2 = mysqli_query($con, "
    SELECT fecha, CONCAT(hora_inicio, ' - ', hora_termino) AS bloque, COUNT(idSala) AS num_asignaciones 
    FROM asignacion 
    WHERE fecha BETWEEN '$weekStart' AND '$weekEnd'
    GROUP BY fecha, bloque
    ORDER BY fecha, hora_inicio
");


$reservas = [];
while ($row = mysqli_fetch_assoc($sqlcs2)) {
    $reservas[] = $row;
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  </head>
  <body>
    <div class="container">
        <h1 class="mt-5">Calendario de Reservas</h1>
        <div class="d-flex justify-content-between my-3">
            <a href="?date=<?= date('Y-m-d', strtotime($weekStart . ' -7 days')) ?>" class="btn btn-primary">&laquo; Semana Anterior</a>
            <h3>Semana del <?= date('d-m-Y', strtotime($weekStart)) ?> al <?= date('d-m-Y', strtotime($weekEnd)) ?></h3>
            <a href="?date=<?= date('Y-m-d', strtotime($weekStart . ' +7 days')) ?>" class="btn btn-primary">Semana Siguiente &raquo;</a>
        </div>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Lunes</th>
                    <th>Martes</th>
                    <th>Miércoles</th>
                    <th>Jueves</th>
                    <th>Viernes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($bloques as $bloque) {
                    echo "<tr>";
                    echo "<td>$bloque</td>";
                    for ($i = 0; $i < 5; $i++) {
                        echo "<td>";
                        $currentDate = date('Y-m-d', strtotime($weekStart . " +$i days"));
                        $reservasPorBloque = array_filter($reservas, function($reserva) use ($currentDate, $bloque) {
                            return $reserva['fecha'] == $currentDate && $reserva['bloque'] == $bloque;
                        });
                        if (count($reservasPorBloque) > 0) {
                            $reserva = array_values($reservasPorBloque)[0];
                            $modalId = str_replace([':', ' '], ['-', '_'], $reserva['fecha'] . $reserva['bloque']);
                            
                            // Consulta para obtener las salas asignadas en ese bloque
                            $sqlAssigned = mysqli_query($con, "
                                SELECT UPPER(idSala) AS idSala, campus, NombreCurso 
                                FROM asignacion 
                                WHERE fecha = '{$reserva['fecha']}' 
                                AND hora_inicio = SUBSTRING_INDEX('{$reserva['bloque']}', ' - ', 1) 
                                AND hora_termino = SUBSTRING_INDEX('{$reserva['bloque']}', ' - ', -1)
                            ");
                            $assignedRooms = [];
                            while ($row = mysqli_fetch_assoc($sqlAssigned)) {
                                $assignedRooms[] = $row;
                            }
                            $assignedRoomIds = array_column($assignedRooms, 'idSala');
                            $assignedRoomsStr = implode("','", $assignedRoomIds);
                            
                            // Consulta para obtener las salas disponibles
                            $sqlAvailable = mysqli_query($con, "
                                SELECT IDENTIFICADOR, AULA, CAMPUS, CAPACIDAD_MAXIMA 
                                FROM pcl_aulas_2024 
                                WHERE IDENTIFICADOR NOT IN ('$assignedRoomsStr')
                            ");
                            $availableRooms = [];
                            while ($row = mysqli_fetch_assoc($sqlAvailable)) {
                                $availableRooms[] = $row;
                            }
                            
                            echo "<a href='#' class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#modal$modalId'>{$reserva['num_asignaciones']}</a><br>";
                            echo "<div class='modal fade' id='modal$modalId' tabindex='-1' aria-labelledby='modalLabel$modalId' aria-hidden='true'>
                                    <div class='modal-dialog modal-lg'>
                                        <div class='modal-content'>
                                            <div class='modal-header'>
                                                <h5 class='modal-title' id='modalLabel$modalId'>Salas Asignadas y Disponibles</h5>
                                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                            </div>
                                            <div class='modal-body'>
                                                <div class='row'>
                                                    <div class='col-md-6'>
                                                        <h6>Salas Asignadas</h6>
                                                        <ul>";
                            foreach ($assignedRooms as $room) {
                                echo "<li><strong>Sala:</strong> {$room['idSala']}, <strong>Campus:</strong> {$room['campus']}, <strong>Curso:</strong> {$room['NombreCurso']}</li>";
                            }
                            echo "</ul>
                                                    </div>
                                                    <div class='col-md-6'>
                                                        <h6>Salas Disponibles</h6>
                                                        <ul>";
                            foreach ($availableRooms as $room) {
                                echo "<li><strong>Sala:</strong> {$room['IDENTIFICADOR']}, <strong>Aula:</strong> {$room['AULA']}, <strong>Campus:</strong> {$room['CAMPUS']}, <strong>Capacidad Máxima:</strong> {$room['CAPACIDAD_MAXIMA']}</li>";
                            }
                            echo "</ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class='modal-footer'>
                                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cerrar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>";
                        } else {
                            echo "<a href='#' class='btn btn-secondary btn-sm' disabled>0</a><br>";
                        }
                        echo "</td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  </body>
</html>
