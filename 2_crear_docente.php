<?php 
//crear docentes.php
header ('Content-type: text/html; charset=utf-8');
session_start(); 
error_reporting(0);
//include("conn.php");
include("conexion.php");
//$rut = $_SESSION['sesion_idLogin']; 
$rut = '162083015';
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);
$consulta=mysqli_query($conexion3,"select EmailReal from spre_personas where rut ='$rut_niv'");
$estate = mysqli_fetch_assoc($consulta);
$mail=$estate['EmailReal'];
$usuariox = $_SESSION['sesion_usuario']; 
$usuario = utf8_decode($usuariox);

$_SESSION["RutUser"] = $rut_niv;
 
$CURSO = "SELECT spre_cursos.idCurso,spre_cursos.CodigoCurso,spre_ramos.nombreCurso,spre_cursos.seccion  FROM spre_cursos 
INNER JOIN spre_ramos ON spre_cursos.codigoCurso = spre_ramos.codigoCurso
WHERE idCurso='$_GET[idcurso]'";
$CURSO_query = mysqli_query($conexion3,$CURSO);

$fila_curso = mysqli_fetch_assoc($CURSO_query);

$PEC = "SELECT * FROM spre_personas WHERE Rut='$rut_niv' ";
$PEC_Query = mysqli_query($conexion3,$PEC);
$PEC_fila = mysqli_fetch_assoc($PEC_Query); 

//Control Profesor (¿Es profesor encargado del curso?)

$ValidarProfe = "SELECT * FROM spre_profesorescurso WHERE idcurso='$_GET[idcurso]' AND rut='$rut_niv' AND vigencia='1' AND idTipoParticipacion IN ('1','2','3','8','10')  ";
$ValidarQuery = mysqli_query($conexion3,$ValidarProfe);
$control_profe = mysqli_num_rows($ValidarQuery);

if($rut!='' && $control_profe > 0)
{
?>

   
	<div class="container">
	
		<div class=" container col-10" style="border: 1px solid #D2D1D1;">
		<br>
		<center><h4>Agregar nuevo docente a <?php echo utf8_encode($fila_curso['nombreCurso']); ?> <i class="fas fa-user-plus"></i></h4></center>	
		<center>
		
    <div class="card shadow-sm">
        <div class="card-body text-center">
            <div class="alert alert-info" role="alert">
                <h5 class="mb-3">¿No conoce el rut?</h5>
                <p class="mb-3">Búsquelo en estos enlaces:</p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="https://rnpi.superdesalud.gob.cl/" 
                       target="_blank" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>
                        Superintendencia de salud
                    </a>
                    
                    <a href="https://www.nombrerutyfirma.com/" 
                       target="_blank"
                       class="btn btn-outline-primary">
                        <i class="fas fa-id-card me-2"></i>
                        Rutificador
                    </a>
					
					 <button type="button" onclick="volverYRecargarTabla()" class="btn btn-secondary ms-2">
    <i class="fas fa-arrow-left me-2"></i>Volver
</button>
                </div>
            </div>
        </div>
    </div>
		</center>
		  <input type="text" id="curso" name="curso" value="<?php echo $_GET['idcurso']; ?>" hidden />
		  
			 	<div class="form-group row mb-3">
					<label for="inputPassword3" class="col-sm-2 col-form-label">RUT <font color="red">*</font></label>
					<div class="col-sm-5 ">
						<input type="text" maxlength="10" id="rut_docente" name="rut_docente" required oninput="checkRut(this)" placeholder="Ingrese Rut sin puntos ni guion" class="form-control">
						<input type="text" id="flag" name="flag" value="" hidden /> 
					
					
					</div>
					<p class="help-block"><small> <i class="fa fa-info-circle"></i> Ej: 12345678-k</small></p>
				</div>	
			  

			  <div class="form-group row">
				<label for="inputPassword3" class="col-sm-2 col-form-label">Unidad Academica <font color="red">*</font></label>
				<div class="col-sm-5">
					<select class="form-control" id="unidad_academica" name="unidad_academica" onchange="habilitar_unidad(this)" required>
					<option value="">Seleccionar</option>
					<?php 
						$unidad = "SELECT DISTINCT idDepartamento, Departamento FROM spre_reparticiones where `idDepartamento` like '12%' ORDER BY `spre_reparticiones`.`Departamento` ASC;"; 
						$unidad_query = mysqli_query($conexion3,$unidad);
						
						while($fila_unidad = mysqli_fetch_assoc($unidad_query)){
					
					?>
						<option value="<?php echo utf8_encode($fila_unidad["Departamento"]); ?>"><?php echo utf8_encode($fila_unidad["Departamento"]); ?></option>
					
						<?php } ?>
					</select>
				
				</div>
				
				
				
				<div class="col-sm-5 mb-3">
				  <input type="text" class="form-control"  id="unidad_externa" name="unidad_externa" placeholder="Unidad Externa" disabled>
				</div>
				
			  </div>
			  <div class="form-group row mb-3">
				<label for="inputPassword3" class="col-sm-2 col-form-label">Nombres <font color="red">*</font></label>
				<div class="col-sm-10">
				  <input type="text" class="form-control" id="nombres" name="nombres" placeholder="Ingresar" required>
				</div>
			  </div>	
			  <div class="form-group row mb-3">
				<label for="inputPassword3" class="col-sm-2 col-form-label">Apellidos <font color="red">*</font> </label>
				<div class="col-sm-5">
				  <input type="text" class="form-control" id="paterno" name="paterno" placeholder="Paterno" required>
				</div>
				<div class="col-sm-5 mb-3">
				  <input type="text" class="form-control" id="materno" name="materno" placeholder="Materno" required>
				</div>
			  </div>
			 	
				<div class="form-group row mb-3">
				<label for="inputPassword3" class="col-sm-2 col-form-label">Email <font color="red">*</font> </label>
				<div class="col-sm-10">
				  <input type="email" class="form-control" id="email" name="email" placeholder="Ingresar" required>
				</div>
			  </div>	
			  <div class="form-group row mb-3">
				<label for="inputPassword3" class="col-sm-2 col-form-label">Funci&#243;n <font color="red">*</font></label>
				<div class="col-sm-10">
				<select class="form-control" id="funcion" name="funcion" required>
			  
					<option value="">Seleccionar</option>
					<?php 
					
						$funcion="SELECT * FROM spre_tipoparticipacion WHERE idTipoParticipacion NOT IN ('1','2','3','10') ORDER BY idTipoParticipacion ASC";
						$funcion_query = mysqli_query($conexion3,$funcion);
						
						while($fila_funcion = mysqli_fetch_assoc($funcion_query)){
						
						?>
						<option value="<?php echo $fila_funcion['idTipoParticipacion']; ?>"><?php echo utf8_encode($fila_funcion['CargoTexto']); ?></option>
						
					<?php } ?>
			    </select>

				</div>
			  </div>
			  <br>
			 
				  <center><button type="button" onclick="guardar_docente()" class="btn btn-success">Guardar</button></center>
			  <br><br>
	
			
		
		</div>
	</div>
	    
<?php }else{ ?>


<div class="alert alert-danger" role="alert">
  <center><h2><strong>Acceso exclusivo para Profesores Encargados de Curso - <?php echo $_GET[idcurso]; ?> - <?php echo $rut_niv; ?></strong></h2> 
  <a class="btn btn-primary" href="http://dpi.med.uchile.cl/planificacion/" role="button">Volver</a></center>

</div>

<?php } ?>