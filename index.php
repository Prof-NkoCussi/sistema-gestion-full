<?php
// index.php (en la raíz): en XAMPP se publica toda la carpeta; esto manda a public/
// En el servidor LAMP no se usa: Apache publica solo public/ (config/escuela.conf)

// Location: el navegador pide public/, donde está el sistema
header('Location: public/');
exit;
