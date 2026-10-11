/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 *
 * Security rule of this file: text that came from another user (titles, places, names, captions) only ever
 * enters the page through textContent / setAttribute / DOM nodes. No HTML string is ever built from data.
 */
(function () {
	'use strict';

	const O = window.Joomla && Joomla.getOptions ? Joomla.getOptions('com_mapaviajes') : null;
	if (!O || !document.getElementById('mv-stage')) { return; }

	const $ = (id) => document.getElementById('mv-' + id);
	const T = (key, fallback) => { const v = Joomla.Text._(key); return v && v !== key ? v : (fallback || key); };
	const fmt = (s, ...a) => { let i = 0; return s.replace(/%[ds]/g, () => String(a[i++])); };
	const SVGNS = 'http://www.w3.org/2000/svg';
	const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/** Tiny DOM builder: strings become text nodes, never markup. */
	function h(tag, attrs, ...kids) {
		const e = document.createElement(tag);
		for (const [k, v] of Object.entries(attrs || {})) {
			if (v === false || v === null || v === undefined) { continue; }
			if (k === 'class') { e.className = v; } else if (k === 'text') { e.textContent = v; } else if (k.startsWith('on')) { e.addEventListener(k.slice(2), v); } else { e.setAttribute(k, v === true ? '' : String(v)); }
		}
		for (const kid of kids.flat()) { if (kid !== null && kid !== undefined && kid !== false) { e.append(kid instanceof Node ? kid : document.createTextNode(String(kid))); } }
		return e;
	}
	const photoSrc = (id) => O.photoUrl + encodeURIComponent(String(id));

	document.body.classList.add('mv-body');
	document.documentElement.classList.add('mv-lock');

	/* ---------------------------------------------------------------- projection (Natural Earth) */
	const D2R = Math.PI / 180;
	let WORLD = null, W = 1000, H = 520;
	function rawNE(l, p) { const p2 = p * p, p4 = p2 * p2; return [l * (0.8707 - 0.131979 * p2 + p4 * (-0.013791 + p4 * (0.003971 * p2 - 0.001529 * p4))), p * (1.007226 + p2 * (0.015085 + p4 * (-0.044475 + 0.028874 * p2 - 0.005916 * p4)))]; }
	function project(lon, lat) { const [a, b] = rawNE(lon * D2R, lat * D2R); return [WORLD.t[0] + WORLD.scale * a, WORLD.t[1] - WORLD.scale * b]; }
	function invert(x, y) {
		const ry = (WORLD.t[1] - y) / WORLD.scale, rx = (x - WORLD.t[0]) / WORLD.scale;
		let p = ry;
		for (let i = 0; i < 12; i++) { const e = 1e-6, f = rawNE(0, p)[1] - ry, d = (rawNE(0, p + e)[1] - rawNE(0, p)[1]) / e; p -= f / d; }
		p = Math.max(-Math.PI / 2, Math.min(Math.PI / 2, p));
		const l = rx / rawNE(1, p)[0];
		return [Math.max(-180, Math.min(180, l / D2R)), p / D2R];
	}
	const dist = (a, b, c, d) => { const dl = (c - a) * D2R, dp = (d - b) * D2R, q = Math.sin(dp / 2) ** 2 + Math.cos(b * D2R) * Math.cos(d * D2R) * Math.sin(dl / 2) ** 2; return 12742 * Math.asin(Math.sqrt(q)); };

	/* ---------------------------------------------------------------- state */
	let TRIPS = [];
	const stage = $('stage'), svg = $('svg'), worldG = $('world'), pinsEl = $('pins');
	const V = { k: 1, tx: 0, ty: 0 };
	let VW = innerWidth, VH = innerHeight, KMIN = 1, KMAX = 40;
	const FIXED_PX = 46, COUNTRY_ZOOM = 2.4, ZP = 6;
	const pinEls = new Map(), polEls = new Map();
	let anim = 0;

	const panelOpen = () => !!$('panel') && $('panel').classList.contains('mv-open');
	const drawerOpen = () => $('drawer').classList.contains('mv-open');
	function visibleBox() {
		if (panelOpen() && VW > 720) { return { x: 0, y: 0, w: VW - 472, h: VH }; }
		if (panelOpen()) { return { x: 0, y: 0, w: VW, h: VH * 0.18 }; }
		if (drawerOpen() && VW > 720) { return { x: 424, y: 0, w: VW - 440, h: VH }; }
		if (drawerOpen()) { return { x: 0, y: 0, w: VW, h: VH * 0.26 }; }
		return { x: 0, y: 0, w: VW, h: VH };
	}
	function limits() { VW = innerWidth; VH = innerHeight; KMIN = VW <= 720 ? VW / W * 1.45 : VW / W; KMAX = KMIN * 40; }
	function clamp(v) {
		v.k = Math.max(KMIN, Math.min(KMAX, v.k));
		const mw = W * v.k, mh = H * v.k, m = 60;
		v.tx = mw <= VW ? (VW - mw) / 2 : Math.min(m, Math.max(VW - mw - m, v.tx));
		v.ty = mh <= VH ? (VH - mh) / 2 : Math.min(m, Math.max(VH - mh - m, v.ty));
		return v;
	}
	function apply() {
		svg.classList.toggle('mv-far', V.k / KMIN < 3.2);
		worldG.setAttribute('transform', 'translate(' + V.tx + ' ' + V.ty + ') scale(' + V.k + ')');
		render();
	}
	function zoomAt(f, sx, sy) { const nk = Math.max(KMIN, Math.min(KMAX, V.k * f)); f = nk / V.k; V.tx = sx - (sx - V.tx) * f; V.ty = sy - (sy - V.ty) * f; V.k = nk; clamp(V); apply(); }
	function viewFor(cx, cy, k) { const b = visibleBox(); return { k, tx: b.x + b.w / 2 - cx * k, ty: b.y + b.h / 2 - cy * k }; }
	function flyTo(t, ms = 750) {
		clamp(t); cancelAnimationFrame(anim);
		if (reduced()) { Object.assign(V, t); apply(); return; }
		const s = { ...V }, t0 = performance.now();
		const step = (now) => {
			const u = Math.min(1, (now - t0) / ms), e = u === 1 ? 1 : 1 - Math.pow(2, -10 * u);
			V.k = Math.exp(Math.log(s.k) + (Math.log(t.k) - Math.log(s.k)) * e); V.tx = s.tx + (t.tx - s.tx) * e; V.ty = s.ty + (t.ty - s.ty) * e; apply();
			if (u < 1) { anim = requestAnimationFrame(step); }
		};
		anim = requestAnimationFrame(step);
	}
	function home() { flyTo(viewFor(W / 2, H / 2 + (VW <= 720 ? -18 : 0), KMIN)); }

	/* ---------------------------------------------------------------- world drawing */
	const PATHS = [];
	function drawWorld() {
		$('sphere').setAttribute('d', WORLD.sphere); $('grat').setAttribute('d', WORLD.grat);
		for (const c of WORLD.countries) {
			const p = document.createElementNS(SVGNS, 'path'); p.setAttribute('d', c.d); p.dataset.c = c.n; $('land').appendChild(p); PATHS.push(p);
			for (const id of ['r1', 'r2', 'r3']) { const q = document.createElementNS(SVGNS, 'path'); q.setAttribute('d', c.d); $(id).appendChild(q); }
		}
		const labels = [['COM_MAPAVIAJES_JS_OCEAN_PACIFIC', -138, 12, 12, .28], ['COM_MAPAVIAJES_JS_OCEAN_ATLANTIC', -46, 32, 7, .12], ['COM_MAPAVIAJES_JS_OCEAN_INDIAN', 78, -24, 12, .32], ['COM_MAPAVIAJES_JS_OCEAN_ARCTIC', 70, 82, 7, .32], ['COM_MAPAVIAJES_JS_OCEAN_SOUTHERN', 20, -62, 9, .32]];
		for (const [key, lo, la, size, ls] of labels) {
			const [x, y] = project(lo, la), e = document.createElementNS(SVGNS, 'text');
			e.textContent = T(key); e.setAttribute('font-size', String(size)); e.setAttribute('transform', 'translate(' + x.toFixed(1) + ' ' + y.toFixed(1) + ')'); e.style.letterSpacing = ls + 'em';
			$('olabs').appendChild(e);
		}
	}
	const probe = svg.createSVGPoint();
	function countryOf(t) {
		if (t._c !== undefined) { return t._c; }
		probe.x = t.x; probe.y = t.y;
		let c = null;
		for (const p of PATHS) {
			if (!p._bb) { p._bb = p.getBBox(); }
			const b = p._bb;
			if (t.x < b.x || t.x > b.x + b.width || t.y < b.y || t.y > b.y + b.height) { continue; }
			if (p.isPointInFill(probe)) { c = p.dataset.c; break; }
		}
		return (t._c = c !== null ? 'c:' + c : 'q:' + Math.round(t.x / 25) + ',' + Math.round(t.y / 25));
	}

	/* ---------------------------------------------------------------- clusters + pins */
	function clusters() {
		const z = V.k / KMIN, out = [];
		if (z < COUNTRY_ZOOM) {
			const m = new Map();
			for (const t of TRIPS) { const k = countryOf(t); if (!m.has(k)) { m.set(k, []); } m.get(k).push(t); }
			for (const [k, arr] of m) { out.push({ key: k, items: arr }); }
		} else {
			const RAD = Math.max(14, FIXED_PX * Math.min(1, 9 / z));
			for (const t of TRIPS) {
				let hit = null;
				for (const c of out) { const dx = (c.cx - t.x) * V.k, dy = (c.cy - t.y) * V.k; if (dx * dx + dy * dy < RAD * RAD) { hit = c; break; } }
				if (hit) { hit.items.push(t); hit.cx = hit.items.reduce((s, i) => s + i.x, 0) / hit.items.length; hit.cy = hit.items.reduce((s, i) => s + i.y, 0) / hit.items.length; } else { out.push({ items: [t], cx: t.x, cy: t.y }); }
			}
			for (const c of out) { c.key = 'g:' + c.items.map((i) => i.id).join(','); }
		}
		for (const c of out) {
			c.cx = c.items.reduce((s, i) => s + i.x, 0) / c.items.length; c.cy = c.items.reduce((s, i) => s + i.y, 0) / c.items.length;
			if (c.key[0] === 'c' && c.items.length > 2) { // keep the country pin on land: nearest trip to the mean, blended
				let best = null, bd = 1e9; for (const i of c.items) { const d = (i.x - c.cx) ** 2 + (i.y - c.cy) ** 2; if (d < bd) { bd = d; best = i; } }
				c.cx = (best.x + c.cx) / 2; c.cy = (best.y + c.cy) / 2;
			}
		}
		return out;
	}
	function pinSVG(n, draft) {
		const s = document.createElementNS(SVGNS, 'svg'); s.setAttribute('viewBox', '0 0 48 66'); s.setAttribute('aria-hidden', 'true');
		const add = (tag, attrs, text) => { const e = document.createElementNS(SVGNS, tag); for (const [k, v] of Object.entries(attrs)) { e.setAttribute(k, String(v)); } if (text !== undefined) { e.textContent = text; } s.appendChild(e); return e; };
		add('ellipse', { cx: 25, cy: 62.5, rx: 13, ry: 3.6, fill: 'url(#mv-gShadow)' });
		add('path', { d: 'M22.4 36h3.2l-1.6 27z', fill: 'url(#mv-gNeedle)' });
		add('circle', { cx: 24, cy: 22, r: 20, fill: 'url(#' + (draft ? 'mv-gDraft' : 'mv-gHead') + ')' });
		add('circle', { cx: 24, cy: 22, r: 19.2, fill: 'none', stroke: 'rgba(255,255,255,.25)', 'stroke-width': 1 });
		add('ellipse', { cx: 16.5, cy: 11.5, rx: 7.5, ry: 4, transform: 'rotate(-32 16.5 11.5)', fill: '#fff', opacity: .6 });
		if (n === 1) { add('circle', { cx: 24, cy: 23, r: 5.5, fill: draft ? '#0d1a52' : '#fff', opacity: .95 }); } else {
			const len = String(n).length, fs = len >= 3 ? 15 : (len === 2 ? 19 : 22);
			const t = add('text', { class: 'mv-num', x: 24, y: 22 + fs * .36 }, String(n)); t.style.fontSize = fs + 'px';
		}
		return s;
	}
	const pinWidth = (n) => Math.round((n === 1 ? 30 : 40 + Math.min(16, Math.log2(n) * 3.6)) * (innerWidth <= 720 ? .74 : 1));
	function spread(cs) { // country pins that would cover each other are nudged apart (screen space only)
		const head = (c) => ({ x: c.sx + c.ox, y: c.sy + c.oy - c.h * .62, r: c.w * .5 });
		for (let it = 0; it < 40; it++) {
			let moved = false;
			for (let i = 0; i < cs.length; i++) { for (let j = i + 1; j < cs.length; j++) {
				const a = cs[i], b = cs[j], ha = head(a), hb = head(b);
				let dx = hb.x - ha.x, dy = hb.y - ha.y, d = Math.hypot(dx, dy); const min = ha.r + hb.r + 3;
				if (d >= min) { continue; } if (d < .01) { dx = 1; dy = 0; d = 1; }
				const push = (min - d) / 2, ux = dx / d, uy = dy / d; a.ox -= ux * push; a.oy -= uy * push; b.ox += ux * push; b.oy += uy * push; moved = true;
			} }
			for (const c of cs) { const m = Math.hypot(c.ox, c.oy); if (m > 80) { c.ox *= 80 / m; c.oy *= 80 / m; } }
			if (!moved) { break; }
		}
	}
	function render() {
		const cs = clusters(), keep = new Set(), z = V.k / KMIN;
		cs.sort((a, b) => a.cy - b.cy);
		for (const c of cs) { c.w = pinWidth(c.items.length); c.h = c.w * 66 / 48; c.sx = V.tx + c.cx * V.k; c.sy = V.ty + c.cy * V.k; c.ox = 0; c.oy = 0; }
		if (z < COUNTRY_ZOOM) { spread(cs); }
		cs.forEach((c, i) => {
			keep.add(c.key);
			const n = c.items.length; let el = pinEls.get(c.key);
			if (!el) {
				const draft = n === 1 && c.items[0].pending;
				el = h('button', { type: 'button', class: 'mv-pin' }, h('span', { class: 'mv-pin-body', style: 'animation-delay:' + Math.min(i, 24) * 14 + 'ms' }, pinSVG(n, draft)));
				el.addEventListener('click', (ev) => { ev.stopPropagation(); onPin(el._c); });
				el.addEventListener('pointerdown', (ev) => ev.stopPropagation());
				pinsEl.appendChild(el); pinEls.set(c.key, el);
			}
			el._c = c; el.style.width = c.w + 'px';
			el.setAttribute('aria-label', n === 1 ? c.items[0].t + ' ' + fmt(T('COM_MAPAVIAJES_JS_BY'), c.items[0].by) : fmt(T('COM_MAPAVIAJES_JS_ALBUMS_N'), n));
			el.style.transform = 'translate(' + (c.sx + c.ox - c.w / 2).toFixed(1) + 'px,' + (c.sy + c.oy - c.h).toFixed(1) + 'px)';
			el.style.zIndex = String(1000 + Math.round(c.sy) + (n > 1 ? n : 0));
			el.hidden = c.sx < -80 || c.sx > VW + 80 || c.sy < -20 || c.sy > VH + 120;
		});
		for (const [k, el] of pinEls) { if (!keep.has(k)) { el.remove(); pinEls.delete(k); } }
		syncPolaroids(cs); placeDraft();
		const countries = new Set(TRIPS.map(countryOf)).size;
		$('stat').textContent = TRIPS.length ? fmt(T('COM_MAPAVIAJES_JS_COUNT_ALBUMS'), TRIPS.length) + ' · ' + fmt(T('COM_MAPAVIAJES_JS_COUNT_PLACES'), countries) : T('COM_MAPAVIAJES_JS_NO_TRIPS');
	}
	function onPin(c) {
		closePop();
		if (c.items.length === 1) { return V.k / KMIN >= ZP ? openViewer(c.items[0]) : openPop(c); }
		if (V.k >= KMAX * .85) { return openPop(c); }
		const xs = c.items.map((i) => i.x), ys = c.items.map((i) => i.y), b = visibleBox();
		const bx = Math.max(...xs) - Math.min(...xs), by = Math.max(...ys) - Math.min(...ys);
		const fit = Math.min(b.w * .55 / (bx || 1), b.h * .5 / (by || 1)), k = Math.min(KMAX, Math.max(V.k * 1.9, Math.min(fit, V.k * 8)));
		flyTo(viewFor((Math.max(...xs) + Math.min(...xs)) / 2, (Math.max(...ys) + Math.min(...ys)) / 2, k));
	}

	/* polaroid: when you are close to one album, its photo hangs from the pin by its highest corner */
	function syncPolaroids(cs) {
		const keep = new Set(), on = V.k / KMIN >= ZP;
		if (on) {
			for (const c of cs) {
				if (c.items.length !== 1) { continue; }
				const t = c.items[0];
				if (!t.ph.length || c.sx < -220 || c.sx > VW + 220 || c.sy < -120 || c.sy > VH + 280) { continue; }
				keep.add(c.key);
				let el = polEls.get(c.key);
				const w = innerWidth <= 720 ? 120 : 158, ang = (t.id % 2 ? -1 : 1) * (2.5 + ((t.id * 37) % 10) * .55);
				if (!el) {
					el = h('button', { type: 'button', class: 'mv-polaroid', 'aria-label': t.t + ' ' + fmt(T('COM_MAPAVIAJES_JS_BY'), t.by) },
						h('span', { class: 'mv-pol-in' }, h('span', { class: 'mv-ph' }, h('img', { alt: '', draggable: 'false', src: photoSrc(t.ph[0].id) })), h('span', { class: 'mv-cap', text: t.t })));
					el.addEventListener('click', (ev) => { ev.stopPropagation(); openViewer(t); });
					el.addEventListener('pointerdown', (ev) => ev.stopPropagation());
					polEls.set(c.key, el); pinsEl.appendChild(el);
				}
				const ax = ang > 0 ? w * .1 : w * .9, ay = 11;
				el.style.width = w + 'px'; el.style.setProperty('--ox', ax + 'px');
				el.style.transform = 'translate(' + c.sx.toFixed(1) + 'px,' + c.sy.toFixed(1) + 'px) rotate(' + ang.toFixed(2) + 'deg) translate(' + (-ax) + 'px,' + (-ay) + 'px)';
				el.style.zIndex = String(999 + Math.round(c.sy));
			}
		}
		for (const [k, el] of polEls) { if (!keep.has(k)) { el.remove(); polEls.delete(k); } }
	}

	/* ---------------------------------------------------------------- popup */
	function closePop() { $('popHost').replaceChildren(); }
	function ago(ts) {
		const m = Math.round((Date.now() / 1000 - ts) / 60);
		if (m < 2) { return T('COM_MAPAVIAJES_JS_AGO_NOW'); } if (m < 60) { return fmt(T('COM_MAPAVIAJES_JS_AGO_MIN'), m); }
		const hh = Math.round(m / 60); if (hh < 24) { return fmt(T('COM_MAPAVIAJES_JS_AGO_H'), hh); }
		const d = Math.round(hh / 24); return d === 1 ? T('COM_MAPAVIAJES_JS_AGO_YESTERDAY') : fmt(T('COM_MAPAVIAJES_JS_AGO_D'), d);
	}
	const photosLabel = (n) => n === 1 ? T('COM_MAPAVIAJES_JS_PHOTO_ONE') : fmt(T('COM_MAPAVIAJES_JS_PHOTOS_N'), n);
	function openPop(c) {
		closePop();
		const sx = V.tx + c.cx * V.k, sy = V.ty + c.cy * V.k, one = c.items.length === 1, t = c.items[0];
		const el = h('div', { class: 'mv-pop', role: 'dialog', 'aria-label': one ? t.t : fmt(T('COM_MAPAVIAJES_JS_ALBUMS_N'), c.items.length) });
		if (one) {
			el.append(h('div', { class: 'mv-ph', style: 'background:#2d1b10' }, t.ph.length ? h('img', { alt: '', src: photoSrc(t.ph[0].id), style: 'width:100%;height:100%;object-fit:cover;display:block' }) : null),
				h('div', { class: 'mv-in' }, h('h3', { text: t.t }),
					h('p', { class: 'mv-meta', text: [t.p, fmt(T('COM_MAPAVIAJES_JS_BY'), t.by), photosLabel(t.ph.length)].join(' · ') }),
					t.pending ? h('p', { class: 'mv-meta', text: T('COM_MAPAVIAJES_JS_PENDING') }) : null,
					h('button', { type: 'button', class: 'mv-btn', text: T('COM_MAPAVIAJES_JS_OPEN'), onclick: () => { closePop(); openViewer(t); } })));
		} else {
			el.append(h('div', { class: 'mv-in', style: 'padding-top:18px' }, h('h3', { text: fmt(T('COM_MAPAVIAJES_JS_ALBUMS_N'), c.items.length) }),
				h('ul', {}, c.items.slice(0, 6).map((i) => h('li', {}, h('button', { type: 'button', onclick: () => { closePop(); openViewer(i); } }, h('strong', { text: i.t }), h('small', { text: fmt(T('COM_MAPAVIAJES_JS_BY'), i.by) + ' · ' + photosLabel(i.ph.length) })))))));
		}
		el.append(h('button', { class: 'mv-x', type: 'button', 'aria-label': T('COM_MAPAVIAJES_JS_CLOSE'), onclick: closePop }, (() => { const s = document.createElementNS(SVGNS, 'svg'); s.setAttribute('width', '16'); s.setAttribute('height', '16'); s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2.4'); const p = document.createElementNS(SVGNS, 'path'); p.setAttribute('d', 'M6 6l12 12M18 6L6 18'); s.appendChild(p); return s; })()));
		$('popHost').append(el);
		const w = 290, hh = el.offsetHeight;
		el.style.left = Math.max(12, Math.min(VW - w - 12, sx - w / 2)) + 'px'; el.style.top = Math.max(80, Math.min(VH - hh - 12, sy - 60 - hh - 8)) + 'px';
		el.querySelector('button').focus({ preventScroll: true });
	}

	/* ---------------------------------------------------------------- pointer / keyboard on the map */
	const ptrs = new Map(); let moved = 0, downTarget = null, pinch = 0;
	stage.addEventListener('pointerdown', (e) => {
		if (e.target.closest('.mv-pin,.mv-polaroid')) { return; }
		stage.setPointerCapture(e.pointerId); ptrs.set(e.pointerId, { x: e.clientX, y: e.clientY });
		if (ptrs.size === 1) { moved = 0; downTarget = e.target; }
		if (ptrs.size === 2) { const [a, b] = [...ptrs.values()]; pinch = Math.hypot(a.x - b.x, a.y - b.y); }
		cancelAnimationFrame(anim);
	});
	stage.addEventListener('pointermove', (e) => {
		const p = ptrs.get(e.pointerId); if (!p) { return; }
		const dx = e.clientX - p.x, dy = e.clientY - p.y;
		if (ptrs.size === 1) { moved += Math.abs(dx) + Math.abs(dy); if (moved > 8) { stage.classList.add('mv-drag'); V.tx += dx; V.ty += dy; clamp(V); apply(); } } else if (ptrs.size === 2) {
			p.x = e.clientX; p.y = e.clientY; const [a, b] = [...ptrs.values()], d = Math.hypot(a.x - b.x, a.y - b.y);
			if (pinch) { moved = 99; zoomAt(d / pinch, (a.x + b.x) / 2, (a.y + b.y) / 2); } pinch = d; return;
		}
		p.x = e.clientX; p.y = e.clientY;
	});
	const endPtr = (e) => {
		if (!ptrs.has(e.pointerId)) { return; }
		const single = ptrs.size === 1; ptrs.delete(e.pointerId); stage.classList.remove('mv-drag');
		if (single && moved <= 8 && e.type === 'pointerup') { mapClick(e.clientX, e.clientY, downTarget); }
		if (ptrs.size < 2) { pinch = 0; }
	};
	stage.addEventListener('pointerup', endPtr); stage.addEventListener('pointercancel', endPtr);
	stage.addEventListener('wheel', (e) => { e.preventDefault(); cancelAnimationFrame(anim); zoomAt(Math.exp(-e.deltaY * (e.ctrlKey ? .01 : .0016)), e.clientX, e.clientY); }, { passive: false });
	stage.addEventListener('keydown', (e) => {
		const s = 60;
		if (e.key === '+' || e.key === '=') { zoomAt(1.5, VW / 2, VH / 2); } else if (e.key === '-') { zoomAt(1 / 1.5, VW / 2, VH / 2); }
		else if (e.key === 'ArrowLeft') { V.tx += s; } else if (e.key === 'ArrowRight') { V.tx -= s; } else if (e.key === 'ArrowUp') { V.ty += s; } else if (e.key === 'ArrowDown') { V.ty -= s; } else { return; }
		e.preventDefault(); clamp(V); apply();
	});
	$('zin').onclick = () => { const b = visibleBox(); zoomAt(1.7, b.w / 2, b.h / 2); };
	$('zout').onclick = () => { const b = visibleBox(); zoomAt(1 / 1.7, b.w / 2, b.h / 2); };
	$('zhome').onclick = home;
	const picker = $('albumpick'); if (picker) { picker.addEventListener('change', () => { const o = picker.selectedOptions[0]; if (o && o.dataset.url) { location.href = o.dataset.url; } }); }

	/* ---------------------------------------------------------------- toast */
	let toastTimer = 0;
	function toast(message, ms = 4600) { const t = $('toast'); t.textContent = message; t.classList.add('mv-show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.remove('mv-show'), ms); }

	/* ---------------------------------------------------------------- drawer: latest trips */
	let dtab = 'new';
	function tripRow(t, i) {
		const meta = [photosLabel(t.ph.length), ago(t.ts)]; if (t.lk > 0) { meta.push(fmt(T('COM_MAPAVIAJES_JS_LIKES_N'), t.lk)); }
		return h('li', { class: 'mv-arow', style: 'animation-delay:' + Math.min(i, 10) * 35 + 'ms' },
			h('a', { class: 'mv-arow-main', href: '#', onclick: (e) => { e.preventDefault(); openViewer(t); } },
				t.ph.length ? h('img', { alt: '', src: photoSrc(t.ph[0].id), width: '64', height: '64' }) : h('span', { style: 'width:64px;height:64px' }),
				h('span', { style: 'min-width:0' }, h('strong', { text: t.t }), h('small', { text: t.p + ' · ' + t.by }), h('small', { class: 'mv-m', text: meta.join(' · ') }))),
			h('div', { class: 'mv-arow-act' }, h('button', { class: 'mv-ib', type: 'button', style: 'min-height:36px', 'aria-label': T('COM_MAPAVIAJES_JS_ON_MAP') + ': ' + t.t, onclick: () => {
				if (innerWidth <= 720) { closeDrawer(); }
				flyTo(viewFor(t.x, t.y, Math.max(KMIN * 16, V.k)), 900);
				setTimeout(() => { const c = clusters().find((cc) => cc.items.includes(t)); if (c && V.k / KMIN < ZP) { openPop(c); } }, 950);
			} }, h('span', { text: T('COM_MAPAVIAJES_JS_ON_MAP') }))));
	}
	function renderDrawer() {
		const body = $('dBody'), favs = TRIPS.filter((t) => t.mf && !t.pending).sort((a, b) => b.ts - a.ts);
		for (const tb of document.querySelectorAll('#mv-drawer [role=tab]')) { tb.setAttribute('aria-selected', String(tb.dataset.tab === dtab)); }
		$('favN').textContent = favs.length ? String(favs.length) : ''; $('favN').hidden = !favs.length;
		body.setAttribute('aria-labelledby', 'mv-tab-' + dtab);
		if (dtab === 'fav') {
			body.replaceChildren(favs.length ? h('p', { class: 'mv-d-sub', text: T('COM_MAPAVIAJES_JS_FAV_HELP') }) : null,
				favs.length ? h('ul', { class: 'mv-list2' }, favs.map(tripRow))
					: h('div', { class: 'mv-empty-fav' }, h('strong', { text: T('COM_MAPAVIAJES_JS_FAV_EMPTY') }), h('span', { text: T('COM_MAPAVIAJES_JS_FAV_EMPTY_HELP') })));
			return;
		}
		const list = [...TRIPS].filter((t) => !t.pending).sort((a, b) => b.ts - a.ts).slice(0, 12);
		body.replaceChildren(h('p', { class: 'mv-d-sub', text: T('COM_MAPAVIAJES_JS_LATEST_HELP') }), h('ul', { class: 'mv-list2' }, list.map(tripRow)));
	}
	for (const tb of document.querySelectorAll('#mv-drawer [role=tab]')) {
		tb.addEventListener('click', () => { dtab = tb.dataset.tab; renderDrawer(); });
		tb.addEventListener('keydown', (e) => { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); const o = document.querySelector('#mv-drawer [role=tab]:not([data-tab="' + tb.dataset.tab + '"])'); o.focus(); o.click(); } });
	}
	function openDrawer() { closePop(); if (panelOpen()) { closePanel(false); } renderDrawer(); $('drawer').classList.add('mv-open'); $('hint').classList.add('mv-off'); setTimeout(() => $('dTitle').focus({ preventScroll: true }), 300); }
	function closeDrawer() { $('drawer').classList.remove('mv-open'); }
	$('btnDisc').onclick = () => (drawerOpen() ? closeDrawer() : openDrawer());
	$('dClose').onclick = () => { closeDrawer(); $('btnDisc').focus({ preventScroll: true }); };

	/* ---------------------------------------------------------------- photo viewer */
	const VWR = { t: null, i: 0, opener: null };
	function openViewer(t) {
		if (!t || !t.ph.length) { return; }
		VWR.t = t; VWR.i = 0; VWR.opener = document.activeElement;
		const v = $('viewer'); v.hidden = false; v.classList.remove('mv-bare'); v.classList.toggle('mv-single', t.ph.length < 2);
		$('vT').textContent = t.t; showPhoto(0, 0); syncReact(); $('vMsg').classList.remove('mv-show'); $('vMsg').textContent = ''; $('vClose').focus({ preventScroll: true });
	}
	function closeViewer() { $('viewer').hidden = true; if (document.fullscreenElement) { try { document.exitFullscreen(); } catch (_) { /* ignore */ } } if (VWR.opener && VWR.opener.focus) { VWR.opener.focus({ preventScroll: true }); } }
	function showPhoto(i, dir) {
		const n = VWR.t.ph.length; VWR.i = (i + n) % n; const p = VWR.t.ph[VWR.i], img = $('vImg');
		img.style.transition = 'none'; img.style.opacity = dir ? 0 : 1; img.style.transform = dir ? 'translateX(' + dir * 48 + 'px)' : 'none';
		img.src = photoSrc(p.id); img.alt = p.cap || fmt(T('COM_MAPAVIAJES_JS_PHOTO_X_OF_Y'), VWR.i + 1, n);
		requestAnimationFrame(() => requestAnimationFrame(() => { img.style.transition = ''; img.style.opacity = 1; img.style.transform = 'none'; }));
		$('vCap').textContent = p.cap || '';
		$('vS').textContent = (n > 1 ? (VWR.i + 1) + ' / ' + n + ' · ' : '') + VWR.t.p;
		$('vLive').textContent = n > 1 ? fmt(T('COM_MAPAVIAJES_JS_PHOTO_X_OF_Y'), VWR.i + 1, n) : '';
		if (n > 1) { for (const d of [1, -1]) { new Image().src = photoSrc(VWR.t.ph[(VWR.i + d + n) % n].id); } }
	}
	/* ---- reactions: a heart with a random kind word underneath, and a star that says "I'm your fan!" ---- */
	const LIKE_MSGS = Array.from({ length: 10 }, (_, i) => 'COM_MAPAVIAJES_JS_LIKE_' + (i + 1));
	let lastMsg = -1, msgTimer = 0;
	function randomLike() { let i; do { i = Math.floor(Math.random() * LIKE_MSGS.length); } while (i === lastMsg && LIKE_MSGS.length > 1); lastMsg = i; return T(LIKE_MSGS[i]); }
	function showMsg(text) { const m = $('vMsg'); m.textContent = text; m.classList.remove('mv-show'); void m.offsetWidth; m.classList.add('mv-show'); clearTimeout(msgTimer); msgTimer = setTimeout(() => m.classList.remove('mv-show'), 2600); }
	function syncReact() {
		const t = VWR.t; if (!t) { return; }
		const own = !!t.mine, rows = [['vLike', 'ml', 'lk', 'COM_MAPAVIAJES_JS_LIKE_LABEL', 'COM_MAPAVIAJES_JS_LIKE_UNDO'], ['vFan', 'mf', 'fn', 'COM_MAPAVIAJES_JS_FAN_LABEL', 'COM_MAPAVIAJES_JS_FAN_UNDO']];
		for (const [id, mineKey, countKey, label, undo] of rows) {
			const b = $(id), on = !!t[mineKey], n = t[countKey] || 0;
			b.setAttribute('aria-pressed', String(on)); b.disabled = own || !!t.pending; $(id + 'N').textContent = n > 0 ? String(n) : '';
			b.setAttribute('aria-label', own ? fmt(T('COM_MAPAVIAJES_JS_OWN_REACT'), t.lk || 0, t.fn || 0) : T(on ? undo : label) + (n > 0 ? ' (' + n + ')' : ''));
		}
	}
	let reacting = false;
	async function react(kind) {
		const t = VWR.t; if (!t || t.mine || t.pending || reacting) { return; }
		const mineKey = kind === 'fan' ? 'mf' : 'ml', countKey = kind === 'fan' ? 'fn' : 'lk', was = !!t[mineKey], wasN = t[countKey] || 0;
		reacting = true; t[mineKey] = !was; t[countKey] = Math.max(0, wasN + (was ? -1 : 1)); syncReact();
		const fd = new FormData(); fd.append(O.token, '1'); fd.append('trip_id', String(t.id)); fd.append('kind', kind);
		try {
			const res = await fetch(O.reactUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
			let j = null; try { j = await res.json(); } catch (_) { /* not JSON */ }
			if (!res.ok || !j || !j.ok) { throw new Error((j && j.error) || 'react'); }
			t[mineKey] = !!j.active; t[countKey] = j.count; syncReact();
			if (j.active) { showMsg(kind === 'fan' ? T('COM_MAPAVIAJES_JS_FAN_MSG') : randomLike()); }
			if (drawerOpen()) { renderDrawer(); }
		} catch (err) {
			t[mineKey] = was; t[countKey] = wasN; syncReact(); toast(err && err.message && err.message !== 'react' ? err.message : T('COM_MAPAVIAJES_JS_REACT_ERR'));
		} finally { reacting = false; }
	}
	$('vLike').onclick = (e) => { e.stopPropagation(); react('like'); }; $('vFan').onclick = (e) => { e.stopPropagation(); react('fan'); };
	const vStep = (d) => { if (VWR.t && VWR.t.ph.length > 1) { showPhoto(VWR.i + d, d); } };
	$('vPrev').onclick = (e) => { e.stopPropagation(); vStep(-1); }; $('vNext').onclick = (e) => { e.stopPropagation(); vStep(1); };
	$('vClose').onclick = closeViewer;
	$('vFs').onclick = () => { try { document.fullscreenElement ? document.exitFullscreen() : $('viewer').requestFullscreen(); } catch (_) { /* ignore */ } };
	document.addEventListener('keydown', (e) => {
		if ($('viewer').hidden) { return; }
		if (e.key === 'ArrowRight') { vStep(1); } else if (e.key === 'ArrowLeft') { vStep(-1); } else if (e.key === 'f' || e.key === 'F') { $('vFs').click(); }
		else if (e.key === 'Tab') { const f = [...$('viewer').querySelectorAll('button:not([disabled])')].filter((x) => x.offsetParent !== null); if (!f.length) { return; } const i = f.indexOf(document.activeElement); if (e.shiftKey && i <= 0) { e.preventDefault(); f[f.length - 1].focus(); } else if (!e.shiftKey && i === f.length - 1) { e.preventDefault(); f[0].focus(); } }
	});
	(function () {
		const st = $('vStage'), img = $('vImg'); let x0 = null, y0 = 0, dx = 0;
		st.addEventListener('pointerdown', (e) => { x0 = e.clientX; y0 = e.clientY; dx = 0; st.setPointerCapture(e.pointerId); img.style.transition = 'none'; });
		st.addEventListener('pointermove', (e) => { if (x0 === null) { return; } dx = e.clientX - x0; if (Math.abs(dx) > 6 && VWR.t.ph.length > 1) { img.style.transform = 'translateX(' + dx + 'px)'; img.style.opacity = String(1 - Math.min(.5, Math.abs(dx) / innerWidth)); } });
		const end = (e) => {
			if (x0 === null) { return; } const mv = Math.abs(dx) > 6 || Math.abs(e.clientY - y0) > 10; x0 = null; img.style.transition = '';
			if (!mv && e.type === 'pointerup') { $('viewer').classList.toggle('mv-bare'); img.style.transform = 'none'; img.style.opacity = 1; return; }
			if (Math.abs(dx) > 60 && VWR.t.ph.length > 1) { vStep(dx < 0 ? 1 : -1); } else { img.style.transform = 'none'; img.style.opacity = 1; }
		};
		st.addEventListener('pointerup', end); st.addEventListener('pointercancel', end);
	})();

	/* ---------------------------------------------------------------- form (only when the user may add trips) */
	const F = { pt: null, titleTouched: false, photos: [], busy: false };
	let renderPhotos = () => {}, syncSub = () => {}; // replaced below when the user may add trips
	let draftEl = null, lastFocus = null;
	const panel = $('panel');

	function placeDraft() {
		if (!F.pt || !panelOpen()) { if (draftEl) { draftEl.remove(); draftEl = null; } return; }
		if (!draftEl) { draftEl = h('div', { class: 'mv-pin mv-draft', style: 'width:46px;pointer-events:none' }, h('span', { class: 'mv-pin-body' }, pinSVG(1, true))); pinsEl.appendChild(draftEl); }
		draftEl.style.transform = 'translate(' + (V.tx + F.pt.x * V.k - 23).toFixed(1) + 'px,' + (V.ty + F.pt.y * V.k - 63.25).toFixed(1) + 'px)'; draftEl.style.zIndex = '9000';
	}

	let nearSeq = 0;
	const getJSON = (url) => fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => (r.ok ? r.json() : null));
	const regionNames = (() => { try { return new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' }); } catch (_) { return null; } })();
	const countryName = (cc) => { try { return (regionNames && regionNames.of(cc)) || cc; } catch (_) { return cc; } };
	function mapClick(sx, sy, target) {
		closePop();
		if (!panel) { return; }
		const mx = (sx - V.tx) / V.k, my = (sy - V.ty) / V.k;
		if (mx < 0 || mx > W || my < 0 || my > H) { return; }
		const [lon, lat] = invert(mx, my);
		const seq = ++nearSeq, onLand = !!(target && target.dataset && target.dataset.c);
		setPoint({ la: lat, lo: lon, x: mx, y: my, label: onLand ? T('COM_MAPAVIAJES_JS_POINT') : T('COM_MAPAVIAJES_JS_SEA'), city: null }, false); openPanel();
		getJSON(O.nearUrl + '&lat=' + lat.toFixed(5) + '&lon=' + lon.toFixed(5)).then((r) => {
			const g = r && r.place; if (seq !== nearSeq || !g || !F.pt || F.pt.x !== mx || F.pt.y !== my) { return; }
			const name = String(g.n), place = name + ', ' + countryName(String(g.c));
			F.pt.label = fmt(T('COM_MAPAVIAJES_JS_NEAR'), place); F.pt.city = name; $('q').value = F.pt.label;
			if (!F.titleTouched) { $('title').value = fmt(T('COM_MAPAVIAJES_JS_TITLE_AUTO', 'Fotos en %s'), name); }
		}).catch(() => {});
	}
	function setPoint(pt, fly) {
		F.pt = pt; $('q').value = pt.label; $('q').removeAttribute('aria-invalid'); $('qerr').textContent = '';
		$('coords').textContent = pt.la.toFixed(3) + ', ' + pt.lo.toFixed(3); $('where').hidden = false;
		if (!F.titleTouched && pt.city) { $('title').value = fmt(T('COM_MAPAVIAJES_JS_TITLE_AUTO', 'Fotos en %s'), pt.city); }
		placeDraft();
		if (fly) { flyTo(viewFor(pt.x, pt.y, Math.max(V.k, KMIN * 5))); } else { const b = visibleBox(), sx = V.tx + pt.x * V.k; if (sx > b.w - 60 || sx < 60) { flyTo(viewFor(pt.x, pt.y, V.k), 600); } }
	}
	function openPanel() {
		if (!panel) { return; }
		lastFocus = document.activeElement; closePop(); closeDrawer(); panel.classList.add('mv-open'); $('hint').classList.add('mv-off');
		setTimeout(() => $('q').focus({ preventScroll: true }), 350);
		if (F.pt) { flyTo(viewFor(F.pt.x, F.pt.y, Math.max(V.k, KMIN * 4))); }
	}
	function closePanel(reset) { if (!panel) { return; } panel.classList.remove('mv-open'); if (reset) { resetForm(); } if (lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); } placeDraft(); }
	function resetForm() {
		F.pt = null; F.titleTouched = false; for (const p of F.photos) { URL.revokeObjectURL(p.url); } F.photos = [];
		$('form').reset(); $('q').value = ''; $('title').value = ''; $('where').hidden = true;
		for (const id of ['qerr', 'terr', 'perr', 'summary']) { $(id).textContent = ''; }
		$('q').removeAttribute('aria-invalid'); $('title').removeAttribute('aria-invalid'); renderPhotos(); syncSub(); placeDraft();
	}

	if (panel) {
		$('btnAdd').onclick = openPanel; $('btnClose').onclick = () => closePanel(false); $('btnCancel').onclick = () => { if (!F.busy) { closePanel(true); } };

		/* place search: our own list, no third party ever sees what the user types */
		const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
		let opts = [], act = -1;
		const parseCoords = (s) => { const m = s.match(/^\s*(-?\d{1,2}(?:[.,]\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:[.,]\d+)?)\s*$/); if (!m) { return null; } const la = +m[1].replace(',', '.'), lo = +m[2].replace(',', '.'); return Math.abs(la) <= 90 && Math.abs(lo) <= 180 ? { la, lo } : null; };
		let searchTimer = 0, searchSeq = 0;
		function search() {
			const q = $('q').value; opts = []; clearTimeout(searchTimer);
			if (norm(q).length < 2) { searchSeq++; hideList(); return; }
			const c = parseCoords(q);
			if (c) { const [x, y] = project(c.lo, c.la), lb = fmt(T('COM_MAPAVIAJES_JS_COORDS'), c.la.toFixed(3) + ', ' + c.lo.toFixed(3)); opts.push({ label: lb, sub: T('COM_MAPAVIAJES_JS_USE_POINT'), pt: { la: c.la, lo: c.lo, x, y, label: lb, city: null } }); act = 0; showList(); return; }
			// our own server answers (GeoNames file, nothing leaves the site); wait for a pause in typing, ignore late answers
			const seq = ++searchSeq;
			searchTimer = setTimeout(() => {
				getJSON(O.searchUrl + '&q=' + encodeURIComponent(q.slice(0, 80))).then((r) => {
					if (seq !== searchSeq) { return; }
					opts = ((r && r.places) || []).slice(0, 8).map((g) => { const [x, y] = project(+g.lo, +g.la), n = String(g.n), cn = countryName(String(g.c)); return { label: n, sub: cn, pt: { la: +g.la, lo: +g.lo, x, y, label: n + ', ' + cn, city: n } }; });
					act = opts.length ? 0 : -1; showList();
				}).catch(() => { if (seq === searchSeq) { opts = []; act = -1; showList(); } });
			}, 160);
		}
		function showList() {
			const l = $('list'); l.hidden = false; $('q').setAttribute('aria-expanded', 'true');
			l.replaceChildren(...(opts.length ? opts.map((o, i) => h('li', { role: 'option', id: 'mv-opt' + i, 'aria-selected': String(i === act), 'data-i': String(i) }, h('span', { text: o.label }), h('small', { text: o.sub })))
				: [h('li', { class: 'mv-none', role: 'option', 'aria-selected': 'false', text: T('COM_MAPAVIAJES_JS_NO_PLACE') })]));
			if (act >= 0) { $('q').setAttribute('aria-activedescendant', 'mv-opt' + act); } else { $('q').removeAttribute('aria-activedescendant'); }
		}
		function hideList() { $('list').hidden = true; $('q').setAttribute('aria-expanded', 'false'); $('q').removeAttribute('aria-activedescendant'); }
		function pick(i) { const o = opts[i]; if (!o) { return; } hideList(); setPoint(o.pt, true); }
		$('q').addEventListener('input', () => { F.pt = null; $('where').hidden = true; placeDraft(); search(); });
		$('q').addEventListener('keydown', (e) => {
			if (!$('list').hidden && opts.length) {
				if (e.key === 'ArrowDown') { e.preventDefault(); act = (act + 1) % opts.length; showList(); } else if (e.key === 'ArrowUp') { e.preventDefault(); act = (act - 1 + opts.length) % opts.length; showList(); } else if (e.key === 'Enter') { e.preventDefault(); pick(act); }
			} else if (e.key === 'Enter') { e.preventDefault(); }
		});
		$('list').addEventListener('pointerdown', (e) => { const li = e.target.closest('[data-i]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
		document.addEventListener('pointerdown', (e) => { if (!e.target.closest('.mv-combo')) { hideList(); } });
		$('title').addEventListener('input', () => { F.titleTouched = true; $('title').removeAttribute('aria-invalid'); $('terr').textContent = ''; });

		/* photos */
		const words = (s) => s.trim().split(/\s+/).filter(Boolean);
		function addFiles(list) {
			const errs = []; let skipped = 0;
			for (const f of list) {
				if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { errs.push(fmt(T('COM_MAPAVIAJES_JS_E_TYPE'), f.name.slice(0, 40))); continue; }
				if (F.photos.length >= O.maxPhotos) { skipped++; continue; }
				F.photos.push({ file: f, url: URL.createObjectURL(f), cap: '' });
			}
			if (skipped) { errs.push(fmt(T('COM_MAPAVIAJES_JS_E_MAX'), O.maxPhotos)); }
			$('perr').textContent = errs.join(' '); renderPhotos();
		}
		renderPhotos = function () {
			const ul = $('photos'); ul.replaceChildren();
			F.photos.forEach((p, i) => {
				const ta = h('textarea', { id: 'mv-cap' + i, rows: '3', placeholder: T('COM_MAPAVIAJES_JS_CAPTION_PH'), 'aria-describedby': 'mv-cc' + i, maxlength: '2500' });
				ta.value = p.cap;
				const n = h('span'), row = h('div', { class: 'mv-prow', id: 'mv-cc' + i }, n, h('button', { class: 'mv-rm', type: 'button', onclick: () => { URL.revokeObjectURL(p.url); F.photos.splice(i, 1); renderPhotos(); $('files').focus(); } }, T('COM_MAPAVIAJES_JS_REMOVE')));
				const upd = () => { const c = words(ta.value).length; n.textContent = fmt(T('COM_MAPAVIAJES_JS_WORDS'), c, O.maxWords); row.classList.toggle('mv-warn', c >= O.maxWords); };
				ta.addEventListener('input', () => { const w = words(ta.value); if (w.length > O.maxWords) { ta.value = w.slice(0, O.maxWords).join(' '); } p.cap = ta.value; upd(); });
				upd();
				ul.appendChild(h('li', { class: 'mv-photo' }, h('div', { class: 'mv-th' }, h('img', { alt: '', src: p.url }), h('em', { text: i + 1 })), h('div', {}, h('label', { class: 'mv-sr', for: 'mv-cap' + i, text: T('COM_MAPAVIAJES_JS_CAPTION_PH') + ' ' + (i + 1) }), ta, row)));
			});
			$('pcount').textContent = fmt(T('COM_MAPAVIAJES_JS_PHOTOS_OF'), F.photos.length, O.maxPhotos); $('drop').hidden = F.photos.length >= O.maxPhotos;
		};
		$('files').addEventListener('change', (e) => { addFiles([...e.target.files]); e.target.value = ''; });
		const drop = $('drop');
		['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('mv-over'); }));
		['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove('mv-over'); }));
		drop.addEventListener('drop', (e) => addFiles([...e.dataTransfer.files]));

		/* options */
		syncSub = function () {
			const r = document.querySelector('input[name="mv-comments"]:checked'), on = r ? r.value === '1' : false, sub = $('sub'); if (!sub) { return; }
			sub.disabled = !on; $('subLabel').classList.toggle('mv-dis', !on);
		};
		document.querySelectorAll('input[name="mv-comments"]').forEach((r) => r.addEventListener('change', syncSub));
		if (document.querySelector('input[name="mv-comments"]')) { document.querySelector('input[name="mv-comments"][value="' + (O.commentsDefault ? 1 : 0) + '"]').checked = true; }

		/* Shrink every photo in the browser first: a phone photo is 3 to 10 MB and a mobile connection should not carry it.
		 * The server still treats the result as hostile and re-encodes it. */
		async function prepare(file) {
			const MAXPX = 1800;
			try {
				const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
				const s = Math.min(1, MAXPX / Math.max(bmp.width, bmp.height));
				if (s === 1 && file.type === 'image/jpeg' && file.size <= 1.5 * 1048576) { if (bmp.close) { bmp.close(); } return file; }
				const cw = Math.max(1, Math.round(bmp.width * s)), ch = Math.max(1, Math.round(bmp.height * s));
				const canvas = document.createElement('canvas'); canvas.width = cw; canvas.height = ch;
				const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch); ctx.drawImage(bmp, 0, 0, cw, ch);
				if (bmp.close) { bmp.close(); }
				const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.88));
				if (!blob) { throw new Error('blob'); }
				return blob;
			} catch (_) {
				if (file.size <= O.maxBytes) { return file; }
				throw new Error('size');
			}
		}

		/* submit */
		function setBusy(on, label) { F.busy = on; $('btnSend').disabled = on; $('btnSend').textContent = on ? label : T('COM_MAPAVIAJES_JS_SEND'); $('progress').hidden = !on; if (!on) { $('progressBar').style.width = '0'; } }
		function showErrors() {
			const errs = [];
			if (!F.pt) { $('qerr').textContent = T('COM_MAPAVIAJES_JS_E_PLACE'); $('q').setAttribute('aria-invalid', 'true'); errs.push($('q')); }
			if (!$('title').value.trim()) { $('terr').textContent = T('COM_MAPAVIAJES_JS_E_TITLE'); $('title').setAttribute('aria-invalid', 'true'); errs.push($('title')); }
			if (!F.photos.length) { $('perr').textContent = T('COM_MAPAVIAJES_JS_E_PHOTOS'); errs.push($('files')); }
			$('summary').textContent = errs.length ? T('COM_MAPAVIAJES_JS_E_MISSING') : '';
			if (errs.length) { const f = errs[0]; (f.id === 'mv-files' ? $('drop') : f).scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' }); if (f.id !== 'mv-files') { f.focus({ preventScroll: true }); } }
			return errs.length === 0;
		}
		$('form').addEventListener('submit', async (e) => {
			e.preventDefault();
			if (F.busy || !showErrors()) { return; }
			setBusy(true, T('COM_MAPAVIAJES_JS_PREPARING'));
			let blobs;
			try { blobs = await Promise.all(F.photos.map((p) => prepare(p.file))); } catch (_) { setBusy(false); $('perr').textContent = fmt(T('COM_MAPAVIAJES_JS_E_SIZE'), ''); return; }
			const fd = new FormData();
			fd.append(O.token, '1'); fd.append('album_id', String(O.albumId)); fd.append('title', $('title').value); fd.append('place', F.pt.label);
			fd.append('lat', F.pt.la.toFixed(6)); fd.append('lon', F.pt.lo.toFixed(6)); fd.append('website', $('website').value);
			const r = document.querySelector('input[name="mv-comments"]:checked'), allow = r ? r.value === '1' : false;
			fd.append('allow_comments', allow ? '1' : '0'); fd.append('notify', allow && $('sub') && $('sub').checked ? '1' : '0');
			blobs.forEach((b, i) => { fd.append('photos[]', b, 'photo-' + (i + 1) + '.jpg'); fd.append('captions[]', F.photos[i].cap); });
			setBusy(true, T('COM_MAPAVIAJES_JS_SENDING'));
			const xhr = new XMLHttpRequest();
			xhr.open('POST', O.submitUrl); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); xhr.setRequestHeader('Accept', 'application/json');
			xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) { $('progressBar').style.width = Math.round(ev.loaded / ev.total * 100) + '%'; } };
			xhr.onerror = xhr.ontimeout = () => { setBusy(false); $('summary').textContent = T('COM_MAPAVIAJES_JS_E_NET'); };
			xhr.timeout = 120000;
			xhr.onload = async () => {
				let j = null; try { j = JSON.parse(xhr.responseText); } catch (_) { /* not JSON */ }
				setBusy(false);
				if (j && j.ok) { toast(String(j.message || ''), 6500); closePanel(true); await loadTrips(); const t = TRIPS.find((x) => x.id === j.id); if (t) { flyTo(viewFor(t.x, t.y, Math.max(V.k, KMIN * 6))); } return; }
				const msg = (j && typeof j.error === 'string') ? j.error : T('COM_MAPAVIAJES_JS_E_NET');
				$('summary').textContent = msg;
				if (j && j.expired) { $('summary').append(' ', h('a', { href: O.loginUrl, text: T('COM_MAPAVIAJES_JS_LOGIN') })); }
				if (j && j.field === 'title') { $('terr').textContent = msg; $('title').setAttribute('aria-invalid', 'true'); $('title').focus(); }
			};
			xhr.send(fd);
		});
	}

	/* ---------------------------------------------------------------- global Escape, resize, boot */
	document.addEventListener('keydown', (e) => {
		if (e.key !== 'Escape') { return; }
		if (!$('viewer').hidden) { closeViewer(); return; }
		if (panel && !$('list').hidden) { $('list').hidden = true; return; }
		if ($('popHost').firstChild) { closePop(); return; }
		if (panelOpen()) { closePanel(false); return; }
		if (drawerOpen()) { closeDrawer(); }
	});
	addEventListener('resize', () => { limits(); clamp(V); apply(); });

	async function loadTrips() {
		try {
			const res = await fetch(O.dataUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
			if (res.status === 403) { toast(T('COM_MAPAVIAJES_JS_SESSION')); return; }
			const j = await res.json();
			TRIPS = (j.trips || []).map((t) => { const [x, y] = project(t.lo, t.la); return { ...t, x, y }; });
			for (const k of [...pinEls.keys()]) { pinEls.get(k).remove(); pinEls.delete(k); }
			render(); if (drawerOpen()) { renderDrawer(); }
		} catch (_) { toast(T('COM_MAPAVIAJES_JS_LOAD_ERROR')); }
	}

	(async function boot() {
		try {
			const w = await fetch(O.worldUrl).then((r) => r.json());
			WORLD = w; W = w.W; H = w.H; drawWorld();
		} catch (_) { toast(T('COM_MAPAVIAJES_JS_LOAD_ERROR'), 20000); return; }
		limits(); Object.assign(V, viewFor(W / 2, H / 2 + (VW <= 720 ? -18 : 0), KMIN)); V.ty = VW <= 720 ? (VH - H * KMIN) / 2 - 20 : V.ty; clamp(V);
		if (panel) { renderPhotos(); syncSub(); }
		apply(); await loadTrips();
		setTimeout(() => $('hint').classList.add('mv-off'), 9000);
	})();
}());
