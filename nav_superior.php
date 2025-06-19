<?php
include("conexion.php");
session_start();
$rut = $_SESSION['sesion_idLogin'];
$name = $_SESSION['sesion_usuario']; 
$viene= array("Ã¡","Ã©","Ã","Ã³","Ãº");
$queda= array("Á","É","Í","Ó","Ú");
$nombre = str_replace($viene, $queda, $name);
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);
?>
<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
      <a href="inicio.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="">
        <span class="d-none d-lg-block">Calendario Académico</span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>
    
    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item d-block d-lg-none">
          <a class="nav-link nav-icon search-bar-toggle " href="#">
            <i class="bi bi-search"></i>
          </a>
        </li>
        <li class="nav-item dropdown pe-3">
		<?php $foto = InfoDocenteUcampus($rut); ?>
          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="<?php echo $foto; ?>" alt="Profile" class="rounded-circle">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $funcionario; ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo $funcionario; ?></h6>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center text-danger" href="login/close.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </nav>
  </header>