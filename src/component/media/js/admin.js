/* Control panel switches: each change is saved on the spot. Text is only ever set with textContent; the only text shown comes from the server's language files. */
(function () {
	'use strict';
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
