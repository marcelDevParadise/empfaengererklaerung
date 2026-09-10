(() => {
  'use strict';
  const app = document.querySelector('.ee-app');
  if (!app) return;
  const config = JSON.parse(app.dataset.config);
  const form = app.querySelector('form');
  const status = app.querySelector('.ee-status');
  const panels = [...app.querySelectorAll('.ee-panel')];
  const canvas = app.querySelector('canvas');
  const ctx = canvas.getContext('2d');
  const back = app.querySelector('[data-back]');
  const next = app.querySelector('[data-next]');
  const submit = app.querySelector('[data-submit]');
  const strokes = [];
  let step = 0, session = '', drawing = false, busy = false, finished = false;
  let activeStroke = null, activePointer = null;
  const rgb = config.accent.slice(1).match(/../g).map(v => parseInt(v, 16) / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
  app.style.setProperty('--ee-button-ink', .2126 * rgb[0] + .7152 * rgb[1] + .0722 * rgb[2] > .179 ? '#12221b' : '#ffffff');
  const value = name => form.elements.namedItem(name)?.value || '';
  const checked = name => Boolean(form.elements.namedItem(name)?.checked);
  function message(text = '', error = false) { status.textContent = text; status.classList.toggle('ee-status-error', error); }
  function error(name, text) {
    const target = app.querySelector(`#ee-${name}-error`);
    if (target) target.textContent = text;
    const group = app.querySelector(`[data-field="${name}"]`);
    group?.querySelectorAll('input,textarea,canvas').forEach(el => {
      el.setAttribute('aria-invalid', text ? 'true' : 'false');
      if (!el.hasAttribute('aria-describedby')) el.setAttribute('aria-describedby', `ee-${name}-error`);
    });
  }
  function clearErrors() { app.querySelectorAll('.ee-error').forEach(el => { el.textContent = ''; }); app.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid')); }
  function conditionals() {
    const conditions = {received: value('receipt') === 'received', not_received: value('receipt') === 'not_received', cod: value('cod') === 'yes'};
    app.querySelectorAll('[data-condition]').forEach(el => {
      const show = conditions[el.dataset.condition]; el.hidden = !show;
      el.querySelectorAll('input').forEach(input => {
        input.disabled = !show;
        if (input.name === 'received_date' || input.name === 'cod_payment') input.required = show;
      });
    });
  }
  function validPanel(index) {
    let first;
    panels[index].querySelectorAll('input,textarea').forEach(input => {
      if (input.disabled) return;
      if (!input.checkValidity()) {
        error(input.name, input.name === 'tracking' && input.validity.patternMismatch ? 'Bitte die vollständige Sendungsnummer eingeben: nur Ziffern, beginnend mit 003404347382.' : input.validity.valueMissing ? 'Bitte dieses Feld ausfüllen oder eine Option auswählen.' : input.validationMessage);
        first ||= input;
      }
    });
    if (first) { first.closest('details')?.setAttribute('open', ''); first.focus(); return false; }
    return true;
  }
  function date(text) { return text ? text.split('-').reverse().join('.') : ''; }
  function statement() {
    const we = value('person') === 'wir';
    let text = we ? 'Wir erklären, dass wir die oben bezeichnete Sendung ' : 'Ich erkläre, dass ich die oben bezeichnete Sendung ';
    text += value('receipt') === 'received' ? `am ${date(value('received_date'))} erhalten ` : 'nicht erhalten ';
    text += we ? 'haben.' : 'habe.';
    if (value('receipt') === 'not_received' && checked('unknown')) text += we ? ' Über den Verbleib ist uns nichts bekannt.' : ' Über den Verbleib ist mir nichts bekannt.';
    if (value('cod') === 'yes') text += ' ' + ({courier:'Der Nachnahmebetrag wurde an den Zusteller gezahlt.',branch:'Der Nachnahmebetrag wurde am Ausgabeschalter der Postfiliale oder Postagentur gezahlt.',unpaid:'Der Nachnahmebetrag wurde nicht gezahlt.'}[value('cod_payment')] || '');
    return text;
  }
  function review() {
    const root = app.querySelector('.ee-review'); root.replaceChildren();
    const dl = document.createElement('dl');
    const entries = [
      ['Sendung', `${value('carrier')} · ${value('tracking')}`], ['Absender', value('sender')], ['Adressiert an', value('recipient')], ['Sendungsinhalt', value('contents')],
      ['Dokumentierte Zustellung an', value('delivery_to')], ['Dokumentiertes Zustelldatum', date(value('delivery_date'))],
      ['Erklärende Person', `${value('first_name')} ${value('last_name')}\n${value('address')}`], ['E-Mail', value('email')], ['Telefon', value('phone')]
    ];
    entries.forEach(([label, text]) => { if (!text) return; const dt = document.createElement('dt'); const dd = document.createElement('dd'); dt.textContent = label; dd.textContent = text; dl.append(dt, dd); });
    const p = document.createElement('p'); p.textContent = statement(); root.append(dl, p);
  }
  function show(index, focus = true) {
    step = index; panels.forEach((panel, i) => { panel.hidden = i !== step; });
    app.querySelectorAll('.ee-progress li').forEach((el, i) => { if (i === step) el.setAttribute('aria-current','step'); else el.removeAttribute('aria-current'); });
    back.hidden = step === 0; next.hidden = step === 2; submit.hidden = step !== 2;
    if (step === 2) review();
    if (focus) panels[step].querySelector('h3').focus();
  }
  function renderSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#172a23'; ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    strokes.forEach(points => {
      ctx.beginPath(); points.forEach(([x, y], i) => { if (i) ctx.lineTo(x * canvas.width, y * canvas.height); else ctx.moveTo(x * canvas.width, y * canvas.height); }); ctx.stroke();
    });
  }
  function clearSignature(changed = false) {
    const hadInk = strokes.length > 0;
    strokes.length = 0; activeStroke = null; activePointer = null; drawing = false; renderSignature();
    app.querySelector('.ee-signature-note').textContent = changed && hadInk ? 'Du hast Angaben geändert. Bitte unterschreibe erneut.' : '';
  }
  function point(event) {
    const rect = canvas.getBoundingClientRect();
    const scale = Math.min(rect.width / canvas.width, rect.height / canvas.height);
    const width = canvas.width * scale, height = canvas.height * scale;
    return [Math.max(0, Math.min(1, (event.clientX - rect.left - (rect.width-width)/2) / width)), Math.max(0, Math.min(1, (event.clientY - rect.top - (rect.height-height)/2) / height))];
  }
  canvas.addEventListener('pointerdown', event => {
    if (busy || (event.pointerType === 'mouse' && event.button !== 0) || drawing) return;
    if (!strokes.length) {
      const rect = canvas.getBoundingClientRect();
      canvas.width = Math.max(200, Math.min(1600, Math.round(rect.width * 2)));
      canvas.height = Math.max(120, Math.min(700, Math.round(rect.height * 2)));
    }
    event.preventDefault(); canvas.setPointerCapture(event.pointerId); activePointer = event.pointerId; drawing = true; activeStroke = [point(event)]; strokes.push(activeStroke); error('signature', ''); app.querySelector('.ee-signature-note').textContent = '';
  });
  canvas.addEventListener('pointermove', event => { if (drawing && activeStroke && event.pointerId === activePointer) { activeStroke.push(point(event)); renderSignature(); } });
  for (const name of ['pointerup', 'pointercancel', 'lostpointercapture']) canvas.addEventListener(name, event => { if (event.pointerId === activePointer) { drawing = false; activeStroke = null; activePointer = null; } });
  app.querySelector('[data-clear]').addEventListener('click', () => clearSignature());
  form.addEventListener('input', event => {
    if (busy) return;
    error(event.target.name, ''); clearSignature(true); conditionals();
  });
  next.addEventListener('click', () => { clearErrors(); if (validPanel(step)) { message(); show(step + 1); } });
  back.addEventListener('click', () => { message(); show(Math.max(0, step - 1)); });
  async function api(path, body) {
    const response = await fetch(config.api + path, {method:'POST', credentials:'omit', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body), cache:'no-store', referrerPolicy:'same-origin'});
    let data;
    try { data = await response.json(); } catch { throw new Error('Der Server hat nicht wie erwartet geantwortet. Bitte versuche es erneut.'); }
    if (!response.ok) { const error = new Error(data.message || 'Die Übermittlung ist fehlgeschlagen.'); error.details = data; throw error; }
    return data;
  }
  async function newSession() {
    const data = await api('session', {}); session = data.session;
    if (!value('date')) form.elements.namedItem('date').value = data.today;
    form.querySelectorAll('input[type=date]').forEach(el => { el.max = data.today; });
  }
  function setBusy(on) {
    busy = on; form.setAttribute('aria-busy', String(on));
    form.querySelectorAll('button,input,textarea').forEach(el => { el.disabled = on; });
    if (!on) conditionals();
    submit.textContent = on ? 'Erklärung wird gespeichert …' : 'Unterschreiben & absenden';
  }
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || finished) return;
    if (step < 2) { next.click(); return; }
    clearErrors();
    for (let i = 0; i < 3; i++) {
      const prior = step; show(i, false);
      if (!validPanel(i)) { show(i); validPanel(i); return; }
      show(prior, false);
    }
    if (strokes.reduce((sum, stroke) => sum + stroke.length, 0) < 8) { error('signature', 'Bitte im Unterschriftsfeld unterschreiben.'); canvas.scrollIntoView({block:'center'}); return; }
    const body = Object.fromEntries(new FormData(form));
    body.confirmed = checked('confirmed'); body.unknown = checked('unknown') && value('receipt') === 'not_received';
    body.signature = canvas.toDataURL('image/png'); body.session = session;
    setBusy(true); message('Deine Erklärung wird erstellt. Bitte lasse diese Seite geöffnet.');
    try {
      const result = await api('declarations', body); finished = true;
      form.hidden = true; app.querySelector('.ee-progress').hidden = true; message();
      const success = app.querySelector('.ee-success'); success.hidden = false;
      app.querySelector('.ee-reference').textContent = `Vorgangsnummer: ${result.reference}`;
      app.querySelector('.ee-mail-message').textContent = result.customer_mail === 'handed_off'
        ? 'Deine PDF-Kopie wurde an den E-Mail-Versand übergeben. Bitte prüfe auch deinen Spam-Ordner.'
        : 'Deine Erklärung ist gespeichert. Die E-Mail-Kopie konnte noch nicht an den Versand übergeben werden. Bitte lade das PDF hier herunter.';
      const link = app.querySelector('.ee-download'); link.hidden = !result.download;
      if (result.download) link.href = result.download;
      app.querySelector('.ee-expiry').textContent = result.download ? `Der Download ist bis ${new Date(result.expires * 1000).toLocaleTimeString('de-DE', {hour:'2-digit', minute:'2-digit'})} Uhr verfügbar.` : 'Der Downloadzugang ist abgelaufen. Nutze bitte deine E-Mail-Kopie oder kontaktiere den Service unter Angabe der Vorgangsnummer.';
      strokes.length = 0; renderSignature(); form.reset(); success.focus();
    } catch (failure) {
      message(failure.message || 'Die Verbindung wurde unterbrochen. Deine Angaben bleiben erhalten. Bitte erneut absenden.', true);
      if (failure.details?.code === 'expired_session') {
        try { await newSession(); } catch { message('Die Verbindung ist unterbrochen. Bitte versuche es später erneut.', true); }
      }
      const fields = failure.details?.data?.fields;
      if (fields) {
        Object.entries(fields).forEach(([key, text]) => error(key, text));
        const first = app.querySelector('[aria-invalid=true]');
        if (first) { show(Number(first.closest('.ee-panel').dataset.step)); first.closest('details')?.setAttribute('open',''); first.focus(); }
      }
    } finally { setBusy(false); }
  });
  window.addEventListener('beforeunload', event => { if (!finished && (busy || strokes.length || value('tracking'))) { event.preventDefault(); event.returnValue = ''; } });
  conditionals();
  newSession().then(() => { form.hidden = false; message(); show(0, false); }).catch(() => {
    message('Das Formular konnte nicht geladen werden. Bitte prüfe deine Verbindung und lade die Seite erneut.', true);
  });
})();
