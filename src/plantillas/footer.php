<?php
// src/plantillas/footer.php: cierre común: pie y fin del HTML
// Cierra el <main> que abrió header.php
?>
</main>
<footer class="pie">
    <p>Gestión Escolar · Alumnos y notas</p>
    <!-- date('Y') pone el año solo: no hay que cambiarlo cada enero -->
    <p>Proyecto de práctica · <?= date('Y') ?></p>
</footer>
</body>
</html>
