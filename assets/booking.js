(function () {
  'use strict';
  function init(wrapper) {
    if (!wrapper || wrapper.dataset.mcGhlInit) return;
    const form = wrapper.querySelector('form.elementor-form');
    if (!form) return;
    wrapper.dataset.mcGhlInit = '1';
    const fields = form.querySelector('.elementor-form-fields-wrapper');
    const submit = form.querySelector('[type="submit"]');
    if (!fields || !submit) return;
    const panel = document.createElement('section');
    panel.className = 'mc-ghl-booking-step';
    panel.hidden = true;
    panel.innerHTML = '<h3>Choose an appointment time</h3><p class="mc-ghl-status" aria-live="polite">Loading available times…</p><div class="mc-ghl-slot-list"></div><button type="button" class="mc-ghl-back">Back to your details</button>';
    fields.appendChild(panel);
    const slotId = wrapper.dataset.mcGhlSlotField || 'ghl_slot';
    let slotInput = Array.from(form.elements).find((element) => element.name === 'form_fields[' + slotId + ']');
    if (!slotInput) {
      slotInput = document.createElement('input');
      slotInput.type = 'hidden';
      slotInput.name = 'form_fields[' + slotId + ']';
      form.appendChild(slotInput);
    }
    const status = panel.querySelector('.mc-ghl-status');
    const list = panel.querySelector('.mc-ghl-slot-list');
    let started = false;
    let showingSlots = false;
    const originalChildren = Array.from(fields.children).filter((el) => el !== panel);
    function setView(slots, clearSlot) {
      showingSlots = slots;
      originalChildren.forEach((el) => { el.hidden = slots; });
      panel.hidden = !slots;
      if (clearSlot) slotInput.value = '';
      (slots ? panel : fields).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    async function loadSlots() {
      if (started) return;
      started = true;
      const zoneSetting = wrapper.dataset.mcGhlTimezone || 'site';
      const visitorZone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
      const timezone = zoneSetting === 'visitor' ? visitorZone : zoneSetting;
      const body = new URLSearchParams({ action: 'mc_ghl_booking_slots', nonce: wrapper.dataset.mcGhlNonce || '', locationId: wrapper.dataset.mcGhlLocation || '', calendarId: wrapper.dataset.mcGhlCalendar || '', timezone, visitorTimezone: visitorZone, signature: wrapper.dataset.mcGhlSignature || '' });
      try {
        const response = await fetch(mcGhlBooking.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
        const result = await response.json();
        if (!response.ok || !result.success) {
          const message = result.data && result.data.message ? result.data.message : 'Could not load appointment times.';
          const debug = result.data && result.data.debug ? ' ' + result.data.debug : '';
          throw new Error(message + debug);
        }
        const availability = result.data || {};
        const entries = Object.entries(availability).flatMap(([date, value]) => (value.slots || []).map((slot) => ({ date, slot })));
        list.replaceChildren();
        if (!entries.length) { status.textContent = 'There are no available times in the next 30 days. Please contact us.'; return; }
        status.textContent = 'Times shown in ' + timezone.replaceAll('_', ' ') + '.';
        entries.forEach(({ date, slot }) => {
          const button = document.createElement('button');
          button.type = 'button'; button.className = 'mc-ghl-slot'; button.textContent = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: timezone }).format(new Date(slot));
          button.addEventListener('click', () => {
            list.querySelectorAll('.mc-ghl-slot').forEach((item) => item.setAttribute('aria-pressed', 'false'));
            button.setAttribute('aria-pressed', 'true'); slotInput.value = slot;
            status.textContent = 'Selected: ' + button.textContent + '. Press the form submit button to confirm.';
            setView(false);
          });
          list.appendChild(button);
        });
      } catch (error) { status.textContent = error.message; }
    }
    form.addEventListener('submit', (event) => {
      if (!showingSlots && !slotInput.value) {
        if (!form.reportValidity()) return;
        event.preventDefault(); event.stopImmediatePropagation(); setView(true); loadSlots();
      } else if (showingSlots) {
        event.preventDefault(); event.stopImmediatePropagation();
        status.textContent = 'Select an available time before continuing.';
      }
    }, true);
    panel.querySelector('.mc-ghl-back').addEventListener('click', () => setView(false, true));
  }
  function scan() { document.querySelectorAll('.elementor-widget-form[data-mc-ghl-calendar]').forEach(init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
  if (window.jQuery) jQuery(window).on('elementor/frontend/init', scan);
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
})();
