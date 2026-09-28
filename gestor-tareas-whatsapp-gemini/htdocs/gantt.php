<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$tituloPagina = "Gantt";
$subtituloPagina = "Planificá tareas en línea de tiempo, fechas, avance y dependencias.";

/* TRAER TAREAS PARA GANTT */
$sql = "SELECT 
            t.id,
            t.titulo,
            t.descripcion,
            t.fecha_inicio,
            t.fecha_vencimiento,
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
        ORDER BY t.fecha_inicio ASC, t.fecha_vencimiento ASC";

$stmt = $pdo->query($sql);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* TRAER DEPENDENCIAS */
$sqlDependencias = "SELECT 
                        tarea_id,
                        depende_de_tarea_id
                    FROM dependencias_tarea";

$stmtDep = $pdo->query($sqlDependencias);
$dependenciasBD = $stmtDep->fetchAll(PDO::FETCH_ASSOC);

$dependenciasPorTarea = [];

foreach ($dependenciasBD as $dep) {
    $dependenciasPorTarea[$dep["tarea_id"]][] = "tarea_" . $dep["depende_de_tarea_id"];
}

/* ARMAR TAREAS PARA FRAPPE GANTT */
$tasksGantt = [];

$totalTareasGantt = count($tareas);
$tareasVencidas = 0;
$tareasFinalizadas = 0;
$tareasSinAsignar = 0;
$hoy = date("Y-m-d");

foreach ($tareas as $tarea) {
    $fechaInicio = $tarea["fecha_inicio"] ?: $tarea["fecha_vencimiento"];
    $fechaFin = $tarea["fecha_vencimiento"] ?: $tarea["fecha_inicio"];

    if (
        $tarea["fecha_vencimiento"] &&
        $tarea["fecha_vencimiento"] < $hoy &&
        !in_array($tarea["estado"], ["Finalizada", "Cancelada"])
    ) {
        $tareasVencidas++;
    }

    if ($tarea["estado"] === "Finalizada") {
        $tareasFinalizadas++;
    }

    if (!$tarea["asignado"]) {
        $tareasSinAsignar++;
    }

    $tasksGantt[] = [
        "id" => "tarea_" . $tarea["id"],
        "name" => $tarea["titulo"],
        "start" => $fechaInicio,
        "end" => $fechaFin,
        "progress" => (int)$tarea["progreso"],
        "dependencies" => isset($dependenciasPorTarea[$tarea["id"]]) 
            ? implode(",", $dependenciasPorTarea[$tarea["id"]]) 
            : "",
        "custom_class" => "gantt-task-" . $tarea["id"],
        "data" => [
            "id_real" => $tarea["id"],
            "proyecto" => $tarea["proyecto"],
            "estado" => $tarea["estado"],
            "estado_color" => $tarea["estado_color"],
            "prioridad" => $tarea["prioridad"],
            "prioridad_color" => $tarea["prioridad_color"],
            "responsable" => $tarea["asignado"] ?: "Sin asignar",
            "descripcion" => $tarea["descripcion"] ?: "Sin descripción.",
            "inicio" => $fechaInicio,
            "fin" => $fechaFin,
            "progreso" => $tarea["progreso"],
            "vencida" => (
                $tarea["fecha_vencimiento"] &&
                $tarea["fecha_vencimiento"] < $hoy &&
                !in_array($tarea["estado"], ["Finalizada", "Cancelada"])
            )
        ]
    ];
}

$tasksJson = json_encode($tasksGantt);
$totalDependencias = count($dependenciasBD);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.css">
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.umd.js"></script>
</head>
<body>

<div class="app-layout">

    <?php require_once "views/partials/sidebar.php"; ?>

    <main class="app-main">

        <?php require_once "views/partials/header.php"; ?>

        <div class="cards-dashboard gantt-summary">
            <div class="card-dashboard">
                <h3><?php echo $totalTareasGantt; ?></h3>
                <p>Tareas planificadas</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $totalDependencias; ?></h3>
                <p>Dependencias</p>
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

        <section class="content-card gantt-page-card">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Diagrama de Gantt</h2>
                    <p>Mové barras para actualizar fechas y ajustá el progreso directamente desde el diagrama.</p>
                </div>

                <div class="form-actions no-margin">
                    <a href="tareas.php" class="btn-primary">Crear tarea</a>
                    <a href="dependencias.php" class="btn-secondary-clean">Dependencias</a>
                    <a href="calendario.php" class="btn-secondary-clean">Calendario</a>
                </div>
            </div>

            <?php if (count($tasksGantt) > 0): ?>

                <div class="gantt-helper">
                    <div>
                        <strong>Guardado automático:</strong>
                        al mover una barra o cambiar el avance, se actualiza la base de datos.
                    </div>

                    <div class="gantt-view-actions">
                        <button type="button" onclick="cambiarVista('Day')" id="btnDay">Día</button>
                        <button type="button" onclick="cambiarVista('Week')" id="btnWeek" class="active">Semana</button>
                        <button type="button" onclick="cambiarVista('Month')" id="btnMonth">Mes</button>
                    </div>
                </div>

                <div id="ganttStatus" class="gantt-status">
                    Listo para editar planificación.
                </div>

                <div class="gantt-wrapper gantt-wrapper-redesign">
                    <svg id="gantt"></svg>
                </div>

            <?php else: ?>

                <div class="empty-state">
                    <span>📈</span>
                    <strong>No hay tareas con fechas para mostrar</strong>
                    <p>Para usar el Gantt, cada tarea debe tener fecha de inicio o vencimiento.</p>
                    <br>
                    <a href="tareas.php" class="btn-primary">Crear tarea con fecha</a>
                </div>

            <?php endif; ?>

        </section>

    </main>

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
                <strong id="modalFin"></strong>
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
    const tareasGantt = <?php echo $tasksJson; ?>;
    let gantt = null;
    let vistaActual = "Week";

    document.addEventListener("DOMContentLoaded", function () {
        if (tareasGantt.length > 0) {
            gantt = new Gantt("#gantt", tareasGantt, {
                view_mode: "Week",
                language: "es",

                on_click: function(task) {
                    abrirModal(task);
                },

                on_date_change: function(task, start, end) {
                    const fechaInicio = formatearFecha(start);
                    const fechaVencimiento = formatearFecha(end);

                    task.data.inicio = fechaInicio;
                    task.data.fin = fechaVencimiento;

                    mostrarEstadoGuardado("Guardando cambios de fecha...");

                    guardarCambiosGantt({
                        id: task.data.id_real,
                        fecha_inicio: fechaInicio,
                        fecha_vencimiento: fechaVencimiento
                    });
                },

                on_progress_change: function(task, progress) {
                    task.data.progreso = progress;

                    mostrarEstadoGuardado("Guardando avance...");

                    guardarCambiosGantt({
                        id: task.data.id_real,
                        progreso: progress
                    });
                }
            });
        }
    });

    function cambiarVista(modo) {
        if (gantt) {
            gantt.change_view_mode(modo);
            vistaActual = modo;

            document.querySelectorAll(".gantt-view-actions button").forEach(btn => {
                btn.classList.remove("active");
            });

            if (modo === "Day") {
                document.getElementById("btnDay").classList.add("active");
            }

            if (modo === "Week") {
                document.getElementById("btnWeek").classList.add("active");
            }

            if (modo === "Month") {
                document.getElementById("btnMonth").classList.add("active");
            }
        }
    }

    function formatearFecha(fecha) {
        const year = fecha.getFullYear();
        const month = String(fecha.getMonth() + 1).padStart(2, "0");
        const day = String(fecha.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }

    function formatearFechaVisual(fecha) {
        if (!fecha) {
            return "-";
        }

        const partes = fecha.split("-");
        return partes[2] + "/" + partes[1] + "/" + partes[0];
    }

    function mostrarEstadoGuardado(texto) {
        const status = document.getElementById("ganttStatus");

        if (!status) {
            return;
        }

        status.textContent = texto;
        status.classList.remove("success", "error");
    }

    function guardarCambiosGantt(datos) {
        fetch("actualizar_gantt.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(datos)
        })
        .then(response => response.json())
        .then(data => {
            const status = document.getElementById("ganttStatus");

            if (!data.success) {
                if (status) {
                    status.textContent = data.message;
                    status.classList.add("error");
                }

                alert(data.message);
                location.reload();
            } else {
                if (status) {
                    status.textContent = "Cambios guardados correctamente.";
                    status.classList.add("success");

                    setTimeout(() => {
                        status.textContent = "Listo para editar planificación.";
                        status.classList.remove("success");
                    }, 2500);
                }
            }
        })
        .catch(error => {
            console.error("Error:", error);

            const status = document.getElementById("ganttStatus");

            if (status) {
                status.textContent = "Ocurrió un error al guardar los cambios.";
                status.classList.add("error");
            }

            alert("Ocurrió un error al guardar los cambios.");
            location.reload();
        });
    }

    function abrirModal(task) {
        document.getElementById("modalTitulo").textContent = task.name;
        document.getElementById("modalDescripcion").textContent = task.data.descripcion;

        document.getElementById("modalProyecto").textContent = task.data.proyecto;
        document.getElementById("modalResponsable").textContent = task.data.responsable;
        document.getElementById("modalInicio").textContent = formatearFechaVisual(task.data.inicio);
        document.getElementById("modalFin").textContent = formatearFechaVisual(task.data.fin);

        document.getElementById("modalProgreso").textContent = task.data.progreso;

        const progressFill = document.getElementById("modalProgressFill");
        progressFill.style.width = task.data.progreso + "%";
        progressFill.textContent = task.data.progreso + "%";

        const estadoBadge = document.getElementById("modalEstadoBadge");
        estadoBadge.textContent = task.data.estado;
        estadoBadge.style.background = task.data.estado_color;

        const prioridadBadge = document.getElementById("modalPrioridadBadge");
        prioridadBadge.textContent = task.data.prioridad;
        prioridadBadge.style.background = task.data.prioridad_color;

        const vencidaAlert = document.getElementById("modalVencidaAlert");
        vencidaAlert.style.display = task.data.vencida ? "block" : "none";

        document.getElementById("modalEditar").href = "tareas.php?editar=" + task.data.id_real;

        document.getElementById("modalTarea").style.display = "flex";
    }

    function cerrarModal() {
        document.getElementById("modalTarea").style.display = "none";
    }

    window.onclick = function(event) {
        const modal = document.getElementById("modalTarea");

        if (event.target === modal) {
            cerrarModal();
        }
    }
</script>

</body>
</html>