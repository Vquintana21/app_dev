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
<!DOCTYPE html>
<html lang="en">
  <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
   <title>Equipo Docente</title>
    <!-- Bootstrap -->
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
	<!-- CSS personalizado -->
	<link href="estilo2.css" rel="stylesheet">

  </head>
  <body>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">


	<br>
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
					<!--<script src="https://dpi.med.uchile.cl/soportecalendario/js/validarRUT.js"></script>-->
					
					<script>
					    
                        function checkRut(rut) {
                            // Despejar Puntos
                            var valor = rut.value.replace('.','');
                            // Despejar GuiÃ³n
                            valor = valor.replace('-','');
                            
                            // Aislar Cuerpo y Digito Verificador
                            cuerpo = valor.slice(0,-1);
                            dv = valor.slice(-1).toUpperCase();
                            
                            // Formatear RUN
                            rut.value = cuerpo + '-'+ dv
                            
                            // Si no cumple con el minimo ej. (n.nnn.nnn)
                            if(cuerpo.length < 7) { rut.setCustomValidity("RUT Incompleto"); $('#flag').val('false'); return false;}
                            
                            // Calcular Digito Verificador
                            suma = 0;
                            multiplo = 2;
                            
                            // Para cada digito del Cuerpo
                            for(i=1;i<=cuerpo.length;i++) {
                            
                                // Obtener su Producto con el MÃºltiplo Correspondiente
                                index = multiplo * valor.charAt(cuerpo.length - i);
                                
                                // Sumar al Contador General
                                suma = suma + index;
                                
                                // Consolidar MÃºltiplo dentro del rango [2,7]
                                if(multiplo < 7) { multiplo = multiplo + 1; } else { multiplo = 2; }
                          
                            }
                            
                            // Calcular Digito Verificador en base al MÃ³dulo 11
                            dvEsperado = 11 - (suma % 11);
                            
                            // Casos Especiales (0 y K)
                            dv = (dv == 'K')?10:dv;
                            dv = (dv == 0)?11:dv;
                            
                            // Validar que el Cuerpo coincide con su Digito Verificador
                            if(dvEsperado != dv) { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            
                            if(cuerpo == '0000000000' || cuerpo == '00000000') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '11111111' || cuerpo == '1111111') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '22222222' || cuerpo == '2222222') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '33333333' || cuerpo == '3333333') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '44444444' || cuerpo == '4444444') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '55555555' || cuerpo == '5555555') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '66666666' || cuerpo == '6666666') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '77777777' || cuerpo == '7777777') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '88888888' || cuerpo == '8888888') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            if(cuerpo == '99999999' || cuerpo == '9999999') { rut.setCustomValidity("RUT InvÃ¡lido"); $('#flag').val('false'); return false;}
                            
                            // Si todo sale bien, eliminar errores (decretar que es vÃ¡lido)
                            rut.setCustomValidity('');
                            $('#flag').val('true');
                            
                        }
					    
					</script>
					
					</div>
					<p class="help-block"><small> <i class="fa fa-info-circle"></i> Ej: 12345678-k</small></p>
				</div>	
			  

			  <div class="form-group row">
				<label for="inputPassword3" class="col-sm-2 col-form-label">Unidad Academica <font color="red">*</font></label>
				<div class="col-sm-5">
					<select class="form-control" id="unidad_academica" name="unidad_academica" onchange="habilitar_unidad(this)" required>
					<option value="">Seleccionar</option>
					<?php 
						$unidad = "SELECT DISTINCT idDepartamento, Departamento FROM spre_reparticiones ORDER BY departamento DESC"; 
						$unidad_query = mysqli_query($conexion3,$unidad);
						
						while($fila_unidad = mysqli_fetch_assoc($unidad_query)){
					
					?>
						<option value="<?php echo utf8_encode($fila_unidad["Departamento"]); ?>"><?php echo utf8_encode($fila_unidad["Departamento"]); ?></option>
					
						<?php } ?>
					</select>
				
				</div>
				
				<script>
				
					function habilitar_unidad(sel){
						
						var depto = sel.value;
					
						if(depto == 'Unidad Externa'){
							
							document.getElementById("unidad_externa").disabled = false;
							document.getElementById("unidad_externa").required = true;
							document.getElementById("unidad_externa").placeholder='Unidad Externa *';
							
						}else{
							
							document.getElementById("unidad_externa").disabled = true;
							document.getElementById("unidad_externa").required = false;
							document.getElementById("unidad_externa").placeholder='Unidad Externa';
							
						}
						
					}
				</script>
				
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
	
		<script>
		    
		    function guardar_docente(){
		        
		        var curso = $("#curso").val(); 
		        var rut = $("#rut_docente").val(); 
		        var flag = $("#flag").val();
		        var unidad = $("#unidad_academica").val(); 
		        
				var largo_rut = rut.length;
				
		        if($("#unidad_externa").val() != ''){
		            
		            var uE = $("#unidad_externa").val();
		            
		        }else{
		            
		            var uE = "Sin Unidad"; 
		            
		        }
		        
		        var nombres = $("#nombres").val(); 
		        var paterno = $("#paterno").val(); 
		        var materno = $("#materno").val(); 
		        var email = $("#email").val(); 
		        var funcion = $("#funcion").val();
		        
		        if(flag == 'true'){
		        
        		        if(rut != '' && largo_rut >=9 && unidad != '' && uE != '' && nombres != '' && paterno != '' && email != '' && funcion != ''){
        		        
        		        
        	        	$.ajax({
        					dataType: "",
        					data: {"curso":curso,"rut_docente":rut,"unidad_academica":unidad,"unidad_externa":uE,"nombres":nombres,"paterno":paterno,"materno":materno,"email":email,"funcion":funcion},
        					url:   'guardar_docente.php', 
        					type:  'POST',
        					beforeSend: function(){
        					//Lo que se hace antes de enviar el formulario
        					
        					},
        					success: function(respuesta){
        					
        						if(respuesta == 1){
            				
                				    alert("DOCENTE HA SIDO AGREGADO AL CURSO CORRECTAMENTE");
                				    window.location.replace("https://dpi.med.uchile.cl/docentes/nuevo_docente.php?idcurso="+curso+"");
                					
                				}else{
                				    
                				    alert("ATENCIÓN: El docente ya existe en el banco docente de la Facultad. Vuelva a la página anterior y examine el buscador docente.");
                				    
                				}
        						
        					}
							//,
        					//error:	function(xhr,err){
        						//alert("readyState: "+xhr.readyState+"\nstatus: "+xhr.status+"\n \n responseText: "+xhr.responseText);
        
        					}
        					
        				});
        		        
        		        }else{
        		            
        		            alert("¡HAY CAMPOS OBLIGATORIOS QUE ESTÁN VACIOS! ");
        		        }
		        
		        }else{
		            
		            
		            alert("EL FORMATO DEL RUT NO ES VÁLIDO. POR FAVOR VERIFIQUE QUE SEA EL RUT CORRECTO");
		        }
		    }
		    
		    
		</script>
		
		
		</div>
	</div>
	    <br><br>
	   
	   
	   <br><br>
	  </div>
	</div>
 <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  </body>
</html>
<?php }else{ ?>


<div class="alert alert-danger" role="alert">
  <center><h2><strong>Acceso exclusivo para Profesores Encargados de Curso - <?php echo $_GET[idcurso]; ?> - <?php echo $rut_niv; ?></strong></h2> 
  <a class="btn btn-primary" href="http://dpi.med.uchile.cl/planificacion/" role="button">Volver</a></center>

</div>

<?php } ?>