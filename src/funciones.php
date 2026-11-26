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


// ALUMNOS //

// leer_cursos(): los cursos de la base como [id => nombre]: [1 => '4° 1ª', …]
// Sirven para el <select> del formulario y como lista blanca al validar
function leer_cursos(PDO $pdo): array
{
    // FETCH_KEY_PAIR: la primera columna es la clave y la segunda, el valor
    return $pdo->query('SELECT id, nombre FROM cursos ORDER BY anio, division')->fetchAll(PDO::FETCH_KEY_PAIR);
}

// leer_alumno(): arma el array del alumno con lo que vino del formulario
function leer_alumno(array $origen): array
{
    return [
        // en el DNI se sacan puntos y espacios: 50.987.654 → 50987654
        'dni'      => str_replace(['.', ' '], '', limpiar($origen['dni'] ?? '')),
        'apellido' => limpiar($origen['apellido'] ?? ''),
        'nombre'   => limpiar($origen['nombre'] ?? ''),
        // el curso llega como texto ('2'): leer_id() lo pasa a entero o null
        'curso_id' => leer_id($origen, 'curso_id'),
    ];
}

// validar_alumno(): revisa los datos; devuelve los errores (array vacío = todo bien)
// $cursos: los cursos de la base, [id => nombre] (lista blanca)
function validar_alumno(array $a, array $cursos): array
{
    $errores = [];
    // 7 u 8 números, nada más (los puntos ya los sacó leer_alumno())
    if (preg_match('/^\d{7,8}$/', $a['dni']) !== 1) {
        $errores['dni'] = 'El DNI tiene que tener 7 u 8 números.';
    }
    // mb_strlen(): cuenta letras, no bytes (la ñ y las tildes ocupan 2 bytes)
    if ($a['apellido'] === '' || mb_strlen($a['apellido']) > 60) {
        $errores['apellido'] = 'El apellido es obligatorio (hasta 60 letras).';
    }
    if ($a['nombre'] === '' || mb_strlen($a['nombre']) > 60) {
        $errores['nombre'] = 'El nombre es obligatorio (hasta 60 letras).';
    }
    // El curso tiene que ser uno de la base: un id inventado no pasa
    if ($a['curso_id'] === null || !array_key_exists($a['curso_id'], $cursos)) {
        $errores['curso_id'] = 'Elegí un curso de la lista.';
    }
    return $errores;
}


// NOTAS //

// Los cuatrimestres del año como [número => cómo se muestra]; también es la lista blanca
const CUATRIMESTRES = [1 => '1.er cuatrimestre', 2 => '2.º cuatrimestre'];

// materias_del_curso(): las materias del año de ese curso, como [id => nombre]
// Ejemplo: 6° 1ª da las materias de 6.º año (Programación III, Base de Datos II, Redes II)
function materias_del_curso(PDO $pdo, int $curso_id): array
{
    // JOIN por el año: la materia es del curso si m.anio coincide con c.anio
    $stmt = $pdo->prepare('SELECT m.id, m.nombre FROM materias AS m
                           JOIN cursos AS c ON c.anio = m.anio
                           WHERE c.id = ? ORDER BY m.id');
    $stmt->execute([$curso_id]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// leer_nota(): una nota escrita en el formulario como entero del 1 al 10, o null si no sirve
// '8' da 8; '11', '0', '7.5' o 'ocho' dan null
function leer_nota(string $texto): ?int
{
    $nota = filter_var($texto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
    return $nota === false ? null : $nota;
}


// PROMEDIOS Y CSV //

// leer_promedios(): el promedio de cada alumno en cada cuatrimestre, y el final
// $curso_id: un curso, o null para todos. Devuelve una fila por alumno:
// [7 => ['apellido' => 'Muñoz', 'nombre' => 'Joaquín', 'curso' => '5° 1ª',
//        'promedios' => [1 => 5.0, 2 => 6.3333], 'final' => 5.6667]]
function leer_promedios(PDO $pdo, ?int $curso_id): array
{
    // LEFT JOIN: el alumno sin notas también aparece (con cuatrimestre NULL)
    // GROUP BY alumno y cuatrimestre: AVG() promedia las notas de cada grupo
    // Sin ROUND: se redondea una sola vez, al mostrar (redondear antes de promediar suma error)
    $sql = 'SELECT a.id, a.apellido, a.nombre, c.nombre AS curso,
                   n.cuatrimestre, AVG(n.nota) AS promedio
            FROM alumnos AS a
            JOIN cursos AS c ON c.id = a.curso_id
            LEFT JOIN notas AS n ON n.alumno_id = a.id';
    $datos = [];
    // El filtro se agrega solo si se eligió un curso (y el valor viaja aparte, preparado)
    if ($curso_id !== null) {
        $sql .= ' WHERE a.curso_id = :curso';
        $datos['curso'] = $curso_id;
    }
    $sql .= ' GROUP BY a.id, n.cuatrimestre
              ORDER BY c.anio, a.apellido, a.nombre, n.cuatrimestre';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($datos);

    // Cada alumno llega en hasta 2 filas (una por cuatrimestre): se juntan en una
    $alumnos = [];
    foreach ($stmt as $f) {
        // ??= : la primera vez que aparece el alumno se arma su fila, con los promedios vacíos
        $alumnos[$f['id']] ??= ['apellido' => $f['apellido'], 'nombre' => $f['nombre'],
                                'curso' => $f['curso'], 'promedios' => []];
        // Sin notas, el cuatrimestre llega NULL: no hay promedio para guardar
        if ($f['cuatrimestre'] !== null) {
            $alumnos[$f['id']]['promedios'][$f['cuatrimestre']] = (float) $f['promedio'];
        }
    }
    // & : $a es la fila misma (no una copia), así se le puede agregar el final
    foreach ($alumnos as &$a) {
        $a['final'] = promedio_final($a['promedios']);
    }
    // unset(): corta el vínculo de $a con la última fila, para no pisarla sin querer después
    unset($a);
    return $alumnos;
}

// promedio_final(): el promedio de los cuatrimestres, o null si todavía falta alguno
function promedio_final(array $promedios): ?float
{
    // Sin todos los cuatrimestres, no hay nota final (medio año no es un final)
    if (count($promedios) < count(CUATRIMESTRES)) {
        return null;
    }
    // array_sum(): suma los valores del array
    return array_sum($promedios) / count($promedios);
}

// formato_promedio(): 6.33 se escribe '6,33' (coma decimal, como en Argentina)
function formato_promedio(float $promedio): string
{
    return number_format($promedio, 2, ',', '');
}

// celda_csv(): si un texto empieza con = + - @, Excel lo tomaría como fórmula; se le agrega un apóstrofo
function celda_csv(string $texto): string
{
    return preg_match('/^[=+\-@]/', $texto) === 1 ? "'" . $texto : $texto;
}
