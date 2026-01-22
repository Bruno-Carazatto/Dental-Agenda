<?php
// dental-agenda/public/orcamentos.php
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
function money(float $v): string { return number_format($v, 2, ',', '.'); }

function to_decimal(string $raw): float {
    // aceita "1.234,56" ou "1234.56" ou "1234,56"
    $raw = trim($raw);
    if ($raw === '') return 0.0;
    $raw = str_replace(['R$', ' '], '', $raw);
    // se tiver vírgula como decimal, remove pontos
    if (str_contains($raw, ',')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    }
    return (float)$raw;
}

// Carrega dados para selects
$patients = $pdo->query("SELECT id, nome FROM patients WHERE ativo = 1 ORDER BY nome ASC LIMIT 500")->fetchAll();
$dentists = $pdo->query("SELECT id, nome FROM users WHERE role = 'dentista' AND ativo = 1 ORDER BY nome ASC LIMIT 200")->fetchAll();
$procedures = $pdo->query("SELECT id, nome, valor_base FROM procedures WHERE ativo = 1 ORDER BY nome ASC LIMIT 500")->fetchAll();

// Estado
$action = $_GET['action'] ?? 'list'; // list | new | edit | print
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Filtros listagem
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_date = trim((string)($_GET['date'] ?? ''));
if ($filter_date === '') $filter_date = date('Y-m-d');
$filter_patient = (int)($_GET['patient'] ?? 0);

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['post_action'] ?? '');
    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $erro = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    } else {
        try {
            if ($postAction === 'create' || $postAction === 'update') {
                $budgetId = (int)($_POST['id'] ?? 0);

                $patient_id = (int)($_POST['patient_id'] ?? 0);
                $dentist_id = (int)($_POST['dentist_id'] ?? 0);
                $data = trim((string)($_POST['data'] ?? date('Y-m-d')));
                $status = trim((string)($_POST['status'] ?? 'pendente'));
                $obs = trim((string)($_POST['observacoes'] ?? ''));

                $proc_ids = $_POST['procedure_id'] ?? [];
                $qtds = $_POST['qtd'] ?? [];
                $vals = $_POST['valor_unit'] ?? [];

                if ($patient_id <= 0 || $dentist_id <= 0) {
                    $erro = 'Selecione paciente e dentista.';
                } elseif ($data === '') {
                    $erro = 'Informe a data do orçamento.';
                } elseif (!is_array($proc_ids) || count($proc_ids) === 0) {
                    $erro = 'Adicione pelo menos 1 item no orçamento.';
                } else {
                    // Monta itens válidos
                    $items = [];
                    $total = 0.0;

                    for ($i = 0; $i < count($proc_ids); $i++) {
                        $pid = (int)($proc_ids[$i] ?? 0);
                        $qtd = (int)($qtds[$i] ?? 0);
                        $vu  = to_decimal((string)($vals[$i] ?? '0'));

                        if ($pid <= 0) continue;
                        if ($qtd <= 0) $qtd = 1;
                        if ($vu < 0) $vu = 0;

                        $subtotal = $qtd * $vu;
                        $total += $subtotal;

                        $items[] = [
                            'procedure_id' => $pid,
                            'qtd' => $qtd,
                            'valor_unit' => $vu,
                            'subtotal' => $subtotal,
                        ];
                    }

                    if (count($items) === 0) {
                        $erro = 'Itens inválidos. Verifique os procedimentos e quantidades.';
                    } else {
                        $pdo->beginTransaction();

                        if ($postAction === 'create') {
                            $ins = $pdo->prepare("
                                INSERT INTO budgets (patient_id, dentist_id, data, status, total, observacoes)
                                VALUES (:patient_id, :dentist_id, :data, :status, :total, :obs)
                            ");
                            $ins->execute([
                                ':patient_id' => $patient_id,
                                ':dentist_id' => $dentist_id,
                                ':data' => $data,
                                ':status' => $status,
                                ':total' => $total,
                                ':obs' => $obs !== '' ? $obs : null,
                            ]);
                            $budgetId = (int)$pdo->lastInsertId();
                        } else {
                            if ($budgetId <= 0) {
                                throw new RuntimeException('Orçamento inválido.');
                            }
                            $upd = $pdo->prepare("
                                UPDATE budgets
                                   SET patient_id = :patient_id,
                                       dentist_id = :dentist_id,
                                       data = :data,
                                       status = :status,
                                       total = :total,
                                       observacoes = :obs
                                 WHERE id = :id
                                 LIMIT 1
                            ");
                            $upd->execute([
                                ':patient_id' => $patient_id,
                                ':dentist_id' => $dentist_id,
                                ':data' => $data,
                                ':status' => $status,
                                ':total' => $total,
                                ':obs' => $obs !== '' ? $obs : null,
                                ':id' => $budgetId,
                            ]);

                            // Atualiza itens (simplificação: apaga e reinserir)
                            $pdo->prepare("DELETE FROM budget_items WHERE budget_id = :bid")->execute([':bid' => $budgetId]);
                        }

                        // Insere itens
                        $insItem = $pdo->prepare("
                            INSERT INTO budget_items (budget_id, procedure_id, qtd, valor_unit, subtotal)
                            VALUES (:budget_id, :procedure_id, :qtd, :valor_unit, :subtotal)
                        ");
                        foreach ($items as $it) {
                            $insItem->execute([
                                ':budget_id' => $budgetId,
                                ':procedure_id' => $it['procedure_id'],
                                ':qtd' => $it['qtd'],
                                ':valor_unit' => $it['valor_unit'],
                                ':subtotal' => $it['subtotal'],
                            ]);
                        }

                        $pdo->commit();

                        header('Location: orcamentos.php?msg=' . ($postAction === 'create' ? 'created' : 'updated'));
                        exit;
                    }
                }
            }

            if ($postAction === 'delete') {
                $bid = (int)($_POST['id'] ?? 0);
                if ($bid <= 0) {
                    $erro = 'Orçamento inválido.';
                } else {
                    $del = $pdo->prepare("DELETE FROM budgets WHERE id = :id LIMIT 1");
                    $del->execute([':id' => $bid]);
                    header('Location: orcamentos.php?msg=deleted');
                    exit;
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = 'Erro ao salvar. Verifique os dados e tente novamente.';
        }
    }
}

// Mensagens PRG
$msg = (string)($_GET['msg'] ?? '');
if ($msg === 'created') $sucesso = 'Orçamento criado com sucesso! ✅';
if ($msg === 'updated') $sucesso = 'Orçamento atualizado com sucesso! ✅';
if ($msg === 'deleted') $sucesso = 'Orçamento excluído com sucesso! ✅';

// Carrega orçamento para edit/print
$editing = null;
$editingItems = [];

if (($action === 'edit' || $action === 'print') && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.nome AS paciente_nome, u.nome AS dentista_nome
          FROM budgets b
          JOIN patients p ON p.id = b.patient_id
          JOIN users u ON u.id = b.dentist_id
         WHERE b.id = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $editing = $stmt->fetch();

    if ($editing) {
        $it = $pdo->prepare("
            SELECT bi.*, pr.nome AS procedimento_nome
              FROM budget_items bi
              JOIN procedures pr ON pr.id = bi.procedure_id
             WHERE bi.budget_id = :bid
          ORDER BY bi.id ASC
        ");
        $it->execute([':bid' => $id]);
        $editingItems = $it->fetchAll();
    } else {
        $erro = 'Orçamento não encontrado.';
        $action = 'list';
    }
}

// PRINT VIEW
if ($action === 'print' && $editing) {
    $total = (float)$editing['total'];
    ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orçamento #<?= (int)$editing['id'] ?> • Dental Agenda</title>
  <style>
    :root{ --blue:#0d6efd; --muted:#6b7280; }
    body{ font-family: Arial, sans-serif; margin: 24px; color:#0f172a; }
    .top{ display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
    .brand{ font-weight:700; font-size:18px; color:var(--blue); }
    .box{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; }
    .muted{ color:var(--muted); font-size:12px; }
    h1{ font-size:18px; margin:0 0 6px; }
    table{ width:100%; border-collapse:collapse; margin-top:14px; }
    th,td{ padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; font-size:13px; }
    th{ background:#f5f8ff; }
    .right{ text-align:right; }
    .total{ font-size:16px; font-weight:700; }
    .actions{ margin-top:14px; display:flex; gap:10px; }
    .btn{ padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff; cursor:pointer; }
    .btn-primary{ border-color: var(--blue); color: var(--blue); }
    @media print{
      .actions{ display:none; }
      body{ margin: 0; }
      .box{ border:none; padding:0; }
    }
  </style>
</head>
<body>
  <div class="top">
    <div>
      <div class="brand">🦷 Dental Agenda</div>
      <div class="muted">Proposta de orçamento odontológico</div>
    </div>
    <div class="box">
      <div class="muted">Orçamento</div>
      <h1>#<?= (int)$editing['id'] ?></h1>
      <div class="muted">Data: <b><?= h((string)$editing['data']) ?></b></div>
      <div class="muted">Status: <b><?= h((string)$editing['status']) ?></b></div>
    </div>
  </div>

  <div class="box" style="margin-top:14px;">
    <div><span class="muted">Paciente:</span> <b><?= h((string)$editing['paciente_nome']) ?></b></div>
    <div><span class="muted">Dentista:</span> <b><?= h((string)$editing['dentista_nome']) ?></b></div>
    <?php if (!empty($editing['observacoes'])): ?>
      <div style="margin-top:8px;"><span class="muted">Observações:</span><br><?= nl2br(h((string)$editing['observacoes'])) ?></div>
    <?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>Procedimento</th>
        <th class="right">Qtd</th>
        <th class="right">Valor Unit.</th>
        <th class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($editingItems as $it): ?>
        <tr>
          <td><?= h((string)$it['procedimento_nome']) ?></td>
          <td class="right"><?= (int)$it['qtd'] ?></td>
          <td class="right">R$ <?= money((float)$it['valor_unit']) ?></td>
          <td class="right">R$ <?= money((float)$it['subtotal']) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="3" class="right total">Total</td>
        <td class="right total">R$ <?= money($total) ?></td>
      </tr>
    </tbody>
  </table>

  <div class="actions">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
    <a class="btn" href="orcamentos.php">← Voltar</a>
  </div>
</body>
</html>
<?php
    exit;
}

// Form defaults
$isEdit = ($action === 'edit' && $editing);
$formPatient = $isEdit ? (int)$editing['patient_id'] : (int)($_POST['patient_id'] ?? 0);
$formDentist = $isEdit ? (int)$editing['dentist_id'] : (int)($_POST['dentist_id'] ?? 0);
$formDate = $isEdit ? (string)$editing['data'] : (string)($_POST['data'] ?? date('Y-m-d'));
$formStatus = $isEdit ? (string)$editing['status'] : (string)($_POST['status'] ?? 'pendente');
$formObs = $isEdit ? (string)($editing['observacoes'] ?? '') : (string)($_POST['observacoes'] ?? '');

// Lista com filtros
$where = "WHERE 1=1";
$params = [];

if ($filter_date !== '') {
    $where .= " AND b.data = :data";
    $params[':data'] = $filter_date;
}
if ($filter_status !== '') {
    $where .= " AND b.status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_patient > 0) {
    $where .= " AND b.patient_id = :pid";
    $params[':pid'] = $filter_patient;
}

$list = $pdo->prepare("
    SELECT b.id, b.data, b.status, b.total,
           p.nome AS paciente_nome,
           u.nome AS dentista_nome
      FROM budgets b
      JOIN patients p ON p.id = b.patient_id
      JOIN users u ON u.id = b.dentist_id
      $where
  ORDER BY b.id DESC
  LIMIT 200
");
$list->execute($params);
$budgets = $list->fetchAll();
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
    .btn-icon { display:inline-flex; align-items:center; gap:.35rem; }
    .table td, .table th { vertical-align: middle; }
    .item-row td { white-space: nowrap; }
    .w-qty { width: 90px; }
    .w-money { width: 150px; }
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
        <h1 class="h4 mb-1">Orçamentos</h1>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="dashboard.php">← Voltar</a>
        <a class="btn btn-primary btn-icon" href="orcamentos.php?action=new">➕ <span>Novo Orçamento</span></a>
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

        <div class="col-12 col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">Todos</option>
            <?php foreach (['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado'] as $k => $lab): ?>
              <option value="<?= h($k) ?>" <?= $filter_status === $k ? 'selected' : '' ?>><?= h($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4">
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
          <a class="btn btn-outline-secondary w-100" href="orcamentos.php">Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Form (novo/editar) -->
  <?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <h2 class="h6 mb-0"><?= $isEdit ? 'Editar Orçamento' : 'Novo Orçamento' ?></h2>
          <span class="badge badge-soft">Formulário</span>
        </div>

        <form method="post" id="budgetForm">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="post_action" value="<?= $isEdit ? 'update' : 'create' ?>">
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
          <?php endif; ?>

          <div class="row g-3">
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

            <div class="col-12 col-md-3">
              <label class="form-label">Data</label>
              <input type="date" class="form-control" name="data" value="<?= h($formDate) ?>" required>
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="pendente" <?= $formStatus==='pendente'?'selected':'' ?>>Pendente</option>
                <option value="aprovado" <?= $formStatus==='aprovado'?'selected':'' ?>>Aprovado</option>
                <option value="recusado" <?= $formStatus==='recusado'?'selected':'' ?>>Recusado</option>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Observações</label>
              <input class="form-control" name="observacoes" value="<?= h($formObs) ?>" placeholder="Ex: condições de pagamento, validade do orçamento...">
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
            <div>
              <h3 class="h6 mb-0">Itens do orçamento</h3>
              <div class="text-muted small">Adicione procedimentos e valores. Total é calculado automaticamente.</div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm btn-icon" id="addItemBtn">➕ <span>Adicionar item</span></button>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle" id="itemsTable">
              <thead class="text-muted">
                <tr>
                  <th>Procedimento</th>
                  <th class="w-qty">Qtd</th>
                  <th class="w-money">Valor Unit.</th>
                  <th class="w-money">Subtotal</th>
                  <th class="text-end">Ação</th>
                </tr>
              </thead>
              <tbody id="itemsTbody">
                <!-- Itens serão inseridos aqui (JS). Se estiver editando, já carrega abaixo -->
                <?php if ($isEdit && $editingItems): ?>
                  <?php foreach ($editingItems as $it): ?>
                    <tr class="item-row">
                      <td>
                        <select class="form-select form-select-sm proc-select" name="procedure_id[]">
                          <option value="0">Selecione...</option>
                          <?php foreach ($procedures as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>"
                              data-base="<?= h((string)$pr['valor_base']) ?>"
                              <?= ((int)$pr['id'] === (int)$it['procedure_id']) ? 'selected' : '' ?>>
                              <?= h((string)$pr['nome']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td><input class="form-control form-control-sm qtd-input" name="qtd[]" type="number" min="1" value="<?= (int)$it['qtd'] ?>"></td>
                      <td><input class="form-control form-control-sm vu-input" name="valor_unit[]" inputmode="decimal" value="<?= h(number_format((float)$it['valor_unit'], 2, '.', '')) ?>"></td>
                      <td class="subtotal-cell">R$ 0,00</td>
                      <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-btn">🗑️</button></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-semibold">Total</td>
                  <td class="fw-semibold" id="totalCell">R$ 0,00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-primary btn-icon" type="submit">💾 <span><?= $isEdit ? 'Salvar orçamento' : 'Criar orçamento' ?></span></button>
            <a class="btn btn-outline-secondary" href="orcamentos.php">Cancelar</a>
            <?php if ($isEdit): ?>
              <a class="btn btn-outline-primary" href="orcamentos.php?action=print&id=<?= (int)$editing['id'] ?>" target="_blank">🖨️ Imprimir</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Lista -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h2 class="h6 mb-0">Lista de Orçamentos</h2>
        <div class="text-muted small">Exibindo até 200 resultados.</div>
      </div>

      <?php if (!$budgets): ?>
        <div class="text-muted">Nenhum orçamento encontrado.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="text-muted">
              <tr>
                <th>#</th>
                <th>Data</th>
                <th>Paciente</th>
                <th>Dentista</th>
                <th>Status</th>
                <th class="text-end">Total</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($budgets as $b): ?>
                <tr>
                  <td class="fw-semibold"><?= (int)$b['id'] ?></td>
                  <td><?= h((string)$b['data']) ?></td>
                  <td><?= h((string)$b['paciente_nome']) ?></td>
                  <td><?= h((string)$b['dentista_nome']) ?></td>
                  <td>
                    <?php
                      $st = (string)$b['status'];
                      $badge = match ($st) {
                        'pendente' => 'text-bg-primary',
                        'aprovado' => 'text-bg-success',
                        'recusado' => 'text-bg-secondary',
                        default => 'text-bg-light'
                      };
                      $label = match ($st) {
                        'pendente' => 'Pendente',
                        'aprovado' => 'Aprovado',
                        'recusado' => 'Recusado',
                        default => $st
                      };
                    ?>
                    <span class="badge <?= h($badge) ?>"><?= h($label) ?></span>
                  </td>
                  <td class="text-end">R$ <?= money((float)$b['total']) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a class="btn btn-outline-primary btn-sm" href="orcamentos.php?action=edit&id=<?= (int)$b['id'] ?>">✏️ Editar</a>
                      <a class="btn btn-outline-secondary btn-sm" target="_blank" href="orcamentos.php?action=print&id=<?= (int)$b['id'] ?>">🖨️ Imprimir</a>
                      <form method="post" class="d-inline"
                            onsubmit="return confirm('Tem certeza que deseja excluir este orçamento?');">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="post_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
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

<script>
  // ====== Catálogo de procedimentos (nome + valor base)
  const PROCEDURES = <?= json_encode(array_map(fn($p) => [
      'id' => (int)$p['id'],
      'nome' => (string)$p['nome'],
      'valor_base' => (float)$p['valor_base'],
  ], $procedures), JSON_UNESCAPED_UNICODE) ?>;

  const tbody = document.getElementById('itemsTbody');
  const totalCell = document.getElementById('totalCell');
  const addBtn = document.getElementById('addItemBtn');

  function fmtBR(value) {
    // value number -> "R$ 1.234,56"
    try {
      return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } catch {
      return 'R$ ' + value.toFixed(2);
    }
  }

  function parseNumber(v) {
    v = (v || '').toString().trim();
    if (!v) return 0;
    // suporta 1.234,56
    if (v.includes(',')) {
      v = v.replaceAll('.', '').replace(',', '.');
    }
    v = v.replace(/[^\d.]/g, '');
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  function buildProcOptions(selectedId = 0) {
    const opts = ['<option value="0">Selecione...</option>'];
    for (const p of PROCEDURES) {
      const sel = (p.id === selectedId) ? 'selected' : '';
      opts.push(`<option value="${p.id}" data-base="${p.valor_base}" ${sel}>${p.nome}</option>`);
    }
    return opts.join('');
  }

  function addRow(selectedProc = 0, qtd = 1, valorUnit = null) {
    const tr = document.createElement('tr');
    tr.className = 'item-row';

    tr.innerHTML = `
      <td>
        <select class="form-select form-select-sm proc-select" name="procedure_id[]">
          ${buildProcOptions(selectedProc)}
        </select>
      </td>
      <td><input class="form-control form-control-sm qtd-input" name="qtd[]" type="number" min="1" value="${qtd}"></td>
      <td><input class="form-control form-control-sm vu-input" name="valor_unit[]" inputmode="decimal" value="${valorUnit ?? ''}"></td>
      <td class="subtotal-cell">R$ 0,00</td>
      <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-btn">🗑️</button></td>
    `;

    tbody.appendChild(tr);

    // Se não veio valorUnit, usa valor_base do procedimento selecionado
    const sel = tr.querySelector('.proc-select');
    const vuInput = tr.querySelector('.vu-input');
    if (valorUnit === null) {
      const base = parseNumber(sel.selectedOptions[0]?.dataset?.base || '0');
      vuInput.value = base ? base.toFixed(2) : '';
    }

    bindRowEvents(tr);
    recalc();
  }

  function bindRowEvents(tr) {
    const sel = tr.querySelector('.proc-select');
    const qtd = tr.querySelector('.qtd-input');
    const vu  = tr.querySelector('.vu-input');
    const rm  = tr.querySelector('.remove-btn');

    sel.addEventListener('change', () => {
      const base = parseNumber(sel.selectedOptions[0]?.dataset?.base || '0');
      if (!vu.value) vu.value = base ? base.toFixed(2) : '';
      recalc();
    });
    qtd.addEventListener('input', recalc);
    vu.addEventListener('input', recalc);

    rm.addEventListener('click', () => {
      tr.remove();
      recalc();
    });
  }

  function recalc() {
    let total = 0;

    const rows = tbody.querySelectorAll('tr.item-row');
    rows.forEach(row => {
      const qtd = Number(row.querySelector('.qtd-input')?.value || 1);
      const vu  = parseNumber(row.querySelector('.vu-input')?.value || '0');
      const sub = (qtd > 0 ? qtd : 1) * (vu >= 0 ? vu : 0);

      row.querySelector('.subtotal-cell').textContent = fmtBR(sub);
      total += sub;
    });

    if (totalCell) totalCell.textContent = fmtBR(total);
  }

  if (addBtn) {
    addBtn.addEventListener('click', () => addRow(0, 1, null));
  }

  // Se for "novo" e não tem itens, adiciona 1 linha padrão
  const isFormPage = document.getElementById('budgetForm');
  if (isFormPage) {
    const hasItems = tbody && tbody.querySelectorAll('tr.item-row').length > 0;
    if (!hasItems) addRow(0, 1, null);

    // Bind eventos nos itens carregados do PHP (modo editar)
    tbody.querySelectorAll('tr.item-row').forEach(bindRowEvents);

    recalc();
  }
</script>

</body>
</html>
