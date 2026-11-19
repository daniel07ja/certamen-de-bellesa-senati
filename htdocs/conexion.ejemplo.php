<?php
declare(strict_types=1);

const DB_HOST = 'sqlXXX.infinityfree.com';
const DB_NAME = 'if0_XXXX_certamen';
const DB_USER = 'if0_XXXX';
const DB_PASS = 'REEMPLAZAR';
const EXIGIR_HTTPS = true;
// Genera 32 caracteres hexadecimales aleatorios para habilitar el primer ingreso.
const CLAVE_INSTALACION = '';

function db(): PDO
{
    static $pdo;
    return $pdo ??= new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}
