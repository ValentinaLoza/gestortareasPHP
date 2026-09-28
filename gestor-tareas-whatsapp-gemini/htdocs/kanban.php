<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$tituloPagina = "Kanban";
$subtituloPagina = "Organizá tus tareas con un tablero visual estilo Trello.";

/* TRAER ESTADOS */
$estados = $pdo->query("
    SELECT * 
    FROM estados_tarea 
    ORDER BY orden ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* TRAER TAREAS */
$sql = "SELECT 
            t.*,
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
        ORDER BY t.fecha_vencimiento ASC, t.creado_en DESC";

$stmt = $pdo->query($sql);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* AGRUPAR TAREAS POR ESTADO */
$tareasPorEstado = [];

foreach ($estados as $estado) {
    $tareasPorEstado[$estado["id"]] = [];
}

foreach ($tareas as $tarea) {
    $tareasPorEstado[$tarea["estado_id"]][] = $tarea;
}

/* ESTADÍSTICAS */
$totalTareas = count($tareas);
$totalColumnas = count($estados);
$tareasVencidas = 0;
$tareasSinAsignar = 0;
$hoy = date("Y-m-d");

foreach ($tareas as $tarea) {
    if (!$tarea["asignado"]) {
        $tareasSinAsignar++;
    }

    if (
        $tarea["fecha_vencimiento"] &&
        $tarea["fecha_vencimiento"] < $hoy &&
        !in_array($tarea["estado"], ["Finalizada", "Cancelada"])
    ) {
        $tareasVencidas++;
    }
}

$tareasJson = json_encode($tareas);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "views/partials/head.php"; ?>

    <!-- Drag and drop estilo Trello -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
</head>
<body>

<div class="app-layout">

    <?php require_once "views/partials/sidebar.php"; ?>

    <main class="app-main">

        <?php require_once "views/partials/header.php"; ?>

        <div class="cards-dashboard kanban-summary">
            <div class="card-dashboard">
                <h3><?php echo $totalTareas; ?></h3>
                <p>Tareas en tablero</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $totalColumnas; ?></h3>
                <p>Estados disponibles</p>
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

        <section class="trello-toolbar">
            <div>
                <strong>Tablero Kanban</strong>
                <span>Arrastrá las tarjetas entre columnas para cambiar su estado.</span>
            </div>

            <div class="trello-toolbar-actions">
                <a href="tareas.php" class="btn-primary">+ Crear tarea</a>
                <a href="calendario.php" class="btn-secondary-clean">Calendario</a>
                <a href="gantt.php" class="btn-secondary-clean">Gantt</a>
            </div>
        </section>

        <?php if ($totalTareas === 0): ?>

            <section class="content-card">
                <div class="empty-state">
                    <span>🧩</span>
                    <strong>Todavía no hay tareas para mostrar</strong>
                    <p>Creá tareas para empezar a usar el tablero Kanban.</p>
                    <br>
                    <a href="tareas.php" class="btn-primary">Crear primera tarea</a>
                </div>
            </section>

        <?php else: ?>

            <section class="trello-board">

                <?php foreach ($estados as $estado): ?>

                    <div class="trello-list">

                        <div class="trello-list-header">
                            <div>
                                <h2><?php echo htmlspecialchars($estado["nombre"]); ?></h2>
                                <span class="trello-count">
                                    <?php echo count($tareasPorEstado[$estado["id"]]); ?>
                                </span>
                            </div>

                            <span 
                                class="trello-list-color" 
                                style="background: <?php echo htmlspecialchars($estado['color']); ?>"
                            ></span>
                        </div>

                        <div 
                            class="trello-cards"
                            data-estado-id="<?php echo $estado['id']; ?>"
                        >

                            <?php if (count($tareasPorEstado[$estado["id"]]) > 0): ?>

                                <?php foreach ($tareasPorEstado[$estado["id"]] as $tarea): ?>

                                    <?php
                                        $estaVencida = $tarea["fecha_vencimiento"] 
                                            && $tarea["fecha_vencimiento"] < $hoy 
                                            && !in_array($tarea["estado"], ["Finalizada", "Cancelada"]);

                                        $inicialResponsable = "S";
                                        if (!empty($tarea["asignado"])) {
                                            $inicialResponsable = strtoupper(substr($tarea["asignado"], 0, 1));
                                        }
                                    ?>

                                    <article 
                                        class="trello-card"
                                        data-tarea-id="<?php echo $tarea['id']; ?>"
                                        onclick="abrirModalTarea(<?php echo $tarea['id']; ?>)"
                                    >
                                        <div class="trello-labels">
                                            <span 
                                                class="trello-label" 
                                                style="background: <?php echo htmlspecialchars($tarea['prioridad_color']); ?>"
                                            >
                                                <?php echo htmlspecialchars($tarea["prioridad"]); ?>
                                            </span>

                                            <?php if ($estaVencida): ?>
                                                <span class="trello-label trello-label-danger">
                                                    Vencida
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <h3><?php echo htmlspecialchars($tarea["titulo"]); ?></h3>

                                        <div class="trello-card-project">
                                            <?php echo htmlspecialchars($tarea["proyecto"]); ?>
                                        </div>

                                        <div class="trello-progress-mini">
                                            <div style="width: <?php echo (int)$tarea['progreso']; ?>%;"></div>
                                        </div>

                                        <div class="trello-card-footer">
                                            <span class="<?php echo $estaVencida ? 'texto-vencido' : ''; ?>">
                                                📅
                                                <?php echo $tarea["fecha_vencimiento"] ? date("d/m", strtotime($tarea["fecha_vencimiento"])) : "-"; ?>
                                            </span>

                                            <div class="trello-avatar" title="<?php echo $tarea["asignado"] ? htmlspecialchars($tarea["asignado"]) : "Sin asignar"; ?>">
                                                <?php echo htmlspecialchars($inicialResponsable); ?>
                                            </div>
                                        </div>
                                    </article>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                        <a href="tareas.php" class="trello-add-card">
                            + Agregar tarjeta
                        </a>

                    </div>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>

    </main>

</div>

<!-- MODAL ESTILO TRELLO -->
<div id="modalTrello" class="modal-overlay">
    <div class="modal-content trello-modal-content">
        <span class="modal-close" onclick="cerrarModalTrello()">×</span>

        <div class="trello-modal-header">
            <div id="modalLabels" class="trello-labels"></div>

            <h2 id="modalTitulo"></h2>
            <span id="modalSubtitulo"></span>
        </div>

        <div class="trello-modal-grid">
            <div class="trello-modal-main">
                <div class="trello-modal-section">
                    <h3>Descripción</h3>
                    <p id="modalDescripcion"></p>
                </div>

                <div class="trello-modal-section">
                    <h3>Progreso</h3>

                    <div class="kanban-progress-info">
                        <span>Avance actual</span>
                        <strong><span id="modalProgresoTexto"></span>%</strong>
                    </div>

                    <div class="progress-bar">
                        <div id="modalProgresoBarra" class="progress-fill" style="width: 0%;">
                            0%
                        </div>
                    </div>
                </div>
            </div>

            <aside class="trello-modal-side">
                <div class="trello-modal-info">
                    <small>Responsable</small>
                    <strong id="modalResponsable"></strong>
                </div>

                <div class="trello-modal-info">
                    <small>Estado</small>
                    <strong id="modalEstado"></strong>
                </div>

                <div class="trello-modal-info">
                    <small>Inicio</small>
                    <strong id="modalInicio"></strong>
                </div>

                <div class="trello-modal-info">
                    <small>Vencimiento</small>
                    <strong id="modalVencimiento"></strong>
                </div>

                <a id="modalEditar" href="#" class="btn-primary trello-modal-edit">
                    Editar tarea
                </a>
            </aside>
        </div>
    </div>
</div>

<script>
    const tareas = <?php echo $tareasJson; ?>;

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".trello-cards").forEach(function (lista) {
            new Sortable(lista, {
                group: "kanban",
                animation: 180,
                ghostClass: "trello-card-ghost",
                dragClass: "trello-card-dragging",

                onEnd: function (event) {
                    const tareaId = event.item.dataset.tareaId;
                    const nuevoEstadoId = event.to.dataset.estadoId;

                    actualizarEstadoKanban(tareaId, nuevoEstadoId);
                    actualizarContadoresColumnas();
                }
            });
        });
    });

    function actualizarEstadoKanban(tareaId, estadoId) {
        fetch("actualizar_estado_kanban.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                tarea_id: tareaId,
                estado_id: estadoId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                location.reload();
            }
        })
        .catch(error => {
            console.error(error);
            alert("Ocurrió un error al actualizar el estado.");
            location.reload();
        });
    }

    function actualizarContadoresColumnas() {
        document.querySelectorAll(".trello-list").forEach(function (columna) {
            const cantidad = columna.querySelectorAll(".trello-card").length;
            const contador = columna.querySelector(".trello-count");

            if (contador) {
                contador.textContent = cantidad;
            }
        });
    }

    function abrirModalTarea(id) {
        const tarea = tareas.find(item => parseInt(item.id) === parseInt(id));

        if (!tarea) {
            return;
        }

        const labels = document.getElementById("modalLabels");
        labels.innerHTML = "";

        const prioridad = document.createElement("span");
        prioridad.className = "trello-label";
        prioridad.style.background = tarea.prioridad_color;
        prioridad.textContent = tarea.prioridad;
        labels.appendChild(prioridad);

        const estaVencida = tarea.fecha_vencimiento 
            && tarea.fecha_vencimiento < "<?php echo $hoy; ?>" 
            && !["Finalizada", "Cancelada"].includes(tarea.estado);

        if (estaVencida) {
            const vencida = document.createElement("span");
            vencida.className = "trello-label trello-label-danger";
            vencida.textContent = "Vencida";
            labels.appendChild(vencida);
        }

        document.getElementById("modalTitulo").textContent = tarea.titulo;
        document.getElementById("modalSubtitulo").textContent = "En proyecto: " + tarea.proyecto;

        document.getElementById("modalDescripcion").textContent = tarea.descripcion || "Sin descripción.";
        document.getElementById("modalResponsable").textContent = tarea.asignado || "Sin asignar";
        document.getElementById("modalEstado").textContent = tarea.estado;
        document.getElementById("modalInicio").textContent = formatearFechaVisual(tarea.fecha_inicio);
        document.getElementById("modalVencimiento").textContent = formatearFechaVisual(tarea.fecha_vencimiento);

        document.getElementById("modalProgresoTexto").textContent = tarea.progreso;

        const barra = document.getElementById("modalProgresoBarra");
        barra.style.width = tarea.progreso + "%";
        barra.textContent = tarea.progreso + "%";

        document.getElementById("modalEditar").href = "tareas.php?editar=" + tarea.id;

        document.getElementById("modalTrello").style.display = "flex";
    }

    function cerrarModalTrello() {
        document.getElementById("modalTrello").style.display = "none";
    }

    function formatearFechaVisual(fecha) {
        if (!fecha) {
            return "-";
        }

        const partes = fecha.split("-");
        return partes[2] + "/" + partes[1] + "/" + partes[0];
    }

    window.onclick = function(event) {
        const modal = document.getElementById("modalTrello");

        if (event.target === modal) {
            cerrarModalTrello();
        }
    }
</script>

</body>
</html>