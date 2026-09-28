<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Gestor de Tareas</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

    <div class="login-container">
        <h1>Gestor de Tareas</h1>
        <h2>Iniciar sesión</h2>

        <?php if (!empty($error)): ?>
            <div class="error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <label>Email</label>
            <input type="email" name="email" required>

            <label>Contraseña</label>
            <input type="password" name="password" required>

            <div class="login-button-container">
    <button type="submit">Ingresar</button>
</div>
            
         
        </form>
<div>
<p class="login-notice">
    Sistema interno de gestión de tareas AVEX. Acceso exclusivo para usuarios autorizados.
</p>
    </div>



</body>
</html>