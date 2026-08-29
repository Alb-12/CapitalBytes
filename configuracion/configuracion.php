
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="db/style.css">
    <title>Document</title>
</head>
<body>
   <div class="modulo-config">
    <h2>Configuración del Sistema</h2>

    <form id="formConfig">
        <fieldset>
            <legend>Parámetros de Mora (CF-10)</legend>
            <label>Tasa de Mora (%):</label>
            <input type="number" id="tasa_mora" step="0.01" value="5.00">
            
            <label>Días de Gracia (Aplicación):</label>
            <input type="number" id="dias_mora" value="10">
        </fieldset>

        <fieldset style="margin-top: 20px;">
            <legend>Mantenimiento y Respaldo (CF-15)</legend>
            <label>
                <input type="checkbox" id="backup_auto" checked> 
                Activar respaldo automático cada 24 horas
            </label>
            <br><br>
            <button type="button" onclick="ejecutarBackup()" style="background: #34495e; color: white;">
                Realizar Respaldo Manual Ahora
            </button>
        </fieldset>

        <div style="margin-top: 20px;">
            <button type="button" onclick="actualizarConfiguracion()" style="padding: 10px 20px; background: #27ae60; color: white; border: none;">
                Guardar Cambios de Configuración
            </button>
        </div>
    </form>
</div> 
</body>
</html>