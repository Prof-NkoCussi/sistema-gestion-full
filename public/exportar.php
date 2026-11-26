<?php
// exportar.php: descarga los promedios como CSV, listo para abrir en Excel
require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/conexion.php';

exigir_login();
$pdo = conectar();
$cursos = leer_cursos($pdo);

// El mismo filtro que promedios.php, con la misma lista blanca
$curso_id = leer_id($_GET, 'curso_id');
if ($curso_id !== null && !array_key_exists($curso_id, $cursos)) {
    $curso_id = null;
}
$alumnos = leer_promedios($pdo, $curso_id);

// Cabeceras: es un CSV en UTF-8 y se descarga como archivo (no se abre en la pestaña)
$archivo = 'promedios-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $archivo . '"');

// php://output: escribir directo en la respuesta, como hace echo
$salida = fopen('php://output', 'w');
// BOM: tres bytes al principio que le avisan a Excel que el texto está en UTF-8 (si no, "MuÃ±oz")
fwrite($salida, "\xEF\xBB\xBF");

// La primera fila: los títulos de las columnas, uno por cuatrimestre y el final
$titulos = ['Apellido', 'Nombre', 'Curso'];
foreach (CUATRIMESTRES as $nombre) {
    $titulos[] = 'Promedio ' . $nombre;
}
$titulos[] = 'Promedio final';
// Excel en español separa las columnas con punto y coma: la coma es la de los decimales
// fputcsv(archivo, fila, separador, comillas, escape): arma una línea y pone comillas donde haga falta
fputcsv($salida, $titulos, ';', '"', '');

foreach ($alumnos as $a) {
    $fila = [celda_csv($a['apellido']), celda_csv($a['nombre']), celda_csv($a['curso'])];
    foreach (CUATRIMESTRES as $num => $nombre) {
        // Sin notas en ese cuatrimestre, la celda queda vacía
        $fila[] = isset($a['promedios'][$num]) ? formato_promedio($a['promedios'][$num]) : '';
    }
    // Sin los dos cuatrimestres, el final también queda vacío
    $fila[] = $a['final'] !== null ? formato_promedio($a['final']) : '';
    fputcsv($salida, $fila, ';', '"', '');
}
fclose($salida);
