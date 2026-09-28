<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$tituloPagina = "Proyectos";
$subtituloPagina = "Creá, editá y organizá los proyectos del sistema.";

$usuarioId = $_SESSION["usuario_id"];
$error = "";
$success = "";

/* CREAR PROYECTO */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["crear_proyecto"])) {
    $nombre = trim($_POST["nombre"]);
    $descripcion = trim($_POST["descripcion"]);

    if ($nombre === "") {
        $error = "El nombre del proyecto es obligatorio.";
    } else {
        $sql = "INSERT INTO proyectos (nombre, descripcion, creado_por)
                VALUES (:nombre, :descripcion, :creado_por)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":nombre" => $nombre,
            ":descripcion" => $descripcion,
            ":creado_por" => $usuarioId
        ]);

        header("Location: proyectos.php?success=creado");
        exit;
    }
}

/* EDITAR PROYECTO */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["editar_proyecto"])) {
    $id = $_POST["id"];
    $nombre = trim($_POST["nombre"]);
    $descripcion = trim($_POST["descripcion"]);

    if ($nombre === "") {
        $error = "El nombre del proyecto es obligatorio.";
    } else {
        $sql = "UPDATE proyectos 
                SET nombre = :nombre, descripcion = :descripcion
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":nombre" => $nombre,
            ":descripcion" => $descripcion,
            ":id" => $id
        ]);

        header("Location: proyectos.php?success=editado");
        exit;
    }
}

/* ELIMINAR PROYECTO */
if (isset($_GET["eliminar"])) {
    $id = $_GET["eliminar"];

    $sql = "DELETE FROM proyectos WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":id" => $id
    ]);

    header("Location: proyectos.php?success=eliminado");
    exit;
}

/* MENSAJES */
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creado") {
        $success = "Proyecto creado correctamente.";
    }

    if ($_GET["success"] === "editado") {
        $success = "Proyecto actualizado correctamente.";
    }

    if ($_GET["success"] === "eliminado") {
        $success = "Proyecto eliminado correctamente.";
    }
}

/* TRAER PROYECTO PARA EDITAR */
$proyectoEditar = null;

if (isset($_GET["editar"])) {
    $id = $_GET["editar"];

    $sql = "SELECT * FROM proyectos WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":id" => $id
    ]);

    $proyectoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* LISTAR PROYECTOS */
$sql = "SELECT 
            p.*,
            u.nombre AS creador,

            COUNT(t.id) AS total_tareas,

            COALESCE(ROUND(AVG(t.progreso)), 0) AS progreso_promedio,

            SUM(CASE 
                WHEN e.nombre = 'Finalizada' THEN 1 
                ELSE 0 
            END) AS tareas_finalizadas,

            SUM(CASE 
                WHEN t.fecha_vencimiento < CURDATE()
                AND e.nombre NOT IN ('Finalizada', 'Cancelada')
                THEN 1 
                ELSE 0 
            END) AS tareas_vencidas,

            SUM(CASE 
                WHEN e.nombre = 'En proceso' THEN 1 
                ELSE 0 
            END) AS tareas_en_proceso,

            SUM(CASE 
                WHEN e.nombre = 'Pendiente' THEN 1 
                ELSE 0 
            END) AS tareas_pendientes

        FROM proyectos p
        INNER JOIN usuarios u ON p.creado_por = u.id
        LEFT JOIN tareas t ON t.proyecto_id = p.id
        LEFT JOIN estados_tarea e ON t.estado_id = e.id
        GROUP BY p.id
        ORDER BY p.creado_en DESC";

$stmt = $pdo->query($sql);
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ESTADÍSTICAS */
$totalProyectos = count($proyectos);
$totalTareas = 0;
$totalFinalizadas = 0;
$proyectosFinalizados = 0;
$proyectosConVencidas = 0;

foreach ($proyectos as $proyecto) {
    $totalTareas += (int)$proyecto["total_tareas"];
    $totalFinalizadas += (int)$proyecto["tareas_finalizadas"];

    if ((int)$proyecto["total_tareas"] > 0 && (int)$proyecto["progreso_promedio"] === 100) {
        $proyectosFinalizados++;
    }

    if ((int)$proyecto["tareas_vencidas"] > 0) {
        $proyectosConVencidas++;
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

        <div class="cards-dashboard projects-summary">
    <div class="card-dashboard">
        <h3><?php echo $totalProyectos; ?></h3>
        <p>Proyectos cargados</p>
    </div>

    <div class="card-dashboard">
        <h3><?php echo $totalTareas; ?></h3>
        <p>Tareas asociadas</p>
    </div>

    <div class="card-dashboard">
        <h3><?php echo $proyectosFinalizados; ?></h3>
        <p>Proyectos finalizados</p>
    </div>

    <div class="card-dashboard card-danger">
        <h3><?php echo $proyectosConVencidas; ?></h3>
        <p>Con tareas vencidas</p>
    </div>
</div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <section class="content-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">
                        <?php echo $proyectoEditar ? "Editar proyecto" : "Crear nuevo proyecto"; ?>
                    </h2>
                    <p>
                        <?php echo $proyectoEditar 
                            ? "Modificá los datos principales del proyecto seleccionado." 
                            : "Cargá un proyecto para agrupar tareas, fechas y responsables."; 
                        ?>
                    </p>
                </div>

                <?php if ($proyectoEditar): ?>
                    <a href="proyectos.php" class="btn-secondary-clean">Cancelar edición</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="form-box">

                <?php if ($proyectoEditar): ?>
                    <input type="hidden" name="id" value="<?php echo $proyectoEditar['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label>Nombre del proyecto</label>
                        <input 
                            type="text" 
                            name="nombre" 
                            required
                            placeholder="Ej: Inspección 100 hs LV-ABC"
                            value="<?php echo $proyectoEditar ? htmlspecialchars($proyectoEditar['nombre']) : ''; ?>"
                        >
                    </div>

                    <div>
                        <label>Creador</label>
                        <input 
                            type="text" 
                            value="<?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?>" 
                            disabled
                        >
                    </div>
                </div>

                <label>Descripción</label>
                <textarea 
                    name="descripcion" 
                    rows="4"
                    placeholder="Agregá una breve descripción del proyecto..."
                ><?php echo $proyectoEditar ? htmlspecialchars($proyectoEditar['descripcion']) : ''; ?></textarea>

                <div class="form-actions">
                    <?php if ($proyectoEditar): ?>
                        <button type="submit" name="editar_proyecto">Guardar cambios</button>
                    <?php else: ?>
                        <button type="submit" name="crear_proyecto">Crear proyecto</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="content-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Listado de proyectos</h2>
                    <p>Revisá los proyectos creados y la cantidad de tareas asociadas.</p>
                </div>

                <a href="tareas.php" class="btn-primary">Crear tarea</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                                <tr>
                <th>Proyecto</th>
                <th>Descripción</th>
                <th>Creador</th>
                <th>Tareas</th>
                <th>Estado</th>
                <th>Avance</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
                    </thead>

                    <tbody>
                        <?php if (count($proyectos) > 0): ?>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <?php
    $total = (int)$proyecto["total_tareas"];
    $avance = (int)$proyecto["progreso_promedio"];
    $vencidas = (int)$proyecto["tareas_vencidas"];

    $estadoProyecto = "Sin tareas";
    $colorEstadoProyecto = "#64748b";

    if ($total > 0) {
        if ($vencidas > 0) {
            $estadoProyecto = "Con vencidas";
            $colorEstadoProyecto = "#dc2626";
        } elseif ($avance === 100) {
            $estadoProyecto = "Finalizado";
            $colorEstadoProyecto = "#16a34a";
        } elseif ($avance > 0) {
            $estadoProyecto = "En proceso";
            $colorEstadoProyecto = "#2563eb";
        } else {
            $estadoProyecto = "Pendiente";
            $colorEstadoProyecto = "#64748b";
        }
    }
?>

                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($proyecto["nombre"]); ?></strong>
                                    </td>

                                    <td>
                                        <?php if (!empty($proyecto["descripcion"])): ?>
                                            <?php echo htmlspecialchars($proyecto["descripcion"]); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin descripción</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?php echo htmlspecialchars($proyecto["creador"]); ?></td>

                                    <td>
    <span class="soft-badge">
        <?php echo $total; ?> tareas
    </span>

    <?php if ($total > 0): ?>
        <br>
        <small class="text-muted">
            <?php echo (int)$proyecto["tareas_finalizadas"]; ?> finalizadas
        </small>
    <?php endif; ?>
</td>

<td>
    <span class="badge" style="background: <?php echo $colorEstadoProyecto; ?>">
        <?php echo $estadoProyecto; ?>
    </span>
</td>

<td>
    <div class="progress-bar">
        <div class="progress-fill" style="width: <?php echo $avance; ?>%;">
            <?php echo $avance; ?>%
        </div>
    </div>

    <small class="text-muted">
        Promedio de avance de tareas
    </small>
</td>

                                    <td><?php echo date("d/m/Y", strtotime($proyecto["creado_en"])); ?></td>

                                    <td class="actions-cell">
                                        <a class="btn-small" href="proyectos.php?editar=<?php echo $proyecto['id']; ?>">
                                            Editar
                                        </a>

                                        <a 
                                            class="btn-small btn-danger" 
                                            href="proyectos.php?eliminar=<?php echo $proyecto['id']; ?>"
                                            onclick="return confirm('¿Seguro que querés eliminar este proyecto? También se eliminarán sus tareas.')"
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
                                        <span>📁</span>
                                        <strong>Todavía no hay proyectos cargados</strong>
                                        <p>Creá tu primer proyecto para empezar a organizar tareas.</p>
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