# CRED Salud

Sistema PHP y MariaDB para registrar pacientes, citas y controles. Diseño azul y blanco inspirado en las referencias proporcionadas; operaciones en ventanas modales y perfiles de administrador, enfermería y supervisor.

## Acceso local

Inicia Apache y MySQL en XAMPP y abre http://localhost/percy/.

La base `cred_salud` ya fue instalada en este equipo. El correo inicial es `admin@cred.local`; la contraseña generada se entregó en la conversación y no se guarda en este archivo. Desde **Mi cuenta**, cambia tu contraseña. Desde **Usuarios**, crea las cuentas de enfermería y supervisión.

Los datos quedan en MariaDB y se comparten entre sesiones conectadas al mismo servidor. Las pantallas comienzan vacías para ingresar datos propios. Fecha y hora: `America/Lima` / UTC−5.

## Rango de edad CRED

La referencia es la NTS 238-MINSA/DGIESP-2025: nacimiento a 11 años, 11 meses y 29 días. La implementación usa fechas calendario: nacimiento incluido y cumpleaños 12 excluido, sin aproximar años como 365 días. Si el aniversario corresponde al 29 de febrero de un año no bisiesto, se toma el último día de febrero.

Las nuevas altas deben estar en rango hoy. Las citas, reprogramaciones, confirmaciones y controles validan la edad en la fecha de la atención. La próxima cita incluida en un control también se valida antes de guardar: si está fuera del rango, no se guarda ninguna parte de la operación. Un control histórico se evalúa en su fecha real, aunque el paciente hoy tenga 12 años o más.

Superar el rango no elimina ni desactiva automáticamente la ficha: el historial y los datos de contacto se conservan. Las correcciones de nacimiento no pueden invalidar controles o citas no canceladas ya vinculadas.

Los modales muestran edad exacta, grupo y orientación de periodicidad del numeral 6.4. La fecha de seguimiento es manual y debe considerar el último control y la evaluación profesional. Esta orientación no certifica que se haya completado el esquema y no reemplaza formularios clínicos específicos ni tamizajes normativos.

Fuente: https://www.gob.pe/institucion/minsa/informes-publicaciones/7857089-norma-tecnica-de-salud-para-el-control-de-crecimiento-y-desarrollo-del-nino-nts-n-238-minsa-dgiesp-2025

## Uso

- Pacientes: buscar, registrar, consultar historial, editar y desactivar/reactivar.
- Agenda: programar, confirmar, reprogramar, cancelar y marcar inasistencia.
- Agenda → Por día muestra todas las citas de la fecha seleccionada. Próximas citas muestra las programadas y confirmadas desde hoy, con fecha y hora, en páginas de 20 registros. En la ficha, «Nueva cita» crea otra cita y «Ver en agenda» abre el día correspondiente resaltando la cita existente.
- Controles: registrar medidas y evaluación, cerrar cita y programar seguimiento.
- Reportes: resultados mensuales y descarga real en CSV.
- Usuarios: crear, editar perfil o contraseña, activar/desactivar; solo administrador.
- Actividad: auditoría de cambios y accesos; solo administrador.

Los controles finalizados se conservan sin sobrescritura. La interfaz no realiza diagnósticos ni utiliza datos simulados. Los responsables del niño no tienen una cuenta de acceso en esta versión.

## Archivos

- `index.php`: acceso, navegación y respuesta HTTP.
- `lib/`: datos, validaciones, permisos, formularios y pantallas.
- `styles.css` y `cred.js`: presentación responsive y comportamiento de modales.
- `schema.sql`: estructura de datos.
- `storage/config.local.php`: credenciales locales de la cuenta SQL de la aplicación; excluido de Git y bloqueado por Apache.
- `ANALISIS.md`: alcance, decisiones y relación con el plan de tesis.

## Instalación en otro XAMPP

Requiere PHP 8.2 con PDO MySQL, mbstring y MariaDB. Copia el proyecto sin `storage/config.local.php` ni respaldos. Ejecuta en PowerShell desde la carpeta del proyecto:

```powershell
& 'C:\xampp\php\php.exe' scripts/install.php
```

El instalador crea `cred_salud`, una cuenta SQL limitada a esa base y un administrador con contraseña aleatoria. Se detiene si la base o configuración ya existe. La cuenta de instalación predeterminada es root local sin contraseña de XAMPP; si difiere, configura `CRED_INSTALL_USER` y `CRED_INSTALL_PASSWORD` en el entorno. No uses el instalador como migración ni reemplaces una base existente.

Apache debe permitir `.htaccess` para que se aplique el bloqueo de `storage`, `lib`, `scripts`, SQL y documentos. El directorio debe servirse con PHP; un servidor estático no ejecuta esta aplicación.

## Pruebas y respaldo

```powershell
& 'C:\xampp\php\php.exe' scripts/test.php
& 'C:\xampp\php\php.exe' scripts/test-cred-age.php
& 'C:\xampp\php\php.exe' scripts/backup.php
```

La prueba requiere Apache local, genera cuentas y fichas temporales y elimina únicamente sus propios registros al finalizar. El respaldo produce un SQL dentro de `storage`, con estructura y datos consistentes. Para restaurar, importa el SQL en una base vacía de prueba mediante una cuenta con permisos de creación y verifica su contenido antes de cambiar la configuración de conexión. No restaures encima de una base de trabajo.

## Móvil

La interfaz se adapta a teléfonos. Incluye manifest, iconos PWA y una pantalla de falta de conexión. Necesita conexión al servidor para registrar atenciones; no guarda datos clínicos en caché ni sincroniza offline. Para instalación PWA fuera de localhost se necesita HTTPS y un navegador compatible. El acceso desde otro dispositivo también necesita una dirección de red del servidor accesible desde ese dispositivo.

## Origen

La solicitud original del README fue leer `PA1_ALAMA HERRERA PERCY FRANK.docx` para analizar el desarrollo de su prototipo. El análisis está documentado en `ANALISIS.md`.



Las credenciales de acceso no se publican en el repositorio. Cada instalación genera su propia cuenta inicial.
