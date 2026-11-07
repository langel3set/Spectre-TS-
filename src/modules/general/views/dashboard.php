<?php
require_once __DIR__ . '/../../../../autoload.php';
require_once __DIR__ . '/../../../../src/config/auth.php';

requiereLogin();
requiereRol('gerente', 'trabajador'); // solo personal

$usuario = usuarioActual();
$rol     = $usuario['rol'];

// Módulos según rol — cada uno tiene su "panel" que carga en el contenido principal
$modulos = [
  'inicio'     => ['icono'=>'home',       'label'=>'Inicio',       'roles'=>['gerente','trabajador']],
  'inventario' => ['icono'=>'box',        'label'=>'Inventario',   'roles'=>['gerente','trabajador']],
  'servicios'  => ['icono'=>'wrench',     'label'=>'Servicios',    'roles'=>['gerente','trabajador']],
  'notas'      => ['icono'=>'libro',      'label'=>'Base de Conocimiento', 'roles'=>['gerente','trabajador']],
  'ventas'     => ['icono'=>'cart',       'label'=>'Ventas',       'roles'=>['gerente', 'trabajador']], // Cambiado: ahora también trabajador
  'gastos'     => ['icono'=>'money',      'label'=>'Gastos',       'roles'=>['gerente','trabajador']],
  'soporte'    => ['icono'=>'ticket',     'label'=>'Soporte TI',   'roles'=>['gerente','trabajador']],
  'reportes'   => ['icono'=>'chart',      'label'=>'Reportes',     'roles'=>['gerente']],
  'clientes'   => ['icono'=>'users',      'label'=>'Clientes',     'roles'=>['gerente','trabajador']],
  'usuarios'   => ['icono'=>'shield',     'label'=>'Usuarios',     'roles'=>['gerente']],
];

$visibles = array_filter($modulos, fn($m) => in_array($rol, $m['roles'], true));

$etiquetas = ['gerente'=>'Gerente','trabajador'=>'Trabajador','cliente'=>'cliente'];
$etiquetaRol = $etiquetas[$rol] ?? $rol;
$inicialNombre = strtoupper(mb_substr($usuario['nombre'], 0, 1));

function iconoSvg(string $nombre): string {
    $iconos = [
        'home' => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'box'  => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'cart' => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
        'ticket'=> '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="m10 14 9-9"/></svg>',
        'chart' => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'users' => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'shield'=> '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'wrench'=> '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 1 5.4-5.4l-3-3z"/><path d="M14.7 6.3 18 3l3 3-3.3 3.3"/></svg>',
        'money' => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 6v0M18 18v0"/></svg>',
        'libro' => '<svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
    ];
    return $iconos[$nombre] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de control — SPECTRE</title>
  <style>
    /* ── Variables ─────────────────────────────────────────── */
    :root {
      --bg:       #07080c;
      --sidebar:  #0b0d14;
      --bg2:      #0f1119;
      --bg3:      #161924;
      --border:   rgba(99,102,241,.15);
      --border2:  rgba(99,102,241,.28);
      --blue:     #4f6ef7;
      --violet:   #7c3aed;
      --grad:     linear-gradient(135deg,#4f6ef7,#7c3aed);
      --grad-v:   linear-gradient(180deg,#4f6ef7,#7c3aed);
      --text:     #e2e5f0;
      --muted:    #5a6280;
      --muted2:   #8890aa;
      --sidebar-w: 240px;
      --radius:   10px;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    html, body { height:100%; }
    body {
      background:var(--bg);
      color:var(--text);
      font-family:'Segoe UI', system-ui, -apple-system, sans-serif;
      display:flex;
      overflow:hidden;
    }
    a { text-decoration:none; color:inherit; }

    /* ── SIDEBAR ──────────────────────────────────────────── */
    .sidebar {
      width: var(--sidebar-w);
      min-width: var(--sidebar-w);
      height: 100vh;
      background: var(--sidebar);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      position: relative;
      z-index: 10;
    }

    /* Glow sutil en el borde derecho */
    .sidebar::after {
      content:'';
      position:absolute; top:0; right:-1px; bottom:0;
      width:1px;
      background: linear-gradient(180deg, transparent 0%, rgba(99,102,241,.5) 40%, rgba(124,58,237,.4) 70%, transparent 100%);
      pointer-events:none;
    }

    /* ── Perfil de usuario ─────────────────────────────────── */
    .user-profile {
      padding: 1.5rem 1.2rem 1.2rem;
      border-bottom: 1px solid var(--border);
      position: relative;
    }

    /* Fondo decorativo detrás del perfil */
    .user-profile::before {
      content:'';
      position:absolute; top:0; left:0; right:0; height:60px;
      background: linear-gradient(180deg, rgba(79,110,247,.08) 0%, transparent 100%);
      pointer-events:none;
    }

    .avatar-wrap {
      display: flex;
      align-items: center;
      gap: .85rem;
      margin-bottom: .85rem;
    }

    .avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: var(--grad);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; font-weight: 800; color: #fff;
      flex-shrink: 0;
      position: relative;
      /* Anillo exterior */
      box-shadow: 0 0 0 2px var(--sidebar), 0 0 0 3.5px rgba(79,110,247,.5);
    }

    /* Indicador online */
    .avatar::after {
      content:'';
      position:absolute; bottom:1px; right:1px;
      width:9px; height:9px; border-radius:50%;
      background:#10b981;
      border:2px solid var(--sidebar);
    }

    .user-info { min-width:0; }
    .user-name {
      font-size:.9rem; font-weight:700; color:var(--text);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .user-email {
      font-size:.68rem; color:var(--muted);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
      margin-top:.1rem;
    }

    /* Badge de rol */
    .rol-badge {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      font-size: .65rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: .25rem .65rem;
      border-radius: 20px;
      border: 1px solid rgba(99,102,241,.3);
      background: rgba(99,102,241,.1);
      color: #818cf8;
    }
    .rol-badge .dot {
      width: 5px; height: 5px; border-radius: 50%;
      background: var(--blue);
    }

    /* ── Navegación ────────────────────────────────────────── */
    .nav-section { flex: 1; overflow-y: auto; padding: .75rem 0; scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
    .nav-label {
      font-size: .6rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: var(--muted);
      padding: .5rem 1.2rem .25rem;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .6rem 1.2rem;
      margin: .1rem .6rem;
      border-radius: 8px;
      font-size: .85rem;
      color: var(--muted2);
      cursor: pointer;
      transition: background .15s, color .15s;
      user-select: none;
      border: 1px solid transparent;
      position: relative;
    }
    .nav-item:hover {
      background: var(--bg3);
      color: var(--text);
    }
    .nav-item.active {
      background: rgba(79,110,247,.12);
      color: #818cf8;
      border-color: rgba(79,110,247,.2);
      font-weight: 600;
    }
    /* Línea izquierda activo */
    .nav-item.active::before {
      content:'';
      position:absolute; left:-7px; top:20%; bottom:20%;
      width:3px; border-radius:0 2px 2px 0;
      background: var(--grad-v);
    }

    .nav-icon {
      width: 18px; height: 18px;
      flex-shrink: 0;
      opacity: .7;
    }
    .nav-item.active .nav-icon { opacity: 1; }

    /* Separador */
    .nav-divider {
      height: 1px;
      background: var(--border);
      margin: .6rem 1.2rem;
    }

    /* ── Footer sidebar ────────────────────────────────────── */
    .sidebar-footer {
      padding: .9rem 1.2rem;
      border-top: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: .4rem;
    }

    .footer-btn {
      display: flex; align-items: center; gap: .7rem;
      padding: .5rem .75rem; border-radius: 7px;
      font-size: .8rem; color: var(--muted2);
      transition: background .15s, color .15s;
      cursor: pointer;
    }
    .footer-btn:hover { background: var(--bg3); color: var(--text); }
    .footer-btn.danger:hover { color: #f87171; }
    .footer-btn svg { width:16px; height:16px; flex-shrink:0; opacity:.7; }

    /* ── MAIN AREA ─────────────────────────────────────────── */
    .main-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0;
    }

    /* Top bar */
    .topbar {
      height: 56px;
      min-height: 56px;
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 2rem;
      gap: 1rem;
    }

    .topbar-title {
      font-size: .95rem;
      font-weight: 700;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .topbar-title .breadcrumb {
      font-size: .78rem;
      color: var(--muted);
      font-weight: 400;
    }
    .topbar-title .breadcrumb span {
      color: var(--muted2);
    }

    .topbar-right {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: .75rem;
    }

    .topbar-time {
      font-size: .75rem;
      color: var(--muted);
      font-variant-numeric: tabular-nums;
    }

    .btn-tienda {
      display: flex; align-items: center; gap: .4rem;
      padding: .35rem .9rem;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 7px;
      font-size: .78rem;
      color: var(--muted2);
      cursor: pointer;
      transition: color .2s, border-color .2s;
    }
    .btn-tienda:hover { color: var(--text); border-color: var(--blue); }
    .btn-tienda svg { width:14px; height:14px; }

    /* Content area */
    .content {
      flex: 1;
      overflow-y: auto;
      padding: 2rem;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
    }

    /* ── PANELES ───────────────────────────────────────────── */
    .panel { display: none; animation: fadeIn .2s ease; }
    .panel.active { display: block; }

    @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

    /* Panel header */
    .panel-hdr {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 1.75rem;
      flex-wrap: wrap;
      gap: .5rem;
    }
    .panel-hdr h1 {
      font-size: 1.35rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
    }
    .panel-hdr h1 span {
      background: var(--grad);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .panel-hdr p {
      font-size: .82rem;
      color: var(--muted2);
      margin-top: .3rem;
    }

    /* Botón nuevo */
    .btn-nuevo {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .55rem 1.1rem; background: var(--grad); color: #fff;
      font-weight: 600; font-size: .8rem; border-radius: 8px;
      white-space: nowrap;
      border: none;
      cursor: pointer;
      transition: opacity .2s;
    }
    .btn-nuevo:hover { opacity: .85; }

    .btn-pdf {
      display: inline-flex; align-items: center; gap: .3rem;
      padding: .25rem .55rem; background: rgba(239,68,68,.15);
      border: 1px solid rgba(239,68,68,.3); border-radius: 5px;
      color: #f87171; font-size: .7rem; font-weight: 600;
      cursor: pointer;
      transition: background .2s;
    }
    .btn-pdf:hover { background: rgba(239,68,68,.25); }

    /* ── Botones de icono en tablas de acciones (Ventas, Inventario, Servicios, Soporte) ──
       Estas clases se generan dinámicamente desde JS; sin esta definición el <svg>
       queda sin tamaño fijo y el navegador lo colapsa a una línea delgada. */
    .inv-acciones {
      display: flex;
      align-items: center;
      gap: .4rem;
      flex-wrap: nowrap;
    }
    .inv-btn-icono {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      flex: 0 0 auto;
      padding: 0;
      border-radius: 7px;
      border: 1px solid var(--border);
      background: var(--bg3);
      color: var(--muted2);
      cursor: pointer;
      transition: background .15s, color .15s, border-color .15s;
    }
    .inv-btn-icono svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
      display: block;
    }
    .inv-btn-icono:hover {
      color: var(--text);
      border-color: var(--blue);
      background: rgba(79,110,247,.12);
    }
    .inv-btn-icono.danger:hover {
      color: #f87171;
      border-color: rgba(239,68,68,.4);
      background: rgba(239,68,68,.12);
    }

    /* Badge de stock (usado en la tabla de inventario) */
    .inv-stock-badge {
      display: inline-flex;
      align-items: center;
      font-size: .78rem;
      font-weight: 700;
      padding: .15rem .5rem;
      border-radius: 6px;
    }
    .inv-stock-badge.ok      { color: #34d399; background: rgba(16,185,129,.12); }
    .inv-stock-badge.bajo    { color: #fbbf24; background: rgba(245,158,11,.12); }
    .inv-stock-badge.agotado { color: #f87171; background: rgba(239,68,68,.12); }

    /* ── Gráficos circulares (Gastos / Reportes) ─────────────────── */
    .chart-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1rem;
      margin-bottom: 1.25rem;
    }
    .chart-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.1rem 1.3rem;
    }
    .chart-card h3 {
      font-size: .82rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: .75rem;
    }
    .chart-card canvas { max-height: 260px; }
    .chart-empty { text-align:center; color: var(--muted); font-size:.8rem; padding: 2.5rem 0; }

    /* ── Selector de columnas / secciones para exportar ──────────── */
    .export-bar {
      display: flex;
      align-items: center;
      gap: .6rem;
      flex-wrap: wrap;
    }
    .export-popover {
      position: relative;
      display: inline-block;
    }
    .export-menu {
      display: none;
      position: absolute;
      right: 0;
      top: calc(100% + 6px);
      z-index: 40;
      width: 280px;
      background: var(--bg2);
      border: 1px solid var(--border2);
      border-radius: var(--radius);
      padding: .9rem 1rem;
      box-shadow: 0 12px 28px rgba(0,0,0,.45);
    }
    .export-menu.open { display: block; }
    .export-menu h4 {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--muted);
      margin-bottom: .6rem;
    }
    .export-menu label {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-size: .8rem;
      color: var(--muted2);
      padding: .3rem 0;
      cursor: pointer;
    }
    .export-menu label:hover { color: var(--text); }
    .export-menu-actions {
      display: flex;
      gap: .5rem;
      margin-top: .8rem;
      padding-top: .7rem;
      border-top: 1px solid var(--border);
    }
    .btn-mini {
      flex: 1;
      padding: .4rem .6rem;
      border-radius: 6px;
      font-size: .72rem;
      font-weight: 600;
      cursor: pointer;
      border: 1px solid var(--border);
      background: var(--bg3);
      color: var(--muted2);
    }
    .btn-mini:hover { color: var(--text); border-color: var(--blue); }

    /* Stats cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px,1fr));
      gap: 1rem;
      margin-bottom: 1.75rem;
    }
    .stat-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.2rem 1.4rem;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content:'';
      position:absolute; top:0; left:0; right:0; height:2px;
      background: var(--acento, var(--grad));
    }
    .stat-label {
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--muted2);
      margin-bottom: .5rem;
    }
    .stat-value {
      font-size: 1.65rem;
      font-weight: 800;
      color: #fff;
      line-height: 1;
    }
    .stat-sub {
      font-size: .72rem;
      color: var(--muted);
      margin-top: .35rem;
    }
    .stat-icon {
      position: absolute;
      right: 1.2rem;
      top: 50%;
      transform: translateY(-50%);
      opacity: .07;
      font-size: 3rem;
    }

    /* Section card */
    .section-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 1.25rem;
    }
    .section-card-hdr {
      padding: 1rem 1.4rem;
      border-bottom: 1px solid var(--border);
      font-size: .82rem;
      font-weight: 700;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .5rem;
    }
    .section-card-hdr span { font-size: .72rem; color: var(--muted2); font-weight:400; }

    .activity-list { padding: .5rem 0; }
    .activity-item {
      display: flex;
      align-items: center;
      gap: .9rem;
      padding: .65rem 1.4rem;
      transition: background .15s;
    }
    .activity-item:hover { background: var(--bg3); }
    .act-dot {
      width: 8px; height: 8px; border-radius:50%; flex-shrink:0;
    }
    .act-text { flex:1; font-size:.82rem; color:var(--muted2); }
    .act-text strong { color:var(--text); }
    .act-time { font-size:.7rem; color:var(--muted); }

    /* Table */
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th {
      text-align:left; font-size:.68rem; text-transform:uppercase;
      letter-spacing:.08em; color:var(--muted); padding:.65rem 1.4rem;
      border-bottom:1px solid var(--border); font-weight:600;
    }
    .data-table td {
      padding:.75rem 1.4rem; font-size:.82rem; color:var(--muted2);
      border-bottom:1px solid rgba(99,102,241,.06);
    }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--bg3); color:var(--text); }

    /* Badge de estado */
    .status {
      display:inline-flex; align-items:center; gap:.3rem;
      font-size:.65rem; font-weight:700; letter-spacing:.06em;
      text-transform:uppercase; padding:.2rem .55rem; border-radius:20px;
    }
    .status.verde  { background:rgba(16,185,129,.12); color:#34d399; border:1px solid rgba(16,185,129,.25); }
    .status.amarillo { background:rgba(245,158,11,.12); color:#fbbf24; border:1px solid rgba(245,158,11,.25); }
    .status.rojo   { background:rgba(239,68,68,.12);  color:#f87171; border:1px solid rgba(239,68,68,.25); }
    .status.morado { background:rgba(124,58,237,.12); color:#a78bfa; border:1px solid rgba(124,58,237,.25); }
    .status.azul   { background:rgba(79,110,247,.12);  color:#7c96f9; border:1px solid rgba(79,110,247,.25); }

    /* Toolbar */
    .inv-toolbar {
      display: flex; flex-wrap: wrap; gap: .75rem;
      padding: 1rem 1.4rem; border-bottom: 1px solid var(--border);
    }
    .inv-toolbar input[type="text"], .inv-toolbar input[type="date"], .inv-toolbar select {
      background: var(--bg3); border: 1px solid var(--border); color: var(--text);
      padding: .5rem .75rem; border-radius: 7px; font-size: .8rem; font-family: inherit;
    }
    .inv-toolbar input[type="text"] { flex: 1; min-width: 180px; }
    .inv-check { display: flex; align-items: center; gap: .4rem; font-size: .8rem; color: var(--muted2); cursor: pointer; }

    .btn-secundario {
      padding: .5rem 1rem; border-radius: 7px; font-size: .8rem; font-weight: 600;
      cursor: pointer; border: none; background: var(--bg3); color: var(--muted2);
      border: 1px solid var(--border);
    }
    .btn-secundario:hover { color: var(--text); border-color: var(--blue); }

    /* Modal */
    .inv-modal-overlay {
      display: none; position: fixed; inset: 0; background: rgba(4,5,9,.65);
      z-index: 100; align-items: center; justify-content: center; padding: 1rem;
    }
    .inv-modal-overlay.open { display: flex; }
    .inv-modal {
      width: 100%; max-width: 480px; background: var(--bg2);
      border: 1px solid var(--border2); border-radius: var(--radius);
      padding: 1.5rem;
      max-height: 90vh;
      overflow-y: auto;
    }
    .inv-modal h3 { font-size: 1.05rem; margin-bottom: .3rem; }
    .inv-modal-sub { font-size: .78rem; color: var(--muted2); margin-bottom: 1rem; }
    .inv-modal label { display: block; font-size: .72rem; color: var(--muted); margin: .8rem 0 .3rem; }
    .inv-modal input, .inv-modal select, .inv-modal textarea {
      width: 100%; background: var(--bg3); border: 1px solid var(--border); color: var(--text);
      padding: .55rem .7rem; border-radius: 7px; font-size: .85rem; font-family: inherit;
    }
    .inv-modal textarea { resize: vertical; }
    .inv-modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
    .inv-modal-msg { margin-top: .9rem; font-size: .8rem; padding: .55rem .75rem; border-radius: 7px; display: none; }
    .inv-modal-msg.ok { background: rgba(16,185,129,.12); color: #34d399; border: 1px solid rgba(16,185,129,.3); display: block; }
    .inv-modal-msg.err { background: rgba(239,68,68,.12); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); display: block; }
    .inv-modal-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: 1.4rem; }
    .btn-primario, .btn-secundario {
      padding: .55rem 1.1rem; border-radius: 7px; font-size: .82rem; font-weight: 600;
      cursor: pointer; border: none;
    }
    .btn-primario { background: var(--grad); color: #fff; }
    .btn-secundario { background: var(--bg3); color: var(--muted2); border: 1px solid var(--border); }

    /* ══════════════════ MÓDULO NOTAS (Base de Conocimiento) ══════════════════ */
    .notas-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 1.25rem;
      align-items: start;
    }
    @media (max-width: 900px) {
      .notas-layout { grid-template-columns: 1fr; }
    }

    /* Sidebar */
    .notas-sidebar {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      position: sticky;
      top: 0;
    }
    .notas-search input {
      width: 100%; background: var(--bg3); border: 1px solid var(--border); color: var(--text);
      padding: .55rem .75rem; border-radius: 7px; font-size: .82rem; font-family: inherit;
      margin-bottom: .8rem;
    }
    .notas-filtros { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1.1rem; }
    .notas-filtro-chip {
      font-size: .72rem; padding: .35rem .7rem; border-radius: 20px;
      background: var(--bg3); border: 1px solid var(--border); color: var(--muted2);
      cursor: pointer; transition: all .15s; white-space: nowrap;
    }
    .notas-filtro-chip:hover { color: var(--text); border-color: var(--blue); }
    .notas-filtro-chip.active { background: var(--grad); color: #fff; border-color: transparent; }

    .notas-arbol-hdr {
      display: flex; align-items: center; justify-content: space-between;
      font-size: .7rem; text-transform: uppercase; letter-spacing: .08em;
      color: var(--muted); margin-bottom: .5rem; padding-top: .3rem;
      border-top: 1px solid var(--border);
    }
    .notas-btn-icono-mini {
      width: 20px; height: 20px; border-radius: 5px; border: 1px solid var(--border);
      background: var(--bg3); color: var(--muted2); cursor: pointer; font-size: .85rem;
      line-height: 1; display: inline-flex; align-items: center; justify-content: center;
    }
    .notas-btn-icono-mini:hover { color: var(--text); border-color: var(--blue); }

    .notas-arbol { max-height: 60vh; overflow-y: auto; scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
    .notas-arbol-item {
      display: flex; align-items: center; gap: .45rem;
      padding: .4rem .5rem; border-radius: 6px; cursor: pointer;
      font-size: .8rem; color: var(--muted2); margin-bottom: .1rem;
    }
    .notas-arbol-item:hover { background: var(--bg3); color: var(--text); }
    .notas-arbol-item.active { background: rgba(79,110,247,.15); color: var(--text); font-weight: 600; }
    .notas-arbol-item .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .notas-arbol-item .cnt { margin-left: auto; font-size: .68rem; color: var(--muted); }
    .notas-arbol-item.sub { padding-left: 1.6rem; }

    /* Main / grid */
    .notas-main-hdr {
      display: flex; align-items: baseline; gap: .6rem; margin-bottom: .9rem;
      font-size: .95rem; font-weight: 700; color: var(--text);
    }
    .notas-contador { font-size: .75rem; font-weight: 400; color: var(--muted2); }

    .notas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }
    .notas-card {
      background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius);
      padding: 1.1rem 1.2rem; cursor: pointer; transition: border-color .15s, transform .1s;
      position: relative; display: flex; flex-direction: column; gap: .5rem;
    }
    .notas-card:hover { border-color: var(--border2); transform: translateY(-1px); }
    .notas-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .notas-card-icono {
      width: 38px; height: 38px; border-radius: 9px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
    }
    .notas-card-fav {
      background: none; border: none; cursor: pointer; font-size: 1.05rem; color: var(--muted);
      padding: .1rem; line-height: 1;
    }
    .notas-card-fav.activo { color: #fbbf24; }
    .notas-card h4 { font-size: .92rem; color: var(--text); font-weight: 700; line-height: 1.3; }
    .notas-card p { font-size: .78rem; color: var(--muted2); line-height: 1.4;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .notas-card-tags { display: flex; flex-wrap: wrap; gap: .3rem; }
    .notas-tag {
      font-size: .65rem; padding: .12rem .5rem; border-radius: 20px;
      background: rgba(79,110,247,.12); color: #7c96f9; border: 1px solid rgba(79,110,247,.25);
    }
    .notas-card-footer {
      display: flex; align-items: center; justify-content: space-between;
      font-size: .68rem; color: var(--muted); margin-top: auto; padding-top: .4rem;
      border-top: 1px solid rgba(99,102,241,.08);
    }

    /* Toast */
    .notas-toast {
      position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(20px);
      background: #14161f; border: 1px solid var(--border2); color: #fff;
      padding: .7rem 1.2rem; border-radius: 9px; font-size: .82rem; font-weight: 600;
      box-shadow: 0 10px 30px rgba(0,0,0,.5); z-index: 300; opacity: 0; pointer-events: none;
      transition: opacity .2s, transform .2s;
    }
    .notas-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    /* Modal ancho (ver / editar procedimiento) */
    .notas-modal-ancho { max-width: 880px; }

    /* Bloque de código con botón copiar */
    .notas-bloque-codigo { position: relative; margin: .6rem 0; }
    .notas-bloque-codigo pre {
      margin: 0 !important; border-radius: 8px !important; font-size: .78rem !important;
      max-height: 420px; overflow: auto;
    }
    .notas-btn-copiar {
      position: absolute; top: .5rem; right: .5rem; z-index: 5;
      display: inline-flex; align-items: center; gap: .3rem;
      padding: .3rem .6rem; font-size: .68rem; font-weight: 700;
      background: rgba(20,22,31,.9); border: 1px solid var(--border2); color: var(--muted2);
      border-radius: 6px; cursor: pointer; transition: all .15s;
    }
    .notas-btn-copiar:hover { color: #fff; border-color: var(--blue); }

    .notas-paso-view { border: 1px solid var(--border); border-radius: 9px; padding: 1rem 1.1rem; margin-bottom: .9rem; background: var(--bg3); }
    .notas-paso-view h4 { font-size: .85rem; color: var(--text); margin-bottom: .4rem; display: flex; align-items: center; gap: .5rem; }
    .notas-paso-num {
      width: 22px; height: 22px; border-radius: 50%; background: var(--grad); color: #fff;
      display: inline-flex; align-items: center; justify-content: center; font-size: .68rem; font-weight: 700; flex-shrink: 0;
    }
    .notas-paso-tipo-badge {
      font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700;
      padding: .12rem .45rem; border-radius: 20px; background: var(--bg2); color: var(--muted2); border: 1px solid var(--border);
      margin-left: auto;
    }
    .notas-paso-desc { font-size: .8rem; color: var(--muted2); margin-bottom: .5rem; white-space: pre-line; }
    .notas-checklist-item { display: flex; align-items: center; gap: .55rem; font-size: .82rem; color: var(--muted2); padding: .3rem 0; }
    .notas-checklist-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--blue); }
    .notas-checklist-item.checked span { text-decoration: line-through; color: var(--muted); }
    .notas-advertencia { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); color: #fbbf24; padding: .7rem .9rem; border-radius: 8px; font-size: .82rem; }
    .notas-recomendacion { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3); color: #34d399; padding: .7rem .9rem; border-radius: 8px; font-size: .82rem; }
    .notas-enlace { display: inline-flex; align-items: center; gap: .4rem; color: var(--blue); font-size: .85rem; text-decoration: none; word-break: break-all; }
    .notas-enlace:hover { text-decoration: underline; }

    /* Constructor de pasos (crear/editar) */
    .notas-paso-editor {
      border: 1px solid var(--border); border-radius: 9px; padding: .9rem 1rem; margin-bottom: .8rem; background: var(--bg3);
    }
    .notas-paso-editor-hdr { display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem; }
    .notas-paso-editor-hdr .notas-paso-num { flex-shrink: 0; }
    .notas-paso-editor select, .notas-paso-editor input, .notas-paso-editor textarea {
      width: 100%; background: var(--bg2); border: 1px solid var(--border); color: var(--text);
      padding: .5rem .65rem; border-radius: 7px; font-size: .82rem; font-family: inherit; margin-top: .3rem;
    }
    .notas-paso-editor textarea.mono { font-family: 'Courier New', monospace; font-size: .8rem; }
    .notas-paso-editor-acciones { display: flex; gap: .4rem; margin-left: auto; }
    .notas-checklist-editor-item { display: flex; gap: .4rem; margin-top: .4rem; }
    .notas-checklist-editor-item input { margin-top: 0 !important; }

    .notas-tag-input-wrap {
      display: flex; flex-wrap: wrap; gap: .35rem; align-items: center;
      background: var(--bg3); border: 1px solid var(--border); border-radius: 7px; padding: .4rem .5rem;
    }
    .notas-tag-chip { display: inline-flex; align-items: center; gap: .3rem; background: rgba(79,110,247,.15); color: #7c96f9;
      font-size: .72rem; padding: .2rem .55rem; border-radius: 20px; }
    .notas-tag-chip button { background: none; border: none; color: inherit; cursor: pointer; font-size: .8rem; line-height: 1; }
    .notas-tag-input-wrap input { flex: 1; min-width: 100px; background: none; border: none; color: var(--text); font-size: .8rem; padding: .2rem; }
    .notas-tag-input-wrap input:focus { outline: none; }

    .notas-adjunto-item { display: flex; align-items: center; justify-content: space-between; gap: .5rem;
      font-size: .8rem; color: var(--muted2); padding: .4rem 0; border-bottom: 1px dashed var(--border); }
    .notas-adjunto-item a { color: var(--blue); text-decoration: none; }

    .notas-version-item { display: flex; align-items: center; justify-content: space-between; gap: .6rem;
      padding: .6rem 0; border-bottom: 1px solid rgba(99,102,241,.08); font-size: .8rem; }
    .notas-version-item .v-num { font-weight: 700; color: var(--text); }
    .notas-version-item .v-meta { color: var(--muted); font-size: .72rem; }

    /* Responsive */
    .mobile-toggle {
      display:none;
      background:none; border:none; cursor:pointer;
      padding:.4rem; color:var(--text);
    }

    @media (max-width:768px) {
      .sidebar {
        position:fixed; left:0; top:0; bottom:0;
        transform:translateX(-100%);
        transition:transform .25s;
        z-index:50;
      }
      .sidebar.open { transform:translateX(0); }
      .main-area { width:100%; }
      .mobile-toggle { display:flex; }
      .content { padding:1.25rem; }
    }
  </style>
</head>
<body>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <!-- Perfil -->
  <div class="user-profile">
    <div class="avatar-wrap">
      <div class="avatar"><?= $inicialNombre ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></div>
        <div class="user-email"><?= htmlspecialchars($usuario['correo']) ?></div>
      </div>
    </div>
    <div class="rol-badge">
      <span class="dot"></span>
      <?= htmlspecialchars($etiquetaRol) ?>
    </div>
  </div>

  <!-- Navegación -->
  <nav class="nav-section">
    <div class="nav-label">Menú principal</div>

    <?php foreach ($visibles as $key => $mod): ?>
      <div class="nav-item <?= $key === 'inicio' ? 'active' : '' ?>"
           onclick="mostrarPanel('<?= $key ?>')"
           id="nav-<?= $key ?>">
        <?= iconoSvg($mod['icono']) ?>
        <?= htmlspecialchars($mod['label']) ?>
      </div>

      <?php if ($key === 'gastos'): ?>
        <div class="nav-divider"></div>
        <div class="nav-label">Gestión</div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="/spectre_erp/index.php" class="footer-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Ver tienda
    </a>
    <a href="/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=logout"
       class="footer-btn danger">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Cerrar sesión
    </a>
  </div>
</aside>

<!-- ══ ÁREA PRINCIPAL ══════════════════════════════════════════ -->
<div class="main-area">

  <!-- Top bar -->
  <header class="topbar">
    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menú">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <div class="topbar-title">
      <span class="breadcrumb">SPECTRE <span>/ Panel</span> /</span>
      <span id="topbar-label">Inicio</span>
    </div>

    <div class="topbar-right">
      <span class="topbar-time" id="reloj">--:--:--</span>
      <a href="/spectre_erp/index.php" class="btn-tienda">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        Tienda
      </a>
    </div>
  </header>

  <!-- Contenido dinámico -->
  <div class="content">

    <!-- ── Panel: Inicio ─────────────────────────────────── -->
    <div class="panel active" id="panel-inicio">
      <div class="panel-hdr">
        <div>
          <h1>Bienvenido, <span><?= htmlspecialchars($usuario['nombre']) ?></span></h1>
          <p>Resumen general del sistema — <?= date('d/m/Y') ?></p>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card" style="--acento:var(--grad)">
          <div class="stat-label">Ventas hoy</div>
          <div class="stat-value" id="inicio-ventas-hoy">S/ 0</div>
          <div class="stat-sub" id="inicio-ventas-hoy-sub">Sin ventas registradas aún</div>
          <span class="stat-icon">💰</span>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#f59e0b,#d97706)">
          <div class="stat-label">Tickets abiertos</div>
          <div class="stat-value" id="inicio-tickets-abiertos">0</div>
          <div class="stat-sub" id="inicio-tickets-sub">Soporte TI al día</div>
          <span class="stat-icon">🎟</span>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#10b981,#059669)">
          <div class="stat-label">Productos en stock</div>
          <div class="stat-value" id="inicio-productos-stock">0</div>
          <div class="stat-sub" id="inicio-productos-sub">Inventario pendiente</div>
          <span class="stat-icon">📦</span>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#8b5cf6,#6d28d9)">
          <div class="stat-label">Clientes registrados</div>
          <div class="stat-value" id="inicio-clientes">0</div>
          <div class="stat-sub" id="inicio-clientes-sub">Base de clientes</div>
          <span class="stat-icon">👥</span>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">
          Actividad reciente
          <span>Últimas acciones</span>
        </div>
        <div class="activity-list">
          <div class="activity-item">
            <div class="act-dot" style="background:#4f6ef7"></div>
            <div class="act-text">Sistema <strong>iniciado</strong> — base de datos conectada.</div>
            <div class="act-time">ahora</div>
          </div>
          <div class="activity-item">
            <div class="act-dot" style="background:#10b981"></div>
            <div class="act-text">Sesión iniciada como <strong><?= htmlspecialchars($etiquetaRol) ?></strong>.</div>
            <div class="act-time">ahora</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Panel: Inventario ─────────────────────────────── -->
    <div class="panel" id="panel-inventario">
      <div class="panel-hdr">
        <div>
          <h1>Inventario <span>& Stock</span></h1>
          <p>Gestión de productos, stock y movimientos.</p>
        </div>
        <a href="/spectre_erp/src/modules/productos/views/nuevo.php" class="btn-nuevo">+ Nuevo producto</a>
      </div>

      <div class="stats-grid" id="inv-stats">
        <div class="stat-card" style="--acento:var(--grad)">
          <div class="stat-label">Productos activos</div>
          <div class="stat-value" id="inv-total-productos">—</div>
          <div class="stat-sub" id="inv-total-unidades">—</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#10b981,#059669)">
          <div class="stat-label">Valor de inventario</div>
          <div class="stat-value" id="inv-valor">—</div>
          <div class="stat-sub">Stock × precio</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#f59e0b,#d97706)">
          <div class="stat-label">Bajo stock</div>
          <div class="stat-value" id="inv-bajo-stock">—</div>
          <div class="stat-sub">Al límite mínimo</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#ef4444,#b91c1c)">
          <div class="stat-label">Sin stock</div>
          <div class="stat-value" id="inv-sin-stock">—</div>
          <div class="stat-sub">Agotados</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">
          Productos
          <span id="inv-contador">—</span>
        </div>
        <div class="inv-toolbar">
          <input type="text" id="inv-buscar" placeholder="Buscar por nombre o código…">
          <select id="inv-categoria">
            <option value="">Todas las categorías</option>
            <?php foreach (Producto::CATEGORIAS as $slug => $label): ?>
              <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="inv-check">
            <input type="checkbox" id="inv-solo-bajo-stock"> Solo bajo stock
          </label>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Categoría</th>
              <th>Stock</th>
              <th>Precio</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="inv-tbody">
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">Cargando inventario…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal: Movimiento de stock ─────────────────────── -->
    <div class="inv-modal-overlay" id="inv-modal-mov">
      <div class="inv-modal">
        <h3>Movimiento de stock</h3>
        <p class="inv-modal-sub" id="mov-producto-nombre"></p>

        <label>Tipo de movimiento</label>
        <select id="mov-tipo">
          <option value="entrada">Entrada (ingresa stock)</option>
          <option value="salida">Salida (descuenta stock)</option>
          <option value="ajuste">Ajuste (corrige al valor exacto)</option>
        </select>

        <label id="mov-cantidad-label">Cantidad</label>
        <input type="number" id="mov-cantidad" min="0" step="1">

        <label>Motivo (opcional)</label>
        <input type="text" id="mov-motivo" placeholder="Ej. Compra a proveedor, merma, conteo físico…">

        <div class="inv-modal-msg" id="mov-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="cerrarModalMovimiento()">Cancelar</button>
          <button class="btn-primario" onclick="enviarMovimiento()">Guardar</button>
        </div>
      </div>
    </div>

    <!-- ── Modal: Editar producto ─────────────────────────── -->
    <div class="inv-modal-overlay" id="inv-modal-editar">
      <div class="inv-modal">
        <h3>Editar producto</h3>

        <label>Nombre</label>
        <input type="text" id="edit-nombre">

        <label>Descripción</label>
        <textarea id="edit-descripcion" rows="3"></textarea>

        <div class="inv-modal-row">
          <div>
            <label>Categoría</label>
            <select id="edit-categoria">
              <?php foreach (Producto::CATEGORIAS as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Precio (S/)</label>
            <input type="number" id="edit-precio" min="0" step="0.01">
          </div>
        </div>

        <label>Stock mínimo (alerta)</label>
        <input type="number" id="edit-stock-minimo" min="0" step="1">

        <div class="inv-modal-msg" id="edit-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="cerrarModalEditar()">Cancelar</button>
          <button class="btn-primario" onclick="enviarEdicion()">Guardar cambios</button>
        </div>
      </div>
    </div>

    <!-- ── Panel: Servicios ──────────────────────────────── -->
    <div class="panel" id="panel-servicios">
      <div class="panel-hdr">
        <div>
          <h1>Servicios <span>técnicos</span></h1>
          <p>Instalaciones, mantenimientos y soporte — sin manejo de stock.</p>
        </div>
        <button class="btn-nuevo" onclick="abrirModalNuevoServicio()" style="border:none;cursor:pointer">+ Nuevo servicio</button>
      </div>

      <div class="stats-grid" id="serv-stats">
        <div class="stat-card" style="--acento:var(--grad)">
          <div class="stat-label">Servicios activos</div>
          <div class="stat-value" id="serv-total">—</div>
          <div class="stat-sub">En catálogo</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#10b981,#059669)">
          <div class="stat-label">Valor de catálogo</div>
          <div class="stat-value" id="serv-valor">—</div>
          <div class="stat-sub">Suma de precios</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#f59e0b,#d97706)">
          <div class="stat-label">Precio promedio</div>
          <div class="stat-value" id="serv-promedio">—</div>
          <div class="stat-sub">Por servicio</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">
          Servicios
          <span id="serv-contador">—</span>
        </div>
        <div class="inv-toolbar">
          <input type="text" id="serv-buscar" placeholder="Buscar por nombre o descripción…">
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Servicio</th>
              <th>Descripción</th>
              <th>Duración estimada</th>
              <th>Precio</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="serv-tbody">
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">Cargando servicios…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal: Nuevo / Editar servicio ─────────────────── -->
    <div class="inv-modal-overlay" id="serv-modal">
      <div class="inv-modal">
        <h3 id="serv-modal-titulo">Nuevo servicio</h3>

        <label>Nombre</label>
        <input type="text" id="serv-nombre" placeholder="Ej. Instalación de Office">

        <label>Descripción</label>
        <textarea id="serv-descripcion" rows="3" placeholder="Detalle del servicio…"></textarea>

        <div class="inv-modal-row">
          <div>
            <label>Precio (S/)</label>
            <input type="number" id="serv-precio" min="0" step="0.01">
          </div>
          <div>
            <label>Duración estimada</label>
            <input type="text" id="serv-duracion" placeholder="Ej. 1 hora, 2 días…">
          </div>
        </div>

        <div class="inv-modal-msg" id="serv-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="cerrarModalServicio()">Cancelar</button>
          <button class="btn-primario" onclick="enviarServicio()">Guardar</button>
        </div>
      </div>
    </div>

    <!-- ── Panel: Notas (Base de Conocimiento Técnica) ────────────────────────────────── -->
    <div class="panel" id="panel-notas">
      <div class="panel-hdr">
        <div>
          <h1><span>Base de</span> Conocimiento</h1>
          <p>Procedimientos, comandos y scripts reutilizables para reparaciones e instalaciones.</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
          <?php if ($rol === 'gerente'): ?>
          <button class="btn-secundario" onclick="notasAbrirImportar()">Importar</button>
          <?php endif; ?>
          <button class="btn-nuevo" onclick="notasAbrirEditor()">+ Nuevo Procedimiento</button>
        </div>
      </div>

      <div class="stats-grid" id="notas-stats">
        <div class="stat-card"><div class="stat-label">Procedimientos</div><div class="stat-value" id="notas-stat-total">—</div></div>
        <div class="stat-card"><div class="stat-label">Categorías</div><div class="stat-value" id="notas-stat-categorias">—</div></div>
        <div class="stat-card"><div class="stat-label">Comandos copiados</div><div class="stat-value" id="notas-stat-copiados">—</div></div>
        <div class="stat-card"><div class="stat-label">Favoritos guardados</div><div class="stat-value" id="notas-stat-favoritos">—</div></div>
      </div>

      <div class="notas-layout">
        <aside class="notas-sidebar">
          <div class="notas-search">
            <input type="text" id="notas-buscar" placeholder="Buscar título, comando, código, etiqueta…" oninput="notasBuscarDebounced()">
          </div>
          <div class="notas-filtros">
            <button class="notas-filtro-chip active" data-filtro="todos" onclick="notasFiltroRapido('todos', this)">Todos</button>
            <button class="notas-filtro-chip" data-filtro="favoritos" onclick="notasFiltroRapido('favoritos', this)">★ Favoritos</button>
            <button class="notas-filtro-chip" data-filtro="mas_usados" onclick="notasFiltroRapido('mas_usados', this)">🔥 Más usados</button>
            <button class="notas-filtro-chip" data-filtro="recientes" onclick="notasFiltroRapido('recientes', this)">🕓 Recientes</button>
          </div>
          <div class="notas-arbol-hdr">
            <span>Categorías</span>
            <?php if ($rol === 'gerente'): ?>
            <button class="notas-btn-icono-mini" onclick="notasAbrirCategoria()" title="Nueva categoría">+</button>
            <?php endif; ?>
          </div>
          <div id="notas-arbol" class="notas-arbol"><div class="chart-empty">Cargando…</div></div>
        </aside>

        <div class="notas-main">
          <div class="notas-main-hdr">
            <span id="notas-titulo-vista">Todos los procedimientos</span>
            <span id="notas-contador" class="notas-contador">—</span>
          </div>
          <div id="notas-grid" class="notas-grid"><div class="chart-empty">Cargando procedimientos…</div></div>
        </div>
      </div>
    </div>

    <!-- Modal: Ver procedimiento completo -->
    <div class="inv-modal-overlay" id="notas-modal-ver">
      <div class="inv-modal notas-modal-ancho">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
          <div>
            <h3 id="notas-ver-titulo">Procedimiento</h3>
            <p class="inv-modal-sub" id="notas-ver-sub"></p>
          </div>
          <button class="notas-card-fav" id="notas-ver-fav-btn" onclick="notasAlternarFavoritoActivo()" title="Marcar como favorito">★</button>
        </div>

        <div id="notas-ver-tags" class="notas-card-tags" style="margin-bottom:.8rem;"></div>
        <p id="notas-ver-desc" class="notas-paso-desc" style="margin-bottom:1rem;"></p>

        <div id="notas-ver-pasos"></div>

        <div id="notas-ver-adjuntos-wrap" style="margin-top:1rem;">
          <h4 style="font-size:.82rem;color:var(--text);margin-bottom:.4rem;">Archivos adjuntos</h4>
          <div id="notas-ver-adjuntos"></div>
        </div>

        <div class="export-bar" style="margin-top:1.3rem;padding-top:1rem;border-top:1px solid var(--border);">
          <span style="font-size:.72rem;color:var(--muted);">Exportar:</span>
          <button class="btn-mini" onclick="notasExportar('pdf')">PDF</button>
          <button class="btn-mini" onclick="notasExportar('word')">Word</button>
          <button class="btn-mini" onclick="notasExportar('html')">HTML</button>
          <button class="btn-mini" onclick="notasExportar('markdown')">Markdown</button>
          <button class="btn-mini" onclick="notasExportar('txt')">TXT</button>
          <button class="btn-mini" onclick="notasVerVersiones()">🕓 Versiones</button>
          <span style="flex:1"></span>
          <button class="btn-secundario" onclick="notasEditarDesdeVista()">Editar</button>
          <?php if ($rol === 'gerente'): ?>
          <button class="btn-secundario" style="color:#f87171;border-color:rgba(239,68,68,.35);" onclick="notasEliminarDesdeVista()">Eliminar</button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Modal: Crear / Editar procedimiento -->
    <div class="inv-modal-overlay" id="notas-modal-editar">
      <div class="inv-modal notas-modal-ancho">
        <h3 id="notas-editor-titulo-modal">Nuevo Procedimiento</h3>
        <p class="inv-modal-sub">Documenta un procedimiento reutilizable: comandos, scripts, checklist, enlaces, notas...</p>

        <input type="hidden" id="notas-editor-id">

        <label>Título</label>
        <input type="text" id="notas-editor-titulo" placeholder="Ej. Office 2024 LTSC — Instalación y activación">

        <div class="inv-modal-row">
          <div>
            <label>Categoría</label>
            <select id="notas-editor-categoria"></select>
          </div>
          <div>
            <label>Estado</label>
            <select id="notas-editor-estado">
              <option value="publicado">Publicado</option>
              <option value="borrador">Borrador</option>
              <option value="archivado">Archivado</option>
            </select>
          </div>
        </div>

        <label>Descripción</label>
        <textarea id="notas-editor-descripcion" rows="2" placeholder="Resumen breve de para qué sirve este procedimiento…"></textarea>

        <div class="inv-modal-row">
          <div>
            <label>Ícono (emoji)</label>
            <input type="text" id="notas-editor-icono" placeholder="🖥️" maxlength="4">
          </div>
          <div>
            <label>Color</label>
            <input type="color" id="notas-editor-color" value="#4f6ef7" style="height:38px;padding:.2rem;">
          </div>
        </div>

        <label>Etiquetas</label>
        <div class="notas-tag-input-wrap" id="notas-editor-tags-wrap">
          <input type="text" id="notas-editor-tags-input" placeholder="Escribe y presiona Enter…" onkeydown="notasTagKeydown(event)">
        </div>
        <div class="ayuda" style="margin-top:.3rem;">Puedes usar variables dinámicas en el contenido: <code>{{cliente}}</code> <code>{{serial}}</code> <code>{{modelo}}</code> <code>{{usuario}}</code> <code>{{fecha}}</code> <code>{{hora}}</code></div>

        <label style="margin-top:1.2rem;display:flex;align-items:center;justify-content:space-between;">
          <span>Pasos del procedimiento</span>
          <button type="button" class="btn-mini" onclick="notasAgregarPaso()">+ Agregar paso</button>
        </label>
        <div id="notas-editor-pasos"></div>

        <div id="notas-editor-adjuntos-seccion" style="display:none;margin-top:1rem;">
          <label>Archivos adjuntos</label>
          <input type="file" id="notas-editor-archivo-input" onchange="notasSubirAdjunto()">
          <div id="notas-editor-adjuntos-lista" style="margin-top:.5rem;"></div>
        </div>

        <div class="inv-modal-msg" id="notas-editor-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="notasCerrarEditor()">Cancelar</button>
          <button class="btn-primario" onclick="notasGuardarProcedimiento()">Guardar procedimiento</button>
        </div>
      </div>
    </div>

    <!-- Modal: Nueva / editar categoría -->
    <div class="inv-modal-overlay" id="notas-modal-categoria">
      <div class="inv-modal">
        <h3>Nueva categoría</h3>
        <p class="inv-modal-sub">Organiza el árbol de la base de conocimiento.</p>

        <input type="hidden" id="notas-cat-id">

        <label>Nombre</label>
        <input type="text" id="notas-cat-nombre" placeholder="Ej. Impresoras">

        <label>Categoría padre (opcional)</label>
        <select id="notas-cat-padre">
          <option value="">— Categoría raíz —</option>
        </select>

        <div class="inv-modal-row">
          <div>
            <label>Ícono (emoji)</label>
            <input type="text" id="notas-cat-icono" placeholder="🖨️" maxlength="4">
          </div>
          <div>
            <label>Color</label>
            <input type="color" id="notas-cat-color" value="#4f6ef7" style="height:38px;padding:.2rem;">
          </div>
        </div>

        <div class="inv-modal-msg" id="notas-cat-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="notasCerrarCategoria()">Cancelar</button>
          <?php if ($rol === 'gerente'): ?>
          <button class="btn-secundario" id="notas-cat-btn-eliminar" style="color:#f87171;border-color:rgba(239,68,68,.35);display:none;" onclick="notasEliminarCategoria()">Eliminar</button>
          <?php endif; ?>
          <button class="btn-primario" onclick="notasGuardarCategoria()">Guardar</button>
        </div>
      </div>
    </div>

    <!-- Modal: Historial de versiones -->
    <div class="inv-modal-overlay" id="notas-modal-versiones">
      <div class="inv-modal">
        <h3>Historial de versiones</h3>
        <p class="inv-modal-sub" id="notas-ver-hist-sub"></p>
        <div id="notas-versiones-lista"></div>
      </div>
    </div>

    <!-- Modal: Ver snapshot de una versión anterior (solo lectura) -->
    <div class="inv-modal-overlay" id="notas-modal-version-detalle">
      <div class="inv-modal notas-modal-ancho">
        <h3 id="notas-vd-titulo">Versión</h3>
        <p class="inv-modal-sub" id="notas-vd-sub"></p>
        <div id="notas-vd-pasos"></div>
        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="document.getElementById('notas-modal-version-detalle').classList.remove('open')">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal: Importar procedimiento (TXT / Markdown) -->
    <div class="inv-modal-overlay" id="notas-modal-importar">
      <div class="inv-modal">
        <h3>Importar procedimiento</h3>
        <p class="inv-modal-sub">Pega contenido en <strong>Markdown</strong> o texto plano. Usa <code># Título</code>, <code>## Paso 1: …</code> y bloques <code>```powershell … ```</code> para que se organice automáticamente en pasos. El Word/HTML no se procesan directamente: copia y pega el texto aquí.</p>

        <label>Categoría de destino</label>
        <select id="notas-import-categoria"></select>

        <label>Contenido</label>
        <textarea id="notas-import-contenido" rows="10" class="mono" style="font-family:'Courier New',monospace;font-size:.8rem;" placeholder="# Título del procedimiento&#10;&#10;## Paso 1: Ejecutar instalador&#10;```cmd&#10;setup.exe /configure configuration.xml&#10;```"></textarea>

        <div class="inv-modal-msg" id="notas-import-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="document.getElementById('notas-modal-importar').classList.remove('open')">Cancelar</button>
          <button class="btn-primario" onclick="notasImportar()">Importar</button>
        </div>
      </div>
    </div>

    <!-- ── Panel: Ventas ────────────────────────────────── -->
    <div class="panel" id="panel-ventas">
      <div class="panel-hdr">
        <div>
          <h1><span>Ventas</span> & Órdenes</h1>
          <p>Registro de ventas simuladas y detalle de órdenes.</p>
        </div>
        <!-- BOTÓN NUEVA VENTA -->
        <a href="/spectre_erp/src/modules/ventas/views/nueva_venta.php" class="btn-nuevo">+ Nueva Venta</a>
      </div>

      <div class="stats-grid" id="ven-stats">
        <div class="stat-card" style="--acento:var(--grad)">
          <div class="stat-label">Ventas del mes</div>
          <div class="stat-value" id="ven-mes">—</div>
          <div class="stat-sub">Solo órdenes completadas</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#10b981,#059669)">
          <div class="stat-label">Ventas de hoy</div>
          <div class="stat-value" id="ven-hoy">—</div>
          <div class="stat-sub" id="ven-fecha-hoy">—</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#f59e0b,#d97706)">
          <div class="stat-label">Órdenes totales</div>
          <div class="stat-value" id="ven-ordenes">—</div>
          <div class="stat-sub">Historial completo</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#8b5cf6,#6d28d9)">
          <div class="stat-label">Ticket promedio</div>
          <div class="stat-value" id="ven-ticket">—</div>
          <div class="stat-sub">Por orden</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">
          Últimas ventas
          <span id="ven-contador">—</span>
        </div>
        <div class="inv-toolbar">
          <label style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:.4rem">
            Desde <input type="date" id="ven-desde" style="width:auto">
          </label>
          <label style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:.4rem">
            Hasta <input type="date" id="ven-hasta" style="width:auto">
          </label>
          <button class="btn-secundario" onclick="cargarVentas()">Filtrar</button>
          <button class="btn-secundario" onclick="limpiarFiltroVentas()">Limpiar</button>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Cliente</th>
              <th>Ítems</th>
              <th>Total</th>
              <th>Fecha</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="ven-tbody">
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Cargando ventas…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal: Detalle de venta ────────────────────────── -->
    <div class="inv-modal-overlay" id="ven-modal-detalle">
      <div class="inv-modal">
        <h3 id="ven-det-titulo">Venta #0</h3>
        <p class="inv-modal-sub" id="ven-det-sub"></p>
        <div id="ven-det-items"></div>
        <div class="inv-modal-actions" style="justify-content:space-between;align-items:center">
          <strong id="ven-det-total" style="font-size:1rem">S/ 0.00</strong>
          <button class="btn-secundario" onclick="cerrarModalVentaDetalle()">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- ── Panel: Gastos ─────────────────────────────────── -->
    <div class="panel" id="panel-gastos">
      <div class="panel-hdr">
        <div>
          <h1><span>Gastos</span> & Egresos</h1>
          <p>Registro de gastos del negocio: proveedores, sueldos, servicios, etc.</p>
        </div>
        <button class="btn-nuevo" onclick="abrirModalNuevoGasto()" style="border:none;cursor:pointer">+ Nuevo gasto</button>
      </div>

      <div class="stats-grid" id="gas-stats">
        <div class="stat-card" style="--acento:linear-gradient(135deg,#ef4444,#b91c1c)">
          <div class="stat-label">Gastos del mes</div>
          <div class="stat-value" id="gas-mes">—</div>
          <div class="stat-sub">Total registrado este mes</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#f59e0b,#d97706)">
          <div class="stat-label">Gastos de hoy</div>
          <div class="stat-value" id="gas-hoy">—</div>
          <div class="stat-sub">Solo el día de hoy</div>
        </div>
        <div class="stat-card" style="--acento:var(--grad)">
          <div class="stat-label">Total del periodo filtrado</div>
          <div class="stat-value" id="gas-total-periodo">—</div>
          <div class="stat-sub" id="gas-cantidad-periodo">—</div>
        </div>
      </div>

      <div class="chart-grid">
        <div class="chart-card">
          <h3>Distribución de gastos por categoría</h3>
          <canvas id="gas-chart-categoria"></canvas>
          <div class="chart-empty" id="gas-chart-vacio" style="display:none">Aún no hay gastos registrados en este periodo.</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">
          Historial de gastos
          <span id="gas-contador">—</span>
        </div>
        <div class="inv-toolbar">
          <label style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:.4rem">
            Desde <input type="date" id="gas-desde" style="width:auto">
          </label>
          <label style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:.4rem">
            Hasta <input type="date" id="gas-hasta" style="width:auto">
          </label>
          <select id="gas-categoria-filtro">
            <option value="">Todas las categorías</option>
            <?php foreach (Gasto::CATEGORIAS as $slug => $label): ?>
              <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" id="gas-buscar" placeholder="Buscar por concepto o descripción…" style="flex:1;min-width:160px">
          <button class="btn-secundario" onclick="cargarGastos()">Filtrar</button>
          <button class="btn-secundario" onclick="limpiarFiltroGastos()">Limpiar</button>

          <div class="export-bar" style="margin-left:auto">
            <div class="export-popover">
              <button class="btn-secundario" onclick="toggleExportMenu('gas-export-menu')">⇩ Exportar</button>
              <div class="export-menu" id="gas-export-menu">
                <h4>Columnas a incluir</h4>
                <div id="gas-export-columnas"></div>
                <div class="export-menu-actions">
                  <button class="btn-mini" onclick="exportarGastos('pdf')">PDF</button>
                  <button class="btn-mini" onclick="exportarGastos('excel')">Excel</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Concepto</th>
              <th>Categoría</th>
              <th>Monto</th>
              <th>Método de pago</th>
              <th>Registrado por</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="gas-tbody">
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Cargando gastos…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal: Nuevo / Editar gasto ─────────────────────── -->
    <div class="inv-modal-overlay" id="gas-modal">
      <div class="inv-modal">
        <h3 id="gas-modal-titulo">Nuevo gasto</h3>

        <label>Concepto</label>
        <input type="text" id="gas-concepto" placeholder="Ej. Pago de internet, compra de insumos…">

        <label>Descripción / motivo</label>
        <textarea id="gas-descripcion" rows="3" placeholder="Detalle del gasto…"></textarea>

        <div class="inv-modal-row">
          <div>
            <label>Categoría</label>
            <select id="gas-categoria">
              <?php foreach (Gasto::CATEGORIAS as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Monto (S/)</label>
            <input type="number" id="gas-monto" min="0" step="0.01">
          </div>
        </div>

        <div class="inv-modal-row">
          <div>
            <label>Fecha del gasto</label>
            <input type="date" id="gas-fecha">
          </div>
          <div>
            <label>Método de pago</label>
            <input type="text" id="gas-metodo-pago" placeholder="Efectivo, transferencia…">
          </div>
        </div>

        <label>Comprobante (opcional)</label>
        <input type="file" id="gas-comprobante" accept="image/*,application/pdf">

        <div class="inv-modal-msg" id="gas-msg"></div>

        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="cerrarModalGasto()">Cancelar</button>
          <button class="btn-primario" onclick="enviarGasto()">Guardar</button>
        </div>
      </div>
    </div>

    <!-- ── Panel: Soporte TI ─────────────────────────────── -->
    <div class="panel" id="panel-soporte">
      <div class="panel-hdr">
        <div>
          <h1>Soporte <span>TI</span></h1>
          <p>Tickets abiertos, en proceso y resueltos.</p>
        </div>
      </div>
      <div class="stats-grid" id="soporte-stats">
        <div class="stat-card" style="--acento:linear-gradient(135deg,#f59e0b,#d97706)">
          <div class="stat-label">Tickets abiertos</div>
          <div class="stat-value" id="soporte-pendientes">0</div>
          <div class="stat-sub">Pendientes</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#4f6ef7,#7c3aed)">
          <div class="stat-label">En proceso</div>
          <div class="stat-value" id="soporte-en-proceso">0</div>
          <div class="stat-sub">Asignados</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#10b981,#059669)">
          <div class="stat-label">Resueltos</div>
          <div class="stat-value" id="soporte-resueltos">0</div>
          <div class="stat-sub">Cerrados</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">
          Cola de tickets
          <span id="soporte-contador">—</span>
        </div>
        <div class="inv-toolbar">
          <select id="soporte-filtro-estado">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="en_proceso">En proceso</option>
            <option value="resuelto">Resuelto</option>
            <option value="cerrado">Cerrado</option>
          </select>
          <button class="btn-secundario" onclick="cargarTicketsSoporte()">Filtrar</button>
          <button class="btn-secundario" onclick="document.getElementById('soporte-filtro-estado').value='';cargarTicketsSoporte()">Limpiar</button>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>#Ticket</th>
              <th>Cliente</th>
              <th>Asunto</th>
              <th>Prioridad</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="soporte-tbody">
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Cargando tickets…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <!-- ── Modal: Detalle y respuesta de ticket ────────────── -->
    <div class="inv-modal-overlay" id="soporte-modal-detalle">
      <div class="inv-modal" style="max-width:600px;">
        <h3 id="soporte-det-titulo">Ticket #0</h3>
        <p class="inv-modal-sub" id="soporte-det-sub"></p>
        <div id="soporte-det-contenido" style="max-height:400px;overflow-y:auto;font-size:.85rem;">
          <!-- Cargado dinámicamente por JavaScript -->
        </div>
        <div class="inv-modal-actions">
          <button class="btn-secundario" onclick="cerrarModalSoporte()">Cerrar</button>
        </div>
      </div>
    </div>
    <!-- ── Panel: Reportes ───────────────────────────────── -->
    <div class="panel" id="panel-reportes">
      <div class="panel-hdr">
        <div>
          <h1>Reportes <span>& Analítica</span></h1>
          <p>Ingresos, egresos, inventario y desempeño general del negocio.</p>
        </div>
        <div class="export-popover">
          <button class="btn-nuevo" onclick="toggleExportMenu('rep-export-menu')" style="border:none;cursor:pointer">⇩ Exportar reporte</button>
          <div class="export-menu" id="rep-export-menu">
            <h4>Secciones a incluir</h4>
            <div id="rep-export-secciones"></div>
            <div class="export-menu-actions">
              <button class="btn-mini" onclick="exportarReporte('pdf')">PDF</button>
              <button class="btn-mini" onclick="exportarReporte('excel')">Excel</button>
            </div>
          </div>
        </div>
      </div>

      <div class="inv-toolbar" style="border:1px solid var(--border);border-radius:var(--radius);margin-bottom:1.25rem;background:var(--bg2)">
        <label style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:.4rem">
          Desde <input type="date" id="rep-desde" style="width:auto">
        </label>
        <label style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:.4rem">
          Hasta <input type="date" id="rep-hasta" style="width:auto">
        </label>
        <button class="btn-secundario" onclick="cargarReportes()">Aplicar filtro</button>
        <button class="btn-secundario" onclick="limpiarFiltroReportes()">Ver histórico completo</button>
      </div>

      <div class="stats-grid" id="rep-stats">
        <div class="stat-card" style="--acento:linear-gradient(135deg,#10b981,#059669)">
          <div class="stat-label">Ingresos</div>
          <div class="stat-value" id="rep-ingresos">—</div>
          <div class="stat-sub">Ventas completadas</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#ef4444,#b91c1c)">
          <div class="stat-label">Egresos</div>
          <div class="stat-value" id="rep-egresos">—</div>
          <div class="stat-sub">Gastos registrados</div>
        </div>
        <div class="stat-card" style="--acento:var(--grad)">
          <div class="stat-label">Balance</div>
          <div class="stat-value" id="rep-balance">—</div>
          <div class="stat-sub" id="rep-balance-sub">—</div>
        </div>
        <div class="stat-card" style="--acento:linear-gradient(135deg,#8b5cf6,#6d28d9)">
          <div class="stat-label">Valor de inventario</div>
          <div class="stat-value" id="rep-inv-valor">—</div>
          <div class="stat-sub" id="rep-inv-sub">—</div>
        </div>
      </div>

      <div class="chart-grid">
        <div class="chart-card">
          <h3>Ingresos vs Egresos</h3>
          <canvas id="rep-chart-balance"></canvas>
        </div>
        <div class="chart-card">
          <h3>Gastos por categoría</h3>
          <canvas id="rep-chart-gastos"></canvas>
          <div class="chart-empty" id="rep-chart-gastos-vacio" style="display:none">Sin gastos en este periodo.</div>
        </div>
        <div class="chart-card">
          <h3>Tickets de soporte por estado</h3>
          <canvas id="rep-chart-tickets"></canvas>
          <div class="chart-empty" id="rep-chart-tickets-vacio" style="display:none">Sin tickets en este periodo.</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">Productos más vendidos</div>
        <table class="data-table">
          <thead><tr><th>Producto</th><th>Unidades</th><th>Total</th></tr></thead>
          <tbody id="rep-tbody-productos">
            <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1.5rem">Cargando…</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section-card">
        <div class="section-card-hdr">Servicios más vendidos</div>
        <table class="data-table">
          <thead><tr><th>Servicio</th><th>Unidades</th><th>Total</th></tr></thead>
          <tbody id="rep-tbody-servicios">
            <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1.5rem">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Panel: Clientes ───────────────────────────────── -->
    <div class="panel" id="panel-clientes">
      <div class="panel-hdr">
        <div>
          <h1><span>Clientes</span></h1>
          <p>Base de clientes registrados en el sistema.</p>
        </div>
      </div>
      <div class="section-card">
        <div class="section-card-hdr">
          Clientes registrados
          <span id="clientes-contador">0</span>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Correo</th>
              <th>Registrado</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody id="clientes-tbody">
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">Cargando clientes…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <!-- ── Panel: Usuarios ───────────────────────────────── -->
    <div class="panel" id="panel-usuarios">
      <div class="panel-hdr">
        <div>
          <h1>Gestión de <span>Usuarios</span></h1>
          <p>Cuentas, roles y permisos del sistema.</p>
        </div>
      </div>
      <div class="section-card">
        <div class="section-card-hdr">Usuarios del sistema <span>tabla `usuarios`</span></div>
        <table class="data-table">
          <thead><tr><th>#</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th></tr></thead>
          <tbody>
            <tr>
              <td>1</td>
              <td><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></td>
              <td><?= htmlspecialchars($usuario['correo']) ?></td>
              <td><span class="status morado"><?= htmlspecialchars($etiquetaRol) ?></span></td>
              <td><span class="status verde">Activo</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main-area -->

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"
        onerror="this.onerror=null; var s=document.createElement('script'); s.src='https://unpkg.com/chart.js@4.4.4/dist/chart.umd.min.js'; document.head.appendChild(s);"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
<script>
  if (window.Prism && Prism.plugins && Prism.plugins.autoloader) {
    Prism.plugins.autoloader.languages_path = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/';
  }
</script>
<script>
// ── Navegación dinámica ─────────────────────────────────────
function mostrarPanel(key) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const panel = document.getElementById('panel-' + key);
  const navItem = document.getElementById('nav-' + key);
  if (panel) panel.classList.add('active');
  if (navItem) navItem.classList.add('active');
  const label = navItem ? navItem.textContent.trim() : key;
  document.getElementById('topbar-label').textContent = label;
  document.getElementById('sidebar').classList.remove('open');
}

// ── Reloj ──────────────────────────────────────────────────
function actualizarReloj() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('reloj').textContent = h + ':' + m + ':' + s;
}
actualizarReloj();
setInterval(actualizarReloj, 1000);

// ── Cerrar sidebar móvil ──────────────────────────────────
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  if (window.innerWidth < 769 && sidebar.classList.contains('open')) {
    if (!e.target.closest('#sidebar') && !e.target.closest('.mobile-toggle')) {
      sidebar.classList.remove('open');
    }
  }
});

// ── CSRF y constantes ──────────────────────────────────────
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const ROL_ACTUAL = <?= json_encode($rol) ?>;
const CATEGORIAS_INV = <?= json_encode(Producto::CATEGORIAS ?? [], JSON_UNESCAPED_UNICODE) ?>;

function fmtMoneda(n) {
  return 'S/ ' + Number(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}
function escapeAttr(str) {
  return String(str ?? '').replace(/'/g, "\\'");
}
function actualizarFechaHora(elementoId) {
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById(elementoId).textContent = h + ':' + m + ':' + s;
}

// ── INVENTARIO ──────────────────────────────────────────────
const INV_URL = '/spectre_erp/src/modules/inventario/controllers/InventarioController.php';
let invItemsCache = [];
let invCargado = false;

function claseStock(item) {
  const stock = Number(item.stock);
  const min = Number(item.stock_minimo);
  if (stock === 0) return 'agotado';
  if (stock <= min) return 'bajo';
  return 'ok';
}

async function cargarResumenInventario() {
  try {
    const r = await fetch(`${INV_URL}?accion=resumen`);
    const json = await r.json();
    if (!json.success) return;
    const res = json.resumen;
    const elStock = document.getElementById('inicio-productos-stock');
    const elSub = document.getElementById('inicio-productos-sub');
    if (elStock) elStock.textContent = res.total_unidades || 0;
    if (elSub) elSub.textContent = `${res.total_productos || 0} productos activos`;
    document.getElementById('inv-valor').textContent = fmtMoneda(res.valor_inventario);
    document.getElementById('inv-bajo-stock').textContent = res.bajo_stock;
    document.getElementById('inv-sin-stock').textContent = res.sin_stock;
    // Panel inicio
    document.getElementById('inicio-productos-stock').textContent = res.total_unidades || 0;
    document.getElementById('inicio-productos-sub').textContent = `${res.total_productos || 0} productos activos`;
  } catch (err) { console.error('Error cargando resumen inventario:', err); }
}

async function cargarInventario() {
  const tbody = document.getElementById('inv-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">Cargando inventario…</td></tr>';
  const categoria = document.getElementById('inv-categoria').value;
  const busqueda = document.getElementById('inv-buscar').value.trim();
  const soloBajo = document.getElementById('inv-solo-bajo-stock').checked;
  const params = new URLSearchParams({ accion: 'listar' });
  if (categoria) params.set('categoria', categoria);
  if (busqueda) params.set('q', busqueda);
  if (soloBajo) params.set('bajo_stock', '1');
  try {
    const r = await fetch(`${INV_URL}?${params.toString()}`);
    const json = await r.json();
    if (!json.success) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">${json.message || 'Error'}</td></tr>`;
      return;
    }
    invItemsCache = json.items;
    document.getElementById('inv-contador').textContent = `${json.items.length} producto(s)`;
    if (json.items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No hay productos que coincidan con el filtro.</td></tr>';
      return;
    }
    tbody.innerHTML = json.items.map(item => {
      const cls = claseStock(item);
      const estadoBadge = cls === 'ok' ? '<span class="status verde">Activo</span>' : cls === 'bajo' ? '<span class="status amarillo">Bajo stock</span>' : '<span class="status rojo">Agotado</span>';
      const btnEliminar = ROL_ACTUAL === 'gerente' ? `<button class="inv-btn-icono danger" title="Dar de baja" onclick="eliminarProducto(${item.id_producto}, '${escapeAttr(item.nombre)}')"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>` : '';
      return `<tr><td><strong style="color:var(--text)">${escapeHtml(item.nombre)}</strong><br><span style="font-size:.7rem">${escapeHtml(item.codigo)}</span></td><td>${escapeHtml(CATEGORIAS_INV[item.categoria] || item.categoria)}</td><td><span class="inv-stock-badge ${cls}">${item.stock}</span> <span style="font-size:.7rem;color:var(--muted)">(mín. ${item.stock_minimo})</span></td><td>${fmtMoneda(item.precio)}</td><td>${estadoBadge}</td><td><div class="inv-acciones"><button class="inv-btn-icono" title="Registrar movimiento" onclick="abrirModalMovimiento(${item.id_producto}, '${escapeAttr(item.nombre)}', ${item.stock})"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button><button class="inv-btn-icono" title="Editar" onclick="abrirModalEditar(${item.id_producto})"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></button>${btnEliminar}</div></td></tr>`;
    }).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No se pudo conectar con el servidor.</td></tr>';
  }
}

let invProductoActivo = null;
function actualizarLabelCantidad() {
  const tipo = document.getElementById('mov-tipo').value;
  document.getElementById('mov-cantidad-label').textContent = tipo === 'ajuste' ? 'Nuevo stock total (valor exacto)' : 'Cantidad';
}
document.getElementById('mov-tipo').addEventListener('change', actualizarLabelCantidad);

function abrirModalMovimiento(id, nombre, stockActual) {
  invProductoActivo = id;
  document.getElementById('mov-producto-nombre').textContent = `${nombre} — stock actual: ${stockActual}`;
  document.getElementById('mov-tipo').value = 'entrada';
  document.getElementById('mov-cantidad').value = '';
  document.getElementById('mov-motivo').value = '';
  document.getElementById('mov-msg').className = 'inv-modal-msg';
  document.getElementById('mov-msg').textContent = '';
  actualizarLabelCantidad();
  document.getElementById('inv-modal-mov').classList.add('open');
}
function cerrarModalMovimiento() {
  document.getElementById('inv-modal-mov').classList.remove('open');
  invProductoActivo = null;
}
async function enviarMovimiento() {
  if (!invProductoActivo) return;
  const tipo = document.getElementById('mov-tipo').value;
  const cantidad = document.getElementById('mov-cantidad').value;
  const motivo = document.getElementById('mov-motivo').value;
  const msg = document.getElementById('mov-msg');
  const datos = new URLSearchParams({ id_producto: invProductoActivo, tipo, cantidad, motivo, csrf_token: CSRF_TOKEN });
  try {
    const r = await fetch(`${INV_URL}?accion=movimiento`, { method: 'POST', body: datos });
    const json = await r.json();
    msg.className = 'inv-modal-msg ' + (json.success ? 'ok' : 'err');
    msg.textContent = json.message;
    if (json.success) {
      await cargarInventario();
      await cargarResumenInventario();
      setTimeout(cerrarModalMovimiento, 900);
    }
  } catch (err) {
    msg.className = 'inv-modal-msg err';
    msg.textContent = 'No se pudo conectar con el servidor.';
  }
}

let invEditandoId = null;
function abrirModalEditar(id) {
  const item = invItemsCache.find(p => Number(p.id_producto) === Number(id));
  if (!item) return;
  invEditandoId = id;
  document.getElementById('edit-nombre').value = item.nombre;
  document.getElementById('edit-descripcion').value = item.descripcion || '';
  document.getElementById('edit-categoria').value = item.categoria;
  document.getElementById('edit-precio').value = item.precio;
  document.getElementById('edit-stock-minimo').value = item.stock_minimo;
  document.getElementById('edit-msg').className = 'inv-modal-msg';
  document.getElementById('edit-msg').textContent = '';
  document.getElementById('inv-modal-editar').classList.add('open');
}
function cerrarModalEditar() {
  document.getElementById('inv-modal-editar').classList.remove('open');
  invEditandoId = null;
}
async function enviarEdicion() {
  if (!invEditandoId) return;
  const msg = document.getElementById('edit-msg');
  const datos = new URLSearchParams({
    id_producto: invEditandoId,
    nombre: document.getElementById('edit-nombre').value,
    descripcion: document.getElementById('edit-descripcion').value,
    categoria: document.getElementById('edit-categoria').value,
    precio: document.getElementById('edit-precio').value,
    stock_minimo: document.getElementById('edit-stock-minimo').value,
    csrf_token: CSRF_TOKEN,
  });
  try {
    const r = await fetch(`${INV_URL}?accion=actualizar`, { method: 'POST', body: datos });
    const json = await r.json();
    msg.className = 'inv-modal-msg ' + (json.success ? 'ok' : 'err');
    msg.textContent = json.message;
    if (json.success) {
      await cargarInventario();
      await cargarResumenInventario();
      setTimeout(cerrarModalEditar, 900);
    }
  } catch (err) {
    msg.className = 'inv-modal-msg err';
    msg.textContent = 'No se pudo conectar con el servidor.';
  }
}
async function eliminarProducto(id, nombre) {
  if (!confirm(`¿Dar de baja "${nombre}"? Ya no aparecerá en la tienda ni en el inventario.`)) return;
  const datos = new URLSearchParams({ id_producto: id, csrf_token: CSRF_TOKEN });
  try {
    const r = await fetch(`${INV_URL}?accion=eliminar`, { method: 'POST', body: datos });
    const json = await r.json();
    if (json.success) {
      await cargarInventario();
      await cargarResumenInventario();
    } else alert(json.message);
  } catch (err) { alert('No se pudo conectar con el servidor.'); }
}

let invDebounce = null;
document.getElementById('inv-buscar').addEventListener('input', () => {
  clearTimeout(invDebounce);
  invDebounce = setTimeout(cargarInventario, 350);
});
document.getElementById('inv-categoria').addEventListener('change', cargarInventario);
document.getElementById('inv-solo-bajo-stock').addEventListener('change', cargarInventario);
document.getElementById('inv-modal-mov').addEventListener('click', (e) => { if (e.target.id === 'inv-modal-mov') cerrarModalMovimiento(); });
document.getElementById('inv-modal-editar').addEventListener('click', (e) => { if (e.target.id === 'inv-modal-editar') cerrarModalEditar(); });

const navInventario = document.getElementById('nav-inventario');
if (navInventario) {
  navInventario.addEventListener('click', () => {
    if (!invCargado) {
      invCargado = true;
      cargarInventario();
      cargarResumenInventario();
    }
  });
}

// ── SERVICIOS ───────────────────────────────────────────────
const SERV_URL = '/spectre_erp/src/modules/servicios/controllers/ServicioController.php';
let servItemsCache = [];
let servCargado = false;
let servEditandoId = null;

async function cargarResumenServicios() {
  try {
    const r = await fetch(`${SERV_URL}?accion=resumen`);
    const json = await r.json();
    if (!json.success) return;
    const res = json.resumen;
    document.getElementById('serv-total').textContent = res.total_servicios;
    document.getElementById('serv-valor').textContent = fmtMoneda(res.valor_catalogo);
    document.getElementById('serv-promedio').textContent = fmtMoneda(res.precio_promedio);
  } catch (err) { console.error('Error cargando resumen servicios:', err); }
}

async function cargarServicios() {
  const tbody = document.getElementById('serv-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">Cargando servicios…</td></tr>';
  const busqueda = document.getElementById('serv-buscar').value.trim();
  const params = new URLSearchParams({ accion: 'listar' });
  if (busqueda) params.set('q', busqueda);
  try {
    const r = await fetch(`${SERV_URL}?${params.toString()}`);
    const json = await r.json();
    if (!json.success) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">${json.message || 'Error'}</td></tr>`;
      return;
    }
    servItemsCache = json.items;
    document.getElementById('serv-contador').textContent = `${json.items.length} servicio(s)`;
    if (json.items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No hay servicios registrados todavía.</td></tr>';
      return;
    }
    tbody.innerHTML = json.items.map(item => {
      const btnEliminar = ROL_ACTUAL === 'gerente' ? `<button class="inv-btn-icono danger" title="Dar de baja" onclick="eliminarServicio(${item.id_servicio}, '${escapeAttr(item.nombre)}')"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>` : '';
      return `<tr>
        <td><strong style="color:var(--text)">${escapeHtml(item.nombre)}</strong></td>
        <td style="color:var(--muted2);font-size:.82rem">${escapeHtml(item.descripcion || '—')}</td>
        <td>${escapeHtml(item.duracion_estimada || '—')}</td>
        <td>${fmtMoneda(item.precio)}</td>
        <td><span class="status verde">Activo</span></td>
        <td><div class="inv-acciones">
          <button class="inv-btn-icono" title="Editar" onclick="abrirModalEditarServicio(${item.id_servicio})"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></button>
          ${btnEliminar}
        </div></td>
      </tr>`;
    }).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No se pudo conectar con el servidor.</td></tr>';
  }
}

function abrirModalNuevoServicio() {
  servEditandoId = null;
  document.getElementById('serv-modal-titulo').textContent = 'Nuevo servicio';
  document.getElementById('serv-nombre').value = '';
  document.getElementById('serv-descripcion').value = '';
  document.getElementById('serv-precio').value = '';
  document.getElementById('serv-duracion').value = '';
  document.getElementById('serv-msg').className = 'inv-modal-msg';
  document.getElementById('serv-msg').textContent = '';
  document.getElementById('serv-modal').classList.add('open');
}
function abrirModalEditarServicio(id) {
  const item = servItemsCache.find(s => Number(s.id_servicio) === Number(id));
  if (!item) return;
  servEditandoId = id;
  document.getElementById('serv-modal-titulo').textContent = 'Editar servicio';
  document.getElementById('serv-nombre').value = item.nombre;
  document.getElementById('serv-descripcion').value = item.descripcion || '';
  document.getElementById('serv-precio').value = item.precio;
  document.getElementById('serv-duracion').value = item.duracion_estimada || '';
  document.getElementById('serv-msg').className = 'inv-modal-msg';
  document.getElementById('serv-msg').textContent = '';
  document.getElementById('serv-modal').classList.add('open');
}
function cerrarModalServicio() {
  document.getElementById('serv-modal').classList.remove('open');
  servEditandoId = null;
}
async function enviarServicio() {
  const msg = document.getElementById('serv-msg');
  const datosObj = {
    nombre: document.getElementById('serv-nombre').value,
    descripcion: document.getElementById('serv-descripcion').value,
    precio: document.getElementById('serv-precio').value,
    duracion_estimada: document.getElementById('serv-duracion').value,
    csrf_token: CSRF_TOKEN,
  };
  const accion = servEditandoId ? 'actualizar' : 'crear';
  if (servEditandoId) datosObj.id_servicio = servEditandoId;
  const datos = new URLSearchParams(datosObj);
  try {
    const r = await fetch(`${SERV_URL}?accion=${accion}`, { method: 'POST', body: datos });
    const json = await r.json();
    msg.className = 'inv-modal-msg ' + (json.success ? 'ok' : 'err');
    msg.textContent = json.message;
    if (json.success) {
      await cargarServicios();
      await cargarResumenServicios();
      setTimeout(cerrarModalServicio, 900);
    }
  } catch (err) {
    msg.className = 'inv-modal-msg err';
    msg.textContent = 'No se pudo conectar con el servidor.';
  }
}
async function eliminarServicio(id, nombre) {
  if (!confirm(`¿Dar de baja "${nombre}"? Ya no aparecerá disponible para nuevas ventas.`)) return;
  const datos = new URLSearchParams({ id_servicio: id, csrf_token: CSRF_TOKEN });
  try {
    const r = await fetch(`${SERV_URL}?accion=eliminar`, { method: 'POST', body: datos });
    const json = await r.json();
    if (json.success) {
      await cargarServicios();
      await cargarResumenServicios();
    } else alert(json.message);
  } catch (err) { alert('No se pudo conectar con el servidor.'); }
}

let servDebounce = null;
document.getElementById('serv-buscar').addEventListener('input', () => {
  clearTimeout(servDebounce);
  servDebounce = setTimeout(cargarServicios, 350);
});
document.getElementById('serv-modal').addEventListener('click', (e) => { if (e.target.id === 'serv-modal') cerrarModalServicio(); });

const navServicios = document.getElementById('nav-servicios');
if (navServicios) {
  navServicios.addEventListener('click', () => {
    if (!servCargado) {
      servCargado = true;
      cargarServicios();
      cargarResumenServicios();
    }
  });
}

// ── VENTAS ──────────────────────────────────────────────────
const VEN_URL = '/spectre_erp/src/modules/ventas/controllers/VentaController.php';
let venCargado = false;
let venItemsCache = [];

document.getElementById('ven-fecha-hoy').textContent = new Date().toLocaleDateString('es-PE', { day: 'numeric', month: 'long', year: 'numeric' });

async function cargarResumenVentas() {
  try {
    const r = await fetch(`${VEN_URL}?accion=resumen`);
    const json = await r.json();
    if (!json.success) return;
    const res = json.resumen;
    document.getElementById('ven-mes').textContent = fmtMoneda(res.ventas_mes);
    document.getElementById('ven-hoy').textContent = fmtMoneda(res.ventas_hoy);
    document.getElementById('ven-ordenes').textContent = res.ordenes_totales;
    document.getElementById('ven-ticket').textContent = fmtMoneda(res.ticket_promedio);
    // Panel inicio
    document.getElementById('inicio-ventas-hoy').textContent = fmtMoneda(res.ventas_hoy || 0);
    document.getElementById('inicio-ventas-hoy-sub').textContent = `Ordenes: ${res.ordenes_totales || 0} | Actualizado ahora`;
  } catch (err) { console.error('Error cargando resumen ventas:', err); }
}

async function cargarVentas() {
  const tbody = document.getElementById('ven-tbody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Cargando ventas…</td></tr>';
  const desde = document.getElementById('ven-desde').value;
  const hasta = document.getElementById('ven-hasta').value;
  const params = new URLSearchParams({ accion: 'listar' });
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  try {
    const r = await fetch(`${VEN_URL}?${params.toString()}`);
    const json = await r.json();
    if (!json.success) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">${json.message || 'Error'}</td></tr>`;
      return;
    }
    venItemsCache = json.items;
    document.getElementById('ven-contador').textContent = `${json.items.length} orden(es)`;
    if (json.items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Aún no hay ventas registradas — pruébalo desde el carrito de la tienda.</td></tr>';
      return;
    }
    tbody.innerHTML = json.items.map(v => {
      const nombreCliente = (v.cliente_nombre ? `${v.cliente_nombre} ${v.cliente_apellido || ''}`.trim() : 'Cliente eliminado');
      const estadoBadge = v.estado === 'completada' ? '<span class="status verde">Completada</span>' : '<span class="status rojo">Cancelada</span>';
      const fecha = new Date(v.fecha_venta.replace(' ', 'T')).toLocaleString('es-PE');
      // Mostramos código de venta (si no existe, mostramos el ID)
      const codigo = v.codigo_venta || `#${v.id_venta}`;
      return `<tr>
        <td><strong style="color:var(--text)">${escapeHtml(codigo)}</strong></td>
        <td>${escapeHtml(nombreCliente)}</td>
        <td>${v.items}</td>
        <td>${fmtMoneda(v.total)}</td>
        <td>${fecha}</td>
        <td>${estadoBadge}</td>
        <td>
          <button class="inv-btn-icono" title="Ver detalle" onclick="verDetalleVenta(${v.id_venta})">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <a href="${VEN_URL}?accion=exportar_pdf&id_venta=${v.id_venta}" target="_blank" class="btn-pdf" title="Exportar PDF">
            PDF
          </a>
        </td>
      </tr>`;
    }).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No se pudo conectar con el servidor.</td></tr>';
  }
}
function limpiarFiltroVentas() {
  document.getElementById('ven-desde').value = '';
  document.getElementById('ven-hasta').value = '';
  cargarVentas();
}
async function verDetalleVenta(idVenta) {
  try {
    const r = await fetch(`${VEN_URL}?accion=detalle&id_venta=${idVenta}`);
    const json = await r.json();
    if (!json.success) { alert(json.message); return; }
    const v = json.venta;
    const nombreCliente = (v.cliente_nombre ? `${v.cliente_nombre} ${v.cliente_apellido || ''}`.trim() : 'Cliente eliminado');
    const fecha = new Date(v.fecha_venta.replace(' ', 'T')).toLocaleString('es-PE');
    // Mostrar código en el título
    const titulo = v.codigo_venta ? `Venta ${v.codigo_venta}` : `Venta #${v.id_venta}`;
    document.getElementById('ven-det-titulo').textContent = titulo;
    document.getElementById('ven-det-sub').textContent = `${nombreCliente} — ${fecha} — ${v.metodo_pago}`;
    let itemsHtml = v.items.map(it => {
      const esServicio = it.tipo_item === 'servicio';
      const badge = esServicio
        ? '<span class="status morado" style="margin-left:.4rem;font-size:.62rem">Servicio</span>'
        : '<span class="status azul" style="margin-left:.4rem;font-size:.62rem">Producto</span>';
      return `<div style="display:flex;justify-content:space-between;align-items:center;font-size:.82rem;padding:.5rem 0;border-bottom:1px dashed var(--border);color:var(--muted2)">
        <span><strong style="color:var(--text)">${it.cantidad}×</strong> ${escapeHtml(it.nombre_producto)} ${badge}</span>
        <span>${fmtMoneda(it.subtotal)} <span style="color:var(--muted);font-size:.72rem">(${fmtMoneda(it.precio_unitario)} c/u)</span></span>
      </div>`;
    }).join('');
    const descuento = Number(v.descuento || 0);
    if (descuento > 0) {
      const etiqueta = v.descuento_tipo === 'porcentaje' ? 'Descuento (%)' : 'Descuento';
      itemsHtml += `<div style="display:flex;justify-content:space-between;font-size:.82rem;padding-top:.6rem;color:var(--muted2)">
        <span>Subtotal</span><span>${fmtMoneda(v.subtotal)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#f59e0b">
        <span>${etiqueta}</span><span>- ${fmtMoneda(descuento)}</span>
      </div>`;
    }
    if (v.descripcion) {
      itemsHtml += `<div style="margin-top:.5rem;font-size:.8rem;color:var(--muted2)"><strong>Descripción:</strong> ${escapeHtml(v.descripcion)}</div>`;
    }
    if (v.comprobante_ruta) {
      itemsHtml += `<div style="margin-top:.5rem;"><a href="/${v.comprobante_ruta}" target="_blank" style="color:var(--blue);font-size:.8rem;">📎 Ver comprobante</a></div>`;
    }
    document.getElementById('ven-det-items').innerHTML = itemsHtml;
    document.getElementById('ven-det-total').textContent = fmtMoneda(v.total);
    document.getElementById('ven-modal-detalle').classList.add('open');
  } catch (err) { alert('No se pudo conectar con el servidor.'); }
}
function cerrarModalVentaDetalle() {
  document.getElementById('ven-modal-detalle').classList.remove('open');
}
document.getElementById('ven-modal-detalle').addEventListener('click', (e) => {
  if (e.target.id === 'ven-modal-detalle') cerrarModalVentaDetalle();
});

const navVentas = document.getElementById('nav-ventas');
if (navVentas) {
  navVentas.addEventListener('click', () => {
    if (!venCargado) {
      venCargado = true;
      cargarVentas();
      cargarResumenVentas();
    }
  });
}

// ── SOPORTE TI ──────────────────────────────────────────────
let soporteCargado = false;
let ticketsCache = [];

async function cargarTicketsSoporte() {
  const tbody = document.getElementById('soporte-tbody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Cargando tickets…</td></tr>';
  try {
    const r = await fetch('/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=listar-json');
    const json = await r.json();
    
    if (!json.success) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">${json.message || 'Error al cargar tickets'}</td></tr>`;
      return;
    }
    
    ticketsCache = json.tickets;
    document.getElementById('soporte-contador').textContent = `${json.tickets.length} ticket(s)`;
    
    const pendientes = json.tickets.filter(t => t.estado === 'pendiente').length;
    const enProceso = json.tickets.filter(t => t.estado === 'en_proceso').length;
    const resueltos = json.tickets.filter(t => t.estado === 'resuelto' || t.estado === 'cerrado').length;
    
    document.getElementById('soporte-pendientes').textContent = pendientes;
    document.getElementById('soporte-en-proceso').textContent = enProceso;
    document.getElementById('soporte-resueltos').textContent = resueltos;
    
    document.getElementById('inicio-tickets-abiertos').textContent = pendientes;
    document.getElementById('inicio-tickets-sub').textContent = `${enProceso} en proceso, ${resueltos} resueltos`;
    
    if (json.tickets.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No hay tickets registrados.</td></tr>';
      return;
    }
    
    tbody.innerHTML = json.tickets.map(t => {
      const prioridadClase = t.prioridad === 'alta' ? 'rojo' : t.prioridad === 'media' ? 'amarillo' : 'verde';
      const prioridadLabel = t.prioridad.charAt(0).toUpperCase() + t.prioridad.slice(1);
      const estadoClase = t.estado === 'pendiente' ? 'amarillo' : t.estado === 'en_proceso' ? 'morado' : t.estado === 'resuelto' ? 'verde' : 'rojo';
      const estadoLabel = t.estado.replace('_', ' ').charAt(0).toUpperCase() + t.estado.replace('_', ' ').slice(1);
      const fecha = new Date(t.fecha_creacion.replace(' ', 'T')).toLocaleString('es-PE');
      
      return `<tr onclick="abrirDetalleSoporte(${t.id_ticket})" style="cursor:pointer;">
        <td>#${t.id_ticket}</td>
        <td>${escapeHtml(t.nombre || '')} ${escapeHtml(t.apellido || '')}</td>
        <td>${escapeHtml(t.titulo)}</td>
        <td><span class="status ${prioridadClase}">${prioridadLabel}</span></td>
        <td><span class="status ${estadoClase}">${estadoLabel}</span></td>
        <td>${fecha}</td>
        <td><button class="inv-btn-icono" onclick="event.stopPropagation();abrirDetalleSoporte(${t.id_ticket})" title="Ver detalle"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></td>
      </tr>`;
    }).join('');
    
  } catch (err) {
    console.error('Error cargando tickets:', err);
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Error de conexión al cargar tickets.</td></tr>';
  }
}

async function abrirDetalleSoporte(idTicket) {
  const modal = document.getElementById('soporte-modal-detalle');
  const contenido = document.getElementById('soporte-det-contenido');
  contenido.innerHTML = 'Cargando detalle…';
  
  try {
    const r = await fetch(`/spectre_erp/src/modules/soporte/controllers/TicketController.php?accion=detalle-json&id=${idTicket}`);
    const json = await r.json();
    
    if (!json.success) {
      contenido.innerHTML = `<p style="color:#f87171;">${json.message || 'Error al cargar el detalle'}</p>`;
      return;
    }
    
    const t = json.ticket;
    document.getElementById('soporte-det-titulo').textContent = `Ticket #${t.id_ticket} - ${escapeHtml(t.titulo)}`;
    document.getElementById('soporte-det-sub').textContent = `Cliente: ${escapeHtml(t.nombre)} ${escapeHtml(t.apellido)} — ${t.correo}`;
    
    let html = `
      <div style="margin-bottom:1rem;">
        <p><strong>Descripción:</strong></p>
        <p style="background:var(--bg3);padding:.5rem;border-radius:5px;font-size:.85rem;">${escapeHtml(t.descripcion_problema).replace(/\n/g, '<br>')}</p>
        <p style="margin-top:.5rem;"><strong>Estado:</strong> <span class="status ${t.estado === 'pendiente' ? 'amarillo' : t.estado === 'en_proceso' ? 'morado' : t.estado === 'resuelto' ? 'verde' : 'rojo'}">${t.estado.replace('_', ' ').toUpperCase()}</span></p>
        <p><strong>Prioridad:</strong> <span class="status ${t.prioridad === 'alta' ? 'rojo' : t.prioridad === 'media' ? 'amarillo' : 'verde'}">${t.prioridad.toUpperCase()}</span></p>
      </div>
      <div style="margin-bottom:1rem;">
        <p><strong>Historial:</strong></p>
        <div style="max-height:150px;overflow-y:auto;background:var(--bg3);padding:.5rem;border-radius:5px;font-size:.8rem;">
          ${json.historial.map(h => `<div style="border-bottom:1px solid var(--border);padding:.3rem 0;"><span style="color:var(--muted)">${new Date(h.fecha.replace(' ', 'T')).toLocaleString('es-PE')}</span> <strong>${escapeHtml(h.nombre || 'Sistema')}:</strong> ${escapeHtml(h.comentario)}</div>`).join('') || 'Sin historial.'}
        </div>
      </div>
      <div>
        <p><strong>Responder ticket:</strong></p>
        <form id="soporte-form-respuesta" onsubmit="enviarRespuestaSoporte(event, ${t.id_ticket})">
          <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem;">
            <select name="estado" style="flex:1;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:.4rem .6rem;border-radius:5px;">
              <option value="pendiente" ${t.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
              <option value="en_proceso" ${t.estado === 'en_proceso' ? 'selected' : ''}>En proceso</option>
              <option value="resuelto" ${t.estado === 'resuelto' ? 'selected' : ''}>Resuelto</option>
              <option value="cerrado" ${t.estado === 'cerrado' ? 'selected' : ''}>Cerrado</option>
            </select>
            <input type="text" name="solucion" placeholder="Solución (si aplica)" style="flex:1;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:.4rem .6rem;border-radius:5px;">
          </div>
          <textarea name="comentario" rows="3" placeholder="Escribe tu respuesta…" required style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:.4rem .6rem;border-radius:5px;font-size:.85rem;"></textarea>
          <button type="submit" class="btn-primario" style="margin-top:.5rem;">Enviar respuesta</button>
        </form>
        <div id="soporte-respuesta-msg" style="margin-top:.5rem;font-size:.8rem;"></div>
      </div>
    `;
    
    contenido.innerHTML = html;
    modal.classList.add('open');
    
  } catch (err) {
    console.error('Error cargando detalle:', err);
    contenido.innerHTML = `<p style="color:#f87171;">Error al cargar el detalle: ${err.message}</p>`;
  }
}

async function enviarRespuestaSoporte(event, idTicket) {
  event.preventDefault();
  const form = event.target;
  const msgDiv = document.getElementById('soporte-respuesta-msg');
  const formData = new FormData(form);
  formData.append('id_ticket', idTicket);
  formData.append('accion', 'responder');
  
  try {
    const r = await fetch('/spectre_erp/src/modules/soporte/controllers/TicketController.php', {
      method: 'POST',
      body: formData
    });
    const json = await r.json();
    
    if (json.success) {
      msgDiv.innerHTML = `<span style="color:#34d399;">✅ ${json.message}</span>`;
      setTimeout(() => {
        cargarTicketsSoporte();
        cerrarModalSoporte();
      }, 1000);
    } else {
      msgDiv.innerHTML = `<span style="color:#f87171;">❌ ${json.message}</span>`;
    }
  } catch (err) {
    console.error('Error enviando respuesta:', err);
    msgDiv.innerHTML = `<span style="color:#f87171;">Error de red: ${err.message}</span>`;
  }
}

function cerrarModalSoporte() {
  document.getElementById('soporte-modal-detalle').classList.remove('open');
}
document.getElementById('soporte-modal-detalle').addEventListener('click', (e) => {
  if (e.target.id === 'soporte-modal-detalle') cerrarModalSoporte();
});

const navSoporte = document.getElementById('nav-soporte');
if (navSoporte) {
  navSoporte.addEventListener('click', () => {
    if (!soporteCargado) {
      soporteCargado = true;
      cargarTicketsSoporte();
    }
  });
}

// ── CLIENTES ────────────────────────────────────────────────
async function cargarClientes() {
  try {
    const rContador = await fetch('/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=contar-clientes');
    const jsonContador = await rContador.json();

    if (jsonContador.success) {
      const total = jsonContador.total || 0;
      const elInicio = document.getElementById('inicio-clientes');
      if (elInicio) elInicio.textContent = total;
      const elSub = document.getElementById('inicio-clientes-sub');
      if (elSub) elSub.textContent = `Usuarios con rol cliente`;
      
      const elContador = document.getElementById('clientes-contador');
      if (elContador) elContador.textContent = total;
    }

    const rLista = await fetch('/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=listar-clientes');
    const jsonLista = await rLista.json();

    const tbody = document.getElementById('clientes-tbody');
    if (!tbody) return;

    if (!jsonLista.success) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f87171;padding:2rem">${jsonLista.message || 'Error al cargar clientes'}</td></tr>`;
      return;
    }

    const clientes = jsonLista.clientes || [];

    if (clientes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No hay clientes registrados.</td></tr>`;
      return;
    }

    tbody.innerHTML = clientes.map(c => {
      const fecha = c.fecha_creacion ? new Date(c.fecha_creacion.replace(' ', 'T')).toLocaleDateString('es-PE') : '—';
      const estadoClase = c.estado === 'activo' ? 'verde' : 'rojo';
      const estadoLabel = c.estado === 'activo' ? 'Activo' : 'Inactivo';
      const nombreCompleto = `${c.nombre || ''} ${c.apellido || ''}`.trim() || 'Sin nombre';
      
      return `<tr>
        <td>${c.id}</td>
        <td>${escapeHtml(nombreCompleto)}</td>
        <td>${escapeHtml(c.correo || '—')}</td>
        <td>${fecha}</td>
        <td><span class="status ${estadoClase}">${estadoLabel}</span></td>
      </tr>`;
    }).join('');

  } catch (err) {
    console.error('Error cargando clientes:', err);
    const tbody = document.getElementById('clientes-tbody');
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#f87171;padding:2rem">Error de conexión al cargar clientes.</td></tr>`;
    }
  }
}

// ── EXPORT MENU (genérico para Gastos y Reportes) ───────────
function toggleExportMenu(id) {
  document.querySelectorAll('.export-menu').forEach(m => { if (m.id !== id) m.classList.remove('open'); });
  document.getElementById(id).classList.toggle('open');
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.export-popover')) {
    document.querySelectorAll('.export-menu').forEach(m => m.classList.remove('open'));
  }
});

function coloresPaleta(n) {
  const base = ['#4f6ef7','#7c3aed','#10b981','#f59e0b','#ef4444','#06b6d4','#ec4899','#84cc16','#8b5cf6','#f97316'];
  const out = [];
  for (let i = 0; i < n; i++) out.push(base[i % base.length]);
  return out;
}

const CHART_OPCIONES_BASE = {
  responsive: true,
  plugins: {
    legend: { position: 'bottom', labels: { color: '#8890aa', boxWidth: 12, font: { size: 11 } } }
  }
};

// Evita que un fallo al cargar Chart.js (CDN caído, red bloqueada, etc.)
// rompa el resto del dashboard. Devuelve true solo si Chart.js ya está listo.
function chartsDisponible() {
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js no está disponible todavía; se omiten los gráficos.');
    return false;
  }
  return true;
}

// ── GASTOS ──────────────────────────────────────────────────
const GAS_URL = '/spectre_erp/src/modules/gastos/controllers/GastoController.php';
let gasCargado = false;
let gasItemsCache = [];
let gasEditandoId = null;
let gasChartCategoria = null;

const GAS_COLUMNAS = {
  concepto: 'Concepto', descripcion: 'Descripción', categoria: 'Categoría',
  monto: 'Monto', fecha_gasto: 'Fecha del gasto', metodo_pago: 'Método de pago',
  usuario: 'Registrado por', fecha_registro: 'Fecha de registro'
};
function initColumnasExport() {
  const cont = document.getElementById('gas-export-columnas');
  if (!cont || cont.childElementCount) return;
  cont.innerHTML = Object.entries(GAS_COLUMNAS).map(([key, label]) => `
    <label><input type="checkbox" class="gas-col-check" value="${key}" checked> ${label}</label>
  `).join('');
}

async function cargarResumenGastos() {
  const desde = document.getElementById('gas-desde').value;
  const hasta = document.getElementById('gas-hasta').value;
  const params = new URLSearchParams();
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  try {
    const r = await fetch(`${GAS_URL}?accion=resumen&${params.toString()}`);
    const json = await r.json();
    if (!json.success) return;
    const res = json.resumen;
    document.getElementById('gas-mes').textContent = fmtMoneda(res.total_mes);
    document.getElementById('gas-hoy').textContent = fmtMoneda(res.total_hoy);
    document.getElementById('gas-total-periodo').textContent = fmtMoneda(res.total_periodo);
    document.getElementById('gas-cantidad-periodo').textContent = `${res.cantidad} registro(s)`;
    renderChartCategoriaGastos(res.por_categoria || []);
  } catch (err) { console.error('Error cargando resumen de gastos:', err); }
}

function renderChartCategoriaGastos(porCategoria) {
  const vacio = document.getElementById('gas-chart-vacio');
  const canvas = document.getElementById('gas-chart-categoria');
  const conDatos = porCategoria.filter(c => Number(c.total) > 0);
  if (conDatos.length === 0) {
    vacio.style.display = 'block';
    canvas.style.display = 'none';
    if (gasChartCategoria) { gasChartCategoria.destroy(); gasChartCategoria = null; }
    return;
  }
  vacio.style.display = 'none';
  canvas.style.display = 'block';
  if (!chartsDisponible()) return;
  const labels = conDatos.map(c => c.categoria_label);
  const data = conDatos.map(c => Number(c.total));
  if (gasChartCategoria) gasChartCategoria.destroy();
  gasChartCategoria = new Chart(canvas, {
    type: 'pie',
    data: { labels, datasets: [{ data, backgroundColor: coloresPaleta(labels.length) }] },
    options: CHART_OPCIONES_BASE
  });
}

async function cargarGastos() {
  const tbody = document.getElementById('gas-tbody');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Cargando gastos…</td></tr>';
  const desde = document.getElementById('gas-desde').value;
  const hasta = document.getElementById('gas-hasta').value;
  const categoria = document.getElementById('gas-categoria-filtro').value;
  const busqueda = document.getElementById('gas-buscar').value.trim();
  const params = new URLSearchParams({ accion: 'listar' });
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  if (categoria) params.set('categoria', categoria);
  if (busqueda) params.set('q', busqueda);
  try {
    const r = await fetch(`${GAS_URL}?${params.toString()}`);
    const json = await r.json();
    if (!json.success) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">${json.message || 'Error'}</td></tr>`;
      return;
    }
    gasItemsCache = json.items;
    document.getElementById('gas-contador').textContent = `${json.items.length} gasto(s)`;
    if (json.items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No hay gastos registrados con este filtro.</td></tr>';
      return;
    }
    tbody.innerHTML = json.items.map(g => {
      const nombreUsuario = (g.usuario_nombre ? `${g.usuario_nombre} ${g.usuario_apellido || ''}`.trim() : '—');
      const btnEliminar = ROL_ACTUAL === 'gerente' ? `<button class="inv-btn-icono danger" title="Eliminar" onclick="eliminarGasto(${g.id_gasto}, '${escapeAttr(g.concepto)}')"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>` : '';
      return `<tr>
        <td>${g.fecha_gasto}</td>
        <td><strong style="color:var(--text)">${escapeHtml(g.concepto)}</strong>${g.descripcion ? `<br><span style="font-size:.7rem">${escapeHtml(g.descripcion)}</span>` : ''}</td>
        <td>${escapeHtml(GASTO_CATEGORIAS[g.categoria] || g.categoria)}</td>
        <td>${fmtMoneda(g.monto)}</td>
        <td>${escapeHtml(g.metodo_pago || '—')}</td>
        <td>${escapeHtml(nombreUsuario)}</td>
        <td><div class="inv-acciones">
          <button class="inv-btn-icono" title="Editar" onclick="abrirModalEditarGasto(${g.id_gasto})"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></button>
          ${btnEliminar}
        </div></td>
      </tr>`;
    }).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No se pudo conectar con el servidor.</td></tr>';
  }
}
function limpiarFiltroGastos() {
  document.getElementById('gas-desde').value = '';
  document.getElementById('gas-hasta').value = '';
  document.getElementById('gas-categoria-filtro').value = '';
  document.getElementById('gas-buscar').value = '';
  cargarGastos();
  cargarResumenGastos();
}

function abrirModalNuevoGasto() {
  gasEditandoId = null;
  document.getElementById('gas-modal-titulo').textContent = 'Nuevo gasto';
  document.getElementById('gas-concepto').value = '';
  document.getElementById('gas-descripcion').value = '';
  document.getElementById('gas-categoria').value = 'otros';
  document.getElementById('gas-monto').value = '';
  document.getElementById('gas-fecha').value = new Date().toISOString().slice(0,10);
  document.getElementById('gas-metodo-pago').value = '';
  document.getElementById('gas-comprobante').value = '';
  document.getElementById('gas-msg').className = 'inv-modal-msg';
  document.getElementById('gas-msg').textContent = '';
  document.getElementById('gas-modal').classList.add('open');
}
function abrirModalEditarGasto(id) {
  const g = gasItemsCache.find(x => Number(x.id_gasto) === Number(id));
  if (!g) return;
  gasEditandoId = id;
  document.getElementById('gas-modal-titulo').textContent = 'Editar gasto';
  document.getElementById('gas-concepto').value = g.concepto;
  document.getElementById('gas-descripcion').value = g.descripcion || '';
  document.getElementById('gas-categoria').value = g.categoria;
  document.getElementById('gas-monto').value = g.monto;
  document.getElementById('gas-fecha').value = g.fecha_gasto;
  document.getElementById('gas-metodo-pago').value = g.metodo_pago || '';
  document.getElementById('gas-comprobante').value = '';
  document.getElementById('gas-msg').className = 'inv-modal-msg';
  document.getElementById('gas-msg').textContent = '';
  document.getElementById('gas-modal').classList.add('open');
}
function cerrarModalGasto() {
  document.getElementById('gas-modal').classList.remove('open');
  gasEditandoId = null;
}
async function enviarGasto() {
  const msg = document.getElementById('gas-msg');
  const formData = new FormData();
  formData.append('concepto', document.getElementById('gas-concepto').value);
  formData.append('descripcion', document.getElementById('gas-descripcion').value);
  formData.append('categoria', document.getElementById('gas-categoria').value);
  formData.append('monto', document.getElementById('gas-monto').value);
  formData.append('fecha_gasto', document.getElementById('gas-fecha').value);
  formData.append('metodo_pago', document.getElementById('gas-metodo-pago').value);
  formData.append('csrf_token', CSRF_TOKEN);
  const archivo = document.getElementById('gas-comprobante').files[0];
  if (archivo) formData.append('comprobante', archivo);
  if (gasEditandoId) formData.append('id_gasto', gasEditandoId);
  const accion = gasEditandoId ? 'actualizar' : 'crear';
  try {
    const r = await fetch(`${GAS_URL}?accion=${accion}`, { method: 'POST', body: formData });
    const json = await r.json();
    msg.className = 'inv-modal-msg ' + (json.success ? 'ok' : 'err');
    msg.textContent = json.message;
    if (json.success) {
      await cargarGastos();
      await cargarResumenGastos();
      setTimeout(cerrarModalGasto, 900);
    }
  } catch (err) {
    msg.className = 'inv-modal-msg err';
    msg.textContent = 'No se pudo conectar con el servidor.';
  }
}
async function eliminarGasto(id, concepto) {
  if (!confirm(`¿Eliminar el gasto "${concepto}"? Esta acción no se puede deshacer.`)) return;
  const datos = new URLSearchParams({ id_gasto: id, csrf_token: CSRF_TOKEN });
  try {
    const r = await fetch(`${GAS_URL}?accion=eliminar`, { method: 'POST', body: datos });
    const json = await r.json();
    if (json.success) {
      await cargarGastos();
      await cargarResumenGastos();
    } else alert(json.message);
  } catch (err) { alert('No se pudo conectar con el servidor.'); }
}

function columnasGastosSeleccionadas() {
  return Array.from(document.querySelectorAll('.gas-col-check:checked')).map(c => c.value);
}
function exportarGastos(formato) {
  const cols = columnasGastosSeleccionadas();
  if (cols.length === 0) { alert('Selecciona al menos una columna para exportar.'); return; }
  const desde = document.getElementById('gas-desde').value;
  const hasta = document.getElementById('gas-hasta').value;
  const categoria = document.getElementById('gas-categoria-filtro').value;
  const params = new URLSearchParams({ accion: formato === 'pdf' ? 'exportar_pdf' : 'exportar_excel' });
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  if (categoria) params.set('categoria', categoria);
  params.set('columnas', cols.join(','));
  window.open(`${GAS_URL}?${params.toString()}`, '_blank');
  toggleExportMenu('gas-export-menu');
}

let GASTO_CATEGORIAS = {};
const navGastos = document.getElementById('nav-gastos');
if (navGastos) {
  navGastos.addEventListener('click', async () => {
    initColumnasExport();
    if (!gasCargado) {
      gasCargado = true;
      const r = await fetch(`${GAS_URL}?accion=resumen`);
      const json = await r.json();
      if (json.success) GASTO_CATEGORIAS = json.categorias || {};
      cargarGastos();
      cargarResumenGastos();
    }
  });
}
document.getElementById('gas-modal').addEventListener('click', (e) => { if (e.target.id === 'gas-modal') cerrarModalGasto(); });

// ── REPORTES ────────────────────────────────────────────────
const REP_URL = '/spectre_erp/src/modules/reportes/controllers/ReporteController.php';
let repCargado = false;
let repChartBalance = null, repChartGastos = null, repChartTickets = null;

const REP_SECCIONES = {
  balance: 'Balance de ingresos vs egresos',
  movimientos: 'Detalle de movimientos (ventas y gastos)',
  gastos_cat: 'Gastos por categoría',
  top_productos: 'Productos más vendidos',
  top_servicios: 'Servicios más vendidos',
  inventario: 'Resumen de inventario',
  tickets: 'Tickets de soporte por estado'
};
function initSeccionesExportReportes() {
  const cont = document.getElementById('rep-export-secciones');
  if (!cont || cont.childElementCount) return;
  cont.innerHTML = Object.entries(REP_SECCIONES).map(([key, label]) => `
    <label><input type="checkbox" class="rep-sec-check" value="${key}" checked> ${label}</label>
  `).join('');
}

async function cargarReportes() {
  const desde = document.getElementById('rep-desde').value;
  const hasta = document.getElementById('rep-hasta').value;
  const params = new URLSearchParams();
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  try {
    const r = await fetch(`${REP_URL}?accion=resumen&${params.toString()}`);
    const json = await r.json();
    if (!json.success) { alert(json.message || 'No se pudo cargar el reporte.'); return; }
    const res = json.resumen;

    document.getElementById('rep-ingresos').textContent = fmtMoneda(res.ingresos);
    document.getElementById('rep-egresos').textContent = fmtMoneda(res.egresos);
    document.getElementById('rep-balance').textContent = fmtMoneda(res.balance);
    document.getElementById('rep-balance-sub').textContent = res.balance >= 0 ? 'Positivo' : 'Negativo';
    document.getElementById('rep-inv-valor').textContent = fmtMoneda(res.inventario.valor_inventario);
    document.getElementById('rep-inv-sub').textContent = `${res.inventario.total_productos} productos, ${res.inventario.sin_stock} agotados`;

    // Gráfico de barras: ingresos vs egresos
    if (chartsDisponible()) {
      const ctxBalance = document.getElementById('rep-chart-balance');
      if (repChartBalance) repChartBalance.destroy();
      repChartBalance = new Chart(ctxBalance, {
        type: 'bar',
        data: {
          labels: ['Ingresos', 'Egresos'],
          datasets: [{ data: [res.ingresos, res.egresos], backgroundColor: ['#10b981', '#ef4444'] }]
        },
        options: { ...CHART_OPCIONES_BASE, plugins: { legend: { display: false } }, scales: { y: { ticks: { color: '#8890aa' } }, x: { ticks: { color: '#8890aa' } } } }
      });
    }

    // Gráfico circular: gastos por categoría
    const gastosCat = (res.gastos_por_categoria || []).filter(c => Number(c.total) > 0);
    const vacioG = document.getElementById('rep-chart-gastos-vacio');
    const canvasG = document.getElementById('rep-chart-gastos');
    if (gastosCat.length === 0) {
      vacioG.style.display = 'block'; canvasG.style.display = 'none';
      if (repChartGastos) { repChartGastos.destroy(); repChartGastos = null; }
    } else if (chartsDisponible()) {
      vacioG.style.display = 'none'; canvasG.style.display = 'block';
      if (repChartGastos) repChartGastos.destroy();
      repChartGastos = new Chart(canvasG, {
        type: 'pie',
        data: { labels: gastosCat.map(c => c.categoria_label), datasets: [{ data: gastosCat.map(c => c.total), backgroundColor: coloresPaleta(gastosCat.length) }] },
        options: CHART_OPCIONES_BASE
      });
    }

    // Gráfico circular: tickets por estado
    const tickets = res.tickets || [];
    const vacioT = document.getElementById('rep-chart-tickets-vacio');
    const canvasT = document.getElementById('rep-chart-tickets');
    if (tickets.length === 0) {
      vacioT.style.display = 'block'; canvasT.style.display = 'none';
      if (repChartTickets) { repChartTickets.destroy(); repChartTickets = null; }
    } else if (chartsDisponible()) {
      vacioT.style.display = 'none'; canvasT.style.display = 'block';
      if (repChartTickets) repChartTickets.destroy();
      repChartTickets = new Chart(canvasT, {
        type: 'doughnut',
        data: { labels: tickets.map(t => t.estado), datasets: [{ data: tickets.map(t => t.cantidad), backgroundColor: coloresPaleta(tickets.length) }] },
        options: CHART_OPCIONES_BASE
      });
    }

    // Tablas top productos / servicios
    const tbP = document.getElementById('rep-tbody-productos');
    tbP.innerHTML = (res.top_productos || []).length
      ? res.top_productos.map(p => `<tr><td>${escapeHtml(p.nombre)}</td><td>${p.unidades}</td><td>${fmtMoneda(p.total)}</td></tr>`).join('')
      : '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1.5rem">Sin datos en este periodo.</td></tr>';

    const tbS = document.getElementById('rep-tbody-servicios');
    tbS.innerHTML = (res.top_servicios || []).length
      ? res.top_servicios.map(s => `<tr><td>${escapeHtml(s.nombre)}</td><td>${s.unidades}</td><td>${fmtMoneda(s.total)}</td></tr>`).join('')
      : '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1.5rem">Sin datos en este periodo.</td></tr>';

  } catch (err) {
    console.error('Error cargando reportes:', err);
  }
}
function limpiarFiltroReportes() {
  document.getElementById('rep-desde').value = '';
  document.getElementById('rep-hasta').value = '';
  cargarReportes();
}
function seccionesReportesSeleccionadas() {
  return Array.from(document.querySelectorAll('.rep-sec-check:checked')).map(c => c.value);
}

function exportarReporte(formato) {
  const secciones = seccionesReportesSeleccionadas();
  if (secciones.length === 0) { alert('Selecciona al menos una sección para exportar.'); return; }
  const desde = document.getElementById('rep-desde').value;
  const hasta = document.getElementById('rep-hasta').value;
  const params = new URLSearchParams({ accion: formato === 'pdf' ? 'exportar_pdf' : 'exportar_excel' });
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  params.set('secciones', secciones.join(','));
  window.open(`${REP_URL}?${params.toString()}`, '_blank');
  toggleExportMenu('rep-export-menu');

}

const navReportes = document.getElementById('nav-reportes');
if (navReportes) {
  navReportes.addEventListener('click', () => {
    initSeccionesExportReportes();
    if (!repCargado) {
      repCargado = true;
      cargarReportes();
    }
  });
}

// ══════════════════════════════════════════════════════════════════════════
// ── MÓDULO NOTAS (Base de Conocimiento Técnica) ─────────────────────────────
// ══════════════════════════════════════════════════════════════════════════
const NOTAS_URL = '/spectre_erp/src/modules/notas/controllers/NotaController.php';
let notasCargado = false;

let notasCategoriasCache = [];      // lista plana de categorías (con conteo)
let notasCategoriaActivaId = null;  // filtro de categoría seleccionado
let notasFiltroRapidoActual = 'todos';
let notasBusquedaTimeout = null;
let notasProcActivo = null;         // procedimiento actualmente abierto en el modal "ver"
let notasPasosEditor = [];          // pasos en edición (array en memoria)
let notasPasoLocalSeq = 0;
let notasTagsEditor = [];

const NOTAS_TIPO_LABEL = {
  texto: 'Texto', cmd: 'CMD', powershell: 'PowerShell', bash: 'Bash',
  codigo: 'Código (otro lenguaje)', sql: 'SQL', json: 'JSON', xml: 'XML',
  html: 'HTML', css: 'CSS', javascript: 'JavaScript', php: 'PHP', reg: 'Registro (.reg)',
  archivo: 'Archivo', enlace: 'Enlace', video: 'Video', imagen: 'Imagen',
  nota: 'Nota', checklist: 'Checklist', advertencia: 'Advertencia', recomendacion: 'Recomendación',
};
const NOTAS_TIPOS_CODIGO = ['cmd','powershell','bash','codigo','sql','json','xml','html','css','javascript','php','reg'];

function notasPrismLang(tipo, lenguajeCustom) {
  const mapa = {
    cmd: 'batch', powershell: 'powershell', bash: 'bash', sql: 'sql', json: 'json',
    xml: 'markup', html: 'markup', css: 'css', javascript: 'javascript', php: 'php', reg: 'ini',
  };
  if (tipo === 'codigo') return (lenguajeCustom || 'clike').toLowerCase();
  return mapa[tipo] || 'clike';
}

// ── Toast ────────────────────────────────────────────────────────────────
function notasToast(mensaje) {
  let toast = document.getElementById('notas-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'notas-toast';
    toast.className = 'notas-toast';
    document.body.appendChild(toast);
  }
  toast.textContent = mensaje;
  toast.classList.add('show');
  clearTimeout(toast._t);
  toast._t = setTimeout(() => toast.classList.remove('show'), 2200);
}

// ── Copiar al portapapeles ──────────────────────────────────────────────
async function notasCopiarTexto(texto, idProcedimiento, idPaso) {
  try {
    await navigator.clipboard.writeText(texto);
  } catch (err) {
    const ta = document.createElement('textarea');
    ta.value = texto;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (err2) { /* último recurso agotado */ }
    document.body.removeChild(ta);
  }
  notasToast('📋 Copiado correctamente');

  if (idProcedimiento) {
    fetch(`${NOTAS_URL}?accion=registrar_copia`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id_procedimiento: idProcedimiento, id_paso: idPaso || '', csrf_token: CSRF_TOKEN }),
    }).catch(() => {});
  }
}
function notasCopiarPaso(idPaso) {
  if (!notasProcActivo) return;
  const paso = notasProcActivo.pasos.find(p => String(p.id_paso) === String(idPaso));
  if (!paso) return;
  notasCopiarTexto(paso.contenido || '', notasProcActivo.id_procedimiento, idPaso);
}

// ── Categorías: cargar + renderizar árbol ───────────────────────────────
async function notasCargarCategorias() {
  try {
    const r = await fetch(`${NOTAS_URL}?accion=categorias`);
    const json = await r.json();
    if (!json.success) return;
    notasCategoriasCache = json.categorias;
    notasRenderArbol();
    notasPoblarSelectsCategoria();
  } catch (err) { console.error('Error cargando categorías de notas:', err); }
}

function notasRenderArbol() {
  const cont = document.getElementById('notas-arbol');
  const raices = notasCategoriasCache.filter(c => !c.id_categoria_padre);

  if (raices.length === 0) {
    cont.innerHTML = '<div class="chart-empty" style="padding:1rem 0;">Sin categorías aún.</div>';
    return;
  }

  const totalGeneral = notasCategoriasCache.reduce((acc, c) => acc + Number(c.total_procedimientos || 0), 0);

  let html = `<div class="notas-arbol-item ${notasCategoriaActivaId === null ? 'active' : ''}" onclick="notasSeleccionarCategoria(null)">
    <span class="dot" style="background:#4f6ef7"></span> Todas <span class="cnt">${totalGeneral}</span>
  </div>`;

  raices.sort((a, b) => a.orden - b.orden || a.nombre.localeCompare(b.nombre));
  raices.forEach(raiz => {
    const activo = notasCategoriaActivaId === raiz.id_categoria;
    html += `<div class="notas-arbol-item ${activo ? 'active' : ''}" onclick="notasSeleccionarCategoria(${raiz.id_categoria})" oncontextmenu="event.preventDefault(); notasAbrirCategoria(${raiz.id_categoria});">
      <span class="dot" style="background:${raiz.color || '#4f6ef7'}"></span> ${escapeHtml(raiz.icono ? raiz.icono + ' ' : '')}${escapeHtml(raiz.nombre)} <span class="cnt">${raiz.total_procedimientos}</span>
    </div>`;

    const hijos = notasCategoriasCache
      .filter(c => c.id_categoria_padre === raiz.id_categoria)
      .sort((a, b) => a.orden - b.orden || a.nombre.localeCompare(b.nombre));

    hijos.forEach(hijo => {
      const activoHijo = notasCategoriaActivaId === hijo.id_categoria;
      html += `<div class="notas-arbol-item sub ${activoHijo ? 'active' : ''}" onclick="notasSeleccionarCategoria(${hijo.id_categoria})" oncontextmenu="event.preventDefault(); notasAbrirCategoria(${hijo.id_categoria});">
        <span class="dot" style="background:${hijo.color || '#4f6ef7'}"></span> ${escapeHtml(hijo.icono ? hijo.icono + ' ' : '')}${escapeHtml(hijo.nombre)} <span class="cnt">${hijo.total_procedimientos}</span>
      </div>`;
    });
  });

  cont.innerHTML = html;
}

function notasPoblarSelectsCategoria() {
  const opciones = notasCategoriasCache
    .slice()
    .sort((a, b) => (a.id_categoria_padre || 0) - (b.id_categoria_padre || 0))
    .map(c => {
      const prefijo = c.id_categoria_padre ? '— ' : '';
      return `<option value="${c.id_categoria}">${prefijo}${escapeHtml(c.nombre)}</option>`;
    }).join('');

  const selEditor = document.getElementById('notas-editor-categoria');
  const selImport = document.getElementById('notas-import-categoria');
  if (selEditor) selEditor.innerHTML = opciones;
  if (selImport) selImport.innerHTML = opciones;

  const selPadre = document.getElementById('notas-cat-padre');
  if (selPadre) {
    selPadre.innerHTML = '<option value="">— Categoría raíz —</option>' +
      notasCategoriasCache.filter(c => !c.id_categoria_padre).map(c =>
        `<option value="${c.id_categoria}">${escapeHtml(c.nombre)}</option>`
      ).join('');
  }
}

function notasSeleccionarCategoria(id) {
  notasCategoriaActivaId = id;
  notasRenderArbol();
  const cat = id ? notasCategoriasCache.find(c => c.id_categoria === id) : null;
  document.getElementById('notas-titulo-vista').textContent = cat ? cat.nombre : 'Todos los procedimientos';
  notasCargarLista();
}

// ── Filtros rápidos / búsqueda ──────────────────────────────────────────
function notasFiltroRapido(tipo, btn) {
  notasFiltroRapidoActual = tipo;
  document.querySelectorAll('.notas-filtro-chip').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  notasCargarLista();
}
function notasBuscarDebounced() {
  clearTimeout(notasBusquedaTimeout);
  notasBusquedaTimeout = setTimeout(notasCargarLista, 300);
}

// ── Listado / grid ───────────────────────────────────────────────────────
async function notasCargarLista() {
  const grid = document.getElementById('notas-grid');
  grid.innerHTML = '<div class="chart-empty">Cargando procedimientos…</div>';

  const params = new URLSearchParams();
  if (notasCategoriaActivaId) params.set('categoria', notasCategoriaActivaId);
  const q = document.getElementById('notas-buscar').value.trim();
  if (q) params.set('q', q);
  if (notasFiltroRapidoActual === 'favoritos') params.set('favoritos', '1');
  if (notasFiltroRapidoActual === 'mas_usados') params.set('orden', 'mas_usados');
  if (notasFiltroRapidoActual === 'recientes') params.set('orden', 'recientes');

  try {
    const r = await fetch(`${NOTAS_URL}?accion=listar&${params.toString()}`);
    const json = await r.json();
    if (!json.success) { grid.innerHTML = `<div class="chart-empty">${escapeHtml(json.message || 'No se pudo cargar.')}</div>`; return; }
    notasRenderGrid(json.items);
  } catch (err) {
    grid.innerHTML = '<div class="chart-empty">No se pudo conectar con el servidor.</div>';
  }
}

function notasRenderGrid(items) {
  const grid = document.getElementById('notas-grid');
  document.getElementById('notas-contador').textContent = `${items.length} procedimiento(s)`;

  if (items.length === 0) {
    grid.innerHTML = '<div class="chart-empty" style="grid-column:1/-1;">No hay procedimientos que coincidan. Prueba con otra búsqueda o crea uno nuevo.</div>';
    return;
  }

  grid.innerHTML = items.map(p => {
    const tags = (p.etiquetas || []).slice(0, 3).map(t => `<span class="notas-tag">${escapeHtml(t)}</span>`).join('');
    const estadoBadge = p.estado !== 'publicado'
      ? `<span class="status ${p.estado === 'borrador' ? 'amarillo' : 'rojo'}" style="margin-left:.4rem;">${p.estado}</span>` : '';
    return `
    <div class="notas-card" onclick="notasAbrirVer(${p.id_procedimiento})">
      <div class="notas-card-top">
        <div class="notas-card-icono" style="background:${p.color || '#4f6ef7'}22;color:${p.color || '#4f6ef7'}">${escapeHtml(p.icono || '📄')}</div>
        <button class="notas-card-fav ${p.es_favorito ? 'activo' : ''}" onclick="event.stopPropagation(); notasAlternarFavoritoDesdeCard(${p.id_procedimiento}, this)">★</button>
      </div>
      <h4>${escapeHtml(p.titulo)}${estadoBadge}</h4>
      <p>${escapeHtml(p.descripcion || 'Sin descripción.')}</p>
      ${tags ? `<div class="notas-card-tags">${tags}</div>` : ''}
      <div class="notas-card-footer">
        <span>${escapeHtml(p.categoria_nombre)} · ${p.total_pasos} paso(s)</span>
        <span>🔥 ${p.veces_utilizado}</span>
      </div>
    </div>`;
  }).join('');
}

async function notasAlternarFavoritoDesdeCard(id, btnEl) {
  try {
    const r = await fetch(`${NOTAS_URL}?accion=favorito`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id_procedimiento: id, csrf_token: CSRF_TOKEN }),
    });
    const json = await r.json();
    if (json.success) {
      btnEl.classList.toggle('activo', json.favorito);
      notasCargarResumen();
    }
  } catch (err) { console.error(err); }
}

// ── Estadísticas ─────────────────────────────────────────────────────────
async function notasCargarResumen() {
  try {
    const r = await fetch(`${NOTAS_URL}?accion=resumen`);
    const json = await r.json();
    if (!json.success) return;
    const res = json.resumen;
    document.getElementById('notas-stat-total').textContent = res.total_procedimientos;
    document.getElementById('notas-stat-categorias').textContent = res.total_categorias;
    document.getElementById('notas-stat-copiados').textContent = res.total_copiados;
    document.getElementById('notas-stat-favoritos').textContent = res.total_favoritos;
  } catch (err) { console.error('Error cargando resumen de notas:', err); }
}

// ── Ver procedimiento completo ──────────────────────────────────────────
async function notasAbrirVer(id) {
  try {
    const r = await fetch(`${NOTAS_URL}?accion=detalle&id_procedimiento=${id}`);
    const json = await r.json();
    if (!json.success) { alert(json.message || 'No se pudo abrir el procedimiento.'); return; }

    notasProcActivo = json.procedimiento;
    const p = notasProcActivo;

    document.getElementById('notas-ver-titulo').textContent = (p.icono ? p.icono + ' ' : '') + p.titulo;
    const autor = p.autor_nombre ? `${p.autor_nombre} ${p.autor_apellido || ''}`.trim() : 'Autor desconocido';
    document.getElementById('notas-ver-sub').textContent =
      `${p.categoria_nombre} · Por ${autor} · v${p.version_actual} · Usado ${p.veces_utilizado} vez/veces`;
    document.getElementById('notas-ver-desc').textContent = p.descripcion || '';
    document.getElementById('notas-ver-tags').innerHTML = (p.etiquetas || [])
      .map(e => `<span class="notas-tag">${escapeHtml(e.nombre)}</span>`).join('');

    const favBtn = document.getElementById('notas-ver-fav-btn');
    favBtn.classList.toggle('activo', !!p.es_favorito);

    document.getElementById('notas-ver-pasos').innerHTML = p.pasos.map((paso, i) => notasRenderPasoVer(paso, i)).join('');

    const adjCont = document.getElementById('notas-ver-adjuntos');
    const adjWrap = document.getElementById('notas-ver-adjuntos-wrap');
    if (p.adjuntos && p.adjuntos.length) {
      adjWrap.style.display = 'block';
      adjCont.innerHTML = p.adjuntos.map(a => `
        <div class="notas-adjunto-item">
          <a href="/${a.ruta_archivo}" target="_blank">📎 ${escapeHtml(a.nombre_original)}</a>
          <span>${notasFmtTamano(a.tamano_bytes)}</span>
        </div>`).join('');
    } else {
      adjWrap.style.display = 'none';
    }

    document.getElementById('notas-modal-ver').classList.add('open');

    if (window.Prism) setTimeout(() => Prism.highlightAllUnder(document.getElementById('notas-modal-ver')), 30);

    notasCargarResumen();
    notasCargarCategorias(); // refresca conteos del árbol
  } catch (err) {
    alert('No se pudo conectar con el servidor.');
  }
}

function notasRenderPasoVer(paso, index) {
  const num = index + 1;
  const badge = `<span class="notas-paso-tipo-badge">${NOTAS_TIPO_LABEL[paso.tipo] || paso.tipo}</span>`;
  const titulo = `<h4><span class="notas-paso-num">${num}</span> ${escapeHtml(paso.titulo || 'Paso ' + num)} ${badge}</h4>`;
  const desc = paso.descripcion ? `<div class="notas-paso-desc">${escapeHtml(paso.descripcion)}</div>` : '';

  let cuerpo = '';

  if (NOTAS_TIPOS_CODIGO.includes(paso.tipo) && paso.contenido) {
    const lang = notasPrismLang(paso.tipo, paso.lenguaje);
    cuerpo = `
      <div class="notas-bloque-codigo">
        <button class="notas-btn-copiar" onclick="notasCopiarPaso(${paso.id_paso})">📋 Copiar</button>
        <pre><code class="language-${lang}">${escapeHtml(paso.contenido)}</code></pre>
      </div>`;
  } else if (paso.tipo === 'checklist') {
    cuerpo = '<div>' + (paso.checklist_items || []).map((item, idx) => `
      <label class="notas-checklist-item" onclick="this.classList.toggle('checked')">
        <input type="checkbox"> <span>${escapeHtml(item.texto)}</span>
      </label>`).join('') + '</div>';
  } else if (paso.tipo === 'advertencia') {
    cuerpo = `<div class="notas-advertencia">⚠️ ${escapeHtml(paso.contenido || '')}</div>`;
  } else if (paso.tipo === 'recomendacion') {
    cuerpo = `<div class="notas-recomendacion">💡 ${escapeHtml(paso.contenido || '')}</div>`;
  } else if (paso.tipo === 'enlace' && paso.contenido) {
    cuerpo = `<a class="notas-enlace" href="${escapeHtml(paso.contenido)}" target="_blank" rel="noopener">🔗 ${escapeHtml(paso.contenido)}</a>
      <button class="btn-mini" style="margin-left:.5rem;" onclick="notasCopiarPaso(${paso.id_paso})">📋 Copiar enlace</button>`;
  } else if (paso.tipo === 'imagen' && paso.contenido) {
    cuerpo = `<img src="${escapeHtml(paso.contenido)}" style="max-width:100%;border-radius:8px;border:1px solid var(--border);" alt="${escapeHtml(paso.titulo || '')}">`;
  } else if (paso.tipo === 'video' && paso.contenido) {
    cuerpo = `<a class="notas-enlace" href="${escapeHtml(paso.contenido)}" target="_blank" rel="noopener">▶️ Ver video</a>`;
  } else if (paso.contenido) {
    cuerpo = `<div class="notas-paso-desc" style="margin-bottom:0;">${escapeHtml(paso.contenido)}</div>
      <button class="btn-mini" style="margin-top:.5rem;" onclick="notasCopiarPaso(${paso.id_paso})">📋 Copiar</button>`;
  }

  return `<div class="notas-paso-view">${titulo}${desc}${cuerpo}</div>`;
}

function notasFmtTamano(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function notasCerrarVer() {
  document.getElementById('notas-modal-ver').classList.remove('open');
  notasProcActivo = null;
}
document.getElementById('notas-modal-ver').addEventListener('click', (e) => {
  if (e.target.id === 'notas-modal-ver') notasCerrarVer();
});

async function notasAlternarFavoritoActivo() {
  if (!notasProcActivo) return;
  const r = await fetch(`${NOTAS_URL}?accion=favorito`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id_procedimiento: notasProcActivo.id_procedimiento, csrf_token: CSRF_TOKEN }),
  });
  const json = await r.json();
  if (json.success) {
    document.getElementById('notas-ver-fav-btn').classList.toggle('activo', json.favorito);
    notasCargarResumen();
    notasCargarLista();
  }
}

function notasExportar(formato) {
  if (!notasProcActivo) return;
  const url = `${NOTAS_URL}?accion=exportar_${formato}&id_procedimiento=${notasProcActivo.id_procedimiento}`;
  window.open(url, '_blank');
}

function notasEliminarDesdeVista() {
  if (!notasProcActivo) return;
  if (!confirm(`¿Eliminar "${notasProcActivo.titulo}"? Esta acción no se puede deshacer.`)) return;

  fetch(`${NOTAS_URL}?accion=eliminar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id_procedimiento: notasProcActivo.id_procedimiento, csrf_token: CSRF_TOKEN }),
  })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        notasCerrarVer();
        notasCargarLista();
        notasCargarResumen();
        notasCargarCategorias();
      } else {
        alert(json.message);
      }
    })
    .catch(() => alert('No se pudo conectar con el servidor.'));
}

// ── Versiones ────────────────────────────────────────────────────────────
async function notasVerVersiones() {
  if (!notasProcActivo) return;
  const r = await fetch(`${NOTAS_URL}?accion=versiones&id_procedimiento=${notasProcActivo.id_procedimiento}`);
  const json = await r.json();
  if (!json.success) return;

  document.getElementById('notas-ver-hist-sub').textContent = notasProcActivo.titulo;
  const lista = document.getElementById('notas-versiones-lista');

  if (json.versiones.length === 0) {
    lista.innerHTML = '<div class="chart-empty">Todavía no hay versiones anteriores guardadas.</div>';
  } else {
    lista.innerHTML = json.versiones.map(v => {
      const usuario = v.usuario_nombre ? `${v.usuario_nombre} ${v.usuario_apellido || ''}`.trim() : 'Desconocido';
      const fecha = new Date(v.fecha_version.replace(' ', 'T')).toLocaleString('es-PE');
      return `<div class="notas-version-item">
        <div><span class="v-num">v${v.numero_version}</span> — ${escapeHtml(v.titulo)}
          <div class="v-meta">${fecha} · ${escapeHtml(usuario)}</div>
        </div>
        <button class="btn-mini" onclick="notasVerVersionDetalle(${v.id_version})">Ver</button>
      </div>`;
    }).join('');
  }

  document.getElementById('notas-modal-versiones').classList.add('open');
}
document.getElementById('notas-modal-versiones').addEventListener('click', (e) => {
  if (e.target.id === 'notas-modal-versiones') e.target.classList.remove('open');
});

async function notasVerVersionDetalle(idVersion) {
  const r = await fetch(`${NOTAS_URL}?accion=version_detalle&id_version=${idVersion}`);
  const json = await r.json();
  if (!json.success) return;

  const v = json.version;
  document.getElementById('notas-vd-titulo').textContent = `${v.titulo} (v${v.numero_version})`;
  document.getElementById('notas-vd-sub').textContent = new Date(v.fecha_version.replace(' ', 'T')).toLocaleString('es-PE');
  document.getElementById('notas-vd-pasos').innerHTML = (v.pasos || []).map((paso, i) => {
    // Los snapshots de versión guardan la misma estructura que se envía al guardar (sin id_paso real)
    const pasoNormalizado = Object.assign({ id_paso: 0, checklist_items: (paso.checklist_items || []).map(t => ({ texto: t })) }, paso);
    return notasRenderPasoVer(pasoNormalizado, i);
  }).join('');

  document.getElementById('notas-modal-version-detalle').classList.add('open');
  if (window.Prism) setTimeout(() => Prism.highlightAllUnder(document.getElementById('notas-modal-version-detalle')), 30);
}
document.getElementById('notas-modal-version-detalle').addEventListener('click', (e) => {
  if (e.target.id === 'notas-modal-version-detalle') e.target.classList.remove('open');
});

// ── Crear / Editar procedimiento ────────────────────────────────────────
function notasEditarDesdeVista() {
  if (!notasProcActivo) return;
  const id = notasProcActivo.id_procedimiento;
  notasCerrarVer();
  notasAbrirEditor(id);
}

async function notasAbrirEditor(id) {
  notasPasosEditor = [];
  notasTagsEditor = [];
  notasPasoLocalSeq = 0;

  document.getElementById('notas-editor-id').value = '';
  document.getElementById('notas-editor-titulo').value = '';
  document.getElementById('notas-editor-descripcion').value = '';
  document.getElementById('notas-editor-icono').value = '';
  document.getElementById('notas-editor-color').value = '#4f6ef7';
  document.getElementById('notas-editor-estado').value = 'publicado';
  document.getElementById('notas-editor-msg').className = 'inv-modal-msg';
  document.getElementById('notas-editor-adjuntos-seccion').style.display = 'none';
  document.getElementById('notas-editor-adjuntos-lista').innerHTML = '';
  notasRenderTagsEditor();

  if (notasCategoriasCache.length === 0) {
    await notasCargarCategorias();
  } else {
    notasPoblarSelectsCategoria();
  }

  if (id) {
    document.getElementById('notas-editor-titulo-modal').textContent = 'Editar Procedimiento';
    try {
      const r = await fetch(`${NOTAS_URL}?accion=detalle&id_procedimiento=${id}&sin_contar=1`);
      const json = await r.json();
      if (json.success) {
        const p = json.procedimiento;
        document.getElementById('notas-editor-id').value = p.id_procedimiento;
        document.getElementById('notas-editor-titulo').value = p.titulo;
        document.getElementById('notas-editor-descripcion').value = p.descripcion || '';
        document.getElementById('notas-editor-categoria').value = p.id_categoria;
        document.getElementById('notas-editor-icono').value = p.icono || '';
        document.getElementById('notas-editor-color').value = p.color || '#4f6ef7';
        document.getElementById('notas-editor-estado').value = p.estado;
        notasTagsEditor = (p.etiquetas || []).map(e => e.nombre);
        notasRenderTagsEditor();

        notasPasosEditor = p.pasos.map(paso => ({
          localId: ++notasPasoLocalSeq,
          titulo: paso.titulo || '',
          descripcion: paso.descripcion || '',
          tipo: paso.tipo,
          contenido: paso.contenido || '',
          lenguaje: paso.lenguaje || '',
          checklist_items: (paso.checklist_items || []).map(it => it.texto),
        }));

        document.getElementById('notas-editor-adjuntos-seccion').style.display = 'block';
        notasRenderAdjuntosEditor(p.adjuntos || []);
      }
    } catch (err) { console.error(err); }
  } else {
    document.getElementById('notas-editor-titulo-modal').textContent = 'Nuevo Procedimiento';
    notasAgregarPaso();
  }

  notasRenderPasosEditor();
  document.getElementById('notas-modal-editar').classList.add('open');
}

function notasCerrarEditor() {
  document.getElementById('notas-modal-editar').classList.remove('open');
}
document.getElementById('notas-modal-editar').addEventListener('click', (e) => {
  if (e.target.id === 'notas-modal-editar') notasCerrarEditor();
});

// ── Etiquetas (tags) ─────────────────────────────────────────────────────
function notasTagKeydown(event) {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault();
    const input = event.target;
    const valor = input.value.trim().replace(/,$/, '');
    if (valor && !notasTagsEditor.includes(valor)) {
      notasTagsEditor.push(valor);
      notasRenderTagsEditor();
    }
    input.value = '';
  }
}
function notasQuitarTag(nombre) {
  notasTagsEditor = notasTagsEditor.filter(t => t !== nombre);
  notasRenderTagsEditor();
}
function notasRenderTagsEditor() {
  const wrap = document.getElementById('notas-editor-tags-wrap');
  const input = document.getElementById('notas-editor-tags-input');
  wrap.querySelectorAll('.notas-tag-chip').forEach(el => el.remove());
  notasTagsEditor.forEach(t => {
    const chip = document.createElement('span');
    chip.className = 'notas-tag-chip';
    chip.innerHTML = `${escapeHtml(t)} <button type="button" onclick="notasQuitarTag('${t.replace(/'/g, "\\'")}')">×</button>`;
    wrap.insertBefore(chip, input);
  });
}

// ── Constructor de pasos ─────────────────────────────────────────────────
function notasAgregarPaso() {
  notasPasosEditor.push({
    localId: ++notasPasoLocalSeq,
    titulo: '', descripcion: '', tipo: 'texto', contenido: '', lenguaje: '',
    checklist_items: [''],
  });
  notasRenderPasosEditor();
}
function notasQuitarPaso(localId) {
  notasPasosEditor = notasPasosEditor.filter(p => p.localId !== localId);
  notasRenderPasosEditor();
}
function notasMoverPaso(localId, direccion) {
  const idx = notasPasosEditor.findIndex(p => p.localId === localId);
  const nuevoIdx = idx + direccion;
  if (idx < 0 || nuevoIdx < 0 || nuevoIdx >= notasPasosEditor.length) return;
  const [item] = notasPasosEditor.splice(idx, 1);
  notasPasosEditor.splice(nuevoIdx, 0, item);
  notasRenderPasosEditor();
}
function notasActualizarCampoPaso(localId, campo, valor) {
  const paso = notasPasosEditor.find(p => p.localId === localId);
  if (paso) paso[campo] = valor;
}
function notasCambiarTipoPaso(localId, nuevoTipo) {
  const paso = notasPasosEditor.find(p => p.localId === localId);
  if (paso) {
    paso.tipo = nuevoTipo;
    if (nuevoTipo === 'checklist' && paso.checklist_items.length === 0) paso.checklist_items = [''];
  }
  notasRenderPasosEditor();
}
function notasChecklistAgregarItem(localId) {
  const paso = notasPasosEditor.find(p => p.localId === localId);
  if (paso) paso.checklist_items.push('');
  notasRenderPasosEditor();
}
function notasChecklistQuitarItem(localId, idx) {
  const paso = notasPasosEditor.find(p => p.localId === localId);
  if (paso) paso.checklist_items.splice(idx, 1);
  notasRenderPasosEditor();
}
function notasChecklistActualizarItem(localId, idx, valor) {
  const paso = notasPasosEditor.find(p => p.localId === localId);
  if (paso) paso.checklist_items[idx] = valor;
}

function notasRenderPasosEditor() {
  const cont = document.getElementById('notas-editor-pasos');

  const opcionesTipo = Object.entries(NOTAS_TIPO_LABEL)
    .map(([val, label]) => `<option value="${val}">${label}</option>`).join('');

  cont.innerHTML = notasPasosEditor.map((paso, i) => {
    const esCodigo = NOTAS_TIPOS_CODIGO.includes(paso.tipo);
    const esChecklist = paso.tipo === 'checklist';
    const esUrl = ['enlace', 'video', 'imagen'].includes(paso.tipo);

    let bloqueContenido = '';
    if (esChecklist) {
      bloqueContenido = `<label>Ítems del checklist</label>` +
        paso.checklist_items.map((texto, idx) => `
          <div class="notas-checklist-editor-item">
            <input type="text" value="${escapeHtml(texto)}" placeholder="Ej. Reiniciar el equipo"
              oninput="notasChecklistActualizarItem(${paso.localId}, ${idx}, this.value)">
            <button type="button" class="btn-mini" onclick="notasChecklistQuitarItem(${paso.localId}, ${idx})">✕</button>
          </div>`).join('') +
        `<button type="button" class="btn-mini" style="margin-top:.4rem;" onclick="notasChecklistAgregarItem(${paso.localId})">+ Ítem</button>`;
    } else if (esUrl) {
      bloqueContenido = `<label>${paso.tipo === 'enlace' ? 'URL del enlace' : paso.tipo === 'video' ? 'URL del video' : 'URL de la imagen'}</label>
        <input type="text" value="${escapeHtml(paso.contenido)}" placeholder="https://…"
          oninput="notasActualizarCampoPaso(${paso.localId}, 'contenido', this.value)">`;
    } else if (['advertencia', 'recomendacion', 'nota', 'texto'].includes(paso.tipo)) {
      bloqueContenido = `<label>Contenido</label>
        <textarea rows="3" placeholder="Escribe el contenido…" oninput="notasActualizarCampoPaso(${paso.localId}, 'contenido', this.value)">${escapeHtml(paso.contenido)}</textarea>`;
    } else if (paso.tipo === 'archivo') {
      bloqueContenido = `<div class="ayuda">Sube el archivo desde la sección "Archivos adjuntos" más abajo (disponible después de guardar). Aquí puedes describirlo:</div>
        <textarea rows="2" placeholder="Ej. Plantilla configuration.xml para Office 2024" oninput="notasActualizarCampoPaso(${paso.localId}, 'contenido', this.value)">${escapeHtml(paso.contenido)}</textarea>`;
    } else {
      // tipos de código
      bloqueContenido = `
        ${paso.tipo === 'codigo' ? `<label>Lenguaje (para el resaltado, ej. python, csharp)</label>
          <input type="text" value="${escapeHtml(paso.lenguaje)}" placeholder="python"
            oninput="notasActualizarCampoPaso(${paso.localId}, 'lenguaje', this.value)">` : ''}
        <label>Contenido / comando</label>
        <textarea rows="4" class="mono" placeholder="Escribe el comando o código…" oninput="notasActualizarCampoPaso(${paso.localId}, 'contenido', this.value)">${escapeHtml(paso.contenido)}</textarea>`;
    }

    return `
      <div class="notas-paso-editor">
        <div class="notas-paso-editor-hdr">
          <span class="notas-paso-num">${i + 1}</span>
          <select style="width:auto;margin-top:0;flex:0 0 180px;" onchange="notasCambiarTipoPaso(${paso.localId}, this.value)">
            ${opcionesTipo.replace(`value="${paso.tipo}"`, `value="${paso.tipo}" selected`)}
          </select>
          <input type="text" style="margin-top:0;flex:1;" placeholder="Título del paso (opcional)" value="${escapeHtml(paso.titulo)}"
            oninput="notasActualizarCampoPaso(${paso.localId}, 'titulo', this.value)">
          <div class="notas-paso-editor-acciones">
            <button type="button" class="btn-mini" onclick="notasMoverPaso(${paso.localId}, -1)" title="Subir">↑</button>
            <button type="button" class="btn-mini" onclick="notasMoverPaso(${paso.localId}, 1)" title="Bajar">↓</button>
            <button type="button" class="btn-mini" onclick="notasQuitarPaso(${paso.localId})" title="Eliminar">✕</button>
          </div>
        </div>
        <textarea rows="1" placeholder="Descripción breve del paso (opcional)" style="margin-bottom:.5rem;"
          oninput="notasActualizarCampoPaso(${paso.localId}, 'descripcion', this.value)">${escapeHtml(paso.descripcion)}</textarea>
        ${bloqueContenido}
      </div>`;
  }).join('');
}

// ── Adjuntos (dentro del editor) ────────────────────────────────────────
function notasRenderAdjuntosEditor(adjuntos) {
  const cont = document.getElementById('notas-editor-adjuntos-lista');
  if (!adjuntos.length) {
    cont.innerHTML = '<div class="ayuda">Sin archivos adjuntos todavía.</div>';
    return;
  }
  cont.innerHTML = adjuntos.map(a => `
    <div class="notas-adjunto-item">
      <a href="/${a.ruta_archivo}" target="_blank">📎 ${escapeHtml(a.nombre_original)}</a>
      <button type="button" class="btn-mini" onclick="notasEliminarAdjunto(${a.id_adjunto})">Eliminar</button>
    </div>`).join('');
}

async function notasSubirAdjunto() {
  const idProcedimiento = document.getElementById('notas-editor-id').value;
  const input = document.getElementById('notas-editor-archivo-input');
  if (!idProcedimiento || !input.files[0]) return;

  const formData = new FormData();
  formData.append('archivo', input.files[0]);
  formData.append('id_procedimiento', idProcedimiento);
  formData.append('csrf_token', CSRF_TOKEN);

  try {
    const r = await fetch(`${NOTAS_URL}?accion=adjunto_subir`, { method: 'POST', body: formData });
    const json = await r.json();
    if (!json.success) { alert(json.message); return; }
    notasToast('Archivo adjuntado correctamente.');
    input.value = '';
    const detalle = await (await fetch(`${NOTAS_URL}?accion=detalle&id_procedimiento=${idProcedimiento}&sin_contar=1`)).json();
    if (detalle.success) notasRenderAdjuntosEditor(detalle.procedimiento.adjuntos);
  } catch (err) { alert('No se pudo subir el archivo.'); }
}

async function notasEliminarAdjunto(idAdjunto) {
  if (!confirm('¿Eliminar este archivo adjunto?')) return;
  const idProcedimiento = document.getElementById('notas-editor-id').value;
  const r = await fetch(`${NOTAS_URL}?accion=adjunto_eliminar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id_adjunto: idAdjunto, csrf_token: CSRF_TOKEN }),
  });
  const json = await r.json();
  if (json.success) {
    const detalle = await (await fetch(`${NOTAS_URL}?accion=detalle&id_procedimiento=${idProcedimiento}&sin_contar=1`)).json();
    if (detalle.success) notasRenderAdjuntosEditor(detalle.procedimiento.adjuntos);
  }
}

// ── Guardar procedimiento ────────────────────────────────────────────────
async function notasGuardarProcedimiento() {
  const msg = document.getElementById('notas-editor-msg');
  msg.className = 'inv-modal-msg';

  const titulo = document.getElementById('notas-editor-titulo').value.trim();
  if (!titulo) { msg.textContent = 'El título es obligatorio.'; msg.className = 'inv-modal-msg err'; return; }
  if (notasPasosEditor.length === 0) { msg.textContent = 'Agrega al menos un paso.'; msg.className = 'inv-modal-msg err'; return; }

  const pasosParaEnviar = notasPasosEditor.map(p => ({
    titulo: p.titulo, descripcion: p.descripcion, tipo: p.tipo,
    contenido: p.contenido, lenguaje: p.lenguaje,
    checklist_items: p.tipo === 'checklist' ? p.checklist_items.filter(t => t.trim() !== '') : undefined,
  }));

  const idExistente = document.getElementById('notas-editor-id').value;
  const params = new URLSearchParams({
    titulo,
    descripcion: document.getElementById('notas-editor-descripcion').value,
    id_categoria: document.getElementById('notas-editor-categoria').value,
    icono: document.getElementById('notas-editor-icono').value,
    color: document.getElementById('notas-editor-color').value,
    estado: document.getElementById('notas-editor-estado').value,
    etiquetas: notasTagsEditor.join(','),
    pasos_json: JSON.stringify(pasosParaEnviar),
    csrf_token: CSRF_TOKEN,
  });
  if (idExistente) params.set('id_procedimiento', idExistente);

  try {
    const r = await fetch(`${NOTAS_URL}?accion=${idExistente ? 'actualizar' : 'crear'}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params,
    });
    const json = await r.json();
    if (!json.success) { msg.textContent = json.message; msg.className = 'inv-modal-msg err'; return; }

    msg.textContent = json.message;
    msg.className = 'inv-modal-msg ok';

    if (!idExistente && json.id_procedimiento) {
      // Habilitar adjuntos inmediatamente después de crear, sin cerrar el modal
      document.getElementById('notas-editor-id').value = json.id_procedimiento;
      document.getElementById('notas-editor-titulo-modal').textContent = 'Editar Procedimiento';
      document.getElementById('notas-editor-adjuntos-seccion').style.display = 'block';
      notasRenderAdjuntosEditor([]);
    }

    notasCargarLista();
    notasCargarResumen();
    notasCargarCategorias();

    setTimeout(() => { notasCerrarEditor(); }, 900);
  } catch (err) {
    msg.textContent = 'No se pudo conectar con el servidor.';
    msg.className = 'inv-modal-msg err';
  }
}

// ── Categorías: crear / editar / eliminar ───────────────────────────────
function notasAbrirCategoria(id) {
  document.getElementById('notas-cat-id').value = '';
  document.getElementById('notas-cat-nombre').value = '';
  document.getElementById('notas-cat-padre').value = '';
  document.getElementById('notas-cat-icono').value = '';
  document.getElementById('notas-cat-color').value = '#4f6ef7';
  document.getElementById('notas-cat-msg').className = 'inv-modal-msg';
  const btnEliminar = document.getElementById('notas-cat-btn-eliminar');
  if (btnEliminar) btnEliminar.style.display = 'none';

  if (id) {
    const cat = notasCategoriasCache.find(c => c.id_categoria === id);
    if (cat) {
      document.getElementById('notas-cat-id').value = cat.id_categoria;
      document.getElementById('notas-cat-nombre').value = cat.nombre;
      document.getElementById('notas-cat-padre').value = cat.id_categoria_padre || '';
      document.getElementById('notas-cat-icono').value = cat.icono || '';
      document.getElementById('notas-cat-color').value = cat.color || '#4f6ef7';
      if (btnEliminar) btnEliminar.style.display = 'inline-block';
    }
  }

  document.getElementById('notas-modal-categoria').classList.add('open');
}
function notasCerrarCategoria() {
  document.getElementById('notas-modal-categoria').classList.remove('open');
}
document.getElementById('notas-modal-categoria').addEventListener('click', (e) => {
  if (e.target.id === 'notas-modal-categoria') notasCerrarCategoria();
});

async function notasGuardarCategoria() {
  const msg = document.getElementById('notas-cat-msg');
  const id = document.getElementById('notas-cat-id').value;
  const params = new URLSearchParams({
    nombre: document.getElementById('notas-cat-nombre').value,
    id_categoria_padre: document.getElementById('notas-cat-padre').value,
    icono: document.getElementById('notas-cat-icono').value,
    color: document.getElementById('notas-cat-color').value,
    csrf_token: CSRF_TOKEN,
  });
  if (id) params.set('id_categoria', id);

  const r = await fetch(`${NOTAS_URL}?accion=${id ? 'categoria_actualizar' : 'categoria_crear'}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params,
  });
  const json = await r.json();
  msg.textContent = json.message;
  msg.className = 'inv-modal-msg ' + (json.success ? 'ok' : 'err');

  if (json.success) {
    await notasCargarCategorias();
    notasCargarLista();
    setTimeout(notasCerrarCategoria, 700);
  }
}

async function notasEliminarCategoria() {
  const id = document.getElementById('notas-cat-id').value;
  if (!id || !confirm('¿Eliminar esta categoría?')) return;

  const r = await fetch(`${NOTAS_URL}?accion=categoria_eliminar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id_categoria: id, csrf_token: CSRF_TOKEN }),
  });
  const json = await r.json();
  if (json.success) {
    await notasCargarCategorias();
    notasCargarLista();
    notasCerrarCategoria();
  } else {
    document.getElementById('notas-cat-msg').textContent = json.message;
    document.getElementById('notas-cat-msg').className = 'inv-modal-msg err';
  }
}

// ── Importar ─────────────────────────────────────────────────────────────
function notasAbrirImportar() {
  document.getElementById('notas-import-contenido').value = '';
  document.getElementById('notas-import-msg').className = 'inv-modal-msg';
  notasPoblarSelectsCategoria();
  document.getElementById('notas-modal-importar').classList.add('open');
}
document.getElementById('notas-modal-importar').addEventListener('click', (e) => {
  if (e.target.id === 'notas-modal-importar') e.target.classList.remove('open');
});

async function notasImportar() {
  const msg = document.getElementById('notas-import-msg');
  const contenido = document.getElementById('notas-import-contenido').value;
  const idCategoria = document.getElementById('notas-import-categoria').value;

  if (!contenido.trim()) { msg.textContent = 'Pega el contenido a importar.'; msg.className = 'inv-modal-msg err'; return; }

  const r = await fetch(`${NOTAS_URL}?accion=importar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ contenido, id_categoria: idCategoria, csrf_token: CSRF_TOKEN }),
  });
  const json = await r.json();
  msg.textContent = json.message;
  msg.className = 'inv-modal-msg ' + (json.success ? 'ok' : 'err');

  if (json.success) {
    notasCargarLista();
    notasCargarResumen();
    notasCargarCategorias();
    setTimeout(() => {
      document.getElementById('notas-modal-importar').classList.remove('open');
      if (json.id_procedimiento) notasAbrirEditor(json.id_procedimiento);
    }, 900);
  }
}

// ── Carga inicial del módulo (perezosa, al entrar por primera vez) ──────
function notasCargarTodo() {
  notasCargarCategorias();
  notasCargarLista();
  notasCargarResumen();
}

const navNotas = document.getElementById('nav-notas');
if (navNotas) {
  navNotas.addEventListener('click', () => {
    if (!notasCargado) {
      notasCargado = true;
      notasCargarTodo();
    }
  });
}

// ── ACTUALIZACIÓN AUTOMÁTICA ──────────────────────────────
async function actualizarDashboard() {
  try {
    const tareas = [
      cargarResumenInventario(),
      cargarResumenVentas(),
      cargarTicketsSoporte(),
      cargarClientes()
    ];
    if (gasCargado) tareas.push(cargarResumenGastos());
    if (repCargado) tareas.push(cargarReportes());
    await Promise.all(tareas);
  } catch (err) {
    console.error('Error en actualización automática:', err);
  }
}

// ── INICIALIZAR ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  cargarResumenInventario();
  cargarResumenVentas();
  cargarClientes();
  cargarTicketsSoporte();
  
  // Si el panel de Ventas está visible por defecto, cargar datos
  if (document.getElementById('panel-ventas').classList.contains('active')) {
    venCargado = true;
    cargarVentas();
  }
  
  setInterval(actualizarDashboard, 30000);
});
</script>

</body>
</html>