<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===== .env =====
$envPath = __DIR__;
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// ===== Endurece cookies de sessão (faça também no index.php principal) =====
ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Lax');

// ===== Produção: não exibir erros para o usuário (logue apenas) =====
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// ===== CSRF simples =====
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
        if (!$ok) {
            http_response_code(403);
            die('CSRF inválido.');
        }
    }
}

$conn = db_connect();
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email = trim($_POST['email'] ?? '');
    // Mensagem genérica (não revela se o e-mail existe)
    $msg = 'Se o e-mail existir, você receberá um código em instantes.';

    if ($email !== '') {
        // 1) Busca usuário ATIVO
        $stmt = $conn->prepare(
            "SELECT id_usuario, nome_usuario, email_usuario
               FROM usuario
              WHERE email_usuario = ? AND status_usuario = 'ATIVO'
              LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $idUsuario = (int)$user['id_usuario'];

            // 2) Rate limit: no máx. 3 pedidos/min
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS c
                   FROM usuario_reset
                  WHERE id_usuario = ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
            );
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            if ($c < 3) {
                // 3) Invalida resets anteriores pendentes deste usuário
                $stmt = $conn->prepare(
                    "UPDATE usuario_reset
                        SET used_at = NOW()
                      WHERE id_usuario = ?
                        AND used_at IS NULL"
                );
                $stmt->bind_param('i', $idUsuario);
                $stmt->execute();
                $stmt->close();

                // 4) Gera código (6 dígitos), guarda hash e expiração
                $code      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $codeHash  = password_hash($code, PASSWORD_DEFAULT);
                $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');
                $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

                $stmt = $conn->prepare(
                    "INSERT INTO usuario_reset (id_usuario, code_hash, expires_at, ip)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param('isss', $idUsuario, $codeHash, $expiresAt, $ip);
                $stmt->execute();
                $stmt->close();

                // 5) Envia e-mail com PHPMailer usando credenciais do .env
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->SMTPAuth   = true;
                    $mail->CharSet    = 'UTF-8';

                    // ===== Lê SMTP do .env =====
                    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
                    $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'] ?? PHPMailer::ENCRYPTION_STARTTLS; // 'tls' ou constante
                    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
                    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';

                    // ===== From/To =====
                    $mail->setFrom($mail->Username, 'Registro de Ponto');
                    $mail->addAddress($user['email_usuario'], $user['nome_usuario']);
                    $mail->Subject = 'Código para redefinição de senha';

                    // ===== Corpo do e-mail =====
                    $mail->isHTML(false);
                    $mail->Body = "Olá {$user['nome_usuario']},\n\n"
                                . "Seu código para redefinição de senha é: {$code}\n"
                                . "Válido por 15 minutos.\n\n"
                                . "Se não foi você, ignore este e-mail.";

                    // ===== Logs só se você quiser depurar (NÃO use em produção) =====
                    if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                        $mail->SMTPDebug  = 2;
                        $mail->Debugoutput = static function ($str, $level) {
                            error_log("SMTP[$level] $str");
                        };
                    }

                    $mail->send();
                } catch (Exception $e) {
                    // Não exponha erro ao usuário final
                    error_log("Erro ao enviar e-mail de reset: " . $e->getMessage());
                }
            } else {
                // Opcional: ajustar $msg se quiser informar rate limit (eu manteria genérico)
                // $msg = 'Aguarde um minuto para solicitar novo código.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Esqueci minha senha</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-ENjdO4Dr2bkBIFxQpeoA7cQe59Bv9GqQ1ZC1og6l5/2hb7U5K8YBq1bVZCkXWTr9" crossorigin="anonymous">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:480px;">
  <h3 class="mb-3 text-center">Esqueci minha senha</h3>

  <?php if (!empty($msg)): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <div class="mb-3">
      <label class="form-label">E-mail cadastrado</label>
      <input name="email" type="email" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Solicitar código</button>
    <a class="btn btn-success w-100 mt-3" href="validar_codigo.php">Já tenho o código</a>
  </form>

  <div class="mt-3 text-center">
    <a href="./index.php">Voltar para Login</a>
  </div>
</div>
</body>
</html>
