/*
 * Page Turn Feedback - admin script.
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * Loaded only on the edit screen of a feedback and on the plugin settings page.
 * The strings come from PHP through the global ptfbAdmin object (see ptfb_admin_assets()).
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
			window.jQuery('.ptfb-color').wpColorPicker();
		}
	}

	initCounter();
	initSettings();
})();
