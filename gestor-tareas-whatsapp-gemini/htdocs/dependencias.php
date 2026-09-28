<?php

session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$tituloPagina = "Dependencias";
$subtituloPagina = "Relacioná tareas para que el Gantt muestre una planificación lógica.";

$error = "";
$success = "";

/* TRAER TAREAS */
$sqlTareas = "SELECT 
                t.id,
                t.titulo,
                t.fecha_inicio,
                t.fecha_vencimiento,
                p.nombre AS proyecto,
                e.nombre AS estado
              FROM tareas t
              INNER JOIN proyectos p ON t.proyecto_id = p.id
              INNER JOIN estados_tarea e ON t.estado_id = e.id
              ORDER BY p.nombre ASC, t.titulo ASC";

$stmt = $pdo->query($sqlTareas);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* FUNCIÓN PARA DETECTAR DEPENDENCIAS CIRCULARES */
function existeDependenciaCircular($pdo, $tareaId, $dependeDeTareaId)
{
    $visitadas = [];

    return buscarCircularidad($pdo, $dependeDeTareaId, $tareaId, $visitadas);
}

function buscarCircularidad($pdo, $tareaActual, $tareaObjetivo, &$visitadas)
{
    if ($tareaActual == $tareaObjetivo) {
        return true;
    }

    if (in_array($tareaActual, $visitadas)) {
        return false;
    }

    $visitadas[] = $tareaActual;

    $sql = "SELECT depende_de_tarea_id 
            FROM dependencias_tarea 
            WHERE tarea_id = :tarea_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":tarea_id" => $tareaActual
    ]);

    $dependencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dependencias as $dependencia) {
        if (buscarCircularidad($pdo, $dependencia["depende_de_tarea_id"], $tareaObjetivo, $visitadas)) {
            return true;
        }
    }

    return false;
}

/* CREAR DEPENDENCIA */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["crear_dependencia"])) {
    $tarea_id = $_POST["tarea_id"];
    $depende_de_tarea_id = $_POST["depende_de_tarea_id"];
    $tipo = "finish_to_start";

    if ($tarea_id === "" || $depende_de_tarea_id === "") {
        $error = "Tenés que seleccionar ambas tareas.";

    } elseif ($tarea_id == $depende_de_tarea_id) {
        $error = "Una tarea no puede depender de sí misma.";

    } elseif (existeDependenciaCircular($pdo, $tarea_id, $depende_de_tarea_id)) {
        $error = "No se puede crear esta dependencia porque genera una dependencia circular.";

    } else {
        try {
            $sql = "INSERT INTO dependencias_tarea 
                    (tarea_id, depende_de_tarea_id, tipo)
                    VALUES 
                    (:tarea_id, :depende_de_tarea_id, :tipo)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":tarea_id" => $tarea_id,
                ":depende_de_tarea_id" => $depende_de_tarea_id,
                ":tipo" => $tipo
            ]);

            header("Location: dependencias.php?success=creada");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Esa dependencia ya existe.";
            } else {
                $error = "Error al crear dependencia: " . $e->getMessage();
            }
        }
    }
}

/* ELIMINAR DEPENDENCIA */
if (isset($_GET["eliminar"])) {
    $id = $_GET["eliminar"];

    $sql = "DELETE FROM dependencias_tarea WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":id" => $id]);

    header("Location: dependencias.php?success=eliminada");
    exit;
}

/* MENSAJES */
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creada") {
        $success = "Dependencia creada correctamente.";
    }

    if ($_GET["success"] === "eliminada") {
        $success = "Dependencia eliminada correctamente.";
    }
}

/* LISTAR DEPENDENCIAS */
$sqlDependencias = "SELECT 
                        d.id,
                        d.tipo,
                        d.creado_en,
                        t1.id AS tarea_id,
                        t1.titulo AS tarea,
                        t1.fecha_inicio AS tarea_inicio,
                        t1.fecha_vencimiento AS tarea_vencimiento,
                        p1.nombre AS proyecto_tarea,
                        t2.id AS depende_id,
                        t2.titulo AS depende_de,
                        t2.fecha_inicio AS depende_inicio,
                        t2.fecha_vencimiento AS depende_vencimiento,
                        p2.nombre AS proyecto_depende
                    FROM dependencias_tarea d
                    INNER JOIN tareas t1 ON d.tarea_id = t1.id
                    INNER JOIN proyectos p1 ON t1.proyecto_id = p1.id
                    INNER JOIN tareas t2 ON d.depende_de_tarea_id = t2.id
                    INNER JOIN proyectos p2 ON t2.proyecto_id = p2.id
                    ORDER BY d.creado_en DESC";

$stmt = $pdo->query($sqlDependencias);
$dependencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ESTADÍSTICAS */
$totalTareas = count($tareas);
$totalDependencias = count($dependencias);

$tareasConFechas = 0;

foreach ($tareas as $tarea) {
    if ($tarea["fecha_inicio"] || $tarea["fecha_vencimiento"]) {
        $tareasConFechas++;
    }
}

$tareasSinFechas = $totalTareas - $tareasConFechas;

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

        <div class="cards-dashboard dependencies-summary">
            <div class="card-dashboard">
                <h3><?php echo $totalDependencias; ?></h3>
                <p>Dependencias cargadas</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $totalTareas; ?></h3>
                <p>Tareas disponibles</p>
            </div>

            <div class="card-dashboard">
                <h3><?php echo $tareasConFechas; ?></h3>
                <p>Tareas con fechas</p>
            </div>

            <div class="card-dashboard card-danger">
                <h3><?php echo $tareasSinFechas; ?></h3>
                <p>Tareas sin fechas</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <section class="content-card dependencies-info-card">
            <div class="dependency-explanation">
                <div class="dependency-step">
                    <span>1</span>
                    <strong>Tarea previa</strong>
                    <p>Es la tarea que debe terminar primero.</p>
                </div>

                <div class="dependency-arrow">→</div>

                <div class="dependency-step">
                    <span>2</span>
                    <strong>Tarea dependiente</strong>
                    <p>Es la tarea que depende de la anterior.</p>
                </div>

                <div class="dependency-arrow">→</div>

                <div class="dependency-step">
                    <span>3</span>
                    <strong>Gantt</strong>
                    <p>El sistema dibuja la relación en la línea de tiempo.</p>
                </div>
            </div>
        </section>

        <section class="content-card">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Crear dependencia</h2>
                    <p>
                        Usá esta pantalla para indicar que una tarea no debería comenzar hasta que otra esté realizada.
                    </p>
                </div>

                <div class="form-actions no-margin">
                    <a href="tareas.php" class="btn-primary">Crear tarea</a>
                    <a href="gantt.php" class="btn-secondary-clean">Ver Gantt</a>
                </div>
            </div>

            <?php if (count($tareas) < 2): ?>
                <div class="error">
                    Necesitás tener al menos dos tareas creadas para poder generar una dependencia.
                </div>
            <?php endif; ?>

            <form method="POST" class="form-box dependency-form">

                <div class="form-grid">
                    <div>
                        <label>Tarea dependiente</label>
                        <select name="tarea_id" required>
                            <option value="">Seleccionar tarea que depende de otra</option>

                            <?php foreach ($tareas as $tarea): ?>
                                <option value="<?php echo $tarea['id']; ?>">
                                    <?php echo htmlspecialchars($tarea['proyecto'] . " - " . $tarea['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-help">
                            Ejemplo: “Prueba funcional”.
                        </small>
                    </div>

                    <div>
                        <label>Depende de esta tarea previa</label>
                        <select name="depende_de_tarea_id" required>
                            <option value="">Seleccionar tarea previa</option>

                            <?php foreach ($tareas as $tarea): ?>
                                <option value="<?php echo $tarea['id']; ?>">
                                    <?php echo htmlspecialchars($tarea['proyecto'] . " - " . $tarea['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-help">
                            Ejemplo: “Instalación”.
                        </small>
                    </div>
                </div>

                <div class="dependency-type-box">
                    <span>Tipo de dependencia</span>
                    <strong>Finaliza para iniciar</strong>
                    <small>finish_to_start: la tarea previa debe finalizar para que la siguiente comience.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="crear_dependencia" <?php echo count($tareas) < 2 ? 'disabled' : ''; ?>>
                        Crear dependencia
                    </button>
                </div>

            </form>

        </section>

        <section class="content-card">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Dependencias cargadas</h2>
                    <p>Estas relaciones se reflejan en el diagrama de Gantt.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tarea dependiente</th>
                            <th>Depende de</th>
                            <th>Relación</th>
                            <th>Creada</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($dependencias) > 0): ?>
                            <?php foreach ($dependencias as $dep): ?>
                                <tr>
                                    <td>
                                        <div class="dependency-task-cell">
                                            <span class="dependency-icon dependency-icon-current">B</span>

                                            <div>
                                                <strong><?php echo htmlspecialchars($dep["tarea"]); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($dep["proyecto_tarea"]); ?>
                                                </small>
                                                <br>
                                                <small>
                                                    Inicio:
                                                    <?php echo $dep["tarea_inicio"] ? date("d/m/Y", strtotime($dep["tarea_inicio"])) : "-"; ?>
                                                    |
                                                    Vence:
                                                    <?php echo $dep["tarea_vencimiento"] ? date("d/m/Y", strtotime($dep["tarea_vencimiento"])) : "-"; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="dependency-task-cell">
                                            <span class="dependency-icon dependency-icon-previous">A</span>

                                            <div>
                                                <strong><?php echo htmlspecialchars($dep["depende_de"]); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($dep["proyecto_depende"]); ?>
                                                </small>
                                                <br>
                                                <small>
                                                    Inicio:
                                                    <?php echo $dep["depende_inicio"] ? date("d/m/Y", strtotime($dep["depende_inicio"])) : "-"; ?>
                                                    |
                                                    Vence:
                                                    <?php echo $dep["depende_vencimiento"] ? date("d/m/Y", strtotime($dep["depende_vencimiento"])) : "-"; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="soft-badge">
                                            Finaliza → Inicia
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($dep["tipo"]); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php echo date("d/m/Y", strtotime($dep["creado_en"])); ?>
                                    </td>

                                    <td class="actions-cell">
                                        <a 
                                            class="btn-small btn-danger" 
                                            href="dependencias.php?eliminar=<?php echo $dep['id']; ?>"
                                            onclick="return confirm('¿Seguro que querés eliminar esta dependencia?')"
                                        >
                                            Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <span>🔗</span>
                                        <strong>Todavía no hay dependencias cargadas</strong>
                                        <p>Creá una relación entre tareas para que el Gantt muestre flechas de dependencia.</p>
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