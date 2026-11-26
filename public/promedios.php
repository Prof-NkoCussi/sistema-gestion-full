<?php
// promedios.php: promedio de cada alumno por cuatrimestre (lo ven los tres roles)
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

// Alcanza con haber entrado: admin, preceptor y docente pueden verlo
exigir_login();
$pdo = conectar();
$cursos = leer_cursos($pdo);

// Filtro optativo por curso, con lista blanca: un curso inventado muestra todos
$curso_id = leer_id($_GET, 'curso_id');
if ($curso_id !== null && !array_key_exists($curso_id, $cursos)) {
    $curso_id = null;
}
$alumnos = leer_promedios($pdo, $curso_id);

// El enlace para exportar lleva el mismo filtro: se descarga lo que se está viendo
$exportar = 'exportar.php' . ($curso_id !== null ? '?curso_id=' . $curso_id : '');

$titulo = 'Promedios';
require __DIR__ . '/../src/plantillas/header.php';
?>
<div class="titular">
    <h1>Promedios</h1>
    <a class="boton" href="<?= e($exportar) ?>">Exportar a CSV</a>
</div>

<form class="filtros" action="promedios.php" method="get">
    <div class="campo">
        <label for="curso_id">Curso</label>
        <select id="curso_id" name="curso_id">
            <!-- value vacío: leer_id() da null y se muestran todos los cursos -->
            <option value="">Todos los cursos</option>
            <?php foreach ($cursos as $cid => $nombre): ?>
                <option value="<?= $cid ?>"<?= $curso_id === $cid ? ' selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="boton" type="submit">Filtrar</button>
</form>

<p class="bajada"><?= count($alumnos) ?> alumnos · promedio de las notas de todas sus materias en cada cuatrimestre; el final, el de los dos cuatrimestres</p>
<div class="tabla-scroll">
    <table class="tabla">
        <thead>
            <tr>
                <th scope="col">Alumno</th>
                <th scope="col">Curso</th>
                <!-- una columna por cuatrimestre: si se agregara uno, aparece solo -->
                <?php foreach (CUATRIMESTRES as $nombre): ?>
                    <th scope="col" class="numero"><?= e($nombre) ?></th>
                <?php endforeach; ?>
                <th scope="col" class="numero">Final</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <tr>
                <td><?= e(mb_strtoupper($a['apellido'])) ?>, <?= e($a['nombre']) ?></td>
                <td><?= e($a['curso']) ?></td>
                <?php foreach (CUATRIMESTRES as $num => $nombre): ?>
                    <!-- sin notas en ese cuatrimestre, una raya -->
                    <td class="numero"><?= isset($a['promedios'][$num]) ? formato_promedio($a['promedios'][$num]) : '—' ?></td>
                <?php endforeach; ?>
                <!-- el final: el promedio de los dos cuatrimestres (con uno solo, todavía no hay) -->
                <td class="numero final"><?= $a['final'] !== null ? formato_promedio($a['final']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
