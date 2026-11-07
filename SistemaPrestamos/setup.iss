; ═══════════════════════════════════════════════════════════════════
; setup.iss — Script de Inno Setup para PRESTA-APP
;
; Cómo usarlo:
;   1. Descarga e instala Inno Setup (gratis): https://jrsoftware.org/isdl.php
;   2. Abre este archivo con "Inno Setup Compiler"
;   3. Clic en "Compile" (o F9)
;   4. El .exe final queda en la carpeta "Output" que se crea junto a este script
;
; Antes de compilar, ajusta las rutas de [Files] si tu carpeta no se
; llama exactamente igual, y cambia MyAppVersion en cada nueva versión
; que publiques.
; ═══════════════════════════════════════════════════════════════════

#define MyAppName "PRESTA-APP"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Tu Empresa"
#define MyAppExeName "iniciar_presta_app.bat"

[Setup]
AppId={{B3F1A2C4-9D8E-4F5A-8B3C-1A2B3C4D5E6F}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
; Instala en Archivos de Programa por defecto; el usuario puede cambiarlo
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
; No requiere ser administrador de Windows para instalar (más simple para el cliente final)
PrivilegesRequired=lowest
OutputDir=Output
OutputBaseFilename=PrestaApp-Instalador-{#MyAppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
; Ícono del instalador y de los accesos directos — reemplaza con tu logo
; SetupIconFile=icono.ico

[Languages]
Name: "spanish"; MessagesFile: "compiler:Languages\Spanish.isl"

[Files]
; Copia TODO: PHP portable, MariaDB portable, tu app, y el launcher.
; "recursesubdirs" incluye todas las subcarpetas automáticamente.
Source: "php\*"; DestDir: "{app}\php"; Flags: recursesubdirs ignoreversion
Source: "mariadb\*"; DestDir: "{app}\mariadb"; Flags: recursesubdirs ignoreversion
Source: "app\*"; DestDir: "{app}\app"; Flags: recursesubdirs ignoreversion
Source: ".desktop_package"; DestDir: "{app}\app"; Flags: ignoreversion
Source: "iniciar_presta_app.bat"; DestDir: "{app}"; Flags: ignoreversion

[Dirs]
; Carpeta vacía donde MariaDB guardará los datos de este cliente —
; se crea vacía, mariadb-install-db.exe la llena la primera vez que
; el launcher corre.
Name: "{app}\data"

[Icons]
; Accesos directos en el menú de inicio y en el escritorio
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; WorkingDir: "{app}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; WorkingDir: "{app}"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "Crear un acceso directo en el Escritorio"; GroupDescription: "Accesos directos:"

[Run]
; Ofrece abrir la app apenas termina de instalar
Filename: "{app}\{#MyAppExeName}"; Description: "Iniciar {#MyAppName} ahora"; Flags: postinstall skipifsilent nowait shellexec

[UninstallDelete]
; Al desinstalar, pregunta si borrar también los datos (la base de datos
; del cliente) — por defecto Inno Setup NO borra archivos creados después
; de instalar (como la carpeta "data"), así que esto es opcional y
; peligroso si lo activas sin avisar. Coméntalo si prefieres que los
; datos del cliente sobrevivan a una desinstalación por accidente.
; Type: filesandordirs; Name: "{app}\data"
