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
      '<label class="mc-ghl-timezone-picker">Time zone <select class="mc-ghl-timezone-select" aria-label="Time zone"></select></label>',
      '<div class="mc-ghl-calendar-view">',
      '<h3>Choose an appointment date</h3>',
      '<p class="mc-ghl-status" aria-live="polite">Loading available dates…</p>',
      '<div class="mc-ghl-calendar">',
      '<div class="mc-ghl-calendar-heading"><button type="button" class="mc-ghl-month-prev" aria-label="Previous month"></button><strong class="mc-ghl-month-label"></strong><button type="button" class="mc-ghl-month-next" aria-label="Next month"></button></div>',
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
      '<div class="mc-ghl-time-actions"><button type="button" class="mc-ghl-back-to-calendar">Back to dates</button></div>',
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
    let timezoneInput = Array.from(form.elements).find((element) => element.name === 'form_fields[mc_ghl_booking_timezone]');
    if (!timezoneInput) {
      timezoneInput = document.createElement('input');
      timezoneInput.type = 'hidden';
      timezoneInput.name = 'form_fields[mc_ghl_booking_timezone]';
      form.appendChild(timezoneInput);
    }

    const calendarView = panel.querySelector('.mc-ghl-calendar-view');
    const timeView = panel.querySelector('.mc-ghl-time-view');
    const status = panel.querySelector('.mc-ghl-status');
    const dayGrid = panel.querySelector('.mc-ghl-calendar-days');
    const monthLabel = panel.querySelector('.mc-ghl-month-label');
    const slotList = panel.querySelector('.mc-ghl-slot-list');
    let confirmButton = null;
    const timezoneSelect = panel.querySelector('.mc-ghl-timezone-select');
    const submitting = panel.querySelector('.mc-ghl-submitting');
    const originalChildren = Array.from(fields.children).filter((element) => element !== panel);
    let stage = 'form';
    let started = false;
    let displayTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    let requestSequence = 0;
    let slotsByDate = {};
    let dates = [];
    let months = [];
    let monthIndex = 0;
    let selectedSlot = '';
    let selectedDate = '';
    let meetingDetails = { duration: Number(wrapper.dataset.mcGhlDuration || 30), location: wrapper.dataset.mcGhlMeetingLocation || '' };

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
      monthLabel.textContent = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(year, monthNumber - 1, 1, 12)));
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
      slotList.replaceChildren();
      const parts = dateParts(dateString);
      panel.querySelector('.mc-ghl-date-label').textContent = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(parts.year, parts.month, parts.day, 12)));
      panel.querySelector('.mc-ghl-timezone-label').textContent = 'Time zone: ' + timezoneLabel(displayTimezone);
      (slotsByDate[dateString] || []).forEach((slot) => {
        const row = document.createElement('div');
        row.className = 'mc-ghl-slot-row';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mc-ghl-slot';
        button.textContent = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: displayTimezone }).format(new Date(slot));
        const confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'mc-ghl-confirm';
        confirm.textContent = 'Confirm';
        confirm.hidden = true;
        confirm.addEventListener('click', submitBooking);
        button.addEventListener('click', () => {
          selectedSlot = slot;
          slotList.querySelectorAll('.mc-ghl-slot-row').forEach((item) => {
            item.classList.remove('is-selected');
            item.querySelector('.mc-ghl-slot').setAttribute('aria-pressed', 'false');
            item.querySelector('.mc-ghl-confirm').hidden = true;
          });
          row.classList.add('is-selected');
          button.setAttribute('aria-pressed', 'true');
          confirm.hidden = false;
          confirmButton = confirm;
        });
        row.append(button, confirm);
        slotList.appendChild(row);
      });
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function timezoneLabel(zone) {
      try {
        const rawOffset = new Intl.DateTimeFormat('en-US', { timeZone: zone, timeZoneName: 'longOffset' }).formatToParts(new Date()).find((part) => part.type === 'timeZoneName')?.value || 'GMT';
        const match = rawOffset.match(/GMT([+-])(\d{2}):?(\d{2})?/);
        const offset = match ? 'GMT' + match[1] + String(Number(match[2])) + (match[3] && Number(match[3]) ? ':' + match[3] : '') : 'GMT';
        return zone.replaceAll('_', ' ') + ' (' + offset + ')';
      } catch (_) { return zone.replaceAll('_', ' '); }
    }

    function addMeetingSummary() {
      const card = document.createElement('section');
      card.className = 'mc-ghl-meeting-summary';
      card.setAttribute('aria-label', 'Appointment details');
      const start = new Date(selectedSlot);
      const end = new Date(start.getTime() + Number(meetingDetails.duration || 30) * 60000);
      const clock = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: displayTimezone });
      const date = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: displayTimezone });
      const schedule = clock.format(start) + ' – ' + clock.format(end) + ', ' + date.format(start);
      const rows = [
        ['◷', (meetingDetails.duration || 30) + ' min'],
        ['▢', schedule],
        ['♧', meetingDetails.location || ''],
        ['◎', timezoneLabel(displayTimezone)]
      ].filter((row) => row[1]);
      rows.forEach(([icon, text]) => {
        const row = document.createElement('div');
        row.className = 'mc-ghl-meeting-summary-row';
        const mark = document.createElement('span');
        mark.className = 'mc-ghl-meeting-summary-icon';
        mark.setAttribute('aria-hidden', 'true');
        const svg = {
          '◷': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
          '▢': '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/>',
          '♧': '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
          '◎': '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18m0-18a15 15 0 0 0 0 18"/>'
        }[icon];
        mark.innerHTML = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">' + svg + '</svg>';
        const value = document.createElement('span');
        value.textContent = text;
        row.append(mark, value);
        card.appendChild(row);
      });
      const success = wrapper.querySelector('.elementor-message-success');
      if (success) success.insertAdjacentElement('afterend', card);
      else form.insertAdjacentElement('afterend', card);
    }

    function populateTimezones() {
      const zones = Array.isArray(mcGhlBooking.timezones) ? mcGhlBooking.timezones : ['UTC'];
      zones.forEach((zone) => {
        const option = document.createElement('option');
        option.value = zone;
        option.textContent = timezoneLabel(zone);
        timezoneSelect.appendChild(option);
      });
      if (!zones.includes(displayTimezone)) displayTimezone = 'UTC';
      timezoneSelect.value = displayTimezone;
    }

    function submitBooking() {
      if (!selectedSlot || !confirmButton) return;
      slotInput.value = selectedSlot;
      stage = 'submitting';
      calendarView.hidden = true;
      timeView.hidden = true;
      submitting.hidden = false;
      confirmButton.disabled = true;
      if (typeof form.requestSubmit === 'function') form.requestSubmit(submit);
      else submit.click();
    }

    async function loadSlots() {
      const request = ++requestSequence;
      status.textContent = 'Loading available dates…';
      const visitorZone = timezoneSelect.value || displayTimezone || 'UTC';
      displayTimezone = visitorZone;
      timezoneInput.value = visitorZone;
      const body = new URLSearchParams({ action: 'mc_ghl_booking_slots', nonce: wrapper.dataset.mcGhlNonce || '', locationId: wrapper.dataset.mcGhlLocation || '', calendarId: wrapper.dataset.mcGhlCalendar || '', zone: 'visitor', requestedTimezone: visitorZone, signature: wrapper.dataset.mcGhlSignature || '' });
      body.set('meetingLocation', wrapper.dataset.mcGhlMeetingLocation || '');
      body.set('fallbackDuration', wrapper.dataset.mcGhlDuration || '30');
      try {
        const response = await fetch(mcGhlBooking.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
        const result = await response.json();
        if (request !== requestSequence) return;
        if (!response.ok || !result.success) {
          const message = result.data && result.data.message ? result.data.message : 'Could not load appointment times.';
          const debug = result.data && result.data.debug ? ' ' + result.data.debug : '';
          throw new Error(message + debug);
        }
        const availability = result.data && result.data.slots ? result.data.slots : {};
        displayTimezone = result.data && result.data.timezone ? result.data.timezone : visitorZone;
        meetingDetails = result.data && result.data.meeting ? result.data.meeting : meetingDetails;
        if (!meetingDetails.duration) meetingDetails.duration = Number(wrapper.dataset.mcGhlDuration || 30);
        slotsByDate = {};
        dates = Object.keys(availability).sort();
        Object.entries(availability).forEach(([date, value]) => { slotsByDate[date] = value.slots || []; });
        months = Array.from(new Set(dates.map((date) => date.slice(0, 7))));
        const hasSlots = Object.values(slotsByDate).some((slots) => slots.length > 0);
        if (!hasSlots) { status.textContent = 'There are no available times in the next 30 days. Please contact us.'; return; }
        status.textContent = 'Select a date with available times.';
        monthIndex = 0;
        renderCalendar();
      } catch (error) {
        if (request === requestSequence) status.textContent = error.message;
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
    timezoneSelect.addEventListener('change', () => {
      selectedSlot = ''; selectedDate = ''; slotInput.value = '';
      calendarView.hidden = false; timeView.hidden = true;
      loadSlots();
    });
    populateTimezones();
    timezoneInput.value = displayTimezone;

    if (window.jQuery) {
      jQuery(document).on('submit_success', function (event) {
        if (event.target !== form) return;
        stage = 'done';
        panel.hidden = true;
        addMeetingSummary();
      });
      jQuery(form).on('submit_error', function () {
        if (stage !== 'submitting') return;
        stage = 'times';
        submitting.hidden = true;
        timeView.hidden = false;
        if (confirmButton) confirmButton.disabled = false;
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
