<?php
include_once 'conexion.php';
require "phpmailer/PHPMailerAutoload.php";

$conn_sin_base = new mysqli('localhost', 'dpimeduchile', 'gD5T4)N1FDj1');
mysqli_query ($conn ,"SET NAMES 'utf8'");

function enviarCorreoLiberacionDesdeProfesor($conn, $idpcl, $justificacion, $detalleSala = null) {
    error_log("📧 INICIO enviarCorreoLiberacionDesdeProfesor - idpcl: {$idpcl}");
       global $conn_sin_base;
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

    error_log("📋 Datos de actividad obtenidos - Curso: {$curso}, Usuario: {$usuario}");

    // ✅ NUEVA LÓGICA: Usar el detalle de sala pasado como parámetro
    $salas_liberadas = [];
    if ($detalleSala !== null && !empty($detalleSala)) {
        $salas_liberadas[] = $detalleSala;
        error_log("✅ Usando detalle de sala del parámetro: " . json_encode($detalleSala));
    } else {
        error_log("⚠️ No se recibió detalle de sala como parámetro, intentando consulta fallback");
        
        // FALLBACK: Intentar obtener de comentarios o logs (opcional)
        $querySalas = mysqli_query($conn, "SELECT a.idSala, s.sa_Nombre, s.sa_UbicCampus, s.sa_UbicOtraInf, s.sa_Capacidad 
                                           FROM asignacion a 
                                           LEFT JOIN sala s ON a.idSala = s.idSala 
                                           WHERE a.idplanclases = $idpcl AND a.idSala != '' AND a.idSala != '1'");
        while($rowSala = mysqli_fetch_assoc($querySalas)) {
            $salas_liberadas[] = $rowSala;
        }
        
        if (empty($salas_liberadas)) {
            error_log("⚠️ No se encontraron salas por consulta fallback");
        }
    }

    error_log("📊 Total salas para incluir en correo: " . count($salas_liberadas));

    // Info del docente
    $queryDocente = mysqli_query($conn,"SELECT `Funcionario`, `EmailReal` FROM spre_personas WHERE Rut ='$usuario'");
    $docente = mysqli_fetch_assoc($queryDocente);
    $nombre = $docente['Funcionario'];
    $email = $docente['EmailReal'];
	
	 $querypersona = mysqli_query($conn_sin_base,"
                SELECT DISTINCT
    p.Rut,
    p.Funcionario,
    COALESCE(
        ca.correo,
        p.Emailreal,
        p.Email
    ) AS email
FROM
    (
    SELECT
        pc.cursos_idcursos
    FROM
        dpimeduc_calendario.planclases pc
    WHERE
        pc.idplanclases = '$idpcl'
) AS plan
LEFT JOIN dpimeduc_planificacion.spre_profesorescurso prc
ON
    plan.cursos_idcursos = prc.idcurso AND prc.Vigencia = 1
   
LEFT JOIN dpimeduc_calendario.docenteclases dc
ON
    dc.idplanclases = '$idpcl' AND dc.vigencia = 1
INNER JOIN dpimeduc_planificacion.spre_personas p
ON
    p.Rut = COALESCE(prc.rut, dc.rutDocente)
LEFT JOIN dpimeduc_calendario.correos_actualizados ca
ON
    ca.rut = p.Rut
WHERE
    COALESCE(
        ca.correo,
        p.Emailreal,
        p.Email
    ) IS NOT NULL AND(
        prc.idTipoParticipacion IN(1, 2, 3, 8) OR prc.idTipoParticipacion IS NULL
    )
ORDER BY
    p.Funcionario;
                ");

    error_log("👤 Docente: {$nombre}, Email: {$email}");

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

    $mail->addAddress($email, $nombre);
	while($correos = mysqli_fetch_array($querypersona)){
		$mail->addCC($correos['email'], $correos['Funcionario']); // ✅ addCC no addAddress
	}
	$mail->addCC('felpilla@gmail.com'); 
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
        error_log("📋 Generando tabla de salas liberadas");
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
        error_log("⚠️ No hay salas para mostrar en el correo");
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
        error_log("✅ Correo enviado exitosamente a: {$email}");
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo de liberación: " . $mail->ErrorInfo);
        throw $e; // Re-throw para que lo capture el backend
    }
}
?>