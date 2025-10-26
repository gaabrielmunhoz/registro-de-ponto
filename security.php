<?php
declare(strict_types=1);

function get_app_env(): string
{
    $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
    return $env ? strtolower((string) $env) : 'production';
}

function configure_error_reporting(): void
{
    if (get_app_env() === 'production') {
        ini_set('display_errors', '0');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);

    session_start();
}

function generate_csrf_token(string $form): string
{
    start_secure_session();
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$form] = [
        'value' => $token,
        'expires_at' => time() + 3600,
    ];
    return $token;
}

function validate_csrf_token(string $form, ?string $token): bool
{
    start_secure_session();
    if (!$token || !isset($_SESSION['csrf_tokens'][$form])) {
        return false;
    }
    $stored = $_SESSION['csrf_tokens'][$form];
    unset($_SESSION['csrf_tokens'][$form]);

    if (!is_array($stored)) {
        return false;
    }

    $expiresAt = (int)($stored['expires_at'] ?? 0);
    if ($expiresAt !== 0 && $expiresAt < time()) {
        return false;
    }

    return hash_equals((string)($stored['value'] ?? ''), $token);
}

function require_valid_csrf(string $form, ?string $token): void
{
    if (!validate_csrf_token($form, $token)) {
        http_response_code(400);
        exit('Requisição inválida. Atualize a página e tente novamente.');
    }
}

function current_user_id(): int
{
    start_secure_session();
    return (int)($_SESSION['id_usuario'] ?? 0);
}

function require_authenticated_user(): int
{
    $userId = current_user_id();
    if ($userId <= 0) {
        header('Location: index.php');
        exit();
    }
    return $userId;
}