<?php
include_once 'dbconfig.php';
require "phpmailer/PHPMailerAutoload.php";

function enviarCorreoLiberacionDesdeProfesor($conn, $idpcl, $justificacion) {
    // Datos de la actividad
    $querycurso = mysqli_query($conn,"SELECT usuario, `CodigoCurso`, `Seccion`, `NombreCurso`, tipoSesion, fecha, hora_inicio, hora_termino 
                                      FROM asignacion
                                      WHERE idplanclases = $idpcl LIMIT 1");
    $rowcurso = mysqli_fetch_assoc($querycurso);
    $curso = $rowcurso['NombreCurso']." - ".$rowcurso['CodigoCurso']."-".$rowcurso['Seccion'];
    $tipoActividad = $rowcurso['tipoSesion'];
    $usuario = $rowcurso['usuario'];
    $fecha = $rowcurso['fecha'];
    $hora_inicio = $rowcurso['hora_inicio'];
    $hora_termino = $rowcurso['hora_termino'];

    // Info de salas
    $querySalas = mysqli_query($conn, "SELECT a.idSala, s.sa_Nombre, s.sa_UbicCampus, s.sa_UbicOtraInf, s.sa_Capacidad 
                                       FROM asignacion a 
                                       LEFT JOIN sala s ON a.idSala = s.idSala 
                                       WHERE a.idplanclases = $idpcl AND a.idSala != '' AND a.idSala != '1'");
    $salas_liberadas = [];
    while($rowSala = mysqli_fetch_assoc($querySalas)) {
        $salas_liberadas[] = $rowSala;
    }

    // Info del docente
    $queryDocente = mysqli_query($conn,"SELECT `Funcionario`, `EmailReal` FROM spre_personas WHERE Rut ='$usuario'");
    $docente = mysqli_fetch_assoc($queryDocente);
    $nombre = $docente['Funcionario'];
    $email = $docente['EmailReal'];

    // Mail setup
    $mail = new PHPMailer(true);
    $mail->CharSet = "UTF-8";
    $mail->Encoding = "quoted-printable";
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "ssl";
    $mail->Host = "mail.dpi.med.uchile.cl";
    $mail->Port = 465;
    $mail->Username = "_mainaccount@dpi.med.uchile.cl";
    $mail->Password = "gD5T4)N1FDj1";
    $mail->setFrom("_mainaccount@dpi.med.uchile.cl", "Sistema de Aulas");
    $mail->Subject = "Liberación de Sala realizada por el Profesor";

	$mail->addAddress('felpilla@gmail.com');
    //$mail->addAddress($email, $nombre);
    //$mail->addCC('gestionaulas.med@uchile.cl', 'Gestión de Aulas');

    // Estilos y cuerpo HTML (basado en tu plantilla)
    $fecha_formateada = DateTime::createFromFormat('Y-m-d', $fecha)->format('d-m-Y');
    $fecha_actual = date('d-m-Y');

    $contenidoCorreo = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
                font-family: Arial, sans-serif;
            }
            .container {
                width: 100%;
                max-width: 900px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            .header {
                background-color: #1da7d9;
                color: #ffffff;
                padding: 20px;
                text-align: center;
            }
            .header img {
                width: 150px;
                margin-bottom: 10px;
            }
            .content {
                padding: 20px;
                color: #333333;
            }
            .content p {
                line-height: 1.6;
            }
            .footer {
                background-color: #1da7d9;
                color: #ffffff;
                text-align: center;
                padding-top: 3px;
                font-size: 14px;
            }
            .footer img {
                width: 100%;
            }
            .small {
                text-align: right;
            }
            table, td, th {
                border: 1px solid #595959;
                border-collapse: collapse;
            }
            td, th {
                padding: 3px;
                width: 25%;
                height: 25px;
                text-align: left;
            }
            th {
                background: #f0e6cc;
            }
            .even {
                background: #fbf8f0;
            }
            .odd {
                background: #fefcf9;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://medicina.uchile.cl/.resources/portal-medicina/images/medicina-logo.png' alt='Logo Medicina UChile'>
                <h1>Liberación de Salas</h1>
            </div>
            <div class='content'>
                <p>Estimado(a) Profesor(a) <strong>{$nombre}</strong>,</p>
                <p>Usted ha realizado una liberación de sala para la actividad del <strong>{$fecha_formateada}</strong>, desde las <strong>{$hora_inicio} a las {$hora_termino}</strong> horas, correspondiente al curso <strong>{$curso}</strong> como tipo de actividad <strong>{$tipoActividad}</strong>.</p>
                <p><strong>Justificación ingresada:</strong></p>
                <blockquote style='border-left: 3px solid #ccc; padding-left: 10px; color: #555;'>{$justificacion}</blockquote>";

    if (count($salas_liberadas) > 0) {
        $contenidoCorreo .= "
                <p>Las salas liberadas son las siguientes:</p>
                <table>
                    <tr>
                        <th>Sala</th>
                        <th>Campus</th>
                        <th>Piso</th>
                        <th>Capacidad</th>
                    </tr>";
        foreach ($salas_liberadas as $sala) {
            $contenidoCorreo .= "
                    <tr>
                        <td>{$sala['idSala']} - {$sala['sa_Nombre']}</td>
                        <td>{$sala['sa_UbicCampus']}</td>
                        <td>{$sala['sa_UbicOtraInf']}</td>
                        <td>{$sala['sa_Capacidad']}</td>
                    </tr>";
        }
        $contenidoCorreo .= "</table>";
    } else {
        $contenidoCorreo .= "<p>No hay información detallada de salas liberadas.</p>";
    }

    $contenidoCorreo .= "
                <p>Si desea volver a solicitar salas, ingrese a la plataforma de calendario haciendo <a href='https://dpi.med.uchile.cl/CALENDARIO/' target='_blank'>clic aquí</a>.</p>
                <div class='small'>
                    <p><small>Este mensaje es generado automáticamente por el sistema de gestión de salas. (code: P - {$idpcl})</small></p>
                </div>
            </div>
            <div class='footer'>
                <img src='https://medicina.uchile.cl/.resources/portal-medicina/images/header-bg.gif' alt='Decoración institucional'>
            </div>
        </div>
    </body>
    </html>";

    $mail->isHTML(true);
    $mail->Body = $contenidoCorreo;

    try {
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo de liberación: " . $mail->ErrorInfo);
    }
}
?>