<?php
// src/plantillas/header.php: cabecera común: <head>, marca del sistema y menú
// Cada página lo trae con require, después de definir $titulo (y $scripts si usa JavaScript)
// $yo: el usuario que entró, o null (en el login)
$yo = usuario_actual();
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <!-- en el celular, que use el ancho real de la pantalla -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- $titulo lo definió la página que hizo el require -->
    <title><?= e($titulo) ?> · Gestión Escolar</title>
    <!-- sin ícono propio: así el navegador no pide /favicon.ico (y no hay un 404 en el log) -->
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="css/styles.css">
    <!-- $scripts: los .js que pidió la página (?? []: si no pidió ninguno, la lista vacía) -->
    <?php foreach ($scripts ?? [] as $js): ?>
    <!-- defer: el archivo se baja en paralelo y corre cuando el HTML ya está armado -->
    <script src="<?= e($js) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
<header class="cabecera">
    <div class="cabecera__marca">
        <!-- aria-hidden: la sigla es decoración, el nombre ya está al lado -->
        <span class="cabecera__sigla" aria-hidden="true">GE</span>
        <div>
            <strong>Gestión Escolar</strong>
            <span>Alumnos y notas · Técnico en Programación</span>
        </div>
    </div>
    <?php if ($yo !== null): ?>
        <!-- Cada rol ve solo lo que puede usar (y cada página lo vuelve a controlar) -->
        <nav class="menu" aria-label="Menú principal">
            <?php if (tiene_rol(['admin', 'preceptor'])): ?>
                <a href="alumnos.php"<?= es_actual('alumnos.php') ?>>Alumnos</a>
            <?php endif; ?>
            <?php if (tiene_rol(['admin', 'docente'])): ?>
                <a href="notas.php"<?= es_actual('notas.php') ?>>Notas</a>
            <?php endif; ?>
            <a href="promedios.php"<?= es_actual('promedios.php') ?>>Promedios</a>
        </nav>
        <!-- Salir es un formulario POST con el token: un enlace lo podría disparar otra página -->
        <form class="sesion" action="logout.php" method="post">
            <span><?= e($yo['usuario']) ?> · <?= e($yo['rol']) ?></span>
            <?= campo_csrf() ?>
            <button type="submit">Salir</button>
        </form>
    <?php endif; ?>
</header>
<main class="contenido">
