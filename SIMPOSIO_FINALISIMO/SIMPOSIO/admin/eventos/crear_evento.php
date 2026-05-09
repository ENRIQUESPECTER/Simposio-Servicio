<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.php");
    exit();
}
function generar_opciones_horas($seleccionada = null) {
    $opciones = '';
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 30) {
            $hora = sprintf("%02d:%02d", $h, $m);
            $selected = ($seleccionada == $hora) ? 'selected' : '';
            $opciones .= "<option value=\"$hora\" $selected>$hora</option>";
        }
    }
    return $opciones;
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Crear evento</title>
        <link rel="stylesheet" href="../../Css/admin.css">
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #f0f4fc 0%, #e9eef5 100%);
                min-height: 100vh;
                padding: 6.5rem 2rem 3rem;
                color: #1a2c3e;
            }
            .action-btn {
                background: #f8fafd;
                border: 1px solid #e2edf7;
                padding: 0.50rem 0.8rem;
                border-radius: 3rem;
                font-weight: 600;
                color: #2d8bc5;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-size: 0.9rem;
                transition: all 0.25s;
                box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            }
            .action-btn i {
                font-size: 1.1rem;
            }

            .action-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
                transform: translateY(-2px);
            }
            form {
                border: 1px solid #fff;

            }
            .container {
                width: 50rem;
                border: 1px solid #fff;
                box-shadow: 0px 4px 20px rgba(0,0,0,0.92)
            }
            i {
                margin-right: 1rem;
            }
        </style>
    </head>    
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
            <div class="container-fluid">
                <a class="navbar-brand" href="../index.php">
                    <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="../eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                        <li class="nav-item"><a class="nav-link" href="../actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                        <li class="nav-item"><a class="nav-link" href="../trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario']  ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                                </ul>
                            </li>
                    </ul>
                </div>
            </div>
        </nav>

        <h2>Crear Evento</h2> 

        <div class="container">       
            <form action="guardar_evento.php" method="POST">
                
                <label>Título del evento</label>
                <input type="text" name="titulo" required>
                
                <label>Descripción</label>
                <textarea name="descripcion"></textarea>
                
                <label>Fecha</label>
                <input type="date" name="fecha" required>
                
                <div class="mb-3">
                    <label class="form-label">Hora inicio</label>
                    <select name="hora_inicio" class="form-select" required>
                        <?php echo generar_opciones_horas($evento['hora_inicio'] ?? ''); ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Hora fin</label>
                    <select name="hora_fin" class="form-select" required>
                        <?php echo generar_opciones_horas($evento['hora_fin'] ?? ''); ?>
                    </select>
                </div>
                
                <button class="btn btn-primary" type="submit">Registrar Evento</button>
                <a class="btn btn-primary" href="../index.php"><i class="fas fa-undo"></i>Regresar</a>
            </form>
        </div>
        <script>
            document.querySelector('form').addEventListener('submit', function(e) {
                var inicio = document.querySelector('select[name="hora_inicio"]').value;
                var fin = document.querySelector('select[name="hora_fin"]').value;
                if (inicio >= fin) {
                    alert('La hora de inicio debe ser menor que la hora de fin.');
                    e.preventDefault();
                }
            });
        </script>
    </body>
</html>