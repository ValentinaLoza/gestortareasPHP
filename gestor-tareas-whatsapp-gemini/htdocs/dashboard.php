<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$tituloPagina = "Dashboard";
$subtituloPagina = "Resumen general de proyectos, tareas y vencimientos.";

$nombreUsuario = $_SESSION["usuario_nombre"];

/* TOTAL DE PROYECTOS */
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM proyectos");
$totalProyectos = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

/* TOTAL DE TAREAS */
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM tareas");
$totalTareas = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

/* TAREAS PENDIENTES */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM tareas t
    INNER JOIN estados_tarea e ON t.estado_id = e.id
    WHERE e.nombre = 'Pendiente'
");
$stmt->execute();
$tareasPendientes = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

/* TAREAS EN PROCESO */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM tareas t
    INNER JOIN estados_tarea e ON t.estado_id = e.id
    WHERE e.nombre = 'En proceso'
");
$stmt->execute();
$tareasEnProceso = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

/* TAREAS FINALIZADAS */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM tareas t
    INNER JOIN estados_tarea e ON t.estado_id = e.id
    WHERE e.nombre = 'Finalizada'
");
$stmt->execute();
$tareasFinalizadas = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

/* TAREAS VENCIDAS */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM tareas t
    INNER JOIN estados_tarea e ON t.estado_id = e.id
    WHERE t.fecha_vencimiento < CURDATE()
    AND e.nombre NOT IN ('Finalizada', 'Cancelada')
");
$stmt->execute();
$tareasVencidas = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

/* PRÓXIMAS TAREAS A VENCER */
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.titulo,
        t.fecha_vencimiento,
        p.nombre AS proyecto,
        e.nombre AS estado,
        e.color AS estado_color,
        pr.nombre AS prioridad,
        pr.color AS prioridad_color
    FROM tareas t
    INNER JOIN proyectos p ON t.proyecto_id = p.id
    INNER JOIN estados_tarea e ON t.estado_id = e.id
    INNER JOIN prioridades_tarea pr ON t.prioridad_id = pr.id
    WHERE t.fecha_vencimiento IS NOT NULL
    AND e.nombre NOT IN ('Finalizada', 'Cancelada')
    ORDER BY t.fecha_vencimiento ASC
    LIMIT 5
");
$stmt->execute();
$proximasTareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <h3><?php echo $totalProyectos; ?></h3>
                <p>Proyectos activos</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $totalTareas; ?></h3>
                <p>Total de tareas</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasPendientes; ?></h3>
                <p>Tareas pendientes</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasEnProceso; ?></h3>
                <p>En proceso</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasFinalizadas; ?></h3>
                <p>Finalizadas</p>
            </div>

            <div class="card-dashboard card-danger">
                <h3><?php echo $tareasVencidas; ?></h3>
                <p>Vencidas</p>
            </div>
        </div>

        <section class="content-card">
            <h2 class="section-title">Próximas tareas a vencer</h2>

            <table>
                <thead>
                    <tr>
                        <th>Tarea</th>
                        <th>Proyecto</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Vencimiento</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($proximasTareas) > 0): ?>
                        <?php foreach ($proximasTareas as $tarea): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($tarea["titulo"]); ?></strong>
                                </td>

                                <td><?php echo htmlspecialchars($tarea["proyecto"]); ?></td>

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
                                    <?php 
                                        $fechaVencimiento = $tarea["fecha_vencimiento"];
                                        $hoy = date("Y-m-d");

                                        if ($fechaVencimiento < $hoy) {
                                            echo "<span class='texto-vencido'>" . htmlspecialchars($fechaVencimiento) . " - Vencida</span>";
                                        } else {
                                            echo htmlspecialchars($fechaVencimiento);
                                        }
                                    ?>
                                </td>

                                <td>
                                    <a class="btn-small" href="tareas.php?editar=<?php echo $tarea['id']; ?>">
                                        Ver / Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No hay tareas próximas a vencer.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="content-card">
            <h2 class="section-title">Accesos rápidos</h2>

            <div class="quick-actions">
                <a href="proyectos.php" class="quick-action-card">
                    <span>📁</span>
                    <strong>Gestionar proyectos</strong>
                    <small>Crear, editar o revisar proyectos.</small>
                </a>

                <a href="tareas.php" class="quick-action-card">
                    <span>✅</span>
                    <strong>Gestionar tareas</strong>
                    <small>Cargar tareas, responsables y fechas.</small>
                </a>

                <a href="kanban.php" class="quick-action-card">
                    <span>🧩</span>
                    <strong>Vista Kanban</strong>
                    <small>Ver tareas separadas por estado.</small>
                </a>

                <a href="calendario.php" class="quick-action-card">
                    <span>📅</span>
                    <strong>Calendario</strong>
                    <small>Ver tareas por fecha.</small>
                </a>

                <a href="gantt.php" class="quick-action-card">
                    <span>📈</span>
                    <strong>Diagrama Gantt</strong>
                    <small>Planificación por línea de tiempo.</small>
                </a>

                <a href="dependencias.php" class="quick-action-card">
                    <span>🔗</span>
                    <strong>Dependencias</strong>
                    <small>Relacionar tareas entre sí.</small>
                </a>
            </div>
        </section>

    </main>

</div>

</body>
</html>