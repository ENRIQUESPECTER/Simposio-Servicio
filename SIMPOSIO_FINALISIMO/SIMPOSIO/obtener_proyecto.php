<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_usuario']) || !isset($_GET['id'])) {
    exit;
}

if (!isset($pdo) && isset($conexion)) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$bd;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error de conexión");
    }
}

$id_proyecto = $_GET['id'];

try {
    // Obtener información del proyecto
    $stmt = $pdo->prepare("SELECT * FROM articulo WHERE id_articulo = ?");
    $stmt->execute([$id_proyecto]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proyecto) {
        echo '<div class="alert alert-danger">Proyecto no encontrado</div>';
        exit;
    }
    
    // Obtener autores y coautores
    $stmt = $pdo->prepare("
        SELECT u.nombre, u.apellidos, pa.rol 
        FROM proyecto_alumno pa
        JOIN alumno a ON pa.id_alumno = a.id_alumno
        JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE pa.id_proyecto = ?
        UNION
        SELECT u.nombre, u.apellidos, 'autor' as rol
        FROM proyecto_docente pd
        JOIN docente d ON pd.id_docente = d.id_docente
        JOIN usuario u ON d.id_usuario = u.id_usuario
        WHERE pd.id_proyecto = ?
    ");
    $stmt->execute([$id_proyecto, $id_proyecto]);
    $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ?>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h4><?php echo htmlspecialchars($proyecto['titulo']); ?></h4>
                <hr>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-3 fw-bold">Tipo:</div>
            <div class="col-md-9">
                <?php 
                $tipo = $proyecto['tipo_trabajo'] ?? 'Ponencia';
                echo ucfirst($tipo);
                ?>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-3 fw-bold">Categoría:</div>
            <div class="col-md-9"><?php echo htmlspecialchars($proyecto['categoria'] ?? 'No especificada'); ?></div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-3 fw-bold">Resumen:</div>
            <div class="col-md-9">
                <p class="text-justify"><?php echo nl2br(htmlspecialchars($proyecto['resumen'] ?? 'Sin resumen')); ?></p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-3 fw-bold">Participantes:</div>
            <div class="col-md-9">
                <ul class="list-group">
                    <?php foreach($participantes as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellidos']); ?>
                        <span class="badge bg-<?php echo $p['rol'] == 'autor' ? 'primary' : 'secondary'; ?>">
                            <?php echo ucfirst($p['rol']); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-3 fw-bold">Fecha registro:</div>
            <div class="col-md-9"><?php echo date('d/m/Y H:i', strtotime($proyecto['fecha_registro'] ?? 'now')); ?></div>
        </div>
    </div>
    <?php
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error al cargar los detalles</div>';
}
?>