<?php
session_start();
session_unset();
session_destroy();

// volver al index de bdga
header("Location: ../bdga/index.php");
exit;