<?php
// Ya vienen $ticket, $adjuntos, $historial desde el controlador
if (!$ticket) die('Ticket no encontrado.');
$csrf_token = csrf_token();

// Manejar mensajes de éxito/error desde la sesión (opcional, pero lo haré simple con GET)
$mensaje = $_GET['mensaje'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket #<?= $ticket['id_ticket'] ?> - Panel</title>
  <style>
    /* Estilos generales del panel (similares a dashboard) */
    :root {
      --bg: #07080c;
      --bg2: #0f1119;
      --bg3: #161924;
      --border: rgba(99,102,241,.15);
      --border2: rgba(99,102,241,.28);
      --blue: #4f6ef7;
      --violet: #7c3aed;
      --grad: linear-gradient(135deg,#4f6ef7,#7c3aed);
      --text: #e2e5f0;
      --muted: #5a6280;
      --muted2: #8890aa;
      --radius: 10px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', system-ui, sans-serif;
      padding: 2rem;
    }
    .container {
      max-width: 900px;
      margin: 0 auto;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
    }
    h1, h2, h3 { color: #fff; }
    h1 { font-size: 1.5rem; margin-bottom: 1rem; }
    h3 { margin: 1.5rem 0 0.8rem; }
    p { margin-bottom: 0.5rem; }
    strong { color: var(--text); }
    .estado {
      display: inline-block;
      padding: .2rem .8rem;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 600;
    }
    .estado.pendiente { background: #f59e0b20; color: #f59e0b; }
    .estado.en_proceso { background: #3b82f620; color: #3b82f6; }
    .estado.resuelto { background: #10b98120; color: #10b981; }
    .estado.cerrado { background: #6b728020; color: #6b7280; }
    .comentario {
      border-left: 3px solid var(--blue);
      padding-left: 1rem;
      margin: 1rem 0;
      background: var(--bg3);
      padding: 0.8rem 1rem;
      border-radius: 0 var(--radius) var(--radius) 0;
    }
    .comentario .meta {
      color: var(--muted2);
      font-size: .8rem;
      margin-bottom: .3rem;
    }
    .adjunto {
      display: inline-block;
      background: var(--bg3);
      padding: .3rem .8rem;
      border-radius: 5px;
      margin: .2rem;
    }
    a { color: var(--blue); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* Formulario de respuesta */
    .respuesta-form {
      margin-top: 2rem;
      background: var(--bg3);
      padding: 1.5rem;
      border-radius: var(--radius);
      border: 1px solid var(--border);
    }
    .respuesta-form h3 {
      margin-top: 0;
      margin-bottom: 1rem;
    }
    .respuesta-form label {
      display: block;
      font-size: .8rem;
      color: var(--muted2);
      margin: .8rem 0 .3rem;
    }
    .respuesta-form textarea,
    .respuesta-form select {
      width: 100%;
      background: var(--bg2);
      border: 1px solid var(--border);
      color: var(--text);
      padding: .6rem;
      border-radius: 7px;
      font-size: .9rem;
      font-family: inherit;
    }
    .respuesta-form textarea:focus,
    .respuesta-form select:focus {
      outline: none;
      border-color: var(--blue);
    }
    .btn {
      background: var(--grad);
      color: #fff;
      border: none;
      padding: .6rem 1.5rem;
      border-radius: 7px;
      cursor: pointer;
      font-weight: 600;
      margin-top: 1rem;
    }
    .btn:hover { opacity: .9; }

    .mensaje {
      padding: .8rem 1rem;
      border-radius: 7px;
      margin-bottom: 1rem;
    }
    .mensaje.ok {
      background: rgba(16,185,129,.12);
      border: 1px solid rgba(16,185,129,.3);
      color: #34d399;
    }
    .mensaje.err {
      background: rgba(239,68,68,.12);
      border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5;
    }
    .btn-volver {
      display: inline-block;
      margin-top: 1.5rem;
      color: var(--muted2);
    }
    .btn-volver:hover { color: var(--text); }
  </style>
</head>
<body>
<div class="container">
  <!-- Mensaje de éxito/error -->
  <?php if ($mensaje): ?>
    <div class="mensaje ok"><?= htmlspecialchars($mensaje) ?></div>
  <?php elseif ($error): ?>
    <div class="mensaje err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <h1>Ticket #<?= $ticket['id_ticket'] ?></h1>
  <p><strong>Cliente:</strong> <?= htmlspecialchars($ticket['nombre'] . ' ' . $ticket['apellido']) ?></p>
  <p><strong>Correo:</strong> <?= htmlspecialchars($ticket['correo']) ?></p>
  <p><strong>Asunto:</strong> <?= htmlspecialchars($ticket['titulo']) ?></p>
  <p><strong>Estado:</strong> <span class="estado <?= $ticket['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $ticket['estado'])) ?></span></p>
  <p><strong>Prioridad:</strong> <?= ucfirst($ticket['prioridad']) ?></p>
  <p><strong>Descripción:</strong><br><?= nl2br(htmlspecialchars($ticket['descripcion_problema'])) ?></p>

  <?php if (!empty($adjuntos)): ?>
    <h3>Archivos adjuntos</h3>
    <?php foreach ($adjuntos as $a): ?>
      <div class="adjunto"><a href="<?= htmlspecialchars($a['ruta_archivo']) ?>" target="_blank"><?= htmlspecialchars($a['nombre_original']) ?></a></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3>Historial</h3>
  <?php foreach ($historial as $h): ?>
    <div class="comentario">
      <div class="meta"><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?> - <strong><?= htmlspecialchars($h['nombre'] ?? 'Sistema') ?></strong></div>
      <p><?= nl2br(htmlspecialchars($h['comentario'])) ?></p>
    </div>
  <?php endforeach; ?>

  <!-- Formulario de respuesta (acción mediante campo oculto) -->
  <div class="respuesta-form">
    <h3>Responder ticket</h3>
    <form method="POST" action="/spectre_erp/src/modules/soporte/controllers/TicketController.php">
      <input type="hidden" name="accion" value="responder">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <input type="hidden" name="id_ticket" value="<?= $ticket['id_ticket'] ?>">

      <div>
        <label>Cambiar estado</label>
        <select name="estado">
          <option value="pendiente" <?= $ticket['estado']=='pendiente'?'selected':'' ?>>Pendiente</option>
          <option value="en_proceso" <?= $ticket['estado']=='en_proceso'?'selected':'' ?>>En proceso</option>
          <option value="resuelto" <?= $ticket['estado']=='resuelto'?'selected':'' ?>>Resuelto</option>
          <option value="cerrado" <?= $ticket['estado']=='cerrado'?'selected':'' ?>>Cerrado</option>
        </select>
      </div>
      <div>
        <label>Comentario / Respuesta *</label>
        <textarea name="comentario" rows="4" required></textarea>
      </div>
      <div>
        <label>Solución (si se resuelve)</label>
        <textarea name="solucion" rows="2"></textarea>
      </div>
      <button type="submit" class="btn">Enviar respuesta</button>
    </form>
  </div>

  <a href="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=panel" class="btn-volver">← Volver al panel de tickets</a>
</div>
</body>
</html>