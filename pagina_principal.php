<?php
session_start();
if (empty($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  </head>
  <body>
    <div class="text-center mt-0">
        <img src="./logo.png" alt="logo" class="d-block mx-auto mb-1" alt="logo" style="max-width: 160px; height: auto;">
    </div>

    <div class="container d-flex justify-content-center align-items-center vh-100 fixed-top">
        <div class="card shadow" style="width: 30rem;">
            <div class="card-body">
                <h3 class="card-title d-flex justify-content-center">Menu</h3>
                <a href="./ponto.php" class="btn btn-primary d-flex justify-content-center mb-3 mt-3">Bater Ponto</a>
                <a href="./editar_cadastro.php" class="btn btn-secondary d-flex justify-content-center mb-3">Editar Cadastro</a>
                <a href="./export_folha.php" class="btn btn-secondary d-flex justify-content-center mb-3">Gerar folha de ponto</a>
                <a href="./redirecionando_logout.php" class="btn btn-danger d-flex justify-content-center mb-3">Sair</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
  </body>
</html>