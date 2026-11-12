/*
 * Velvet Leap Page Turn Feedback - admin script.
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * Loaded only on the edit screen of a feedback, on the plugin settings page and on the Order page.
 * The strings and settings come from PHP through the global ptfbAdmin object (see ptfb_admin_assets()).
 */
(function () {
	'use strict';

	var cfg = window.ptfbAdmin || {};

	/* Replaces %d, %1$d and %2$d in a translated string. */
	function format(text, first, second) {
		return String(text)
			.replace('%1$d', first)
			.replace('%2$d', second)
			.replace('%d', first);
	}

	/* Replaces %s in a translated string. */
	function fill(text, value) {
		return String(text).replace('%s', value);
	}

	/* ---- Edit screen: live character counter for the comment ---- */

	function initCounter() {
		var out = document.getElementById('ptfb-count');
		if (!out) { return; }
		var limit = parseInt(out.getAttribute('data-limit'), 10) || cfg.limit || 300;

		/* Comment text without formatting, in both the visual and the "Text" editor. */
		function plain() {
			var t = '';
			var ed = window.tinymce && window.tinymce.get ? window.tinymce.get('content') : null;
			if (ed && !ed.isHidden()) {
				t = ed.getContent({ format: 'text' });
			} else {
				var ta = document.getElementById('content');
				t = ta ? ta.value.replace(/<[^>]*>/g, ' ') : '';
			}
			return t.replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
		}

		function update() {
			var n = plain().length;
			if (n > limit) {
				out.textContent = format(cfg.tooLong || '%d', n, limit);
				out.style.color = '#b32d2e';
			} else {
				out.textContent = format(cfg.count || '%1$d / %2$d', n, limit);
				out.style.color = n > limit * 0.85 ? '#996800' : '#50575e';
			}
		}

		update();
		setInterval(update, 500);
	}

	/* ---- Settings page ---- */

	/* Shows the "custom value" field only when "custom" is selected in the menu. */
	function toggleOnCustom(select, box, focusTarget) {
		if (!select || !box) { return; }
		select.addEventListener('change', function () {
			box.hidden = select.value !== 'custom';
			if (select.value === 'custom' && focusTarget) { focusTarget.focus(); }
		});
	}

	function initSettings() {
		toggleOnCustom(
			document.getElementById('ptfb_speed'),
			document.getElementById('ptfb_custom_wrap'),
			document.getElementById('ptfb_custom')
		);
		toggleOnCustom(
			document.getElementById('ptfb_anim'),
			document.getElementById('ptfb_anim_wrap'),
			document.getElementById('ptfb_anim_custom')
		);
		Array.prototype.forEach.call(document.querySelectorAll('.ptfb-font-select'), function (sel) {
			var box = document.getElementById(sel.getAttribute('data-target'));
			toggleOnCustom(sel, box, box ? box.querySelector('input') : null);
		});

		/* WordPress color picker (its script is a dependency of this one on the settings page). */
		if (window.jQuery && window.jQuery.fn.wpColorPicker) {
			window.jQuery('.ptfb-color').wpColorPicker({
				change: function () { onColorChange(); },
				clear: function () { onColorChange(); }
			});
		}

		initPresetsAndPreview();
	}

	/* ---- Settings page: ready-made styles and live preview ---- */

	var form = null;
	var onColorChange = function () {}; /* replaced by the preview code below */

	function fieldValue(name) {
		var el = form && form.elements[name];
		return el ? String(el.value) : '';
	}

	/* Same cleaning as ptfb_clean_font_name(): harmless characters only, and quotes only when they come in pairs. */
	var FONT_NAME = '(?:"[A-Za-z0-9 _-]+"|\'[A-Za-z0-9 _-]+\'|[A-Za-z0-9 _-]+)';
	var FONT_LIST = new RegExp('^' + FONT_NAME + '(?:\\s*,\\s*' + FONT_NAME + ')*$');

	function cleanFontName(value) {
		var v = String(value).replace(/[^A-Za-z0-9 ,'"_-]/g, '').trim().substring(0, 120).trim();
		return FONT_LIST.test(v) ? v : v.replace(/["']/g, '').trim();
	}

	/* Colors, fonts and alignment as they are in the form right now (what would be saved), with the same fallbacks as PHP. */
	function currentStyle() {
		var defaults = cfg.defaults || {};
		var st = {};
		(cfg.colors || []).forEach(function (key) {
			var c = fieldValue('ptfb_style[' + key + ']').trim().toLowerCase();
			if (/^[0-9a-f]{3}([0-9a-f]{3})?$/.test(c)) { c = '#' + c; }
			st[key] = /^#([0-9a-f]{3}|[0-9a-f]{6})$/.test(c) ? c : defaults[key];
		});
		(cfg.roles || []).forEach(function (role) {
			var choice = fieldValue('ptfb_style[font_' + role + ']') || 'theme';
			st['font_' + role] = choice;
			st['font_' + role + '_custom'] = cleanFontName(fieldValue('ptfb_style[font_' + role + '_custom]'));
		});
		['align_text', 'align_name'].forEach(function (key) {
			var v = fieldValue('ptfb_style[' + key + ']');
			st[key] = (v === 'left' || v === 'center' || v === 'right') ? v : defaults[key];
		});
		return st;
	}

	/* Same as ptfb_font_family(): CSS font-family for a role, or '' to keep the theme font. */
	function fontFamily(st, role) {
		var choice = st['font_' + role];
		var stacks = cfg.stacks || {};
		if (choice === 'heading') { return 'var(--ptfb-heading-font, inherit)'; }
		if (choice === 'body') { return 'var(--ptfb-body-font, inherit)'; }
		if (stacks[choice]) { return stacks[choice]; }
		if (choice === 'custom' && st['font_' + role + '_custom'] !== '') { return st['font_' + role + '_custom']; }
		return '';
	}

	/* Same rules as ptfb_inline_css(), but always complete (PHP writes only what differs from the default: same result). */
	function buildCss(st) {
		var vars = (cfg.colors || []).map(function (key) { return '--ptfb-' + key + ':' + st[key]; });
		var css = 'section.ptfb{' + vars.join(';') + '}';
		var targets = { title: '.ptfb__title', text: '.ptfb__quote', name: '.ptfb__author' };
		(cfg.roles || []).forEach(function (role) {
			var family = fontFamily(st, role);
			if (family !== '') { css += 'section.ptfb ' + targets[role] + '{font-family:' + family + '}'; }
		});
		css += 'section.ptfb .ptfb__quote,section.ptfb .ptfb__quote p{text-align:' + st.align_text + '}';
		css += 'section.ptfb .ptfb__author{text-align:' + st.align_name + '}';
		return css;
	}

	/* Seconds between two feedback (0 = no automatic sliding), as ptfb_get_interval(). */
	function intervalSeconds() {
		var choice = fieldValue('ptfb_speed');
		if (choice === 'off') { return 0; }
		if (choice === 'custom') {
			var n = parseInt(fieldValue('ptfb_custom'), 10);
			return isNaN(n) ? 8 : Math.max(2, Math.min(120, n));
		}
		return parseInt(choice, 10) || 5;
	}

	/* Page-turn duration in milliseconds, as ptfb_get_anim_ms(). */
	function turnMs() {
		var choice = fieldValue('ptfb_anim');
		var sec = parseFloat(fieldValue(choice === 'custom' ? 'ptfb_anim_custom' : 'ptfb_anim').replace(',', '.'));
		if (isNaN(sec)) { sec = choice === 'custom' ? 1.5 : 1.1; }
		sec = Math.max(0.3, Math.min(5, Math.round(sec * 10) / 10));
		return Math.round(sec * 1000);
	}

	function initPresetsAndPreview() {
		form = document.querySelector('.ptfb-settings__main form');
		if (!form) { return; }

		var jq = window.jQuery;
		var frame = document.getElementById('ptfb-preview');
		var box = document.getElementById('ptfb-preview-box');
		var tpl = document.getElementById('ptfb-preview-template');
		var live = !!(frame && box && tpl && cfg.preview);
		var timers = {};

		function later(key, fn, ms) {
			clearTimeout(timers[key]);
			timers[key] = setTimeout(fn, ms);
		}

		/* The whole page of the preview: the real markup from PHP plus the plugin stylesheet and script. */
		function buildDoc() {
			var holder = document.createElement('template');
			holder.innerHTML = tpl.innerHTML;
			var section = holder.content.querySelector('section.ptfb');
			if (!section) { return ''; }
			section.setAttribute('data-interval', String(intervalSeconds() * 1000));
			section.setAttribute('data-turn', String(turnMs()));

			var title = fieldValue('ptfb_title').trim();
			var heading = section.querySelector('.ptfb__title');
			if (heading && title === '') {
				heading.parentNode.removeChild(heading);
			} else if (heading) {
				heading.textContent = title;
			} else if (title !== '') {
				var inner = section.querySelector('.ptfb__inner');
				heading = document.createElement('h2');
				heading.className = 'ptfb__title';
				heading.textContent = title;
				if (inner) { inner.insertBefore(heading, inner.firstChild); }
			}
			var wrap = document.createElement('div');
			wrap.appendChild(holder.content.cloneNode(true));

			return '<!doctype html><html><head><meta charset="utf-8">' +
				'<meta name="viewport" content="width=device-width,initial-scale=1">' +
				'<link rel="stylesheet" href="' + cfg.preview.css.replace(/"/g, '&quot;') + '">' +
				'<style id="ptfb-live">' + buildCss(currentStyle()) + '</style>' +
				'<style>html,body{margin:0;padding:0}section.ptfb{margin-top:0}body{font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#222;background:#fff}</style>' +
				'</head><body>' + wrap.innerHTML +
				'<script src="' + cfg.preview.js.replace(/"/g, '&quot;') + '"><\/script></body></html>';
		}

		/* The frame is as tall as its content (the card is taller or shorter with other fonts). */
		function fitFrame() {
			try {
				var doc = frame.contentDocument;
				if (doc && doc.documentElement) {
					frame.style.height = Math.max(200, doc.documentElement.scrollHeight) + 'px';
				}
			} catch (e) { /* not reachable: keep the current height */ }
		}

		function rebuild() {
			if (!live) { return; }
			frame.srcdoc = buildDoc();
		}

		/* Colors, fonts and alignment change without reloading the preview, so the page keeps turning. */
		function restyle() {
			if (!live) { return; }
			try {
				var el = frame.contentDocument.getElementById('ptfb-live');
				if (el) { el.textContent = buildCss(currentStyle()); }
			} catch (e) { /* the frame is not ready yet: it starts with the current style anyway */ }
			later('fit', fitFrame, 60);
		}

		function onFieldChange(event) {
			var name = event && event.target ? (event.target.name || '') : '';
			if (name.indexOf('ptfb_style[') === 0) {
				restyle();
			} else if (name === 'ptfb_title' || name === 'ptfb_speed' || name === 'ptfb_custom' || name === 'ptfb_anim' || name === 'ptfb_anim_custom') {
				later('rebuild', rebuild, 350);
			}
		}

		if (live) {
			box.hidden = false;
			frame.addEventListener('load', function () {
				fitFrame();
				later('fit2', fitFrame, 400);
			});
			form.addEventListener('input', onFieldChange);
			form.addEventListener('change', onFieldChange);
			rebuild();
		}

		onColorChange = function () { later('restyle', restyle, 0); };

		/* Ready-made styles. */
		var presetsWrap = document.getElementById('ptfb-presets-wrap');
		var note = document.getElementById('ptfb-preset-note');
		if (!presetsWrap || !cfg.presets) { return; }
		presetsWrap.hidden = false;

		function setField(name, value) {
			var el = form.elements[name];
			if (!el) { return; }
			el.value = value;
			el.dispatchEvent(new Event('change', { bubbles: true }));
		}

		function applyStyle(st) {
			(cfg.colors || []).forEach(function (key) {
				var el = form.elements['ptfb_style[' + key + ']'];
				if (!el) { return; }
				el.value = st[key];
				if (jq && jq.fn.wpColorPicker) {
					try { jq(el).wpColorPicker('color', st[key]); } catch (e) { /* picker not started: the value is already set */ }
				}
			});
			(cfg.roles || []).forEach(function (role) {
				setField('ptfb_style[font_' + role + '_custom]', st['font_' + role + '_custom'] || '');
				setField('ptfb_style[font_' + role + ']', st['font_' + role]);
			});
			setField('ptfb_style[align_text]', st.align_text);
			setField('ptfb_style[align_name]', st.align_name);
			restyle();
		}

		Array.prototype.forEach.call(presetsWrap.querySelectorAll('.ptfb-preset'), function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.getAttribute('data-preset');
				if (key === 'default') {
					applyStyle(cfg.defaults);
					if (note) { note.textContent = cfg.presetDefault || ''; }
				} else if (cfg.presets[key]) {
					applyStyle(cfg.presets[key].style);
					if (note) { note.textContent = fill(cfg.presetApplied || '%s', cfg.presets[key].label); }
				}
			});
		});
	}

	/* ---- Order page: drag and drop, arrows ---- */

	function initOrder() {
		var list = document.getElementById('ptfb-order-list');
		if (!list) { return; }
		var max = parseInt(list.getAttribute('data-max'), 10) || 0;

		function rows() {
			return Array.prototype.slice.call(list.children);
		}

		/* Disables the arrows at the ends and marks the rows the site does not show (beyond the limit). */
		function refresh() {
			var all = rows();
			all.forEach(function (row, i) {
				row.classList.toggle('ptfb-order__row--beyond', max > 0 && i >= max);
				var up = row.querySelector('[data-move="up"]');
				var down = row.querySelector('[data-move="down"]');
				if (up) { up.disabled = i === 0; }
				if (down) { down.disabled = i === all.length - 1; }
			});
		}

		function speak(row) {
			if (window.wp && window.wp.a11y && window.wp.a11y.speak && cfg.moved) {
				window.wp.a11y.speak(format(cfg.moved, rows().indexOf(row) + 1, rows().length));
			}
		}

		list.addEventListener('click', function (event) {
			var btn = event.target.closest ? event.target.closest('[data-move]') : null;
			if (!btn || btn.disabled) { return; }
			var row = btn.closest('li');
			var up = btn.getAttribute('data-move') === 'up';
			var other = up ? row.previousElementSibling : row.nextElementSibling;
			if (!other) { return; }
			list.insertBefore(row, up ? other : other.nextElementSibling);
			refresh();
			/* Keep the focus on the same kind of arrow, or on the other one if this one is now disabled. */
			var focus = btn.disabled ? row.querySelector(up ? '[data-move="down"]' : '[data-move="up"]') : btn;
			if (focus) { focus.focus(); }
			speak(row);
		});

		if (window.jQuery && window.jQuery.fn.sortable) {
			window.jQuery(list).sortable({
				handle: '.ptfb-order__handle',
				axis: 'y',
				tolerance: 'pointer',
				placeholder: 'ptfb-order__placeholder',
				forcePlaceholderSize: true,
				update: function (event, ui) {
					refresh();
					speak(ui.item[0]);
				}
			});
		}

		var reset = document.querySelector('#ptfb-order-form [name="ptfb_reset"]');
		if (reset) {
			reset.addEventListener('click', function (event) {
				if (cfg.confirmReset && !window.confirm(cfg.confirmReset)) { event.preventDefault(); }
			});
		}

		refresh();
	}

	initCounter();
	initSettings();
	initOrder();
})();
