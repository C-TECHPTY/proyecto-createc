(function () {
  const page = document.querySelector('.raffle-layout');
  if (!page) return;

  const price = Number(page.dataset.price || 0);
  const grid = document.getElementById('numberGrid');
  const form = document.getElementById('reserveForm');
  const selectedInput = document.getElementById('selectedNumbers');
  const selectedLabel = document.getElementById('selectedLabel');
  const totalLabel = document.getElementById('totalLabel');
  const result = document.getElementById('reserveResult');
  const selected = new Set();

  function formatMoney(value) {
    return 'B/.' + value.toFixed(2);
  }

  function refreshSummary() {
    const numbers = Array.from(selected).sort((a, b) => a - b);
    selectedInput.value = numbers.join(',');
    selectedLabel.textContent = numbers.length ? 'Números: ' + numbers.map((n) => String(n).padStart(2, '0')).join(', ') : 'Sin números seleccionados';
    totalLabel.textContent = formatMoney(numbers.length * price);
  }

  grid.addEventListener('click', (event) => {
    const cell = event.target.closest('.number-cell');
    if (!cell || cell.disabled) return;
    const number = Number(cell.dataset.number);
    if (selected.has(number)) {
      selected.delete(number);
      cell.classList.remove('is-selected');
    } else {
      selected.add(number);
      cell.classList.add('is-selected');
    }
    refreshSummary();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!selected.size) {
      result.className = 'reserve-result is-error';
      result.textContent = 'Selecciona al menos un número.';
      return;
    }

    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Reservando...';
    result.className = 'reserve-result';
    result.textContent = '';

    try {
      const response = await fetch(window.RIFA_CONFIG.reserveEndpoint, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!data.ok) throw new Error(data.message || 'No se pudo reservar.');

      result.className = 'reserve-result is-success';
      result.innerHTML = `
        <strong>${data.message}</strong><br>
        Total: ${data.total}<br>
        <a class="button whatsapp" target="_blank" href="${data.whatsapp_url}">Enviar comprobante por WhatsApp</a>
        <form class="receipt-form" id="receiptForm" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="${form.querySelector('[name="_csrf"]').value}">
          <input type="hidden" name="reservation_id" value="${data.reservation_id}">
          <label>Subir comprobante JPG, PNG o PDF
            <input type="file" name="receipt" accept="image/jpeg,image/png,application/pdf" required>
          </label>
          <button class="button button-ghost" type="submit">Subir comprobante</button>
          <div class="receipt-message"></div>
        </form>`;
      selected.forEach((number) => {
        const cell = grid.querySelector(`[data-number="${number}"]`);
        if (cell) {
          cell.classList.remove('is-selected');
          cell.classList.add('is-reserved');
          cell.disabled = true;
        }
      });
      selected.clear();
      refreshSummary();
      form.reset();
    } catch (error) {
      result.className = 'reserve-result is-error';
      result.textContent = error.message;
    } finally {
      button.disabled = false;
      button.textContent = 'Reservar números';
    }
  });

  document.addEventListener('submit', async (event) => {
    const receiptForm = event.target.closest('#receiptForm');
    if (!receiptForm) return;
    event.preventDefault();

    const button = receiptForm.querySelector('button');
    const message = receiptForm.querySelector('.receipt-message');
    button.disabled = true;
    message.textContent = 'Subiendo...';

    try {
      const response = await fetch('../api/upload_receipt.php', {
        method: 'POST',
        body: new FormData(receiptForm),
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!data.ok) throw new Error(data.message || 'No se pudo subir.');
      message.className = 'receipt-message is-success';
      message.textContent = data.message;
      receiptForm.querySelector('input[type="file"]').disabled = true;
    } catch (error) {
      message.className = 'receipt-message is-error';
      message.textContent = error.message;
      button.disabled = false;
    }
  });

  refreshSummary();
})();
