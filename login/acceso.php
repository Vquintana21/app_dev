<?php
//error_reporting(E_ALL);

function login_medicina($cerrarSesion="NO")
{

# Path y Variables Principales
$pathLogin 		= "https://sesion.med.uchile.cl/authsesion.php"; 

$sesion_idLogin = "";
$_varGetPost = $_REQUEST;
	
	if ($cerrarSesion != "NO") Kill_sesion($_varGetPost);

	$respuesta_sesionOK = false;
	$ya_existe_sesionOK = false;
	$ya_existe_sesionOK = valida_si_existe_sesion();
	

	if  (!($ya_existe_sesionOK )) 
	{ 
		if (isset($_varGetPost["sesion_numero"])){	$respuesta_sesionOK = valida_respuesta_x_sesion_numero($_varGetPost);  	}
		if ( $respuesta_sesionOK) // continua si la solicitud es valida
		{
			// valida que exista respuesta Autenticacion = OK
			if (isset($_varGetPost["sesion_Autenticacion"]))
				if ($_varGetPost["sesion_Autenticacion"] = "OK") {
					if (isset($_varGetPost["sesion_idLogin"])) {
						$sesion_idLogin = $_varGetPost["sesion_idLogin"];
						almacena_variables_en_sesion($_varGetPost);
					}
				} else call_login($pathLogin,$_varGetPost,$cerrarSesion);
		}
		else call_login($pathLogin,$_varGetPost,$cerrarSesion);
	} 
	$sesion_idLogin = $_varGetPost["sesion_idLogin"];

return $sesion_idLogin;
}

#--------------------------------------------------------------------	
# Funciones 
#--------------------------------------------------------------------

function valida_respuesta_x_sesion_numero($_varGetPost)
{
$sesion_numero="";
	if (isset($_varGetPost["sesion_numero"]))  $sesion_numero=$_varGetPost["sesion_numero"] ;
	if(isset($_SESSION['sesion_numero'])) {
		if ($sesion_numero == $_SESSION['sesion_numero'])
		{
			return true;
		} 
	}

return false;	
}


function valida_si_existe_sesion()
{
global $_SESSION;
$sesion_numero="";

	if(isset($_SESSION['sesion_numero'])) {
		if ( isset($_SESSION['sesion_Autenticacion']) && isset($_SESSION['sesion_idLogin']))
		{
			//session_destroy();			
			return true;
		} 
	}
return false;	
}

function destruye_session()
{
	$_SESSION['sesion_idLogin'] = "";
	$_SESSION['sesion_Autenticacion'] = "";
	session_destroy();
}

function call_login($pathLogin,$_varGetPost,$cerrarSesion)
{
$pathAplicacion = dondeEstoy($_varGetPost);
$sesion_Login = "";


 	 //rj 	session_start();

		//alimentamos el generador de aleatorios
		mt_srand (time());

		//generamos un número aleatorio
		$sesion_numero = mt_rand(1000000,999999999);
		
		// Se guarda numero de sesion para validar envio desde login
		$_SESSION['sesion_numero'] = $sesion_numero;
		
	
		// lamada a login con numero de sesion
		echo "<script>window.location = '$pathLogin?pathAplicacion=$pathAplicacion&sesion_numero=$sesion_numero&cerrarSesion=$cerrarSesion'</script>";
	
return;			
}

function almacena_variables_en_sesion($_varGetPost)
{
 //rj  	session_start();
	foreach($_varGetPost as $clave => $valor) {
           if (substr($clave,0,6) == "sesion") {
		   		$_SESSION[$clave] = $valor;
				//echo "[$clave] = " . $_SESSION[$clave];
			}
	}
 //rj 	session_destroy();
 
}

function dondeEstoy($_varGetPost)
{
	//global $_SERVER;
	$host = $_SERVER['HTTP_HOST'];
	$self = $_SERVER['PHP_SELF'];
	$query = !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
	
	if (!empty($query)) {
		$query = preg_replace('/aqui=0.*/','',$query);
	
	}
	if (empty($query)) {
			foreach($_varGetPost as $clave => $valor) {$query .= "&$clave=$valor";}
	}
	
	//ec("query=$query");
	$url = !empty($query) ? "https://$host$self?$query&aqui=0" : "https://$host$self?aqui=0";
	//echo "url=$url";exit;
	return $url;	
}

function Kill_sesion($_varGetPost)
{
// Unset all of the session variables.
$_SESSION = array();

//unset($_COOKIE['id_usuario_dw']);  
//unset($_COOKIE['marca_aleatoria_usuario_dw']);  
 
$parametros="";
$NoConsiderar = array("pathAplicacion", "cerrarSesion","OpcionSesion","Opcion","_txtUsuario","_txtPassword","sesion_numero");
foreach($_varGetPost as $clave => $valor) {
	if (!(in_array($clave, $NoConsiderar))) $parametros .= "&$clave=$valor";
	}

	echo "<script>window.location = 'index.html'</script>";

}

function Kill_sesion_con_Mensaje($msg)
{
global $_SESSION;

//	unset($_SESSION['sesion_numero']);  
	unset($_SESSION['sesion_Autenticacion']);  
	unset($_COOKIE['id_usuario_dw']);  
	unset($_COOKIE['marca_aleatoria_usuario_dw']);  
	$_SESSION = array();

	echo "<script>alert('$msg');window.location = 'index.html'</script>";
}

function alert($t){ echo "<script>alert('$t');</script>"; }
	
	
?>