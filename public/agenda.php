<?php
// dental-agenda/public/agenda.php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth/check.php';
require_once __DIR__ . '/../app/config/db.php';

$user = $_SESSION['user'];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$pdo = db();

$erro = '';
$sucesso = '';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function only_digits(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }

function tipo_label(string $tipo): string {
    return match ($tipo) {
        'avaliacao' => 'Avaliação',
        'consulta'  => 'Consulta',
        'retorno'   => 'Retorno',
        default     => $tipo,
    };
}
function status_label(string $status): string {
    return match ($status) {
        'agendado'   => 'Agendado',
        'confirmado' => 'Confirmado',
        'concluido'  => 'Concluído',
        'cancelado'  => 'Cancelado',
        default      => $status,
    };
}
function status_badge_class(string $status): string {
    return match ($status) {
        'agendado'   => 'text-bg-primary',
        'confirmado' => 'text-bg-info',
        'concluido'  => 'text-bg-success',
        'cancelado'  => 'text-bg-secondary',
        default      => 'text-bg-light',
    };
}

// Carrega dentistas (users role dentista e ativo)
$dentStmt = $pdo->prepare("SELECT id, nome FROM users WHERE role = 'dentista' AND ativo = 1 ORDER BY nome ASC LIMIT 200");
$dentStmt->execute();
$dentists = $dentStmt->fetchAll();

// Carrega pacientes (ativos)
$patStmt = $pdo->prepare("SELECT id, nome FROM patients WHERE ativo = 1 ORDER BY nome ASC LIMIT 500");
$patStmt->execute();
$patientsList = $patStmt->fetchAll();

// Estado / ações
$action = $_GET['action'] ?? 'list'; // list | new | edit
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Filtros
$filter_date = trim((string)($_GET['date'] ?? ''));
if ($filter_date === '') {
    $filter_date = date('Y-m-d'); // padrão: hoje
}
$filter_dentist = (int)($_GET['dentist'] ?? 0);
$filter_status = trim((string)($_GET['status'] ?? ''));

// Processa POST (create / update / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['post_action'] ?? '');
    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $erro = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    } else {
        try {
            if ($postAction === 'create') {
                $patient_id = (int)($_POST['patient_id'] ?? 0);
                $dentist_id = (int)($_POST['dentist_id'] ?? 0);
                $data = trim((string)($_POST['data'] ?? ''));
                $hora = trim((string)($_POST['hora'] ?? ''));
                $tipo = trim((string)($_POST['tipo'] ?? 'avaliacao'));
                $status = trim((string)($_POST['status'] ?? 'agendado'));
                $obs = trim((string)($_POST['observacoes'] ?? ''));

                if ($patient_id <= 0 || $dentist_id <= 0) {
                    $erro = 'Selecione um paciente e um dentista.';
                } elseif ($data === '' || $hora === '') {
                    $erro = 'Informe a data e a hora do agendamento.';
                } else {
                    // Checagem extra (além do UNIQUE)
                    $chk = $pdo->prepare("
                        SELECT id FROM appointments
                         WHERE dentist_id = :dentist_id AND data = :data AND hora = :hora
                         LIMIT 1
                    ");
                    $chk->execute([
                        ':dentist_id' => $dentist_id,
                        ':data' => $data,
                        ':hora' => $hora,
                    ]);
                    $conflict = $chk->fetch();

                    if ($conflict) {
                        $erro = '⚠️ Horário já ocupado para este dentista. Escolha outro horário.';
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO appointments (patient_id, dentist_id, data, hora, tipo, status, observacoes)
                            VALUES (:patient_id, :dentist_id, :data, :hora, :tipo, :status, :obs)
                        ");
                        $ins->execute([
                            ':patient_id' => $patient_id,
                            ':dentist_id' => $dentist_id,
                            ':data' => $data,
                            ':hora' => $hora,
                            ':tipo' => $tipo,
                            ':status' => $status,
                            ':obs' => $obs !== '' ? $obs : null,
                        ]);

                        header('Location: agenda.php?msg=created&date=' . urlencode($data) . '&dentist=' . $dentist_id);
                        exit;
                    }
                }
            }

            if ($postAction === 'update') {
                $aid = (int)($_POST['id'] ?? 0);
                $patient_id = (int)($_POST['patient_id'] ?? 0);
                $dentist_id = (int)($_POST['dentist_id'] ?? 0);
                $data = trim((string)($_POST['data'] ?? ''));
                $hora = trim((string)($_POST['hora'] ?? ''));
                $tipo = trim((string)($_POST['tipo'] ?? 'avaliacao'));
                $status = trim((string)($_POST['status'] ?? 'agendado'));
                $obs = trim((string)($_POST['observacoes'] ?? ''));

                if ($aid <= 0) {
                    $erro = 'Agendamento inválido.';
                } elseif ($patient_id <= 0 || $dentist_id <= 0) {
                    $erro = 'Selecione um paciente e um dentista.';
                } elseif ($data === '' || $hora === '') {
                    $erro = 'Informe a data e a hora do agendamento.';
                } else {
                    // Checa conflito ignorando o próprio ID
                    $chk = $pdo->prepare("
                        SELECT id FROM appointments
                         WHERE dentist_id = :dentist_id
                           AND data = :data
                           AND hora = :hora
                           AND id <> :id
                         LIMIT 1
                    ");
                    $chk->execute([
                        ':dentist_id' => $dentist_id,
                        ':data' => $data,
                        ':hora' => $hora,
                        ':id' => $aid,
                    ]);
                    $conflict = $chk->fetch();

                    if ($conflict) {
                        $erro = '⚠️ Horário já ocupado para este dentista. Escolha outro horário.';
                    } else {
                        $upd = $pdo->prepare("
                            UPDATE appointments
                               SET patient_id = :patient_id,
                                   dentist_id = :dentist_id,
                                   data = :data,
                                   hora = :hora,
                                   tipo = :tipo,
                                   status = :status,
                                   observacoes = :obs
                             WHERE id = :id
                             LIMIT 1
                        ");
                        $upd->execute([
                            ':patient_id' => $patient_id,
                            ':dentist_id' => $dentist_id,
                            ':data' => $data,
                            ':hora' => $hora,
                            ':tipo' => $tipo,
                            ':status' => $status,
                            ':obs' => $obs !== '' ? $obs : null,
                            ':id' => $aid,
                        ]);

                        header('Location: agenda.php?msg=updated&date=' . urlencode($data) . '&dentist=' . $dentist_id);
                        exit;
                    }
                }
            }

            if ($postAction === 'delete') {
                $aid = (int)($_POST['id'] ?? 0);
                if ($aid <= 0) {
                    $erro = 'Agendamento inválido.';
                } else {
                    $del = $pdo->prepare("DELETE FROM appointments WHERE id = :id LIMIT 1");
                    $del->execute([':id' => $aid]);

                    header('Location: agenda.php?msg=deleted&date=' . urlencode($filter_date) . '&dentist=' . $filter_dentist);
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Se bater no UNIQUE (horário duplicado)
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $erro = '⚠️ Horário já ocupado para este dentista. Escolha outro horário.';
            } else {
                $erro = 'Erro no banco. Verifique o phpMyAdmin/conexão.';
            }
        } catch (Throwable $e) {
            $erro = 'Erro inesperado. Tente novamente.';
        }
    }
}

// Mensagens PRG
$msg = (string)($_GET['msg'] ?? '');
if ($msg === 'created') $sucesso = 'Agendamento criado com sucesso! ✅';
if ($msg === 'updated') $sucesso = 'Agendamento atualizado com sucesso! ✅';
if ($msg === 'deleted') $sucesso = 'Agendamento excluído com sucesso! ✅';

// Carrega para edição
$editing = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $editing = $stmt->fetch();

    if (!$editing) {
        $erro = 'Agendamento não encontrado.';
        $action = 'list';
    }
}

// Monta query de lista com filtros
$where = "WHERE a.data = :data";
$params = [':data' => $filter_date];

if ($filter_dentist > 0) {
    $where .= " AND a.dentist_id = :dentist";
    $params[':dentist'] = $filter_dentist;
}
if ($filter_status !== '') {
    $where .= " AND a.status = :status";
    $params[':status'] = $filter_status;
}

// Lista
$list = $pdo->prepare("
    SELECT a.id, a.data, a.hora, a.tipo, a.status, a.observacoes,
           p.nome AS paciente_nome,
           u.nome AS dentista_nome
      FROM appointments a
      JOIN patients p ON p.id = a.patient_id
      JOIN users u ON u.id = a.dentist_id
      $where
  ORDER BY a.hora ASC, a.id DESC
  LIMIT 300
");
$list->execute($params);
$appointments = $list->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../assets/img/icon.png" type="image/png">
  <title>Dental Agenda</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">

  <style>
    .page-header { border-radius: 16px; }
    .table td, .table th { vertical-align: middle; }
    .badge-soft {
      background: rgba(13,110,253,.10);
      border: 1px solid rgba(13,110,253,.18);
      color: #0b5ed7;
    }
    .btn-icon { display:inline-flex; align-items:center; gap:.35rem; }
  </style>
</head>
<body class="bg-soft">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="dashboard.php">🦷 Dental Agenda</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navTop">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navTop">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
        <li class="nav-item">
          <span class="navbar-text small">
            <b><?= h($user['nome']) ?></b>
            <span class="badge text-bg-light ms-2"><?= h($user['role']) ?></span>
          </span>
        </li>
        <li class="nav-item">
          <a class="btn btn-outline-light btn-sm ms-lg-3" href="logout.php">Sair</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4">

  <div class="card border-0 shadow-sm page-header mb-4">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div>
        <h1 class="h4 mb-1">Agenda</h1>
        <p class="text-muted mb-0">Agendamentos com filtro e bloqueio de horário por dentista. 🔵⚪</p>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="dashboard.php">← Voltar</a>
        <a class="btn btn-primary btn-icon" href="agenda.php?action=new&date=<?= h($filter_date) ?>&dentist=<?= (int)$filter_dentist ?>">
          ➕ <span>Novo Agendamento</span>
        </a>
      </div>
    </div>
  </div>

  <?php if ($sucesso): ?>
    <div class="alert alert-success"><?= h($sucesso) ?></div>
  <?php endif; ?>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get">
        <input type="hidden" name="action" value="list">

        <div class="col-12 col-md-3">
          <label class="form-label">Data</label>
          <input type="date" class="form-control" name="date" value="<?= h($filter_date) ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Dentista</label>
          <select class="form-select" name="dentist">
            <option value="0">Todos</option>
            <?php foreach ($dentists as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= ((int)$d['id'] === $filter_dentist) ? 'selected' : '' ?>>
                <?= h((string)$d['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">Todos</option>
            <?php
              $statusOptions = ['agendado','confirmado','concluido','cancelado'];
              foreach ($statusOptions as $st):
            ?>
              <option value="<?= h($st) ?>" <?= ($st === $filter_status) ? 'selected' : '' ?>>
                <?= h(status_label($st)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
          <button class="btn btn-outline-primary w-100" type="submit">🔎 Filtrar</button>
          <a class="btn btn-outline-secondary w-100" href="agenda.php">Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Form (novo/editar) -->
  <?php if ($action === 'new' || $action === 'edit'): ?>
    <?php
      $isEdit = $action === 'edit' && is_array($editing);
      $formTitle = $isEdit ? 'Editar Agendamento' : 'Novo Agendamento';

      $valPatient = (int)($isEdit ? $editing['patient_id'] : (int)($_POST['patient_id'] ?? 0));
      $valDentist = (int)($isEdit ? $editing['dentist_id'] : (int)($_POST['dentist_id'] ?? ($filter_dentist ?: 0)));
      $valData    = (string)($isEdit ? $editing['data'] : (string)($_POST['data'] ?? $filter_date));
      $valHora    = (string)($isEdit ? $editing['hora'] : (string)($_POST['hora'] ?? ''));
      $valTipo    = (string)($isEdit ? $editing['tipo'] : (string)($_POST['tipo'] ?? 'avaliacao'));
      $valStatus  = (string)($isEdit ? $editing['status'] : (string)($_POST['status'] ?? 'agendado'));
      $valObs     = (string)($isEdit ? ($editing['observacoes'] ?? '') : (string)($_POST['observacoes'] ?? ''));
    ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <h2 class="h6 mb-0"><?= h($formTitle) ?></h2>
          <span class="badge badge-soft">Formulário</span>
        </div>

        <form method="post" class="row g-3">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="post_action" value="<?= $isEdit ? 'update' : 'create' ?>">
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
          <?php endif; ?>

          <div class="col-12 col-md-6">
            <label class="form-label">Paciente <span class="text-danger">*</span></label>
            <select class="form-select" name="patient_id" required>
              <option value="0">Selecione...</option>
              <?php foreach ($patientsList as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= ((int)$p['id'] === $valPatient) ? 'selected' : '' ?>>
                  <?= h((string)$p['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Se não achar, cadastre em <b>Pacientes</b> primeiro.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Dentista <span class="text-danger">*</span></label>
            <select class="form-select" name="dentist_id" required>
              <option value="0">Selecione...</option>
              <?php foreach ($dentists as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= ((int)$d['id'] === $valDentist) ? 'selected' : '' ?>>
                  <?= h((string)$d['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Data <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="data" value="<?= h($valData) ?>" required>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Hora <span class="text-danger">*</span></label>
            <input type="time" class="form-control" name="hora" value="<?= h(substr($valHora,0,5)) ?>" required>
            <div class="form-text">O sistema bloqueia horários duplicados por dentista.</div>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="tipo">
              <?php foreach (['avaliacao','consulta','retorno'] as $t): ?>
                <option value="<?= h($t) ?>" <?= ($t === $valTipo) ? 'selected' : '' ?>>
                  <?= h(tipo_label($t)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <?php foreach (['agendado','confirmado','concluido','cancelado'] as $st): ?>
                <option value="<?= h($st) ?>" <?= ($st === $valStatus) ? 'selected' : '' ?>>
                  <?= h(status_label($st)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Observações</label>
            <textarea class="form-control" name="observacoes" rows="3" placeholder="Ex: queixa do paciente, preferências, avisos..."><?= h($valObs) ?></textarea>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-icon" type="submit">
              💾 <span><?= $isEdit ? 'Salvar alterações' : 'Criar agendamento' ?></span>
            </button>
            <a class="btn btn-outline-secondary" href="agenda.php?date=<?= h($filter_date) ?>&dentist=<?= (int)$filter_dentist ?>&status=<?= h($filter_status) ?>">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Lista -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h2 class="h6 mb-0">Agendamentos do dia</h2>
        <div class="text-muted small">
          Data: <b><?= h($filter_date) ?></b> • Total: <b><?= (int)count($appointments) ?></b>
        </div>
      </div>

      <?php if (!$appointments): ?>
        <div class="text-muted">Nenhum agendamento encontrado para os filtros atuais.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr class="text-muted">
                <th>Hora</th>
                <th>Paciente</th>
                <th>Dentista</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Obs</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($appointments as $a): ?>
                <tr>
                  <td class="fw-semibold"><?= h(substr((string)$a['hora'],0,5)) ?></td>
                  <td><?= h((string)$a['paciente_nome']) ?></td>
                  <td><?= h((string)$a['dentista_nome']) ?></td>
                  <td><?= h(tipo_label((string)$a['tipo'])) ?></td>
                  <td>
                    <span class="badge <?= h(status_badge_class((string)$a['status'])) ?>">
                      <?= h(status_label((string)$a['status'])) ?>
                    </span>
                  </td>
                  <td class="text-muted small"><?= h((string)($a['observacoes'] ?? '')) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a class="btn btn-outline-primary btn-sm"
                         href="agenda.php?action=edit&id=<?= (int)$a['id'] ?>&date=<?= h($filter_date) ?>&dentist=<?= (int)$filter_dentist ?>&status=<?= h($filter_status) ?>">
                        ✏️ Editar
                      </a>

                      <form method="post" class="d-inline"
                            onsubmit="return confirm('Tem certeza que deseja excluir este agendamento? Essa ação não pode ser desfeita.');">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="post_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">🗑️ Excluir</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
