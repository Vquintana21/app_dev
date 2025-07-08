<?php
include("conexion.php");


function registrarLogSala2($conn, $idplanclases, $accion, $idSala = '') {   
    
	$usuarioX = isset($_SESSION['sesion_idLogin']) ? $_SESSION['sesion_idLogin'] : 
               (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '167847811');
			   
	$usuario = str_pad($usuarioX, 10, "0", STR_PAD_LEFT);
    
    // Definir estado y comentario según la acción
    switch($accion) {
		
		case 'no_requiere':
            $idEstado = 0;
            $comentario = "Usuario(a) $usuario ha declarado no requerir sala(s) para la actividad $idplanclases.";
            $idSala = ''; 
            break;
			
        case 'solicitar':
            $idEstado = 0;
            $comentario = "Usuario(a) $usuario ha solicitado sala(s) para la actividad $idplanclases.";
            $idSala = ''; 
            break;
            
        case 'modificar':
            $idEstado = 1;
            $comentario = "Usuario(a) $usuario ha solicitado modificación de sala para la actividad $idplanclases.";
            $idSala = ''; 
            break;
            
        case 'modificar_asignada':
            $idEstado = 1;
            $comentario = "Usuario(a) $usuario ha solicitado modificación de sala asignada para la actividad $idplanclases. Sala anterior: $idSala";
            break;
            
        case 'liberar':
            $idEstado = 4;
            $comentario = "Usuario(a) $usuario ha liberado el uso de sala para la actividad $idplanclases. Sala liberada: $idSala";
            break;
            
        case 'reservar_computacion':
            $idEstado = 0;
            $comentario = "Usuario(a) $usuario ha reservado sala de computación $idSala para la actividad $idplanclases.";
            break;
            
        default:
            $idEstado = 0;
            $comentario = "Usuario(a) $usuario realizó acción '$accion' en actividad $idplanclases.";
    }
    
    // Insertar en log_salas
    $query = "INSERT INTO log_salas (idplanclases, idEstado, idSala, comentario, usuario) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("iisss", $idplanclases, $idEstado, $idSala, $comentario, $usuario);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    
    return false;
}


?>

