<?php
// Ya vienen $ticket, $adjuntos, $historial desde el controlador
if (!$ticket) die('Ticket no encontrado.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ticket #<?= $ticket['id_ticket'] ?></title>
  <style>
    body { background: #08090d; color: #e8eaf0; font-family: 'Segoe UI', sans-serif; padding: 2rem; }
    .container { max-width: 800px; margin: 0 auto; }
    .card { background: #0e1018; border: 1px solid rgba(99,102,241,.2); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .estado { display: inline-block; padding: .2rem .8rem; border-radius: 20px; font-size: .75rem; font-weight: 600; }
    .estado.pendiente { background: #f59e0b20; color: #f59e0b; }
    .estado.en_proceso { background: #3b82f620; color: #3b82f6; }
    .estado.resuelto { background: #10b98120; color: #10b981; }
    .estado.cerrado { background: #6b728020; color: #6b7280; }
    .comentario { border-left: 3px solid #4f6ef7; padding-left: 1rem; margin: 1rem 0; }
    .comentario .meta { color: #6b7280; font-size: .8rem; }
    .adjunto { display: inline-block; background: #141620; padding: .3rem .8rem; border-radius: 5px; margin: .2rem; }
    a { color: #4f6ef7; text-decoration: none; }
  </style>
</head>
<body>
<div class="container">
  <h1>Ticket #<?= $ticket['id_ticket'] ?></h1>
  <p><strong>Asunto:</strong> <?= htmlspecialchars($ticket['titulo']) ?></p>
  <p><strong>Estado:</strong> <span class="estado <?= $ticket['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $ticket['estado'])) ?></span></p>
  <p><strong>Prioridad:</strong> <?= ucfirst($ticket['prioridad']) ?></p>
  <p><strong>Descripción:</strong><br><?= nl2br(htmlspecialchars($ticket['descripcion_problema'])) ?></p>

  <?php if (!empty($adjuntos)): ?>
    <h3>Archivos adjuntos</h3>
    <?php foreach ($adjuntos as $a): ?>
      <div class="adjunto">
        <a href="<?= htmlspecialchars($a['ruta_archivo']) ?>" target="_blank"><?= htmlspecialchars($a['nombre_original']) ?></a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3>Historial</h3>
  <?php foreach ($historial as $h): ?>
    <div class="comentario">
      <div class="meta"><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?> - <strong><?= htmlspecialchars($h['nombre'] ?? 'Sistema') ?></strong></div>
      <p><?= nl2br(htmlspecialchars($h['comentario'])) ?></p>
    </div>
  <?php endforeach; ?>

  <a href="/spectre_erp/src/modules/soporte/views/mis_tickets.php">← Volver a mis tickets</a>
</div>
</body>
</html>