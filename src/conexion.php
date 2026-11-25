<?php
// src/conexion.php: abre la conexión con MySQL usando PDO

// conectar(): devuelve un objeto PDO listo para hacer consultas
function conectar(): PDO
{
    // Los datos de acceso vienen de config/config.php, que no está en el repositorio
    $config = require __DIR__ . '/../config/config.php';

    // DSN: qué motor (mysql), en qué servidor, qué base y qué juego de caracteres
    $dsn = 'mysql:host=' . $config['db_host']
         . ';dbname=' . $config['db_nombre']
         . ';charset=utf8mb4';

    // Opciones de PDO
    $opciones = [
        // si una consulta falla, lanza una excepción (no sigue callada)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // cada fila llega como array con el nombre de la columna como clave
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // las consultas preparadas las hace MySQL de verdad, no PHP
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        // new PDO(...) intenta conectarse; si no puede, lanza una PDOException
        return new PDO($dsn, $config['db_usuario'], $config['db_clave'], $opciones);
    } catch (PDOException $e) {
        // El detalle técnico va al log de Apache (error.log), nunca a la pantalla
        error_log('Error de conexión a MySQL: ' . $e->getMessage());
        // 500 = error del servidor
        http_response_code(500);
        // Al usuario, un mensaje claro y sin datos internos; exit corta el script
        exit('No se pudo conectar con la base de datos. Avisá al administrador del sistema.');
    }
}
