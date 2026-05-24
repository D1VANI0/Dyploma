<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $host = getenv("DB_HOST") ?: "";
  $db   = getenv("DB_NAME") ?: "";
  $user = getenv("DB_USER") ?: "";
  $pass = getenv("DB_PASS") ?: "";
  $port = (int)(getenv("DB_PORT") ?: 3306);
  $charset = "utf8mb4";

  if ($host === "" || $db === "" || $user === "") {
    throw new RuntimeException("Database environment variables DB_HOST, DB_NAME and DB_USER are required.");
  }

  $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  $ssl = strtolower((string)(getenv("DB_SSL") ?: ""));
  if (in_array($ssl, ["1", "true", "yes"], true) && defined("PDO::MYSQL_ATTR_SSL_CA")) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = getenv("DB_SSL_CA") ?: "/etc/ssl/certs/ca-certificates.crt";
  }

  $pdo = new PDO($dsn, $user, $pass, $options);

  return $pdo;
}
