/**
 * CyberPOS — login.js
 * Validación local de credenciales con rol y redirección inteligente.
 */
document.addEventListener('DOMContentLoaded', () => {
  // Tabs de rol
  const tabs = document.querySelectorAll('.role-tabs button');
  const form = document.querySelector('form.auth-form');

  let rolActivo = 'cliente';

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      rolActivo = tab.dataset.rol || (tab.textContent.trim().toLowerCase().includes('admin') ? 'administrador' : 'cliente');
    });
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const emailEl    = document.getElementById('email');
      const passwordEl = document.getElementById('password');
      const submitBtn  = form.querySelector('button[type="submit"]');

      const email    = emailEl ? emailEl.value.trim() : '';
      const password = passwordEl ? passwordEl.value.trim() : '';

      if (!email || !password) {
        showToast('Por favor ingresa correo y contraseña.', 'error');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Ingresando...';

      try {
        const res = await apiFetch('/auth/login', {
          method: 'POST',
          body: JSON.stringify({ email, password, rol: rolActivo })
        });

        Auth.setSession(res.user, res.token);
        showToast(`Bienvenido, ${res.user.nombre_completo}`, 'success', 1500);

        setTimeout(() => {
          if (res.user.rol === 'administrador') {
            window.location.href = 'dashboard.html';
          } else {
            window.location.href = 'dashboard_cliente.html';
          }
        }, 800);
      } catch (err) {
        showToast(err.message || 'Error al iniciar sesión.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Ingresar';
      }
    });
  }
});
