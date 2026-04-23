/* ================================================================
   BUTIK MENIK MODESTE — Global JavaScript
================================================================ */

/* ── Sidebar Toggle (mobile) ─────────────────────────────── */
function bmToggleSidebar() {
  const sidebar  = document.getElementById('bm-sidebar');
  const overlay  = document.getElementById('bm-overlay');
  if (!sidebar) return;
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
}

document.addEventListener('DOMContentLoaded', () => {
  const overlay = document.getElementById('bm-overlay');
  if (overlay) overlay.addEventListener('click', bmToggleSidebar);

  /* ── Mark active nav item ──────────────────────────────── */
  const currentPath = window.location.pathname;
  document.querySelectorAll('.bm-nav-item[href]').forEach(link => {
    if (currentPath.endsWith(link.getAttribute('href').split('/').pop())) {
      link.classList.add('active');
    }
  });

  /* ── Drag-over upload zones ─────────────────────────────── */
  document.querySelectorAll('.bm-upload-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      const input = zone.querySelector('input[type=file]');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });
    zone.addEventListener('click', () => zone.querySelector('input[type=file]')?.click());
  });

  /* ── File preview label update ──────────────────────────── */
  document.querySelectorAll('.bm-file-input').forEach(input => {
    input.addEventListener('change', () => {
      const label = input.closest('.bm-upload-zone')?.querySelector('.bm-upload-filename');
      if (label && input.files[0]) label.textContent = input.files[0].name;
    });
  });

  /* ── Auto-dismiss alerts ─────────────────────────────────── */
  document.querySelectorAll('.bm-alert-auto-dismiss').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 3500);
    setTimeout(() => el.remove(), 4000);
  });

  /* ── Confirm dangerous actions ───────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

/* ── Toast Notification ──────────────────────────────────── */
function bmToast(message, type = 'success') {
  const colors = {
    success: '#10b981',
    danger:  '#ef4444',
    warning: '#f59e0b',
    info:    '#3b82f6',
  };
  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };

  const toast = document.createElement('div');
  toast.style.cssText = `
    position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
    background:#fff; border-left:4px solid ${colors[type]};
    border-radius:10px; padding:.85rem 1.1rem;
    display:flex; align-items:center; gap:.65rem;
    box-shadow:0 8px 30px rgba(0,0,0,.15);
    font-family:'DM Sans',sans-serif; font-size:.88rem; font-weight:500;
    color:#1a1d23; max-width:320px;
    animation: toastIn .25s ease;
  `;
  toast.innerHTML = `<i class="bi ${icons[type]}" style="color:${colors[type]};font-size:1.1rem"></i><span>${message}</span>`;
  document.body.appendChild(toast);

  const style = document.createElement('style');
  style.textContent = `@keyframes toastIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }`;
  document.head.appendChild(style);

  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; }, 3200);
  setTimeout(() => toast.remove(), 3600);
}

/* ── Format Rupiah ───────────────────────────────────────── */
function formatRupiah(num) {
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

/* ── AJAX helper ─────────────────────────────────────────── */
async function bmPost(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  return res.json();
}
