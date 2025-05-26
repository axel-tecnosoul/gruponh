<?php
// session_js.php — asume que $expiresAt viene de config.php
if (!isset($expiresAt) || !defined('WARNING_OFFSET')) {
  // si no están, salimos sin imprimir nada
  return;
}
?>
<script>
// Offset de aviso y expiración (segundos), inyectados desde PHP
const WARNING_OFFSET   = <?= WARNING_OFFSET ?>;
let sessionExpiresAt   = <?= $expiresAt ?>;
let warningTime        = sessionExpiresAt - WARNING_OFFSET;

// Calcula ms hasta un UNIX timestamp
function msUntil(ts) { return (ts * 1000) - Date.now(); }

// Crea el canal para sincronizar entre pestañas
const bc = new BroadcastChannel('session-channel');

function showWarning() {
  if (Date.now() >= sessionExpiresAt * 1000) return;
  if (confirm("Tu sesión expira en " + WARNING_OFFSET + " s. ¿Extenderla?")) {
    fetch('keepalive.php')
      .then(r => r.json())
      .then(d => {
        if (d.status === 'ok') {
          // 1) Actualiza tu propio timer
          sessionExpiresAt = d.expires_at;
          warningTime      = sessionExpiresAt - WARNING_OFFSET;
          resetTimers();
          // 2) Publica el nuevo expires_at a las otras pestañas
          bc.postMessage({ expires_at: d.expires_at });
        } else {
          expireSession();
        }
      })
      .catch(expireSession);
  }
}

function expireSession() {
  alert("Sesión expirada. Serás redirigido al login.");
  window.location = 'login.php?sesion=1';
}

function resetTimers() {
  clearTimeout(window._warnTO);
  clearTimeout(window._expireTO);
  window._warnTO   = setTimeout(showWarning, msUntil(warningTime));
  window._expireTO = setTimeout(expireSession, msUntil(sessionExpiresAt));
}

// Contador visible en #tiempoSesion
function formatMMSS(sec) {
  const m = String(Math.floor(sec / 60)).padStart(2, '0');
  const s = String(sec % 60).padStart(2, '0');
  return `${m}:${s}`;
}

function startCountdown() {
  const span = document.getElementById('tiempoSesion');
  if (!span) return;
  function tick() {
    let rem = sessionExpiresAt - Math.floor(Date.now() / 1000);
    span.textContent = formatMMSS(rem > 0 ? rem : 0);
    if (rem <= 0) clearInterval(window._countdownSI);
  }
  tick();
  window._countdownSI = setInterval(tick, 1000);
}

// Escucha mensajes de otras pestañas
bc.onmessage = (ev) => {
  const newExp = ev.data.expires_at;
  if (newExp > sessionExpiresAt) {
    sessionExpiresAt = newExp;
    warningTime      = newExp - WARNING_OFFSET;
    resetTimers();
  }
};

// Al cargar la página, sincroniza y arranca todo
window.addEventListener('load', () => {
  // 1) Publica inmediatamente el expires_at actual
  bc.postMessage({ expires_at: sessionExpiresAt });

  // 2) Inicia el contador visible
  startCountdown();

  // 3) Programa los timeouts para warning y expiración
  window._warnTO   = setTimeout(showWarning, msUntil(warningTime));
  window._expireTO = setTimeout(expireSession, msUntil(sessionExpiresAt));
});
</script>