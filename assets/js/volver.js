/**
 * Utilidades de UI globales:
 *   - Botón flotante "Volver" (en páginas internas).
 *   - Cierre del dropdown "Acciones Rápidas" al clickear un item o ESC.
 *
 * El script se incluye en todas las páginas; el comportamiento concreto
 * se activa según lo que exista en el DOM de cada una.
 */
(function () {
  // ── Bandeja de notificaciones in-app (campanita) ──────────────────────
  // Se inyecta en la navbar en todas las páginas internas. Hace polling
  // cada 60s al endpoint backend/modules/notificaciones/listar.php.
  function inyectarBandejaNotificaciones() {
    // Solo si hay usuario logueado.
    let usr = null;
    try { usr = JSON.parse(sessionStorage.getItem('usuario') || 'null'); } catch (_) {}
    if (!usr) return;

    // Si la página ya tiene un contenedor con id 'notif-bell', reutilizarlo.
    if (document.getElementById('notif-bell')) return;

    // Crear el botón de campana y panel
    const navRight = document.querySelector('.navbar-right, .nb-right');
    if (!navRight) return;  // página sin navbar estándar

    const wrap = document.createElement('div');
    wrap.id = 'notif-bell-wrap';
    wrap.style.cssText = 'position:relative;margin-right:6px';
    wrap.innerHTML = `
      <button id="notif-bell" type="button"
              style="background:rgba(255,255,255,.15);border:none;color:#fff;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:14px;position:relative">
        <i class="fa-solid fa-bell"></i>
        <span id="notif-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#DC2626;color:#fff;border-radius:10px;font-size:10px;font-weight:700;padding:2px 6px;min-width:16px;text-align:center"></span>
      </button>
      <div id="notif-panel"
           style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:340px;max-height:420px;overflow-y:auto;background:#fff;color:#1A1A2E;border:1px solid #E2E2EE;border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.18);z-index:10000">
        <div style="padding:10px 14px;border-bottom:1px solid #E2E2EE;display:flex;justify-content:space-between;align-items:center">
          <strong style="font-size:13px">Notificaciones</strong>
          <button id="notif-marcar-todas" type="button"
                  style="background:none;border:none;font-size:11px;color:#6B1F7C;cursor:pointer;font-weight:600">
            Marcar todas leídas
          </button>
        </div>
        <div id="notif-lista">
          <div style="padding:18px;text-align:center;color:#AAAABF;font-size:12px">Cargando…</div>
        </div>
      </div>
    `;
    navRight.insertBefore(wrap, navRight.firstChild);

    const btn   = document.getElementById('notif-bell');
    const panel = document.getElementById('notif-panel');
    const badge = document.getElementById('notif-badge');
    const lista = document.getElementById('notif-lista');

    btn.addEventListener('click', e => {
      e.stopPropagation();
      panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) panel.style.display = 'none';
    });

    document.getElementById('notif-marcar-todas').addEventListener('click', async () => {
      await fetch('backend/modules/notificaciones/marcar_leida.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ todas: true })
      }).catch(()=>{});
      cargarNotif();
    });

    function escHtml(s){return String(s==null?'':s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
    function tipoIcon(t) {
      const map = {
        aprobada: 'fa-circle-check', rechazada: 'fa-circle-xmark',
        prestada: 'fa-box-open', devuelta: 'fa-circle-check',
        vencida: 'fa-clock', alerta_foto: 'fa-camera',
        stock_bajo: 'fa-triangle-exclamation', info: 'fa-circle-info'
      };
      return map[t] || 'fa-bell';
    }
    function tipoColor(t) {
      const map = {
        aprobada: '#16A34A', rechazada: '#DC2626',
        prestada: '#D97706', devuelta: '#16A34A',
        vencida: '#DC2626', alerta_foto: '#7C3AED',
        stock_bajo: '#D97706', info: '#2563EB'
      };
      return map[t] || '#6B6B85';
    }

    async function cargarNotif() {
      try {
        const resp = await fetch('backend/modules/notificaciones/listar.php?_t=' + Date.now(),
          { credentials:'same-origin' });
        const json = await resp.json();
        if (!resp.ok || !json.ok) return;
        const { notificaciones, no_leidas } = json.data;
        if (no_leidas > 0) { badge.style.display = 'inline-block'; badge.textContent = no_leidas > 99 ? '99+' : no_leidas; }
        else badge.style.display = 'none';
        if (!notificaciones.length) {
          lista.innerHTML = `<div style="padding:24px;text-align:center;color:#AAAABF;font-size:12px">
            <i class="fa-regular fa-bell-slash" style="font-size:24px;display:block;margin-bottom:6px"></i>
            Sin notificaciones</div>`;
          return;
        }
        lista.innerHTML = notificaciones.map(n => `
          <div data-id="${n.n_idnotificacion}" data-link="${escHtml(n.t_link||'')}"
               style="padding:10px 14px;border-bottom:1px solid #F3F3F8;cursor:pointer;display:flex;gap:10px;${n.t_leida==='N'?'background:#FAF5FB':''}"
               class="notif-item">
            <div style="width:32px;height:32px;flex-shrink:0;border-radius:50%;background:${tipoColor(n.t_tipo)}1A;color:${tipoColor(n.t_tipo)};display:flex;align-items:center;justify-content:center">
              <i class="fa-solid ${tipoIcon(n.t_tipo)}"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:12px;font-weight:700;color:#1A1A2E">${escHtml(n.t_titulo)}</div>
              <div style="font-size:11px;color:#6B6B85;margin-top:2px;line-height:1.35">${escHtml(n.t_mensaje)}</div>
              <div style="font-size:10px;color:#AAAABF;margin-top:4px">${escHtml((n.dt_fechacreacion||'').replace('T',' ').substring(0,16))}</div>
            </div>
          </div>`).join('');
        // Click en item: marca como leída y navega si tiene link
        lista.querySelectorAll('.notif-item').forEach(it => {
          it.addEventListener('click', async () => {
            const id = it.dataset.id;
            const link = it.dataset.link;
            await fetch('backend/modules/notificaciones/marcar_leida.php', {
              method:'POST', credentials:'same-origin',
              headers:{'Content-Type':'application/json'},
              body: JSON.stringify({ id: parseInt(id, 10) })
            }).catch(()=>{});
            if (link) window.location.href = link;
            else cargarNotif();
          });
        });
      } catch (_) { /* ignore */ }
    }

    cargarNotif();
    setInterval(cargarNotif, 60000); // refrescar cada 60s
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inyectarBandejaNotificaciones);
  } else {
    inyectarBandejaNotificaciones();
  }

  // ── Avatar global: si el usuario tiene foto de perfil guardada, úsala ──
  // El sessionStorage guarda la ruta en usr.foto tras subir o al hacer login.
  // Se aplica a cualquier <img id="nav-avatar-img"> en páginas internas.
  function aplicarFotoPerfil() {
    try {
      const usr = JSON.parse(sessionStorage.getItem('usuario') || 'null');
      if (!usr || !usr.foto) return;
      document.querySelectorAll('#nav-avatar-img, .nav-avatar img').forEach(img => {
        img.src = usr.foto + '?t=' + Date.now();
      });
    } catch (_) { /* ignore */ }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aplicarFotoPerfil);
  } else {
    aplicarFotoPerfil();
  }

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
