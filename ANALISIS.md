# Análisis y alcance implementado de CRED Salud

La fuente es `PA1_ALAMA HERRERA PERCY FRANK.docx`. El plan propone desarrollar y evaluar un prototipo web y móvil para apoyar el seguimiento CRED realizado por enfermería en Piura. El documento establece objetivos de funcionalidad, compatibilidad y usabilidad; no incluye un catálogo completo de pantallas, permisos, campos clínicos ni reglas de diagnóstico.

## Decisiones de implementación

Se implementó una aplicación PHP con MariaDB en el XAMPP existente. El navegador consulta datos del servidor; no se usa localStorage para expedientes. HTML y CSS resuelven la presentación y JavaScript abre los diálogos, controla el menú móvil y muestra el reloj. La zona horaria es America/Lima en PHP y UTC−5 en cada conexión SQL. Las citas se interpretan como hora local de Lima.

La referencia visual aportada guía la paleta azul y blanco y la presentación institucional del acceso, inspirada en SIHCE. El producto tiene identidad CRED propia. No se presenta como una plataforma oficial ni tiene integración con SIHCE o MINSA. No se incorporó el escudo como archivo de imagen; se creó un identificador gráfico propio para CRED.

Las altas, consultas individuales, modificaciones y cambios de estado se presentan en ventanas modales. Los listados, indicadores, agenda y auditoría permanecen en las pantallas principales.

## Usuarios propuestos

| Perfil | Permisos implementados |
| --- | --- |
| Administrador | Pacientes, agenda, controles, reportes, usuarios y auditoría |
| Enfermería | Pacientes, agenda, controles y reportes; sin administración de usuarios |
| Supervisor | Consulta de pacientes, historial, agenda y reportes; sin cambios asistenciales |

Estos tres perfiles son una decisión de diseño para el prototipo, no una clasificación especificada por el plan de tesis. Los permisos se verifican en PHP incluso si se manipula una petición HTTP. Los apoderados son contactos de la ficha; no tienen acceso al sistema en esta versión.

## Flujo funcional

1. Acceso con una cuenta individual y un perfil asignado.
2. Búsqueda previa y registro del niño, fecha de nacimiento, sexo y responsable.
3. Programación de una cita, con confirmación, reprogramación, cancelación o inasistencia.
4. Registro de medidas, evaluación realizada por enfermería, hallazgos e indicaciones.
5. Cierre de la cita vinculada y creación opcional de la siguiente cita en una misma transacción.
6. Consulta de historial, citas pendientes e indicadores mensuales.

El historial conserva autor y fecha de registro. No se permite sobrescribir o eliminar controles finalizados desde la interfaz. Los pacientes y usuarios admiten desactivación reversible. Una corrección clínica versionada requeriría un flujo adicional de enmiendas, que no se implementó.

## Datos

`users` representa las cuentas; `patients`, la ficha del niño y un responsable principal; `appointments`, la agenda; `controls`, cada atención y sus medidas; `audit_log`, la trazabilidad; `login_attempts`, el control de intentos de acceso.

Se usan claves foráneas y restricciones de unicidad para DNI, correo, cita por paciente y horario, y atención vinculada a una cita. El DNI puede quedar pendiente y cada paciente mantiene un número interno de historia. Los niños sin DNI deben buscarse por nombre y responsable para evitar duplicados.

## Indicadores y evaluación de la tesis

El panel muestra pacientes activos, citas del día, controles registrados en la fecha y citas vencidas. El reporte mensual calcula cumplimiento como citas completadas / citas del periodo, excluyendo canceladas. Este indicador no equivale a cobertura poblacional CRED: para eso se requiere definir la población objetivo y su denominador.

Para evaluar eficacia, se pueden observar tareas de registrar paciente, localizar historial y completar control, contando éxitos y errores. Para eficiencia, medir el tiempo de esas mismas tareas y comparar repeticiones. Para satisfacción, aplicar el instrumento definido y validado para el estudio. La aplicación no inventa resultados de encuestas ni incorpora aún un módulo para recolectarlos.

El plan presenta una inconsistencia que conviene revisar: formula la usabilidad como factor que mejora el seguimiento, pero en la operacionalización coloca el prototipo como variable independiente y la usabilidad como dependiente. Debe precisarse el modelo de investigación y qué resultados se compararán antes de realizar la evaluación.

## Límites explícitos

Se incorporó el rango etario de la NTS 238-MINSA/DGIESP-2025: nacimiento a 11 años, 11 meses y 29 días, operacionalizado como fechas calendario anteriores al cumpleaños 12. Se valida en el servidor para el ingreso, los controles y las citas, incluida la próxima cita generada por una atención. El historial permanece disponible al superar el rango. El detalle del control muestra la edad que tenía el paciente cuando fue atendido, además de su edad actual en la ficha.

Los formularios modales muestran el grupo etario y orientación de frecuencia con base en los numerales 6.3 y 6.4 de la norma. Los intervalos no se convierten en bloqueos clínicos ni en citas automáticas: enfermería mantiene la selección de fecha. No se declara implementada la totalidad de la norma; faltan los formularios e instrumentos específicos de evaluación por edad, la identificación de cada control del esquema y la evaluación formal de completitud.

Fuente oficial: https://www.gob.pe/institucion/minsa/informes-publicaciones/7857089-norma-tecnica-de-salud-para-el-control-de-crecimiento-y-desarrollo-del-nino-nts-n-238-minsa-dgiesp-2025

La aplicación registra evaluaciones profesionales; no diagnostica ni clasifica curvas de crecimiento automáticamente. Los rangos de los campos son validaciones de captura, no criterios de normalidad.

La PWA necesita conexión al servidor para consultar y guardar datos. Incluye manifest e iconos de instalación y un mensaje de falta de conexión; no almacena expedientes offline. La instalación en dispositivos requiere un contexto seguro compatible (HTTPS o localhost). No incluye mensajes SMS/WhatsApp, integración con sistemas externos, varios establecimientos ni sincronización offline.

El sistema queda funcional para pruebas de uso y validación del flujo. El documento académico no sustituye la validación de formularios y procedimientos por el establecimiento donde se utilizará.

El módulo de vacunación fue retirado del alcance y de la aplicación a solicitud del usuario, ya que el Word no lo exige. La tabla histórica `vaccinations` se conserva en la base y en los respaldos para no borrar datos previamente registrados; no hay formularios ni acciones de escritura para ella.
