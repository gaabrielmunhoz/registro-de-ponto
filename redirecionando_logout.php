<?php
declare(strict_types=1);
session_start();
unset($_SESSION['id_usuario'], $_SESSION['nome_usuario'], $_SESSION['cpf_usuario']);
$_SESSION = [];
session_destroy();

// Evita que o "Voltar" mostre páginas protegidas em cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Mostra página por 3s e depois vai para o login
$destino = './index.php?logout=1';
header('Refresh: 3; url=' . $destino);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Saindo...</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6">
        <div class="card shadow-sm p-4 text-center">
          <div class="d-flex justify-content-center my-3">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          </div>
          <h1 class="h5 mb-2 text-danger">Finalizando sua sessão...</h1>
          <p class="text-muted mb-3">Você será redirecionado em <strong>instantes</strong>.</p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
