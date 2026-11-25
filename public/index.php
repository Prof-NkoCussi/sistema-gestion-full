<?php
// index.php: inicio: cada rol va a su página principal
require __DIR__ . '/../src/funciones.php';

// Sin login, al login (exigir_login() corta acá)
exigir_login();

// match: elige el destino según el rol; el docente carga notas y los demás ven alumnos
redirigir(match (usuario_actual()['rol']) {
    'docente' => 'notas.php',
    default   => 'alumnos.php',
});
