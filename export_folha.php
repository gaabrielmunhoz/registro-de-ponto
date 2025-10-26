<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Composer
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('Composer autoload não encontrado em <code>vendor/autoload.php</code>. Rode <code>composer require phpoffice/phpspreadsheet:^4.0</code> na pasta deste arquivo.');
}
require $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Conexão
if (file_exists(__DIR__ . '/db_functions.php')) {
    require_once __DIR__ . '/db_functions.php';
}

// IDs de estagiários (6h/dia). O restante fica com 8h/dia.
const ESTAGIARIO_IDS = [1];

// jornada de sábado é reduzida
function jornadaMinPorDia(int $idUsuario, string $dataYmd): int {
    $dow = (int)(new DateTime($dataYmd))->format('N');
    if ($dow === 7) return 0;
    if ($dow === 6) return 4 * 60;
    return in_array($idUsuario, ESTAGIARIO_IDS, true) ? (6 * 60) : (8 * 60);
}

function db_conn(): mysqli {
    if (function_exists('db_connect')) return db_connect();
    $conn = new mysqli("localhost", "root", "", "EllaDePrata");
    if ($conn->connect_error) { http_response_code(500); die("Erro de conexão: ".$conn->connect_error); }
    $conn->set_charset('utf8mb4');
    return $conn;
}
$conn = db_conn();

// Helpers tempo
function minutos_para_hhmm(int $min): string {
    $h = intdiv($min, 60); $m = $min % 60; return sprintf('%d:%02d', $h, $m);
}
function diff_minutos(?string $ini, ?string $fim): int {
    if (!$ini || !$fim) return 0;
    $a = strtotime($ini); $b = strtotime($fim);
    return max(0, (int) round(($b - $a) / 60));
}
function coluna_existe(mysqli $conn, string $tabela, string $col): bool {
    $sql = "SHOW COLUMNS FROM `{$tabela}`";
    if (!$res = $conn->query($sql)) return false;
    while ($row = $res->fetch_assoc()) {
        if (strcasecmp($row['Field'] ?? '', $col) === 0) return true;
    }
    return false;
}

// Acesso a dados
function listar_usuarios_ativos(mysqli $conn): array {
    $lista = [];
    $sql = "SELECT id_usuario, nome_usuario FROM usuario WHERE status_usuario = 'ATIVO' ORDER BY nome_usuario ASC";
    if ($res = $conn->query($sql)) while ($row = $res->fetch_assoc()) $lista[] = $row;
    return $lista;
}
function buscar_usuario(mysqli $conn, int $idUsuario): ?array {
    $sql = "SELECT id_usuario, nome_usuario FROM usuario WHERE id_usuario = ?";
    $st = $conn->prepare($sql); $st->bind_param("i", $idUsuario); $st->execute();
    $r = $st->get_result(); return $r->fetch_assoc() ?: null;
}
function buscar_registros_mes(mysqli $conn, int $idUsuario, string $anoMes): array {
    $tem_inicio_almoco   = coluna_existe($conn, 'ponto_dia', 'inicio_almoco');
    $tem_fim_almoco      = coluna_existe($conn, 'ponto_dia', 'fim_almoco');
    $tem_observacoes     = coluna_existe($conn, 'ponto_dia', 'observacoes');
    $tem_qtd_just        = coluna_existe($conn, 'ponto_dia', 'qtd_justificativas');
    $tem_horas_trab      = coluna_existe($conn, 'ponto_dia', 'horas_trabalhadas');
    $tem_minutos_trab    = coluna_existe($conn, 'ponto_dia', 'minutos_trabalhados');

    $campos = [
        "data_ponto",
        "entrada",
        "saida",
        $tem_inicio_almoco ? "inicio_almoco" : "NULL AS inicio_almoco",
        $tem_fim_almoco    ? "fim_almoco"    : "NULL AS fim_almoco",
        $tem_horas_trab    ? "horas_trabalhadas"    : "NULL AS horas_trabalhadas",
        $tem_minutos_trab  ? "minutos_trabalhados"  : "NULL AS minutos_trabalhados",
        $tem_observacoes   ? "observacoes"          : "NULL AS observacoes",
        $tem_qtd_just      ? "qtd_justificativas"   : "0 AS qtd_justificativas",
    ];

    $sql = "
        SELECT ".implode(", ", $campos)."
        FROM ponto_dia
        WHERE id_usuario = ?
          AND DATE_FORMAT(data_ponto, '%Y-%m') = ?
        ORDER BY data_ponto ASC
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("is", $idUsuario, $anoMes);
    $st->execute();
    $res = $st->get_result();

    $linhas = [];
    while ($row = $res->fetch_assoc()) {
        if (empty($row['minutos_trabalhados'])) {
            $min = 0;
            $ini = $row['entrada'] ?? null;
            $iAl = $row['inicio_almoco'] ?? null;
            $fAl = $row['fim_almoco'] ?? null;
            $fim = $row['saida'] ?? null;

            $min += diff_minutos($ini, $iAl ?: $fim);
            if (!empty($fAl) && !empty($fim)) {
                $min += diff_minutos($fAl, $fim);
            }
            $row['minutos_trabalhados'] = $min;
        }

        $row['observacoes'] = (string)($row['observacoes'] ?? '');
        $row['qtd_justificativas'] = (int)($row['qtd_justificativas'] ?? 0);

        $row['_dia_ativo'] = (
            !empty($row['entrada']) ||
            !empty($row['saida']) ||
            !empty($row['inicio_almoco']) ||
            !empty($row['fim_almoco']) ||
            ((int)$row['minutos_trabalhados'] > 0)
        ) ? 1 : 0;

        $linhas[] = $row;
    }
    return $linhas;
}

// Gerar Excel
function gerar_planilha(string $anoMes, array $usuario, array $linhas): Spreadsheet {
    $idUsuario = (int)($usuario['id_usuario'] ?? 0);

    $hhmm_para_min = function (?string $hhmm): int {
        if (!$hhmm || !preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) return 0;
        return (int)$m[1]*60 + (int)$m[2];
    };
    if (!function_exists('minutos_para_hhmm')) {
        function minutos_para_hhmm(int $min): string {
            $h = intdiv($min, 60); $m = $min % 60; return sprintf('%02d:%02d', $h, $m);
        }
    }
    $signed_hhmm = function (int $min): string {
        $sig = $min < 0 ? '-' : ($min > 0 ? '+' : '');
        return $sig . minutos_para_hhmm(abs($min));
    };

    $ss = new Spreadsheet();
    $sh = $ss->getActiveSheet();
    $sh->setTitle("Folha $anoMes");

    $sh->setCellValue('A1', 'Folha de Ponto');
    $sh->setCellValue('A2', 'Funcionário: ' . ($usuario['nome_usuario'] ?? ''));
    $sh->setCellValue('A3', 'Referência: ' . $anoMes);

    $sh->fromArray(
        ['Dia','Entrada','Início Almoço','Fim Almoço','Saída','Total (h:mm)','Meta (h/dia)','Banco do dia','Status','Justificativas'],
        null, 'A5'
    );

    $linha = 6;
    $totalMin = 0;
    $bancoMesMin = 0;
    $metaAcumulada = 0;

    foreach ($linhas as $l) {
        $min_dia = isset($l['minutos_trabalhados'])
            ? (int)$l['minutos_trabalhados']
            : $hhmm_para_min($l['horas_trabalhadas'] ?? '');

        $totalMin += $min_dia;

        $metaMinDia = jornadaMinPorDia($idUsuario, $l['data_ponto']);
        $saldoDia = $min_dia - $metaMinDia;

        $dow = (int)(new DateTime($l['data_ponto']))->format('N');
        if ($dow === 6 && $saldoDia > 0) $saldoDia = 0;
        if ($dow === 7) $saldoDia = 0;

        $bancoMesMin += $saldoDia;

        if (!empty($l['_dia_ativo'])) $metaAcumulada += $metaMinDia;

        $sh->setCellValue("A$linha", date('d/m/Y', strtotime($l['data_ponto'])));
        $sh->setCellValue("B$linha", !empty($l['entrada'])        ? substr($l['entrada'], 0, 5)        : '');
        $sh->setCellValue("C$linha", !empty($l['inicio_almoco'])  ? substr($l['inicio_almoco'], 0, 5)  : '');
        $sh->setCellValue("D$linha", !empty($l['fim_almoco'])     ? substr($l['fim_almoco'], 0, 5)     : '');
        $sh->setCellValue("E$linha", !empty($l['saida'])          ? substr($l['saida'], 0, 5)          : '');
        $sh->setCellValue("F$linha",
            isset($l['minutos_trabalhados'])
                ? minutos_para_hhmm((int)$l['minutos_trabalhados'])
                : ($l['horas_trabalhadas'] ?? '')
        );

        $sh->setCellValue("G$linha", minutos_para_hhmm($metaMinDia));
        $sh->setCellValue("H$linha", $signed_hhmm($saldoDia));
        $sh->setCellValue("I$linha",
            $saldoDia > 0 ? 'Hora extra' : ($saldoDia < 0 ? 'Dívida' : 'OK')
        );

        $obs = str_replace(["\r\n","\r"], "\n", $l['observacoes'] ?? '');
        $sh->setCellValueExplicit("J$linha", $obs, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $linha++;
    }

    $sh->setCellValue("E$linha", "Totais");
    $sh->setCellValue("F$linha", minutos_para_hhmm($totalMin));
    $sh->setCellValue("G$linha", "Meta acumulada");
    $sh->setCellValue("H$linha", minutos_para_hhmm($metaAcumulada));
    $sh->setCellValue("I$linha", "Banco do mês");
    $sh->setCellValue("J$linha", $signed_hhmm($bancoMesMin));

    foreach (range('A','I') as $col) $sh->getColumnDimension($col)->setAutoSize(true);

    $sh->getStyle("J6:J".($linha-1))->getAlignment()->setWrapText(true);
    $sh->getColumnDimension('J')->setAutoSize(false);
    $sh->getColumnDimension('J')->setWidth(60);

    for ($r = 6; $r < $linha; $r++) {
        $status = $sh->getCell("I$r")->getValue();
        if ($status === 'Hora extra') {
            $sh->getStyle("I$r")->getFont()->getColor()->setARGB('FF1F7A1F');
        } elseif ($status === 'Dívida') {
            $sh->getStyle("I$r")->getFont()->getColor()->setARGB('FFB00020');
        }
    }

    return $ss;
}

// Entrada / Ação
$usuariosAtivos = listar_usuarios_ativos($conn);

$idUsuario = filter_input(INPUT_GET, 'usuario', FILTER_VALIDATE_INT) ?: 0;
$anoMes    = isset($_GET['mes']) ? trim($_GET['mes']) : '';
$exportar  = ($idUsuario > 0 && preg_match('/^\d{4}-\d{2}$/', $anoMes));

if ($exportar) {
    $usuario = buscar_usuario($conn, $idUsuario);
    if (!$usuario) { http_response_code(404); die("Usuário não encontrado."); }

    $linhas = buscar_registros_mes($conn, $idUsuario, $anoMes);
    $ss = gerar_planilha($anoMes, $usuario, $linhas);
    $writer = new Xlsx($ss);

    $baseDir = __DIR__ . '/folhas';
    $dir     = $baseDir . '/' . $anoMes;
    $canSave = false;

    if (is_dir($dir) || @mkdir($dir, 0777, true)) {
        if (is_writable($dir)) $canSave = true;
    }

    $slug         = preg_replace('/[^a-z0-9\-_.]+/i', '_', strtolower($usuario['nome_usuario'] ?? 'usuario_'.$idUsuario));
    $downloadName = $slug . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$downloadName.'"');

    if ($canSave) {
        $path = $dir . '/' . $downloadName;
        try {
            $writer->save($path);
        } catch (Throwable $e) {
            $writer->save('php://output');
            exit;
        }
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
        $writer->save('php://output');
        exit;
    }
}

$valorMesDefault = date('Y-m');
$usuariosAtivos = $usuariosAtivos ?? [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Gerar Folha de Ponto</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="h4 mb-4 text-center">Gerar Folha de Ponto</h1>

            <form method="get" class="row g-3">
              <div class="col-12">
                <label class="form-label">Usuário</label>
                <select name="usuario" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php foreach ($usuariosAtivos as $u): ?>
                    <option value="<?= (int)$u['id_usuario'] ?>">
                      <?= htmlspecialchars($u['nome_usuario']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Mês</label>
                <input type="month" name="mes" class="form-control" required value="<?= htmlspecialchars($valorMesDefault) ?>">
                <label class="form-label">Ex: 2025-08</label>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary">Gerar Excel</button>
                <a href="./pagina_principal.php" class="btn btn-danger">Voltar</a>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
