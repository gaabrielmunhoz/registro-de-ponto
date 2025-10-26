<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Lax');

session_start();
require_once __DIR__ . '/db_functions.php';

$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production';
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $now = time();
    $attempts = $_SESSION['login_attempts'] ?? [];
    $attempts = array_filter(
        $attempts,
        static fn($ts) => is_int($ts) && ($now - $ts) < 60
    );

    if (count($attempts) >= 5) {
        $_SESSION['login_attempts'] = $attempts;
        $_SESSION['login_error_message'] = 'Muitas tentativas de login. Aguarde um minuto antes de tentar novamente.';
        header("Location: ./redirecionando_erro_login.php");
        exit;
    }
    #pegando os dados do login
    $cpf = trim($_POST["cpf_usuario"] ?? '');
    $senha = $_POST["senha"] ?? '';

    $attempts[] = $now;
    $_SESSION['login_attempts'] = $attempts;

    $res = db_select("usuario", ["cpf_usuario" => $cpf ]);
    if (!$res) {
        $_SESSION['login_error_message'] = 'Senha ou usuário incorretos.';
        header("Location: ./redirecionando_erro_login.php");
        exit;
    }

    $user = $res[0];

    #conferencia da senha criptografada
    if (!password_verify($senha, $user["senha_hash"])){
        echo "Usuário ou senha incorretos.";
        header("Location: ./redirecionando_erro_login.php");
        exit;
    }

    #redirecionando para a página principal
    unset($_SESSION['login_attempts']);
    $_SESSION["id_usuario"] = $user["id_usuario"] ?? null;
    $_SESSION["nome_usuario"] = $user["nome_usuario"] ?? null;
    $_SESSION["cpf_usuario"] = $user["cpf_usuario"] ?? null;

    header("Location: ./pagina_principal.php");
    exit;

}


?>


<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Registro de Ponto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  </head>
  <body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    <div class="text-center mt-0">
        <img src="./logo.png" alt="logo" class="d-block mx-auto mb-1" alt="logo" style="max-width: 160px; height: auto;">
    </div>

    <div class="container d-flex justify-content-center align-items-center vh-100 fixed-top">
        <div class="card shadow" style="width: 30rem;">
            <div class="card-body">
                <h3 class="card-title d-flex justify-content-center ">Login</h3>
                <form method="POST" action="./index.php">
                    <div class="mb-3">
                        <label for="cpf_usuario" class="form-label">CPF</label>
                        <input type="text" inputmode="numeric" class="form-control" id="cpf_usuario" name="cpf_usuario">
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha">
                        <a href="./esqueci_senha.php">Esqueci minha senha</a>
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary d-flex align-items-center">Entrar</button>
                        <a href="./cadastro_usuario.php" class="btn btn-secondary ms-3">Cadastrar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

  </body>
</html>