<?php
include("conexion.php");

$idplanclases = isset($_GET['idplanclases']) ? intval($_GET['idplanclases']) : 0;

$buscarCurso = "SELECT cursos_idcursos FROM `planclases` WHERE idplanclases=$idplanclases";
$buscarCursoQ = mysqli_query($conn,$buscarCurso);
$FilaCurso = mysqli_fetch_assoc($buscarCursoQ);
$idCurso = $FilaCurso["cursos_idcursos"];

function InfoDocenteUcampus($rut){
	
	$rut_def = ltrim($rut, "0");
	$cad = substr ($rut_def, 0, -1);

	$url = 'https://3da5f7dc59b7f086569838076e7d7df5:698c0edbf95ddbde@ucampus.uchile.cl/api/0/medicina_mufasa/personas?rut='.$cad;

	//SE INICIA CURL
	$ch = curl_init($url);

	//PARÁMETROS
	$parametros = "rut=$rut";

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

// HTML inicial
echo '<h5 class="card-title">Docentes en la actividad (A->Z)</h5>';
echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
echo '<i class="bi bi-info-circle"></i> Si no encuentras a docente en el listado, debes agregarlo en el apartado <b>Equipo Docente</b>';
echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
echo '</div>';

// Controles generales
echo '<div class="row">';
echo '<div class="col-md-8"><p>Seleccionar todo</p></div>';
echo '<div class="col-md-2">';
echo '<div class="form-check form-switch">';
echo '<input class="form-check-input" type="checkbox" id="selectAllDocentes">';
echo '<label class="form-check-label" for="selectAllDocentes"></label>';
echo '</div></div></div>';

// Consulta de docentes
$equipo_docente = "SELECT A.rut, B.Funcionario, A.idTipoParticipacion 
                   FROM spre_profesorescurso A
                   LEFT JOIN spre_personas B ON B.Rut = A.rut
                   WHERE idcurso = '$idCurso'
                   AND A.idTipoParticipacion NOT IN (8,10)
                   AND Vigencia = 1
                   GROUP BY A.rut
                   ORDER BY Funcionario ASC";

$equpo_docenteQ = mysqli_query($conexion3, $equipo_docente);

// Verificar si la consulta fue exitosa
if (!$equpo_docenteQ) {
    echo '<div class="alert alert-danger">Error al cargar los docentes</div>';
    exit;
}

// Consulta para verificar docentes asignados  
$docentes_asignados = "SELECT distinct rutDocente as rut FROM docenteclases WHERE idPlanClases = $idplanclases and vigencia=1";
$result = mysqli_query($conn, $docentes_asignados);
$docentes_asignados = [];

if ($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $docentes_asignados[] = $row['rut'];
    }
}

// Generar lista de docentes
while($fila_equipo = mysqli_fetch_assoc($equpo_docenteQ)) {
    $foto = InfoDocenteUcampus($fila_equipo["rut"]);
    $checked = in_array($fila_equipo["rut"], $docentes_asignados) ? 'checked' : '';
    
    echo '<div class="row docente-row mb-2">';
    echo '<div class="col-md-3">';
    echo '<img width="70%" src="'.$foto.'" alt="Profile" class="rounded-circle mt-2">';
    echo '</div>';
    echo '<div class="col-md-7">';
    echo '<p class="mt-3">'.utf8_encode($fila_equipo["Funcionario"]).'</p>';
    echo '</div>';
    echo '<div class="col-md-2">';
    echo '<div class="form-check form-switch mt-3">';
    echo '<input class="form-check-input docente-check" type="checkbox" '.$checked.' data-rut="'.$fila_equipo["rut"].'">';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
?>
