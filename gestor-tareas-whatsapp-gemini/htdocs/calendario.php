<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
require_once "services/TareaEstadoService.php";
/* DATOS PARA CREAR TAREAS DESDE EL CALENDARIO */
$proyectos = $pdo->query("SELECT * FROM proyectos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT * FROM estados_tarea ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);
$prioridades = $pdo->query("SELECT * FROM prioridades_tarea ORDER BY nivel ASC")->fetchAll(PDO::FETCH_ASSOC);

/* CREAR TAREA DESDE CALENDARIO */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["crear_tarea_calendario"])) {
    $proyecto_id = $_POST["proyecto_id"];
    $titulo = trim($_POST["titulo"]);
    $descripcion = trim($_POST["descripcion"]);
    $estado_id = $_POST["estado_id"];
    $prioridad_id = $_POST["prioridad_id"];
    $asignado_a = !empty($_POST["asignado_a"]) ? $_POST["asignado_a"] : null;

    $fecha_inicio = $_POST["fecha_inicio"];
    $hora_inicio = $_POST["hora_inicio"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $hora_fin = $_POST["hora_fin"];

    $usuarioId = $_SESSION["usuario_id"];
    $datosEstado = TareaEstadoService::sincronizarDesdeEstado($pdo, $estado_id, 0);
    $estado_id = $datosEstado["estado_id"];
    $progreso = $datosEstado["progreso"];

    if ($titulo !== "" && $proyecto_id !== "") {
        $sqlCrear = "INSERT INTO tareas (
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
                        NULL,
                        CASE WHEN :finalizada = 1 THEN NOW() ELSE NULL END
                    )";

        $stmtCrear = $pdo->prepare($sqlCrear);
        $stmtCrear->execute([
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
            ":finalizada" => $datosEstado["finalizada"] ? 1 : 0
        ]);

        header("Location: calendario.php");
        exit;
    }
}

$tituloPagina = "Calendario";
$subtituloPagina = "Visualizá las tareas por fecha de inicio y vencimiento.";

/* TRAER TAREAS PARA EL CALENDARIO */
$sql = "SELECT 
            t.id,
            t.titulo,
            t.descripcion,
            t.fecha_inicio,
            t.hora_inicio,
            t.fecha_vencimiento,
            t.hora_fin,
            t.progreso,
            p.nombre AS proyecto,
            e.nombre AS estado,
            e.color AS estado_color,
            pr.nombre AS prioridad,
            pr.color AS prioridad_color,
            u.nombre AS asignado
        FROM tareas t
        INNER JOIN proyectos p ON t.proyecto_id = p.id
        INNER JOIN estados_tarea e ON t.estado_id = e.id
        INNER JOIN prioridades_tarea pr ON t.prioridad_id = pr.id
        LEFT JOIN usuarios u ON t.asignado_a = u.id
        WHERE t.fecha_inicio IS NOT NULL 
           OR t.fecha_vencimiento IS NOT NULL
        ORDER BY t.fecha_vencimiento ASC";

$stmt = $pdo->query($sql);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$eventos = [];
$totalEventos = count($tareas);
$tareasVencidas = 0;
$tareasSinAsignar = 0;
$tareasFinalizadas = 0;
$hoy = date("Y-m-d");

foreach ($tareas as $tarea) {
    $fechaInicio = $tarea["fecha_inicio"] ?: $tarea["fecha_vencimiento"];
$fechaFin = $tarea["fecha_vencimiento"] ?: $tarea["fecha_inicio"];

$tieneHora = !empty($tarea["hora_inicio"]);

$inicioEvento = $fechaInicio;
$finEvento = $fechaFin;

if ($tieneHora) {
    $inicioEvento = $fechaInicio . "T" . $tarea["hora_inicio"];

    if (!empty($tarea["hora_fin"])) {
        $finEvento = $fechaFin . "T" . $tarea["hora_fin"];
    } else {
        $finEvento = $fechaInicio . "T" . date("H:i:s", strtotime($tarea["hora_inicio"] . " +1 hour"));
    }
} else {
    if ($finEvento) {
        $finEvento = date("Y-m-d", strtotime($finEvento . " +1 day"));
    }
}

    if (
        $tarea["fecha_vencimiento"] &&
        $tarea["fecha_vencimiento"] < $hoy &&
        !in_array($tarea["estado"], ["Finalizada", "Cancelada"])
    ) {
        $tareasVencidas++;
    }

    if (!$tarea["asignado"]) {
        $tareasSinAsignar++;
    }

    if ($tarea["estado"] === "Finalizada") {
        $tareasFinalizadas++;
    }

    /*
        FullCalendar maneja la fecha final como exclusiva.
        Si una tarea vence el 10, sumamos 1 día para que se vea hasta el 10 inclusive.
    */
    $fechaFinCalendario = $fechaFin;

    if ($fechaFinCalendario) {
        $fechaFinCalendario = date("Y-m-d", strtotime($fechaFinCalendario . " +1 day"));
    }

    $eventos[] = [
        "id" => $tarea["id"],
        "title" => $tarea["titulo"],
        "start" => $inicioEvento,
        "end" => $finEvento,
        "allDay" => !$tieneHora,
        "color" => $tarea["estado_color"],
        "extendedProps" => [
            "proyecto" => $tarea["proyecto"],
            "estado" => $tarea["estado"],
            "prioridad" => $tarea["prioridad"],
            "prioridad_color" => $tarea["prioridad_color"],
            "responsable" => $tarea["asignado"] ?: "Sin asignar",
            "progreso" => $tarea["progreso"],
            "descripcion" => $tarea["descripcion"] ?: "",
            "fecha_inicio" => $fechaInicio,
            "fecha_vencimiento" => $fechaFin,
            "hora_inicio" => $tarea["hora_inicio"],
            "hora_fin" => $tarea["hora_fin"],
            "vencida" => (
                $tarea["fecha_vencimiento"] &&
                $tarea["fecha_vencimiento"] < $hoy &&
                !in_array($tarea["estado"], ["Finalizada", "Cancelada"])
            )
        ]
    ];
}

$eventosJson = json_encode($eventos);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
</head>
<body>

<div class="app-layout">

    <?php require_once "views/partials/sidebar.php"; ?>

    <main class="app-main">

        <?php require_once "views/partials/header.php"; ?>

        <div class="cards-dashboard calendar-summary">
            <div class="card-dashboard">
                <h3><?php echo $totalEventos; ?></h3>
                <p>Tareas con fecha</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasFinalizadas; ?></h3>
                <p>Finalizadas</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasSinAsignar; ?></h3>
                <p>Sin responsable</p>
            </div>

            <div class="card-dashboard card-danger">
                <h3><?php echo $tareasVencidas; ?></h3>
                <p>Vencidas</p>
            </div>
        </div>

        <section class="content-card calendar-page-card">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Calendario de tareas</h2>
                    <p>Las tareas se muestran según su fecha de inicio y vencimiento.</p>
                </div>

                <div class="form-actions no-margin">
                    <a href="tareas.php" class="btn-primary">Crear tarea</a>
                    <a href="kanban.php" class="btn-secondary-clean">Kanban</a>
                    <a href="gantt.php" class="btn-secondary-clean">Gantt</a>
                </div>
            </div>

            <?php if ($totalEventos === 0): ?>
                <div class="empty-state">
                    <span>📅</span>
                    <strong>No hay tareas con fechas cargadas</strong>
                    <p>Para ver tareas en el calendario, cargales fecha de inicio o vencimiento.</p>
                    <br>
                    <a href="tareas.php" class="btn-primary">Crear tarea con fecha</a>
                </div>
            <?php else: ?>
                <div class="calendar-helper">
                    <div>
                        <strong>Tip:</strong>
                        hacé clic sobre una tarea para ver su detalle.
                    </div>

                    <div class="calendar-legend">
                        <span><i class="legend-dot legend-blue"></i> Según estado</span>
                        <span><i class="legend-dot legend-red"></i> Vencidas</span>
                    </div>
                </div>

                <div id="calendar"></div>
            <?php endif; ?>

        </section>

    </main>

</div>

    <!-- MODAL CREAR TAREA DESDE CALENDARIO -->
<div id="modalCrearTarea" class="modal-overlay">
    <div class="modal-content modal-task-detail">
        <span class="modal-close" onclick="cerrarModalCrearTarea()">×</span>

        <div class="modal-task-header">
            <h2>Crear tarea</h2>
            <p>La tarea se va a crear en el horario seleccionado del calendario.</p>
        </div>

        <form method="POST" class="form-box">

            <input type="hidden" name="crear_tarea_calendario" value="1">

            <div class="form-grid">
                <div>
                    <label>Proyecto</label>
                    <select name="proyecto_id" required>
                        <option value="">Seleccionar proyecto</option>

                        <?php foreach ($proyectos as $proyecto): ?>
                            <option value="<?php echo $proyecto['id']; ?>">
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
                            <option value="<?php echo $usuario['id']; ?>">
                                <?php echo htmlspecialchars($usuario['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label>Título</label>
            <input type="text" name="titulo" required placeholder="Ej: Reunión técnica">

            <label>Descripción</label>
            <textarea name="descripcion" rows="3" placeholder="Detalle de la tarea..."></textarea>

            <div class="form-grid">
                <div>
                    <label>Estado</label>
                    <select name="estado_id" required>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?php echo $estado['id']; ?>">
                                <?php echo htmlspecialchars($estado['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Prioridad</label>
                    <select name="prioridad_id" required>
                        <?php foreach ($prioridades as $prioridad): ?>
                            <option value="<?php echo $prioridad['id']; ?>">
                                <?php echo htmlspecialchars($prioridad['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label>Fecha inicio</label>
                    <input type="date" name="fecha_inicio" id="crearFechaInicio" required>
                </div>

                <div>
                    <label>Hora inicio</label>
                    <input type="time" name="hora_inicio" id="crearHoraInicio" required>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label>Fecha fin</label>
                    <input type="date" name="fecha_vencimiento" id="crearFechaFin" required>
                </div>

                <div>
                    <label>Hora fin</label>
                    <input type="time" name="hora_fin" id="crearHoraFin" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Crear tarea</button>
                <button type="button" class="btn-secondary-clean" onclick="cerrarModalCrearTarea()">
                    Cancelar
                </button>
            </div>

        </form>
    </div>
</div>
    
<!-- MODAL DETALLE DE TAREA -->
<div id="modalTarea" class="modal-overlay">
    <div class="modal-content modal-task-detail">
        <span class="modal-close" onclick="cerrarModal()">×</span>

        <div class="modal-task-header">
            <div>
                <span id="modalEstadoBadge" class="badge">Estado</span>
                <span id="modalPrioridadBadge" class="badge">Prioridad</span>
            </div>

            <h2 id="modalTitulo"></h2>
            <p id="modalDescripcion"></p>
        </div>

        <div class="modal-task-grid">
            <div class="modal-info-box">
                <small>Proyecto</small>
                <strong id="modalProyecto"></strong>
            </div>

            <div class="modal-info-box">
                <small>Responsable</small>
                <strong id="modalResponsable"></strong>
            </div>

            <div class="modal-info-box">
                <small>Inicio</small>
                <strong id="modalInicio"></strong>
            </div>

            <div class="modal-info-box">
                <small>Vencimiento</small>
                <strong id="modalVencimiento"></strong>
            </div>
        </div>

        <div class="modal-progress-block">
            <div class="kanban-progress-info">
                <span>Avance</span>
                <strong><span id="modalProgreso"></span>%</strong>
            </div>

            <div class="progress-bar">
                <div id="modalProgressFill" class="progress-fill" style="width: 0%;">
                    0%
                </div>
            </div>
        </div>

        <div id="modalVencidaAlert" class="mini-modal-alert">
            Esta tarea está vencida.
        </div>

        <div class="modal-actions">
            <a id="modalEditar" class="btn-primary" href="#">Editar tarea</a>
            <button type="button" class="btn-secondary-clean" onclick="cerrarModal()">Cerrar</button>
        </div>
    </div>
</div>
    
    

<script>
    const eventos = <?php echo $eventosJson; ?>;

    document.addEventListener("DOMContentLoaded", function () {
        const calendarEl = document.getElementById("calendar");

        if (!calendarEl) {
            return;
        }

        const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: "timeGridDay",
                locale: "es",
                height: "auto",
                nowIndicator: true,
                selectable: true,
                editable: false,
                slotMinTime: "07:00:00",
                slotMaxTime: "22:00:00",
                slotDuration: "00:30:00",
                allDaySlot: true,

            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "timeGridDay,timeGridWeek,dayGridMonth,listWeek"
            },

           buttonText: {
                today: "Hoy",
                day: "Día",
                week: "Semana",
                month: "Mes",
                list: "Lista"
            },

            events: eventos,

            eventDidMount: function(info) {
                if (info.event.extendedProps.vencida) {
                    info.el.classList.add("calendar-event-vencida");
                }

                info.el.title = info.event.title + " - " + info.event.extendedProps.proyecto;
            },
            
            select: function(info) {
    abrirModalCrearTarea(info.start, info.end);
},

            eventClick: function(info) {
                const tarea = info.event;

                document.getElementById("modalTitulo").textContent = tarea.title;

                document.getElementById("modalDescripcion").textContent =
                    tarea.extendedProps.descripcion || "Sin descripción.";

                document.getElementById("modalProyecto").textContent = tarea.extendedProps.proyecto;
                document.getElementById("modalResponsable").textContent = tarea.extendedProps.responsable;
                document.getElementById("modalInicio").textContent = formatearFechaVisual(tarea.extendedProps.fecha_inicio);
                document.getElementById("modalVencimiento").textContent = formatearFechaVisual(tarea.extendedProps.fecha_vencimiento);
                const horaInicio = tarea.extendedProps.hora_inicio ? tarea.extendedProps.hora_inicio.substring(0, 5) : "";
                const horaFin = tarea.extendedProps.hora_fin ? tarea.extendedProps.hora_fin.substring(0, 5) : "";

                if (horaInicio || horaFin) {
                    document.getElementById("modalInicio").textContent += " " + horaInicio;
                    document.getElementById("modalVencimiento").textContent += " " + horaFin;
}

                document.getElementById("modalProgreso").textContent = tarea.extendedProps.progreso;

                const progressFill = document.getElementById("modalProgressFill");
                progressFill.style.width = tarea.extendedProps.progreso + "%";
                progressFill.textContent = tarea.extendedProps.progreso + "%";

                const estadoBadge = document.getElementById("modalEstadoBadge");
                estadoBadge.textContent = tarea.extendedProps.estado;
                estadoBadge.style.background = tarea.backgroundColor;

                const prioridadBadge = document.getElementById("modalPrioridadBadge");
                prioridadBadge.textContent = tarea.extendedProps.prioridad;
                prioridadBadge.style.background = tarea.extendedProps.prioridad_color;

                const vencidaAlert = document.getElementById("modalVencidaAlert");
                vencidaAlert.style.display = tarea.extendedProps.vencida ? "block" : "none";

                document.getElementById("modalEditar").href = "tareas.php?editar=" + tarea.id;

                document.getElementById("modalTarea").style.display = "flex";
            }
        });

        calendar.render();
    });

    function formatearFechaVisual(fecha) {
        if (!fecha) {
            return "-";
        }

        const partes = fecha.split("-");
        return partes[2] + "/" + partes[1] + "/" + partes[0];
    }

    function cerrarModal() {
        document.getElementById("modalTarea").style.display = "none";
    }

    window.onclick = function(event) {
    const modalDetalle = document.getElementById("modalTarea");
    const modalCrear = document.getElementById("modalCrearTarea");

    if (event.target === modalDetalle) {
        cerrarModal();
    }

    if (event.target === modalCrear) {
        cerrarModalCrearTarea();
    }
}
    
    function abrirModalCrearTarea(inicio, fin) {
    document.getElementById("crearFechaInicio").value = formatearFechaInput(inicio);
    document.getElementById("crearHoraInicio").value = formatearHoraInput(inicio);

    document.getElementById("crearFechaFin").value = formatearFechaInput(fin);
    document.getElementById("crearHoraFin").value = formatearHoraInput(fin);

    document.getElementById("modalCrearTarea").style.display = "flex";
}

function cerrarModalCrearTarea() {
    document.getElementById("modalCrearTarea").style.display = "none";
}

function formatearFechaInput(fecha) {
    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, "0");
    const day = String(fecha.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

function formatearHoraInput(fecha) {
    const hours = String(fecha.getHours()).padStart(2, "0");
    const minutes = String(fecha.getMinutes()).padStart(2, "0");

    return `${hours}:${minutes}`;
}
    
</script>

</body>
</html>
