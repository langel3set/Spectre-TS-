<?php
http_response_code(404);
require_once __DIR__ . '/../../../../autoload.php';
iniciarSesionSegura();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página no encontrada — SPECTRE</title>
  <style>
    :root {
      --bg:#08090d; --bg2:#0e1018; --bg3:#141620;
      --border:rgba(99,102,241,.2);
      --blue:#4f6ef7; --violet:#7c3aed;
      --grad:linear-gradient(135deg,#4f6ef7,#7c3aed);
      --text:#e8eaf0; --muted:#6b7280;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body {
      background:var(--bg); color:var(--text);
      font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh;
      display:flex; align-items:center; justify-content:center;
    }
    /* fondo grid sutil */
    body::before {
      content:''; position:fixed; inset:0; pointer-events:none;
      background-image:
        linear-gradient(rgba(79,110,247,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79,110,247,.03) 1px, transparent 1px);
      background-size:48px 48px;
    }
    .box {
      text-align:center; padding:3rem 2rem;
      max-width:480px; position:relative;
    }
    /* Número 404 grande */
    .num {
      font-size:clamp(5rem,18vw,8rem);
      font-weight:900; letter-spacing:-.02em; line-height:1;
      background:var(--grad); -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      margin-bottom:.5rem; position:relative;
    }
    /* Icono gamepad decorativo */
    .num::after {
      content:'';
      position:absolute; bottom:-12px; left:50%; transform:translateX(-50%);
      width:80px; height:2px;
      background:var(--grad); border-radius:2px; opacity:.6;
    }
    h1 {
      font-size:1.15rem; font-weight:700; color:#fff;
      margin:1.5rem 0 .6rem;
    }
    p {
      font-size:.87rem; color:var(--muted); line-height:1.65;
      margin-bottom:2rem;
    }
    .actions { display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap; }
    .btn-primary {
      padding:.6rem 1.4rem; background:var(--grad);
      color:#fff; font-weight:600; font-size:.88rem;
      border-radius:8px; transition:opacity .2s; text-decoration:none;
    }
    .btn-primary:hover { opacity:.85; }
    .btn-secondary {
      padding:.6rem 1.4rem; background:var(--bg2);
      border:1px solid var(--border);
      color:var(--muted); font-size:.88rem;
      border-radius:8px; transition:color .2s, border-color .2s; text-decoration:none;
    }
    .btn-secondary:hover { color:var(--text); border-color:var(--blue); }

    /* Quick links */
    .quick {
      margin-top:2.5rem; padding-top:2rem;
      border-top:1px solid var(--border);
    }
    .quick p { font-size:.75rem; margin-bottom:.75rem; }
    .quick-links { display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap; }
    .quick-links a {
      padding:.3rem .85rem; background:var(--bg2);
      border:1px solid var(--border); border-radius:6px;
      font-size:.78rem; color:var(--muted);
      transition:color .2s, border-color .2s; text-decoration:none;
    }
    .quick-links a:hover { color:var(--text); border-color:var(--blue); }
  </style>
</head>
<body>

<div class="box">
  <div class="num">404</div>

  <h1>Esta página no existe</h1>
  <p>
    La URL que buscas no fue encontrada.<br>
    Puede que haya sido movida, eliminada o que hayas escrito mal la dirección.
  </p>

  <div class="actions">
    <a href="/spectre_erp/index.php" class="btn-primary">Ir a la tienda</a>
    <a href="javascript:history.back()" class="btn-secondary">Volver atrás</a>
  </div>

  <div class="quick">
    <p>O ve directamente a:</p>
    <div class="quick-links">
      <a href="/spectre_erp/index.php?cat=gpus">Tarjetas Gráficas</a>
      <a href="/spectre_erp/index.php?cat=monitores">Monitores</a>
      <a href="/spectre_erp/index.php?cat=pcs">PCs Gaming</a>
      <a href="/spectre_erp/src/modules/soporte/views/soporte.php">Soporte TI</a>
    </div>
  </div>
</div>

</body>
</html>
