<?php
// C:\sites\cv\config.mssql.php
$DB_HOST = 'localhost';  // o IP del servidor
$DB_PORT = 1433;
$DB_NAME = 'CVDB';
$DB_USER = 'david';
$DB_PASS = 'root';

// El cifrado es recomendable; TrustServerCertificate=Yes solo para desarrollo
$dsn = "sqlsrv:Server=$DB_HOST,$DB_PORT;Database=$DB_NAME;Encrypt=Yes;TrustServerCertificate=Yes;LoginTimeout=30";

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  // Si estas constantes no existen en tu build, coméntalas:
  PDO::SQLSRV_ATTR_ENCODING     => PDO::SQLSRV_ENCODING_UTF8,
  PDO::SQLSRV_ATTR_DIRECT_QUERY => false,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  http_response_code(500);
  die('Error de conexión: ' . htmlspecialchars($e->getMessage()));
}
