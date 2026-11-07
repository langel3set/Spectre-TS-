<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/csrf.php';

$pdo = getDB();

$token = trim($_GET['token'] ?? '');


if ($token === '') {

    die('Token inválido.');

}


/*
|--------------------------------------------------------------------------
| Validar token en base de datos
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM password_resets
    WHERE token = ?
    LIMIT 1
");

$stmt->execute([$token]);

$reset = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$reset) {

    die('Token no encontrado.');

}



/*
|--------------------------------------------------------------------------
| Validar expiración
|--------------------------------------------------------------------------
*/

if (isset($reset['expires_at'])) {


    if (strtotime($reset['expires_at']) < time()) {


        die('El enlace de recuperación ha expirado.');


    }

}



$csrf = csrf_token();


?>


<!DOCTYPE html>
<html lang="es">


<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Restablecer contraseña</title>


<link 
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


</head>



<body class="bg-light">



<div class="container">


<div class="row justify-content-center mt-5">


<div class="col-md-5">



<div class="card shadow">



<div class="card-header text-center">

<h4>
Restablecer contraseña
</h4>

</div>




<div class="card-body">



<div id="mensaje" class="alert d-none"></div>




<form id="formReset">



<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">



<input
type="hidden"
name="token"
value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">





<div class="mb-3">


<label class="form-label">

Nueva contraseña

</label>


<input

type="password"

class="form-control"

name="contrasena"

required

minlength="6"

>


</div>






<div class="mb-3">


<label class="form-label">

Confirmar contraseña

</label>


<input

type="password"

class="form-control"

name="confirmar_contrasena"

required

minlength="6"

>


</div>






<button

type="submit"

class="btn btn-primary w-100"

>

Guardar contraseña

</button>




</form>



</div>


</div>



</div>


</div>


</div>






<script>

document
.getElementById("formReset")
.addEventListener("submit", async function(e){

    e.preventDefault();

    const datos = new FormData(this);

    const mensaje = document.getElementById("mensaje");

    try {

        const respuesta = await fetch(
            "/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=reset",
            {
                method:"POST",
                body:datos
            }
        );


        const texto = await respuesta.text();


        console.log("Respuesta servidor:", texto);


        let json;


        try {

            json = JSON.parse(texto);

        } catch(error) {

            mensaje.className="alert alert-danger";
            mensaje.classList.remove("d-none");

            mensaje.innerHTML =
            "Error PHP:<br><pre>"+texto+"</pre>";

            return;

        }


        mensaje.className="alert";
        mensaje.classList.remove("d-none");


        if(json.success){

            mensaje.classList.add("alert-success");


            setTimeout(()=>{

                window.location.href="/spectre_erp";

            },2000);


        }else{

            mensaje.classList.add("alert-danger");

        }


        mensaje.innerHTML=json.message;


    }
    catch(error){


        mensaje.className="alert alert-danger";
        mensaje.classList.remove("d-none");

        mensaje.innerHTML=error.message;


    }


});

</script>



</body>

</html>