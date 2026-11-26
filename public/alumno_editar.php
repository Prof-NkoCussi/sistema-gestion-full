<?php
// alumno_editar.php: modificación de un alumno: el id llega por GET (alumno_editar.php?id=3)
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

exigir_rol(['admin', 'preceptor']);

// 1) ¿Qué alumno? leer_id() devuelve un entero o null
$id = leer_id($_GET);
if ($id === null) {
    pagina_error(404, 'Falta el alumno a modificar.');
}

$pdo = conectar();
$cursos = leer_cursos($pdo);

// 2) Se busca en la base. El id viene de la URL: también va preparado
$stmt = $pdo->prepare('SELECT dni, apellido, nombre, curso_id FROM alumnos WHERE id = :id');
$stmt->execute(['id' => $id]);
$alumno = $stmt->fetch();   // fetch() trae UNA fila, o false si no hay ninguna
if ($alumno === false) {
    pagina_error(404, 'Ese alumno no existe.');
}
$errores = [];

// 3) Si llegó el formulario, se valida y se guarda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $alumno = leer_alumno($_POST);
    $errores = validar_alumno($alumno, $cursos);

    if (!$errores) {
        try {
            // El WHERE es lo más importante: sin él, se cambiarían TODOS los alumnos
            $stmt = $pdo->prepare('UPDATE alumnos
                                   SET dni = :dni, apellido = :apellido, nombre = :nombre, curso_id = :curso_id
                                   WHERE id = :id');
            // + une los dos arrays: los datos del alumno y el id
            $stmt->execute($alumno + ['id' => $id]);
            redirigir('alumnos.php?ok=cambio');
        } catch (PDOException $e) {
            // 1062: el DNI nuevo ya lo tiene OTRO alumno
            if (($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $errores['dni'] = 'Ese DNI ya lo tiene otro alumno.';
        }
    }
}

// El formulario se envía a esta misma página, con el mismo id en la URL
$accion = 'alumno_editar.php?id=' . $id;
$boton = 'Guardar cambios';

$titulo = 'Modificar alumno';
require __DIR__ . '/../src/plantillas/header.php';
?>
<h1>Modificar alumno</h1>
<?php require __DIR__ . '/../src/plantillas/form_alumno.php'; ?>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
