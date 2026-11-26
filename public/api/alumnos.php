<?php
// api/alumnos.php: busca alumnos y responde en JSON (lo usa el buscador en vivo)
// Ejemplo: api/alumnos.php?q=gut → {"total":1,"alumnos":[{"id":10,"dni":"47234567",…}]}
require __DIR__ . '/../../src/funciones.php';
require __DIR__ . '/../../src/conexion.php';

// Quien pregunta es JavaScript: en vez de redirigir al login, se responde el error en JSON
// 401 = no entraste; 403 = entraste, pero tu rol no puede ver esto
if (usuario_actual() === null) {
    responder_json(['error' => 'Tenés que entrar al sistema.'], 401);
}
if (!tiene_rol(['admin', 'preceptor'])) {
    responder_json(['error' => 'No tenés permiso para ver los alumnos.'], 403);
}

// Lo buscado, hasta 50 letras
$q = mb_substr(limpiar($_GET['q'] ?? ''), 0, 50);
// addcslashes(): % y _ son comodines de LIKE; así se buscan como letras comunes
$patron = '%' . addcslashes($q, '%_\\') . '%';

// Consulta preparada: el texto viaja aparte, como en todo el sistema
// La base es _ai_ci: 'gutierrez' encuentra a 'Gutiérrez' sin hacer nada más
$stmt = conectar()->prepare(
    "SELECT a.id, a.dni, a.apellido, a.nombre, c.nombre AS curso
     FROM alumnos AS a
     JOIN cursos AS c ON c.id = a.curso_id
     WHERE CONCAT(a.apellido, ' ', a.nombre, ' ', a.dni) LIKE :patron
     ORDER BY a.apellido, a.nombre
     LIMIT 50"
);
$stmt->execute(['patron' => $patron]);
$alumnos = $stmt->fetchAll();

responder_json(['total' => count($alumnos), 'alumnos' => $alumnos]);
