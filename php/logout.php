<?php
require_once __DIR__ . '/../config/pengaturan.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parameter_cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $parameter_cookie['path'], $parameter_cookie['domain'],
        $parameter_cookie['secure'], $parameter_cookie['httponly']
    );
}

session_destroy();
header('Location: ../login.php');
exit;
