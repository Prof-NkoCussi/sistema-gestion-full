<?php
// alumno_alta.php: alta de un alumno: formulario + INSERT
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

exigir_rol(['admin', 'preceptor']);
$pdo = conectar();
$cursos = leer_cursos($pdo);

// Con un array vacío, leer_alumno() arma el alumno en blanco para el primer GET
$alumno = leer_alumno([]);
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lo primero de cada POST: sin el token de la sesión, 403
    verificar_csrf();
    $alumno = leer_alumno($_POST);
    $errores = validar_alumno($alumno, $cursos);

    if (!$errores) {
        try {
            // Consulta preparada: los datos viajan aparte, nunca pegados al SQL
            $stmt = $pdo->prepare('INSERT INTO alumnos (dni, apellido, nombre, curso_id)
                                   VALUES (:dni, :apellido, :nombre, :curso_id)');
            $stmt->execute($alumno);
            redirigir('alumnos.php?ok=alta');
        } catch (PDOException $e) {
            // 1062: el DNI ya está (el índice UNIQUE); cualquier otro error sigue de largo
            if (($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $errores['dni'] = 'Ya hay un alumno cargado con ese DNI.';
        }
    }
}

// Lo que necesita form_alumno.php: a dónde se envía y qué dice el botón
$accion = 'alumno_alta.php';
$boton = 'Guardar alumno';

$titulo = 'Nuevo alumno';
require __DIR__ . '/../src/plantillas/header.php';
?>
<h1>Nuevo alumno</h1>
<?php require __DIR__ . '/../src/plantillas/form_alumno.php'; ?>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
