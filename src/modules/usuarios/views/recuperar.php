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
<title>Recuperar contraseña - SPECTRE</title>

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
    background:var(--bg);
    font-family:Segoe UI,sans-serif;
    color:var(--text);
}

.card{

    width:100%;
    max-width:430px;
    background:var(--bg2);
    border:1px solid var(--border);
    border-radius:15px;
    padding:35px;
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
    margin-bottom:20px;
    text-transform:uppercase;
    font-size:13px;

}

.descripcion{

    text-align:center;
    color:#b8bcc8;
    font-size:.9rem;
    margin-bottom:25px;
    line-height:1.5;

}

.campo{

    margin-bottom:18px;

}

label{

    display:block;
    margin-bottom:6px;
    color:var(--muted);
    text-transform:uppercase;
    font-size:12px;

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

button{

    width:100%;
    padding:13px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    color:white;
    font-weight:bold;
    background:var(--grad);

}

.error-msg{

    background:#3d1111;
    border:1px solid #b63a3a;
    color:#ffb3b3;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
    text-align:center;

}

.success-msg{

    background:#10351b;
    border:1px solid #2ecc71;
    color:#87ffb2;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
    text-align:center;

}

.footer{

    margin-top:25px;
    text-align:center;
    font-size:.85rem;

}

.footer a{

    color:var(--blue);
    text-decoration:none;

}

</style>

</head>

<body>

<div class="card">

<div class="logo">

<div class="logo-text">
PAF<span>WEB</span>
</div>

<div class="logo-sub">
Recuperación
</div>

</div>

<h2>Recuperar contraseña</h2>

<p class="descripcion">

Ingresa el correo asociado a tu cuenta.
Si existe, recibirás un enlace para cambiar tu contraseña.

</p>

<div id="mensaje"></div>

<form id="recuperarForm">

<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">

<div class="campo">

<label>Correo electrónico</label>

<input
type="email"
name="correo"
required
autocomplete="email">

</div>

<button type="submit">

Enviar enlace

</button>

</form>

<div class="footer">

<a href="login.php">

← Volver al inicio de sesión

</a>

</div>

</div>

<script>

document
.getElementById("recuperarForm")
.addEventListener("submit",async function(e){

e.preventDefault();

const datos=new FormData(this);

try{

const r=await fetch(

"/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=recuperar",

{

method:"POST",

body:datos,

credentials:"same-origin",

headers:{

"X-Requested-With":"XMLHttpRequest",
"Accept":"application/json"

}

});

const json=await r.json();

if(json.success){

document.getElementById("mensaje").innerHTML=
'<div class="success-msg">'+json.message+'</div>';

this.reset();

}else{

document.getElementById("mensaje").innerHTML=
'<div class="error-msg">'+json.message+'</div>';

}

}catch(e){

document.getElementById("mensaje").innerHTML=
'<div class="error-msg">Error al conectar con el servidor.</div>';

}

});

</script>

</body>
</html>