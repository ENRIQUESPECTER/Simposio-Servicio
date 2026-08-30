<?php
session_start();
session_destroy();
header("Location: simposio.php");
exit();