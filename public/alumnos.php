<?php
// alumnos.php: listado de alumnos (lo usan el admin y el preceptor)
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

// Sin login, al login; con el rol docente, 403
exigir_rol(['admin', 'preceptor']);

// JOIN con cursos para mostrar el nombre del curso ('4° 1ª') y no su número
// La consulta no lleva datos del usuario: alcanza con query(), sin preparar
$alumnos = conectar()->query(
    'SELECT a.id, a.dni, a.apellido, a.nombre, c.nombre AS curso
     FROM alumnos AS a
     JOIN cursos AS c ON c.id = a.curso_id
     ORDER BY a.apellido, a.nombre'
)->fetchAll();

// Después de guardar, las otras páginas vuelven acá con ?ok=alta, ?ok=cambio u ?ok=baja
// Lista blanca: solo se muestra un mensaje de este array, nunca el texto de la URL
$mensajes = [
    'alta'   => 'Alumno cargado.',
    'cambio' => 'Cambios guardados.',
    'baja'   => 'Alumno dado de baja.',
];
$aviso = $mensajes[limpiar($_GET['ok'] ?? '')] ?? null;

$titulo = 'Alumnos';
// buscador.js: busca en el servidor mientras se escribe (fetch + JSON)
$scripts = ['js/buscador.js'];
require __DIR__ . '/../src/plantillas/header.php';
?>
<div class="titular">
    <h1>Alumnos</h1>
    <a class="boton" href="alumno_alta.php">+ Nuevo alumno</a>
</div>

<?php if ($aviso !== null): ?>
    <!-- role="status": los lectores de pantalla anuncian el mensaje -->
    <p class="aviso aviso--ok" role="status"><?= e($aviso) ?></p>
<?php endif; ?>

<div class="campo buscador">
    <label for="buscador">Buscar por apellido, nombre o DNI</label>
    <!-- type="search": en el celular muestra la tecla «buscar» y una cruz para borrar -->
    <input id="buscador" type="search" autocomplete="off" placeholder="Ej.: gutierrez">
</div>
<!-- aria-live: cuando buscador.js cambia la cuenta, el lector de pantalla la lee -->
<p class="bajada" id="cuenta" aria-live="polite"><?= count($alumnos) ?> alumnos</p>
<div class="tabla-scroll">
    <!-- data-total y data-admin: datos para buscador.js (cuántos hay y si se muestra la baja) -->
    <table class="tabla" id="tabla-alumnos" data-total="<?= count($alumnos) ?>" data-admin="<?= tiene_rol(['admin']) ? '1' : '0' ?>">
        <thead>
            <tr>
                <th scope="col">Apellido y nombre</th>
                <th scope="col">DNI</th>
                <th scope="col">Curso</th>
                <th scope="col"><span class="sr-only">Acciones</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <tr>
                <!-- e() en cada dato: un apellido con etiquetas se ve como texto -->
                <td><?= e(mb_strtoupper($a['apellido'])) ?>, <?= e($a['nombre']) ?></td>
                <td><?= e($a['dni']) ?></td>
                <td><?= e($a['curso']) ?></td>
                <td class="acciones">
                    <a href="alumno_editar.php?id=<?= $a['id'] ?>">Editar</a>
                    <?php if (tiene_rol(['admin'])): ?>
                        <!-- La baja solo la ve el admin (y alumno_baja.php lo vuelve a controlar) -->
                        <a class="peligro" href="alumno_baja.php?id=<?= $a['id'] ?>">Baja</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
