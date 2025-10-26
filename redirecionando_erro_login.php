<?php
declare(strict_types=1);

session_start();
$destino = $_GET['to'] ?? './index.php';
$destino = htmlspecialchars($destino, ENT_QUOTES);
$mensagem = $_SESSION['login_error_message'] ?? 'Senha ou usuário incorretos';
unset($_SESSION['login_error_message']);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Redirecionando...</title>
  <meta http-equiv="refresh" content="3;url=<?= $destino ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background: #f8f9fa;
    }
    .card {
      max-width: 500px;
      text-align: center;
      border-radius: 1rem;
    }
  </style>
</head>
<body>
  <div class="card shadow p-4">
    <h2 class="mb-3 text-danger"><?= htmlspecialchars($mensagem, ENT_QUOTES) ?></h2>
    <p>Você será redirecionado para a página de login em <strong>instantes</strong>.</p>
    <div class="d-flex justify-content-center my-3">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Carregando...</span>
      </div>
    </div>
  </div>
</body>
</html>
