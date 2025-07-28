<?php
session_start();
session_unset();
session_destroy();

// Lee si viene ?expirada=1 ó 0  (por default 0)
$flag = (isset($_GET['expirada']) && $_GET['expirada'] == '1') ? 1 : 0;

// Redirige al login con esa misma marca
header("Location: https://dpi.med.uchile.cl/calendario/login.php?expirada={$flag}");
exit();
