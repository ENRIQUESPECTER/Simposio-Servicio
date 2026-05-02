<?php
session_start();
require "../../includes/conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
$sql="SELECT * FROM evento ORDER BY fecha DESC";
$result=$conexion->query($sql);

?>

<!DOCTYPE html>
<html>
    <head>        
        <title>Eventos</title>
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="../../Css/admin.css">
        <link rel="stylesheet" href="Css/interfaz_usuario.css">
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
            padding: 3.5rem 2rem 3rem;
            color: #1a2c3e;
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
        <div class="container">     
            <h2>Eventos registrados</h2>        
            <table border="1">            
                <tr>                
                    <th>Nombre</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Acciones</th>                   
                </tr>            
                <?php while($evento=$result->fetch_assoc()): ?>
                    
                    <tr>
                        <td><?php echo $evento['titulo']; ?></td>
                        <td><?php echo $evento['fecha']; ?></td>
                        <td>
                            <?php echo $evento['hora_inicio']." - ".$evento['hora_fin']; ?>
                        </td>

                        <td>
                            <a class="" href="editar_evento.php?id_evento=<?php echo $evento['id_evento']; ?>">
                                Editar
                            </a>
                            <a class="" href="eliminar_evento.php?id_evento=<?php echo $evento['id_evento']; ?>"onclick="return confirm('¿Eliminar esta actividad?');">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <a class="btn" href="../index.php" style="">Regresar</a>
            </table>
        </div>
    </body>
</html>