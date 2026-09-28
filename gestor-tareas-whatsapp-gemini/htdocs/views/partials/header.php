<header class="topbar">
    <div>
        <h1><?php echo htmlspecialchars($tituloPagina ?? "Panel"); ?></h1>
        <p><?php echo htmlspecialchars($subtituloPagina ?? "Resumen general del sistema"); ?></p>
    </div>

    <div class="topbar-user">
        <div class="user-info">
            <strong><?php echo htmlspecialchars($_SESSION["usuario_nombre"] ?? "Usuario"); ?></strong>
            <span><?php echo htmlspecialchars($_SESSION["usuario_rol"] ?? ""); ?></span>
        </div>

        <div class="user-avatar">
            <?php 
                $nombre = $_SESSION["usuario_nombre"] ?? "U";
                echo strtoupper(substr($nombre, 0, 1)); 
            ?>
        </div>
    </div>
</header>