"use strict";

document.addEventListener("DOMContentLoaded", () => {

    const form = document.getElementById("loginForm");

    if (!form) return;

    const boton = form.querySelector(".btn-submit");
    const mensaje = document.getElementById("mensaje");

    form.addEventListener("submit", async (e) => {

        e.preventDefault();

        boton.disabled = true;
        boton.textContent = "Verificando...";

        mensaje.innerHTML = "";

        try {

            const datos = new FormData(form);

            const respuesta = await fetch(
                "/spectre_erp/src/modules/usuarios/controllers/UserController.php?accion=procesar",
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

            if (!respuesta.ok) {
                throw new Error("Error HTTP " + respuesta.status);
            }

            const json = await respuesta.json();

            if (json.success) {

                mensaje.innerHTML = `
                    <div class="success-msg">
                        Inicio de sesión correcto.
                    </div>
                `;

                setTimeout(() => {
                    window.location.href = json.redirect || "/spectre_erp/index.php";
                }, 700);

            } else {

                mensaje.innerHTML = `
                    <div class="error-msg">
                        ${escapeHtml(json.message || "Credenciales incorrectas.")}
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
            boton.textContent = "Ingresar";

        }

    });

});

/**
 * Evita XSS al mostrar mensajes del servidor.
 */
function escapeHtml(text) {

    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;

}