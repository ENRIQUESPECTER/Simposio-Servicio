<?php
session_start();
include 'conexion.php';

// Verificar si la conexión es con PDO o mysqli
if (!isset($pdo) && isset($conexion)) {
    $pdo = new PDO("mysql:host=$host;dbname=$bd;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $apellidos = $_POST['apellidos'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $tipo_usuario = $_POST['tipo_usuario'] ?? '';
    
    // Validaciones
    if (empty($correo) || empty($password) || empty($confirm_password) || empty($nombre) || empty($tipo_usuario)) {
        $error = 'Por favor complete todos los campos obligatorios';
    } elseif ($password != $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        try {
            // Verificar si el correo ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE correo = ?");
            $stmt->execute([$correo]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'El correo electrónico ya está registrado';
            } else {
                // Insertar usuario
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuario (correo, password, nombre, apellidos, direccion, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$correo, $password_hash, $nombre, $apellidos, $direccion, $tipo_usuario]);
                
                $id_usuario = $pdo->lastInsertId();
                
                // Insertar datos específicos según el tipo
                if ($tipo_usuario == 'alumno') {
                    $matricula = $_POST['matricula'] ?? '';
                    $carrera = $_POST['carrera'] ?? '';
                    $semestre = $_POST['semestre'] ?? null;
                    
                    $stmt2 = $pdo->prepare("INSERT INTO alumno (id_usuario, matricula, carrera, semestre) VALUES (?, ?, ?, ?)");
                    $stmt2->execute([$id_usuario, $matricula, $carrera, $semestre]);
                } elseif ($tipo_usuario == 'docente') {
                    $especialidad = $_POST['especialidad'] ?? '';
                    $grado_academico = $_POST['grado_academico'] ?? '';
                    
                    $stmt2 = $pdo->prepare("INSERT INTO docente (id_usuario, especialidad, grado_academico) VALUES (?, ?, ?)");
                    $stmt2->execute([$id_usuario, $especialidad, $grado_academico]);
                } elseif ($tipo_usuario == 'empresa') {
                    $nombre_empresa = $_POST['nombre_empresa'] ?? '';
                    $sector = $_POST['sector'] ?? '';
                    
                    $stmt2 = $pdo->prepare("INSERT INTO empresa (id_usuario, nombre_empresa, sector) VALUES (?, ?, ?)");
                    $stmt2->execute([$id_usuario, $nombre_empresa, $sector]);
                }
                
                header('Location: login.php?registro=exitoso');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error al registrar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - FES CUAUTITLÁN</title>
    <link rel="stylesheet" href="Css/redunistyle.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Js/funciones.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0a7eeb, #c0902a);
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }
        
        #mainNav {
            background-color: transparent !important;
            padding: 15px 0;
        }
        
        .nav-container {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        
        .menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 30px;
        }
        
        .menu .nav-link {
            color: white !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }
        
        .menu .nav-link:hover {
            color: #ffd700 !important;
            transform: translateY(-2px);
        }
        
        .campus-bar {
            text-align: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .container-registro {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 20px;
        }
        
        .registro-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.6s ease-out;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .input-group input,
        .input-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: #0a7eeb;
            box-shadow: 0 0 0 3px rgba(10, 126, 235, 0.1);
        }
        
        .registro-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #0a7eeb, #c0902a);
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .registro-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(10, 126, 235, 0.4);
        }
        
        .campos-especificos {
            display: none;
            padding: 20px;
            background: rgba(10, 126, 235, 0.05);
            border-radius: 10px;
            margin-top: 10px;
            border: 1px solid rgba(10, 126, 235, 0.2);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .campus-bar {
                font-size: 32px;
                margin-top: 80px;
            }
            
            .menu {
                gap: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom fixed-top" id="mainNav">
        <div class="navbar-brand container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target=".navbar-collapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse">
                <ul class="navbar-nav nav nav-container menu">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="convocatoria.php">Convocatoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponencias.php">Ponencias</a></li>
                    <li class="nav-item"><a class="nav-link" href="programa/index_programa.php">Eventos</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="campus-bar">
        FES CUAUTITLÁN
    </div>

    <div class="container-registro">
        <div class="registro-card">
            <h2 class="text-center mb-4">Registro de Usuario</h2>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="formRegistro">
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" required value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <label>Apellidos</label>
                            <input type="text" name="apellidos" value="<?php echo isset($_POST['apellidos']) ? htmlspecialchars($_POST['apellidos']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="input-group">
                    <label>Correo Electrónico *</label>
                    <input type="email" name="correo" required value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <label>Contraseña *</label>
                            <input type="password" name="password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <label>Confirmar Contraseña *</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                
                <div class="input-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>">
                </div>
                
                <div class="input-group">
                    <label>Tipo de Usuario *</label>
                    <select name="tipo_usuario" id="tipo_usuario" required onchange="mostrarCamposEspecificos()">
                        <option value="">Seleccione...</option>
                        <option value="alumno" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] == 'alumno') ? 'selected' : ''; ?>>Alumno</option>
                        <option value="docente" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] == 'docente') ? 'selected' : ''; ?>>Docente</option>
                        <option value="empresa" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] == 'empresa') ? 'selected' : ''; ?>>Empresa</option>
                    </select>
                </div>
                
                <!-- Campos para Alumno -->
                <div id="campos_alumno" class="campos-especificos">
                    <h5>Datos Académicos</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="input-group">
                                <label>Matrícula</label>
                                <input type="text" name="matricula" value="<?php echo isset($_POST['matricula']) ? htmlspecialchars($_POST['matricula']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <label>Carrera</label>
                                <input type="text" name="carrera" value="<?php echo isset($_POST['carrera']) ? htmlspecialchars($_POST['carrera']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <label>Semestre</label>
                                <input type="number" name="semestre" min="1" max="12" value="<?php echo isset($_POST['semestre']) ? htmlspecialchars($_POST['semestre']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Campos para Docente -->
                <div id="campos_docente" class="campos-especificos">
                    <h5>Datos Profesionales</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group">
                                <label>Especialidad</label>
                                <input type="text" name="especialidad" value="<?php echo isset($_POST['especialidad']) ? htmlspecialchars($_POST['especialidad']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label>Grado Académico</label>
                                <select name="grado_academico">
                                    <option value="">Seleccione...</option>
                                    <option value="Licenciatura" <?php echo (isset($_POST['grado_academico']) && $_POST['grado_academico'] == 'Licenciatura') ? 'selected' : ''; ?>>Licenciatura</option>
                                    <option value="Maestría" <?php echo (isset($_POST['grado_academico']) && $_POST['grado_academico'] == 'Maestría') ? 'selected' : ''; ?>>Maestría</option>
                                    <option value="Doctorado" <?php echo (isset($_POST['grado_academico']) && $_POST['grado_academico'] == 'Doctorado') ? 'selected' : ''; ?>>Doctorado</option>
                                    <option value="Posdoctorado" <?php echo (isset($_POST['grado_academico']) && $_POST['grado_academico'] == 'Posdoctorado') ? 'selected' : ''; ?>>Posdoctorado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Campos para Empresa -->
                <div id="campos_empresa" class="campos-especificos">
                    <h5>Datos de la Empresa</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group">
                                <label>Nombre de la Empresa</label>
                                <input type="text" name="nombre_empresa" value="<?php echo isset($_POST['nombre_empresa']) ? htmlspecialchars($_POST['nombre_empresa']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label>Sector</label>
                                <input type="text" name="sector" value="<?php echo isset($_POST['sector']) ? htmlspecialchars($_POST['sector']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="registro-btn mt-3">Registrarse</button>
            </form>
            
            <div class="text-center mt-3">
                <a href="login.php" style="color: #0a7eeb;">¿Ya tienes cuenta? Inicia sesión</a>
            </div>
        </div>
    </div>

    <script>
        function mostrarCamposEspecificos() {
            var tipo = document.getElementById('tipo_usuario').value;
            document.getElementById('campos_alumno').style.display = tipo === 'alumno' ? 'block' : 'none';
            document.getElementById('campos_docente').style.display = tipo === 'docente' ? 'block' : 'none';
            document.getElementById('campos_empresa').style.display = tipo === 'empresa' ? 'block' : 'none';
        }
        
        // Mostrar campos si ya había un tipo seleccionado (después de error)
        window.onload = function() {
            mostrarCamposEspecificos();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>