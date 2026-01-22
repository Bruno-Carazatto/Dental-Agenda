// dental-agenda/assets/js/app.js

(function () {
  const toggle = document.getElementById('toggleSenha');
  const senha = document.getElementById('senha');

  if (toggle && senha) {
    toggle.addEventListener('click', () => {
      const isPass = senha.getAttribute('type') === 'password';
      senha.setAttribute('type', isPass ? 'text' : 'password');
      toggle.textContent = isPass ? '🙈' : '👁️';
    });
  }

  const btn = document.getElementById('btnEntrar');
  const loader = document.getElementById('btnLoader');

  if (btn && loader) {
    btn.addEventListener('click', () => {
      // Só pra UX: mostra loader quando o form for enviado
      // (o servidor ainda valida de verdade)
      setTimeout(() => loader.classList.remove('d-none'), 50);
    });
  }
})();
