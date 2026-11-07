<?php
require_once __DIR__ . '/../../../config/auth.php';
iniciarSesionSegura();
if (rolActual() !== 'cliente') {
    header('Location: /spectre_erp/index.php');
    exit;
}
// Los tickets ya están en $tickets (pasados desde el controlador)
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mis tickets</title>
  <style>
    body { background: #08090d; color: #e8eaf0; font-family: 'Segoe UI', sans-serif; padding: 2rem; }
    .container { max-width: 800px; margin: 0 auto; }
    .ticket-item {
      background: #0e1018;
      border: 1px solid rgba(99,102,241,.2);
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1rem;
    }
    .ticket-item .estado {
      display: inline-block;
      padding: .2rem .8rem;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 600;
    }
    .estado.pendiente { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b40; }
    .estado.en_proceso { background: #3b82f620; color: #3b82f6; border: 1px solid #3b82f640; }
    .estado.resuelto { background: #10b98120; color: #10b981; border: 1px solid #10b98140; }
    .estado.cerrado { background: #6b728020; color: #6b7280; border: 1px solid #6b728040; }
    a { color: #4f6ef7; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="container">
  <h1>Mis tickets de soporte</h1>
  <a href="/spectre_erp/index.php">← Volver a la tienda</a>
  <br><br>
  <a href="/spectre_erp/src/modules/soporte/views/soporte.php" style="display:inline-block; background:#4f6ef7; color:#fff; padding:.5rem 1rem; border-radius:7px;">Nuevo ticket</a>

  <?php if (empty($tickets)): ?>
    <p style="color: #6b7280; margin-top: 2rem;">No tienes tickets aún.</p>
  <?php else: ?>
    <?php foreach ($tickets as $t): ?>
      <div class="ticket-item">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <h3><?= htmlspecialchars($t['titulo']) ?></h3>
          <span class="estado <?= $t['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $t['estado'])) ?></span>
        </div>
        <p style="color: #6b7280; font-size: .85rem;"><?= date('d/m/Y H:i', strtotime($t['fecha_creacion'])) ?></p>
        <a href="/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=detalle&id=<?= $t['id_ticket'] ?>">Ver detalle</a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>