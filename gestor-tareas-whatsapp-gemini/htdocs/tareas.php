<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
require_once "services/TareaEstadoService.php";

$tituloPagina = "Tareas";
$subtituloPagina = "Creá, asigná y controlá el avance de las tareas.";

$usuarioId = $_SESSION["usuario_id"];
$error = "";
$success = "";

/* TRAER DATOS PARA LOS SELECT */
$proyectos = $pdo->query("SELECT * FROM proyectos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT * FROM estados_tarea ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);
$prioridades = $pdo->query("SELECT * FROM prioridades_tarea ORDER BY nivel ASC")->fetchAll(PDO::FETCH_ASSOC);

/* CREAR TAREA */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["crear_tarea"])) {
    $proyecto_id = $_POST["proyecto_id"];
    $titulo = trim($_POST["titulo"]);
    $descripcion = trim($_POST["descripcion"]);
    $estado_id = $_POST["estado_id"];
    $prioridad_id = $_POST["prioridad_id"];
    $asignado_a = !empty($_POST["asignado_a"]) ? $_POST["asignado_a"] : null;
    $fecha_inicio = !empty($_POST["fecha_inicio"]) ? $_POST["fecha_inicio"] : null;
    $hora_inicio = !empty($_POST["hora_inicio"]) ? $_POST["hora_inicio"] : null;

    $fecha_vencimiento = !empty($_POST["fecha_vencimiento"]) ? $_POST["fecha_vencimiento"] : null;
    $hora_fin = !empty($_POST["hora_fin"]) ? $_POST["hora_fin"] : null;

    $datosEstado = TareaEstadoService::sincronizarFormulario($pdo, $estado_id, $_POST["progreso"]);
    $estado_id = $datosEstado["estado_id"];
    $progreso = $datosEstado["progreso"];
    $horas_estimadas = !empty($_POST["horas_estimadas"]) ? $_POST["horas_estimadas"] : null;

    if ($titulo === "") {
        $error = "El título de la tarea es obligatorio.";
    } elseif ($proyecto_id === "") {
        $error = "Tenés que seleccionar un proyecto.";
    }  elseif ($fecha_inicio && $fecha_vencimiento && $fecha_inicio > $fecha_vencimiento) {
    $error = "La fecha de inicio no puede ser posterior a la fecha de vencimiento.";
    } elseif (($hora_inicio && !$fecha_inicio) || ($hora_fin && !$fecha_vencimiento)) {
    $error = "Para cargar una hora también tenés que cargar la fecha correspondiente.";

} elseif ($fecha_inicio && $fecha_vencimiento && $fecha_inicio === $fecha_vencimiento && $hora_inicio && $hora_fin && $hora_inicio >= $hora_fin) {
    $error = "Si la tarea empieza y termina el mismo día, la hora de inicio debe ser anterior a la hora de fin.";

} else {
        $sql = "INSERT INTO tareas (
                    proyecto_id,
                    titulo,
                    descripcion,
                    estado_id,
                    prioridad_id,
                    asignado_a,
                    creado_por,
                    fecha_inicio,
                    hora_inicio,
                    fecha_vencimiento,
                    hora_fin,
                    progreso,
                    horas_estimadas,
                    finalizado_en
                ) VALUES (
                    :proyecto_id,
                    :titulo,
                    :descripcion,
                    :estado_id,
                    :prioridad_id,
                    :asignado_a,
                    :creado_por,
                    :fecha_inicio,
                    :hora_inicio,
                    :fecha_vencimiento,
                    :hora_fin,
                    :progreso,
                    :horas_estimadas,
                    CASE WHEN :finalizada = 1 THEN NOW() ELSE NULL END
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":proyecto_id" => $proyecto_id,
            ":titulo" => $titulo,
            ":descripcion" => $descripcion,
            ":estado_id" => $estado_id,
            ":prioridad_id" => $prioridad_id,
            ":asignado_a" => $asignado_a,
            ":creado_por" => $usuarioId,
            ":fecha_inicio" => $fecha_inicio,
            ":hora_inicio" => $hora_inicio,
            ":fecha_vencimiento" => $fecha_vencimiento,
            ":hora_fin" => $hora_fin,
            ":progreso" => $progreso,
            ":horas_estimadas" => $horas_estimadas,
            ":finalizada" => $datosEstado["finalizada"] ? 1 : 0
        ]);

        header("Location: tareas.php?success=creada");
        exit;
    }
}

/* EDITAR TAREA */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["editar_tarea"])) {
    $id = $_POST["id"];
    $proyecto_id = $_POST["proyecto_id"];
    $titulo = trim($_POST["titulo"]);
    $descripcion = trim($_POST["descripcion"]);
    $estado_id = $_POST["estado_id"];
    $prioridad_id = $_POST["prioridad_id"];
    $asignado_a = !empty($_POST["asignado_a"]) ? $_POST["asignado_a"] : null;
    $fecha_inicio = !empty($_POST["fecha_inicio"]) ? $_POST["fecha_inicio"] : null;
    $hora_inicio = !empty($_POST["hora_inicio"]) ? $_POST["hora_inicio"] : null;

    $fecha_vencimiento = !empty($_POST["fecha_vencimiento"]) ? $_POST["fecha_vencimiento"] : null;
    $hora_fin = !empty($_POST["hora_fin"]) ? $_POST["hora_fin"] : null;

    $datosEstado = TareaEstadoService::sincronizarFormulario($pdo, $estado_id, $_POST["progreso"]);
    $estado_id = $datosEstado["estado_id"];
    $progreso = $datosEstado["progreso"];
    $horas_estimadas = !empty($_POST["horas_estimadas"]) ? $_POST["horas_estimadas"] : null;

    if ($titulo === "") {
        $error = "El título de la tarea es obligatorio.";
    } elseif ($fecha_inicio && $fecha_vencimiento && $fecha_inicio > $fecha_vencimiento) {
    $error = "La fecha de inicio no puede ser posterior a la fecha de vencimiento.";
    } elseif (($hora_inicio && !$fecha_inicio) || ($hora_fin && !$fecha_vencimiento)) {
    $error = "Para cargar una hora también tenés que cargar la fecha correspondiente.";

} elseif ($fecha_inicio && $fecha_vencimiento && $fecha_inicio === $fecha_vencimiento && $hora_inicio && $hora_fin && $hora_inicio >= $hora_fin) {
    $error = "Si la tarea empieza y termina el mismo día, la hora de inicio debe ser anterior a la hora de fin.";

} else  {
        $sql = "UPDATE tareas SET
                    proyecto_id = :proyecto_id,
                    titulo = :titulo,
                    descripcion = :descripcion,
                    estado_id = :estado_id,
                    prioridad_id = :prioridad_id,
                    asignado_a = :asignado_a,
                    fecha_inicio = :fecha_inicio,
                    hora_inicio = :hora_inicio,
                    fecha_vencimiento = :fecha_vencimiento,
                    hora_fin = :hora_fin,
                    progreso = :progreso,
                    horas_estimadas = :horas_estimadas,
                    finalizado_en = CASE
                        WHEN :finalizada = 1 THEN COALESCE(finalizado_en, NOW())
                        ELSE NULL
                    END
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":proyecto_id" => $proyecto_id,
            ":titulo" => $titulo,
            ":descripcion" => $descripcion,
            ":estado_id" => $estado_id,
            ":prioridad_id" => $prioridad_id,
            ":asignado_a" => $asignado_a,
            ":fecha_inicio" => $fecha_inicio,
            ":hora_inicio" => $hora_inicio,
            ":fecha_vencimiento" => $fecha_vencimiento,
            ":hora_fin" => $hora_fin,
            ":progreso" => $progreso,
            ":horas_estimadas" => $horas_estimadas,
            ":finalizada" => $datosEstado["finalizada"] ? 1 : 0,
            ":id" => $id
        ]);

        header("Location: tareas.php?success=editada");
        exit;
    }
}

/* ELIMINAR TAREA */
if (isset($_GET["eliminar"])) {
    $id = $_GET["eliminar"];

    $sql = "DELETE FROM tareas WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":id" => $id]);

    header("Location: tareas.php?success=eliminada");
    exit;
}

/* MENSAJES */
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creada") {
        $success = "Tarea creada correctamente.";
    }

    if ($_GET["success"] === "editada") {
        $success = "Tarea actualizada correctamente.";
    }

    if ($_GET["success"] === "eliminada") {
        $success = "Tarea eliminada correctamente.";
    }
}

/* TRAER TAREA PARA EDITAR */
$tareaEditar = null;

if (isset($_GET["editar"])) {
    $id = $_GET["editar"];

    $sql = "SELECT * FROM tareas WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":id" => $id]);

    $tareaEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* FILTRO POR PROYECTO */
$filtroProyecto = $_GET["proyecto_id"] ?? "";

/* LISTAR TAREAS */
$sql = "SELECT 
            t.*,
            p.nombre AS proyecto,
            e.nombre AS estado,
            e.color AS estado_color,
            pr.nombre AS prioridad,
            pr.color AS prioridad_color,
            u.nombre AS asignado,
            c.nombre AS creador
        FROM tareas t
        INNER JOIN proyectos p ON t.proyecto_id = p.id
        INNER JOIN estados_tarea e ON t.estado_id = e.id
        INNER JOIN prioridades_tarea pr ON t.prioridad_id = pr.id
        LEFT JOIN usuarios u ON t.asignado_a = u.id
        INNER JOIN usuarios c ON t.creado_por = c.id";

$params = [];

if ($filtroProyecto !== "") {
    $sql .= " WHERE t.proyecto_id = :proyecto_id";
    $params[":proyecto_id"] = $filtroProyecto;
}

$sql .= " ORDER BY t.creado_en DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ESTADÍSTICAS */
$totalTareas = count($tareas);
$tareasSinAsignar = 0;
$tareasVencidas = 0;
$tareasFinalizadas = 0;
$hoy = date("Y-m-d");

foreach ($tareas as $tarea) {
    if (!$tarea["asignado"]) {
        $tareasSinAsignar++;
    }

    if ($tarea["fecha_vencimiento"] && $tarea["fecha_vencimiento"] < $hoy && !in_array($tarea["estado"], ["Finalizada", "Cancelada"])) {
        $tareasVencidas++;
    }

    if ($tarea["estado"] === "Finalizada") {
        $tareasFinalizadas++;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>
</head>
<body>

<div class="app-layout">

    <?php require_once "views/partials/sidebar.php"; ?>

    <main class="app-main">

        <?php require_once "views/partials/header.php"; ?>

        <div class="cards-dashboard">
            <div class="card-dashboard">
                <h3><?php echo $totalTareas; ?></h3>
                <p>Total de tareas</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasFinalizadas; ?></h3>
                <p>Finalizadas</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasSinAsignar; ?></h3>
                <p>Sin asignar</p>
            </div>

            <div class="card-dashboard card-danger">
                <h3><?php echo $tareasVencidas; ?></h3>
                <p>Vencidas</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (count($proyectos) === 0): ?>
            <div class="error">
                Primero tenés que crear al menos un proyecto para poder cargar tareas.
            </div>
        <?php endif; ?>

        <section class="content-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">
                        <?php echo $tareaEditar ? "Editar tarea" : "Crear nueva tarea"; ?>
                    </h2>
                    <p>
                        <?php echo $tareaEditar 
                            ? "Modificá responsable, fechas, estado, prioridad y avance." 
                            : "Cargá una tarea con fechas para que luego aparezca en calendario y Gantt."; 
                        ?>
                    </p>
                </div>

                <?php if ($tareaEditar): ?>
                    <a href="tareas.php" class="btn-secondary-clean">Cancelar edición</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="form-box">

                <?php if ($tareaEditar): ?>
                    <input type="hidden" name="id" value="<?php echo $tareaEditar['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label>Proyecto</label>
                        <select name="proyecto_id" required>
                            <option value="">Seleccionar proyecto</option>

                            <?php foreach ($proyectos as $proyecto): ?>
                                <option 
                                    value="<?php echo $proyecto['id']; ?>"
                                    <?php echo ($tareaEditar && $tareaEditar['proyecto_id'] == $proyecto['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($proyecto['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Responsable</label>
                        <select name="asignado_a">
                            <option value="">Sin asignar</option>

                            <?php foreach ($usuarios as $usuario): ?>
                                <option 
                                    value="<?php echo $usuario['id']; ?>"
                                    <?php echo ($tareaEditar && $tareaEditar['asignado_a'] == $usuario['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label>Título de la tarea</label>
                <input 
                    type="text" 
                    name="titulo" 
                    required
                    placeholder="Ej: Revisar documentación inicial"
                    value="<?php echo $tareaEditar ? htmlspecialchars($tareaEditar['titulo']) : ''; ?>"
                >

                <label>Descripción</label>
                <textarea 
                    name="descripcion" 
                    rows="4"
                    placeholder="Detalle de la tarea, observaciones o aclaraciones..."
                ><?php echo $tareaEditar ? htmlspecialchars($tareaEditar['descripcion']) : ''; ?></textarea>

                <div class="form-grid">
                    <div>
                        <label>Estado</label>
                        <select name="estado_id" required>
                            <?php foreach ($estados as $estado): ?>
                                <option 
                                    value="<?php echo $estado['id']; ?>"
                                    <?php echo ($tareaEditar && $tareaEditar['estado_id'] == $estado['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($estado['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Prioridad</label>
                        <select name="prioridad_id" required>
                            <?php foreach ($prioridades as $prioridad): ?>
                                <option 
                                    value="<?php echo $prioridad['id']; ?>"
                                    <?php echo ($tareaEditar && $tareaEditar['prioridad_id'] == $prioridad['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($prioridad['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
    <div>
        <label>Fecha de inicio</label>
        <input 
            type="date" 
            name="fecha_inicio"
            value="<?php echo $tareaEditar ? $tareaEditar['fecha_inicio'] : ''; ?>"
        >
    </div>

    <div>
        <label>Hora de inicio</label>
        <input 
            type="time" 
            name="hora_inicio"
            value="<?php echo $tareaEditar && $tareaEditar['hora_inicio'] ? substr($tareaEditar['hora_inicio'], 0, 5) : ''; ?>"
        >
    </div>
</div>

<div class="form-grid">
    <div>
        <label>Fecha de vencimiento</label>
        <input 
            type="date" 
            name="fecha_vencimiento"
            value="<?php echo $tareaEditar ? $tareaEditar['fecha_vencimiento'] : ''; ?>"
        >
    </div>

    <div>
        <label>Hora de fin</label>
        <input 
            type="time" 
            name="hora_fin"
            value="<?php echo $tareaEditar && $tareaEditar['hora_fin'] ? substr($tareaEditar['hora_fin'], 0, 5) : ''; ?>"
        >
    </div>
</div>

                <div class="form-grid">
                    <div>
                        <label>Progreso %</label>
                        <input 
                            type="number" 
                            name="progreso" 
                            min="0" 
                            max="100"
                            value="<?php echo $tareaEditar ? $tareaEditar['progreso'] : '0'; ?>"
                        >
                    </div>

                    <div>
                        <label>Horas estimadas</label>
                        <input 
                            type="number" 
                            step="0.25" 
                            name="horas_estimadas"
                            placeholder="Ej: 2.5"
                            value="<?php echo $tareaEditar ? $tareaEditar['horas_estimadas'] : ''; ?>"
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($tareaEditar): ?>
                        <button type="submit" name="editar_tarea">Guardar cambios</button>
                    <?php else: ?>
                        <button type="submit" name="crear_tarea" <?php echo count($proyectos) === 0 ? 'disabled' : ''; ?>>
                            Crear tarea
                        </button>
                    <?php endif; ?>

                    <a href="kanban.php" class="btn-secondary-clean">Ver Kanban</a>
                    <a href="calendario.php" class="btn-secondary-clean">Ver calendario</a>
                    <a href="gantt.php" class="btn-secondary-clean">Ver Gantt</a>
                </div>

            </form>
        </section>

        <section class="content-card">
    <div class="section-header">
        <div>
            <h2 class="section-title">Listado de tareas</h2>
            <p>Controlá estado, prioridad, responsable, fechas y avance de cada tarea.</p>
        </div>
    </div>

    <form method="GET" class="tasks-filter-bar">
        <div>
            <label>Filtrar por proyecto</label>
            <select name="proyecto_id" onchange="this.form.submit()">
                <option value="">Todos los proyectos</option>

                <?php foreach ($proyectos as $proyecto): ?>
                    <option 
                        value="<?php echo $proyecto['id']; ?>"
                        <?php echo $filtroProyecto == $proyecto['id'] ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($proyecto['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($filtroProyecto !== ""): ?>
            <a href="tareas.php" class="btn-secondary-clean">
                Limpiar filtro
            </a>
        <?php endif; ?>
    </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tarea</th>
                            <th>Proyecto</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Fechas</th>
                            <th>Progreso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($tareas) > 0): ?>
                            <?php foreach ($tareas as $tarea): ?>
                                <?php
                                    $estaVencida = $tarea["fecha_vencimiento"] 
                                        && $tarea["fecha_vencimiento"] < $hoy 
                                        && !in_array($tarea["estado"], ["Finalizada", "Cancelada"]);
                                ?>

                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tarea["titulo"]); ?></strong>

                                        <?php if (!empty($tarea["descripcion"])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($tarea["descripcion"]); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td><?php echo htmlspecialchars($tarea["proyecto"]); ?></td>

                                    <td>
                                        <?php if ($tarea["asignado"]): ?>
                                            <span class="soft-badge">
                                                <?php echo htmlspecialchars($tarea["asignado"]); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="badge" style="background: <?php echo htmlspecialchars($tarea['estado_color']); ?>">
                                            <?php echo htmlspecialchars($tarea["estado"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge" style="background: <?php echo htmlspecialchars($tarea['prioridad_color']); ?>">
                                            <?php echo htmlspecialchars($tarea["prioridad"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                       <small>
                                            <strong>Inicio:</strong>
                                            <?php echo $tarea["fecha_inicio"] ? date("d/m/Y", strtotime($tarea["fecha_inicio"])) : "-"; ?>

                                            <?php if (!empty($tarea["hora_inicio"])): ?>
                                                <?php echo " " . substr($tarea["hora_inicio"], 0, 5); ?>
                                            <?php endif; ?>
                                        </small>
                                        <br>
                                        <small class="<?php echo $estaVencida ? 'texto-vencido' : ''; ?>">
                                            <strong>Vence:</strong>
                                            <?php echo $tarea["fecha_vencimiento"] ? date("d/m/Y", strtotime($tarea["fecha_vencimiento"])) : "-"; ?>

                                            <?php if (!empty($tarea["hora_fin"])): ?>
                                                <?php echo " " . substr($tarea["hora_fin"], 0, 5); ?>
                                            <?php endif; ?>

                                            <?php echo $estaVencida ? " - Vencida" : ""; ?>
                                        </small>
                                    </td>

                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $tarea['progreso']; ?>%;">
                                                <?php echo $tarea["progreso"]; ?>%
                                            </div>
                                        </div>

                                        <?php if ($tarea["horas_estimadas"]): ?>
                                            <small class="text-muted">
                                                <?php echo $tarea["horas_estimadas"]; ?> hs estimadas
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td class="actions-cell">
                                        <a class="btn-small" href="tareas.php?editar=<?php echo $tarea['id']; ?>">
                                            Editar
                                        </a>

                                        <a 
                                            class="btn-small btn-danger" 
                                            href="tareas.php?eliminar=<?php echo $tarea['id']; ?>"
                                            onclick="return confirm('¿Seguro que querés eliminar esta tarea?')"
                                        >
                                            Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <span>✅</span>
                                        <strong>Todavía no hay tareas cargadas</strong>
                                        <p>Creá tu primera tarea para empezar a visualizarla en Kanban, Calendario y Gantt.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

</div>

</body>
</html>
