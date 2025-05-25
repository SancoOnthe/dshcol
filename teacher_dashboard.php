<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
// Verificar si el usuario está autenticado

// Verificar sesión y rol
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'docente') {
    header('Location: index.php');
    exit;
}

$id_docente = $_SESSION['usuario']['id_usuario'];

// Procesar registro o edición de notas
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'], $_POST['id_curso'], $_POST['nota'], $_POST['periodo'])) {
    $nota = floatval($_POST['nota']);
    $periodo = $_POST['periodo'];
    $id_usuario = intval($_POST['id_usuario']);
    $id_curso = intval($_POST['id_curso']);

    // Verificar si ya existe una nota
    $stmt = $conn->prepare("SELECT id_nota FROM notas WHERE id_usuario=? AND id_curso=? AND periodo=?");
    $stmt->bind_param("iis", $id_usuario, $id_curso, $periodo);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($nota >= 0 && $nota <= 100) {
        if ($res->num_rows > 0) {
            // Actualizar nota
            $stmt = $conn->prepare("UPDATE notas SET nota=? WHERE id_usuario=? AND id_curso=? AND periodo=?");
            $stmt->bind_param("diis", $nota, $id_usuario, $id_curso, $periodo);
            $stmt->execute();
            $msg = "Nota actualizada correctamente.";
        } else {
            // Insertar nota
            $stmt = $conn->prepare("INSERT INTO notas (id_usuario, id_curso, nota, periodo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iids", $id_usuario, $id_curso, $nota, $periodo);
            $stmt->execute();
            $msg = "Nota registrada correctamente.";
        }
    } else {
        $msg = "La nota debe estar entre 0 y 100.";
    }
}

// 1. Cursos que imparte el docente
$stmt = $conn->prepare("
    SELECT c.id_curso, c.nombre_curso, p.nombre_programa
    FROM docentecurso dc
    JOIN cursos c ON dc.id_curso = c.id_curso
    JOIN programas p ON c.id_programa = p.id_programa
    WHERE dc.id_usuario = ?
");
$stmt->bind_param("i", $id_docente);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Docente</title>
    <link rel="stylesheet" href="assets/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin:0; }
        .sidebar {
            width: 220px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            padding-top: 30px;
        }
        .sidebar h2 { text-align: center; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { padding: 15px 20px; }
        .sidebar li a { color: white; text-decoration: none; display: block; }
        .sidebar li a:hover { background: #34495e; }
        .sidebar ul ul { background: #22313a; margin: 0; }
        .sidebar ul ul li { padding: 10px 30px; }
        .main-content {
            margin-left: 240px;
            padding: 30px;
        }
        .curso { border: 1px solid #ccc; margin: 1em 0; padding: 1em; background: #f9f9f9; }
        .materias, .estudiantes, .notas { margin: 1em 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #aaa; padding: 0.5em; text-align: left; }
        .nota-form { display: flex; gap: 6px; align-items: center; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        /* Para la ancla invisible */
        #notas-general { display: block; height: 1px; margin-top: -80px; visibility: hidden; }
    </style>
</head>
<body>
    <?php include 'header_dashboard.php'; ?>
    <div class="sidebar">
        <h2>Docente</h2>
        <ul>
            <li><a href="teacher_dashboard.php">Inicio</a></li>
            <li><a href="#cursos">Mis cursos</a></li>
            <li>
                <a href="#notas-general">Registrar Notas</a>
                <ul>
                    <?php foreach ($cursos as $curso): ?>
                        <li>
                            <a href="#curso-<?php echo $curso['id_curso']; ?>">Notas <?php echo htmlspecialchars($curso['nombre_curso']); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li><a href="perfildoc.php">Mi Perfil</a></li>
            <li><a href="logout.php">Cerrar sesión</a></li>
        </ul>
    </div>
    <div class="main-content">
        <!-- Ancla para "Registrar Notas" -->
        <div id="notas-general"></div>
        <h1>Bienvenido, <?php echo $_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']; ?></h1>
        <h2 id="cursos">Cursos que imparte</h2>

        <?php if ($msg): ?>
            <div class="<?php echo strpos($msg, 'correcta') !== false ? 'success' : 'error'; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if (empty($cursos)): ?>
            <p>No tiene cursos asignados.</p>
        <?php else: ?>
            <?php foreach ($cursos as $curso): ?>
                <div class="curso" id="curso-<?php echo $curso['id_curso']; ?>">
                    <h3><?php echo htmlspecialchars($curso['nombre_curso']); ?> (<?php echo htmlspecialchars($curso['nombre_programa']); ?>)</h3>

                    <!-- Materias del curso que imparte este docente -->
                    <div class="materias">
                        <strong>Materias que imparte:</strong>
                        <ul>
                            <?php
                            $stmt2 = $conn->prepare("SELECT nombre FROM materias WHERE id_curso = ? AND id_docente = ?");
                            $stmt2->bind_param("ii", $curso['id_curso'], $id_docente);
                            $stmt2->execute();
                            $materias = $stmt2->get_result();
                            if ($materias->num_rows == 0) {
                                echo "<li>No tiene materias asignadas en este curso.</li>";
                            } else {
                                while ($mat = $materias->fetch_assoc()) {
                                    echo "<li>" . htmlspecialchars($mat['nombre']) . "</li>";
                                }
                            }
                            ?>
                        </ul>
                    </div>

                    <!-- Estudiantes inscritos en el curso -->
                    <div class="estudiantes">
                        <strong>Estudiantes inscritos y notas:</strong>
                        <table>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Nota 1er Periodo</th>
                                <th>Nota 2do Periodo</th>
                                <th>Registrar / Editar Nota</th>
                            </tr>
                            <?php
                            $stmt3 = $conn->prepare("
                                SELECT u.id_usuario, u.nombre, u.apellido, u.email
                                FROM inscripciones i
                                JOIN usuarios u ON i.id_usuario = u.id_usuario
                                WHERE i.id_curso = ?
                            ");
                            $stmt3->bind_param("i", $curso['id_curso']);
                            $stmt3->execute();
                            $estudiantes = $stmt3->get_result();
                            if ($estudiantes->num_rows == 0) {
                                echo "<tr><td colspan='5'>No hay estudiantes inscritos.</td></tr>";
                            } else {
                                while ($est = $estudiantes->fetch_assoc()) {
                                    // Obtener notas del estudiante en este curso
                                    $stmt4 = $conn->prepare("SELECT periodo, nota FROM notas WHERE id_usuario = ? AND id_curso = ?");
                                    $stmt4->bind_param("ii", $est['id_usuario'], $curso['id_curso']);
                                    $stmt4->execute();
                                    $notas = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
                                    $nota1 = $nota2 = '';
                                    foreach ($notas as $n) {
                                        if ($n['periodo'] == 'primer') $nota1 = $n['nota'];
                                        if ($n['periodo'] == 'segundo') $nota2 = $n['nota'];
                                    }
                                    // Formulario para registrar/editar nota
                                    echo "<tr>
                                        <td>" . htmlspecialchars($est['nombre']) . " " . htmlspecialchars($est['apellido']) . "</td>
                                        <td>" . htmlspecialchars($est['email']) . "</td>
                                        <td>" . htmlspecialchars($nota1) . "</td>
                                        <td>" . htmlspecialchars($nota2) . "</td>
                                        <td>
                                            <form method='post' class='nota-form'>
                                                <input type='hidden' name='id_usuario' value='{$est['id_usuario']}'>
                                                <input type='hidden' name='id_curso' value='{$curso['id_curso']}'>
                                                <select name='periodo'>
                                                    <option value='primer'>1er Periodo</option>
                                                    <option value='segundo'>2do Periodo</option>
                                                </select>
                                                <input type='number' name='nota' step='0.01' min='0' max='100' required placeholder='Nota'>
                                                <button type='submit'>Guardar</button>
                                            </form>
                                        </td>
                                    </tr>";
                                }
                            }
                            ?>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>