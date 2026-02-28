<nav class="navbar navbar-expand-lg border-bottom fixed-top" id="mainNav">
     <div class="navbar-brand container-fluid">
            <button class="navbar-toggler navbar-toggler-right navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target=".navbar-collapse" aria-controls="navbarSupportedContent"
            aria-expanded="false" aria-label="Toggle Navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbarResponsive">
                <ul class="navbar-nav nav nav-container menu">
                    <li class="nav-item"><a class="nav-link" href="index.html">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Convocatoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Ponencias</a></li>
                    <li class="nav-item"><a class="nav-link" href="programa/index_programa.php">Programa</a></li>
                    <li><a class="nav-link" href="login.html">Login</a></li>
                </ul>
            </div>
        <div class="nav-auth">
            <?php if(isset($_SESSION['id_usuario'])): ?>
                <span class="user-name">
                    <?php echo $_SESSION['nombre']; ?>
                </span>
                <a href="../logout.php" class="btn-logout">Cerrar sesión</a>
            <?php else: ?>
                <a href="../login.html" class="btn-login">Iniciar sesión</a>
            <?php endif; ?>
        </div>
    </div>
</nav>