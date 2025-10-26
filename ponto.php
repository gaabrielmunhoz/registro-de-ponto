<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/db_functions.php';

date_default_timezone_set('America/Sao_Paulo');

#protege a página

if (empty($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = (int) $_SESSION['id_usuario'];
$hoje = date('Y-m-d');
$agora = date('H:i:s');
$msg = "";

#busca ou cria a linha do dia para o usuário

function getOrCreatePontoHoje(int $id_usuario, string $data) {
    $row = db_select("ponto_dia", ["id_usuario" => $id_usuario, "data_ponto" => $data]);
    if (!$row) {
        db_insert("ponto_dia", [
            "id_usuario" => $id_usuario,
            "data_ponto" => $data,
            "status_dia" => "INCOMPLETO"
        ]);
        $row = db_select("ponto_dia", ["id_usuario" => $id_usuario, "data_ponto" => $data]);
    }
    return $row ? $row[0] : null;
}
# valida o fluxo, não pode marcar fim almoço sem ter marcado o inicio
function validaSequencia(array $p, string $campo) : ?string {
    $deps = [
        "inicio_almoco" => ["entrada"],
        "fim_almoco" => ["entrada","inicio_almoco"],
        "inicio_lanche" => ["entrada"],
        "fim_lanche" => ["entrada","inicio_lanche"],
        "saida" => ["entrada"]
    ];
    if (!isset($deps[$campo])) return null;
    foreach ($deps[$campo] as $d) {
        if (empty($p[$d])) return "Antes de registrar ".label($campo).", registre ".label($d).".";
    }
    return null;
}

#rótulos bonitos
function label(string $campo): string {
    return [
        "entrada" => "Entrada",
        "inicio_almoco" => "Início do Almoço",
        "fim_almoco" => "Fim do Almoço",
        "inicio_lanche" => "Início do Lanche",
        "fim_lanche" => "Fim do Lanche",
        "saida" => "Saída",
    ][$campo] ?? $campo;
}

#calcula horas trabalhadas quando necessário
function recalculaHorasTrabalhadas(array $p): ?string {
    if (empty($p['entrada']) || empty($p['saida'])) return null;

    $entrada = strtotime($p['data_ponto'].' '.$p['entrada']);
    $saida = strtotime($p['data_ponto'].' '.$p['saida']);
    if ($saida <= $entrada) return null;

    $total = $saida - $entrada;

    if (!empty($p['inicio_almoco']) && !empty($p['fim_almoco'])) {
        $total -= (strtotime($p['data_ponto'].' '.$p['fim_almoco']) - strtotime($p['data_ponto'].' '.$p['inicio_almoco']));
    }
    if (!empty($p['inicio_lanche']) && !empty($p['fim_lanche'])) {
        $total -= (strtotime($p['data_ponto'].' '.$p['fim_lanche']) - strtotime($p['data_ponto'].' '.$p['inicio_lanche']));
    }

    $h = floor($total / 3600);
    $m = floor(($total % 3600) / 60);
    return sprintf("%02d:%02d", $h, $m);
}

#carrega/cria o registro do dia
const CENTRO_LAT = -15.8355944; // latitude
const CENTRO_LON = -47.9161422; // logitude
const RAIO_KM    = 0.30;       // 0.30 km = 300 metros

function haversineKm($lat1,$lon1,$lat2,$lon2){
    $R=6371;$dLat=deg2rad($lat2-$lat1);$dLon=deg2rad($lon2-$lon1);
    $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return $R*2*asin(min(1, sqrt($a)));
}

const GEOFENCE_BYPASS_IDS = [6]; // permite marcar o ponto sem localização, trocar pelo id do funcionário híbrido/remoto

function geofenceBypass(int $id): bool {
    return in_array($id, GEOFENCE_BYPASS_IDS, true);
}

function podeAdicionarJustificativa(array $pontoHoje) : bool {
    $qtd = (int)($pontoHoje["qtd_justificativas"] ?? 0);
    return $qtd < 1;
}

function anexarJustificativa(string $atual, string $nova, string $acao = ''): string {
    $stamp = date('H:i');
    $prefixo = $acao ? "[$stamp][$acao]" : "[$stamp]";
    $novaLinha = trim($prefixo.' '.trim($nova));
    $atual = trim((string)$atual);
    return $atual === '' ? $novaLinha : ($atual . "\n" . $novaLinha);
}


try {
    $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);
} catch (Throwable $e) {
    die('Erro ao acessar o banco: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lê a ação (considera o envio programático do JS)
    $acao = $_POST['action'] ?? ($_POST['action_js'] ?? '');

    // Ações que exigem geofence
    $skipGeofence = geofenceBypass($id_usuario);
    $acoesGeofenced = ["entrada","inicio_almoco","fim_almoco","inicio_lanche","fim_lanche","saida"];

    // Pré-checagem de geofence (preenche $msg se estiver fora ou sem localização)
    if (in_array($acao, $acoesGeofenced, true) && !$skipGeofence) {
        $lat = (isset($_POST['lat']) && $_POST['lat'] !== '') ? floatval($_POST['lat']) : null;
        $lon = (isset($_POST['lon']) && $_POST['lon'] !== '') ? floatval($_POST['lon']) : null;

        if ($lat === null || $lon === null) {
            $msg = "Localização obrigatória para registrar ".label($acao).".";
        } else {
            $distKm = haversineKm($lat, $lon, CENTRO_LAT, CENTRO_LON);
            if ($distKm > RAIO_KM) {
                $msg = "Fora da área permitida (".round($distKm*1000)." m > ".(RAIO_KM*1000)." m).";
            }
        }
    }

    // Campos válidos de batida
    $camposValidos = ["entrada", "inicio_almoco", "fim_almoco", "inicio_lanche", "fim_lanche", "saida"];

    if (in_array($acao, $camposValidos, true)) {

        // FREIO: se geofence barrou ($msg não vazio), não grava
        if (in_array($acao, $acoesGeofenced, true) && !$skipGeofence && $msg !== "") {
            // só exibe a mensagem; não persiste nada
        } else {
            $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);
            if (!empty($pontoHoje[$acao])) {
                $msg = label($acao) . " já foi registrado(a) em ".$pontoHoje[$acao].".";
            } else {
                if ($erro = validaSequencia($pontoHoje, $acao)) {
                    $msg = $erro;
                } else {
                    db_update("ponto_dia", [$acao => $agora], ["id_usuario" => $id_usuario, "data_ponto" => $hoje]);
                    $msg = label($acao)." registrado(a) às ".$agora.".";
                    $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);

                    if ($acao === "saida" || (!empty($pontoHoje['entrada']) && !empty($pontoHoje['saida']))) {
                        $horas = recalculaHorasTrabalhadas($pontoHoje);
                        if ($horas) {
                            db_update("ponto_dia", ["horas_trabalhadas" => $horas, "status_dia" => "COMPLETO"], ["id_usuario"=>$id_usuario, "data_ponto"=>$hoje]);
                            $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);
                        }
                    }
                }
            }
        }

    } elseif ($acao === 'inserir_manual') {
    $alvo = $_POST['alvo_manual'] ?? '';
    $hora = $_POST['hora_manual'] ?? '';
    $just = trim($_POST['justificativa'] ?? '');

    if (!in_array($alvo, $camposValidos, true)) {
        $msg = "Campo de destino inválido.";
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        $msg = "Informe o horário (HH:MM).";
    } elseif ($just === '') {
        $msg = "Justificativa obrigatória para inserção manual.";
    } else {
        $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);

        if (!empty($pontoHoje[$alvo])) {
            $msg = label($alvo) . " já foi registrada em ".$pontoHoje[$alvo].".";
        } elseif (!podeAdicionarJustificativa($pontoHoje)) {
            $msg = "Você só pode registrar uma justificativa por dia.";
        } elseif ($erro = validaSequencia($pontoHoje, $alvo)) {
            $msg = $erro;
        } else {
            // grava a batida manual
            $ok1 = db_update("ponto_dia", [$alvo => $hora.":00"], ["id_usuario"=>$id_usuario, "data_ponto"=>$hoje]);

            // prepara e grava a justificativa + incrementa contador
            $observacoesNovas = anexarJustificativa($pontoHoje['observacoes'] ?? '', $just, "MANUAL:$alvo $hora");
            $ok2 = db_update("ponto_dia",
                [
                    "observacoes" => $observacoesNovas,
                    "qtd_justificativas" => (int)($pontoHoje['qtd_justificativas'] ?? 0) + 1
                ],
                ["id_usuario"=>$id_usuario, "data_ponto"=>$hoje]
            );

            if ($ok1 && $ok2) {
                $msg = label($alvo)." ajustada para ".$hora." e justificativa registrada.";
                $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);

                // recalcula horas se couber
                $horas = recalculaHorasTrabalhadas($pontoHoje);
                if ($horas) {
                    db_update("ponto_dia", ["horas_trabalhadas" => $horas], ["id_usuario"=>$id_usuario, "data_ponto"=>$hoje]);
                    $pontoHoje = getOrCreatePontoHoje($id_usuario, $hoje);
                }
            } else {
                $msg = "Falha ao salvar. Tente novamente.";
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
    <title>Registro de ponto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  </head>
  <body>
    <div class="text-center mt-0">
        <img src="./logo.png" alt="logo" class="d-block mx-auto mb-2" style="max-width:160px;height:auto;">
    </div>

    <div class="container d-flex justify-content-center align-items-center vh-100 mt-5">
        <div class="card shadow" style="width:34rem;">
            <div class="card-body">
                <h3 class="card-title text-center mb-2">Registro de Ponto</h3>
                <?php if (!empty($msg)): ?>
                <div class="alert alert-info text-center py-2 mb-3"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                    <p class="text-center mb-1"><strong>Data:</strong> <?=date('d/m/Y', strtotime($hoje))?></p>
                    <p class="text-center mb-3"><strong>Hora:</strong> <?=date('H:i:s')?></p>

                <form method="POST" action="./ponto.php">
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lon" id="lon">
                    <input type="hidden" name="action_js" id="action_js">
                    <input type="hidden" id="gf_bypass" value="<?= geofenceBypass($id_usuario) ? '1' : '0' ?>">
                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" name="action" value="entrada" class="btn btn-success w-75" formnovalidate>Entrada</button>
                            <span class="ms-3"><?= $pontoHoje['entrada'] ?? '--:--:--' ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" name="action" value="inicio_almoco" class="btn btn-primary w-75" formnovalidate>Início Almoço</button>
                            <span class="ms-3"><?= $pontoHoje['inicio_almoco'] ?? '--:--:--' ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" name="action" value="fim_almoco" class="btn btn-primary w-75" formnovalidate>Fim Almoço</button>
                            <span class="ms-3"><?= $pontoHoje['fim_almoco'] ?? '--:--:--' ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" name="action" value="inicio_lanche" class="btn btn-primary w-75" formnovalidate>Início Lanche</button>
                            <span class="ms-3"><?= $pontoHoje['inicio_lanche'] ?? '--:--:--' ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" name="action" value="fim_lanche" class="btn btn-primary w-75" formnovalidate>Fim Lanche</button>
                            <span class="ms-3"><?= $pontoHoje['fim_lanche'] ?? '--:--:--' ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" name="action" value="saida" class="btn btn-danger w-75" formnovalidate>Saída</button>
                            <span class="ms-3"><?= $pontoHoje['saida'] ?? '--:--:--' ?></span>
                        </div>
                        <div class="mb-2">
                            <div class="small text-muted">Horas trabalhadas: <strong><?= $pontoHoje['horas_trabalhadas'] ?? '--:--' ?></strong></div>
                        </div>

                        <div class="mb-2">
                            <div class="small text-muted">
                                Justificativas hoje: 
                                <strong><?= (int)($pontoHoje['qtd_justificativas'] ?? 0) ?>/1</strong>
                            </div>
                        </div>
                        
                        <div class="mb-3 text-center">
                        <label class="form-label ms-3 me-3">Inserir manualmente</label>

                        <div class="d-flex flex-column align-items-center gap-2">
                            <div class="input-group w-75">
                            <select class="form-select" name="alvo_manual" required>
                                <option value="" selected disabled>Escolha o registro</option>
                                <option value="entrada">Entrada</option>
                                <option value="inicio_almoco">Início Almoço</option>
                                <option value="fim_almoco">Fim Almoço</option>
                                <option value="inicio_lanche">Início Lanche</option>
                                <option value="fim_lanche">Fim Lanche</option>
                                <option value="saida">Saída</option>
                            </select>
                            <input type="time" class="form-control" name="hora_manual" step="60" min="00:00" max="23:59" required>
                            </div>

                            <div class="input-group w-75">
                            <input type="text" class="form-control" name="justificativa"
                                    placeholder="Digite sua justificativa" required>
                            <button class="btn btn-outline-primary" type="submit" name="action" value="inserir_manual">
                                Enviar
                            </button>
                            </div>

                            <?php if (!empty($pontoHoje['observacoes'])): ?>
                            <pre class="mt-2 small bg-light p-2 w-75" style="white-space:pre-wrap;"><?= htmlspecialchars($pontoHoje['observacoes']) ?></pre>
                            <?php endif; ?>
                        </div>
                        </div>



                    </div>
                </form>
                <div class="d-flex  align-items-center"></div>
                <a href="./pagina_principal.php" class="btn btn-danger d-flex justify-content-center align-items-center mb-3">Voltar para o menu principal</a>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    <script>
        (function(){
            const form = document.querySelector('form[action="./ponto.php"]');
            const geofenced = new Set(['entrada','inicio_almoco','fim_almoco','inicio_lanche','fim_lanche','saida']);
            const bypass = document.getElementById('gf_bypass')?.value === '1';
            if (!form) return;

            form.addEventListener('submit', function(ev){
                const submitter = ev.submitter;
                const action = submitter ? submitter.value : '';
                if (!geofenced.has(action)) return;

                if (bypass) return; // liberado: não exige GPS

                ev.preventDefault();

                if (!navigator.geolocation) {
                alert("Seu navegador não disponibilizou geolocalização.");
                return;
                }

                navigator.geolocation.getCurrentPosition(function(pos){
                document.getElementById('lat').value = pos.coords.latitude;
                document.getElementById('lon').value = pos.coords.longitude;
                document.getElementById('action_js').value = action;
                form.submit();
                }, function(err){
                alert("Não foi possível obter sua localização: " + err.message);
                }, { enableHighAccuracy: true, timeout: 10000 });
            }, true);
            })();

    </script>

  </body>
</html>