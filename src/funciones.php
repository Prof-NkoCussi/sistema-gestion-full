<?php
// src/funciones.php: funciones que usan todas las páginas

// PHP no toma la zona horaria de Linux: se la decimos acá, una sola vez
date_default_timezone_set('America/Argentina/Ushuaia');

// e(): escapa un texto antes de mostrarlo en el HTML (protege contra XSS)
// ?string = acepta texto o null (un dato vacío puede llegar de la base como NULL)
function e(?string $texto): string
{
    // ?? '' : si es null, usa texto vacío
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// limpiar(): saca los espacios de los costados; si no es texto, devuelve ''
function limpiar(mixed $valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

// es_actual(): marca en el menú el enlace de la página abierta
// basename($_SERVER['SCRIPT_NAME']) es el nombre del archivo pedido: 'alumnos.php'
function es_actual(string $archivo): string
{
    return basename($_SERVER['SCRIPT_NAME']) === $archivo ? ' aria-current="page"' : '';
}

// redirigir(): le dice al navegador que vaya a otra página y corta el script
// never = esta función nunca vuelve: termina con exit
function redirigir(string $url): never
{
    // Cabecera HTTP "Location": el navegador pide esa URL con GET
    header('Location: ' . $url);
    // Sin exit, PHP seguiría ejecutando el resto de la página
    exit;
}

// leer_id(): toma un id de un array ($_GET o $_POST) y lo devuelve como entero
// $clave: el nombre del dato ('id', 'curso_id'…); devuelve null si no vino o no sirve
function leer_id(array $origen, string $clave = 'id'): ?int
{
    // FILTER_VALIDATE_INT: '7' da 7; '7abc', '' o un array dan false
    $id = filter_var($origen[$clave] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

// pagina_error(): muestra un aviso con el diseño del sistema y corta el script
function pagina_error(int $codigo, string $texto): never
{
    // Código de estado HTTP: 403 = sin permiso, 404 = no existe, 500 = error del servidor
    http_response_code($codigo);
    $titulo = 'Aviso';
    require __DIR__ . '/plantillas/header.php';
    echo '<p class="aviso aviso--error">' . e($texto) . '</p>';
    echo '<p><a href="index.php">Volver al inicio</a></p>';
    require __DIR__ . '/plantillas/footer.php';
    exit;
}


// SESIONES Y ROLES //

// iniciar_sesion(): arranca la sesión (o retoma la que ya tiene el navegador)
function iniciar_sesion(): void
{
    // Si ya está abierta en este pedido, no se abre otra vez
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Nombre propio para la cookie: el sistema de docentes, en el mismo servidor, usa PHPSESSID
    // Con el mismo nombre, las dos aplicaciones compartirían la sesión (y el usuario que entró)
    session_name('ESCUELA');
    // Cómo es la cookie de sesión que se manda al navegador
    session_set_cookie_params([
        'lifetime' => 0,                         // 0 = se borra al cerrar el navegador
        'httponly' => true,                      // JavaScript no la puede leer: un XSS no la roba
        'samesite' => 'Lax',                     // un formulario POST de otro sitio no la lleva
        'secure'   => !empty($_SERVER['HTTPS']), // con HTTPS, solo viaja cifrada
    ]);
    // Modo estricto: PHP no acepta un id de sesión que no haya creado él
    ini_set('session.use_strict_mode', '1');
    // session_start(): lee la cookie, busca el archivo de esa sesión y llena $_SESSION
    session_start();
}

// usuario_actual(): el usuario que entró (id, usuario y rol) o null si nadie entró
function usuario_actual(): ?array
{
    iniciar_sesion();
    return $_SESSION['usuario'] ?? null;
}

// exigir_login(): va al principio de cada página protegida
// Si nadie entró, manda al login y corta el script
function exigir_login(): void
{
    if (usuario_actual() === null) {
        redirigir('login.php');
    }
}

// tiene_rol(): true si el rol del usuario que entró está en la lista
// Ejemplo: tiene_rol(['admin', 'preceptor'])
function tiene_rol(array $roles): bool
{
    // in_array(…, true): compara exacto; sin usuario, el rol es '' y no está en ninguna lista
    return in_array(usuario_actual()['rol'] ?? '', $roles, true);
}

// exigir_rol(): para las páginas que solo pueden usar algunos roles
function exigir_rol(array $roles): void
{
    exigir_login();
    if (!tiene_rol($roles)) {
        // 403 = entraste, pero no tenés permiso para esto
        pagina_error(403, 'No tenés permiso para entrar a esta página.');
    }
}


// TOKEN CSRF //

// token_csrf(): el token secreto de esta sesión; se crea la primera vez que se pide
function token_csrf(): string
{
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) {
        // random_bytes(32): 32 bytes al azar, imposibles de adivinar; bin2hex los escribe con 64 caracteres
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// campo_csrf(): el campo oculto con el token, para poner dentro de cada formulario POST
function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

// verificar_csrf(): va primero en cada POST; si el token no vino o no coincide, corta con 403
function verificar_csrf(): void
{
    iniciar_sesion();
    $enviado = limpiar($_POST['csrf'] ?? '');
    // hash_equals(): compara los dos textos tardando siempre lo mismo (no da pistas)
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        pagina_error(403, 'El formulario no es válido o venció. Volvé a la página y probá otra vez.');
    }
}
