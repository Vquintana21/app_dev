<!DOCTYPE html>
<?php
include("conexion.php");
include("login/control_sesion.php"); 
session_start();
$rut = $_SESSION['sesion_idLogin'];
$name = $_SESSION['sesion_usuario']; 
$viene= array("Ã¡","Ã©","Ã","Ã³","Ãº");
$queda= array("Á","É","Í","Ó","Ú");
$nombre = str_replace($viene, $queda, $name);
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);

if (empty($_SESSION['sesion_idLogin'])) {
    header("Location: login/close.php");
    exit; 
}else{


//Consulta Funcionario
$spre_personas = "SELECT * FROM spre_personas WHERE Rut='$rut_niv' ";
$spre_personasQ = mysqli_query($conexion3,$spre_personas);
$fila_personas = mysqli_fetch_assoc($spre_personasQ);

$funcionario = utf8_encode($fila_personas["Funcionario"]);

function InfoDocenteUcampus($rut_niv){
	
	$rut_niv_def = ltrim($rut_niv, "0");
	$cad = substr ($rut_niv_def, 0, -1);

	$url = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/personas?rut='.$cad;

	//SE INICIA CURL
	$ch = curl_init($url);

	//PARÁMETROS
	$parametros = "rut=$rut_niv";

	//MAXIMO TIEMPO DE ESPERA DE RESPUESTA DEL SERVIDOR
	curl_setopt($ch, CURLOPT_TIMEOUT, 20); 

	//RESPUESTA DEL SERVICIO WEB
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	//EJECUTAMOS LA PETICIÓN
	$resultado = curl_exec($ch);

	//CERRAR 
	curl_close($ch);
		
	$array_cursos = json_decode($resultado);

	if($array_cursos != NULL){

		$foto = $array_cursos->i;
			
	}else{
		
		$foto = "../../undraw_profile.svg"; 
	}

	return $foto; 


}

$foto_docente = InfoDocenteUcampus($rut_niv);

?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preguntas frecuentes calendario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">	
	  
	 <!-- Vendor CSS Files   -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

 <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
  <!-- CSS personalizado -->
  <link href="estilo.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
</head>

<body>

 <!-- ======= Header ======= -->
  <?php include 'nav_superior.php'; ?>
  
    <!-- ======= Sidebar ======= -->
 <?php include 'nav_lateral.php'; ?>


  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Preguntas frecuentes sobre calendario y salas</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Inicio</a></li>
          <li class="breadcrumb-item active">Preguntas frecuentes sobre calendario y salas</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section faq" style="text-align: justify;">
	<div class="card basic">
		<div class="card-body">
		<h6 class="card-subtitle m-2"><i class="fas fa-podcast text-success"></i> ¿Quieres escuchar el manual de reglas de uso de salas y calendario? Te invitamos a escuchar un podcast generado con IA</h6>
			<audio controls>
	  <source src="audio/Preguntas Frecuentes Calendario y Salas.wav" type="audio/wav">
	  Tu navegador no soporta la reproducción de audio.
	</audio>
		</div>
	</div>


	<h6><i class="far fa-clock text-danger"></i> Tiempo de lectura: 6 minutos</h6>
      <div class="row">
        <div class="col-lg-6">

          <div class="card basic">
            <div class="card-body">
              <h5 class="card-title">Preguntas Básicas</h5>

              <div>
                <h6>1. ¿Cuáles son los bloques horarios oficiales para planificar actividades de pregrado?</h6>
                <p>
				Las actividades de los cursos de pregrado deben planificarse considerando los bloques horarios establecidos 
				por la Dirección de Pregrado de la Facultad, en coherencia con la planificación general del curso 
				y <b>deben ser respetados siempre</b>. Los bloques horarios vigentes en la Facultad de Medicina son los siguientes: 
				<br>
					<br>a.	08:30 – 10:00
					<br>b.	10:15 – 11:45
					<br>c.	12:00 – 13:30
					<br>d.	15:00 – 16:30
					<br>e.	16:45 – 18:15

				</p>
              </div>

              <div class="pt-2">
                <h6>2. ¿Puedo usar la hora de almuerzo para hacer actividades? </h6>
                <p>
				<b>No.</b> La franja horaria correspondiente al almuerzo (13:30 a 15:00) no está destinada a la programación regular de 
				actividades de docencia <b>(con excepción de actividades de internados)</b>. 
				De manera excepcional, si un curso requiere utilizar este bloque, 
				deberá enviar una solicitud por correo electrónico a la Dirección de Pregrado, 
				Profesora Marcela Díaz (mdiaz@uchile.cl) y al Profesor Pablo Quiroga (pquiroga@uchile.cl), 
				quienes evaluarán la situación y podrán autorizar su uso. 
				</p>
              </div>

              <div class="pt-2">
                <h6>3. ¿La Unidad de Aulas Docentes puede reservar el bloque horario del almuerzo?</h6>
                <p>En relación al punto N°2, la Unidad de Aulas Docentes <b>NO</b> tiene la atribución de autorizar 
				   el uso de la franja horaria destinada al almuerzo. 
				   Cualquier solicitud relacionada con este bloque debe ser canalizada a través de las autoridades correspondientes.
				</p>
              </div>
			  
			  <div class="pt-2">
                <h6>4. ¿En qué campus puedo realizar mi actividad?</h6>
                <p>La Facultad de Medicina cuenta con cinco campus, y no todas las actividades deben concentrarse exclusivamente en la sede Norte. Los cursos de pregrado que cumplan con los  criterios definidos podrán desarrollar sus actividades en cualquiera de las sedes disponibles (norte, occidente y sur), según corresponda y en función de la disponibilidad de espacios. Es atribución de la Unidad de Aulas Docentes asignar salas en los distintos campus siempre y cuando el curso tenga las características apropiadas:
					<br>
					<br>
					a.	Actividades con horarios AM y/o PM, es decir, actividades planificadas durante toda la mañana, toda la tarde o todo el día.
				</p>
              </div>


				<div class="pt-2">
                <h6>5. ¿Cómo gestiono mi solicitud y/o modificación de salas?</h6>
                <p>Todas las solicitudes de asignación o modificación de aulas deben gestionarse <b>exclusivamente</b> a través de ésta plataforma. La atención presencial, vía correo electrónico y/o telefónico son complementarias. La unidad de aulas siempre le exigirá haber creado un requerimiento en el sistema antes de cualquier atención por correo o presencial.
				</p>
              </div>


            </div>
          </div>

       
        </div>

        <div class="col-lg-6">

          <!-- F.A.Q Group 2 -->
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Sobre el uso de la plataforma calendario</h5>

              <div class="accordion accordion-flush" id="faq-group-2">

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-1" type="button" data-bs-toggle="collapse">
                      ¿Qué es la plataforma calendario?
                    </button>
                  </h2>
                  <div id="faqsTwo-1" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						La plataforma de calendario es la bitácora institucional oficial y única vía de solicitud de salas de las asignaturas asociadas a los diferentes planes de estudios de pregrado. Además, permite organizar los contenidos del curso en función de las fechas correspondiente al semestre/año en que se imparte y el docente responsable en cada una de las actividades calendarizadas. Por último, incluye el detalle de las horas directas efectuadas por cada docente.                     </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-2" type="button" data-bs-toggle="collapse">
                      ¿Por qué es importante completar la información?
                    </button>
                  </h2>
                  <div id="faqsTwo-2" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						El calendario es la plataforma oficial de registro de actividades. Los datos ingresados son importantes y valiosos ya que tributan a distintos procesos de registro institucional de la Universidad.                    </div>
                  </div>
                </div>
				

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-3" type="button" data-bs-toggle="collapse">
                      ¿Dónde pido salas para mi curso?
                    </button>
                  </h2>
                  <div id="faqsTwo-3" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						El calendario es la plataforma oficial para la solicitud de salas. La información ingresada por los usuarios es recibida por la Unidad de Aulas Docentes, la cual se encarga de gestionar su asignación y/o reserva según disponibilidad.                    </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-4" type="button" data-bs-toggle="collapse">
                      ¿Los estudiantes pueden ver lo que registro en la plataforma?
                    </button>
                  </h2>
                  <div id="faqsTwo-4" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						<b>Sí</b>. Los estudiantes tienen acceso al contenido publicado por los docentes a través del portal de estudiantes de la Facultad de Medicina.                     </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-5" type="button" data-bs-toggle="collapse">
                      ¿Quiénes tienen acceso a la plataforma de calendario?
                    </button>
                  </h2>
                  <div id="faqsTwo-5" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						El equipo docente autorizado para editar el calendario son los profesores encargados de curso, profesores coordinadores, coordinadores generales y secretarios/as docentes.                     </div>
                  </div>
                </div>
				<div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-6" type="button" data-bs-toggle="collapse">
                      ¿Quién es el responsable del llenado de la información? ¿Puede hacerlo alguien por mí?
                    </button>
                  </h2>
                  <div id="faqsTwo-6" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						La responsabilidad del llenado de la información es del equipo docente autorizado para editar el calendario del curso. No existen unidades ni funcionarios externos a la unidad académica con dedicación al llenado de la plataforma.                  
						</div>
                </div>
              </div>
			  <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-7" type="button" data-bs-toggle="collapse">
                      En concreto, ¿qué debo gestionar en la plataforma calendario?
                    </button>
                  </h2>
                  <div id="faqsTwo-7" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						En la plataforma de calendario deben gestionarse tres aspectos fundamentales para la planificación académica:
						<br><br>1.	Detalle de actividades: Incluir tipo de actividad y otro tipo de información relevante para su correcta programación. Las actividades de autoaprendizaje son generadas semanalmente y su duración es proporcional con los créditos del curso. 
						<br>2.	Equipo docente: Registrar los nombres de los/as docentes responsables de cada actividad.
						<br>3.	Solicitud de salas: Indicar las necesidades de espacio físico, considerando campus, capacidad y características requeridas.
						</div>
                </div>
              </div> 
			  
			  <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsTwo-8" type="button" data-bs-toggle="collapse">
                      ¿Cuáles son mis obligaciones como docente editor de calendario?
                    </button>
                  </h2>
                  <div id="faqsTwo-8" class="accordion-collapse collapse" data-bs-parent="#faq-group-2">
                    <div class="accordion-body">
						1.	Es obligación del usuario dar cumplimiento a las normas establecidas en el manual de uso de salas y calendario. 
						<br>2.	Es obligación del usuario completar la totalidad del calendario de actividades académicas. 
						<br>3.	Al comienzo del semestre, es responsabilidad del usuario planificar la totalidad de sus cursos. En caso de requerir modificaciones, estas deben ser informadas con al menos un mes de anticipación.
						<br>4.	Es responsabilidad del usuario dar seguimiento al estado de la reserva de salas a través de la plataforma de calendario y/o consulta de aulas docentes. 

					</div>
                </div>
              </div>

            </div>
            </div>
          </div><!-- End F.A.Q Group 2 -->

          <!-- F.A.Q Group 3 -->
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Sobre las reglas de asignación de salas</h5>

              <div class="accordion accordion-flush" id="faq-group-3">

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsThree-1" type="button" data-bs-toggle="collapse">
                      ¿Quién revisa mis solicitudes de salas?
                    </button>
                  </h2>
                  <div id="faqsThree-1" class="accordion-collapse collapse" data-bs-parent="#faq-group-3">
                    <div class="accordion-body">
						<b>Todas las solicitudes</b> de uso de salas deben ser revisadas y validadas por la Unidad de Aulas Docentes, encargada de coordinar y autorizar la asignación de espacios según disponibilidad y criterios establecidos.                    </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsThree-2" type="button" data-bs-toggle="collapse">
                      ¿Qué sala me asignarán?
                    </button>
                  </h2>
                  <div id="faqsThree-2" class="accordion-collapse collapse" data-bs-parent="#faq-group-3">
                    <div class="accordion-body">
						La asignación de salas debe respetar el número de estudiantes inscritos y el tamaño estimado de la actividad. Solo se considerarán excepciones cuando él o la docente indique un requerimiento especial en el campo de observaciones al momento de realizar la solicitud.                    </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsThree-3" type="button" data-bs-toggle="collapse">
                      ¿Y los estudiantes prioritarios? ¿El proceso se hace cargo de este tipo de requerimientos?
                    </button>
                  </h2>
                  <div id="faqsThree-3" class="accordion-collapse collapse" data-bs-parent="#faq-group-3">
                    <div class="accordion-body">
						La gestión centralizada de salas permite incorporar adecuadamente a todos(as) los(as) estudiantes prioritarios(as) de la Facultad de Medicina. Actualmente, el registro de estudiantes con movilidad reducida está integrado a la plataforma de aulas y cumple un rol informativo, debiendo contrastarse con la disponibilidad real de espacios accesibles al momento de la asignación y en conformidad a la evaluación de salas realizada periódicamente por el CEA Inclusión.                    </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsThree-4" type="button" data-bs-toggle="collapse">
                      ¿Cuál es el plazo de resolución de mi solicitud de salas?
                    </button>
                  </h2>
                  <div id="faqsThree-4" class="accordion-collapse collapse" data-bs-parent="#faq-group-3">
                    <div class="accordion-body">
                      Todas las solicitudes ingresadas al sistema de aulas deben ser resueltas por la Unidad de Aulas Docentes con <b>al menos 14 días de anticipación a la fecha de la actividad</b>, con el fin de asegurar una planificación adecuada y permitir eventuales ajustes. Si usted detecta un retraso en el tiempo de solución, por favor escriba a dpi.med@uchile.cl
                    </div>
                  </div>
                </div>

                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsThree-5" type="button" data-bs-toggle="collapse">
                      ¿Puedo extender el horario de mi actividad?
                    </button>
                  </h2>
                  <div id="faqsThree-5" class="accordion-collapse collapse" data-bs-parent="#faq-group-3">
                    <div class="accordion-body">
						<b>No. </b>La Unidad de Aulas Docentes no está facultada para extender el horario de los bloques establecidos, salvo una tolerancia máxima de 15 minutos con fines operacionales. Cualquier solicitud que implique una extensión mayor del bloque horario debe ser canalizada formalmente a través de la Dirección de Pregrado.                    </div>
                  </div>
                </div>
				
				<div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-target="#faqsThree-6" type="button" data-bs-toggle="collapse">
                      ¿Cuál es la vía de ingreso de solicitudes?
                    </button>
                  </h2>
                  <div id="faqsThree-6" class="accordion-collapse collapse" data-bs-parent="#faq-group-3">
                    <div class="accordion-body">
						La Unidad de Aulas Docentes solo revisará solicitudes que provengan directamente desde el sistema de Calendario Académico, lo que garantiza trazabilidad y consistencia en la programación institucional. La unidad de aulas docentes exigirá una solicitud creada previamente en calendario.                   </div>
                </div>
				</div>
				
				

            </div>
          </div><!-- End F.A.Q Group 3 -->

        </div>

      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
 <footer id="footer" class="footer">
    <div class="copyright">
      &copy; <b>2025 Facultad de Medicina Universidad de Chile</b>
    </div>
    <div class="credits">
      Diseñado por <b><a target="_blank" href="https://dpi.med.uchile.cl">DPI</b></a>
    </div>
  </footer>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>
<?php } ?>