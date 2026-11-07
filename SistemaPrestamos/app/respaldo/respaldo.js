// respaldo.js — módulo de Respaldo y Configuración. Autocontenido.

async function apiRequest(url, options = {}) {
    const res = await fetch(url, options);
    const textoCrudo = await res.text();

    let data = null;
    try {
        data = JSON.parse(textoCrudo);
    } catch {
        // La respuesta no era JSON válido: mostramos el texto crudo (suele
        // ser un warning/notice de PHP impreso antes del json_encode) en
        // vez de fallar en silencio con un TypeError confuso.
        throw new Error('Respuesta no válida del servidor: ' + textoCrudo.slice(0, 300));
    }

    if (!res.ok) {
        throw new Error(data?.message ?? data?.mensaje ?? `Error del servidor (${res.status})`);
    }
    return data;
}

// ─── Generar respaldo manual ───────────────────────────────────────────
async function generarRespaldoManual() {
    const btn = document.getElementById('btn-respaldo-manual');
    const resultado = document.getElementById('resultado-respaldo-manual');

    btn.disabled = true;
    btn.textContent = 'Generando... (puede tardar unos segundos)';
    resultado.innerHTML = '';

    try {
        const data = await apiRequest('respaldo/respaldo_generar.php', { method: 'POST' });

        if (data.status === 'success') {
            resultado.innerHTML = `<div class="text-success small">✔ Respaldo generado: ${data.archivo}</div>`;
            setTimeout(() => location.reload(), 1200);
        } else {
            resultado.innerHTML = `<div class="text-danger small">Error: ${data.mensaje}</div>`;
        }
    } catch (error) {
        console.error(error);
        resultado.innerHTML = `<div class="text-danger small">${error.message}</div>`;
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Generar Respaldo Ahora';
    }
}

// ─── Guardar configuración de respaldo automático ──────────────────────
async function guardarConfigRespaldo() {
    const datos = {
        backup_automatico: document.getElementById('cfg_backup_automatico').checked,
        backup_frecuencia: document.getElementById('cfg_backup_frecuencia').value,
        backup_hora: document.getElementById('cfg_backup_hora').value,
        backup_destino: document.getElementById('cfg_backup_destino').value
    };

    try {
        const data = await apiRequest('respaldo/config_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tipo: 'respaldo', ...datos })
        });
        if (data.status === 'success') {
            alert('Configuración de respaldo guardada');
        }
    } catch (error) {
        console.error(error);
        alert('Error al guardar: ' + error.message);
    }
}

// ─── Guardar configuración de empresa y WhatsApp ───────────────────────
async function guardarConfigEmpresa() {
    const datos = {
        nombre_empresa: document.getElementById('cfg_nombre_empresa').value,
        whatsapp_token: document.getElementById('cfg_whatsapp_token').value,
        whatsapp_phone_id: document.getElementById('cfg_whatsapp_phone_id').value,
        whatsapp_api_version: document.getElementById('cfg_whatsapp_version').value
    };

    try {
        const data = await apiRequest('respaldo/config_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tipo: 'empresa', ...datos })
        });
        if (data.status === 'success') {
            alert('Configuración de empresa guardada');
        }
    } catch (error) {
        console.error(error);
        alert('Error al guardar: ' + error.message);
    }
}

// ─── Desconectar Google Drive ───────────────────────────────────────────
async function desconectarGoogle() {
    if (!confirm('¿Desconectar la cuenta de Google Drive? Los respaldos automáticos a la nube dejarán de funcionar hasta que reconectes.')) return;

    try {
        const data = await apiRequest('respaldo/google_desconectar.php', { method: 'POST' });
        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('Error al desconectar: ' + error.message);
    }
}
// ─── Desconectar OneDrive ────────────────────────────────────────────────
async function desconectarOneDrive() {
    if (!confirm('¿Desconectar la cuenta de OneDrive? Los respaldos automáticos a la nube dejarán de funcionar hasta que reconectes.')) return;

    try {
        const data = await apiRequest('respaldo/onedrive_desconectar.php', { method: 'POST' });
        if (data.status === 'success') {
            location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('Error al desconectar: ' + error.message);
    }
}

// ─── Guardar configuración de mora ──────────────────────────────────────
async function guardarConfigMora() {
    const datos = {
        mora_porcentaje_default: document.getElementById('cfg_mora_porcentaje').value,
        dias_gracia: document.getElementById('cfg_dias_gracia').value
    };

    try {
        const data = await apiRequest('respaldo/config_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tipo: 'mora', ...datos })
        });
        if (data.status === 'success') {
            alert('Configuración de mora guardada');
        }
    } catch (error) {
        console.error(error);
        alert('Error al guardar: ' + error.message);
    }
}