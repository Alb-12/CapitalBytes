// reportes.js — lógica del módulo de Reportes.
// Autocontenido: no depende de common.js (que solo se carga en clientes).

// Estado del reporte actualmente visible (para exportar/imprimir)
let reporteActual = { tipo: '', datos: [], columnas: [], titulo: '' };

// Config de cada pestaña: a qué 'tipo' del backend corresponde y en qué
// <div> debe pintar su tabla. 'resumen' no está aquí porque ese panel
// ya lo renderiza PHP directamente, sin AJAX.
const TABS = {
    activos:  { tipo: 'prestamos', extra: { estado: 'Activo' }, contenedor: 'tabla-activos',  titulo: 'Préstamos Activos' },
    vencidos: { tipo: 'prestamos', extra: { estado: 'Mora' },   contenedor: 'tabla-vencidos', titulo: 'Préstamos Vencidos' },
    ingresos: { tipo: 'ingresos_mensuales', extra: {},          contenedor: 'tabla-ingresos', titulo: 'Ingresos Mensuales' }
};

// Recuerda qué pestañas ya se cargaron, para no repetir la petición cada
// vez que el usuario hace clic en la misma pestaña.
const cargadas = new Set();

// ─── Cambiar de pestaña ────────────────────────────────────────────────
function cambiarTab(tab) {
    document.querySelectorAll('.panel-reporte').forEach((p) => { p.style.display = 'none'; });
    document.getElementById(`panel-${tab}`).style.display = 'block';

    document.querySelectorAll('.tab-reporte').forEach((btn) => {
        const esActiva = btn.dataset.tab === tab;
        btn.classList.toggle('btn-primary', esActiva);
        btn.classList.toggle('btn-light', !esActiva);
    });

    if (tab === 'resumen') {
        reporteActual = { tipo: '', datos: [], columnas: [], titulo: '' };
        return;
    }

    if (!cargadas.has(tab)) {
        cargarReporteTab(tab);
    }
}

// ─── Cargar los datos de una pestaña vía AJAX ──────────────────────────
async function cargarReporteTab(tab) {
    const config = TABS[tab];
    const contenedor = document.getElementById(config.contenedor);
    contenedor.innerHTML = '<p class="text-muted">Cargando...</p>';

    try {
        const res = await fetch('reportes/reporte_datos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tipo: config.tipo, ...config.extra })
        });
        if (!res.ok) throw new Error(`Error del servidor (${res.status})`);
        const data = await res.json();

        if (data.status !== 'success') {
            contenedor.innerHTML = `<p class="text-danger">${data.mensaje}</p>`;
            return;
        }

        cargadas.add(tab);
        reporteActual = { tipo: config.tipo, datos: data.datos, columnas: data.columnas, titulo: config.titulo };
        renderizarTabla(contenedor, data);
    } catch (error) {
        console.error(error);
        contenedor.innerHTML = `<p class="text-danger">Error al cargar: ${error.message}</p>`;
    }
}

// ─── Renderizar una tabla Bootstrap dentro de un contenedor ────────────
function renderizarTabla(contenedor, data) {
    if (!data.datos.length) {
        contenedor.innerHTML = '<p class="text-muted">No se encontraron registros.</p>';
        return;
    }

    const thead = data.columnas.map((c) => `<th>${c.label}</th>`).join('');
    const tbody = data.datos.map((fila) =>
        '<tr>' + data.columnas.map((c) => `<td>${fila[c.key] ?? '—'}</td>`).join('') + '</tr>'
    ).join('');

    contenedor.innerHTML = `
        <p class="text-muted small">Total registros: ${data.datos.length}</p>
        <table class="table table-hover table-sm">
            <thead><tr>${thead}</tr></thead>
            <tbody>${tbody}</tbody>
        </table>
    `;
}

// ─── Exportar PDF ─────────────────────────────────────────────────────
function exportarPDF() {
    if (!reporteActual.datos.length) { alert('No hay datos para exportar en esta pestaña'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(14);
    doc.text('PRESTA-APP — ' + reporteActual.titulo, 14, 15);
    doc.setFontSize(10);
    doc.text('Generado: ' + new Date().toLocaleString(), 14, 22);

    const columnas = reporteActual.columnas.map((c) => c.label);
    const filas = reporteActual.datos.map((fila) =>
        reporteActual.columnas.map((c) => fila[c.key] ?? '—')
    );

    doc.autoTable({
        head: [columnas],
        body: filas,
        startY: 28,
        styles: { fontSize: 8 },
        headStyles: { fillColor: [41, 128, 185] }
    });

    doc.save(reporteActual.tipo + '_' + new Date().toISOString().slice(0, 10) + '.pdf');
}

// ─── Exportar Excel ───────────────────────────────────────────────────
function exportarExcel() {
    if (!reporteActual.datos.length) { alert('No hay datos para exportar en esta pestaña'); return; }

    const encabezados = reporteActual.columnas.map((c) => c.label);
    const filas = reporteActual.datos.map((fila) =>
        reporteActual.columnas.map((c) => fila[c.key] ?? '')
    );

    const wsData = [encabezados, ...filas];
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, reporteActual.titulo.slice(0, 31));
    XLSX.writeFile(wb, reporteActual.tipo + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
}

// ─── Imprimir reporte ─────────────────────────────────────────────────
function imprimirReporte() {
    if (!reporteActual.datos.length) { alert('No hay datos para imprimir en esta pestaña'); return; }

    const config = Object.values(TABS).find((t) => t.titulo === reporteActual.titulo);
    const contenido = document.getElementById(config.contenedor).innerHTML;
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html><head>
        <style>
            body  { font-family:Arial,sans-serif; font-size:12px; margin:1.5cm; }
            table { width:100%; border-collapse:collapse; margin-top:10px; }
            th    { background:#2980b9; color:#fff; padding:6px; text-align:left; }
            td    { padding:5px; border-bottom:1px solid #ddd; }
            h3    { margin-bottom:4px; }
        </style>
        </head><body>
        <h2>PRESTA-APP — ${reporteActual.titulo}</h2>
        <p style="color:gray;">Generado: ${new Date().toLocaleString()}</p>
        ${contenido}
        </body></html>
    `);
    ventana.document.close();
    ventana.focus();
    ventana.print();
    ventana.close();
}