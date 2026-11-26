<?php
// config/config.xampp.php: configuración para probar en XAMPP, SOLO en la propia PC
// instalar-xampp.bat la copia como config/config.php (si config.php no existe).
// La contraseña es pública y es la que crea database/demo_xampp.sql: nunca usar en un servidor.

// return devuelve este array a quien haga require del archivo
return [
    'db_host'    => 'localhost',        // MySQL de XAMPP, en la misma PC
    'db_nombre'  => 'escuela',          // la base que crea database/escuela.sql
    'db_usuario' => 'app_escuela',      // usuario con permisos mínimos (no root)
    'db_clave'   => 'Xampp-Demo-2026',  // contraseña de demo, la de demo_xampp.sql
];
