<?php
// dental-agenda/public/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth/check.php';
$user = $_SESSION['user'];
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
            Logado como: <b><?= htmlspecialchars($user['nome']) ?></b>
            <span class="badge text-bg-light ms-2"><?= htmlspecialchars($user['role']) ?></span>
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
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">
    <div>
      <h1 class="h4 mb-1">Dashboard</h1>
    </div>
<div class="dashboard-actions">
  <a href="agenda.php" class="dash-btn primary">➕ Novo Agendamento</a>
  <a href="pacientes.php" class="dash-btn">👤 Pacientes</a>
  <a href="orcamentos.php" class="dash-btn">💰 Orçamentos</a>
  <a href="tratamentos.php" class="dash-btn">🦷 Tratamentos</a>
</div>
  </div>

  <!-- Cards -->
  <div class="row g-3">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Agendamentos (hoje)</div>
              <div class="display-6 fw-semibold">—</div>
              <div class="text-muted small">Aguardando integração</div>
            </div>
            <div class="icon-bubble">📅</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Pacientes</div>
              <div class="display-6 fw-semibold">—</div>
              <div class="text-muted small">Aguardando integração</div>
            </div>
            <div class="icon-bubble">👥</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Orçamentos</div>
              <div class="display-6 fw-semibold">—</div>
              <div class="text-muted small">Aguardando integração</div>
            </div>
            <div class="icon-bubble">💰</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Tratamentos</div>
              <div class="display-6 fw-semibold">—</div>
              <div class="text-muted small">Aguardando integração</div>
            </div>
            <div class="icon-bubble">🦷</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
