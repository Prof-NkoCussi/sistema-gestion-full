<?php
// alumno_baja.php: baja de un alumno en dos pasos:
//   GET  alumno_baja.php?id=7 → muestra quién es y pide confirmación (no borra nada)
//   POST alumno_baja.php?id=7 → recién ahí hace el DELETE
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

// La baja es solo para el admin: sin login, al login; con otro rol, 403
exigir_rol(['admin']);

$id = leer_id($_GET);
if ($id === null) {
    pagina_error(404, 'Falta el alumno a dar de baja.');
}

$pdo = conectar();

// Se busca al alumno, para mostrar su nombre en la confirmación
$stmt = $pdo->prepare('SELECT a.dni, a.apellido, a.nombre, c.nombre AS curso
                       FROM alumnos AS a JOIN cursos AS c ON c.id = a.curso_id
                       WHERE a.id = :id');
$stmt->execute(['id' => $id]);
$alumno = $stmt->fetch();
if ($alumno === false) {
    pagina_error(404, 'Ese alumno no existe (puede que ya se haya dado de baja).');
}

// Solo con POST se borra: un enlace (GET) nunca debe cambiar datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El token prueba que el POST salió de nuestro formulario
    verificar_csrf();
    // ON DELETE CASCADE en notas: sus notas se borran con él
    $stmt = $pdo->prepare('DELETE FROM alumnos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    redirigir('alumnos.php?ok=baja');
}

$titulo = 'Dar de baja';
require __DIR__ . '/../src/plantillas/header.php';
?>
<h1>Dar de baja</h1>

<!-- La confirmación es un formulario POST: un clic en un enlace no alcanza para borrar -->
<form class="tarjeta confirmar" action="alumno_baja.php?id=<?= $id ?>" method="post">
    <?= campo_csrf() ?>
    <p>¿Seguro que querés dar de baja a
       <strong><?= e(mb_strtoupper($alumno['apellido'])) ?>, <?= e($alumno['nombre']) ?></strong>
       (DNI <?= e($alumno['dni']) ?>, <?= e($alumno['curso']) ?>)?</p>
    <p class="bajada">Se borran también todas sus notas. No se puede deshacer.</p>
    <div class="botones">
        <button class="boton boton--peligro" type="submit">Sí, dar de baja</button>
        <a href="alumnos.php">Cancelar</a>
    </div>
</form>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
