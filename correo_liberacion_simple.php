<?php
include_once 'conexion.php';
require "phpmailer/PHPMailerAutoload.php";

$conn_sin_base = new mysqli('localhost', 'dpimeduchile', 'gD5T4)N1FDj1');
mysqli_query($conn_sin_base, "SET NAMES 'utf8'");

function enviarCorreoLiberacionSimple($conn, $idpcl, $detalleSala = null) {
    global $conn_sin_base;
    
    error_log("📧 INICIO enviarCorreoLiberacionSimple - idpcl: {$idpcl}");
    
    // Datos de la actividad
    $querycurso = mysqli_query($conn,"SELECT `CodigoCurso`, `Seccion`, `NombreCurso`, tipoSesion, fecha, hora_inicio, hora_termino 
                                      FROM asignacion
                                      WHERE idplanclases = $idpcl LIMIT 1");
    $rowcurso = mysqli_fetch_assoc($querycurso);
    $curso = $rowcurso['NombreCurso']." - ".$rowcurso['CodigoCurso']."-".$rowcurso['Seccion'];
    $tipoActividad = $rowcurso['tipoSesion'];
    $fecha = $rowcurso['fecha'];
    $hora_inicio = $rowcurso['hora_inicio'];
    $hora_termino = $rowcurso['hora_termino'];

    error_log("📋 Datos de actividad obtenidos - Curso: {$curso}");

    // Usar el detalle de sala pasado como parámetro
    $salas_liberadas = [];
    if ($detalleSala !== null && !empty($detalleSala)) {
        $salas_liberadas[] = $detalleSala;
        error_log("✅ Usando detalle de sala del parámetro: " . json_encode($detalleSala));
    }

    error_log("📊 Total salas para incluir en correo: " . count($salas_liberadas));

    // ===== OBTENER TODOS LOS DOCENTES DE LA ACTIVIDAD =====
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

    if (!$querypersona || mysqli_num_rows($querypersona) === 0) {
        error_log("⚠️ No se encontraron docentes para la actividad {$idpcl}");
        return; // Salir sin enviar correo pero sin fallar
    }

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
    $mail->Subject = "Liberación de Sala - " . $curso;

    // ===== AGREGAR DESTINATARIOS =====
    $contadorDestinatarios = 0;
    $listaCorreos = [];
    
    // Primer docente como destinatario principal (PEC)
    $primerDocente = mysqli_fetch_array($querypersona);
    if ($primerDocente) {
        $mail->addAddress($primerDocente['email'], $primerDocente['Funcionario']);
        $listaCorreos[] = $primerDocente['email'];
        $contadorDestinatarios++;
        $nombrePrincipal = $primerDocente['Funcionario'];
        error_log("✅ Destinatario principal: {$primerDocente['email']} ({$primerDocente['Funcionario']})");
    }
    
    // Resto como copias
    while ($correos = mysqli_fetch_array($querypersona)) {
        $mail->addCC($correos['email'], $correos['Funcionario']);
        $listaCorreos[] = $correos['email'];
        $contadorDestinatarios++;
        error_log("✅ Copia agregada: {$correos['email']} ({$correos['Funcionario']})");
    }
    
    //$mail->addCC('felpilla@gmail.com'); // Para pruebas
    $mail->addCC('gestionaulas.med@uchile.cl', 'Gestión de Aulas'); // Para producción
    
    error_log("📊 Total destinatarios: {$contadorDestinatarios}");

    // Formatear fecha
    $fecha_formateada = DateTime::createFromFormat('Y-m-d', $fecha)->format('d-m-Y');

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
                margin: 10px 0;
            }
            td, th {
                padding: 8px 12px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background: #f0e6cc;
                font-weight: bold;
            }
            .even {
                background: #fbf8f0;
            }
            .odd {
                background: #fefcf9;
            }
            .sala-info {
                margin: 10px 0;
                padding: 10px;
                border-left: 4px solid #1da7d9;
                background-color: #f8f9fa;
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
                <p>Estimado(a) Profesor(a) <strong>{$nombrePrincipal}</strong>,</p>
                <p>Se ha liberado desde la plataforma calendario, una sala correspondiente a la actividad del <strong>{$fecha_formateada}</strong>, desde las <strong>{$hora_inicio} a las {$hora_termino}</strong> horas, correspondiente al curso <strong>{$curso}</strong> como tipo de actividad <strong>{$tipoActividad}</strong>.</p>";

    if (count($salas_liberadas) > 0) {
        $contenidoCorreo .= "
                <div class='sala-info'>
                    <p><strong>La sala liberada es la siguiente:</strong></p>
                    <table style='width: 100%;'>
                        <thead>
                            <tr>
                                <th>Sala</th>
                                <th>Campus</th>
                                <th>Ubicación</th>
                                <th>Capacidad</th>
                            </tr>
                        </thead>
                        <tbody>";
        
        foreach ($salas_liberadas as $sala) {
            $contenidoCorreo .= "
                            <tr>
                                <td><strong>{$sala['idSala']}</strong><br><small>{$sala['sa_Nombre']}</small></td>
                                <td>{$sala['sa_UbicCampus']}</td>
                                <td>{$sala['sa_UbicOtraInf']}</td>
                                <td>{$sala['sa_Capacidad']} personas</td>
                            </tr>";
        }
        
        $contenidoCorreo .= "
                        </tbody>
                    </table>
                </div>";
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
        $correosEnviados = implode(", ", $listaCorreos);
        error_log("✅ Correo enviado exitosamente a {$contadorDestinatarios} docente(s): {$correosEnviados}");
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo de liberación: " . $mail->ErrorInfo);
        // No lanzar excepción para que no falle la liberación
    }
}
?>