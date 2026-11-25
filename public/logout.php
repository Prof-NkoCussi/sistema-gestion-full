<?php
// logout.php: salir del sistema (solo con un formulario POST)
require __DIR__ . '/../src/funciones.php';

iniciar_sesion();
// Un enlace o una imagen de otra página no pueden cerrar la sesión: hace falta un POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}
verificar_csrf();

// 1) Vaciar los datos de la sesión
$_SESSION = [];
// 2) Borrar la cookie del navegador: la misma cookie, con una fecha de vencimiento ya pasada
$p = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => $p['path'],
    'httponly' => $p['httponly'],
    'samesite' => $p['samesite'],
    'secure'   => $p['secure'],
]);
// 3) Borrar el archivo de la sesión en el servidor
session_destroy();

redirigir('login.php?salio=1');
