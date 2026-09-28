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

if (!isset($_SESSION["csrf_editar_resumen_whatsapp"])) {
    $_SESSION["csrf_editar_resumen_whatsapp"] = bin2hex(random_bytes(32));
}

$idResumen = isset($_POST["id"]) ? (int)$_POST["id"] : (isset($_GET["id"]) ? (int)$_GET["id"] : 0);
if ($idResumen <= 0) {
    header("Location: resumen_diario.php");
    exit;
}

$sqlResumen = "SELECT id, chat_nombre, fecha_resumen, resumen
               FROM resumenes_whatsapp
               WHERE id = :id AND usuario_id = :usuario_id
               LIMIT 1";
$stmtResumen = $pdo->prepare($sqlResumen);
$stmtResumen->execute([
    ":id" => $idResumen,
    ":usuario_id" => (int)$_SESSION["usuario_id"],
]);
$resumenWhatsApp = $stmtResumen->fetch(PDO::FETCH_ASSOC);

if (!$resumenWhatsApp) {
    header("Location: resumen_diario.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = isset($_POST["csrf_token"]) ? (string)$_POST["csrf_token"] : "";
    $accionFormulario = isset($_POST["accion"]) ? (string)$_POST["accion"] : "guardar";
    $chatNombre = trim(isset($_POST["chat_nombre"]) ? (string)$_POST["chat_nombre"] : "");
    $resumenEditado = trim(isset($_POST["resumen"]) ? (string)$_POST["resumen"] : "");

    if (!hash_equals($_SESSION["csrf_editar_resumen_whatsapp"], $csrf)) {
        $error = "La sesión del formulario venció. Recargá la página e intentá otra vez.";
    } elseif ($accionFormulario === "eliminar") {
        $sqlEliminar = "DELETE FROM resumenes_whatsapp
                        WHERE id = :id AND usuario_id = :usuario_id";
        $stmtEliminar = $pdo->prepare($sqlEliminar);
        $stmtEliminar->execute([
            ":id" => $idResumen,
            ":usuario_id" => (int)$_SESSION["usuario_id"],
        ]);

        header("Location: resumen_diario.php?whatsapp=eliminado");
        exit;
    } elseif ($chatNombre === "" || strlen($chatNombre) > 190) {
        $error = "Ingresá un nombre de chat válido.";
    } elseif ($resumenEditado === "" || strlen($resumenEditado) > 120000) {
        $error = "El resumen no puede quedar vacío ni superar los 120.000 caracteres.";
    } else {
        $sqlActualizar = "UPDATE resumenes_whatsapp
                          SET chat_nombre = :chat_nombre,
                              resumen = :resumen,
                              actualizado_en = NOW()
                          WHERE id = :id AND usuario_id = :usuario_id";
        try {
            $stmtActualizar = $pdo->prepare($sqlActualizar);
            $stmtActualizar->execute([
                ":chat_nombre" => $chatNombre,
                ":resumen" => $resumenEditado,
                ":id" => $idResumen,
                ":usuario_id" => (int)$_SESSION["usuario_id"],
            ]);

            header("Location: resumen_diario.php?whatsapp=editado");
            exit;
        } catch (PDOException $e) {
            $error = "No se pudo guardar. Verificá que no exista otro resumen de hoy con ese mismo nombre de chat.";
        }
    }

    $resumenWhatsApp["chat_nombre"] = $chatNombre;
    $resumenWhatsApp["resumen"] = $resumenEditado;
}

$tituloPagina = "Editar resumen de WhatsApp";
$subtituloPagina = "Corregí el contenido generado antes de copiarlo o compartirlo.";
$fechaVisible = date("d/m", strtotime($resumenWhatsApp["fecha_resumen"]));

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>
    <style>
        .edit-summary-form {
            display: grid;
            gap: 18px;
        }

        .edit-summary-field label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-weight: 700;
        }

        .edit-summary-field input,
        .edit-summary-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #ffffff;
            color: #0f172a;
            font: inherit;
        }

        .edit-summary-field textarea {
            min-height: 520px;
            resize: vertical;
            line-height: 1.55;
        }

        .edit-summary-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .edit-summary-save {
            border: 0;
            border-radius: 10px;
            padding: 11px 18px;
            background: #16a34a;
            color: #ffffff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .edit-summary-delete {
            margin-left: auto;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 11px 18px;
            background: #ffffff;
            color: #b91c1c;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .edit-summary-error {
            padding: 14px 16px;
            border-radius: 12px;
            background: #fef2f2;
            color: #b91c1c;
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="app-layout">
    <?php require_once "views/partials/sidebar.php"; ?>

    <main class="app-main">
        <?php require_once "views/partials/header.php"; ?>

        <section class="content-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Resumen del <?php echo htmlspecialchars($fechaVisible); ?></h2>
                    <p>Podés cambiar el nombre del chat y cualquier parte del texto generado.</p>
                </div>
            </div>

            <?php if ($error !== ""): ?>
                <div class="edit-summary-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" class="edit-summary-form">
                <input type="hidden" name="id" value="<?php echo (int)$resumenWhatsApp["id"]; ?>">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["csrf_editar_resumen_whatsapp"]); ?>"
                >

                <div class="edit-summary-field">
                    <label for="chat_nombre">Nombre del chat</label>
                    <input
                        type="text"
                        id="chat_nombre"
                        name="chat_nombre"
                        maxlength="190"
                        required
                        value="<?php echo htmlspecialchars($resumenWhatsApp["chat_nombre"]); ?>"
                    >
                </div>

                <div class="edit-summary-field">
                    <label for="resumen">Contenido del resumen</label>
                    <textarea
                        id="resumen"
                        name="resumen"
                        maxlength="120000"
                        required
                    ><?php echo htmlspecialchars($resumenWhatsApp["resumen"]); ?></textarea>
                </div>

                <div class="edit-summary-actions">
                    <button type="submit" name="accion" value="guardar" class="edit-summary-save">Guardar cambios</button>
                    <a href="resumen_diario.php" class="btn-secondary-clean">Cancelar</a>
                    <button
                        type="submit"
                        name="accion"
                        value="eliminar"
                        class="edit-summary-delete"
                        formnovalidate
                        onclick="return confirm('¿Eliminar definitivamente este resumen de WhatsApp?');"
                    >Eliminar resumen</button>
                </div>
            </form>
        </section>
    </main>
</div>

</body>
</html>
