<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Obtener datos del artículo y su PDF
$stmt = $conexion->prepare("
    SELECT a.*, ae.archivo_pdf, ae.id_actividad,
           u.nombre as autor_nombre, u.apellidos as autor_apellidos
    FROM articulo a
    JOIN actividad_evento ae ON a.id_articulo = ae.id_articulo
    JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE a.id_articulo = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$trabajo = $stmt->get_result()->fetch_assoc();

if (!$trabajo || empty($trabajo['archivo_pdf'])) {
    die("Trabajo no encontrado o sin archivo PDF.");
}

$resultado_evaluacion = null;
$error = null;

// Procesar evaluación si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['evaluar'])) {
    $api_key = trim($_POST['api_key']); // Se solicita al usuario ingresar su clave
    if (empty($api_key)) {
        $error = "Debes ingresar tu API Key de Google Gemini.";
    } else {
        $ruta_pdf = '../../' . $trabajo['archivo_pdf']; // ajustar ruta
        $resultado_evaluacion = evaluar_extenso_con_gemini($ruta_pdf, $api_key);
        if (isset($resultado_evaluacion['error'])) {
            $error = $resultado_evaluacion['error'];
            $resultado_evaluacion = null;
        }
    }
}

// Procesar aprobación/rechazo manual (basado en evaluación)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    if (in_array($nuevo_estado, ['aprobado', 'rechazado'])) {
        $stmt = $conexion->prepare("UPDATE articulo SET estado = ? WHERE id_articulo = ?");
        $stmt->bind_param("si", $nuevo_estado, $id);
        $stmt->execute();
        // Si se rechaza, ocultar actividad
        if ($nuevo_estado == 'rechazado') {
            $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 0 WHERE id_articulo = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
        }
        header("Location: index.php?mensaje=actualizado");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluar extenso - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../Css/admin.css">
</head>
<body>
    <?php include '../../includes/navbar_admin.php'; ?>
    <div class="container mt-5">
        <h2>Evaluar extenso: <?php echo htmlspecialchars($trabajo['titulo']); ?></h2>
        <p><strong>Autor:</strong> <?php echo htmlspecialchars($trabajo['autor_nombre'] . ' ' . $trabajo['autor_apellidos']); ?></p>
        <p><strong>Archivo PDF actual:</strong> <a href="../../<?php echo $trabajo['archivo_pdf']; ?>" target="_blank">Ver PDF</a></p>

        <div class="card mb-4">
            <div class="card-header bg-info text-white">Evaluación con IA</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="api_key" class="form-label">API Key de Google Gemini</label>
                        <input type="text" class="form-control" name="api_key" id="api_key" placeholder="Ingresa tu API Key" required>
                        <small class="text-muted">Obtén tu clave en <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a></small>
                    </div>
                    <button type="submit" name="evaluar" class="btn btn-primary">Evaluar con IA</button>
                </form>

                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($resultado_evaluacion): ?>
                    <div class="mt-4">
                        <h4>Resultado de la evaluación:</h4>
                        <ul class="list-group">
                            <li class="list-group-item <?php echo $resultado_evaluacion['cumple'] ? 'list-group-item-success' : 'list-group-item-danger'; ?>">
                                <strong>Cumple requisitos:</strong> <?php echo $resultado_evaluacion['cumple'] ? 'Sí' : 'No'; ?>
                            </li>
                            <li class="list-group-item"><strong>Páginas detectadas:</strong> <?php echo $resultado_evaluacion['paginas'] ?? 'N/A'; ?> (deben ser 8)</li>
                            <li class="list-group-item"><strong>Tipo de letra:</strong> <?php echo $resultado_evaluacion['fuente'] ?? 'N/A'; ?> (debe ser Arial)</li>
                            <li class="list-group-item"><strong>Explicación del proyecto:</strong> <?php echo $resultado_evaluacion['explicacion'] ?? 'N/A'; ?></li>
                            <li class="list-group-item"><strong>Comentarios:</strong> <?php echo nl2br(htmlspecialchars($resultado_evaluacion['comentarios'] ?? '')); ?></li>
                        </ul>

                        <form method="POST" class="mt-3">
                            <input type="hidden" name="nuevo_estado" value="<?php echo $resultado_evaluacion['cumple'] ? 'aprobado' : 'rechazado'; ?>">
                            <button type="submit" name="cambiar_estado" class="btn btn-<?php echo $resultado_evaluacion['cumple'] ? 'success' : 'danger'; ?>">
                                <?php echo $resultado_evaluacion['cumple'] ? 'Aprobar trabajo (cumple requisitos)' : 'Rechazar trabajo (no cumple)'; ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="index.php" class="btn btn-secondary">← Volver al listado</a>
    </div>
</body>
</html>