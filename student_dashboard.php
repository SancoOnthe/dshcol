<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Verifica la sesión y el tipo de usuario estudiante
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'estudiante') {
    header('Location: index.php');
    exit;
}

$id_estudiante = $_SESSION['usuario']['id_usuario'];
$msg = "";

// Procesar actualización de perfil (correo y teléfono)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $stmt = $conn->prepare("UPDATE usuarios SET email=?, telefono=? WHERE id_usuario=?");
    $stmt->bind_param("ssi", $email, $telefono, $id_estudiante);
    if ($stmt->execute()) {
        $msg = "<div class='success'>Datos actualizados correctamente.</div>";
        $_SESSION['usuario']['email'] = $email;
    } else {
        $msg = "<div class='error'>Ocurrió un error al actualizar los datos.</div>";
    }
}

// Obtener información de perfil
$stmt = $conn->prepare("SELECT nombre, apellido, documento_identidad, email, telefono, direccion, estado, fecha_registro FROM usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param("i", $id_estudiante);
$stmt->execute();
$estudiante = $stmt->get_result()->fetch_assoc();

// Obtener notas
$notas = [];
$stmtN = $conn->prepare("
    SELECT c.nombre_curso AS curso, n.nota, n.periodo
    FROM notas n
    JOIN cursos c ON n.id_curso = c.id_curso
    WHERE n.id_usuario = ?
");
$stmtN->bind_param("i", $id_estudiante);
$stmtN->execute();
$resNotas = $stmtN->get_result();
while ($row = $resNotas->fetch_assoc()) $notas[] = $row;

// Obtener cursos inscritos
$cursos = [];
$stmtC = $conn->prepare("
    SELECT c.nombre_curso, c.id_curso
    FROM cursos c
    JOIN inscripciones i ON i.id_curso = c.id_curso
    WHERE i.id_usuario = ?
");
$stmtC->bind_param("i", $id_estudiante);
$stmtC->execute();
$resCursos = $stmtC->get_result();
while ($row = $resCursos->fetch_assoc()) $cursos[] = $row;

// Obtener eventos próximos
$eventos = [];
$stmtE = $conn->prepare("
    SELECT titulo_evento, descripcion_evento, fecha_evento, lugar_evento
    FROM eventos
    WHERE fecha_evento >= CURDATE()
    ORDER BY fecha_evento ASC
    LIMIT 10
");
$stmtE->execute();
$resEventos = $stmtE->get_result();
while ($row = $resEventos->fetch_assoc()) $eventos[] = $row;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Estudiante | Colegio</title>
    <link rel="stylesheet" href="assets/style.css"/>
    <style>
        .perfil-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 18px;
        }
        .perfil-header img {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            border: 3px solid #2563eb33;
            background: #f1f5f9;
            object-fit: cover;
        }
        .perfil-header .nombre {
            font-size: 1.5em;
            font-weight: bold;
            color: #2563eb;
            letter-spacing: 1px;
        }
        .perfil-form {
            max-width: 430px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .perfil-form label {
            margin-bottom: 2px;
            font-weight: 600;
        }
        .perfil-form input {
            width: 100%;
            margin-bottom: 8px;
        }
        .readonly-info {
            background: #f1f5f9;
            border-radius: 7px;
            padding: 10px 16px;
            margin-bottom: 13px;
            color: #555;
        }
    </style>
    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
            document.getElementById(sectionId).style.display = 'block';
            document.querySelectorAll('.sidebar ul li').forEach(li => li.classList.remove('active'));
            document.getElementById('menu-' + sectionId).classList.add('active');
        }
        window.onload = function() { showSection('perfil'); };
    </script>
</head>
<body>
        <?php include 'header_dashboard.php'; ?>
    <nav class="sidebar">
        <div class="logo-section">
            <img src="assets/logo-colegio.png" alt="Logo Colegio" />
            <span class="school-name">Colegio San José</span>
        </div>
        <ul>
            <li id="menu-perfil" onclick="showSection('perfil')">Perfil</li>
            <li id="menu-notas" onclick="showSection('notas')">Notas</li>
            <li id="menu-cursos" onclick="showSection('cursos')">Cursos inscritos</li>
            <li id="menu-eventos" onclick="showSection('eventos')">Eventos</li>
        </ul>
        <a href="logout.php" class="logout">Cerrar sesión</a>
    </nav>
    <main class="main-content">
        <!-- Perfil -->
        <section id="perfil" class="section" style="display:none;">
            <h2>Mi perfil</h2>
            <div class="perfil-header">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($estudiante['nombre'].' '.$estudiante['apellido']); ?>&background=dbeafe&color=2563eb&rounded=true&size=96"
                alt="Avatar">
                <span class="nombre"><?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']); ?></span>
            </div>
            <?php if ($msg) echo $msg; ?>
            <form method="post" class="perfil-form" autocomplete="off">
                <input type="hidden" name="update_profile" value="1">
                <label for="documento_identidad">Documento de identidad</label>
                <input type="text" id="documento_identidad" value="<?php echo htmlspecialchars($estudiante['documento_identidad']); ?>" readonly>

                <label for="email">Correo electrónico</label>
                <input type="email" required name="email" id="email" value="<?php echo htmlspecialchars($estudiante['email']); ?>">

                <label for="telefono">Teléfono</label>
                <input type="text" name="telefono" id="telefono" value="<?php echo htmlspecialchars($estudiante['telefono'] ?? ''); ?>">

                <div class="readonly-info" style="margin-top:10px;">
                    <strong>Dirección:</strong> <?php echo htmlspecialchars($estudiante['direccion'] ?? ''); ?><br>
                    <strong>Estado:</strong> <?php echo htmlspecialchars($estudiante['estado']); ?><br>
                    <strong>Fecha de registro:</strong> <?php echo htmlspecialchars($estudiante['fecha_registro']); ?>
                </div>
                <button type="submit">Actualizar datos</button>
            </form>
        </section>
        <!-- Notas -->
        <section id="notas" class="section" style="display:none;">
            <h2>Mis notas</h2>
            <?php if (empty($notas)): ?>
                <p>No tienes notas registradas.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Nota</th>
                            <th>Periodo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notas as $n): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($n['curso']); ?></td>
                            <td><?php echo htmlspecialchars($n['nota']); ?></td>
                            <td><?php echo htmlspecialchars($n['periodo']); ?></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </section>
        <!-- Cursos inscritos -->
        <section id="cursos" class="section" style="display:none;">
            <h2>Mis cursos inscritos</h2>
            <?php if (empty($cursos)): ?>
                <p>No tienes cursos inscritos actualmente.</p>
            <?php else: ?>
                <ul style="padding-left:0;">
                    <?php foreach ($cursos as $c): ?>
                        <li style="margin-bottom:13px;list-style:none;background:#f9fafb;padding:14px 18px;border-radius:7px;box-shadow:0 1px 6px #2563eb11;">
                            <strong><?php echo htmlspecialchars($c['nombre_curso']); ?></strong>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
        <!-- Eventos -->
        <section id="eventos" class="section" style="display:none;">
            <h2>Próximos eventos</h2>
            <?php if (empty($eventos)): ?>
                <p>No hay eventos próximos.</p>
            <?php else: ?>
                <ul style="padding-left:0;">
                    <?php foreach ($eventos as $e): ?>
                        <li style="margin-bottom:13px;list-style:none;background:#fef9c3;padding:14px 18px;border-radius:7px;box-shadow:0 1px 6px #2563eb0d;">
                            <strong><?php echo htmlspecialchars($e['titulo_evento']); ?></strong><br>
                            <?php echo htmlspecialchars($e['descripcion_evento']); ?><br>
                            <span>Fecha: <?php echo htmlspecialchars($e['fecha_evento']); ?></span><br>
                            <span>Lugar: <?php echo htmlspecialchars($e['lugar_evento']); ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </main>
</body>
</html>