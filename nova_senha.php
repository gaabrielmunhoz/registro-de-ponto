<?php
session_start();
require_once "db_functions.php";

if (empty($_SESSION["id_usuario"])) {
    header("Location: ./index.php");
    exit;
}
if (empty($_SESSION["can_change_password"])) {
    header("Location: ./alterar_senha_atual.php");
    exit;
}

$erro = "";
$okmsg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id    = (int) $_SESSION["id_usuario"];
    $senha = $_POST["senha"] ?? '';
    $conf  = $_POST["senha_confirm"] ?? '';

    // Validações básicas
    if ($senha === '' || $conf === '') {
        $erro = "Preencha e confirme a nova senha.";
    } elseif ($senha !== $conf) {
        $erro = "As senhas não coincidem.";
    }

    if ($erro === "") {
        // impedir senha igual à atual
        $res = db_select("usuario", ["id_usuario" => $id]);
        if (!$res) {
            $erro = "Usuário não encontrado.";
        } else {
            $hash_atual = $res[0]["senha_hash"] ?? '';
            if ($hash_atual && password_verify($senha, $hash_atual)) {
                $erro = "A nova senha não pode ser igual à senha atual.";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                // ATUALIZA 
                $ok = db_update("usuario",
                    [
                        "senha_hash" => $senha_hash,

                    ],
                    ["id_usuario" => $id]
                );

                if ($ok) {
                    unset($_SESSION["can_change_password"]); // nome correto
                    header("Location: ./redirecionando_menu.php?senha=ok");
                    exit;
                } else {
                    $erro = "Falha ao atualizar senha.";
                }
            }
        }
    }
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
    
    <div class="text-center mt-0">
        <img src="./logo.png" alt="logo" class="d-block mx-auto mb-1" alt="logo" style="max-width: 160px; height: auto;">
    </div>

    <div class="container d-flex justify-content-center align-items-center vh-100 fixed-top mt-3">
        <div class="card shadow" style="width: 30rem;">
            <div class="card-body">
                <h3 class="card-title d-flex justify-content-center ">Insira sua nova senha</h3>
                <?php if (!empty($erro)): ?>
                <div class="alert alert-danger text-center py-2 mb-3"><?= htmlspecialchars($erro) ?></div>
                <?php elseif (!empty($okmsg)): ?>
                <div class="alert alert-success text-center py-2 mb-3"><?= htmlspecialchars($okmsg) ?></div>
                <?php endif; ?>
                <form method="POST" action="./nova_senha.php">
                    <div class="mb-3">
                        <label for="senha" class="form-label">Nova senha</label>
                        <input type="password" class="form-control" id="senha" name="senha">
                    </div>
                    <div class="mb-3">
                        <label for="senha_confirm" class="form-label">Confirmar nova senha</label>
                        <input type="password" class="form-control" id="senha_confirm" name="senha_confirm">
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary d-flex align-items-center">Alterar</button>
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