<?php
session_start();
require "includes/conexion.php";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $tipo = $_POST['tipo_usuario'];
    $correo = $_POST['correo'];
    $password = $_POST['password'];
    $sql = "SELECT * FROM usuario 
        WHERE correo = ? 
        AND tipo_usuario = ? 
        LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $correo, $tipo);
    $stmt->execute();

    $resultado = $stmt->get_result();
    try{
        if (empty($correo) || empty($password) || empty($tipo)) {
            $error = 'Por favor complete todos los campos obligatorios';
        } elseif($resultado->num_rows === 1){

            $usuario = $resultado->fetch_assoc();

            if(password_verify($password, $usuario['password'])){

                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

                // Redirección inteligente
                header("Location: index.php");
                exit();

            } else {
                $error = "Contraseña incorrecta";
            }
        }
        else {
            $error = 'Correo electrónico, Contraseña o Tipo de Usuario incorrectos';
        } 
    } catch (PDOException $e) {
        $error = 'Error al iniciar sesión: ' . $e->getMessage();
    }
}
// Verificar si hay mensaje de registro exitoso
if (isset($_GET['registro']) && $_GET['registro'] == 'exitoso') {
    $success = 'Registro exitoso. Ahora puede iniciar sesión.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proyecto Seguridad</title>
    <link rel="stylesheet" href="Css/redunistyle.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Js/funciones.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            height: 100vh;
            background: linear-gradient(135deg, #0a7eeb, #c0902a);
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }
        .login-btn {
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
            margin-top: 10px;   
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(10, 126, 235, 0.4);
        }
        .campus-bar {
            text-align: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
            margin-top: 79px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 20px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 40px;
            max-width: 400px;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        .input-group select:hover,
        .input-group input:hover {
            border-color: #c0902a;
        }
        .menu .nav-link:hover {
            color: #ffd700 !important;
            transform: translateY(-2px);
        }
        /* Animación de entrada */
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
        
        .login-card {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Iconos decorativos (opcional) */
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            right: 15px;
            top: 36px;
            color: #999;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom fixed-top" id="mainNav">
        <div class="navbar-brand container-fluid">
            <button class="navbar-toggler navbar-toggler-right navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target=".navbar-collapse" aria-controls="navbarSupportedContent"
            aria-expanded="false" aria-label="Toggle Navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbarResponsive">
                <ul class="navbar-nav nav nav-container menu">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="convocatoria.php">Convocatoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponencias.php">Ponencias</a></li>
                    <li class="nav-item"><a class="nav-link" href="programa/index_programa.php">Eventos</a></li>
                    <li><a class="nav-link" href="registro.php">Registrate</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="campus-bar">
        FES CUAUTITLÁN
    </div>

    <div class="container justify-content-center">
        <div class="login-card">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <div class="input-group">
                    <label for="tipo_usuario">¿Cómo desea Ingresar?</label>
                    <select name="tipo_usuario" id="tipo_usuario" required>
                        <option value="alumno">Alumno</option>
                        <option value="docente">Docente</option>
                        <option value="empresa">Empresa</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="correo">Correo Electronico</label>
                    <input type="email" name="correo" id="email" required placeholder="Correo electronico">
                    <i class="bi bi-envelope-fill"></i>
                </div>
                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required placeholder="Contraseña">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <button type="submit" class="login-btn">Iniciar Sesión</button>
                <button type="button" class="login-btn"><a href="registro.php" style="text-decoration: none; color: rgb(253, 253, 253);">Registrarse</a></button>
            </form>
        </div>
    </div>
</body>
</html>
