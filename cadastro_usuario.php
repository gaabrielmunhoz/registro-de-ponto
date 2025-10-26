<?php
session_start();
require_once "db_functions.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

$erro = "";
$okmsg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Dados do form
    $nome  = trim($_POST["nome_usuario"]  ?? '');
    $email = trim($_POST["email_usuario"] ?? '');
    $cpf   = preg_replace('/\D+/', '', $_POST["cpf_usuario"] ?? ''); // só números
    $senha = $_POST["senha"] ?? '';
    $conf  = $_POST["senha_confirm"] ?? '';

    // Validações
    if ($nome === '' || $email === '' || $cpf === '' || $senha === '' || $conf === '') {
        $erro = "Preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "E-mail inválido.";
    } elseif (strlen($cpf) !== 11) {
        $erro = "CPF deve ter 11 dígitos (somente números).";
    } elseif ($senha !== $conf) {
        $erro = "As senhas não coincidem.";
    } elseif (strlen($senha) < 8) {
        $erro = "A senha deve ter pelo menos 8 caracteres.";
    }

    // verifica se há duplicidade
    if ($erro === "") {
        if (db_select("usuario", ["cpf_usuario" => $cpf])) {
            $erro = "Já existe um usuário com esse CPF.";
        } elseif (db_select("usuario", ["email_usuario" => $email])) {
            $erro = "Já existe um usuário com esse e-mail.";
        }
    }

    if ($erro === "") {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $ok = db_insert("usuario", [
            "nome_usuario"  => $nome,
            "cpf_usuario"   => $cpf,
            "email_usuario" => $email,
            "senha_hash"    => $senha_hash
        ]);

        if ($ok) {
            // redireciona para o login com flag de sucesso
            header("Location: ./index.php?cadastro=ok");
            exit;
        } else {
            $erro = "Erro ao cadastrar usuário.";
        }
    }
}
?>



<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  </head>
  <body>
    
    <div class="text-center mt-0">
        <img src="./logo.png" alt="logo" class="d-block mx-auto mb-1" alt="logo" style="max-width: 160px; height: auto;">
    </div>

    <div class="container d-flex justify-content-center align-items-center vh-100 fixed-top mt-3">
        <div class="card shadow" style="width: 30rem;">
            <div class="card-body">
                <h3 class="card-title d-flex justify-content-center ">Cadastro</h3>
                <?php if (!empty($erro)): ?>
                <div class="alert alert-danger text-center py-2 mb-3"><?= htmlspecialchars($erro) ?></div>
                <?php endif; ?>
                <form method="POST" action="./cadastro_usuario.php">
                    <div class="mb-3">
                        <label for="nome_usuario" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome_usuario" name="nome_usuario">
                    </div>
                    <div class="mb-3">
                        <label for="email_usuario" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email_usuario" name="email_usuario">
                    </div>
                    <div class="mb-3">
                        <label for="cpf_usuario" class="form-label">CPF</label>
                        <input type="text" inputmode="numeric" class="form-control" id="cpf_usuario" name="cpf_usuario">
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha">
                    </div>
                    <div class="mb-3">
                        <label for="senha_confirm" class="form-label">Confirmar senha</label>
                        <input type="password" class="form-control" id="senha_confirm" name="senha_confirm">
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary d-flex align-items-center">Cadastrar</button>
                        <a href="./index.php" class="btn btn-secondary ms-3">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>

<script>
    const form = document.querySelector('form');
    form.addEventListener('submit', (e) => {
        const s1 = document.getElementById('senha').value;
        const s2 = document.getElementById('senha_confirm').value;
        if (s1 !== s2) {
            e.preventDefault();
            alert('As senhas não coincidem.');
        }
    });
</script>

  </body>
</html>