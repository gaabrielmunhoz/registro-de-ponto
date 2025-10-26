<?php
session_start();
require_once "db_functions.php";

if (empty($_SESSION["id_usuario"])) {
    header("Location: ./index.php");
    exit;
}

$erro = "";
$okmsg = "";

$id = (int) $_SESSION["id_usuario"];
$atual = db_select("usuario", ["id_usuario" => $id]);
if (!$atual) {
    $erro = "Usuário não encontrado.";
    $atual = [["nome_usuario"=>"", "email_usuario"=>"", "cpf_usuario"=>""]];
}
$atual = $atual[0]; // linha atual do usuário

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Campos recebidos (podem vir iguais ao atual)
    $nome  = trim($_POST["nome_usuario"]  ?? "");
    $email = trim($_POST["email_usuario"] ?? "");
    $cpf   = preg_replace('/\D+/', '', $_POST["cpf_usuario"] ?? "");

    // Validações 
    if ($nome === "" || $email === "" || $cpf === "") {
        $erro = "Preencha nome, e-mail e CPF.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "E-mail inválido.";
    } elseif (strlen($cpf) !== 11) {
        $erro = "CPF deve conter 11 dígitos.";
    }

    // Checa duplicidade apenas se mudou
    if ($erro === "" && $cpf !== ($atual["cpf_usuario"] ?? "")) {
        $dup = db_select("usuario", ["cpf_usuario" => $cpf]);
        if (!empty($dup) && (int)$dup[0]["id_usuario"] !== $id) {
            $erro = "Já existe um usuário com esse CPF.";
        }
    }
    if ($erro === "" && $email !== ($atual["email_usuario"] ?? "")) {
        $dup = db_select("usuario", ["email_usuario" => $email]);
        if (!empty($dup) && (int)$dup[0]["id_usuario"] !== $id) {
            $erro = "Já existe um usuário com esse e-mail.";
        }
    }

    if ($erro === "") {
        // Atualiza só o que mudou (opcional, mas elegante)
        $updates = [];
        if ($nome  !== ($atual["nome_usuario"]  ?? "")) $updates["nome_usuario"]  = $nome;
        if ($email !== ($atual["email_usuario"] ?? "")) $updates["email_usuario"] = $email;
        if ($cpf   !== ($atual["cpf_usuario"]   ?? "")) $updates["cpf_usuario"]   = $cpf;

        if ($updates) {
            $ok = db_update("usuario", $updates, ["id_usuario" => $id]);
            if ($ok) {
                // Reflete no $_SESSION
                $_SESSION["nome_usuario"] = $nome;
                $_SESSION["cpf_usuario"]  = $cpf;

                header("Location: ./redirecionando_menu.php?ok=1");
                exit;
            } else {
                $erro = "Falha ao atualizar cadastro.";
            }
        } else {
            // Nada mudou
            header("Location: ./editar_cadastro.php?ok=1");
            exit;
        }
    }

    // Se chegou aqui, houve erro; reexibe com o que foi digitado
    $atual = ["nome_usuario"=>$nome, "email_usuario"=>$email, "cpf_usuario"=>$cpf];
}

if (isset($_GET["ok"]) && !$erro) {
    $okmsg = "Cadastro atualizado com sucesso!";
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edição de cadastro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="text-center mt-0">
    <img src="./logo.png" alt="logo" class="d-block mx-auto mb-1" style="max-width:160px;height:auto;">
  </div>

  <div class="container d-flex justify-content-center align-items-center vh-100 fixed-top mt-3">
    <div class="card shadow" style="width:30rem;">
      <div class="card-body">
        <h3 class="card-title text-center">Cadastro</h3>

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger text-center py-2 mb-3"><?= htmlspecialchars($erro) ?></div>
        <?php elseif (!empty($okmsg)): ?>
          <div class="alert alert-success text-center py-2 mb-3"><?= htmlspecialchars($okmsg) ?></div>
        <?php endif; ?>

        <!-- IMPORTANTE: action aponta para a PRÓPRIA página -->
        <form method="POST" action="./editar_cadastro.php" novalidate>
          <div class="mb-3">
            <label for="nome_usuario" class="form-label">Nome</label>
            <input type="text" class="form-control" id="nome_usuario" name="nome_usuario"
                   value="<?= htmlspecialchars($atual['nome_usuario'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label for="email_usuario" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email_usuario" name="email_usuario"
                   value="<?= htmlspecialchars($atual['email_usuario'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label for="cpf_usuario" class="form-label">CPF</label>
            <input type="text" inputmode="numeric" pattern="\d{11}" maxlength="14"
                   class="form-control" id="cpf_usuario" name="cpf_usuario"
                   value="<?= htmlspecialchars($atual['cpf_usuario'] ?? '') ?>" required>
            <div class="form-text">Somente números (11 dígitos).</div>
          </div>

          <div class="d-flex justify-content-center">
            <button type="submit" class="btn btn-danger">Atualizar cadastro</button>
            <a href="./alterar_senha_atual.php" class="btn btn-primary ms-3">Alterar senha</a>
            <a href="./pagina_principal.php" class="btn btn-secondary ms-3">Voltar</a>
          </div>
        </form>

      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
