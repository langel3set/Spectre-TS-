<?php
require_once __DIR__ . '/../../../../autoload.php';

iniciarSesionSegura();

if (usuarioLogueado()) {
    header('Location: /spectre_erp/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear cuenta — SPECTRE</title>

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
    padding:30px 15px;
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
    max-width:440px;
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

.fila-doble{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
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

.hint{
    margin-top:6px;
    color:var(--muted);
    font-size:.75rem;
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

@media (max-width:480px){
    .fila-doble{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>

<a class="back-link" href="/spectre_erp/index.php">
← Volver a la tienda
</a>

<div class="card">

<div class="logo">
<div class="logo-text">PAF<span>WEB</span></div>
<div class="logo-sub">Hardware Gaming</div>
</div>

<h2>Crear una cuenta</h2>

<div id="mensaje"></div>

<form id="registroForm">

<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

<div class="fila-doble">

<div class="campo">
<label>Nombre</label>
<input
type="text"
name="nombre"
required
autocomplete="given-name">
</div>

<div class="campo">
<label>Apellido</label>
<input
type="text"
name="apellido"
required
autocomplete="family-name">
</div>

</div>

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
minlength="8"
autocomplete="new-password">
<div class="hint">Mínimo 8 caracteres.</div>
</div>

<div class="campo">
<label>Confirmar contraseña</label>
<input
type="password"
name="confirmar_contrasena"
required
minlength="8"
autocomplete="new-password">
</div>

<button class="btn-submit" type="submit">
    Crear cuenta
</button>

</form>

<p class="footer-link">
    ¿Ya tienes cuenta?
    <a href="/spectre_erp/src/modules/usuarios/views/login.php">Inicia sesión</a>
</p>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const form = document.getElementById("registroForm");
    const boton = form.querySelector(".btn-submit");
    const mensaje = document.getElementById("mensaje");

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    form.addEventListener("submit", async (e) => {

        e.preventDefault();

        const contrasena = form.querySelector('[name="contrasena"]').value;
        const confirmar = form.querySelector('[name="confirmar_contrasena"]').value;

        if (contrasena !== confirmar) {
            mensaje.innerHTML = `
                <div class="error-msg">Las contraseñas no coinciden.</div>
            `;
            return;
        }

        boton.disabled = true;
        boton.textContent = "Creando cuenta...";
        mensaje.innerHTML = "";

        try {

            const datos = new FormData(form);

            const respuesta = await fetch(
                "/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=registrar",
                {
                    method: "POST",
                    body: datos,
                    credentials: "same-origin",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    }
                }
            );

            const json = await respuesta.json();

            if (json.success) {

                mensaje.innerHTML = `
                    <div class="success-msg">
                        ${escapeHtml(json.message || "Cuenta creada correctamente.")}
                    </div>
                `;

                form.reset();

                setTimeout(() => {
                    window.location.href = "/spectre_erp/src/modules/usuarios/views/login.php";
                }, 1200);

            } else {

                mensaje.innerHTML = `
                    <div class="error-msg">
                        ${escapeHtml(json.message || "No se pudo completar el registro.")}
                    </div>
                `;

            }

        } catch (error) {

            console.error(error);

            mensaje.innerHTML = `
                <div class="error-msg">
                    No fue posible conectar con el servidor.
                </div>
            `;

        } finally {

            boton.disabled = false;
            boton.textContent = "Crear cuenta";

        }

    });

});
</script>

</body>
</html>