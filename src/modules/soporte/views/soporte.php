<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/csrf.php';
iniciarSesionSegura();

// Solo clientes logueados
if (!usuarioLogueado() || rolActual() !== 'cliente') {
    header('Location: /spectre_erp/src/modules/usuarios/views/login.php');
    exit;
}

$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Soporte TI - SPECTRE</title>
  <style>
    :root {
      --bg:#08090d; --bg2:#0e1018; --bg3:#141620;
      --border:rgba(99,102,241,.2);
      --blue:#4f6ef7; --violet:#7c3aed;
      --grad:linear-gradient(135deg,#4f6ef7,#7c3aed);
      --text:#e8eaf0; --muted:#6b7280;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; }
    a { text-decoration:none; color:inherit; }

    .site-header {
      position:sticky; top:0; z-index:100;
      background:rgba(8,9,13,.95); backdrop-filter:blur(12px);
      border-bottom:1px solid var(--border);
    }
    .hdr-inner {
      display:flex; align-items:center; gap:1rem;
      max-width:1200px; margin:0 auto; padding:.7rem 1.5rem;
    }
    .site-logo { display:flex; align-items:center; }
    .logo-img  { height:34px; }
    .logo-fallback {
      display:none; align-items:center;
      font-size:1.2rem; font-weight:800; letter-spacing:.06em; color:#fff;
    }
    .logo-fallback span { background:var(--grad); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .hdr-actions { margin-left:auto; display:flex; gap:.75rem; align-items:center; }
    .hdr-link {
      font-size:.82rem; color:var(--muted); padding:.4rem .8rem;
      border-radius:6px; transition:color .2s, background .2s;
    }
    .hdr-link:hover { color:var(--text); background:var(--bg3); }
    .btn-login-hdr {
      padding:.45rem 1.1rem; background:var(--grad); color:#fff;
      font-size:.85rem; font-weight:600; border-radius:7px;
    }

    .page { max-width:760px; margin:0 auto; padding:3rem 1.5rem; }

    .page-hero { margin-bottom:2.5rem; }
    .page-hero h1 {
      font-size:1.7rem; font-weight:800; color:#fff; margin-bottom:.5rem;
    }
    .page-hero h1 span { background:var(--grad); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .page-hero p { font-size:.9rem; color:var(--muted); line-height:1.6; }

    .info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin-bottom:2rem; }
    .info-card {
      background:var(--bg2); border:1px solid var(--border);
      border-radius:10px; padding:1rem;
      display:flex; flex-direction:column; gap:.3rem;
    }
    .info-icon { font-size:1.2rem; }
    .info-label { font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
    .info-value { font-size:.85rem; color:var(--text); font-weight:600; }

    .ticket-form {
      background:var(--bg2); border:1px solid var(--border);
      border-radius:12px; padding:2rem; position:relative;
    }
    .ticket-form::before {
      content:''; position:absolute; top:0; left:15%; right:15%;
      height:2px; background:var(--grad); border-radius:2px;
    }
    .form-title { font-size:1rem; font-weight:700; color:#fff; margin-bottom:1.5rem; }

    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .campo { display:flex; flex-direction:column; gap:.4rem; }
    .campo.full { grid-column:1/-1; }
    label { font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
    input[type="text"], input[type="email"], select, textarea {
      background:var(--bg3); border:1px solid var(--border);
      border-radius:7px; color:var(--text); font-size:.9rem;
      padding:.6rem .9rem; outline:none; transition:border-color .2s;
      font-family:inherit; width:100%;
    }
    input:focus, select:focus, textarea:focus { border-color:var(--blue); }
    select option { background:var(--bg3); }
    textarea { resize:vertical; min-height:120px; }

    .prioridad-row { display:flex; gap:.5rem; }
    .prio-btn {
      flex:1; padding:.5rem; border-radius:7px; cursor:pointer;
      font-size:.78rem; font-weight:600; transition:all .2s; text-align:center;
      border:1px solid var(--border); background:var(--bg3); color:var(--muted);
    }
    .prio-btn.baja.active   { border-color:#10b981; background:rgba(16,185,129,.12); color:#10b981; }
    .prio-btn.media.active  { border-color:#f59e0b; background:rgba(245,158,11,.12); color:#f59e0b; }
    .prio-btn.alta.active   { border-color:#ef4444; background:rgba(239,68,68,.12);  color:#ef4444; }
    .prio-btn.critica.active { border-color:var(--violet); background:rgba(124,58,237,.15); color:#a78bfa; }

    .file-input { margin-top: .5rem; }
    .file-input input[type="file"] {
      background:var(--bg3); border:1px solid var(--border);
      border-radius:7px; padding:.6rem; width:100%;
      color:var(--text); font-size:.9rem;
    }
    .file-input small { color:var(--muted); font-size:.75rem; display:block; margin-top:.3rem; }

    .error-msg {
      background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.3);
      border-radius:7px; color:#f87171; font-size:.83rem;
      padding:.6rem 1rem; margin-bottom:1rem;
      display:none;
    }
    .btn-submit {
      width:100%; margin-top:1.5rem; padding:.75rem;
      background:var(--grad); color:#fff; font-weight:700;
      font-size:.95rem; border:none; border-radius:8px; cursor:pointer;
      transition:opacity .2s;
    }
    .btn-submit:hover { opacity:.88; }
    .btn-submit:disabled { opacity:.5; cursor:not-allowed; }

    .loading-spinner {
      display:inline-block; width:20px; height:20px;
      border:3px solid rgba(255,255,255,.3);
      border-radius:50%;
      border-top-color:#fff;
      animation:spin .8s linear infinite;
      vertical-align:middle;
      margin-right:8px;
    }
    @keyframes spin { to { transform:rotate(360deg); } }

    @media(max-width:600px) {
      .form-grid  { grid-template-columns:1fr; }
      .info-grid  { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<header class="site-header">
  <div class="hdr-inner">
    <a href="/spectre_erp/index.php" class="site-logo">
      <img src="/spectre_erp/assets/logo.png"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
           alt="SPECTRE" class="logo-img">
      <span class="logo-fallback">PAF<span>WEB</span></span>
    </a>
    <nav class="hdr-actions">
      <a href="/spectre_erp/index.php" class="hdr-link">← Tienda</a>
      <?php if (usuarioLogueado()): ?>
        <a href="/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=logout" class="hdr-link">Cerrar sesión</a>
      <?php else: ?>
        <a href="/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=login" class="btn-login-hdr">Iniciar sesión</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="page">

  <div class="page-hero">
    <h1>Soporte <span>Técnico TI</span></h1>
    <p>¿Problemas con tu equipo o pedido? Describe tu caso y un técnico se pondrá en contacto contigo.</p>
  </div>

  <div class="info-grid">
    <div class="info-card">
      <span class="info-icon">⏱</span>
      <span class="info-label">Tiempo de respuesta</span>
      <span class="info-value">2–4 horas hábiles</span>
    </div>
    <div class="info-card">
      <span class="info-icon">📞</span>
      <span class="info-label">Soporte directo</span>
      <span class="info-value">Lun–Vie 9am–6pm</span>
    </div>
    <div class="info-card">
      <span class="info-icon">🎟</span>
      <span class="info-label">Seguimiento</span>
      <span class="info-value">Por número de ticket</span>
    </div>
  </div>

  <div class="ticket-form">
    <h2 class="form-title">Abrir nuevo ticket</h2>

    <div class="error-msg" id="errorMsg"></div>

    <form id="ticketForm" method="POST" enctype="multipart/form-data" action="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=crear">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="form-grid">
        <div class="campo full">
          <label for="asunto">Asunto del problema *</label>
          <input type="text" id="asunto" name="asunto" placeholder="Ej: Mi GPU no es reconocida..." required>
        </div>
        <div class="campo full">
          <label>Prioridad</label>
          <div class="prioridad-row">
            <?php
            $prio = $_POST['prioridad'] ?? 'media';
            foreach (['baja'=>'Baja','media'=>'Media','alta'=>'Alta','critica'=>'Crítica'] as $v => $l):
            ?>
              <label class="prio-btn <?= $v ?> <?= $prio === $v ? 'active' : '' ?>"
                     onclick="setPrio('<?= $v ?>')">
                <?= $l ?>
              </label>
            <?php endforeach; ?>
            <input type="hidden" id="prioridad" name="prioridad" value="<?= htmlspecialchars($prio) ?>">
          </div>
        </div>
        <div class="campo full">
          <label for="problema">Descripción detallada *</label>
          <textarea id="problema" name="problema" required
                    placeholder="Describe el problema con el mayor detalle posible: qué pasó, cuándo, qué equipos están involucrados..."></textarea>
        </div>
        <div class="campo full">
          <label for="tipo">Tipo de problema</label>
          <select name="tipo" id="tipo">
            <option value="soporte">Soporte técnico</option>
            <option value="garantia">Garantía</option>
            <option value="consulta">Consulta</option>
          </select>
        </div>
        <div class="campo full">
          <label for="adjuntos">Adjuntar archivos (imágenes, videos, documentos)</label>
          <div class="file-input">
            <input type="file" name="adjuntos[]" id="adjuntos" multiple accept="image/*,video/*,.pdf,.doc,.docx">
            <small>Tamaño máximo: 50 MB por archivo. Formatos permitidos: JPG, PNG, GIF, MP4, MOV, AVI, PDF, DOC, DOCX.</small>
          </div>
        </div>
      </div>
      <button type="submit" class="btn-submit" id="submitBtn">Enviar ticket</button>
    </form>
  </div>

</div>

<script>
function setPrio(val) {
  document.querySelectorAll('.prio-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.prio-btn.' + val).classList.add('active');
  document.getElementById('prioridad').value = val;
}

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('ticketForm');
  const submitBtn = document.getElementById('submitBtn');
  const errorMsg = document.getElementById('errorMsg');

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Mostrar loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading-spinner"></span> Enviando...';
    errorMsg.style.display = 'none';

    const formData = new FormData(form);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        // Redirigir si hay redirect
        if (data.redirect) {
          window.location.href = data.redirect;
        } else {
          // Si no hay redirect, recargar o mostrar mensaje
          window.location.href = '/spectre_erp/src/modules/soporte/views/mis_tickets.php';
        }
      } else {
        // Mostrar error
        errorMsg.textContent = data.message || 'Error al enviar el ticket.';
        errorMsg.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Enviar ticket';
      }
    } catch (error) {
      console.error('Error:', error);
      errorMsg.textContent = 'Error de red. Inténtalo de nuevo.';
      errorMsg.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Enviar ticket';
    }
  });
});
</script>
</body>
</html>