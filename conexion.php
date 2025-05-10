<?php
//Conectamos a la BD
$servername = "localhost";
$username = "dpimeduchile";
$password = "gD5T4)N1FDj1";
$dbname = "dpimeduc_calendario";

// Crea la conexión
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");


if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

//Conectamos a la BD de campos clínicos
$db_host2="campoclinico.med.uchile.cl";    
$db_nombre2 = "campocli_campos_clinicos"; 
$db_usuario2="campoclinicomedu";
$db_password2="BRbFh&_1+y1PS2CplS";

$conexion2 = mysqli_connect($db_host2,$db_usuario2,$db_password2,$db_nombre2);

if(mysqli_connect_errno())
  { 
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  }
  
  
 //Conectamos a la BD
$db_host3="localhost"; 
$db_nombre3 = "dpimeduc_planificacion"; 
$db_usuario3="dpimeduchile";
$db_password3="gD5T4)N1FDj1";

$conexion3 = mysqli_connect($db_host3,$db_usuario3,$db_password3,$db_nombre3);

if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

 //Conectamos a la BD soporte
$db_host4="localhost"; 
$db_nombre4 = "dpimeduc_soporte"; 
$db_usuario4="dpimeduc_dpi";
$db_password4="Dpi_decanato";

$conexion4 = mysqli_connect($db_host4,$db_usuario4,$db_password4,$db_nombre4);

if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

 //Conectamos a la BD soporte
$db_host5 = "consultaaulas.med.uchile.cl"; 
$db_nombre5 = "consulta_adminrecursos"; 
$db_usuario5="consulta_reserva";
$db_password5="msq,H3k4efsk";

$conexion5 = mysqli_connect($db_host5,$db_usuario5,$db_password5,$db_nombre5);

if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL: " . mysqli_connect_error();
}



?>