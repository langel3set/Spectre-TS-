<?php
iniciarSesionSegura();
$logueado = usuarioLogueado();
$rol      = rolActual();
$usuario  = $logueado ? usuarioActual() : null;
$contadorCarritoNav = $logueado ? (new CarritoItem())->contarUnidades((int)$_SESSION['usuario_id']) : 0;
?>
<header class="site-header">
  <div class="hdr-inner">

    <!-- Logo -->
    <a href="/spectre_erp/index.php" class="site-logo">
      <img src="/spectre_erp/assets/logo.png"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
           alt="SPECTRE" class="logo-img">
      <span class="logo-fallback">PAF<span>WEB</span></span>
    </a>

    <!-- Buscador -->
    <form class="search-bar" action="/spectre_erp/index.php" method="GET">
      <input type="search" name="q"
             placeholder="Buscar GPUs, monitores, PCs gaming..."
             value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      <button type="submit" aria-label="Buscar">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
    </form>

    <!-- Acciones del usuario -->
    <nav class="hdr-actions">
      <!-- Enlace a Carrito -->
      <a href="/spectre_erp/src/modules/productos/views/carrito.php" class="hdr-link" id="cartLink">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <span class="hdr-link-text">Carrito</span>
        <span id="cartCount" class="cart-badge" style="display:<?= $contadorCarritoNav > 0 ? 'flex' : 'none' ?>;">
          <?= $contadorCarritoNav ?>
        </span>
      </a>

      <!-- Enlace a Soporte TI (visible para todos, pero controlado en la página) -->
      <a href="/spectre_erp/src/modules/soporte/views/soporte.php" class="hdr-link hdr-link-support">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <span class="hdr-link-text">Soporte TI</span>
      </a>

      <!-- Enlaces específicos según rol (visibles solo si logueado) -->
      <?php if ($logueado): ?>
        <?php if ($rol === 'cliente'): ?>
          <a href="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=mis-tickets" class="hdr-link">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/>
              <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            <span class="hdr-link-text">Mis tickets</span>
          </a>
        <?php elseif (in_array($rol, ['gerente', 'trabajador'], true)): ?>
          <a href="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=panel" class="hdr-link">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/>
              <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            <span class="hdr-link-text">Gestión tickets</span>
          </a>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Menú de usuario -->
      <?php if ($logueado): ?>
        <div class="user-menu">
          <button class="user-btn" onclick="toggleUserMenu()">
            <span class="user-avatar"><?= strtoupper($usuario['nombre'][0]) ?></span>
            <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="m6 9 6 6 6-6"/>
            </svg>
          </button>
          <div class="user-dropdown" id="userDropdown">
            <?php if (esPersonal()): ?>
              <a href="/spectre_erp/src/modules/general/views/dashboard.php">Panel de control</a>
            <?php endif; ?>
            <?php if (in_array($rol, ['gerente', 'trabajador'], true)): ?>
              <a href="/spectre_erp/src/modules/productos/views/nuevo.php">+ Nuevo producto</a>
            <?php endif; ?>
            <a href="/spectre_erp/src/modules/productos/views/carrito.php">Mi carrito</a>
            <?php if ($rol === 'cliente'): ?>
              <a href="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=mis-tickets">Mis tickets</a>
            <?php endif; ?>
            <a href="/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=logout">
              Cerrar sesión
            </a>
          </div>
        </div>
      <?php else: ?>
        <a href="/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=login"
           class="btn-login-hdr">Iniciar sesión</a>
      <?php endif; ?>
    </nav>
  </div>

  <!-- Categorías -->
  <nav class="cat-bar">
    <a href="/spectre_erp/index.php?cat=gpus">Tarjetas Gráficas</a>
    <a href="/spectre_erp/index.php?cat=monitores">Monitores</a>
    <a href="/spectre_erp/index.php?cat=pcs">PCs Gaming</a>
    <a href="/spectre_erp/index.php?cat=perifericos">Periféricos</a>
    <a href="/spectre_erp/index.php?cat=memorias">RAM &amp; Storage</a>
    <a href="/spectre_erp/index.php?cat=refrigeracion">Refrigeración</a>
    <a href="/spectre_erp/index.php?cat=fuentes">Fuentes de poder</a>
    <a href="/spectre_erp/index.php?cat=gabinetes">Gabinetes</a>
  </nav>
</header>

<script>
function toggleUserMenu() {
  document.getElementById('userDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.user-menu')) {
    document.getElementById('userDropdown')?.classList.remove('open');
  }
});
</script>