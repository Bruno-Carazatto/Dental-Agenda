<?php
// dental-agenda/public/pacientes.php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth/check.php';
require_once __DIR__ . '/../app/config/db.php';

$user = $_SESSION['user'];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CSRF token (protege ações POST)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$pdo = db();

$erro = '';
$sucesso = '';

// Helpers
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

function format_cpf(?string $cpf): string {
    $d = only_digits((string)$cpf);
    if (strlen($d) !== 11) return (string)$cpf;
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

function format_phone(?string $phone): string {
    $d = only_digits((string)$phone);
    if ($d === '') return '';
    // Formatos comuns BR: (11) 99999-9999 ou (11) 9999-9999
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
    }
    return (string)$phone;
}

function valid_email_or_empty(string $email): bool {
    if ($email === '') return true;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Estado de tela
$action = $_GET['action'] ?? 'list'; // list | new | edit
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Busca
$q = trim((string)($_GET['q'] ?? ''));
$qDigits = only_digits($q);

// Processa POST (create / update / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['post_action'] ?? '');
    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $erro = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    } else {
        try {
            if ($postAction === 'create') {
                $nome = trim((string)($_POST['nome'] ?? ''));
                $cpf = trim((string)($_POST['cpf'] ?? ''));
                $telefone = trim((string)($_POST['telefone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $data_nascimento = trim((string)($_POST['data_nascimento'] ?? ''));
                $observacoes = trim((string)($_POST['observacoes'] ?? ''));

                if ($nome === '') {
                    $erro = 'O campo Nome é obrigatório.';
                } elseif (!valid_email_or_empty($email)) {
                    $erro = 'E-mail inválido.';
                } else {
                    $cpfDigits = only_digits($cpf);
                    $telDigits = only_digits($telefone);

                    // Data pode ser vazia
                    $dateValue = $data_nascimento !== '' ? $data_nascimento : null;

                    $stmt = $pdo->prepare("
                        INSERT INTO patients (nome, cpf, telefone, email, data_nascimento, observacoes, ativo)
                        VALUES (:nome, :cpf, :telefone, :email, :data_nascimento, :observacoes, 1)
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':cpf' => $cpfDigits !== '' ? $cpfDigits : null,
                        ':telefone' => $telDigits !== '' ? $telDigits : null,
                        ':email' => $email !== '' ? $email : null,
                        ':data_nascimento' => $dateValue,
                        ':observacoes' => $observacoes !== '' ? $observacoes : null,
                    ]);

                    header('Location: pacientes.php?msg=created');
                    exit;
                }
            }

            if ($postAction === 'update') {
                $pid = (int)($_POST['id'] ?? 0);

                $nome = trim((string)($_POST['nome'] ?? ''));
                $cpf = trim((string)($_POST['cpf'] ?? ''));
                $telefone = trim((string)($_POST['telefone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $data_nascimento = trim((string)($_POST['data_nascimento'] ?? ''));
                $observacoes = trim((string)($_POST['observacoes'] ?? ''));
                $ativo = isset($_POST['ativo']) ? 1 : 0;

                if ($pid <= 0) {
                    $erro = 'Paciente inválido.';
                } elseif ($nome === '') {
                    $erro = 'O campo Nome é obrigatório.';
                } elseif (!valid_email_or_empty($email)) {
                    $erro = 'E-mail inválido.';
                } else {
                    $cpfDigits = only_digits($cpf);
                    $telDigits = only_digits($telefone);
                    $dateValue = $data_nascimento !== '' ? $data_nascimento : null;

                    $stmt = $pdo->prepare("
                        UPDATE patients
                           SET nome = :nome,
                               cpf = :cpf,
                               telefone = :telefone,
                               email = :email,
                               data_nascimento = :data_nascimento,
                               observacoes = :observacoes,
                               ativo = :ativo
                         WHERE id = :id
                         LIMIT 1
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':cpf' => $cpfDigits !== '' ? $cpfDigits : null,
                        ':telefone' => $telDigits !== '' ? $telDigits : null,
                        ':email' => $email !== '' ? $email : null,
                        ':data_nascimento' => $dateValue,
                        ':observacoes' => $observacoes !== '' ? $observacoes : null,
                        ':ativo' => $ativo,
                        ':id' => $pid,
                    ]);

                    header('Location: pacientes.php?msg=updated');
                    exit;
                }
            }

            if ($postAction === 'delete') {
                $pid = (int)($_POST['id'] ?? 0);

                if ($pid <= 0) {
                    $erro = 'Paciente inválido.';
                } else {
                    // Exclusão física (DELETE). Se preferir, podemos trocar para "ativo=0".
                    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $pid]);

                    header('Location: pacientes.php?msg=deleted');
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Erro comum: CPF duplicado (UK)
            if ((int)$e->errorInfo[1] === 1062) {
                $erro = 'CPF já cadastrado. Verifique e tente novamente.';
            } else {
                $erro = 'Erro no banco. Verifique o phpMyAdmin/conexão.';
            }
        } catch (Throwable $e) {
            $erro = 'Erro inesperado. Tente novamente.';
        }
    }
}

// Mensagens por querystring (PRG)
$msg = (string)($_GET['msg'] ?? '');
if ($msg === 'created') $sucesso = 'Paciente cadastrado com sucesso! ✅';
if ($msg === 'updated') $sucesso = 'Paciente atualizado com sucesso! ✅';
if ($msg === 'deleted') $sucesso = 'Paciente excluído com sucesso! ✅';

// Carrega paciente para edição
$editing = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $editing = $stmt->fetch();
    if (!$editing) {
        $erro = 'Paciente não encontrado.';
        $action = 'list';
    }
}

// Lista com busca (simples)
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE nome LIKE :q OR email LIKE :q OR telefone LIKE :q OR cpf LIKE :q";
    $params[':q'] = '%' . $q . '%';
}

// Busca (lista)
$stmt = $pdo->prepare("
    SELECT id, nome, cpf, telefone, email, data_nascimento, ativo, criado_em
      FROM patients
      $where
  ORDER BY id DESC
  LIMIT 200
");
$stmt->execute($params);
$patients = $stmt->fetchAll();
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
    /* Pequenos ajustes específicos desta página */
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
        <h1 class="h4 mb-1">Pacientes</h1>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="dashboard.php">← Voltar</a>
        <a class="btn btn-primary btn-icon" href="pacientes.php?action=new">
          ➕ <span>Novo Paciente</span>
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

  <!-- Busca -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-center" method="get">
        <input type="hidden" name="action" value="list">
        <div class="col-12 col-md-8">
          <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Buscar por nome, CPF, telefone ou e-mail...">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
          <button class="btn btn-outline-primary w-100" type="submit">🔎 Buscar</button>
          <a class="btn btn-outline-secondary w-100" href="pacientes.php">Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($action === 'new' || $action === 'edit'): ?>
    <?php
      $isEdit = $action === 'edit' && is_array($editing);
      $formTitle = $isEdit ? 'Editar Paciente' : 'Novo Paciente';

      $valNome = $isEdit ? (string)$editing['nome'] : (string)($_POST['nome'] ?? '');
      $valCpf  = $isEdit ? format_cpf((string)$editing['cpf']) : (string)($_POST['cpf'] ?? '');
      $valTel  = $isEdit ? format_phone((string)$editing['telefone']) : (string)($_POST['telefone'] ?? '');
      $valEmail = $isEdit ? (string)$editing['email'] : (string)($_POST['email'] ?? '');
      $valNasc  = $isEdit ? (string)($editing['data_nascimento'] ?? '') : (string)($_POST['data_nascimento'] ?? '');
      $valObs   = $isEdit ? (string)($editing['observacoes'] ?? '') : (string)($_POST['observacoes'] ?? '');
      $valAtivo = $isEdit ? ((int)$editing['ativo'] === 1) : true;
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
            <label class="form-label">Nome completo <span class="text-danger">*</span></label>
            <input class="form-control" name="nome" required value="<?= h($valNome) ?>" placeholder="Ex: Maria Aparecida Souza">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">CPF</label>
            <input class="form-control" name="cpf" value="<?= h($valCpf) ?>" placeholder="000.000.000-00">
            <div class="form-text">Opcional. Se informado, deve ser único.</div>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Telefone</label>
            <input class="form-control" name="telefone" value="<?= h($valTel) ?>" placeholder="(11) 99999-9999">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">E-mail</label>
            <input class="form-control" name="email" value="<?= h($valEmail) ?>" placeholder="exemplo@email.com">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Data de nascimento</label>
            <input type="date" class="form-control" name="data_nascimento" value="<?= h($valNasc) ?>">
          </div>

          <div class="col-12 col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?= $valAtivo ? 'checked' : '' ?>>
              <label class="form-check-label" for="ativo">Paciente ativo</label>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Observações</label>
            <textarea class="form-control" name="observacoes" rows="4" placeholder="Ex: alergias, preferências, avisos..."><?= h($valObs) ?></textarea>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-icon" type="submit">
              💾 <span><?= $isEdit ? 'Salvar alterações' : 'Cadastrar paciente' ?></span>
            </button>
            <a class="btn btn-outline-secondary" href="pacientes.php">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Lista -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h2 class="h6 mb-0">Lista de Pacientes</h2>
        <div class="text-muted small">
          Exibindo até 200 resultados <?= $q !== '' ? '(filtrado)' : '' ?>.
        </div>
      </div>

      <?php if (!$patients): ?>
        <div class="text-muted">Nenhum paciente encontrado.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr class="text-muted">
                <th>#</th>
                <th>Nome</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($patients as $p): ?>
                <tr>
                  <td><?= (int)$p['id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$p['nome']) ?></td>
                  <td><?= h(format_cpf((string)($p['cpf'] ?? ''))) ?></td>
                  <td><?= h(format_phone((string)($p['telefone'] ?? ''))) ?></td>
                  <td><?= h((string)($p['email'] ?? '')) ?></td>
                  <td>
                    <?php if ((int)$p['ativo'] === 1): ?>
                      <span class="badge text-bg-success">Ativo</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Inativo</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a class="btn btn-outline-primary btn-sm" href="pacientes.php?action=edit&id=<?= (int)$p['id'] ?>">✏️ Editar</a>

                      <!-- Excluir com confirmação -->
                      <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este paciente? Essa ação não pode ser desfeita.');">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="post_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
