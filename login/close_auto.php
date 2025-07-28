<?php
session_start();

// Elimina todas las variables de sesión
session_unset();

// Destruye la sesión
session_destroy();

header("Location: https://dpi.med.uchile.cl/calendario/login.php?expirada=1");
exit();


?>