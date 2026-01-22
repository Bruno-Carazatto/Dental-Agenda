<?php
// dental-agenda/public/login.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/db.php';

session_start();

// Se já estiver logado, vai pro dashboard
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';
$usuario = '';

function client_ip(): string {
    // simples e suficiente para MVP (sem confiar cegamente em headers de proxy)
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $senha   = (string)($_POST['senha'] ?? '');

    if ($usuario === '' || $senha === '') {
        $erro = 'Preencha usuário e senha.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id, nome, usuario, senha_hash, role, ativo FROM users WHERE usuario = :u LIMIT 1");
            $stmt->execute([':u' => $usuario]);
            $user = $stmt->fetch();

            $ok = $user && (int)$user['ativo'] === 1 && password_verify($senha, (string)$user['senha_hash']);

            // Loga tentativa (sucesso/falha)
            $log = $pdo->prepare("
                INSERT INTO auth_logs (user_id, usuario_digitado, evento, ip, user_agent)
                VALUES (:user_id, :usuario_digitado, :evento, :ip, :ua)
            ");

            if ($ok) {
                // Sessão segura (MVP)
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'nome' => (string)$user['nome'],
                    'usuario' => (string)$user['usuario'],
                    'role' => (string)$user['role'],
                ];

                $log->execute([
                    ':user_id' => (int)$user['id'],
                    ':usuario_digitado' => $usuario,
                    ':evento' => 'login_sucesso',
                    ':ip' => client_ip(),
                    ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]);

                header('Location: dashboard.php');
                exit;
            }

            $log->execute([
                ':user_id' => $user ? (int)$user['id'] : null,
                ':usuario_digitado' => $usuario,
                ':evento' => 'login_falha',
                ':ip' => client_ip(),
                ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            $erro = 'Usuário ou senha inválidos.';
        } catch (Throwable $e) {
            $erro = 'Erro interno. Verifique a conexão com o banco.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../assets/img/icon.png" type="image/png">
  <title>Dental Agenda</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-soft">

  <div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <div class="card shadow-sm border-0 login-card w-100" style="max-width: 420px;">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="brand-badge">🦷</div>
          <div>
            <h1 class="h5 mb-0">Dental Agenda</h1>
            <small class="text-muted">Sistema de clínica odontológica</small>
          </div>
        </div>

        <div class="alert alert-info py-2 small mb-3">
          Se for a primeira vez, rode <b>install.php</b> para criar o banco e o admin. ✅
        </div>

        <?php if ($erro): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3" autocomplete="off">
          <div>
            <label class="form-label">Usuário</label>
            <input
              type="text"
              name="usuario"
              class="form-control"
              placeholder="ex: Bruno.Carazatto"
              value="<?= htmlspecialchars($usuario) ?>"
              required
            >
          </div>

          <div>
            <label class="form-label">Senha</label>
            <div class="input-group">
              <input type="password" name="senha" id="senha" class="form-control" placeholder="••••••••" required>
              <button class="btn btn-outline-primary" type="button" id="toggleSenha" aria-label="Mostrar ou ocultar senha">
                👁️
              </button>
            </div>
          </div>

          <button class="btn btn-primary w-100" type="submit" id="btnEntrar">
            <span class="btn-text">Entrar</span>
            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true" id="btnLoader"></span>
          </button>

        </form>
      </div>
    </div>
  </div>

  <script src="../assets/js/app.js"></script>
</body>
</html>
