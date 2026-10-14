/* Control panel switches: each change is saved on the spot. Text is only ever set with textContent; the only text shown comes from the server's language files. */
(function () {
	'use strict';
	/* trips list: buttons on a card act on that card; the floating bar acts on the ticked ones (Joomla's own list tasks, token and ACL checks included) */
	/* confirmation window of our own: the text comes from the server's language files and is only ever set with textContent */
	function confirmThen(text, ok, no, run) {
		if (typeof HTMLDialogElement !== 'function') { run(); return; }
		const dlg = document.createElement('dialog'); dlg.id = 'mvA-dlg';
		const p = document.createElement('p'); p.textContent = text;
		const row = document.createElement('div'); row.className = 'row';
		const b1 = document.createElement('button'); b1.type = 'button'; b1.className = 'mvA-btn g'; b1.textContent = no || '×';
		const b2 = document.createElement('button'); b2.type = 'button'; b2.className = 'mvA-btn p'; b2.textContent = ok || 'OK';
		row.append(b1, b2); dlg.append(p, row); document.body.append(dlg);
		const done = () => { dlg.close(); dlg.remove(); };
		b1.onclick = done; b2.onclick = () => { done(); run(); }; dlg.addEventListener('cancel', () => dlg.remove());
		dlg.showModal(); b1.focus();
	}
	document.addEventListener('click', (ev) => {
		const t = ev.target instanceof Element ? ev.target.closest('[data-mv-task],[data-mv-bulk],[data-mv-submit]') : null;
		if (!t || !window.Joomla) { return; }
		const go = () => {
			if (t.dataset.mvTask) { Joomla.listItemTask(t.dataset.mvCb, t.dataset.mvTask); }
			else if (t.dataset.mvBulk) { Joomla.submitbutton(t.dataset.mvBulk); }
			else if (t.dataset.mvSubmit) {
				const f = document.getElementById(t.dataset.mvForm); const task = t.dataset.mvSubmit;
				if (f && (/\.cancel$/.test(task) || !document.formvalidator || document.formvalidator.isValid(f))) { Joomla.submitform(task, f); }
			}
		};
		ev.preventDefault();
		if (t.dataset.mvConfirm) { confirmThen(t.dataset.mvConfirm, t.dataset.mvOk, t.dataset.mvNo, go); } else { go(); }
	});
	const bar = document.getElementById('mvA-bulk');
	if (bar) {
		const upd = () => { const n = document.querySelectorAll('input[name="cid[]"]:checked').length; bar.classList.toggle('on', n > 0); const c = document.getElementById('mvA-bulkN'); if (c) { c.textContent = String(n); } };
		document.addEventListener('change', (ev) => { if (ev.target instanceof Element && ev.target.matches('input[name="cid[]"]')) { upd(); } });
	}
	const o = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_mapaviajes.admin')) || null;
	const status = document.getElementById('mvA-status');
	if (!o) { return; }
	const say = (text, bad) => { if (status) { status.textContent = text; status.classList.toggle('mvA-bad', !!bad); } };
	async function save(el, value, undo) {
		const fd = new FormData(); fd.append(o.token, '1'); fd.append('name', el.dataset.name); fd.append('value', value);
		try {
			const r = await fetch(o.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
			const j = await r.json();
			if (!r.ok || !j.ok) { throw new Error('x'); }
			say(o.saved, false);
		} catch (_) { undo(); say(o.error, true); }
	}
	for (const el of document.querySelectorAll('.mvA-sw[data-name]')) {
		el.addEventListener('change', () => { const on = el.checked; save(el, on ? '1' : '0', () => { el.checked = !on; }); });
	}
	for (const el of document.querySelectorAll('.mvA-select[data-name]')) {
		let last = el.value;
		el.addEventListener('change', () => { const v = el.value, prev = last; last = v; save(el, v, () => { el.value = prev; last = prev; }); });
	}
}());
