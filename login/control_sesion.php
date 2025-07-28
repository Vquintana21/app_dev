<?php
session_start();

$duracionSesion = 900;          // 15 min  (ajusta a 5 seg solo para pruebas)
//$duracionSesion = 75;          //  ←  descomenta SOLO si estás testeando

// ► 1 : sin sesión → close.php (se marca como expirada=0 porque aún no ha caducado)
if (!isset($_SESSION['sesion_idLogin'])) {
    header("Location: login/close.php?expirada=0");
    exit();
}

// ► 2 : establece o refresca el timestamp
if (!isset($_SESSION['inicio_sesion'])) {
    $_SESSION['inicio_sesion'] = time();
}
$transcurrido = time() - $_SESSION['inicio_sesion'];

// ► 3 : si excede la duración, mandar a close.php con expirada=1
if ($transcurrido > $duracionSesion) {
    header("Location: login/close.php?expirada=1");
    exit();
}

// ► 4 : resetea el timestamp por cada petición válida
$_SESSION['inicio_sesion'] = time();

// ► 5 : valor que usará tu JS para la cuenta regresiva
define('TIEMPO_RESTANTE', $duracionSesion);
