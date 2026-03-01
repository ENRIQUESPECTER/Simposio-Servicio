<?php
session_start();
session_destroy();
header("Location: programa/index_programa.php");
exit();