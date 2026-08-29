// login.js — flujo de login vía AJAX, con progressive enhancement:
// si algo falla aquí (JS bloqueado, error de red inesperado), el <form>
// de login.php sigue funcionando con un envío normal a login_proceso.php,
// porque nunca quitamos el action/method del formulario.

document.getElementById('formLogin')?.addEventListener('submit', iniciarSesion);

async function iniciarSesion(evento) {
    // Evita el envío normal del formulario (recarga de página) SOLO si
    // JS está corriendo — si este script no llegó a cargar, el navegador
    // nunca ejecuta este código y el <form> se envía normal igual.
    evento.preventDefault();

    const btn = document.getElementById('btnLogin');
    const errorCliente = document.getElementById('errorCliente');
    const errorServidor = document.getElementById('errorServidor');
    const campoUsuario = document.getElementById('nombre_usuario');
    const campoContrasena = document.getElementById('contrasena');

    errorServidor?.remove(); // limpia el mensaje del fallback sin-JS si estaba visible
    errorCliente.style.display = 'none';
    errorCliente.textContent = '';

    const datos = {
        nombre_usuario: campoUsuario.value.trim(),
        contrasena: campoContrasena.value,
        csrf_token: document.getElementById('csrf_token').value
    };

    if (!datos.nombre_usuario || !datos.contrasena) {
        mostrarError('Completa todos los campos');
        return;
    }

    // Deshabilita el botón mientras se procesa: evita doble-clic / doble
    // envío (que podría, por ejemplo, contar como 2 intentos fallidos en
    // vez de 1 si la contraseña estaba mal).
    btn.disabled = true;
    const textoOriginal = btn.textContent;
    btn.textContent = 'Verificando...';

    try {
        const res = await fetch('login_proceso.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        const textoCrudo = await res.text();
        let data;
        try {
            data = JSON.parse(textoCrudo);
        } catch {
            throw new Error('Respuesta inesperada del servidor.');
        }

        if (res.ok && data.status === 'success') {
            // No reactivamos el botón: la página va a navegar de todas
            // formas, y dejarlo deshabilitado evita un doble-click
            // accidental mientras el navegador redirige.
            window.location.href = 'dashboard.php';
            return;
        }

        mostrarError(data.message || 'Usuario o contraseña incorrectos');

        // Por seguridad (y para que no quede una contraseña fallida visible
        // si alguien más mira la pantalla), limpiamos el campo de
        // contraseña tras un intento fallido. El usuario no se borra, para
        // no obligar a reescribirlo cada vez.
        campoContrasena.value = '';
        campoContrasena.focus();
    } catch (error) {
        console.error(error);
        mostrarError('No se pudo conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
    } finally {
        btn.disabled = false;
        btn.textContent = textoOriginal;
    }

    function mostrarError(mensaje) {
        errorCliente.textContent = mensaje;
        errorCliente.style.display = 'block';
    }
}