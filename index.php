<?php
session_start();
require_once 'includes/auth.php';
$rol = null;
$msg = "";
$tipo_msg = ""; // 'success', 'info', 'error'

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $resultado = login($email, $password);

    if (isset($resultado['ok'])) {
        $_SESSION['usuario'] = $resultado['ok'];
        $rol = $_SESSION['usuario']['tipo_usuario'];
        if ($rol === 'estudiante') {
            header("Location: student_dashboard.php");
        } elseif ($rol === 'docente') {
            header("Location: teacher_dashboard.php");
        } elseif ($rol === 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } elseif (isset($resultado['error'])) {
        $msg = $resultado['error'];
        $tipo_msg = "error";
    } elseif (isset($resultado['info'])) {
        $msg = $resultado['info'];
        $tipo_msg = "info";
    }
} else {
    if (isset($_GET['msg']) && $_GET['msg'] == 'registered') {
        $msg = "Usuario registrado exitosamente. Por favor, inicie sesión.";
        $tipo_msg = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión | Colegio</title>
    <link rel="stylesheet" href="assets/logyreg.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    
</head>
<body>
    <div class="header">
        <img src="assets/logo-colegio.png" alt="Logo Colegio" />
        <span class="header-title">Colegio Quibdó</span>
    </div>
    <div class="login-card">
        <h2>Iniciar Sesión</h2>
        <?php if ($msg) echo "<p style='color:red'>$msg</p>"; ?>
        <form method="POST" autocomplete="off">
            <input type="email" name="email" placeholder="Correo" required maxlength="255" />
            <input type="password" name="password" placeholder="Contraseña" required minlength="8" maxlength="30" />
            <button type="submit">Ingresar</button>
        </form>
        <a href="register.php">¿No tienes cuenta? Regístrate</a>
    </div>
</body>
</html>