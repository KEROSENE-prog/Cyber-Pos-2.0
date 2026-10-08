/**
 * CyberPOS — registro.js
 */
document.addEventListener('DOMContentLoaded', () => {
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
      const nombre    = document.getElementById('nombre')?.value.trim()    || '';
      const email     = document.getElementById('email')?.value.trim()     || '';
      const password  = document.getElementById('password')?.value.trim()  || '';
      const password2 = document.getElementById('password2')?.value.trim() || '';
      const submitBtn = form.querySelector('button[type="submit"]');

      if (!nombre || !email || !password) {
        showToast('Por favor completa todos los campos.', 'error'); return;
      }
      if (password !== password2) {
        showToast('Las contraseñas no coinciden.', 'error'); return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Registrando...';

      try {
        const res = await apiFetch('/auth/register', {
          method: 'POST',
          body: JSON.stringify({ nombre_completo: nombre, email, password, rol: rolActivo })
        });
        Auth.setSession(res.user, res.token);
        showToast('Cuenta creada exitosamente. ¡Bienvenido!', 'success', 2000);
        setTimeout(() => {
          window.location.href = res.user.rol === 'administrador' ? 'dashboard.html' : 'dashboard_cliente.html';
        }, 1200);
      } catch (err) {
        showToast(err.message || 'Error al registrarse.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Crear Cuenta';
      }
    });
  }
});
