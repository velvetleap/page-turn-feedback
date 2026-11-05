/*
 * Page Turn Feedback – nessuna dipendenza.
 * Copyright (C) 2026 Mattia Scudellaro - GPL-2.0-or-later
 *
 * La scheda gira pagina come un libro, con una vera piega: un angolo in basso a destra
 * è già piegato, e girando pagina la piega attraversa la scheda da destra a sinistra
 * scoprendo il feedback successivo (il retro della pagina ripiegata resta visibile
 * finché la pagina non esce a sinistra).
 *
 * Si gira pagina:
 *  - da soli, dopo il tempo scelto nelle impostazioni;
 *  - toccando/cliccando l'angolo piegato (o trascinandolo);
 *  - tenendo premuto sul testo e trascinando a sinistra (avanti) o a destra (indietro).
 * Finché si tiene premuto lo scorrimento automatico è fermo; al rilascio riparte.
 *
 * Geometria: l'angolo C (basso a destra) viene "tirato" fino a un punto P. La piega è
 * l'asse del segmento C-P; la parte di pagina oltre la piega si ribalta specchiandosi
 * rispetto all'asse. Il percorso di P è calcolato in path().
 */
(function () {
	'use strict';

	var TURN_MS = 1100;       // durata predefinita del giro pagina (si cambia da Impostazioni: attributo data-turn)
	var DRAG_START = 6;      // pixel prima che il movimento conti come trascinamento
	var DRAG_RANGE = 0.85;    // quota di larghezza da trascinare per girare tutta la pagina
	var COMMIT_AT = 0.28;     // quota di giro oltre cui, al rilascio, la pagina si gira
	var FLICK_SPEED = 0.5;    // px/ms: uno "scatto" veloce gira pagina anche se breve
	var LIFT = 0.42;          // quanto si solleva l'angolo durante il giro (in altezze della scheda)
	var EAR_MS = 200;         // animazione dell'angolo quando ci si passa sopra

	function easeInOut(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
	function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

	/*
	 * Ultimo modo con cui si usa la pagina: tastiera o puntatore (mouse/dito).
	 * Serve a mostrare l'indicatore di focus dell'angolo solo a chi naviga con Tab:
	 * dopo un clic non deve restare nessun contorno sopra l'angolo.
	 */
	var usingKeyboard = false;
	document.addEventListener('keydown', function (e) {
		var k = e.key || '';
		if (k === 'Tab' || k === 'Enter' || k === ' ' || k.indexOf('Arrow') === 0) { usingKeyboard = true; }
	}, true);
	document.addEventListener('pointerdown', function () { usingKeyboard = false; }, true);

	/* Titoli e testi usano i font del tema: li leggiamo da un elemento di prova. */
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

	/* Ritaglia un poligono tenendo i punti con sign*((p-M)·n) >= 0 (Sutherland–Hodgman). */
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
		/* Durata del giro pagina scelta nelle Impostazioni (tra 0,3 e 5 secondi). */
		var turnMs = Math.max(300, Math.min(5000, parseInt(root.getAttribute('data-turn'), 10) || TURN_MS));
		var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
		var autoplay = interval > 0 && !reduceMotion;

		var current = 0;
		var busy = false;       // giro in corso (automatico o completamento dopo il rilascio)
		var drag = null;        // trascinamento in corso
		var raf = 0;
		var timer = 0;
		var held = false;       // dito/mouse premuto
		var focused = false;    // focus da tastiera dentro la sezione
		var inView = true;
		var suppressClick = false;
		var width = 1;
		var height = 1;

		/* Angolo piegato a riposo: dimensione attuale (ear) e dimensioni a riposo / con il mouse sopra. */
		var ear = 0;
		var earRest = 32;
		var earBig = 50;
		var earRaf = 0;
		var cornerHover = false;
		var cornerFocus = false;

		var view = null;        // ultimo fotogramma disegnato durante un giro, null = pagina ferma

		/* Due livelli di piega: [0] pagina in primo piano, [1] pagina sotto (con il suo angolo). */
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

		/* ---------- geometria della piega ---------- */

		/*
		 * Posizione del punto P a cui è tirato l'angolo, con a da 0 (angolo piegato a riposo)
		 * a 1 (pagina completamente girata, fuori dalla scheda a sinistra).
		 * La piega è l'asse di C-P: sale con P, quindi risulta inclinata a metà giro e verticale alla fine.
		 */
		function path(a, d) {
			var ext = width * 0.08;
			return [
				(width - d) - a * (2 * width - d + ext),
				(height - d) + a * d - LIFT * height * Math.sin(Math.PI * a)
			];
		}

		/* Gradiente lineare con direzione (ux,uy) e stop in px a partire dalla piega (passante per mx,my). */
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
		 * Disegna la pagina "slide" piegata al punto a, con i suoi strati di ombra.
		 * base: livello (z-index) di partenza dello strato.
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
			var nx = dx / len;                 // dalla parte di P verso l'angolo C
			var ny = dy / len;
			var mx = (cx + p[0]) / 2;          // un punto della piega
			var my = (cy + p[1]) / 2;
			var half = len / 2;                // distanza tra la piega e P

			var rect = [[0, 0], [width, 0], [width, height], [0, height]];
			var kept = clipHalf(rect, mx, my, nx, ny, -1);     // parte di pagina non piegata
			var away = clipHalf(rect, mx, my, nx, ny, 1);      // parte piegata: qui si vede la pagina sotto
			var flap = away.map(function (q) {                 // la stessa parte, ribaltata sulla pagina
				var s = (q[0] - mx) * nx + (q[1] - my) * ny;
				return [q[0] - 2 * s * nx, q[1] - 2 * s * ny];
			});

			var wave = Math.sin(Math.PI * a);

			setClip(slide, polyValue(kept));

			/* Ombra della pagina sulla pagina sotto, appena oltre la piega. */
			f.cast.classList.add('is-on');
			setClip(f.cast, polyValue(away));
			f.cast.style.backgroundImage = gradient(nx, ny, mx, my, [
				['rgba(0,0,0,' + (0.12 + 0.12 * wave).toFixed(3) + ')', 0],
				['rgba(0,0,0,0)', 16 + 46 * wave]
			]);

			/* Retro della pagina ripiegata: più scuro vicino alla piega. */
			f.wrap.classList.add('is-on');
			setClip(f.flap, polyValue(flap));
			/* Come una pagina che si incurva: ombra stretta sulla piega, poi un riflesso chiaro che sfuma. */
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

		/* Angolo a riposo che cresce o si riduce (mouse o tastiera sopra l'angolo). */
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

		/* ---------- stato delle pagine ---------- */

		function clearOne(slide) {
			slide.classList.remove('is-flip', 'is-under');
			slide.style.zIndex = '';
			setClip(slide, '');
		}

		/* Mostra solo la pagina i, ferma, con l'angolo piegato a riposo. */
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
		 * Disegna il giro a un certo punto.
		 * dir = 1: la pagina corrente si piega verso sinistra e scopre la successiva.
		 * dir = -1: la pagina precedente rientra da sinistra e copre la corrente.
		 * p va da 0 (inizio) a 1 (fine).
		 */
		function frame(dir, target, p) {
			view = { dir: dir, target: target, p: p };
			var a = dir > 0 ? p : 1 - p;                   // quanto è girata la pagina in primo piano
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

		/* Gira pagina fino in fondo, partendo da p (0 se parte da ferma). */
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

		/* Rilascio troppo corto: la pagina torna com'era. */
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

		/* ---------- scorrimento automatico ---------- */

		function canRun() {
			return autoplay && !held && !focused && inView && !document.hidden && !busy && !drag;
		}

		/* Riallinea il timer allo stato attuale; riparte sempre da zero. */
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
			/* Mentre cambia da solo non si annuncia ogni pagina agli screen reader. */
			stage.setAttribute('aria-live', timer ? 'off' : 'polite');
		}

		/* ---------- trascinamento: tieni premuto e trascina ---------- */

		stage.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse' && e.button !== 0) { return; }
			if (e.target.closest && e.target.closest('.ptfb__sr')) { return; }

			held = true;     // si ferma subito, anche prima di muoversi
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
				try { stage.setPointerCapture(e.pointerId); } catch (err) { /* non indispensabile */ }
			}

			var dt = e.timeStamp - drag.lastT;
			if (dt > 0) { drag.vx = 0.6 * ((e.clientX - drag.lastX) / dt) + 0.4 * drag.vx; }
			drag.lastX = e.clientX;
			drag.lastT = e.timeStamp;

			var dir = dx < 0 ? 1 : -1;     // a sinistra = avanti, a destra = indietro
			drag.dx = dx;
			drag.p = Math.min(1, Math.abs(dx) / (width * DRAG_RANGE));
			if (dir !== drag.dir) {
				drag.dir = dir;
				drag.target = (current + dir + total) % total;
				slides.forEach(clearOne);   // cambio di verso: si riparte puliti
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
				try { stage.releasePointerCapture(d.id); } catch (err) { /* ignora */ }
			}

			if (!d || !d.active || d.dir === 0) {
				sync();
				return;
			}

			/* Dopo un trascinamento iniziato sull'angolo non deve partire anche il clic. */
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

		/* ---------- angolo piegato, tastiera e screen reader ---------- */

		if (corner) {
			corner.addEventListener('click', function (e) {
				if (suppressClick) { return; }
				/* Clic con mouse o dito (detail > 0): l'angolo non deve restare "selezionato". Da tastiera (detail 0) il focus resta. */
				if (e.detail > 0) {
					try { corner.blur(); } catch (err) { /* ignora */ }
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

		/* Il focus ferma lo scorrimento solo se arriva dalla tastiera: un clic non lo ferma per sempre. */
		root.addEventListener('focusin', function (e) {
			var visible = false;
			try {
				visible = !!(e.target.matches && e.target.matches(':focus-visible'));
			} catch (err) {
				visible = true;
			}
			focused = usingKeyboard || visible;
			if (corner && e.target === corner) {
				/* L'indicatore di focus dell'angolo (angolo più grande + contorno) solo se si usa la tastiera. */
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

		/* Scorre solo quando la sezione si vede e la scheda del browser è in primo piano. */
		document.addEventListener('visibilitychange', sync);
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				inView = entries[0].isIntersecting;
				sync();
			}, { threshold: 0.2 }).observe(root);
		}

		/* La piega dipende dalle misure della scheda: si ridisegna se cambiano (finestra, font caricati...). */
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
		easeEar(earRest, 450);   // l'angolo compare piegandosi
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
