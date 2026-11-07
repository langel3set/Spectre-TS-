<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailService
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->configurar();
    }

    private function configurar(): void
    {
        $this->mail->isSMTP();
        $this->mail->Host = $_ENV['MAIL_HOST'];
        $this->mail->Port = (int) $_ENV['MAIL_PORT'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $_ENV['MAIL_USERNAME'];
        $this->mail->Password = $_ENV['MAIL_PASSWORD'];
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->CharSet = 'UTF-8';
        $this->mail->isHTML(true);

        // Depuración (comentar en producción)
        // $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
        // $this->mail->Debugoutput = 'error_log';

        $this->mail->setFrom(
            $_ENV['MAIL_FROM'],
            $_ENV['MAIL_FROM_NAME']
        );
    }

    /**
     * Envía un correo HTML.
     */
    public function enviar(
        string $destinatario,
        string $nombre,
        string $asunto,
        string $html
    ): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($destinatario, $nombre);
            $this->mail->Subject = $asunto;
            $this->mail->Body = $html;
            $this->mail->AltBody = strip_tags($html);
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function enviarRecuperacion(
        string $correo,
        string $nombre,
        string $token
    ): bool {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/spectre_erp', '/');
        $url = $appUrl . '/src/modules/usuarios/views/restablecer.php?token=' . urlencode($token);

        $html = '
        <div style="font-family:Segoe UI,Arial,sans-serif">
            <h2>Recuperación de contraseña</h2>
            <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>.</p>
            <p>Haz clic en el siguiente botón para crear una nueva contraseña.</p>
            <p>
                <a href="' . $url . '"
                   style="background:#0d6efd; color:#fff; padding:12px 20px; text-decoration:none; border-radius:5px; display:inline-block;">
                   Restablecer contraseña
                </a>
            </p>
            <p>Este enlace expirará en 30 minutos.</p>
            <hr>
            <small>Si no solicitaste este cambio simplemente ignora este correo.</small>
        </div>';

        return $this->enviar($correo, $nombre, 'Restablecimiento de contraseña', $html);
    }

    public function enviarBienvenida(
        string $correo,
        string $nombre
    ): bool {
        $html = '
        <div style="font-family:Segoe UI,sans-serif">
            <h2>Bienvenido a SPECTRE</h2>
            <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong></p>
            <p>Tu cuenta fue creada correctamente.</p>
        </div>';
        return $this->enviar($correo, $nombre, 'Bienvenido a SPECTRE', $html);
    }

    /**
     * Envía confirmación de creación de ticket al cliente.
     */
    public function enviarConfirmacionTicket(
        string $correo,
        string $nombre,
        int $idTicket,
        string $titulo
    ): bool {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/spectre_erp', '/');
        $url = $appUrl . '/src/modules/soporte/controllers/TicketController.php?accion=detalle&id=' . $idTicket;

        $html = '
        <div style="font-family:Segoe UI,sans-serif">
            <h2>Ticket creado correctamente</h2>
            <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p>Hemos recibido tu ticket de soporte con el asunto: <strong>' . htmlspecialchars($titulo) . '</strong></p>
            <p>Número de ticket: <strong>#' . $idTicket . '</strong></p>
            <p>Puedes hacer seguimiento en el siguiente enlace:</p>
            <p>
                <a href="' . $url . '"
                   style="background:#4f6ef7; color:#fff; padding:12px 20px; text-decoration:none; border-radius:5px; display:inline-block;">
                   Ver mi ticket
                </a>
            </p>
            <p>Te responderemos a la brevedad.</p>
            <hr>
            <small>Este es un correo automático, por favor no responder.</small>
        </div>';

        return $this->enviar($correo, $nombre, 'Ticket de soporte #' . $idTicket, $html);
    }

    /**
     * Envía notificación de respuesta a un ticket (cuando un trabajador responde).
     */
    public function enviarRespuestaTicket(
        string $correo,
        string $nombre,
        int $idTicket,
        string $comentario
    ): bool {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/spectre_erp', '/');
        $url = $appUrl . '/src/modules/soporte/controllers/TicketController.php?accion=detalle&id=' . $idTicket;

        $html = '
        <div style="font-family:Segoe UI,sans-serif">
            <h2>Nueva respuesta en tu ticket</h2>
            <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p>Se ha añadido una nueva respuesta a tu ticket #' . $idTicket . ':</p>
            <div style="background:#f4f4f4; padding:15px; border-radius:5px; margin:10px 0;">
                ' . nl2br(htmlspecialchars($comentario)) . '
            </div>
            <p>
                <a href="' . $url . '"
                   style="background:#4f6ef7; color:#fff; padding:12px 20px; text-decoration:none; border-radius:5px; display:inline-block;">
                   Ver respuesta
                </a>
            </p>
            <hr>
            <small>Este es un correo automático, por favor no responder.</small>
        </div>';

        return $this->enviar($correo, $nombre, 'Nueva respuesta en ticket #' . $idTicket, $html);
    }
}