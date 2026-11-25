<?php
// scripts/crear_usuario.php: crea un usuario del sistema desde la terminal del servidor
// Uso:  php scripts/crear_usuario.php USUARIO ROL      (ROL: admin, preceptor o docente)
// La contraseña se pide dos veces y no se ve mientras se escribe.

// PHP_SAPI vale 'cli' en la terminal. scripts/ no tiene URL; igual, si llegara un navegador: 404
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

// pedir_clave(): muestra el texto y lee una línea sin que se vea (stty -echo apaga el eco)
function pedir_clave(string $texto): string
{
    echo $texto;
    shell_exec('stty -echo');
    $clave = rtrim((string) fgets(STDIN), "\r\n");
    shell_exec('stty echo');
    echo "\n";
    return $clave;
}

// $argv: lo que se escribió después de "php": [0] el archivo, [1] el usuario, [2] el rol
$usuario = $argv[1] ?? '';
$rol = $argv[2] ?? '';
// Usuario: de 3 a 50 letras minúsculas, números, punto o guion bajo; rol: lista blanca
if (preg_match('/^[a-z0-9._]{3,50}$/', $usuario) !== 1 || !in_array($rol, ['admin', 'preceptor', 'docente'], true)) {
    fwrite(STDERR, "Uso: php scripts/crear_usuario.php USUARIO admin|preceptor|docente\n");
    exit(1);
}

$clave = pedir_clave('Contraseña (12 caracteres o más): ');
if (mb_strlen($clave) < 12) {
    fwrite(STDERR, "La contraseña es demasiado corta.\n");
    exit(1);
}
if (pedir_clave('Repetila: ') !== $clave) {
    fwrite(STDERR, "Las contraseñas no coinciden.\n");
    exit(1);
}

// password_hash(): sal al azar + bcrypt; en la base queda el hash, nunca la contraseña
$hash = password_hash($clave, PASSWORD_DEFAULT);

try {
    $stmt = conectar()->prepare('INSERT INTO usuarios (usuario, password_hash, rol) VALUES (:usuario, :hash, :rol)');
    $stmt->execute(['usuario' => $usuario, 'hash' => $hash, 'rol' => $rol]);
} catch (PDOException $e) {
    // 1062: el índice UNIQUE de usuarios.usuario
    if (($e->errorInfo[1] ?? 0) === 1062) {
        fwrite(STDERR, "Ya existe un usuario con ese nombre.\n");
        exit(1);
    }
    throw $e;
}
echo "Usuario creado: $usuario ($rol)\n";
