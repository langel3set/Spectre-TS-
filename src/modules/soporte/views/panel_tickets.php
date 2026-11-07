<?php
// Ya vienen $tickets
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de tickets</title>
  <style>
    body { background: #08090d; color: #e8eaf0; font-family: 'Segoe UI', sans-serif; padding: 2rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: .8rem; text-align: left; border-bottom: 1px solid rgba(99,102,241,.1); }
    th { color: #6b7280; }
    .estado { display: inline-block; padding: .2rem .8rem; border-radius: 20px; font-size: .75rem; }
    .pendiente { background: #f59e0b20; color: #f59e0b; }
    .en_proceso { background: #3b82f620; color: #3b82f6; }
    .resuelto { background: #10b98120; color: #10b981; }
    .cerrado { background: #6b728020; color: #6b7280; }
    a { color: #4f6ef7; }
  </style>
</head>
<body>
<h1>Panel de tickets</h1>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Cliente</th>
      <th>Asunto</th>
      <th>Prioridad</th>
      <th>Estado</th>
      <th>Fecha</th>
      <th>Acción</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><?= $t['id_ticket'] ?></td>
        <td><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellido']) ?></td>
        <td><?= htmlspecialchars($t['titulo']) ?></td>
        <td><?= ucfirst($t['prioridad']) ?></td>
        <td><span class="estado <?= $t['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $t['estado'])) ?></span></td>
        <td><?= date('d/m/Y H:i', strtotime($t['fecha_creacion'])) ?></td>
        <td><a href="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=detalle&id=<?= $t['id_ticket'] ?>">Ver</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>