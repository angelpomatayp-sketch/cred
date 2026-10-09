'use strict';
const modal = document.querySelector('#activeModal');
if (modal && typeof modal.showModal === 'function') {
  modal.removeAttribute('open');
  modal.showModal();
  const close = () => { window.location.href = modal.dataset.return; };
  modal.addEventListener('cancel', event => { event.preventDefault(); close(); });
  modal.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', close));
}
document.querySelectorAll('[data-password]').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.password);
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.textContent = visible ? 'Ocultar' : 'Mostrar';
    button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
  });
});
const menu = document.querySelector('[data-menu]');
const closeMenu = () => { document.body.classList.remove('menu-open'); menu?.setAttribute('aria-expanded', 'false'); };
menu?.addEventListener('click', () => { const open = document.body.classList.toggle('menu-open'); menu.setAttribute('aria-expanded', String(open)); });
document.querySelector('[data-menu-close]')?.addEventListener('click', closeMenu);
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
const clock = document.querySelector('[data-clock]');
if (clock) {
  const serverTime = Date.parse(clock.dateTime);
  const start = performance.now();
  const format = new Intl.DateTimeFormat('es-PE', { timeZone:'America/Lima', day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:false });
  const update = () => {
    const time = new Date(serverTime + performance.now() - start);
    clock.replaceChildren(document.createTextNode(format.format(time)));
    const label = document.createElement('small'); label.textContent = 'Lima, Perú'; clock.append(label);
  };
  update(); setInterval(update, 30000);
}
document.querySelectorAll('form[method="post"]').forEach(form => form.addEventListener('submit', () => {
  if (!form.checkValidity()) return;
  form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button => { button.disabled = true; button.textContent = 'Procesando…'; });
}));
window.addEventListener('pageshow', event => { if(event.persisted) window.location.reload(); });
if ('serviceWorker' in navigator && window.isSecureContext) navigator.serviceWorker.register('sw.js').catch(() => {});
document.querySelectorAll('[data-cred-identity]').forEach(panel => {
  const form = panel.closest('form');
  const identity = form.elements.namedItem(panel.dataset.credIdentity);
  const date = panel.dataset.credDate ? form.elements.namedItem(panel.dataset.credDate) : null;
  const target = date || identity;
  let generation = 0;
  async function update() {
    const current = ++generation;
    target.setCustomValidity('');
    panel.classList.remove('out-of-range');
    const on = date ? date.value : panel.dataset.today;
    if (!identity.value || !on) {
      panel.querySelector('[data-age-title]').textContent = 'Edad y grupo CRED';
      panel.querySelector('[data-age-description]').textContent = 'Selecciona el paciente o nacimiento y la fecha para consultar el rango.';
      return;
    }
    const params = new URLSearchParams({ cred_context:'1', on });
    params.set(panel.dataset.credIdentity, identity.value);
    try {
      const response = await fetch('index.php?' + params, {headers:{Accept:'application/json'},cache:'no-store'});
      const result = await response.json();
      if (current !== generation) return;
      if (!response.ok) throw new Error(result.error || 'No se pudo consultar la edad.');
      panel.querySelector('[data-age-title]').textContent = result.age + ' · ' + result.label;
      panel.querySelector('[data-age-description]').textContent = result.guidance;
      panel.classList.toggle('out-of-range', !result.eligible);
      if (!result.eligible && panel.dataset.credEnforce === '1') target.setCustomValidity('Edad fuera del rango CRED en esta fecha: desde el nacimiento hasta antes de cumplir 12 años.');
    } catch (error) {
      if (current !== generation) return;
      panel.querySelector('[data-age-description]').textContent = 'No se pudo consultar la edad. El servidor comprobará el rango al guardar.';
    }
  }
  identity.addEventListener('input', update);
  date?.addEventListener('input', update);
  update();
});
