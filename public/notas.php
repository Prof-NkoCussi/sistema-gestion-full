<?php
// notas.php: carga de notas por curso, materia y cuatrimestre (la usan el admin y el docente)
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

// Sin login, al login; con el rol preceptor, 403
exigir_rol(['admin', 'docente']);
$pdo = conectar();
$cursos = leer_cursos($pdo);

// 1) QUÉ PLANILLA SE PIDE: curso, materia y cuatrimestre llegan por GET (en la URL)
$curso_id = leer_id($_GET, 'curso_id');
$materia_id = leer_id($_GET, 'materia_id');
$cuatrimestre = leer_id($_GET, 'cuatrimestre');

// Listas blancas: un dato inventado en la URL queda en null
if ($curso_id !== null && !array_key_exists($curso_id, $cursos)) {
    $curso_id = null;
}
// Las materias dependen del curso: sin curso, la lista queda vacía
$materias = $curso_id !== null ? materias_del_curso($pdo, $curso_id) : [];
if ($materia_id !== null && !array_key_exists($materia_id, $materias)) {
    $materia_id = null;
}
if ($cuatrimestre !== null && !array_key_exists($cuatrimestre, CUATRIMESTRES)) {
    $cuatrimestre = null;
}

// La planilla aparece solo si los tres datos son válidos
$elegido = $curso_id !== null && $materia_id !== null && $cuatrimestre !== null;
// Los tres datos armados como texto de URL: curso_id=4&materia_id=7&cuatrimestre=1
$consulta = http_build_query(['curso_id' => $curso_id, 'materia_id' => $materia_id, 'cuatrimestre' => $cuatrimestre]);

$alumnos = [];
$valores = [];   // lo que muestra cada casilla: [alumno_id => '8']
$errores = [];   // [alumno_id => mensaje]

if ($elegido) {
    // Los alumnos del curso, cada uno con su nota de esa materia y cuatrimestre
    // LEFT JOIN: el alumno aparece aunque todavía no tenga nota (n.nota llega NULL)
    $stmt = $pdo->prepare(
        'SELECT a.id, a.apellido, a.nombre, n.nota
         FROM alumnos AS a
         LEFT JOIN notas AS n
           ON n.alumno_id = a.id AND n.materia_id = :materia AND n.cuatrimestre = :cuatrimestre
         WHERE a.curso_id = :curso
         ORDER BY a.apellido, a.nombre'
    );
    $stmt->execute(['materia' => $materia_id, 'cuatrimestre' => $cuatrimestre, 'curso' => $curso_id]);
    $alumnos = $stmt->fetchAll();
    foreach ($alumnos as $a) {
        // (string): 8 pasa a '8' y NULL pasa a '' (casilla vacía)
        $valores[$a['id']] = (string) $a['nota'];
    }
}

// 2) GUARDAR: el formulario manda nota[13]=8, nota[14]=…, una casilla por alumno
if ($elegido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    // $_POST['nota'] tiene que ser un array; si no, se toma vacío
    $enviadas = is_array($_POST['nota'] ?? null) ? $_POST['nota'] : [];
    $guardar = [];   // [alumno_id => nota], o null para borrar la que tenía

    // Se recorren los alumnos de la base, no lo que mandó el navegador:
    // un id que no es de este curso se ignora
    foreach ($alumnos as $a) {
        $id = $a['id'];
        // Si la casilla de ese alumno no vino en el formulario, su nota queda como estaba
        if (!array_key_exists($id, $enviadas)) {
            continue;
        }
        $texto = limpiar($enviadas[$id]);
        // Si hay errores, cada casilla vuelve con lo que se escribió
        $valores[$id] = $texto;
        if ($texto === '') {
            $guardar[$id] = null;   // vacía = sin nota
        } elseif (leer_nota($texto) !== null) {
            $guardar[$id] = leer_nota($texto);
        } else {
            $errores[$id] = 'Del 1 al 10.';
        }
    }

    // Con un solo error no se guarda nada: se corrige y se vuelve a enviar
    if (!$errores) {
        // Si la nota ya existe (índice UNIQUE alumno + materia + cuatrimestre), se actualiza
        // AS nueva: el nombre de la fila que se quiso insertar, para usar su nota
        $insertar = $pdo->prepare(
            'INSERT INTO notas (alumno_id, materia_id, cuatrimestre, nota)
             VALUES (:alumno, :materia, :cuatrimestre, :nota) AS nueva
             ON DUPLICATE KEY UPDATE nota = nueva.nota'
        );
        $borrar = $pdo->prepare(
            'DELETE FROM notas WHERE alumno_id = :alumno AND materia_id = :materia AND cuatrimestre = :cuatrimestre'
        );

        // Transacción: se guardan todas las notas o ninguna
        $pdo->beginTransaction();
        try {
            foreach ($guardar as $id => $nota) {
                $datos = ['alumno' => $id, 'materia' => $materia_id, 'cuatrimestre' => $cuatrimestre];
                if ($nota === null) {
                    $borrar->execute($datos);
                } else {
                    // + agrega la clave nota al array
                    $insertar->execute($datos + ['nota' => $nota]);
                }
            }
            // commit(): recién acá los cambios quedan guardados
            $pdo->commit();
        } catch (Throwable $e) {
            // rollBack(): si algo falló, se deshace todo lo de la transacción
            $pdo->rollBack();
            throw $e;
        }
        // Post/Redirect/Get: recargar la página no vuelve a mandar el formulario
        redirigir('notas.php?' . $consulta . '&ok=1');
    }
}

// El aviso aparece solo al volver del guardado (ok=1 lo agrega la redirección)
$guardado = $elegido && limpiar($_GET['ok'] ?? '') === '1' && !$errores;

$titulo = 'Notas';
// notas.js: al cambiar el curso, la página se recarga sola con sus materias
$scripts = ['js/notas.js'];
require __DIR__ . '/../src/plantillas/header.php';
?>
<h1>Carga de notas</h1>

<!-- Filtros con GET: la planilla elegida queda en la URL (se puede recargar o guardar) -->
<form class="filtros" action="notas.php" method="get">
    <div class="campo">
        <label for="curso_id">Curso</label>
        <select id="curso_id" name="curso_id" required>
            <option value="">Elegí…</option>
            <?php foreach ($cursos as $cid => $nombre): ?>
                <option value="<?= $cid ?>"<?= $curso_id === $cid ? ' selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="materia_id">Materia</label>
        <select id="materia_id" name="materia_id">
            <!-- sin curso elegido no hay materias para mostrar -->
            <option value=""><?= $materias ? 'Elegí…' : 'Primero el curso' ?></option>
            <?php foreach ($materias as $mid => $nombre): ?>
                <option value="<?= $mid ?>"<?= $materia_id === $mid ? ' selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="cuatrimestre">Cuatrimestre</label>
        <select id="cuatrimestre" name="cuatrimestre">
            <option value="">Elegí…</option>
            <?php foreach (CUATRIMESTRES as $num => $nombre): ?>
                <option value="<?= $num ?>"<?= $cuatrimestre === $num ? ' selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="boton" type="submit">Ver planilla</button>
</form>

<?php if (!$elegido): ?>
    <p class="bajada">Elegí el curso (aparecen sus materias), después la materia y el cuatrimestre, y tocá «Ver planilla».</p>
<?php else: ?>
    <h2><?= e($cursos[$curso_id]) ?> · <?= e($materias[$materia_id]) ?> · <?= e(CUATRIMESTRES[$cuatrimestre]) ?></h2>

    <?php if ($guardado): ?>
        <p class="aviso aviso--ok" role="status">Notas guardadas.</p>
    <?php endif; ?>
    <?php if ($errores): ?>
        <!-- role="alert": los lectores de pantalla lo anuncian enseguida -->
        <p class="aviso aviso--error" role="alert">Hay notas que no sirven: no se guardó ninguna. Corregí las marcadas en rojo.</p>
    <?php endif; ?>

    <?php if (!$alumnos): ?>
        <p class="bajada">Este curso no tiene alumnos cargados.</p>
    <?php else: ?>
        <!-- El POST va a la misma planilla: la URL lleva curso, materia y cuatrimestre -->
        <form class="planilla" action="notas.php?<?= e($consulta) ?>" method="post">
            <?= campo_csrf() ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th scope="col">Alumno</th>
                            <th scope="col">Nota (1 a 10)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($alumnos as $a): ?>
                        <?php $id = $a['id']; ?>
                        <tr>
                            <!-- el label hace que tocar el nombre lleve a su casilla -->
                            <td><label for="nota-<?= $id ?>"><?= e(mb_strtoupper($a['apellido'])) ?>, <?= e($a['nombre']) ?></label></td>
                            <td class="celda-nota">
                                <!-- name="nota[13]": PHP lo recibe como $_POST['nota'][13] -->
                                <!-- min, max y step: el navegador ya frena un 11 o un 7.5 (PHP controla igual) -->
                                <input class="nota" id="nota-<?= $id ?>" name="nota[<?= $id ?>]"
                                       type="number" min="1" max="10" step="1" inputmode="numeric"
                                       value="<?= e($valores[$id]) ?>"<?= isset($errores[$id]) ? ' aria-invalid="true"' : '' ?>>
                                <?php if (isset($errores[$id])): ?>
                                    <span class="error"><?= e($errores[$id]) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="bajada">Casilla vacía = sin nota (si tenía una, se borra).</p>
            <div class="botones">
                <button class="boton" type="submit">Guardar notas</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../src/plantillas/footer.php'; ?>
