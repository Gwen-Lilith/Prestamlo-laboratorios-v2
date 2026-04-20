/**
 * Utilidades de UI globales:
 *   - Botón flotante "Volver" (en páginas internas).
 *   - Cierre del dropdown "Acciones Rápidas" al clickear un item o ESC.
 *
 * El script se incluye en todas las páginas; el comportamiento concreto
 * se activa según lo que exista en el DOM de cada una.
 */
(function () {
  // ── Dropdown "Acciones Rápidas" — cerrar siempre al hacer click en un item ──
  document.addEventListener('click', function (e) {
    const item = e.target.closest('.dropdown-item');
    if (!item) return;
    const dd = document.getElementById('dropdown-acciones');
    if (!dd) return;
    dd.classList.remove('open');
    // Si el link va a la misma página en la que estamos, evitamos la
    // recarga silenciosa: el dropdown ya se cerró, no hace falta navegar.
    try {
      const href = item.getAttribute('href');
      if (href) {
        const target = new URL(href, window.location.href);
        if (target.pathname === window.location.pathname && target.search === window.location.search) {
          e.preventDefault();
        }
      }
    } catch (_) {}
  });

  // ── ESC cierra dropdown de Acciones Rápidas ──
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    const dd = document.getElementById('dropdown-acciones');
    if (dd && dd.classList.contains('open')) {
      dd.classList.remove('open');
    }
  });

  // ── Botón flotante "Volver" — solo en páginas internas ──
  const fn = (typeof window.location.pathname === 'string' ? window.location.pathname : '')
    .split('/').pop().toLowerCase();

  const home = new Set([
    '', 'index.html', 'dashboard.html', 'dashboard-usuario.html', 'seleccion-modulo.html'
  ]);
  if (home.has(fn)) return;

  function irAtras() {
    if (window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
      window.history.back();
    } else {
      // Si llegaron con link directo, mandar al dashboard según rol.
      let destino = 'dashboard.html';
      try {
        const u = JSON.parse(sessionStorage.getItem('usuario') || '{}');
        if (u.rol === 'profesor') destino = 'dashboard-usuario.html';
      } catch (_) {}
      window.location.href = destino;
    }
  }

  function insertar() {
    if (document.getElementById('btn-volver-global')) return;
    const btn = document.createElement('button');
    btn.id = 'btn-volver-global';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Volver');
    btn.innerHTML = '<i class="fa-solid fa-arrow-left"></i><span>Volver</span>';
    btn.style.cssText = [
      'position:fixed',
      'left:12px',
      'bottom:16px',
      'z-index:9999',
      'display:inline-flex',
      'align-items:center',
      'gap:8px',
      'padding:9px 14px',
      'background:#6B1F7C',
      'color:#fff',
      'border:none',
      'border-radius:999px',
      'font-family:Nunito, sans-serif',
      'font-size:13px',
      'font-weight:700',
      'cursor:pointer',
      'box-shadow:0 6px 20px rgba(107,31,124,0.35)',
      'transition:transform 0.15s, background 0.2s'
    ].join(';');
    btn.onmouseenter = () => { btn.style.background = '#4A1258'; btn.style.transform = 'translateY(-1px)'; };
    btn.onmouseleave = () => { btn.style.background = '#6B1F7C'; btn.style.transform = 'translateY(0)'; };
    btn.onclick = irAtras;

    // ESC también regresa
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !document.querySelector('.modal-overlay.open, .modal-overlay[style*="flex"]')) {
        irAtras();
      }
    });

    document.body.appendChild(btn);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', insertar);
  } else {
    insertar();
  }
})();
