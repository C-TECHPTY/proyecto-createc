(function () {
  const menuToggle = document.querySelector('.admin-menu-toggle');
  const adminNav = document.getElementById('adminNav');
  if (menuToggle && adminNav) {
    menuToggle.addEventListener('click', () => {
      const isOpen = adminNav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', String(isOpen));
      menuToggle.textContent = isOpen ? 'Cerrar' : 'Menú';
    });
  }

  const count = document.getElementById('notificationCount');
  const toast = document.getElementById('notificationToast');
  const toastTitle = document.getElementById('notificationToastTitle');
  const toastBody = document.getElementById('notificationToastBody');
  const toastLink = document.getElementById('notificationToastLink');
  const toastClose = document.getElementById('notificationToastClose');
  let lastSeenId = Number(window.localStorage.getItem('rifagrid_last_notification_id') || 0);
  let csrf = '';
  let audioContext = null;

  async function pollNotifications() {
    if (!count) return;
    try {
      const response = await fetch('../api/notifications.php', { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!data.ok) return;
      count.textContent = data.count;
      csrf = data.csrf || csrf;

      const newestUnread = (data.items || []).find((item) => !item.read_at);
      if (newestUnread && Number(newestUnread.id) > lastSeenId) {
        lastSeenId = Number(newestUnread.id);
        window.localStorage.setItem('rifagrid_last_notification_id', String(lastSeenId));
        showToast(newestUnread);
        playNotificationSound();
      }
    } catch (error) {
      // The panel keeps working even if polling fails.
    }
  }

  function showToast(item) {
    if (!toast || !toastTitle || !toastBody || !toastLink) return;
    toastTitle.textContent = item.title || 'Nueva notificación';
    toastBody.textContent = item.body || '';
    toastLink.href = item.url || 'notificaciones.php';
    toast.hidden = false;
    window.setTimeout(() => {
      if (toast) toast.hidden = true;
    }, 9000);
  }

  function playNotificationSound() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      audioContext = audioContext || new AudioCtx();
      if (audioContext.state === 'suspended') {
        audioContext.resume().catch(() => undefined);
      }
      const oscillator = audioContext.createOscillator();
      const gain = audioContext.createGain();
      oscillator.type = 'sine';
      oscillator.frequency.setValueAtTime(740, audioContext.currentTime);
      oscillator.frequency.setValueAtTime(920, audioContext.currentTime + 0.09);
      gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.08, audioContext.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.28);
      oscillator.connect(gain);
      gain.connect(audioContext.destination);
      oscillator.start();
      oscillator.stop(audioContext.currentTime + 0.3);
    } catch (error) {
      // Browsers may block audio until user interaction.
    }
  }

  if (toastClose) {
    toastClose.addEventListener('click', () => {
      if (toast) toast.hidden = true;
    });
  }

  document.addEventListener('click', () => {
    if (audioContext && audioContext.state === 'suspended') {
      audioContext.resume().catch(() => undefined);
    }
  }, { once: true });

  pollNotifications();
  setInterval(pollNotifications, 30000);

  async function postAction(url, reservationId, csrf) {
    const formData = new FormData();
    formData.set('reservation_id', reservationId);
    formData.set('_csrf', csrf);
    const response = await fetch(url, { method: 'POST', body: formData, headers: { Accept: 'application/json' } });
    const data = await response.json();
    if (!data.ok) throw new Error(data.message || 'No se pudo completar la acción.');
    return data;
  }

  document.addEventListener('click', async (event) => {
    const confirmButton = event.target.closest('.js-confirm-payment');
    const cancelButton = event.target.closest('.js-cancel-reservation');
    if (!confirmButton && !cancelButton) return;

    const button = confirmButton || cancelButton;
    const isConfirm = Boolean(confirmButton);
    const original = button.textContent;
    button.disabled = true;
    button.textContent = isConfirm ? 'Confirmando...' : 'Cancelando...';

    try {
      const data = await postAction(
        isConfirm ? '../api/confirm_payment.php' : '../api/cancel_reservation.php',
        button.dataset.id,
        button.dataset.csrf
      );
      if (data.whatsapp_url) {
        window.open(data.whatsapp_url, '_blank', 'noopener');
      }
      await pollNotifications();
      window.location.reload();
    } catch (error) {
      alert(error.message);
      button.disabled = false;
      button.textContent = original;
    }
  });

  const winnerForm = document.getElementById('winnerForm');
  if (winnerForm) {
    winnerForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const result = document.getElementById('winnerResult');
      const button = winnerForm.querySelector('button[type="submit"]');
      button.disabled = true;
      button.textContent = 'Procesando...';
      if (result) {
        result.className = 'reserve-result';
        result.textContent = '';
      }
      try {
        const response = await fetch('../api/mark_winner.php', {
          method: 'POST',
          body: new FormData(winnerForm),
          headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.message || 'No se pudo marcar ganadores.');
        if (result) {
          result.className = 'reserve-result is-success';
          result.textContent = data.message;
        }
        window.setTimeout(() => window.location.reload(), 1100);
      } catch (error) {
        if (result) {
          result.className = 'reserve-result is-error';
          result.textContent = error.message;
        }
        button.disabled = false;
        button.textContent = 'Marcar ganadores';
      }
    });
  }

  const freeNumberForm = document.getElementById('freeNumberForm');
  if (freeNumberForm) {
    freeNumberForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const result = document.getElementById('freeNumberResult');
      const button = freeNumberForm.querySelector('button[type="submit"]');
      button.disabled = true;
      button.textContent = 'Aplicando...';
      if (result) {
        result.className = 'reserve-result';
        result.textContent = '';
      }
      try {
        const response = await fetch('../api/apply_free_number.php', {
          method: 'POST',
          body: new FormData(freeNumberForm),
          headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.message || 'No se pudo aplicar el número.');
        if (result) {
          result.className = 'reserve-result is-success';
          result.textContent = data.message;
        }
        window.setTimeout(() => window.location.reload(), 1000);
      } catch (error) {
        if (result) {
          result.className = 'reserve-result is-error';
          result.textContent = error.message;
        }
        button.disabled = false;
        button.textContent = 'Aplicar número gratis';
      }
    });
  }
})();
