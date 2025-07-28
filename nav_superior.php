<?php
include("conexion.php");
include("login/control_sesion.php");
session_start();


// Validación de sesión: si no existe, redirige
if (!isset($_SESSION['sesion_idLogin'])) {
    header("Location: login/close.php");
    exit(); // Importante: detener la ejecución
}


$rut = $_SESSION['sesion_idLogin'];
$name = $_SESSION['sesion_usuario']; 
$viene= array("Ã¡","Ã©","Ã","Ã³","Ãº");
$queda= array("Á","É","Í","Ó","Ú");
$nombre = str_replace($viene, $queda, $name);
$rut_niv = str_pad($rut, 10, "0", STR_PAD_LEFT);

?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="inicio.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="">
        <span class="d-none d-lg-block">calendario académico</span>
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
		<li>
		<button id="cronometroSesion" class="btn btn-outline-danger ms-auto">
		<i class="bi bi-stopwatch"></i>&nbsp;
		  Tiempo restante: <span id="tiempoRestante">--:--</span>
		</button>
		</li>
		&nbsp;
		&nbsp;
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
  

<script>
  let tiempoRestante = <?php echo TIEMPO_RESTANTE; ?>;
  let alertaMostrada = false;
  
  // Variables para tiempo real
  const tiempoInicialSesion = <?php echo TIEMPO_RESTANTE; ?>;
  const momentoInicioConteo = Date.now();
  let timeoutId = null;

  const cronometro = document.getElementById("tiempoRestante");

  function actualizarCronometro() {
    // Calcular tiempo real transcurrido (resistente a pausas del navegador)
    const tiempoTranscurrido = Math.floor((Date.now() - momentoInicioConteo) / 1000);
    tiempoRestante = tiempoInicialSesion - tiempoTranscurrido;
    
    if (tiempoRestante <= 0) {
      console.log('⏰ Sesión expirada exactamente a los 15 minutos');
      window.location.href = "login/close.php?expirada=1";
      return;
    }

    // Mostrar alerta cuando queda 1 minuto
    if (tiempoRestante <= 60 && !alertaMostrada) {
      Swal.fire({
        icon: 'warning',
        title: 'Tu sesión está por expirar',
        text: 'Queda 1 minuto de sesión. Guarda tu trabajo o refresca la página para extenderla.',
        confirmButtonText: 'Entendido',
        timer: 20000,
        timerProgressBar: true
      });
      alertaMostrada = true;
    }

    // Actualizar display
    const minutos = Math.floor(tiempoRestante / 60);
    const segundos = tiempoRestante % 60;
    cronometro.textContent = `${minutos}:${segundos.toString().padStart(2, '0')}`;
    
    // 🔥 CLAVE: setTimeout recursivo en lugar de setInterval
    timeoutId = setTimeout(actualizarCronometro, 1000);
  }

  // Detectar cambios de visibilidad de pestaña
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      // Pestaña inactiva - limpiar timeout para evitar acumulación
      if (timeoutId) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }
      console.log('📱 Pestaña inactiva - pausando cronómetro visual');
    } else {
      // Pestaña activa - reiniciar con cálculo actualizado
      console.log('👀 Pestaña activa - recalculando tiempo real');
      if (timeoutId) clearTimeout(timeoutId); // Prevenir duplicados
      actualizarCronometro(); // Ejecutar inmediatamente con tiempo correcto
    }
  });

  // Iniciar el cronómetro
  actualizarCronometro();
  
  // Cleanup al salir de la página
  window.addEventListener('beforeunload', function() {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  });
</script>


