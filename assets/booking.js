(function () {
  'use strict';

  function init(wrapper) {
    if (!wrapper || wrapper.dataset.mcGhlInit) return;
    const form = wrapper.querySelector('form.elementor-form');
    if (!form) return;
    const fields = form.querySelector('.elementor-form-fields-wrapper');
    const submit = form.querySelector('[type="submit"]');
    if (!fields || !submit) return;
    wrapper.dataset.mcGhlInit = '1';

    const buttonText = submit.querySelector('.elementor-button-text');
    if (buttonText) buttonText.textContent = 'Continue';

    const panel = document.createElement('section');
    panel.className = 'mc-ghl-booking-step';
    panel.hidden = true;
    panel.innerHTML = [
      '<div class="mc-ghl-calendar-view">',
      '<h3>Choose an appointment date</h3>',
      '<p class="mc-ghl-status" aria-live="polite">Loading available dates…</p>',
      '<div class="mc-ghl-calendar">',
      '<div class="mc-ghl-calendar-heading"><button type="button" class="mc-ghl-month-prev" aria-label="Previous month">‹</button><strong class="mc-ghl-month-label"></strong><button type="button" class="mc-ghl-month-next" aria-label="Next month">›</button></div>',
      '<div class="mc-ghl-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>',
      '<div class="mc-ghl-calendar-days"></div>',
      '</div>',
      '<button type="button" class="mc-ghl-back-to-form">Back to your details</button>',
      '</div>',
      '<div class="mc-ghl-time-view" hidden>',
      '<h3>Choose Time Slot</h3>',
      '<p class="mc-ghl-date-label"></p>',
      '<p class="mc-ghl-timezone-label"></p>',
      '<div class="mc-ghl-slot-list"></div>',
      '<div class="mc-ghl-time-actions"><button type="button" class="mc-ghl-back-to-calendar">Back to dates</button><button type="button" class="mc-ghl-confirm" hidden>Confirm</button></div>',
      '</div>',
      '<p class="mc-ghl-submitting" aria-live="polite" hidden>Confirming your appointment…</p>'
    ].join('');
    fields.appendChild(panel);

    const slotId = wrapper.dataset.mcGhlSlotField || 'ghl_slot';
    let slotInput = Array.from(form.elements).find((element) => element.name === 'form_fields[' + slotId + ']');
    if (!slotInput) {
      slotInput = document.createElement('input');
      slotInput.type = 'hidden';
      slotInput.name = 'form_fields[' + slotId + ']';
      form.appendChild(slotInput);
    }

    const calendarView = panel.querySelector('.mc-ghl-calendar-view');
    const timeView = panel.querySelector('.mc-ghl-time-view');
    const status = panel.querySelector('.mc-ghl-status');
    const dayGrid = panel.querySelector('.mc-ghl-calendar-days');
    const monthLabel = panel.querySelector('.mc-ghl-month-label');
    const slotList = panel.querySelector('.mc-ghl-slot-list');
    const confirmButton = panel.querySelector('.mc-ghl-confirm');
    const submitting = panel.querySelector('.mc-ghl-submitting');
    const originalChildren = Array.from(fields.children).filter((element) => element !== panel);
    let stage = 'form';
    let started = false;
    let displayTimezone = 'UTC';
    let slotsByDate = {};
    let dates = [];
    let months = [];
    let monthIndex = 0;
    let selectedSlot = '';
    let selectedDate = '';

    function showForm(clearSelection) {
      stage = 'form';
      originalChildren.forEach((element) => { element.hidden = false; });
      panel.hidden = true;
      if (clearSelection) {
        selectedSlot = '';
        selectedDate = '';
        slotInput.value = '';
      }
      fields.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showCalendar() {
      stage = 'calendar';
      originalChildren.forEach((element) => { element.hidden = true; });
      panel.hidden = false;
      calendarView.hidden = false;
      timeView.hidden = true;
      submitting.hidden = true;
      renderCalendar();
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function dateParts(dateString) {
      const parts = dateString.split('-').map(Number);
      return { year: parts[0], month: parts[1] - 1, day: parts[2] };
    }

    function renderCalendar() {
      if (!months.length) return;
      const month = months[monthIndex];
      const [year, monthNumber] = month.split('-').map(Number);
      const firstWeekday = new Date(year, monthNumber - 1, 1).getDay();
      const daysInMonth = new Date(year, monthNumber, 0).getDate();
      monthLabel.textContent = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(new Date(year, monthNumber - 1, 1));
      panel.querySelector('.mc-ghl-month-prev').disabled = monthIndex === 0;
      panel.querySelector('.mc-ghl-month-next').disabled = monthIndex === months.length - 1;
      dayGrid.replaceChildren();
      for (let blank = 0; blank < firstWeekday; blank += 1) {
        const spacer = document.createElement('span');
        spacer.className = 'mc-ghl-day-spacer';
        dayGrid.appendChild(spacer);
      }
      for (let day = 1; day <= daysInMonth; day += 1) {
        const dateString = year + '-' + String(monthNumber).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mc-ghl-day';
        button.textContent = String(day);
        const count = (slotsByDate[dateString] || []).length;
        button.disabled = count === 0 || !dates.includes(dateString);
        button.setAttribute('aria-label', dateString + (count ? ', ' + count + ' available times' : ', no available times'));
        if (count) button.classList.add('has-slots');
        button.addEventListener('click', () => showTimes(dateString));
        dayGrid.appendChild(button);
      }
    }

    function showTimes(dateString) {
      selectedDate = dateString;
      selectedSlot = '';
      slotInput.value = '';
      stage = 'times';
      calendarView.hidden = true;
      timeView.hidden = false;
      submitting.hidden = true;
      confirmButton.hidden = true;
      confirmButton.disabled = false;
      slotList.replaceChildren();
      const parts = dateParts(dateString);
      panel.querySelector('.mc-ghl-date-label').textContent = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).format(new Date(parts.year, parts.month, parts.day));
      panel.querySelector('.mc-ghl-timezone-label').textContent = 'Time zone: ' + displayTimezone.replaceAll('_', ' ');
      (slotsByDate[dateString] || []).forEach((slot) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mc-ghl-slot';
        button.textContent = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: displayTimezone }).format(new Date(slot));
        button.addEventListener('click', () => {
          selectedSlot = slot;
          slotList.querySelectorAll('.mc-ghl-slot').forEach((item) => item.setAttribute('aria-pressed', 'false'));
          button.setAttribute('aria-pressed', 'true');
          confirmButton.hidden = false;
        });
        slotList.appendChild(button);
      });
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function loadSlots() {
      if (started) return;
      started = true;
      status.textContent = 'Loading available dates…';
      const zoneSetting = wrapper.dataset.mcGhlTimezone || 'site';
      const visitorZone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
      displayTimezone = zoneSetting === 'visitor' ? visitorZone : (zoneSetting === 'site' ? wrapper.dataset.mcGhlSiteTimezone || 'UTC' : zoneSetting);
      const body = new URLSearchParams({ action: 'mc_ghl_booking_slots', nonce: wrapper.dataset.mcGhlNonce || '', locationId: wrapper.dataset.mcGhlLocation || '', calendarId: wrapper.dataset.mcGhlCalendar || '', zone: zoneSetting, visitorTimezone: visitorZone, signature: wrapper.dataset.mcGhlSignature || '' });
      try {
        const response = await fetch(mcGhlBooking.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
        const result = await response.json();
        if (!response.ok || !result.success) {
          const message = result.data && result.data.message ? result.data.message : 'Could not load appointment times.';
          const debug = result.data && result.data.debug ? ' ' + result.data.debug : '';
          throw new Error(message + debug);
        }
        const availability = result.data && result.data.slots ? result.data.slots : {};
        displayTimezone = result.data && result.data.timezone ? result.data.timezone : displayTimezone;
        slotsByDate = {};
        dates = Object.keys(availability).sort();
        Object.entries(availability).forEach(([date, value]) => { slotsByDate[date] = value.slots || []; });
        months = Array.from(new Set(dates.map((date) => date.slice(0, 7))));
        const hasSlots = Object.values(slotsByDate).some((slots) => slots.length > 0);
        if (!hasSlots) {
          status.textContent = 'There are no available times in the next 30 days. Please contact us.';
          started = false;
          return;
        }
        status.textContent = 'Select a date with available times. Time zone: ' + displayTimezone.replaceAll('_', ' ') + '.';
        monthIndex = 0;
        renderCalendar();
      } catch (error) {
        status.textContent = error.message;
        started = false;
      }
    }

    form.addEventListener('submit', (event) => {
      if (stage === 'form') {
        if (!form.reportValidity()) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        showCalendar();
        loadSlots();
      } else if (stage !== 'submitting') {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);

    panel.querySelector('.mc-ghl-month-prev').addEventListener('click', () => { if (monthIndex > 0) { monthIndex -= 1; renderCalendar(); } });
    panel.querySelector('.mc-ghl-month-next').addEventListener('click', () => { if (monthIndex < months.length - 1) { monthIndex += 1; renderCalendar(); } });
    panel.querySelector('.mc-ghl-back-to-form').addEventListener('click', () => showForm(true));
    panel.querySelector('.mc-ghl-back-to-calendar').addEventListener('click', showCalendar);
    confirmButton.addEventListener('click', () => {
      if (!selectedSlot) return;
      slotInput.value = selectedSlot;
      stage = 'submitting';
      calendarView.hidden = true;
      timeView.hidden = true;
      submitting.hidden = false;
      confirmButton.disabled = true;
      if (typeof form.requestSubmit === 'function') form.requestSubmit(submit);
      else submit.click();
    });

    if (window.jQuery) {
      jQuery(document).on('submit_success', function (event) {
        if (event.target !== form) return;
        stage = 'done';
        panel.hidden = true;
      });
      jQuery(form).on('submit_error', function () {
        if (stage !== 'submitting') return;
        stage = 'times';
        submitting.hidden = true;
        timeView.hidden = false;
        confirmButton.disabled = false;
      });
    }
  }

  function scan() {
    document.querySelectorAll('.elementor-widget-form[data-mc-ghl-calendar]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
  if (window.jQuery) jQuery(window).on('elementor/frontend/init', scan);
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
})();
