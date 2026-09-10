(() => {
  'use strict';
  const archive = document.querySelector('[data-archive-form]');
  if (archive) {
    const boxes = [...archive.querySelectorAll('input[type=checkbox][name="ids[]"]')];
    const all = archive.querySelector('[data-select-all]');
    function update() {
      const count = boxes.filter(el => el.checked).length;
      const label = archive.querySelector('[data-selection-count]');
      if (label) label.textContent = `${count} ausgewählt`;
      if (all) { all.checked = count > 0 && count === boxes.length; all.indeterminate = count > 0 && count < boxes.length; archive.querySelector('.ee-delete').disabled = count === 0; }
    }
    all?.addEventListener('change', () => { boxes.forEach(el => { el.checked = all.checked; }); update(); });
    boxes.forEach(el => el.addEventListener('change', update));
    archive.addEventListener('submit', event => {
      const count = all ? boxes.filter(el => el.checked).length : 1;
      if (!count || !window.confirm(`${count} Erklärung(en) einschließlich PDF endgültig löschen? Dies kann nicht rückgängig gemacht werden.`)) event.preventDefault();
    });
    update();
  }
  document.querySelectorAll('[data-uncertain-mail]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('Der vorherige Versandstatus ist unbekannt. Ein erneuter Versuch kann eine zweite E-Mail auslösen. Fortfahren?')) event.preventDefault();
  }));
  document.querySelector('[data-logo-select]')?.addEventListener('click', () => {
    const picker = wp.media({title:'Logo auswählen', button:{text:'Logo verwenden'}, library:{type:['image/png','image/jpeg']}, multiple:false});
    picker.on('select', () => {
      const item = picker.state().get('selection').first().toJSON();
      document.querySelector('#ee-logo-id').value = item.id;
      const img = document.createElement('img'); img.src = item.sizes?.thumbnail?.url || item.url; img.alt = '';
      document.querySelector('#ee-logo-preview').replaceChildren(img);
    }); picker.open();
  });
  document.querySelector('[data-logo-remove]')?.addEventListener('click', () => { document.querySelector('#ee-logo-id').value = '0'; document.querySelector('#ee-logo-preview').replaceChildren(); });
})();
