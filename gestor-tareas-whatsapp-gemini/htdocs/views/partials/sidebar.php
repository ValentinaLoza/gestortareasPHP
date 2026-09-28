<?php
$paginaActual = basename($_SERVER["PHP_SELF"]);
$esValentinaLoza = isset($_SESSION["usuario_nombre"])
    && strcasecmp(trim($_SESSION["usuario_nombre"]), "Valentina Loza") === 0;
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">✓</div>
        <div>
            <h2>TaskFlow</h2>
            <span>Gestor de tareas</span>
        </div>
    </div>

    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?php echo $paginaActual === 'dashboard.php' ? 'active' : ''; ?>">
            <span>📊</span>
            Dashboard
        </a>

        <a href="proyectos.php" class="<?php echo $paginaActual === 'proyectos.php' ? 'active' : ''; ?>">
            <span>📁</span>
            Proyectos
        </a>

        <a href="tareas.php" class="<?php echo $paginaActual === 'tareas.php' ? 'active' : ''; ?>">
            <span>✅</span>
            Tareas
        </a>

        <a href="kanban.php" class="<?php echo $paginaActual === 'kanban.php' ? 'active' : ''; ?>">
            <span>🧩</span>
            Kanban
        </a>

        <a href="calendario.php" class="<?php echo $paginaActual === 'calendario.php' ? 'active' : ''; ?>">
            <span>📅</span>
            Calendario
        </a>

        <a href="gantt.php" class="<?php echo $paginaActual === 'gantt.php' ? 'active' : ''; ?>">
            <span>📈</span>
            Gantt
        </a>

        <?php if ($esValentinaLoza): ?>
            <a href="resumen_diario.php" class="<?php echo in_array($paginaActual, ['resumen_diario.php', 'importar_whatsapp.php'], true) ? 'active' : ''; ?>">
                <span>📋</span>
                Resumen diario
            </a>
        <?php endif; ?>

        <a href="dependencias.php" class="<?php echo $paginaActual === 'dependencias.php' ? 'active' : ''; ?>">
            <span>🔗</span>
            Dependencias
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link">
            <span>🚪</span>
            Cerrar sesión
        </a>
    </div>
</aside>
