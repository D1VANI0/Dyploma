<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function mail_config_value(string $key, string $default = ""): string {
  $envValue = getenv($key);
  if ($envValue !== false && $envValue !== "") {
    return (string)$envValue;
  }

  static $config = null;
  if ($config === null) {
    $path = __DIR__ . "/../config/mail.php";
    $config = is_file($path) ? require $path : [];
    if (!is_array($config)) {
      $config = [];
    }
  }

  return array_key_exists($key, $config) ? (string)$config[$key] : $default;
}

function absolute_app_url(string $path): string {
  $configuredUrl = rtrim(mail_config_value("APP_URL"), "/");
  if ($configuredUrl === "") {
    throw new RuntimeException("APP_URL environment variable is required.");
  }

  return $configuredUrl . "/" . ltrim($path, "/");
}

function smtp_read_response($socket): array {
  $lines = [];
  $code = 0;

  while (($line = fgets($socket, 515)) !== false) {
    $lines[] = rtrim($line, "\r\n");
    if (preg_match('/^(\d{3})(\s|-)/', $line, $m)) {
      $code = (int)$m[1];
      if ($m[2] === " ") {
        break;
      }
    }
  }

  return [$code, implode("\n", $lines)];
}

function smtp_command($socket, string $command, array $expected): bool {
  fwrite($socket, $command . "\r\n");
  [$code] = smtp_read_response($socket);
  return in_array($code, $expected, true);
}

function smtp_address(string $email): string {
  return "<" . str_replace([">", "<", "\r", "\n"], "", $email) . ">";
}

function smtp_dot_stuff(string $body): string {
  $body = str_replace(["\r\n", "\r"], "\n", $body);
  $lines = explode("\n", $body);
  $lines = array_map(static function(string $line): string {
    return str_starts_with($line, ".") ? "." . $line : $line;
  }, $lines);
  return implode("\r\n", $lines);
}

function send_smtp_mail(string $to, string $subject, string $body): bool {
  $host = trim(mail_config_value("SMTP_HOST"));
  if ($host === "") {
    return false;
  }

  $port = (int)mail_config_value("SMTP_PORT", "587");
  $secure = strtolower(trim(mail_config_value("SMTP_SECURE", "tls")));
  $username = mail_config_value("SMTP_USERNAME");
  $password = mail_config_value("SMTP_PASSWORD");
  $from = mail_config_value("MAIL_FROM", $username);
  $fromName = mail_config_value("MAIL_FROM_NAME", "ScoutHub");

  if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
    return false;
  }

  $target = ($secure === "ssl" ? "ssl://" : "") . $host . ":" . $port;
  $socket = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
  if (!$socket) {
    return false;
  }

  stream_set_timeout($socket, 15);
  [$code] = smtp_read_response($socket);
  if ($code !== 220) {
    fclose($socket);
    return false;
  }

  $serverName = parse_url(mail_config_value("APP_URL"), PHP_URL_HOST);
  $serverName = preg_replace('/[^a-zA-Z0-9.-]/', "", (string)$serverName) ?: "scouthub";
  if (!smtp_command($socket, "EHLO " . $serverName, [250])) {
    fclose($socket);
    return false;
  }

  if ($secure === "tls") {
    if (!smtp_command($socket, "STARTTLS", [220])) {
      fclose($socket);
      return false;
    }
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
      fclose($socket);
      return false;
    }
    if (!smtp_command($socket, "EHLO " . $serverName, [250])) {
      fclose($socket);
      return false;
    }
  }

  if ($username !== "") {
    if (!smtp_command($socket, "AUTH LOGIN", [334])) {
      fclose($socket);
      return false;
    }
    if (!smtp_command($socket, base64_encode($username), [334])) {
      fclose($socket);
      return false;
    }
    if (!smtp_command($socket, base64_encode($password), [235])) {
      fclose($socket);
      return false;
    }
  }

  if (!smtp_command($socket, "MAIL FROM:" . smtp_address($from), [250])) {
    fclose($socket);
    return false;
  }
  if (!smtp_command($socket, "RCPT TO:" . smtp_address($to), [250, 251])) {
    fclose($socket);
    return false;
  }
  if (!smtp_command($socket, "DATA", [354])) {
    fclose($socket);
    return false;
  }

  $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
  $safeFromName = trim(str_replace(["\r", "\n", '"'], "", $fromName));
  $headers = [
    "From: \"{$safeFromName}\" <{$from}>",
    "To: <{$to}>",
    "Subject: {$encodedSubject}",
    "MIME-Version: 1.0",
    "Content-Type: text/plain; charset=UTF-8",
    "Content-Transfer-Encoding: 8bit",
  ];

  fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . smtp_dot_stuff($body) . "\r\n.\r\n");
  [$code] = smtp_read_response($socket);
  smtp_command($socket, "QUIT", [221]);
  fclose($socket);

  return $code === 250;
}

function send_app_mail(string $to, string $subject, string $body): bool {
  $to = trim($to);
  if ($to === "" || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    return false;
  }

  if (trim(mail_config_value("SMTP_HOST")) !== "" && send_smtp_mail($to, $subject, $body)) {
    return true;
  }

  $from = mail_config_value("MAIL_FROM");
  if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
    return false;
  }

  $fromName = mail_config_value("MAIL_FROM_NAME", "ScoutHub");
  $headers = [
    "From: {$fromName} <{$from}>",
    "Reply-To: {$from}",
    "MIME-Version: 1.0",
    "Content-Type: text/plain; charset=UTF-8",
  ];

  $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
  if ($sent) {
    return true;
  }

  return false;
}
