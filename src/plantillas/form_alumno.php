<?php
// src/plantillas/form_alumno.php: formulario de alumno, para el alta y la modificación
// Antes del require, la página define $accion, $alumno, $errores, $cursos y $boton
?>
<!-- id="form-alumno": así lo encuentra validar.js -->
<form id="form-alumno" class="tarjeta formulario" action="<?= e($accion) ?>" method="post">
    <?= campo_csrf() ?>
    <div class="campo">
        <label for="dni">DNI</label>
        <!-- required y pattern: el navegador controla aunque no haya JavaScript -->
        <input id="dni" name="dni" type="text" inputmode="numeric" maxlength="10"
               required pattern="[0-9. ]{7,10}" value="<?= e($alumno['dni']) ?>">
        <!-- el error de cada campo: lo escribe PHP al volver, o validar.js antes de enviar -->
        <p class="error" id="error-dni"><?= e($errores['dni'] ?? '') ?></p>
    </div>
    <div class="campo">
        <label for="apellido">Apellido</label>
        <input id="apellido" name="apellido" type="text" maxlength="60" required value="<?= e($alumno['apellido']) ?>">
        <p class="error" id="error-apellido"><?= e($errores['apellido'] ?? '') ?></p>
    </div>
    <div class="campo">
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" maxlength="60" required value="<?= e($alumno['nombre']) ?>">
        <p class="error" id="error-nombre"><?= e($errores['nombre'] ?? '') ?></p>
    </div>
    <div class="campo">
        <label for="curso_id">Curso</label>
        <select id="curso_id" name="curso_id" required>
            <option value="">Elegí…</option>
            <!-- $cid: el id del curso (no se llama $id para no pisar el id del alumno en la edición) -->
            <?php foreach ($cursos as $cid => $nombre): ?>
                <!-- selected: queda elegido el curso que ya tenía (o el que se mandó) -->
                <option value="<?= $cid ?>"<?= $alumno['curso_id'] === $cid ? ' selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="error" id="error-curso_id"><?= e($errores['curso_id'] ?? '') ?></p>
    </div>
    <div class="botones">
        <button class="boton" type="submit"><?= e($boton) ?></button>
        <a href="alumnos.php">Cancelar</a>
    </div>
</form>
