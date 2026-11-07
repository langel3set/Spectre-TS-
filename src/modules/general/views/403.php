<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso denegado — SPECTRE</title>
  <style>
    :root { --bg:#08090d; --grad:linear-gradient(135deg,#4f6ef7,#7c3aed); --text:#e8eaf0; --muted:#6b7280; }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { background:var(--bg); color:var(--text); font-family:'Segoe UI',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .box { text-align:center; padding:3rem 2rem; }
    .num { font-size:7rem; font-weight:900; background:var(--grad); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    h1 { font-size:1.1rem; color:#fff; margin:.75rem 0 .5rem; }
    p  { font-size:.85rem; color:var(--muted); margin-bottom:1.5rem; }
    a  { padding:.55rem 1.3rem; background:var(--grad); color:#fff; border-radius:7px; font-size:.85rem; font-weight:600; }
  </style>
</head>
<body>
  <div class="box">
    <div class="num">403</div>
    <h1>Acceso denegado</h1>
    <p>No tienes permiso para ver esta sección.</p>
    <a href="/spectre_erp/index.php">Volver a la tienda</a>
  </div>
</body>
</html>
