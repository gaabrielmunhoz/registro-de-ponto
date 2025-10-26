<?php

session_start();
require_once "db_functions.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // garante que o usuário está logado
    if (empty($_SESSION["id_usuario"])) {
        header("Location: ./index.php");
        exit;
    }

    $id    = (int) $_SESSION["id_usuario"];
    $senha = $_POST["senha"] ?? '';

    $res = db_select("usuario", ["id_usuario" => $id]);
    if (!$res) {
        header("Location: ./redirecionando_erro_alterar_senha.php");
        exit;
    }
    $user = $res[0];

    if (!password_verify($senha, $user["senha_hash"])) {
        header("Location: ./redirecionando_erro_alterar_senha.php");
        exit;
    }

    // libera para a tela de nova senha
    $_SESSION["can_change_password"] = true;
    header("Location: ./nova_senha.php?tab=senha");
    exit;
}


?>


<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alterar senha</title>
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
                <h3 class="card-title d-flex justify-content-center ">Insira a sua senha atual</h3>
                <form method="POST" action="./alterar_senha_atual.php">
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha atual</label>
                        <input type="password" class="form-control" id="senha" name="senha">
                        <a href="./esqueci_senha.php">Esqueci minha senha</a>
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary d-flex align-items-center">Confirmar</button>
                        <a href="./editar_cadastro.php" class="btn btn-secondary ms-3">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

  </body>
</html>