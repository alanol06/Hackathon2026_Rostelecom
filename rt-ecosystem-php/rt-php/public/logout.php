<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_boot();
$_SESSION = [];
session_destroy();
redirect('/login.php');
