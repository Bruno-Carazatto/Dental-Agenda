<?php
// dental-agenda/public/tratamentos.php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth/check.php';
require_once __DIR__ . '/../app/config/db.php';

$user = $_SESSION['user'];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$pdo = db();

$erro = '';
$sucesso = '';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function t_status_label(string $s): string {
    return match ($s) {
        'em_andamento' => 'Em andamento',
        'concluido'    => 'Concluído',
        'cancelado'    => 'Cancelado',
        default        => $s,
    };
}
function t_status_badge(string $s): string {
    return match ($s) {
        'em_andamento' => 'text-bg-primary',
        'concluido'    => 'text-bg-success',
        'cancelado'    => 'text-bg-secondary',
        default        => 'text-bg-light',
    };
}
function step_label(string $s): string {
    return match ($s) {
        'pendente'     => 'Pendente',
        'em_andamento' => 'Em andamento',
        'concluido'    => 'Concluído',
        default        => $s,
    };
}
function step_badge(string $s): string {
    return match ($s) {
        'pendente'     => 'text-bg-secondary',
        'em_andamento' => 'text-bg-info',
        'concluido'    => 'text-bg-success',
        default        => 'text-bg-light',
    };
}

// Selects
$patients = $pdo->query("SELECT id, nome FROM patients WHERE ativo = 1 ORDER BY nome ASC LIMIT 500")->fetchAll();
$dentists = $pdo->query("SELECT id, nome FROM users WHERE role = 'dentista' AND ativo = 1 ORDER BY nome ASC LIMIT 200")->fetchAll();
$procedures = $pdo->query("SELECT id, nome FROM procedures WHERE ativo = 1 ORDER BY nome ASC LIMIT 500")->fetchAll();

// Estado
$action = $_GET['action'] ?? 'list'; // list | new | edit | view
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Filtros listagem
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_patient = (int)($_GET['patient'] ?? 0);

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['post_action'] ?? '');
    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $erro = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    } else {
        try {
            // ========== TRATAMENTO (create/update/delete) ==========
            if ($postAction === 'create_treatment' || $postAction === 'update_treatment') {
                $tid = (int)($_POST['id'] ?? 0);
                $patient_id = (int)($_POST['patient_id'] ?? 0);
                $dentist_id = (int)($_POST['dentist_id'] ?? 0);
                $titulo = trim((string)($_POST['titulo'] ?? ''));
                $status = trim((string)($_POST['status'] ?? 'em_andamento'));
                $obs = trim((string)($_POST['observacoes'] ?? ''));

                if ($patient_id <= 0 || $dentist_id <= 0) {
                    $erro = 'Selecione paciente e dentista.';
                } elseif ($titulo === '') {
                    $erro = 'Informe o título do tratamento.';
                } else {
                    if ($postAction === 'create_treatment') {
                        $ins = $pdo->prepare("
                            INSERT INTO treatments (patient_id, dentist_id, titulo, status, observacoes)
                            VALUES (:patient_id, :dentist_id, :titulo, :status, :obs)
                        ");
                        $ins->execute([
                            ':patient_id' => $patient_id,
                            ':dentist_id' => $dentist_id,
                            ':titulo' => $titulo,
                            ':status' => $status,
                            ':obs' => $obs !== '' ? $obs : null,
                        ]);
                        $newId = (int)$pdo->lastInsertId();
                        header('Location: tratamentos.php?action=view&id=' . $newId . '&msg=created');
                        exit;
                    } else {
                        if ($tid <= 0) throw new RuntimeException('Tratamento inválido.');
                        $upd = $pdo->prepare("
                            UPDATE treatments
                               SET patient_id = :patient_id,
                                   dentist_id = :dentist_id,
                                   titulo = :titulo,
                                   status = :status,
                                   observacoes = :obs
                             WHERE id = :id
                             LIMIT 1
                        ");
                        $upd->execute([
                            ':patient_id' => $patient_id,
                            ':dentist_id' => $dentist_id,
                            ':titulo' => $titulo,
                            ':status' => $status,
                            ':obs' => $obs !== '' ? $obs : null,
                            ':id' => $tid,
                        ]);
                        header('Location: tratamentos.php?action=view&id=' . $tid . '&msg=updated');
                        exit;
                    }
                }
            }

            if ($postAction === 'delete_treatment') {
                $tid = (int)($_POST['id'] ?? 0);
                if ($tid <= 0) {
                    $erro = 'Tratamento inválido.';
                } else {
                    $del = $pdo->prepare("DELETE FROM treatments WHERE id = :id LIMIT 1");
                    $del->execute([':id' => $tid]);
                    header('Location: tratamentos.php?msg=deleted');
                    exit;
                }
            }

            // ========== ETAPAS (create/update/delete) ==========
            if ($postAction === 'add_step') {
                $tid = (int)($_POST['treatment_id'] ?? 0);
                $procedure_id = (int)($_POST['procedure_id'] ?? 0);
                $status = trim((string)($_POST['step_status'] ?? 'pendente'));
                $obs = trim((string)($_POST['step_observacoes'] ?? ''));

                if ($tid <= 0) {
                    $erro = 'Tratamento inválido.';
                } elseif ($procedure_id <= 0) {
                    $erro = 'Selecione um procedimento.';
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO treatment_steps (treatment_id, procedure_id, status, observacoes)
                        VALUES (:tid, :pid, :status, :obs)
                    ");
                    $ins->execute([
                        ':tid' => $tid,
                        ':pid' => $procedure_id,
                        ':status' => $status,
                        ':obs' => $obs !== '' ? $obs : null,
                    ]);
                    header('Location: tratamentos.php?action=view&id=' . $tid . '&msg=step_added');
                    exit;
                }
            }

            if ($postAction === 'update_step') {
                $sid = (int)($_POST['step_id'] ?? 0);
                $tid = (int)($_POST['treatment_id'] ?? 0);
                $status = trim((string)($_POST['step_status'] ?? 'pendente'));
                $obs = trim((string)($_POST['step_observacoes'] ?? ''));

                if ($sid <= 0 || $tid <= 0) {
                    $erro = 'Etapa inválida.';
                } else {
                    $upd = $pdo->prepare("
                        UPDATE treatment_steps
                           SET status = :status,
                               observacoes = :obs
                         WHERE id = :sid AND treatment_id = :tid
                         LIMIT 1
                    ");
                    $upd->execute([
                        ':status' => $status,
                        ':obs' => $obs !== '' ? $obs : null,
                        ':sid' => $sid,
                        ':tid' => $tid,
                    ]);
                    header('Location: tratamentos.php?action=view&id=' . $tid . '&msg=step_updated');
                    exit;
                }
            }

            if ($postAction === 'delete_step') {
                $sid = (int)($_POST['step_id'] ?? 0);
                $tid = (int)($_POST['treatment_id'] ?? 0);

                if ($sid <= 0 || $tid <= 0) {
                    $erro = 'Etapa inválida.';
                } else {
                    $del = $pdo->prepare("DELETE FROM treatment_steps WHERE id = :sid AND treatment_id = :tid LIMIT 1");
                    $del->execute([':sid' => $sid, ':tid' => $tid]);
                    header('Location: tratamentos.php?action=view&id=' . $tid . '&msg=step_deleted');
                    exit;
                }
            }

        } catch (Throwable $e) {
            $erro = 'Erro ao salvar. Verifique os dados e tente novamente.';
        }
    }
}

// Mensagens PRG
$msg = (string)($_GET['msg'] ?? '');
if ($msg === 'created') $sucesso = 'Tratamento criado com sucesso! ✅';
if ($msg === 'updated') $sucesso = 'Tratamento atualizado com sucesso! ✅';
if ($msg === 'deleted') $sucesso = 'Tratamento excluído com sucesso! ✅';
if ($msg === 'step_added') $sucesso = 'Etapa adicionada! ✅';
if ($msg === 'step_updated') $sucesso = 'Etapa atualizada! ✅';
if ($msg === 'step_deleted') $sucesso = 'Etapa excluída! ✅';

// Carrega tratamento para view/edit
$treatment = null;
$steps = [];
if (($action === 'view' || $action === 'edit') && $id > 0) {
    $st = $pdo->prepare("
        SELECT t.*, p.nome AS paciente_nome, u.nome AS dentista_nome
          FROM treatments t
          JOIN patients p ON p.id = t.patient_id
          JOIN users u ON u.id = t.dentist_id
         WHERE t.id = :id
         LIMIT 1
    ");
    $st->execute([':id' => $id]);
    $treatment = $st->fetch();

    if ($treatment) {
        $stp = $pdo->prepare("
            SELECT s.*, pr.nome AS procedimento_nome
              FROM treatment_steps s
              JOIN procedures pr ON pr.id = s.procedure_id
             WHERE s.treatment_id = :tid
          ORDER BY s.id ASC
        ");
        $stp->execute([':tid' => $id]);
        $steps = $stp->fetchAll();
    } else {
        $erro = 'Tratamento não encontrado.';
        $action = 'list';
    }
}

// Lista de tratamentos
$where = "WHERE 1=1";
$params = [];
if ($filter_status !== '') {
    $where .= " AND t.status = :st";
    $params[':st'] = $filter_status;
}
if ($filter_patient > 0) {
    $where .= " AND t.patient_id = :pid";
    $params[':pid'] = $filter_patient;
}

$list = $pdo->prepare("
    SELECT t.id, t.titulo, t.status, t.criado_em,
           p.nome AS paciente_nome,
           u.nome AS dentista_nome
      FROM treatments t
      JOIN patients p ON p.id = t.patient_id
      JOIN users u ON u.id = t.dentist_id
      $where
  ORDER BY t.id DESC
  LIMIT 200
");
$list->execute($params);
$treatments = $list->fetchAll();

// Defaults formulário
$isEdit = ($action === 'edit' && $treatment);
$formPatient = $isEdit ? (int)$treatment['patient_id'] : (int)($_POST['patient_id'] ?? 0);
$formDentist = $isEdit ? (int)$treatment['dentist_id'] : (int)($_POST['dentist_id'] ?? 0);
$formTitle = $isEdit ? (string)$treatment['titulo'] : (string)($_POST['titulo'] ?? '');
$formStatus = $isEdit ? (string)$treatment['status'] : (string)($_POST['status'] ?? 'em_andamento');
$formObs = $isEdit ? (string)($treatment['observacoes'] ?? '') : (string)($_POST['observacoes'] ?? '');
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
    .badge-soft {
      background: rgba(13,110,253,.10);
      border: 1px solid rgba(13,110,253,.18);
      color: #0b5ed7;
    }
    .table td, .table th { vertical-align: middle; }
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
        <h1 class="h4 mb-1">Tratamentos</h1>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="dashboard.php">← Voltar</a>
        <a class="btn btn-primary btn-icon" href="tratamentos.php?action=new">➕ <span>Novo Tratamento</span></a>
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
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">Todos</option>
            <?php foreach (['em_andamento' => 'Em andamento', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'] as $k => $lab): ?>
              <option value="<?= h($k) ?>" <?= $filter_status === $k ? 'selected' : '' ?>><?= h($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-7">
          <label class="form-label">Paciente</label>
          <select class="form-select" name="patient">
            <option value="0">Todos</option>
            <?php foreach ($patients as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((int)$p['id'] === $filter_patient) ? 'selected' : '' ?>>
                <?= h((string)$p['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
          <button class="btn btn-outline-primary w-100" type="submit">🔎 Filtrar</button>
          <a class="btn btn-outline-secondary w-100" href="tratamentos.php">Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Form Novo/Editar -->
  <?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <h2 class="h6 mb-0"><?= $isEdit ? 'Editar Tratamento' : 'Novo Tratamento' ?></h2>
          <span class="badge badge-soft">Formulário</span>
        </div>

        <form method="post" class="row g-3">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="post_action" value="<?= $isEdit ? 'update_treatment' : 'create_treatment' ?>">
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$treatment['id'] ?>">
          <?php endif; ?>

          <div class="col-12 col-md-6">
            <label class="form-label">Paciente <span class="text-danger">*</span></label>
            <select class="form-select" name="patient_id" required>
              <option value="0">Selecione...</option>
              <?php foreach ($patients as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= ((int)$p['id'] === $formPatient) ? 'selected' : '' ?>>
                  <?= h((string)$p['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Dentista <span class="text-danger">*</span></label>
            <select class="form-select" name="dentist_id" required>
              <option value="0">Selecione...</option>
              <?php foreach ($dentists as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= ((int)$d['id'] === $formDentist) ? 'selected' : '' ?>>
                  <?= h((string)$d['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Título <span class="text-danger">*</span></label>
            <input class="form-control" name="titulo" required value="<?= h($formTitle) ?>"
                   placeholder="Ex: Tratamento ortodôntico / Restaurações / Canal...">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="em_andamento" <?= $formStatus==='em_andamento'?'selected':'' ?>>Em andamento</option>
              <option value="concluido" <?= $formStatus==='concluido'?'selected':'' ?>>Concluído</option>
              <option value="cancelado" <?= $formStatus==='cancelado'?'selected':'' ?>>Cancelado</option>
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Observações</label>
            <input class="form-control" name="observacoes" value="<?= h($formObs) ?>" placeholder="Opcional">
          </div>

          <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-icon" type="submit">💾 <span><?= $isEdit ? 'Salvar' : 'Criar tratamento' ?></span></button>
            <a class="btn btn-outline-secondary" href="tratamentos.php">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- View (detalhes + etapas) -->
  <?php if ($action === 'view' && $treatment): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <div class="text-muted small">Tratamento #<?= (int)$treatment['id'] ?></div>
            <h2 class="h5 mb-1"><?= h((string)$treatment['titulo']) ?></h2>
            <div class="text-muted">
              Paciente: <b><?= h((string)$treatment['paciente_nome']) ?></b> •
              Dentista: <b><?= h((string)$treatment['dentista_nome']) ?></b>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge <?= h(t_status_badge((string)$treatment['status'])) ?>">
              <?= h(t_status_label((string)$treatment['status'])) ?>
            </span>
            <a class="btn btn-outline-primary btn-sm" href="tratamentos.php?action=edit&id=<?= (int)$treatment['id'] ?>">✏️ Editar</a>

            <form method="post" class="d-inline"
                  onsubmit="return confirm('Excluir este tratamento? Todas as etapas serão removidas.');">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="post_action" value="delete_treatment">
              <input type="hidden" name="id" value="<?= (int)$treatment['id'] ?>">
              <button class="btn btn-outline-danger btn-sm" type="submit">🗑️ Excluir</button>
            </form>

            <a class="btn btn-outline-secondary btn-sm" href="tratamentos.php">← Voltar</a>
          </div>
        </div>

        <?php if (!empty($treatment['observacoes'])): ?>
          <div class="mt-3 text-muted small">
            <b>Observações:</b> <?= h((string)$treatment['observacoes']) ?>
          </div>
        <?php endif; ?>

        <hr class="my-4">

        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
          <div>
            <h3 class="h6 mb-0">Etapas</h3>
            <div class="text-muted small">Adicione procedimentos e acompanhe o status.</div>
          </div>
          <span class="badge badge-soft">Etapas do tratamento</span>
        </div>

        <!-- Adicionar etapa -->
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="post_action" value="add_step">
          <input type="hidden" name="treatment_id" value="<?= (int)$treatment['id'] ?>">

          <div class="col-12 col-md-6">
            <label class="form-label">Procedimento</label>
            <select class="form-select" name="procedure_id" required>
              <option value="0">Selecione...</option>
              <?php foreach ($procedures as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>"><?= h((string)$pr['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="step_status">
              <option value="pendente">Pendente</option>
              <option value="em_andamento">Em andamento</option>
              <option value="concluido">Concluído</option>
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Observações</label>
            <input class="form-control" name="step_observacoes" placeholder="Opcional">
          </div>

          <div class="col-12">
            <button class="btn btn-outline-primary btn-sm btn-icon" type="submit">➕ <span>Adicionar etapa</span></button>
          </div>
        </form>

        <hr class="my-3">

        <!-- Lista etapas -->
        <?php if (!$steps): ?>
          <div class="text-muted">Nenhuma etapa cadastrada ainda.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="text-muted">
                <tr>
                  <th>Procedimento</th>
                  <th>Status</th>
                  <th>Observações</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($steps as $s): ?>
                  <tr>
                    <td class="fw-semibold"><?= h((string)$s['procedimento_nome']) ?></td>
                    <td>
                      <span class="badge <?= h(step_badge((string)$s['status'])) ?>">
                        <?= h(step_label((string)$s['status'])) ?>
                      </span>
                    </td>
                    <td class="text-muted small"><?= h((string)($s['observacoes'] ?? '')) ?></td>
                    <td class="text-end">

                      <!-- Atualizar etapa (status + obs) -->
                      <form method="post" class="d-inline-flex gap-2 align-items-center">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="post_action" value="update_step">
                        <input type="hidden" name="treatment_id" value="<?= (int)$treatment['id'] ?>">
                        <input type="hidden" name="step_id" value="<?= (int)$s['id'] ?>">

                        <select class="form-select form-select-sm" name="step_status" style="width:160px;">
                          <option value="pendente" <?= $s['status']==='pendente'?'selected':'' ?>>Pendente</option>
                          <option value="em_andamento" <?= $s['status']==='em_andamento'?'selected':'' ?>>Em andamento</option>
                          <option value="concluido" <?= $s['status']==='concluido'?'selected':'' ?>>Concluído</option>
                        </select>

                        <input class="form-control form-control-sm" name="step_observacoes"
                               value="<?= h((string)($s['observacoes'] ?? '')) ?>" placeholder="Observação"
                               style="width:220px;">

                        <button class="btn btn-outline-primary btn-sm" type="submit">💾</button>
                      </form>

                      <!-- Excluir etapa -->
                      <form method="post" class="d-inline"
                            onsubmit="return confirm('Excluir esta etapa?');">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="post_action" value="delete_step">
                        <input type="hidden" name="treatment_id" value="<?= (int)$treatment['id'] ?>">
                        <input type="hidden" name="step_id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">🗑️</button>
                      </form>

                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>
  <?php endif; ?>

  <!-- Lista tratamentos -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h2 class="h6 mb-0">Lista de Tratamentos</h2>
        <div class="text-muted small">Exibindo até 200 resultados.</div>
      </div>

      <?php if (!$treatments): ?>
        <div class="text-muted">Nenhum tratamento encontrado.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="text-muted">
              <tr>
                <th>#</th>
                <th>Título</th>
                <th>Paciente</th>
                <th>Dentista</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($treatments as $t): ?>
                <tr>
                  <td class="fw-semibold"><?= (int)$t['id'] ?></td>
                  <td><?= h((string)$t['titulo']) ?></td>
                  <td><?= h((string)$t['paciente_nome']) ?></td>
                  <td><?= h((string)$t['dentista_nome']) ?></td>
                  <td>
                    <span class="badge <?= h(t_status_badge((string)$t['status'])) ?>">
                      <?= h(t_status_label((string)$t['status'])) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a class="btn btn-outline-primary btn-sm" href="tratamentos.php?action=view&id=<?= (int)$t['id'] ?>">👁️ Abrir</a>
                      <a class="btn btn-outline-secondary btn-sm" href="tratamentos.php?action=edit&id=<?= (int)$t['id'] ?>">✏️ Editar</a>
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
