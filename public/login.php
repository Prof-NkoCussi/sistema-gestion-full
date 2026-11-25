<?php
// login.php: entrar al sistema con usuario y contraseña
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

iniciar_sesion();
// Si ya entró, el formulario no hace falta
if (usuario_actual() !== null) {
    redirigir('index.php');
}

$usuario = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $usuario = limpiar($_POST['usuario'] ?? '');
    // La contraseña no pasa por limpiar(): un espacio puede ser parte de ella
    $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';

    // Se busca al usuario con una consulta preparada; se trae el hash, nunca una contraseña
    $stmt = conectar()->prepare('SELECT id, usuario, password_hash, rol FROM usuarios WHERE usuario = :usuario');
    $stmt->execute(['usuario' => $usuario]);
    $fila = $stmt->fetch();

    // password_verify(): calcula el hash de lo que se escribió, con la sal guardada, y lo compara
    if ($fila !== false && password_verify($clave, $fila['password_hash'])) {
        // Id de sesión nuevo al entrar: el que se tenía antes del login deja de servir
        session_regenerate_id(true);
        // En la sesión va lo justo para saber quién es y qué puede hacer
        $_SESSION['usuario'] = [
            'id'      => $fila['id'],
            'usuario' => $fila['usuario'],
            'rol'     => $fila['rol'],
        ];
        redirigir('index.php');
    }
    // El mismo mensaje si el usuario no existe o si la contraseña está mal: no se dan pistas
    $error = 'Usuario o contraseña incorrectos.';
}

$titulo = 'Entrar';
require __DIR__ . '/../src/plantillas/header.php';
?>
<h1>Entrar al sistema</h1>

<?php if (isset($_GET['salio'])): ?>
    <p class="aviso aviso--ok" role="status">Saliste del sistema.</p>
<?php endif; ?>
<?php if ($error !== null): ?>
    <p class="aviso aviso--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="tarjeta formulario" action="login.php" method="post">
    <?= campo_csrf() ?>
    <div class="campo">
        <label for="usuario">Usuario</label>
        <!-- autocomplete: el navegador puede ofrecer el usuario guardado -->
        <input id="usuario" name="usuario" type="text" maxlength="50" autocomplete="username"
               value="<?= e($usuario) ?>" required>
    </div>
    <div class="campo">
        <label for="clave">Contraseña</label>
        <!-- type="password": se ven puntos; la contraseña nunca vuelve a mostrarse en el value -->
        <input id="clave" name="clave" type="password" autocomplete="current-password" required>
    </div>
    <div class="botones">
        <button class="boton" type="submit">Entrar</button>
    </div>
</form>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
