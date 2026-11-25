<?php
// config/config.ejemplo.php: modelo de la configuración
// En el servidor se copia como config.php y ahí se escribe la contraseña real:
//   cp config/config.ejemplo.php config/config.php
// config/config.php está en .gitignore: la contraseña nunca llega a GitHub.

// return devuelve este array a quien haga require del archivo
return [
    'db_host'    => 'localhost',    // MySQL está en el mismo servidor que Apache
    'db_nombre'  => 'escuela',      // la base de datos del sistema
    'db_usuario' => 'app_escuela',  // usuario con permisos mínimos (nunca root)
    'db_clave'   => 'CAMBIAR',      // en config.php va la contraseña real
];
