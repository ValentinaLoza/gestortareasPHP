<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set("America/Argentina/Buenos_Aires");

require_once "config/database.php";
require_once "services/GeminiResumenService.php";

$sqlUsuarioActual = "SELECT id, nombre FROM usuarios WHERE id = :id LIMIT 1";
$stmtUsuarioActual = $pdo->prepare($sqlUsuarioActual);
$stmtUsuarioActual->execute([":id" => $_SESSION["usuario_id"]]);
$usuarioActual = $stmtUsuarioActual->fetch(PDO::FETCH_ASSOC);

if (!$usuarioActual || strcasecmp(trim($usuarioActual["nombre"]), "Valentina Loza") !== 0) {
    header("Location: dashboard.php");
    exit;
}

// La consulta a Gemini puede tardar lo suficiente para que InfinityFree cierre
// una conexión MySQL inactiva. Ya validado el usuario, liberamos esta conexión
// y creamos una nueva únicamente al momento de guardar el resumen.
$stmtUsuarioActual = null;
$pdo = null;

if (!isset($_SESSION["csrf_whatsapp"])) {
    $_SESSION["csrf_whatsapp"] = bin2hex(random_bytes(32));
}

$configGemini = require "config/gemini.php";
$gemini = new GeminiResumenService(
    isset($configGemini["api_key"]) ? $configGemini["api_key"] : "",
    isset($configGemini["model"]) ? $configGemini["model"] : "gemini-3.6-flash"
);

$tituloPagina = "Importar WhatsApp";
$subtituloPagina = "Revisá los mensajes antes de enviarlos a Gemini.";
$error = "";

function cargarAdjuntosTemporales($jsonAdjuntos)
{
    $jsonAdjuntos = trim((string)$jsonAdjuntos);
    if ($jsonAdjuntos === "" || $jsonAdjuntos === "[]") {
        return [];
    }

    if (strlen($jsonAdjuntos) > 6 * 1024 * 1024) {
        throw new RuntimeException("Los adjuntos detectados superan el límite permitido para el resumen.");
    }

    $datos = json_decode($jsonAdjuntos, true);
    if (!is_array($datos)) {
        throw new RuntimeException("No fue posible interpretar los adjuntos enviados por la extensión.");
    }

    $permitidos = [
        "image/jpeg" => true,
        "image/png" => true,
        "image/webp" => true,
        "application/pdf" => true,
    ];
    $adjuntos = [];
    $totalBytes = 0;

    foreach ($datos as $dato) {
        if (count($adjuntos) >= 5) {
            break;
        }

        if (!is_array($dato)) {
            continue;
        }

        $nombre = basename(isset($dato["nombre"]) ? (string)$dato["nombre"] : "adjunto-whatsapp");
        $mime = strtolower(trim(isset($dato["mime_type"]) ? (string)$dato["mime_type"] : ""));
        $base64 = preg_replace('/\s+/', '', isset($dato["base64"]) ? (string)$dato["base64"] : "");
        $autor = trim(isset($dato["autor"]) ? (string)$dato["autor"] : "");

        if (!isset($permitidos[$mime]) || $base64 === "") {
            continue;
        }

        $contenido = base64_decode($base64, true);
        if ($contenido === false) {
            throw new RuntimeException("Uno de los adjuntos recibidos no es válido.");
        }

        $tamanio = strlen($contenido);
        if ($tamanio <= 0 || $tamanio > 3 * 1024 * 1024) {
            throw new RuntimeException("Uno de los adjuntos supera el límite de 3 MB.");
        }

        $totalBytes += $tamanio;
        if ($totalBytes > 4 * 1024 * 1024) {
            throw new RuntimeException("El total de adjuntos supera el límite de 4 MB.");
        }

        unset($contenido);

        $adjuntos[] = [
            "nombre" => substr($nombre, 0, 180),
            "mime_type" => $mime,
            "base64" => $base64,
            "autor" => substr($autor, 0, 120),
        ];
    }

    return $adjuntos;
}

function crearConexionBaseWhatsApp()
{
    global $host, $dbname, $username, $password;

    $conexion = new PDO(
        "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 12,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $conexion;
}

function guardarResumenWhatsApp($sql, $parametros)
{
    $ultimoError = null;

    for ($intento = 1; $intento <= 2; $intento++) {
        try {
            $conexion = crearConexionBaseWhatsApp();
            $stmt = $conexion->prepare($sql);
            $stmt->execute($parametros);
            $stmt = null;
            $conexion = null;
            return;
        } catch (PDOException $e) {
            $ultimoError = $e;
            $codigoMySql = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;

            if ($intento === 1 && ($codigoMySql === 2006 || $codigoMySql === 2013)) {
                continue;
            }

            throw $e;
        }
    }

    if ($ultimoError !== null) {
        throw $ultimoError;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = isset($_POST["csrf_token"]) ? (string)$_POST["csrf_token"] : "";

    if (!hash_equals($_SESSION["csrf_whatsapp"], $csrf)) {
        $error = "La sesión venció. Recargá la página y volvé a intentarlo.";
    } else {
        $chatNombre = trim(isset($_POST["chat_nombre"]) ? (string)$_POST["chat_nombre"] : "");
        $mensajes = trim(isset($_POST["mensajes"]) ? (string)$_POST["mensajes"] : "");
        $cantidadMensajes = isset($_POST["cantidad_mensajes"]) ? (int)$_POST["cantidad_mensajes"] : 0;

        if ($chatNombre === "" || $mensajes === "") {
            $error = "No se recibieron mensajes. Volvé a WhatsApp Web y usá el botón Enviar al gestor.";
        } elseif (strlen($chatNombre) > 190) {
            $error = "El nombre del chat es demasiado largo.";
        } elseif (strlen($mensajes) > 180000) {
            $error = "La conversación supera el límite de 180.000 caracteres. Cargá menos mensajes y volvé a intentarlo.";
        } elseif (!$gemini->estaConfigurado()) {
            $error = "Primero configurá la API Key en config/gemini.php.";
        } else {
            try {
                $fechaHoy = date("Y-m-d");
                $fechaVisible = date("d/m");
                $adjuntos = cargarAdjuntosTemporales(isset($_POST["adjuntos_json"]) ? $_POST["adjuntos_json"] : "");
                $resumen = $gemini->resumirConversacion($chatNombre, $mensajes, $fechaVisible, $adjuntos);
                unset($adjuntos);

                $sqlGuardar = "INSERT INTO resumenes_whatsapp (
                                    usuario_id,
                                    chat_nombre,
                                    fecha_resumen,
                                    resumen,
                                    cantidad_mensajes
                                ) VALUES (
                                    :usuario_id,
                                    :chat_nombre,
                                    :fecha_resumen,
                                    :resumen,
                                    :cantidad_mensajes
                                )
                                ON DUPLICATE KEY UPDATE
                                    resumen = VALUES(resumen),
                                    cantidad_mensajes = VALUES(cantidad_mensajes),
                                    actualizado_en = NOW()";

                guardarResumenWhatsApp($sqlGuardar, [
                    ":usuario_id" => (int)$usuarioActual["id"],
                    ":chat_nombre" => $chatNombre,
                    ":fecha_resumen" => $fechaHoy,
                    ":resumen" => $resumen,
                    ":cantidad_mensajes" => max(0, $cantidadMensajes),
                ]);

                header("Location: resumen_diario.php?whatsapp=guardado");
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>

    <style>
        .whatsapp-import-card {
            max-width: 980px;
        }

        .import-status {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        .import-status.ready {
            background: #ecfdf5;
            color: #047857;
        }

        .import-error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fef2f2;
            color: #b91c1c;
            font-weight: 600;
        }

        .import-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .import-field label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-weight: 700;
        }

        .import-field input,
        .import-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font: inherit;
            background: #ffffff;
        }

        .import-field textarea {
            min-height: 360px;
            resize: vertical;
            line-height: 1.45;
        }

        .attachment-box {
            margin-top: 16px;
            padding: 16px;
            border: 1px dashed #94a3b8;
            border-radius: 12px;
            background: #f8fafc;
        }

        .attachment-help,
        .attachment-status {
            margin: 8px 0 0;
            color: #64748b;
            line-height: 1.45;
        }

        .attachment-status {
            color: #334155;
            font-weight: 600;
        }

        .import-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .import-submit {
            border: 0;
            border-radius: 10px;
            padding: 12px 18px;
            background: #16a34a;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        .import-submit:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .import-note {
            margin-top: 18px;
            color: #64748b;
            line-height: 1.5;
        }

        @media (max-width: 760px) {
            .import-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <?php require_once "views/partials/sidebar.php"; ?>

    <main class="app-main">
        <?php require_once "views/partials/header.php"; ?>

        <section class="content-card whatsapp-import-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Importar conversación de WhatsApp</h2>
                    <p>La extensión cargará aquí los mensajes del chat abierto.</p>
                </div>

                <a href="resumen_diario.php" class="btn-secondary-clean">Cancelar</a>
            </div>

            <?php if ($error !== ""): ?>
                <div class="import-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div id="extensionStatus" class="import-status" aria-live="polite">
                Esperando los mensajes enviados por la extensión...
            </div>

            <form method="POST" id="whatsappImportForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_whatsapp"]); ?>">
                <input type="hidden" id="adjuntos_json" name="adjuntos_json" value="">

                <div class="import-grid">
                    <div class="import-field">
                        <label for="chat_nombre">Grupo o contacto</label>
                        <input
                            type="text"
                            id="chat_nombre"
                            name="chat_nombre"
                            maxlength="190"
                            value="<?php echo isset($_POST["chat_nombre"]) ? htmlspecialchars($_POST["chat_nombre"]) : ""; ?>"
                            required
                        >
                    </div>

                    <div class="import-field">
                        <label for="cantidad_mensajes">Mensajes detectados</label>
                        <input
                            type="number"
                            id="cantidad_mensajes"
                            name="cantidad_mensajes"
                            min="0"
                            value="<?php echo isset($_POST["cantidad_mensajes"]) ? (int)$_POST["cantidad_mensajes"] : 0; ?>"
                            readonly
                        >
                    </div>
                </div>

                <div class="import-field">
                    <label for="mensajes">Mensajes que serán procesados</label>
                    <textarea id="mensajes" name="mensajes" maxlength="180000" required><?php echo isset($_POST["mensajes"]) ? htmlspecialchars($_POST["mensajes"]) : ""; ?></textarea>
                </div>

                <div class="import-field attachment-box">
                    <label>Imágenes y PDF detectados en WhatsApp</label>
                    <p class="attachment-help">
                        La extensión los toma automáticamente de los mensajes cargados. Se envían a Gemini solo para
                        generar este resumen y no se guardan en la base de datos ni en una carpeta del gestor.
                    </p>
                    <p id="attachmentStatus" class="attachment-status" aria-live="polite">
                        Esperando la detección automática de la extensión...
                    </p>
                </div>

                <div class="import-actions">
                    <button type="submit" id="processButton" class="import-submit" disabled>
                        Generar resumen con Gemini
                    </button>

                    <span>El resumen de este chat reemplazará al anterior si ya existe uno de hoy.</span>
                </div>
            </form>

            <p class="import-note">
                Revisá el contenido antes de continuar. Solo se procesa cuando presionás el botón y no se envía ningún mensaje a WhatsApp.
                El gestor conserva únicamente el resumen escrito por Gemini; no conserva los mensajes ni los adjuntos detectados.
            </p>
        </section>
    </main>
</div>

<script>
    (function () {
        const mensajes = document.getElementById("mensajes");
        const chat = document.getElementById("chat_nombre");
        const boton = document.getElementById("processButton");
        const estado = document.getElementById("extensionStatus");

        function actualizarEstado() {
            const listo = mensajes.value.trim() !== "" && chat.value.trim() !== "";
            boton.disabled = !listo;

            if (listo) {
                estado.textContent = "Mensajes recibidos. Revisalos y presioná Generar resumen con Gemini.";
                estado.classList.add("ready");
            }
        }

        mensajes.addEventListener("input", actualizarEstado);
        chat.addEventListener("input", actualizarEstado);
        actualizarEstado();
    }());
</script>

</body>
</html>
