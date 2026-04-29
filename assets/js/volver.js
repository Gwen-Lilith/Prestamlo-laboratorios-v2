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

/* ════════════════════════════════════════════════════════════════════
   Diálogos UI globales — reemplazan los confirm()/alert()/prompt()
   nativos del navegador (esos popups negros "localhost dice...")
   por modales internos consistentes con el resto de la app.

   Cualquier archivo HTML que importe volver.js puede usar:
       await uiConfirm({ titulo, mensaje, ok, peligro:true })
       await uiAlert({ titulo, mensaje, tipo:'info'|'warning'|'error'|'success' })
       await uiPrompt({ titulo, mensaje, defecto, multilinea:true })
   Las tres funciones devuelven Promise: confirm/prompt resuelven con
   true/false o con string/null. alert siempre resuelve con true.
   ════════════════════════════════════════════════════════════════════ */
(function () {
  // Evitar duplicar la inyección si volver.js se carga dos veces
  if (window.uiConfirm) return;

  function montar(html) {
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    const node = wrap.firstElementChild;
    document.body.appendChild(node);
    // Forzar reflow para que la transición de entrada se vea
    void node.offsetWidth;
    node.classList.add('open');
    return node;
  }

  function cerrar(node) {
    node.classList.remove('open');
    setTimeout(() => node.remove(), 180);
  }

  const COLORES = {
    info:    { bg:'#2563EB', icono:'fa-circle-info' },
    warning: { bg:'#F59E0B', icono:'fa-triangle-exclamation' },
    error:   { bg:'#DC2626', icono:'fa-circle-xmark' },
    success: { bg:'#16A34A', icono:'fa-circle-check' },
    danger:  { bg:'#DC2626', icono:'fa-triangle-exclamation' },
    primary: { bg:'#6B1F7C', icono:'fa-circle-question' }
  };

  function escHtml(s){return String(s==null?'':s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

  // Estilos base — iguales a los modales que ya existen en los HTML admin
  const ESTILO_OVERLAY = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity .18s';
  const ESTILO_OPEN    = 'opacity:1';
  const ESTILO_BOX     = 'background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.28);width:100%;max-width:440px;overflow:hidden;font-family:inherit;color:#1A1A2E';

  /**
   * Confirmación: devuelve Promise<boolean>.
   * Sustituto directo de confirm(). El sí queda en color "peligro"
   * (rojo) si opciones.peligro = true, sino en morado UPB.
   */
  window.uiConfirm = function (opciones) {
    return new Promise(resolve => {
      const o = opciones || {};
      const tipo  = o.peligro ? 'danger' : 'primary';
      const cfg   = COLORES[tipo];
      const ok    = o.ok    || (o.peligro ? 'Confirmar' : 'Aceptar');
      const cancel= o.cancel|| 'Cancelar';
      const titulo= o.titulo|| '¿Confirmar acción?';
      const mensaje = o.mensaje || '';
      const html = `
        <div class="ui-modal" style="${ESTILO_OVERLAY}">
          <div style="${ESTILO_BOX}">
            <div style="display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid #EDEDF3">
              <div style="width:34px;height:34px;border-radius:50%;background:${cfg.bg};color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fa-solid ${cfg.icono}"></i>
              </div>
              <div style="font-weight:700;font-size:15px">${escHtml(titulo)}</div>
            </div>
            <div style="padding:18px 22px;font-size:13.5px;line-height:1.55;color:#4B4B66;white-space:pre-wrap">${escHtml(mensaje)}</div>
            <div style="display:flex;gap:8px;padding:12px 18px 16px;justify-content:flex-end">
              <button type="button" data-ui-cancel style="background:#F3F4F6;color:#1A1A2E;border:1px solid #E5E7EB;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">${escHtml(cancel)}</button>
              <button type="button" data-ui-ok style="background:${cfg.bg};color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">${escHtml(ok)}</button>
            </div>
          </div>
        </div>`;
      const node = montar(html);
      // Aplicar estilo "abierto"
      const overlay = node;
      overlay.style.opacity = '1';
      const close = (val) => { cerrar(overlay); resolve(val); };
      overlay.querySelector('[data-ui-ok]').onclick     = () => close(true);
      overlay.querySelector('[data-ui-cancel]').onclick = () => close(false);
      overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
      // ESC cancela, Enter acepta
      const onKey = (e) => {
        if (e.key === 'Escape') { document.removeEventListener('keydown', onKey); close(false); }
        if (e.key === 'Enter')  { document.removeEventListener('keydown', onKey); close(true); }
      };
      document.addEventListener('keydown', onKey);
      // Foco en OK
      setTimeout(() => overlay.querySelector('[data-ui-ok]')?.focus(), 60);
    });
  };

  /**
   * Alerta informativa — sustituto directo de alert().
   * Para mensajes muy cortos preferir el toast (showToast) que ya existe
   * en algunos HTML; uiAlert es para textos un poco más largos o críticos.
   */
  window.uiAlert = function (opciones) {
    if (typeof opciones === 'string') opciones = { mensaje: opciones };
    const o = opciones || {};
    const tipo = o.tipo || 'info';
    const cfg  = COLORES[tipo] || COLORES.info;
    const titulo = o.titulo || ({info:'Información', warning:'Atención', error:'Error', success:'Listo'}[tipo] || 'Aviso');
    return new Promise(resolve => {
      const html = `
        <div class="ui-modal" style="${ESTILO_OVERLAY}">
          <div style="${ESTILO_BOX}">
            <div style="display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid #EDEDF3">
              <div style="width:34px;height:34px;border-radius:50%;background:${cfg.bg};color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fa-solid ${cfg.icono}"></i>
              </div>
              <div style="font-weight:700;font-size:15px">${escHtml(titulo)}</div>
            </div>
            <div style="padding:18px 22px;font-size:13.5px;line-height:1.55;color:#4B4B66;white-space:pre-wrap">${escHtml(o.mensaje||'')}</div>
            <div style="display:flex;padding:12px 18px 16px;justify-content:flex-end">
              <button type="button" data-ui-ok style="background:${cfg.bg};color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer">Aceptar</button>
            </div>
          </div>
        </div>`;
      const node = montar(html);
      node.style.opacity = '1';
      const close = () => { cerrar(node); resolve(true); };
      node.querySelector('[data-ui-ok]').onclick = close;
      node.addEventListener('click', e => { if (e.target === node) close(); });
      const onKey = (e) => { if (e.key === 'Escape' || e.key === 'Enter') { document.removeEventListener('keydown', onKey); close(); } };
      document.addEventListener('keydown', onKey);
      setTimeout(() => node.querySelector('[data-ui-ok]')?.focus(), 60);
    });
  };

  /**
   * Pedir un input — sustituto directo de prompt().
   * Devuelve Promise<string|null>: el valor o null si cancela.
   * Soporta multilinea (textarea).
   */
  window.uiPrompt = function (opciones) {
    if (typeof opciones === 'string') opciones = { mensaje: opciones };
    const o = opciones || {};
    const titulo  = o.titulo  || 'Ingresa un valor';
    const mensaje = o.mensaje || '';
    const defecto = o.defecto != null ? o.defecto : '';
    const tipoHtml = o.tipo === 'password' ? 'password' : 'text';
    const multi   = !!o.multilinea;
    return new Promise(resolve => {
      const inputHtml = multi
        ? `<textarea data-ui-input rows="4" style="width:100%;padding:10px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-family:inherit;font-size:14px;resize:vertical;box-sizing:border-box">${escHtml(defecto)}</textarea>`
        : `<input data-ui-input type="${tipoHtml}" value="${escHtml(defecto)}" style="width:100%;padding:10px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box"/>`;
      const html = `
        <div class="ui-modal" style="${ESTILO_OVERLAY}">
          <div style="${ESTILO_BOX}">
            <div style="display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid #EDEDF3">
              <div style="width:34px;height:34px;border-radius:50%;background:#6B1F7C;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fa-solid fa-pen"></i>
              </div>
              <div style="font-weight:700;font-size:15px">${escHtml(titulo)}</div>
            </div>
            <div style="padding:16px 22px">
              ${mensaje ? `<div style="font-size:13px;line-height:1.5;color:#4B4B66;margin-bottom:10px;white-space:pre-wrap">${escHtml(mensaje)}</div>` : ''}
              ${inputHtml}
            </div>
            <div style="display:flex;gap:8px;padding:12px 18px 16px;justify-content:flex-end">
              <button type="button" data-ui-cancel style="background:#F3F4F6;color:#1A1A2E;border:1px solid #E5E7EB;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">Cancelar</button>
              <button type="button" data-ui-ok style="background:#6B1F7C;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer">Aceptar</button>
            </div>
          </div>
        </div>`;
      const node = montar(html);
      node.style.opacity = '1';
      const input = node.querySelector('[data-ui-input]');
      const close = (val) => { cerrar(node); resolve(val); };
      node.querySelector('[data-ui-ok]').onclick     = () => close(input.value);
      node.querySelector('[data-ui-cancel]').onclick = () => close(null);
      node.addEventListener('click', e => { if (e.target === node) close(null); });
      // ESC cancela, Enter (sin shift en textarea) acepta
      input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !multi) { e.preventDefault(); close(input.value); }
        if (e.key === 'Enter' && multi && (e.ctrlKey || e.metaKey)) { e.preventDefault(); close(input.value); }
      });
      const onKey = (e) => { if (e.key === 'Escape') { document.removeEventListener('keydown', onKey); close(null); } };
      document.addEventListener('keydown', onKey);
      setTimeout(() => { input.focus(); if (!multi) input.select(); }, 60);
    });
  };
})();
