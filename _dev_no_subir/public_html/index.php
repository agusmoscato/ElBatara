<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (estaLogueado()) {
    redirigir('dashboard.php');
} else {
    redirigir('login.php');
}
