<?php
if(session_status() === PHP_SESSION_NONE){
session_start();
}
?>

<nav class="admin-navbar">

<div class="nav-left">
<span class="logo">UNAM Simposio</span>
</div>

<div class="nav-center">

<a href="../admin/dashboard.php">Dashboard</a>
<a href="../admin/eventos/lista_eventos.php">Eventos</a>
<a href="../admin/actividades/lista_actividades.php">Actividades</a>
<a href="../admin/agenda_admin.php">Agenda</a>

</div>

<div class="nav-right">

<div class="user-menu">

<span class="user-name">
<?php echo $_SESSION['usuario']; ?>
</span>

<div class="dropdown">

<a href="#">Perfil</a>
<a href="../logout.php">Cerrar sesión</a>

</div>

</div>

</div>

</nav>