<?php
require_once __DIR__ . '/../../../../autoload.php';
require_once __DIR__ . '/../../../config/csrf.php';

iniciarSesionSegura();

if (usuarioLogueado()) {
    header('Location: /spectre_erp/index.php');
    exit;
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — SPECTRE</title>

<style>
:root{
    --bg:#08090d;
    --bg2:#0e1018;
    --bg3:#141620;
    --border:rgba(99,102,241,.2);
    --blue:#4f6ef7;
    --violet:#7c3aed;
    --grad:linear-gradient(135deg,#4f6ef7,#7c3aed);
    --text:#e8eaf0;
    --muted:#6b7280;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    font-family:'Segoe UI',sans-serif;
    background:var(--bg);
    color:var(--text);
}

body::before{
    content:"";
    position:fixed;
    inset:0;
    pointer-events:none;
    background:
    radial-gradient(ellipse 600px 400px at 20% 60%,rgba(79,110,247,.07),transparent 70%),
    radial-gradient(ellipse 500px 350px at 80% 30%,rgba(124,58,237,.07),transparent 70%);
}

.back-link{
    position:fixed;
    top:20px;
    left:25px;
    color:var(--muted);
    text-decoration:none;
    font-size:.85rem;
}

.back-link:hover{
    color:white;
}

.card{
    width:100%;
    max-width:420px;
    background:var(--bg2);
    border:1px solid var(--border);
    border-radius:15px;
    padding:35px;
    box-shadow:0 0 80px rgba(79,110,247,.08);
    position:relative;
}

.card::before{
    content:"";
    position:absolute;
    left:15%;
    right:15%;
    top:0;
    height:2px;
    background:var(--grad);
}

.logo{
    text-align:center;
    margin-bottom:25px;
}

.logo-text{
    font-size:30px;
    font-weight:bold;
}

.logo-text span{
    background:var(--grad);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}

.logo-sub{
    color:var(--muted);
    text-transform:uppercase;
    font-size:11px;
    letter-spacing:3px;
}

h2{
    text-align:center;
    color:var(--muted);
    font-size:13px;
    margin-bottom:25px;
    text-transform:uppercase;
}

.campo{
    margin-bottom:18px;
}

label{
    display:block;
    margin-bottom:6px;
    color:var(--muted);
    font-size:12px;
    text-transform:uppercase;
}

input{
    width:100%;
    padding:12px;
    background:var(--bg3);
    border:1px solid var(--border);
    border-radius:8px;
    color:white;
    outline:none;
}

input:focus{
    border-color:var(--blue);
}

.btn-submit{
    width:100%;
    padding:13px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    background:var(--grad);
    color:white;
    font-weight:bold;
    margin-top:10px;
}

.btn-submit:disabled{
    opacity:.6;
    cursor:not-allowed;
}

.error-msg{
    margin-bottom:20px;
    background:#3b1111;
    color:#ff8d8d;
    border:1px solid #a94442;
    padding:12px;
    border-radius:8px;
    text-align:center;
}

.success-msg{
    margin-bottom:20px;
    background:#103618;
    color:#5cff90;
    border:1px solid #2ecc71;
    padding:12px;
    border-radius:8px;
    text-align:center;
}

.footer-link{
    margin-top:25px;
    text-align:center;
    color:var(--muted);
    font-size:.8rem;
}

.footer-link a{
    color:var(--blue);
}

.login-links{
    margin-top:18px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    font-size:.82rem;
}

.login-links a{
    color:var(--blue);
    text-decoration:none;
    transition:.2s;
}

.login-links a:hover{
    color:#ffffff;
}

.login-links span{
    color:var(--muted);
}

</style>
</head>
<body>

<a class="back-link" href="/spectre_erp/index.php">
← Volver a la tienda
</a>

<div class="card">

<div class="logo">
<div class="logo-text">SPECTRE</div>
<div class="logo-sub">Hardware Gaming</div>
</div>

<h2>Acceso al sistema</h2>

<div id="mensaje">
<?php if($error): ?>
<div class="error-msg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
</div>

<form id="loginForm">

<input
type="hidden"
name="csrf_token"
value="<?= csrf_token(); ?>">

<div class="campo">
<label>Correo electrónico</label>
<input
type="email"
name="correo"
required
autocomplete="email">
</div>

<div class="campo">
<label>Contraseña</label>
<input
type="password"
name="contrasena"
required
autocomplete="current-password">
</div>

<button class="btn-submit" type="submit">
    Ingresar
</button>

<div class="login-links">
    <a href="/spectre_erp/src/modules/usuarios/views/registro.php">
        Crear una cuenta
    </a>

    <span>•</span>

    <a href="/spectre_erp/src/modules/usuarios/views/recuperar.php">
        ¿Olvidaste tu contraseña?
    </a>
</div>

<p class="footer-link">
    ¿Problemas para acceder?
    <a href="/spectre_erp/src/modules/soporte/views/mail_soporte.php">
        Contacta soporte TI
    </a>
</p>

</form>

</div>
<script src="/spectre_erp/assets/js/login.js"></script>
</body>
</html>
