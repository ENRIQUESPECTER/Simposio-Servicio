<?php
session_start();
require "../includes/conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.php");
    exit();
} 

/*contar eventos*/
$sql_eventos = "SELECT COUNT(*) as total FROM evento";
$res_eventos = $conexion->query($sql_eventos);
$eventos = $res_eventos->fetch_assoc()['total'];

/*contar actividades*/
$sql_act = "SELECT COUNT(*) as total FROM actividad_evento";
$res_act = $conexion->query($sql_act);
$actividades = $res_act->fetch_assoc()['total'];

/*contar tipos*/
$sql_tipo = "SELECT COUNT(*) as total FROM tipo_actividad";
$res_tipo = $conexion->query($sql_tipo);
$tipos = $res_tipo->fetch_assoc()['total'];


$stmt = $conexion->prepare("SELECT COUNT(*) as pendientes FROM articulo WHERE estado = 'pendiente'");
$stmt->execute();
$pendientes = $stmt->get_result()->fetch_assoc()['pendientes'];

?>

<!DOCTYPE html>
<html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="../Css/admin.css">
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
            padding: 1.5rem 2rem 3rem;
            color: #1a2c3e;
        }

        /* Contenedor principal */
        .dashboard-wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header elegante */
        .admin-header {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            padding: 1.8rem 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.03), 0 2px 6px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.2s;
        }

        .admin-header h1 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(130deg, #1E3C5C, #2A5F8A);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .admin-header h1 i {
            background: none;
            color: #2a6b9e;
            font-size: 2rem;
            background-clip: unset;
            -webkit-background-clip: unset;
            color: #2c6e9e;
        }

        .user-badge {
            background: #ffffffcc;
            backdrop-filter: blur(4px);
            border-radius: 60px;
            padding: 0.5rem 1.2rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: #1f4e6e;
            border: 1px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .user-badge i {
            font-size: 1.1rem;
            color: #2a7faa;
        }

        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Grid de tarjetas (KPI) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.8rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 1.8rem;
            padding: 1.5rem 1.2rem;
            box-shadow: 0 15px 35px rgba(0, 20, 30, 0.08);
            transition: transform 0.25s ease, box-shadow 0.3s;
            border: 1px solid rgba(166, 194, 220, 0.3);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(2px);
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 25px 40px rgba(0, 30, 50, 0.12);
            border-color: rgba(58, 134, 185, 0.4);
        }

        .card-icon {
            font-size: 2.4rem;
            margin-bottom: 1rem;
            color: #2c7cb6;
            background: #eef4fc;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1.2rem;
        }

        .stat-card h2 {
            font-size: 2.8rem;
            font-weight: 800;
            margin: 0.5rem 0 0.2rem;
            color: #0e2a3b;
            letter-spacing: -1px;
        }

        .stat-card p {
            font-size: 0.95rem;
            font-weight: 500;
            color: #54738f;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-small {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2f8;
            color: #1f6390;
            padding: 0.45rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            margin-top: 0.5rem;
        }

        .btn-small i {
            font-size: 0.7rem;
        }

        .btn-small:hover {
            background: #dce6f0;
            color: #0f4b6e;
            transform: translateX(3px);
        }

        /* Menú de acciones (botones elegantes) */
        .action-menu {
            background: white;
            border-radius: 2rem;
            padding: 1.8rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .action-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1.2rem;
            align-items: center;
        }

        .action-btn {
            background: #f8fafd;
            border: 1px solid #e2edf7;
            padding: 0.85rem 1.8rem;
            border-radius: 3rem;
            font-weight: 600;
            color: #1f5e86;
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
            color: #3683b0;
        }

        .action-btn:hover {
            background: #ffffff;
            border-color: #bcd5e9;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            color: #0f4b70;
        }

        .logout-btn {
            background: #fff4f0;
            border-color: #ffe1d4;
            color: #bc6f4c;
        }

        .logout-btn i {
            color: #c57f5c;
        }

        .logout-btn:hover {
            background: #ffeae2;
            border-color: #e6bfab;
            color: #9b5333;
        }

        /* Trabajos pendientes destacado */
        .highlight-card {
            background: linear-gradient(125deg, #fff9f0, #ffffff);
            border-left: 5px solid #f4b942;
        }

        /* Responsive */
        @media (max-width: 680px) {
            body {
                padding: 1rem;
            }
            .admin-header {
                padding: 1.2rem 1.5rem;
            }
            .admin-header h1 {
                font-size: 1.5rem;
            }
            .stat-card h2 {
                font-size: 2rem;
            }
            .action-btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.8rem;
            }
        }

        /* animación sutil */
        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .admin-header, .action-menu {
            animation: fadeSlideUp 0.4s ease-out forwards;
        }

        .stat-card:nth-child(2) { animation-delay: 0.05s; }
        .stat-card:nth-child(3) { animation-delay: 0.1s; }
        .stat-card:nth-child(4) { animation-delay: 0.15s; }
        .stat-card:nth-child(5) { animation-delay: 0.2s; }

        footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #7c95ad;
        }
    </style>
</head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                        <li class="nav-item"><a class="nav-link" href="actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                        <li class="nav-item"><a class="nav-link" href="trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario'];  ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                                </ul>
                            </li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="header" style="margin-top: 5rem;">
            <h1>Dashboard del Simposio</h1>
            <h2>Administrador: <?php echo $_SESSION['usuario']; ?></h2>
        </div>
        
        <!-- Tarjetas estadísticas (KPI) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="card-icon"><i class="fas fa-calendar-week"></i></div>
            <h2><?php echo isset($eventos) ? $eventos : '0'; ?></h2>
            <p>Eventos activos</p>
            <small style="color:#6c8eaa;">Conferencias y simposios</small>
        </div>
        <div class="stat-card">
            <div class="card-icon"><i class="fas fa-running"></i></div>
            <h2><?php echo isset($actividades) ? $actividades : '0'; ?></h2>
            <p>Actividades</p>
            <small style="color:#6c8eaa;">Charlas, talleres, paneles</small>
        </div>
        <div class="stat-card">
            <div class="card-icon"><i class="fas fa-tags"></i></div>
            <h2><?php echo isset($tipos) ? $tipos : '0'; ?></h2>
            <p>Tipos de actividad</p>
            <small style="color:#6c8eaa;">Categorías dinámicas</small>
        </div>
        <div class="stat-card">
            <div class="card-icon"><i class="fas fa-chair"></i></div>
            <h2>-</h2>
            <p>Espacios libres</p>
            <small style="color:#6c8eaa;">Por definir capacidad</small>
        </div>
        <div class="stat-card highlight-card">
            <div class="card-icon" style="background:#fff0df; color:#e68a2e;"><i class="fas fa-file-alt"></i></div>
            <h2><?php echo isset($pendientes) ? $pendientes : '0'; ?></h2>
            <p>Trabajos pendientes</p>
            <a href="trabajos/pendientes.php" class="btn-small">
                <i class="fas fa-clipboard-list"></i> Revisar artículos
            </a>
        </div>
    </div>

    <!-- Menú de acciones principales -->
    <div class="action-menu">
        <div class="action-grid">
            <a class="action-btn" href="eventos/crear_evento.php">
                <i class="fas fa-plus-circle"></i> Crear Evento
            </a>
            <a class="action-btn" href="eventos/lista_eventos.php">
                <i class="fas fa-list-ul"></i> Gestionar Eventos
            </a>
            <a class="action-btn" href="actividades/lista_actividades.php">
                <i class="fas fa-calendar-alt"></i> Actividades
            </a>
            <a class="action-btn" href="tipos/lista_tipos.php">
                <i class="fas fa-layer-group"></i> Tipos de actividad
            </a>
            <a class="action-btn" href="agenda_admin.php">
                <i class="fas fa-clock"></i> Agenda General
            </a>
            <a class="action-btn logout-btn" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
            </a>
        </div>
    </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>