<?php

$hash = '$2y$10$7bJbzqKgtG5in1IhzGK1r.iGnDwMlrGWvzGp02XnnRXFYJPltfRWe';

var_dump(password_verify("12345", $hash));