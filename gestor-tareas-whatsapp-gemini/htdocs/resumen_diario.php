<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set("America/Argentina/Buenos_Aires");

require_once "config/database.php";

$sqlUsuarioActual = "SELECT nombre FROM usuarios WHERE id = :id LIMIT 1";
$stmtUsuarioActual = $pdo->prepare($sqlUsuarioActual);
$stmtUsuarioActual->execute([":id" => $_SESSION["usuario_id"]]);
$usuarioActual = $stmtUsuarioActual->fetch(PDO::FETCH_ASSOC);

if (!$usuarioActual || strcasecmp(trim($usuarioActual["nombre"]), "Valentina Loza") !== 0) {
    header("Location: dashboard.php");
    exit;
}

$tituloPagina = "Resumen diario";
$subtituloPagina = "Tareas pendientes y finalizadas durante el día.";
$fechaHoy = date("Y-m-d");
$fechaVisible = date("d/m");
$resumenesWhatsApp = [];
$whatsappDisponible = true;

try {
    $sqlWhatsApp = "SELECT
                        id,
                        chat_nombre,
                        resumen,
                        cantidad_mensajes,
                        actualizado_en
                    FROM resumenes_whatsapp
                    WHERE usuario_id = :usuario_id
                      AND fecha_resumen = :fecha_resumen
                    ORDER BY actualizado_en ASC";

    $stmtWhatsApp = $pdo->prepare($sqlWhatsApp);
    $stmtWhatsApp->execute([
        ":usuario_id" => (int)$_SESSION["usuario_id"],
        ":fecha_resumen" => $fechaHoy,
    ]);
    $resumenesWhatsApp = $stmtWhatsApp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // El resumen de tareas sigue funcionando aunque todavía no se haya importado el SQL de instalación.
    $whatsappDisponible = false;
}

function agruparPorProyecto($tareas)
{
    $grupos = [];

    foreach ($tareas as $tarea) {
        $proyecto = $tarea["proyecto"];

        if (!isset($grupos[$proyecto])) {
            $grupos[$proyecto] = [];
        }

        $grupos[$proyecto][] = $tarea;
    }

    return $grupos;
}

function formatearVencimiento($fecha, $hora)
{
    if (!$fecha) {
        return "";
    }

    $texto = " — vence " . date("d/m", strtotime($fecha));

    if ($hora) {
        $texto .= " " . substr($hora, 0, 5);
    }

    return $texto;
}

function obtenerApocopeResponsable($responsable)
{
    $nombre = trim((string)$responsable);

    if ($nombre === "") {
        return "Sin asignar";
    }

    $nombreComparacion = function_exists("mb_strtolower")
        ? mb_strtolower($nombre, "UTF-8")
        : strtolower($nombre);

    if (strpos($nombreComparacion, "cristian") === 0) {
        $nombre = "CAB";
    } elseif (strpos($nombreComparacion, "valentina") === 0) {
        $nombre = "VML";
    } elseif (strpos($nombreComparacion, "victoriano") === 0) {
        $nombre = "VIC";
    }

    return $nombre;
}

function formatearResponsable($responsable)
{
    return " (" . obtenerApocopeResponsable($responsable) . ")";
}

$sqlPendientes = "SELECT
                        t.id,
                        t.titulo,
                        t.fecha_vencimiento,
                        t.hora_fin,
                        t.progreso,
                        p.nombre AS proyecto,
                        e.nombre AS estado,
                        u.nombre AS asignado
                    FROM tareas t
                    INNER JOIN proyectos p ON t.proyecto_id = p.id
                    INNER JOIN estados_tarea e ON t.estado_id = e.id
                    LEFT JOIN usuarios u ON t.asignado_a = u.id
                    WHERE LOWER(e.nombre) NOT IN ('finalizada', 'cancelada')
                    ORDER BY p.nombre ASC,
                             COALESCE(t.fecha_vencimiento, '9999-12-31') ASC,
                             t.titulo ASC";

$pendientes = $pdo->query($sqlPendientes)->fetchAll(PDO::FETCH_ASSOC);

$sqlFinalizadas = "SELECT
                        t.id,
                        t.titulo,
                        t.finalizado_en,
                        p.nombre AS proyecto,
                        u.nombre AS asignado
                    FROM tareas t
                    INNER JOIN proyectos p ON t.proyecto_id = p.id
                    INNER JOIN estados_tarea e ON t.estado_id = e.id
                    LEFT JOIN usuarios u ON t.asignado_a = u.id
                    WHERE LOWER(e.nombre) = 'finalizada'
                      AND DATE(t.finalizado_en) = :fecha_hoy
                    ORDER BY p.nombre ASC, t.finalizado_en ASC";

$stmtFinalizadas = $pdo->prepare($sqlFinalizadas);
$stmtFinalizadas->execute([":fecha_hoy" => $fechaHoy]);
$finalizadasHoy = $stmtFinalizadas->fetchAll(PDO::FETCH_ASSOC);

$pendientesPorProyecto = agruparPorProyecto($pendientes);
$finalizadasPorProyecto = agruparPorProyecto($finalizadasHoy);

$lineas = [];
$lineas[] = "📋 *RESUMEN DEL DÍA — " . $fechaVisible . "*";
$lineas[] = "";
$lineas[] = "🔴 *TAREAS PENDIENTES*";
$lineas[] = "";

if (count($pendientesPorProyecto) === 0) {
    $lineas[] = "• Sin tareas pendientes";
} else {
    foreach ($pendientesPorProyecto as $proyecto => $tareasProyecto) {
        $lineas[] = "📁 *" . $proyecto . "*";

        foreach ($tareasProyecto as $tarea) {
            $linea = "• " . $tarea["titulo"];
            $linea .= formatearResponsable($tarea["asignado"]);
            $linea .= formatearVencimiento($tarea["fecha_vencimiento"], $tarea["hora_fin"]);
            $lineas[] = $linea;
        }

        $lineas[] = "";
    }
}

$lineas[] = "✅ *FINALIZADAS HOY*";
$lineas[] = "";

if (count($finalizadasPorProyecto) === 0) {
    $lineas[] = "• Ninguna tarea finalizada hoy";
} else {
    foreach ($finalizadasPorProyecto as $proyecto => $tareasProyecto) {
        $lineas[] = "📁 *" . $proyecto . "*";

        foreach ($tareasProyecto as $tarea) {
            $lineas[] = "~• " . $tarea["titulo"] . formatearResponsable($tarea["asignado"]) . "~";
        }

        $lineas[] = "";
    }
}

if (count($resumenesWhatsApp) > 0) {
    $lineas[] = "";
    $lineas[] = "💬 *RESUMEN DE WHATSAPP*";
    $lineas[] = "";

    foreach ($resumenesWhatsApp as $resumenWhatsApp) {
        $lineas[] = "👥 *" . $resumenWhatsApp["chat_nombre"] . "*";
        $lineas[] = trim($resumenWhatsApp["resumen"]);
        $lineas[] = "";
    }
}

$textoResumen = trim(implode("\n", $lineas));

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>

    <style>
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-column {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            background: #ffffff;
        }

        .summary-column h3 {
            margin: 0 0 18px;
        }

        .summary-project + .summary-project {
            margin-top: 22px;
        }

        .summary-project h4 {
            margin: 0 0 10px;
            color: #334155;
        }

        .summary-task {
            padding: 10px 12px;
            margin-bottom: 8px;
            border-radius: 10px;
            background: #f8fafc;
        }

        .summary-task strong {
            display: block;
        }

        .summary-task small {
            display: block;
            margin-top: 4px;
            color: #64748b;
        }

        .summary-task-completed strong {
            text-decoration: line-through;
            color: #64748b;
        }

        .summary-textarea {
            width: 100%;
            min-height: 360px;
            padding: 16px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            resize: vertical;
            font: inherit;
            line-height: 1.55;
            background: #f8fafc;
        }

        .summary-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .summary-copy-button {
            border: 0;
            border-radius: 10px;
            padding: 11px 18px;
            background: #16a34a;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        .summary-copy-button:hover {
            background: #15803d;
        }

        #copyStatus {
            color: #15803d;
            font-weight: 600;
        }

        .whatsapp-summary-list {
            display: grid;
            gap: 14px;
            margin-bottom: 24px;
        }

        .whatsapp-summary-item {
            border: 1px solid #bbf7d0;
            border-radius: 14px;
            padding: 18px;
            background: #f0fdf4;
        }

        .whatsapp-summary-item h3 {
            margin: 0 0 8px;
            color: #166534;
        }

        .whatsapp-summary-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .whatsapp-summary-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 13px;
            border: 1px solid #86efac;
            border-radius: 9px;
            background: #ffffff;
            color: #166534;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .whatsapp-summary-edit:hover {
            background: #dcfce7;
        }

        .whatsapp-summary-item pre {
            margin: 14px 0 0;
            white-space: pre-wrap;
            word-break: break-word;
            font: inherit;
            line-height: 1.55;
            color: #1f2937;
        }

        .summary-notice {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #ecfdf5;
            color: #047857;
            font-weight: 700;
        }

        .summary-warning {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fffbeb;
            color: #92400e;
        }

        @media (max-width: 900px) {
            .summary-grid {
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

        <div class="cards-dashboard">
            <div class="card-dashboard">
                <h3><?php echo count($pendientes); ?></h3>
                <p>Tareas pendientes</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo count($finalizadasHoy); ?></h3>
                <p>Finalizadas hoy</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo count($resumenesWhatsApp); ?></h3>
                <p>Chats resumidos hoy</p>
            </div>
        </div>

        <section class="content-card">
            <?php if (isset($_GET["whatsapp"]) && $_GET["whatsapp"] === "guardado"): ?>
                <div class="summary-notice">El resumen de WhatsApp se generó y guardó correctamente.</div>
            <?php endif; ?>

            <?php if (isset($_GET["whatsapp"]) && $_GET["whatsapp"] === "editado"): ?>
                <div class="summary-notice">El resumen de WhatsApp se actualizó correctamente.</div>
            <?php endif; ?>

            <?php if (isset($_GET["whatsapp"]) && $_GET["whatsapp"] === "eliminado"): ?>
                <div class="summary-notice">El resumen de WhatsApp se eliminó correctamente.</div>
            <?php endif; ?>

            <?php if (!$whatsappDisponible): ?>
                <div class="summary-warning">
                    Para habilitar WhatsApp, importá una vez el archivo <strong>instalacion_resumen_whatsapp.sql</strong> desde phpMyAdmin.
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <h2 class="section-title">Resumen del <?php echo htmlspecialchars($fechaVisible); ?></h2>
                    <p>Las tareas finalizadas hoy dejarán de aparecer mañana.</p>
                </div>

                <a href="tareas.php" class="btn-secondary-clean">Volver a tareas</a>
            </div>

            <div class="summary-grid">
                <div class="summary-column">
                    <h3>🔴 Tareas pendientes</h3>

                    <?php if (count($pendientesPorProyecto) === 0): ?>
                        <div class="empty-state">
                            <strong>No hay tareas pendientes</strong>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendientesPorProyecto as $proyecto => $tareasProyecto): ?>
                            <div class="summary-project">
                                <h4>📁 <?php echo htmlspecialchars($proyecto); ?></h4>

                                <?php foreach ($tareasProyecto as $tarea): ?>
                                    <div class="summary-task">
                                        <strong><?php echo htmlspecialchars($tarea["titulo"]); ?></strong>
                                        <small>
                                            Responsable: <?php echo htmlspecialchars(obtenerApocopeResponsable($tarea["asignado"])); ?> ·
                                            <?php echo htmlspecialchars($tarea["estado"]); ?> · <?php echo (int)$tarea["progreso"]; ?>%

                                            <?php if ($tarea["fecha_vencimiento"]): ?>
                                                · Vence <?php echo date("d/m", strtotime($tarea["fecha_vencimiento"])); ?>
                                                <?php echo $tarea["hora_fin"] ? substr($tarea["hora_fin"], 0, 5) : ""; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="summary-column">
                    <h3>✅ Finalizadas hoy</h3>

                    <?php if (count($finalizadasPorProyecto) === 0): ?>
                        <div class="empty-state">
                            <strong>Todavía no finalizaste tareas hoy</strong>
                        </div>
                    <?php else: ?>
                        <?php foreach ($finalizadasPorProyecto as $proyecto => $tareasProyecto): ?>
                            <div class="summary-project">
                                <h4>📁 <?php echo htmlspecialchars($proyecto); ?></h4>

                                <?php foreach ($tareasProyecto as $tarea): ?>
                                    <div class="summary-task summary-task-completed">
                                        <strong><?php echo htmlspecialchars($tarea["titulo"]); ?></strong>
                                        <small>
                                            Responsable: <?php echo htmlspecialchars(obtenerApocopeResponsable($tarea["asignado"])); ?> ·
                                            Finalizada a las <?php echo date("H:i", strtotime($tarea["finalizado_en"])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-header">
                <div>
                    <h2 class="section-title">💬 Resúmenes de WhatsApp</h2>
                    <p>Se agregan al texto final cuando los enviás desde la extensión.</p>
                </div>
            </div>

            <?php if (count($resumenesWhatsApp) === 0): ?>
                <div class="empty-state" style="margin-bottom: 24px;">
                    <strong>Todavía no importaste conversaciones hoy</strong>
                    <p>Abrí WhatsApp Web y presioná “Enviar chat al gestor”.</p>
                </div>
            <?php else: ?>
                <div class="whatsapp-summary-list">
                    <?php foreach ($resumenesWhatsApp as $resumenWhatsApp): ?>
                        <article class="whatsapp-summary-item">
                            <div class="whatsapp-summary-header">
                                <div>
                                    <h3>👥 <?php echo htmlspecialchars($resumenWhatsApp["chat_nombre"]); ?></h3>
                                    <small>
                                        <?php echo (int)$resumenWhatsApp["cantidad_mensajes"]; ?> mensajes ·
                                        Actualizado <?php echo date("H:i", strtotime($resumenWhatsApp["actualizado_en"])); ?>
                                    </small>
                                </div>
                                <a
                                    class="whatsapp-summary-edit"
                                    href="editar_resumen_whatsapp.php?id=<?php echo (int)$resumenWhatsApp["id"]; ?>"
                                >Editar</a>
                            </div>
                            <pre><?php echo htmlspecialchars($resumenWhatsApp["resumen"]); ?></pre>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <h2 class="section-title">Texto para WhatsApp</h2>
                    <p>Copialo y pegalo directamente en la conversación.</p>
                </div>
            </div>

            <textarea id="summaryText" class="summary-textarea" readonly><?php echo htmlspecialchars($textoResumen); ?></textarea>

            <div class="summary-actions">
                <button type="button" class="summary-copy-button" onclick="copiarResumen()">
                    Copiar para WhatsApp
                </button>
                <span id="copyStatus" aria-live="polite"></span>
            </div>
        </section>
    </main>
</div>

<script>
    async function copiarResumen() {
        const campo = document.getElementById("summaryText");
        const estado = document.getElementById("copyStatus");

        try {
            await navigator.clipboard.writeText(campo.value);
        } catch (error) {
            campo.removeAttribute("readonly");
            campo.select();
            document.execCommand("copy");
            campo.setAttribute("readonly", "readonly");
            window.getSelection().removeAllRanges();
        }

        estado.textContent = "Resumen copiado.";

        setTimeout(function () {
            estado.textContent = "";
        }, 2500);
    }
</script>

</body>
</html>
