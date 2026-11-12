/*
 * Velvet Leap Page Turn Feedback - no dependencies.
 * Copyright (C) 2026 Mattia Scudellaro - GPL-2.0-or-later
 *
 * The card turns its page like a book, with a real fold: a corner at the bottom right
 * is already folded, and when the page turns the crease crosses the card from right to left,
 * revealing the next feedback (the back of the folded page stays visible
 * until the page leaves on the left).
 *
 * The page turns:
 *  - by itself, after the time chosen in the settings;
 *  - by tapping/clicking the folded corner (or dragging it);
 *  - by pressing and holding on the text and dragging left (forward) or right (back).
 * While the text is held the automatic sliding is stopped; on release it restarts.
 *
 * Geometry: the corner C (bottom right) is "pulled" to a point P. The crease is
 * the perpendicular bisector of the segment C-P; the part of the page beyond the crease is
 * flipped by reflecting it over that axis. The path of P is computed in path().
 */
(function () {
	'use strict';

	var TURN_MS = 1100;       // default page-turn duration (changed in Settings: data-turn attribute)
	var DRAG_START = 6;      // pixels before a movement counts as a drag
	var DRAG_RANGE = 0.85;    // share of the width to drag to turn the whole page
	var COMMIT_AT = 0.28;     // share of the turn beyond which the page turns on release
	var FLICK_SPEED = 0.5;    // px/ms: a quick "flick" turns the page even if short
	var LIFT = 0.42;          // how much the corner lifts during the turn (in card heights)
	var EAR_MS = 200;         // corner animation on hover

	function easeInOut(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
	function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

	/*
	 * Last way the page was used: keyboard or pointer (mouse/finger).
	 * Used to show the corner focus indicator only to people navigating with Tab:
	 * after a click no outline must remain on the corner.
	 */
	var usingKeyboard = false;
	document.addEventListener('keydown', function (e) {
		var k = e.key || '';
		if (k === 'Tab' || k === 'Enter' || k === ' ' || k.indexOf('Arrow') === 0) { usingKeyboard = true; }
	}, true);
	document.addEventListener('pointerdown', function () { usingKeyboard = false; }, true);

	/* Titles and texts use the theme fonts: we read them from a probe element. */
	function copyFont(root, tag, varName) {
		var probe = document.createElement(tag);
		probe.textContent = 'x';
		probe.style.cssText = 'position:absolute;visibility:hidden;height:0;margin:0;padding:0;overflow:hidden';
		root.appendChild(probe);
		var family = window.getComputedStyle(probe).fontFamily;
		root.removeChild(probe);
		if (family) {
			root.style.setProperty(varName, family);
		}
	}

	/* Clips a polygon keeping the points with sign*((p-M)·n) >= 0 (Sutherland–Hodgman). */
	function clipHalf(poly, mx, my, nx, ny, sign) {
		var out = [];
		for (var i = 0; i < poly.length; i++) {
			var a = poly[i];
			var b = poly[(i + 1) % poly.length];
			var fa = sign * ((a[0] - mx) * nx + (a[1] - my) * ny);
			var fb = sign * ((b[0] - mx) * nx + (b[1] - my) * ny);
			if (fa >= 0) { out.push(a); }
			if ((fa >= 0) !== (fb >= 0)) {
				var t = fa / (fa - fb);
				out.push([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]);
			}
		}
		return out;
	}

	function polyValue(poly) {
		if (poly.length < 3) { return 'polygon(0px 0px,0px 0px,0px 0px)'; }
		return 'polygon(' + poly.map(function (p) {
			return p[0].toFixed(1) + 'px ' + p[1].toFixed(1) + 'px';
		}).join(',') + ')';
	}

	function setClip(el, value) {
		el.style.clipPath = value;
		el.style.webkitClipPath = value;
	}

	function div(cls) {
		var el = document.createElement('div');
		el.className = cls;
		el.setAttribute('aria-hidden', 'true');
		return el;
	}

	function init(root) {
		var stage = root.querySelector('.ptfb__stage');
		var slides = Array.prototype.slice.call(root.querySelectorAll('.ptfb__slide'));
		var total = slides.length;

		root.classList.add('is-ready');
		copyFont(root, 'h2', '--ptfb-heading-font');
		copyFont(root, 'p', '--ptfb-body-font');
		if (!stage || total < 2) {
			return;
		}

		var prevBtn = root.querySelector('.ptfb__sr-prev');
		var corner = root.querySelector('.ptfb__corner');

		var interval = parseInt(root.getAttribute('data-interval'), 10) || 0;
		/* Page-turn duration chosen in Settings (between 0.3 and 5 seconds). */
		var turnMs = Math.max(300, Math.min(5000, parseInt(root.getAttribute('data-turn'), 10) || TURN_MS));
		var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
		var autoplay = interval > 0 && !reduceMotion;

		var current = 0;
		var busy = false;       // turn in progress (automatic, or completing after release)
		var drag = null;        // drag in progress
		var raf = 0;
		var timer = 0;
		var held = false;       // finger/mouse pressed
		var focused = false;    // keyboard focus inside the section
		var inView = true;
		var suppressClick = false;
		var width = 1;
		var height = 1;

		/* Folded corner at rest: current size (ear) and sizes at rest / on hover. */
		var ear = 0;
		var earRest = 32;
		var earBig = 50;
		var earRaf = 0;
		var cornerHover = false;
		var cornerFocus = false;

		var view = null;        // last frame drawn during a turn, null = page at rest

		/* Two fold levels: [0] foreground page, [1] page underneath (with its corner). */
		var folds = [makeFold(), makeFold()];

		stage.classList.add('is-interactive');

		function makeFold() {
			var f = { cast: div('ptfb__cast'), wrap: div('ptfb__flapwrap'), flap: div('ptfb__flap') };
			f.wrap.appendChild(f.flap);
			stage.appendChild(f.cast);
			stage.appendChild(f.wrap);
			return f;
		}

		function measure() {
			width = stage.clientWidth || 1;
			height = stage.clientHeight || 1;
			earRest = Math.max(24, Math.min(40, width * 0.04));
			earBig = earRest * 1.6;
			if (!earRaf && ear > 0) { ear = earTarget(); }
		}

		function earTarget() {
			return (cornerHover || cornerFocus) ? earBig : earRest;
		}

		/* ---------- fold geometry ---------- */

		/*
		 * Position of the point P the corner is pulled to, with a from 0 (corner folded at rest)
		 * to 1 (page fully turned, out of the card on the left).
		 * The crease is the bisector of C-P: it rises with P, so it is slanted halfway and vertical at the end.
		 */
		function path(a, d) {
			var ext = width * 0.08;
			return [
				(width - d) - a * (2 * width - d + ext),
				(height - d) + a * d - LIFT * height * Math.sin(Math.PI * a)
			];
		}

		/* Linear gradient with direction (ux,uy) and stops in px starting from the crease (passing through mx,my). */
		function gradient(ux, uy, mx, my, stops) {
			var ang = Math.atan2(ux, -uy) * 180 / Math.PI;
			var lg = Math.abs(width * ux) + Math.abs(height * uy);
			var t = (mx - width / 2) * ux + (my - height / 2) * uy + lg / 2;
			return 'linear-gradient(' + ang.toFixed(2) + 'deg,' + stops.map(function (s) {
				return s[0] + ' ' + (t + s[1]).toFixed(1) + 'px';
			}).join(',') + ')';
		}

		function hideFold(f) {
			f.cast.classList.remove('is-on');
			f.wrap.classList.remove('is-on');
		}

		/*
		 * Draws the "slide" page folded at point a, with its shadow layers.
		 * base: starting level (z-index) of the layer.
		 */
		function drawFold(f, slide, a, base) {
			slide.style.zIndex = String(base + 1);
			f.cast.style.zIndex = String(base);
			f.wrap.style.zIndex = String(base + 2);

			var d = ear;
			if (d < 0.5) {
				setClip(slide, '');
				hideFold(f);
				return;
			}

			var p = path(a, d);
			var cx = width;
			var cy = height;
			var dx = cx - p[0];
			var dy = cy - p[1];
			var len = Math.sqrt(dx * dx + dy * dy);
			var nx = dx / len;                 // from P towards the corner C
			var ny = dy / len;
			var mx = (cx + p[0]) / 2;          // a point on the crease
			var my = (cy + p[1]) / 2;
			var half = len / 2;                // distance between the crease and P

			var rect = [[0, 0], [width, 0], [width, height], [0, height]];
			var kept = clipHalf(rect, mx, my, nx, ny, -1);     // part of the page that is not folded
			var away = clipHalf(rect, mx, my, nx, ny, 1);      // folded part: the page underneath shows here
			var flap = away.map(function (q) {                 // the same part, flipped onto the page
				var s = (q[0] - mx) * nx + (q[1] - my) * ny;
				return [q[0] - 2 * s * nx, q[1] - 2 * s * ny];
			});

			var wave = Math.sin(Math.PI * a);

			setClip(slide, polyValue(kept));

			/* Shadow of the page on the page underneath, just beyond the crease. */
			f.cast.classList.add('is-on');
			setClip(f.cast, polyValue(away));
			f.cast.style.backgroundImage = gradient(nx, ny, mx, my, [
				['rgba(0,0,0,' + (0.12 + 0.12 * wave).toFixed(3) + ')', 0],
				['rgba(0,0,0,0)', 16 + 46 * wave]
			]);

			/* Back of the folded page: darker near the crease. */
			f.wrap.classList.add('is-on');
			setClip(f.flap, polyValue(flap));
			/* Like a page that curls: a narrow shadow on the crease, then a light reflection that fades. */
			var fade = Math.min(half, 30 + 130 * wave);
			f.flap.style.setProperty('--ptfb-fold', gradient(-nx, -ny, mx, my, [
				['rgba(0,0,0,' + (0.2 + 0.06 * wave).toFixed(3) + ')', 0],
				['rgba(0,0,0,0.05)', fade * 0.2],
				['rgba(255,255,255,0.32)', fade * 0.5],
				['rgba(255,255,255,0)', fade]
			]));
		}

		function renderRest() {
			slides.forEach(function (slide, k) {
				if (k !== current) { clearOne(slide); }
			});
			drawFold(folds[0], slides[current], 0, 4);
			hideFold(folds[1]);
		}

		function redraw() {
			if (view) {
				frame(view.dir, view.target, view.p);
			} else {
				renderRest();
			}
		}

		/* Corner at rest that grows or shrinks (mouse or keyboard on the corner). */
		function easeEar(to, ms) {
			cancelAnimationFrame(earRaf);
			earRaf = 0;
			if (reduceMotion || ms <= 0 || Math.abs(to - ear) < 0.5) {
				ear = to;
				redraw();
				return;
			}
			var from = ear;
			var t0 = null;
			function tick(now) {
				if (t0 === null) { t0 = now; }
				var k = Math.min(1, (now - t0) / ms);
				ear = from + (to - from) * easeOut(k);
				redraw();
				if (k < 1) {
					earRaf = requestAnimationFrame(tick);
				} else {
					earRaf = 0;
				}
			}
			earRaf = requestAnimationFrame(tick);
		}

		/* ---------- page state ---------- */

		function clearOne(slide) {
			slide.classList.remove('is-flip', 'is-under');
			slide.style.zIndex = '';
			setClip(slide, '');
		}

		/* Shows only page i, at rest, with the corner folded at rest. */
		function setState(i) {
			slides.forEach(function (slide, k) {
				var on = k === i;
				clearOne(slide);
				slide.classList.toggle('is-current', on);
				slide.setAttribute('aria-hidden', on ? 'false' : 'true');
			});
			current = i;
			view = null;
			renderRest();
		}

		/*
		 * Draws the turn at a given point.
		 * dir = 1: the current page folds to the left and reveals the next one.
		 * dir = -1: the previous page comes back from the left and covers the current one.
		 * p goes from 0 (start) to 1 (end).
		 */
		function frame(dir, target, p) {
			view = { dir: dir, target: target, p: p };
			var a = dir > 0 ? p : 1 - p;                   // how far the foreground page is turned
			var front = dir > 0 ? slides[current] : slides[target];
			var under = dir > 0 ? slides[target] : slides[current];

			slides.forEach(function (slide) {
				if (slide !== front && slide !== under) { clearOne(slide); }
			});

			under.classList.remove('is-flip');
			under.classList.add('is-under');
			front.classList.remove('is-under');
			front.classList.add('is-flip');

			drawFold(folds[1], under, 0, 1);
			drawFold(folds[0], front, a, 4);
		}

		function tween(from, to, ms, easing, step, done) {
			var t0 = null;
			cancelAnimationFrame(raf);
			function tick(now) {
				if (t0 === null) { t0 = now; }
				var k = ms <= 0 ? 1 : Math.min(1, (now - t0) / ms);
				step(from + (to - from) * easing(k));
				if (k < 1) {
					raf = requestAnimationFrame(tick);
				} else {
					raf = 0;
					done();
				}
			}
			raf = requestAnimationFrame(tick);
		}

		/* Turns the page all the way, starting from p (0 if it starts from rest). */
		function turn(dir, startP, ms, easing) {
			if (busy) { return; }
			var target = (current + dir + total) % total;
			if (reduceMotion) {
				setState(target);
				sync();
				return;
			}
			busy = true;
			var from = startP || 0;
			frame(dir, target, from);
			tween(from, 1, ms || turnMs, easing || easeInOut, function (p) {
				frame(dir, target, p);
			}, function () {
				setState(target);
				busy = false;
				sync();
			});
		}

		/* Release too short: the page goes back as it was. */
		function snapBack(dir, target, fromP) {
			busy = true;
			tween(fromP, 0, Math.max(160, fromP * 560), easeOut, function (p) {
				frame(dir, target, p);
			}, function () {
				setState(current);
				busy = false;
				sync();
			});
		}

		/* ---------- automatic sliding ---------- */

		function canRun() {
			return autoplay && !held && !focused && inView && !document.hidden && !busy && !drag;
		}

		/* Realigns the timer with the current state; it always restarts from zero. */
		function sync() {
			if (timer) {
				clearTimeout(timer);
				timer = 0;
			}
			if (canRun()) {
				timer = setTimeout(function () {
					timer = 0;
					if (canRun()) { turn(1); }
				}, interval);
			}
			/* While it changes by itself, each page is not announced to screen readers. */
			stage.setAttribute('aria-live', timer ? 'off' : 'polite');
		}

		/* ---------- drag: press and hold, then drag ---------- */

		stage.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse' && e.button !== 0) { return; }
			if (e.target.closest && e.target.closest('.ptfb__sr')) { return; }

			held = true;     // stops at once, even before moving
			sync();
			if (busy || drag) { return; }

			measure();
			drag = {
				id: e.pointerId,
				x0: e.clientX,
				y0: e.clientY,
				lastX: e.clientX,
				lastT: e.timeStamp,
				vx: 0,
				dx: 0,
				p: 0,
				dir: 0,
				target: -1,
				active: false
			};
		});

		stage.addEventListener('pointermove', function (e) {
			if (!drag || e.pointerId !== drag.id) { return; }

			var dx = e.clientX - drag.x0;
			var dy = e.clientY - drag.y0;

			if (!drag.active) {
				if (Math.abs(dx) < DRAG_START || Math.abs(dy) > Math.abs(dx)) { return; }
				drag.active = true;
				stage.classList.add('is-dragging');
				try { stage.setPointerCapture(e.pointerId); } catch (err) { /* not essential */ }
			}

			var dt = e.timeStamp - drag.lastT;
			if (dt > 0) { drag.vx = 0.6 * ((e.clientX - drag.lastX) / dt) + 0.4 * drag.vx; }
			drag.lastX = e.clientX;
			drag.lastT = e.timeStamp;

			var dir = dx < 0 ? 1 : -1;     // left = forward, right = back
			drag.dx = dx;
			drag.p = Math.min(1, Math.abs(dx) / (width * DRAG_RANGE));
			if (dir !== drag.dir) {
				drag.dir = dir;
				drag.target = (current + dir + total) % total;
				slides.forEach(clearOne);   // direction change: start again clean
			}
			if (!reduceMotion) { frame(drag.dir, drag.target, drag.p); }
			e.preventDefault();
		});

		function release(e) {
			if (e.pointerId !== undefined && drag && e.pointerId !== drag.id) { return; }
			var d = drag;
			drag = null;
			held = false;
			stage.classList.remove('is-dragging');
			if (d) {
				try { stage.releasePointerCapture(d.id); } catch (err) { /* ignore */ }
			}

			if (!d || !d.active || d.dir === 0) {
				sync();
				return;
			}

			/* After a drag that started on the corner, the click must not fire as well. */
			suppressClick = true;
			setTimeout(function () { suppressClick = false; }, 60);

			var stillFor = e.timeStamp - d.lastT;
			var speed = stillFor > 120 ? 0 : Math.abs(d.vx);
			var sameWay = d.vx === 0 || (d.vx < 0) === (d.dir > 0);
			var commit = e.type !== 'pointercancel' &&
				(d.p > COMMIT_AT || (speed > FLICK_SPEED && sameWay && Math.abs(d.dx) > 24));

			if (reduceMotion) {
				if (commit) { setState((current + d.dir + total) % total); }
				sync();
			} else if (commit) {
				turn(d.dir, d.p, Math.max(Math.min(240, turnMs), (1 - d.p) * turnMs * 0.85), easeOut);
			} else {
				snapBack(d.dir, d.target, d.p);
			}
		}

		stage.addEventListener('pointerup', release);
		stage.addEventListener('pointercancel', release);
		stage.addEventListener('dragstart', function (e) { e.preventDefault(); });

		/* ---------- folded corner, keyboard and screen reader ---------- */

		if (corner) {
			corner.addEventListener('click', function (e) {
				if (suppressClick) { return; }
				/* Click with mouse or finger (detail > 0): the corner must not stay "selected". With the keyboard (detail 0) the focus stays. */
				if (e.detail > 0) {
					try { corner.blur(); } catch (err) { /* ignore */ }
				}
				turn(1);
			});
			corner.addEventListener('pointerenter', function (e) {
				if (e.pointerType !== 'mouse') { return; }
				cornerHover = true;
				easeEar(earTarget(), EAR_MS);
			});
			corner.addEventListener('pointerleave', function () {
				cornerHover = false;
				easeEar(earTarget(), EAR_MS);
			});
		}
		if (prevBtn) { prevBtn.addEventListener('click', function () { turn(-1); }); }

		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				turn(-1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				turn(1);
			}
		});

		/* Focus stops the sliding only if it comes from the keyboard: a click must not stop it forever. */
		root.addEventListener('focusin', function (e) {
			var visible = false;
			try {
				visible = !!(e.target.matches && e.target.matches(':focus-visible'));
			} catch (err) {
				visible = true;
			}
			focused = usingKeyboard || visible;
			if (corner && e.target === corner) {
				/* The corner focus indicator (bigger corner + outline) only when the keyboard is used. */
				cornerFocus = usingKeyboard;
				stage.classList.toggle('is-kbd-corner', cornerFocus);
				easeEar(earTarget(), EAR_MS);
			}
			sync();
		});
		root.addEventListener('focusout', function (e) {
			if (corner && e.target === corner) {
				cornerFocus = false;
				stage.classList.remove('is-kbd-corner');
				easeEar(earTarget(), EAR_MS);
			}
			if (!e.relatedTarget || !root.contains(e.relatedTarget)) {
				focused = false;
				sync();
			}
		});

		/* Slides only when the section is visible and the browser tab is in the foreground. */
		document.addEventListener('visibilitychange', sync);
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				inView = entries[0].isIntersecting;
				sync();
			}, { threshold: 0.2 }).observe(root);
		}

		/* The fold depends on the card size: it is redrawn if the size changes (window, loaded fonts...). */
		function resized() {
			measure();
			redraw();
		}
		if ('ResizeObserver' in window) {
			new ResizeObserver(resized).observe(stage);
		} else {
			window.addEventListener('resize', resized);
		}

		measure();
		setState(0);
		easeEar(earRest, 450);   // the corner appears by folding
		sync();
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.ptfb'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
