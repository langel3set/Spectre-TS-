<?php
// Rutas relativas desde src/modules/soporte/views/ hacia la raíz
require_once __DIR__ . '/../../../../autoload.php';
require_once __DIR__ . '/../../../../src/config/csrf.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Iniciar sesión para CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensaje = '';
$error   = '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        // Recoger y limpiar datos
        $nombre   = trim($_POST['nombre'] ?? '');
        $correo   = trim($_POST['correo'] ?? '');
        $asunto   = trim($_POST['asunto'] ?? '');
        $mensaje_texto = trim($_POST['mensaje'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        // Validaciones básicas
        if (empty($nombre) || empty($correo) || empty($asunto) || empty($mensaje_texto)) {
            $error = 'Todos los campos marcados con * son obligatorios.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } else {
            // --- Envío de correo usando PHPMailer ---
            try {
                $mail = new PHPMailer(true);

                // Configuración SMTP desde $_ENV (cargado por autoload.php)
                $mail->isSMTP();
                $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USERNAME'] ?? 'lasolutions354@gmail.com';
                $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? 'vlidwidvitwflzuh';
                $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $_ENV['MAIL_PORT'] ?? 587;
                $mail->CharSet    = 'UTF-8';
                $mail->isHTML(true);

                // Remitente (el que envía el correo, normalmente tu correo del sistema)
                $mail->setFrom(
                    $_ENV['MAIL_FROM'] ?? 'lasolutions354@gmail.com',
                    $_ENV['MAIL_FROM_NAME'] ?? 'SPECTRE Soporte'
                );

                // Destinatario: el correo fijo al que quieres recibir los mensajes
                $mail->addAddress('lasolutions354@gmail.com', 'Soporte LASolutions');

                // Responder al correo del usuario (para que al responder vaya a él)
                $mail->addReplyTo($correo, $nombre);

                // Asunto y cuerpo
                $mail->Subject = "📩 Mensaje de soporte: $asunto";
                $cuerpo = "
                    <h2>Nuevo mensaje de soporte</h2>
                    <p><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</p>
                    <p><strong>Correo:</strong> <a href='mailto:$correo'>$correo</a></p>
                    " . (!empty($telefono) ? "<p><strong>Teléfono:</strong> " . htmlspecialchars($telefono) . "</p>" : "") . "
                    <p><strong>Asunto:</strong> " . htmlspecialchars($asunto) . "</p>
                    <hr>
                    <p><strong>Mensaje:</strong></p>
                    <p style='white-space:pre-wrap;'>" . nl2br(htmlspecialchars($mensaje_texto)) . "</p>
                    <hr>
                    <p style='font-size:0.8rem; color:#666;'>Este mensaje fue enviado desde el formulario de soporte de SPECTRE.</p>
                ";
                $mail->Body    = $cuerpo;
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $cuerpo));

                // Enviar
                $mail->send();
                $mensaje = '✅ ¡Tu mensaje fue enviado correctamente! Nos pondremos en contacto a la brevedad.';

                // Limpiar campos después del envío exitoso
                $_POST = [];

            } catch (Exception $e) {
                $error = '❌ Error al enviar el mensaje: ' . $mail->ErrorInfo;
                error_log('Error en mail_soporte: ' . $mail->ErrorInfo);
            }
        }
    }
}

// Generar token CSRF
$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte — SPECTRE</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #08090d;
            color: #e8eaf0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: #0e1018;
            border: 1px solid rgba(99,102,241,.2);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.3rem;
            background: linear-gradient(135deg, #4f6ef7, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .sub {
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(99,102,241,.15);
            padding-bottom: 1rem;
        }
        .campo {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin-bottom: 0.3rem;
        }
        label .req {
            color: #ef4444;
            margin-left: 0.2rem;
        }
        input, textarea {
            width: 100%;
            background: #141620;
            border: 1px solid rgba(99,102,241,.2);
            border-radius: 8px;
            color: #e8eaf0;
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #4f6ef7;
        }
        textarea {
            min-height: 140px;
            resize: vertical;
        }
        .mensaje {
            padding: 0.7rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .mensaje.ok {
            background: rgba(16,185,129,.12);
            color: #34d399;
            border: 1px solid rgba(16,185,129,.25);
        }
        .mensaje.err {
            background: rgba(239,68,68,.12);
            color: #f87171;
            border: 1px solid rgba(239,68,68,.25);
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #4f6ef7, #7c3aed);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .volver {
            display: inline-block;
            margin-top: 1rem;
            color: #6b7280;
            font-size: 0.8rem;
            text-decoration: none;
        }
        .volver:hover { color: #e8eaf0; }
        small {
            color: #6b7280;
            font-size: 0.7rem;
        }
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="container">
    <h1>📨 Soporte SPECTRE</h1>
    <p class="sub">Envía un correo directamente a nuestro equipo de soporte.</p>

    <?php if ($mensaje): ?>
        <div class="mensaje ok"><?= $mensaje ?></div>
    <?php elseif ($error): ?>
        <div class="mensaje err"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="campo">
            <label>Tu nombre <span class="req">*</span></label>
            <input type="text" name="nombre" required
                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                   placeholder="Ej: Carlos Pérez">
        </div>

        <div class="campo">
            <label>Tu correo electrónico <span class="req">*</span></label>
            <input type="email" name="correo" required
                   value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                   placeholder="tucorreo@ejemplo.com">
        </div>

        <div class="campo">
            <label>Teléfono (opcional)</label>
            <input type="tel" name="telefono"
                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                   placeholder="Ej: +51 987 654 321">
        </div>

        <div class="campo">
            <label>Asunto <span class="req">*</span></label>
            <input type="text" name="asunto" required
                   value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>"
                   placeholder="Breve resumen del problema">
        </div>

        <div class="campo">
            <label>Mensaje <span class="req">*</span></label>
            <textarea name="mensaje" required placeholder="Describe tu consulta con detalle..."><?= htmlspecialchars($_POST['mensaje'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn" id="btnEnviar">📤 Enviar mensaje</button>
    </form>

    <a href="/spectre_erp/index.php" class="volver">← Volver a la tienda</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const btn = document.getElementById('btnEnviar');

    form.addEventListener('submit', function() {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Enviando...';
    });
});
</script>

</body>
</html>