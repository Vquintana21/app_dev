<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
      
      <!-- 1. Inicio -> Redirige al home del sistema (cursos) -->
      <li class="nav-item">
        <a class="nav-link" href="login.php">
          <i class="bi bi-house-door"></i>
          <span>Inicio</span>
        </a>
      </li>
      
      <!-- Calendario (mantener el actual) -->
      <li class="nav-item">
        <a class="nav-link" href="index.php?curso=<?php echo $idCurso; ?>">
          <i class="bi bi-calendar3"></i>
          <span>Calendario</span>
        </a>
      </li>
      
      <!-- Separador visual -->
      <li class="nav-heading">Ayuda y Soporte</li>
      
      <!-- 2. Tutorial -> Redirige al tutorial en youtube del sistema en nueva pestaña -->
      <li class="nav-item">
        <a class="nav-link" href="https://www.youtube.com/watch?v=TU_VIDEO_ID" target="_blank" rel="noopener noreferrer">
          <i class="bi bi-play-circle"></i>
          <span>Tutorial</span>
          <i class="bi bi-box-arrow-up-right ms-auto" style="font-size: 0.8rem; opacity: 0.7;"></i>
        </a>
      </li>
      
      <!-- 3. Preguntas frecuentes -> Redirige al F.A.Q -->
      <li class="nav-item">
        <a class="nav-link" href="pages-faq.php">
          <i class="bi bi-question-circle"></i>
          <span>Preguntas frecuentes</span>
        </a>
      </li>
      
      <!-- 4. ¿Necesitas ayuda? -> Redirige a nuestro formulario de ayuda (pestaña nueva) -->
      <li class="nav-item">
        <a class="nav-link" href="https://dpi.med.uchile.cl/gestion/sugerencias/" target="_blank" rel="noopener noreferrer">
          <i class="bi bi-life-preserver"></i>
          <span>¿Necesitas ayuda?</span>
          <i class="bi bi-box-arrow-up-right ms-auto" style="font-size: 0.8rem; opacity: 0.7;"></i>
        </a>
      </li>
      
      <!-- Separador visual -->
      <li class="nav-heading">Documentación</li>
      
      <!-- 5. Reglamento calendario -> Redirige al documento que cargaremos en el futuro (por ahora enlace desactivado) -->
      <li class="nav-item">
        <a class="nav-link disabled" href="#" onclick="return false;" style="opacity: 0.5; cursor: not-allowed;">
          <i class="bi bi-file-earmark-text"></i>
          <span>Reglamento calendario</span>
          <small class="text-muted ms-auto" style="font-size: 0.7rem;">(Próximamente)</small>
        </a>
      </li>
      
    </ul>
</aside>