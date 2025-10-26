<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db_functions.php';

$conn = db_connect();
$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['code'] ?? '');

    if ($email === '' || $code === '') {
        $msg = 'Preencha e-mail e código.';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $msg = 'Código deve ter 6 dígitos.';
    } else {
        // busca usuário
        $stmt = $conn->prepare("SELECT id_usuario, status_usuario FROM usuario
                                WHERE email_usuario = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || $user['status_usuario'] !== 'ATIVO') {
            $msg = 'Código inválido ou expirado.';
        } else {
            // busca reset válido
            $stmt = $conn->prepare("SELECT id, code_hash
                                    FROM usuario_reset
                                    WHERE id_usuario = ?
                                      AND used_at IS NULL
                                      AND expires_at >= NOW()
                                    ORDER BY id DESC
                                    LIMIT 1");
            $stmt->bind_param('i', $user['id_usuario']);
            $stmt->execute();
            $reset = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$reset || !password_verify($code, $reset['code_hash'])) {
                $msg = 'Código inválido ou expirado.';
            } else {
                // marca como usado e invalida demais pendentes
                $stmt = $conn->prepare("UPDATE usuario_reset SET used_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $reset['id']);
                $stmt->execute();
                $stmt->close();

                $conn->query("UPDATE usuario_reset
                              SET used_at = NOW()
                              WHERE id_usuario = {$user['id_usuario']} AND used_at IS NULL");

                // autoriza troca de senha no arquivo atual
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = (int)$user['id_usuario'];          
                $_SESSION['can_change_password'] = true;                  
                // tempo limite dessa permissão:
                $_SESSION['can_change_password_expires'] = time() + 15*60;

                header('Location: nova_senha.php');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Validar código</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:480px;">
  <h3 class="mb-3 text-center">Validar código</h3>
  <?php if ($msg): ?><div class="alert alert-warning"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3">
      <label class="form-label">E-mail</label>
      <input name="email" type="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Código (6 dígitos)</label>
      <input name="code" type="text" class="form-control" maxlength="6" pattern="\d{6}" required>
    </div>
    <button class="btn btn-primary w-100">Validar e alterar senha</button>
  </form>
  <div class="mt-3 text-center">
    <a href="esqueci_senha.php">Enviar outro código</a> • <a href="index.php">Login</a>
  </div>
</div>
</body>
</html>
