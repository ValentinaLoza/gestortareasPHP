# Gestor de tareas AVEX + resumen de WhatsApp con Gemini

Este paquete contiene:

- `htdocs/`: el gestor completo actualizado para subir al hosting.
- `extension-chrome/`: la extensión que obtiene los mensajes cargados del chat abierto.
- `instalacion_resumen_whatsapp.sql`: la tabla nueva para guardar los resúmenes.

La función solo está disponible para el usuario **Valentina Loza**.

## 1. Crear la API Key de Gemini

1. Abrí <https://aistudio.google.com/apikey>.
2. Iniciá sesión con tu cuenta de Google.
3. Presioná **Create API key**.
4. Creá una clave nueva. Las claves nuevas de Google AI Studio son claves de autorización para Gemini.
5. Copiá la clave una sola vez y guardala. No la envíes por WhatsApp ni la coloques dentro de la extensión.

Documentación oficial: <https://ai.google.dev/gemini-api/docs/api-key>

## 2. Configurar Gemini en el gestor

Dentro del paquete abrí:

`htdocs/config/gemini.php`

Reemplazá solamente este texto:

```php
"api_key" => "PEGAR_AQUI_LA_API_KEY_DE_GEMINI",
```

por tu clave:

```php
"api_key" => "TU_CLAVE_REAL",
```

No modifiques las comillas, la coma ni el modelo.

El modelo configurado es `gemini-3.6-flash`, compatible con texto, imágenes y PDF. El gestor solicita razonamiento medio, OCR de alta resolución para imágenes y una respuesta estructurada para evitar resúmenes incoherentes.

La integración utiliza `responseMimeType` y `responseSchema`, los campos compatibles con el endpoint `generateContent` empleado por el hosting.
El servicio admite respuestas JSON directas, encerradas en Markdown o escapadas. Si Gemini corta la primera respuesta durante el razonamiento, realiza un segundo intento automático con menor nivel de razonamiento.

## 3. Crear la tabla en phpMyAdmin

1. Ingresá al panel de InfinityFree.
2. Abrí **phpMyAdmin** para la base del gestor.
3. Seleccioná la base de datos del gestor.
4. Entrá en **Importar**.
5. Elegí `instalacion_resumen_whatsapp.sql`.
6. Presioná **Continuar**.

Al terminar debe existir la tabla `resumenes_whatsapp`.

## 4. Subir el gestor actualizado

1. Abrí el Administrador de archivos o tu programa FTP.
2. Entrá en la carpeta `htdocs` de InfinityFree.
3. Subí **el contenido** de la carpeta `htdocs/` de este paquete.
4. Permití reemplazar los archivos existentes.

No subas `extension-chrome/` al hosting. Esa carpeta se instala solamente en Google Chrome.

## 5. Instalar la extensión en Chrome

1. Extraé este ZIP en una carpeta de tu computadora que no vayas a borrar.
2. Abrí Chrome y escribí `chrome://extensions`.
3. Activá **Modo de desarrollador** arriba a la derecha.
4. Presioná **Cargar extensión sin empaquetar**.
5. Seleccioná la carpeta `extension-chrome`.
6. Fijá la extensión desde el ícono de rompecabezas.
7. Verificá que tenga acceso a:
   - `https://web.whatsapp.com/*`
   - `https://gestortareasavex.site.je/*`

La extensión usa Manifest V3 y no contiene la clave de Gemini.

El panel **Resumen AVEX** se puede arrastrar desde el encabezado para colocarlo donde no moleste. Presioná **−** para minimizarlo a un ícono 📋 y tocá ese ícono para abrirlo nuevamente. Chrome conserva la posición y el estado minimizado.

## 6. Generar el resumen de un chat

1. Iniciá sesión en el gestor desde Chrome con Valentina Loza.
2. Abrí <https://web.whatsapp.com/>.
3. Entrá al grupo o contacto que querés resumir.
4. Desplazate hacia arriba hasta cargar el primer mensaje de hoy. Dejá que WhatsApp muestre también las imágenes y documentos de esos mensajes.
5. Presioná el botón verde **Enviar chat al gestor**.
6. Se abrirá `importar_whatsapp.php`.
7. Revisá el nombre del chat, la cantidad, los mensajes y la cantidad de adjuntos detectados automáticamente.
8. No tenés que descargar ni seleccionar archivos: la extensión envía temporalmente las imágenes y PDF que WhatsApp Web tenga cargados en el chat.
9. Presioná **Generar resumen con Gemini**.
10. El gestor volverá a **Resumen diario** y agregará la sección:

```text
💬 RESUMEN DE WHATSAPP
```

Podés repetir el procedimiento con otros grupos o contactos. Si volvés a procesar el mismo chat durante el mismo día, se actualiza su resumen anterior.

Para corregir un resultado guardado, abrí **Resumen diario**, buscá el chat y presioná **Editar**. Podés modificar tanto el nombre del chat como todo el contenido del resumen. Desde esa misma pantalla también podés eliminar un resumen incorrecto.

## Qué información produce Gemini

El resumen compacto separa:

- hasta cinco conversaciones clave, cada una con sus autores reales y estado (`Pendiente`, `Resuelto` o `Informativo`);
- una sola lista de acciones pendientes, con el responsable entre paréntesis cuando esté identificado.

La información no se repite en una sección separada de personas. Los participantes aparecen directamente al lado de la conversación correspondiente para que el texto sea más corto y fácil de enviar por WhatsApp.

Gemini tiene instrucciones para no inventar responsables ni información que no aparezca en la conversación. Además, el gestor filtra la lista de personas y permite únicamente nombres detectados como autores de mensajes. Las personas citadas dentro de un PDF, una imagen o el texto de otra persona no se muestran como participantes.

Las imágenes y PDF se interpretan como evidencia silenciosa. Sus datos relevantes pueden aparecer integrados en una conversación, decisión o tarea, pero el resumen no muestra nombres de archivos ni una sección de adjuntos.

Los mensajes que contienen un **RESUMEN DEL DÍA** ya generado se excluyen antes de consultar Gemini para evitar volver a crear las mismas tareas.

## Privacidad

- La extensión solo lee el chat cuando presionás el botón.
- Antes de consultar Gemini, el gestor muestra todos los mensajes detectados para revisión.
- Los mensajes se envían a Google Gemini únicamente cuando presionás **Generar resumen con Gemini**.
- Las imágenes y PDF detectados viajan temporalmente en memoria desde la extensión hasta Gemini. No se descargan en tu computadora, no se copian a ninguna carpeta y no se guardan en la base de datos.
- La base de datos conserva únicamente el resumen textual generado por Gemini.
- No se envían ni contestan mensajes en WhatsApp.
- No se capturan llamadas ni audios.
- En el nivel gratuito de Gemini, Google puede utilizar el contenido para mejorar sus productos. No proceses información que no deba enviarse a Google.

Precios y condiciones del nivel gratuito: <https://ai.google.dev/gemini-api/docs/pricing>

## Solución de problemas

### No aparece “Enviar chat al gestor”

- Recargá WhatsApp Web con `Ctrl + Shift + R`.
- Revisá que la extensión esté activada en `chrome://extensions`.
- Entrá en **Detalles** y habilitá el acceso a WhatsApp Web.

### El gestor abre la pantalla de inicio de sesión

Iniciá sesión con Valentina Loza, volvé a WhatsApp y presioná nuevamente **Enviar chat al gestor**.

### No se detectaron mensajes

Entrá al chat y desplazate hacia arriba para que WhatsApp cargue los mensajes de hoy. La extensión solo puede leer los mensajes que WhatsApp Web ya tiene cargados en pantalla.

### Gemini no interpreta una imagen o un archivo

La extensión solo puede tomar el contenido que WhatsApp Web ya cargó en la pestaña. Desplazate por los mensajes del día y esperá a que aparezcan las miniaturas antes de presionar **Enviar chat al gestor**. Se procesan automáticamente hasta 5 archivos JPG, PNG, WEBP o PDF, con un máximo de 3 MB por archivo y 4 MB en total. Si WhatsApp muestra únicamente el ícono del documento y todavía no expuso su contenido, el importador avisará que no pudo capturarlo.

### “Primero configurá la API Key”

Revisá `htdocs/config/gemini.php` y confirmá que reemplazaste el texto de ejemplo por la clave real.

### La tabla `resumenes_whatsapp` no existe

Importá `instalacion_resumen_whatsapp.sql` desde phpMyAdmin.

### Error 429 o límite de uso

Se alcanzó temporalmente el límite gratuito de Gemini. Esperá y volvé a intentarlo.

### Error al conectarse con Gemini

Comprobá que la clave sea válida y que el modelo continúe habilitado en Google AI Studio. InfinityFree permite solicitudes salientes por HTTPS, pero aplica límites de uso.

### `MySQL server has gone away`

El importador cierra la conexión MySQL antes de consultar Gemini y abre una nueva justo antes de guardar. Si el hosting corta esa conexión, el guardado se reintenta una vez automáticamente sin volver a procesar los mensajes ni los adjuntos con Gemini.
