from playwright.sync_api import sync_playwright

import os
S = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out")  # pages written by make_pages.sh
ok = True
errs = []


def check(label, cond, extra=""):
    global ok
    print(("PASS " if cond else "FAIL ") + label, extra)
    ok = ok and bool(cond)


def cur(pg):
    return pg.evaluate("[...document.querySelectorAll('.ptfb__slide')].findIndex(s=>s.classList.contains('is-current'))")


def leftovers(pg):
    """Stili temporanei rimasti dopo un giro: classi di giro, stili sulle pagine non correnti, strato 'sotto' acceso."""
    return pg.evaluate("""(()=>{
        const sl=[...document.querySelectorAll('.ptfb__slide')];
        let n=sl.filter(s=>s.classList.contains('is-flip')||s.classList.contains('is-under')||s.style.transform||s.style.boxShadow).length;
        n+=sl.filter(s=>!s.classList.contains('is-current') && (s.style.zIndex||s.style.clipPath)).length;
        if(document.querySelector('.ptfb__flapwrap')) n+=Math.abs(document.querySelectorAll('.ptfb__flapwrap.is-on').length-1);
        return n})()""")


def poly(pg, sel):
    """Poligono clip-path di un elemento come lista di (x,y)."""
    v = pg.evaluate("(s)=>{const e=document.querySelector(s);return e?e.style.clipPath:''}", sel)
    import re
    return [(float(a), float(b)) for a, b in re.findall(r"(-?[\d.]+)px (-?[\d.]+)px", v)]


def area(pts):
    if len(pts) < 3:
        return 0.0
    return abs(sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))) / 2


def ear(pg):
    """Lato dell'angolo piegato a riposo: spazio tolto alla pagina corrente sul bordo basso."""
    pts = poly(pg, ".ptfb__slide.is-current")
    W = pg.evaluate("document.querySelector('.ptfb__stage').clientWidth")
    H = pg.evaluate("document.querySelector('.ptfb__stage').clientHeight")
    bottom = [x for x, y in pts if abs(y - H) < 0.6]
    return round(W - max(bottom), 1) if bottom else 0


def visible_count(pg):
    return pg.evaluate("[...document.querySelectorAll('.ptfb__slide')].filter(s=>getComputedStyle(s).visibility==='visible').length")


def flipping(pg):
    return pg.evaluate("document.querySelectorAll('.ptfb__slide.is-flip').length")


def open_page(b, name, w=1400, h=900, ctx=None):
    pg = ctx.new_page() if ctx else b.new_page(viewport={"width": w, "height": h})
    pg.on("pageerror", lambda e: errs.append(str(e)))
    pg.on("console", lambda m: errs.append(m.text) if m.type == "error" else None)
    pg.goto(f"file://{S}/{name}")
    pg.evaluate("document.querySelector('.ptfb').scrollIntoView({block:'center'})")
    pg.wait_for_timeout(250)
    return pg


def drag(pg, frac, slow=True, hold=0, release=True):
    """frac < 0: trascina a sinistra (avanti); frac > 0: a destra (indietro). Frazione della larghezza della scheda."""
    box = pg.locator(".ptfb__stage").bounding_box()
    W = box["width"]
    y = box["y"] + box["height"] / 2
    x0 = box["x"] + (W * 0.75 if frac < 0 else W * 0.25)
    pg.mouse.move(x0, y)
    pg.mouse.down()
    n = 6
    for i in range(1, n + 1):
        pg.mouse.move(x0 + W * frac * i / n, y)
        if slow:
            pg.wait_for_timeout(70)
    if hold:
        pg.wait_for_timeout(hold)
    if release:
        pg.mouse.up()


with sync_playwright() as p:
    b = p.chromium.launch()

    # ------------------------------------------------------------------ 1. stato iniziale
    print("--- 1. stato iniziale")
    pg = open_page(b, "p_5_slow.html")
    check("parte dalla prima pagina", cur(pg) == 0)
    check("una sola pagina visibile", visible_count(pg) == 1)
    check("le altre 4 sono aria-hidden", pg.evaluate("document.querySelectorAll('.ptfb__slide[aria-hidden=true]').length") == 4)
    check("scheda interattiva (cursore grab)", pg.evaluate("document.querySelector('.ptfb__stage').classList.contains('is-interactive')"))
    check("nessun pallino, numero, freccia o pausa visibili",
          pg.evaluate("""[...document.querySelectorAll('.ptfb button:not(.ptfb__corner)')].every(b=>b.getBoundingClientRect().width<=1)""")
          and pg.locator(".ptfb__dot, .ptfb__toggle, .ptfb__count, .ptfb__nav").count() == 0)
    fam = pg.evaluate("""({t:getComputedStyle(document.querySelector('.ptfb__title')).fontFamily,
        a:getComputedStyle(document.querySelector('.ptfb__slide .ptfb__author')).fontFamily,
        q:getComputedStyle(document.querySelector('.ptfb__quote')).fontFamily,
        body:getComputedStyle(document.body).fontFamily})""")
    check("nome nel font dei titoli del tema", fam["a"] == fam["t"] and "Oswald" in fam["a"], fam["a"])
    check("commento nel font del testo del tema", fam["q"] == fam["body"] and "Poppins" in fam["q"], fam["q"])
    hs = pg.evaluate("[...document.querySelectorAll('.ptfb__slide')].map(s=>Math.round(s.getBoundingClientRect().height))")
    sh = pg.evaluate("Math.round(document.querySelector('.ptfb__stage').clientHeight)")
    check("tutte le pagine hanno la stessa altezza (niente salti)", len(set(hs)) == 1, str(hs))
    al = pg.evaluate("""[...document.querySelectorAll('.ptfb__slide')].map(s=>{const q=s.querySelector('.ptfb__quote').getBoundingClientRect().left,a=s.querySelector('.ptfb__author').getBoundingClientRect().left;return Math.round(a-q)})""")
    check("il nome e' allineato al commento in ogni pagina (desktop)", all(abs(v) <= 1 for v in al) , str(al))
    check("la virgoletta non esce dalla scheda", pg.evaluate("""[...document.querySelectorAll('.ptfb__slide')].every(s=>{const st=s.getBoundingClientRect(),m=s.querySelector('.ptfb__mark').getBoundingClientRect();return m.bottom<=st.bottom && m.top>=st.top})"""))
    check("niente piu' rotazione 3D (nessuna prospettiva)", pg.evaluate("document.querySelector('.ptfb__stage').style.perspective")=="" and pg.evaluate("[...document.querySelectorAll('.ptfb__slide')].every(s=>!s.style.transform)"))
    pg.wait_for_timeout(600)
    e0 = ear(pg)
    check("c'e' un angolo piegato a riposo (24-40 px)", 24 <= e0 <= 40, str(e0))
    cb = pg.evaluate("(()=>{const c=document.querySelector('.ptfb__corner').getBoundingClientRect(),s=document.querySelector('.ptfb__stage').getBoundingClientRect();return {w:c.width,dr:Math.round(s.right-1-c.right),db:Math.round(s.bottom-1-c.bottom)}})()")
    check("il pulsante-angolo sta in basso a destra e si vede", cb["w"] > 40 and abs(cb["dr"]) <= 1 and abs(cb["db"]) <= 1, str(cb))
    check("l'angolo e' trasparente (nessuno stile del tema sul pulsante)", pg.evaluate("(()=>{const c=getComputedStyle(document.querySelector('.ptfb__corner'));return c.backgroundColor==='rgba(0, 0, 0, 0)'&&c.borderTopWidth==='0px'})()"))
    check("aria-label 'Next feedback' sull'angolo", pg.evaluate("document.querySelector('.ptfb__corner').getAttribute('aria-label')")=="Next feedback")
    check("a riposo e' acceso un solo strato di piega", leftovers(pg) == 0)
    pg.close()

    # ------------------------------------------------------------------ 2. scorrimento automatico
    print("--- 2. scorrimento automatico (1 s)")
    pg = b.new_page(viewport={"width": 1400, "height": 900})
    pg.on("pageerror", lambda e: errs.append(str(e)))
    pg.goto(f"file://{S}/p_5_1000.html")
    pg.evaluate("document.querySelector('.ptfb').scrollIntoView({block:'center'})")
    t0 = pg.evaluate("performance.now()")
    pg.wait_for_timeout(500)
    check("dopo 0,5 s e' ancora la prima", cur(pg) == 0)
    seen = False
    for _ in range(40):
        if flipping(pg) and pg.evaluate("document.querySelectorAll('.ptfb__slide.is-under').length") == 1:
            seen = True
            break
        pg.wait_for_timeout(50)
    check("durante il cambio c'e' una pagina che gira sopra la successiva", seen)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=4000)
    el = (pg.evaluate("performance.now()") - t0) / 1000
    check("passa alla seconda da sola (~1 s di attesa + giro)", 1.5 <= el <= 3.3, f"{el:.2f}s")
    check("finito il giro non restano classi o stili temporanei", leftovers(pg) == 0)
    check("una sola pagina visibile a riposo", visible_count(pg) == 1)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[2].classList.contains('is-current')", timeout=4500)
    check("poi passa alla terza", cur(pg) == 2)
    pg.close()

    # ------------------------------------------------------------------ 3. tenere premuto ferma, rilasciare riprende
    print("--- 3. tieni premuto = pausa; rilascia = riparte")
    pg = open_page(b, "p_5_1000.html")
    box = pg.locator(".ptfb__stage").bounding_box()
    pg.mouse.move(box["x"] + 400, box["y"] + 100)
    pg.mouse.down()
    pg.wait_for_timeout(3200)
    check("tenendo premuto per 3 s non cambia pagina", cur(pg) == 0 and flipping(pg) == 0)
    check("aria-live passa a polite quando e' fermo", pg.evaluate("document.querySelector('.ptfb__stage').getAttribute('aria-live')") == "polite")
    pg.mouse.up()
    pg.wait_for_timeout(300)
    check("subito dopo il rilascio non salta (il conteggio riparte da zero)", cur(pg) == 0)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=4500)
    check("dopo il rilascio lo scorrimento riprende", cur(pg) == 1)
    pg.close()

    # ------------------------------------------------------------------ 4. trascinamento
    print("--- 4. trascinamento (mouse)")
    pg = open_page(b, "p_5_slow.html")
    drag(pg, -0.45)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    check("trascinando a sinistra: pagina successiva", cur(pg) == 1)
    pg.wait_for_timeout(200)
    check("   senza resti di stile", leftovers(pg) == 0 and visible_count(pg) == 1)
    drag(pg, +0.45)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[0].classList.contains('is-current')", timeout=3000)
    check("trascinando a destra: pagina precedente", cur(pg) == 0)
    pg.wait_for_timeout(200)
    drag(pg, +0.45)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[4].classList.contains('is-current')", timeout=3000)
    check("dalla prima, a destra: si torna all'ultima (giro completo)", cur(pg) == 4)
    pg.wait_for_timeout(200)
    drag(pg, -0.45)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[0].classList.contains('is-current')", timeout=3000)
    check("dall'ultima, a sinistra: si torna alla prima", cur(pg) == 0)
    pg.wait_for_timeout(200)

    # trascinamento breve e lento: la pagina torna com'era
    box = pg.locator(".ptfb__stage").bounding_box()
    y = box["y"] + box["height"] / 2
    x0 = box["x"] + box["width"] * 0.7
    pg.mouse.move(x0, y); pg.mouse.down()
    for dx in (-20, -40, -60):
        pg.mouse.move(x0 + dx, y); pg.wait_for_timeout(90)
    pg.wait_for_timeout(260)
    midflip = flipping(pg) == 1
    pg.mouse.up()
    pg.wait_for_timeout(900)
    check("durante il trascinamento la pagina e' in movimento", midflip)
    check("trascinamento breve e lento: resta sulla stessa pagina", cur(pg) == 0)
    check("   e torna perfettamente piatta", leftovers(pg) == 0 and visible_count(pg) == 1)

    # scatto veloce: gira anche se corto
    x0 = box["x"] + box["width"] * 0.7
    pg.mouse.move(x0, y); pg.mouse.down()
    pg.mouse.move(x0 - 45, y); pg.wait_for_timeout(15)
    pg.mouse.move(x0 - 110, y); pg.wait_for_timeout(15)
    pg.mouse.up()
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    check("scatto veloce e corto: gira pagina", cur(pg) == 1)
    pg.wait_for_timeout(300)

    # cambio di verso a meta' trascinamento
    box = pg.locator(".ptfb__stage").bounding_box()
    x0 = box["x"] + box["width"] * 0.5
    pg.mouse.move(x0, y); pg.mouse.down()
    pg.mouse.move(x0 - 200, y, steps=5); pg.wait_for_timeout(60)
    pg.mouse.move(x0 + 200, y, steps=8); pg.wait_for_timeout(60)
    state = pg.evaluate("[...document.querySelectorAll('.ptfb__slide')].map(s=>s.classList.contains('is-flip')?'flip':(s.classList.contains('is-under')?'under':'-'))")
    check("cambiando verso a meta' compaiono le pagine giuste (corrente sotto, precedente sopra)", state[0] == "flip" and state[1] == "under", str(state))
    pg.mouse.move(x0, y, steps=6); pg.wait_for_timeout(200); pg.mouse.up(); pg.wait_for_timeout(800)
    check("   rilasciando al centro non cambia nulla", cur(pg) == 1 and leftovers(pg) == 0)

    # scorrimento verticale: un tocco che scorre in verticale non gira pagina
    pg.mouse.move(x0, y); pg.mouse.down(); pg.mouse.move(x0 + 8, y + 80, steps=6); pg.mouse.up(); pg.wait_for_timeout(300)
    check("un movimento verticale non gira pagina", cur(pg) == 1 and leftovers(pg) == 0)
    pg.close()

    # ------------------------------------------------------------------ 4b. geometria della piega
    print("--- 4b. geometria della piega")
    pg = open_page(b, "p_5_slow.html")
    box = pg.locator(".ptfb__stage").bounding_box()
    Wc = pg.evaluate("document.querySelector('.ptfb__stage').clientWidth")
    Hc = pg.evaluate("document.querySelector('.ptfb__stage').clientHeight")
    y = box["y"] + box["height"] * 0.4
    x0 = box["x"] + box["width"] * 0.8
    pg.mouse.move(x0, y); pg.mouse.down()
    bad = []
    for frac in (0.05, 0.15, 0.3, 0.45, 0.6, 0.75, 0.9):
        pg.mouse.move(x0 - box["width"] * 0.85 * frac, y, steps=3)
        pg.wait_for_timeout(40)
        A = area(poly(pg, ".ptfb__slide.is-flip"))
        Bk = area(poly(pg, ".ptfb__cast.is-on"))
        # il poligono dello strato di piega 0 e' il secondo .ptfb__flap
        F = 0
        for el in range(2):
            pass
        Fp = pg.evaluate("""(()=>{const w=[...document.querySelectorAll('.ptfb__flapwrap')].filter(e=>e.classList.contains('is-on'));
            const z=w.sort((a,b)=>b.style.zIndex-a.style.zIndex)[0];return z.firstElementChild.style.clipPath})()""")
        import re
        fpts = [(float(a), float(c)) for a, c in re.findall(r"(-?[\d.]+)px (-?[\d.]+)px", Fp)]
        castp = pg.evaluate("""(()=>{const w=[...document.querySelectorAll('.ptfb__cast.is-on')].sort((a,b)=>b.style.zIndex-a.style.zIndex)[0];return w.style.clipPath})()""")
        cpts = [(float(a), float(c)) for a, c in re.findall(r"(-?[\d.]+)px (-?[\d.]+)px", castp)]
        Ac, Bc, Fc = A, area(cpts), area(fpts)
        if abs(Ac + Bc - Wc * Hc) > Wc * Hc * 0.004 or abs(Fc - Bc) > Bc * 0.01 + 1:
            bad.append((frac, round(Ac), round(Bc), round(Fc)))
    check("in ogni punto: pagina intera = parte non piegata + parte piegata, e il retro ha la stessa area", not bad, str(bad))
    pg.mouse.move(x0 - box["width"] * 0.95, y, steps=3); pg.wait_for_timeout(60)
    A = area(poly(pg, ".ptfb__slide.is-flip"))
    check("trascinando fino in fondo la pagina e' tutta piegata via (nessuna parte resta)", A < 1, str(round(A, 1)))
    pg.mouse.up(); pg.wait_for_timeout(900)
    check("   e a fine giro e' la pagina 2 con l'angolo piegato uguale a prima", cur(pg) == 1 and abs(ear(pg) - e0) < 0.3 and leftovers(pg) == 0, f"{ear(pg)} vs {e0}")
    # trascinamento a destra: la pagina corrente tiene il proprio angolo mentre entra la precedente
    x1 = box["x"] + box["width"] * 0.25
    pg.mouse.move(x1, y); pg.mouse.down(); pg.mouse.move(x1 + box["width"] * 0.3, y, steps=6); pg.wait_for_timeout(60)
    check("trascinando a destra: due strati di piega accesi (la pagina sotto tiene l'angolo)", pg.evaluate("document.querySelectorAll('.ptfb__flapwrap.is-on').length") == 2)
    pg.mouse.up(); pg.wait_for_timeout(1000)
    check("   rilasciando si torna alla pagina 1, angolo regolare", cur(pg) == 0 and abs(ear(pg) - e0) < 0.3 and leftovers(pg) == 0)
    pg.close()

    # ------------------------------------------------------------------ 4c. angolo piegato
    print("--- 4c. angolo piegato (clic, mouse sopra, trascinamento)")
    pg = open_page(b, "p_5_slow.html")
    box = pg.locator(".ptfb__stage").bounding_box()
    cx, cy = box["x"] + box["width"] - 14, box["y"] + box["height"] - 14
    pg.wait_for_timeout(600)
    e_rest = ear(pg)
    pg.mouse.move(cx, cy); pg.wait_for_timeout(450)
    e_hover = ear(pg)
    check("mouse sopra l'angolo: l'angolo si allarga", e_hover > e_rest * 1.4, f"{e_rest} -> {e_hover}")
    pg.mouse.move(box["x"] + 300, box["y"] + 100); pg.wait_for_timeout(450)
    check("mouse via dall'angolo: torna com'era", abs(ear(pg) - e_rest) < 1, str(ear(pg)))
    pg.mouse.move(cx, cy); pg.mouse.down(); pg.mouse.up()
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    pg.wait_for_timeout(1300)
    check("clic sull'angolo: pagina successiva (una sola)", cur(pg) == 1 and leftovers(pg) == 0)
    check("   e il mouse e' ancora sopra: l'angolo resta grande", ear(pg) > e_rest * 1.4)
    pg.mouse.move(box["x"] + 300, box["y"] + 100); pg.wait_for_timeout(400)
    # trascinamento che parte dall'angolo
    pg.mouse.move(cx, cy); pg.mouse.down()
    pg.mouse.move(cx - 60, cy - 4, steps=4); pg.wait_for_timeout(50)
    check("trascinando l'angolo: la pagina si piega", flipping(pg) == 1)
    pg.mouse.move(cx - box["width"] * 0.6, cy - 6, steps=8); pg.wait_for_timeout(60)
    pg.mouse.up()
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[2].classList.contains('is-current')", timeout=3000)
    pg.wait_for_timeout(1300)
    check("   rilasciando oltre meta': pagina successiva (e il clic finale non gira una seconda volta)", cur(pg) == 2 and leftovers(pg) == 0)
    # trascinamento corto dall'angolo: torna indietro e non conta come clic
    pg.mouse.move(cx, cy); pg.mouse.down()
    pg.mouse.move(cx - 30, cy - 3, steps=4); pg.wait_for_timeout(300)
    pg.mouse.up(); pg.wait_for_timeout(900)
    check("trascinamento corto dall'angolo: resta sulla stessa pagina", cur(pg) == 2 and leftovers(pg) == 0)
    pg.close()

    # ------------------------------------------------------------------ 5. dopo il trascinamento riparte
    print("--- 5. dopo un trascinamento lo scorrimento riparte da solo")
    pg = open_page(b, "p_5_1000.html")
    drag(pg, -0.08, hold=2400, release=False)
    check("tenendo premuto dopo aver trascinato un po': nessun cambio automatico", cur(pg) == 0)
    pg.mouse.up()
    pg.wait_for_timeout(900)
    check("rilasciato un trascinamento troppo corto: torna alla stessa pagina", cur(pg) == 0 and leftovers(pg) == 0)
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=4500)
    check("poi riparte lo scorrimento automatico", cur(pg) == 1)
    pg.close()

    # ------------------------------------------------------------------ 6. tastiera
    print("--- 6. tastiera e screen reader")
    pg = open_page(b, "p_5_1000.html")
    pg.keyboard.press("Tab")
    act = pg.evaluate("document.activeElement.className")
    check("il primo Tab arriva sul comando 'precedente'", "ptfb__sr-prev" in act, act)
    w = pg.evaluate("document.activeElement.getBoundingClientRect().width")
    check("   che compare a vista solo quando ha il focus", w > 60, f"{w:.0f}px")
    pg.screenshot(path=f"{S}/k_focus.png", clip=dict(zip(("x", "y", "width", "height"), (0, 0, 1400, 900))))
    pg.wait_for_timeout(2600)
    check("con il focus da tastiera lo scorrimento e' fermo", cur(pg) == 0)
    pg.keyboard.press("ArrowRight")
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    check("freccia destra: avanti", cur(pg) == 1)
    pg.keyboard.press("ArrowLeft")
    pg.wait_for_timeout(1300)
    check("freccia sinistra: indietro", cur(pg) == 0)
    pg.keyboard.press("Tab")
    act = pg.evaluate("document.activeElement.className")
    check("il secondo Tab arriva sull'angolo piegato", "ptfb__corner" in act, act)
    pg.wait_for_timeout(350)
    check("   con l'angolo che si allarga e un contorno a forma di angolo (niente riquadro nero)", pg.evaluate("document.querySelector('.ptfb__stage').classList.contains('is-kbd-corner')") and pg.evaluate("getComputedStyle(document.activeElement).outlineStyle")=="none" and ear(pg) > 48, str(ear(pg)))
    check("   il contorno e' sullo strato dell'angolo (filtro con il colore del focus)", "drop-shadow" in pg.evaluate("getComputedStyle(document.querySelector('.ptfb__flapwrap.is-on')).filter") and "34, 34, 34" in pg.evaluate("getComputedStyle(document.querySelector('.ptfb__flapwrap.is-on')).filter"))
    pg.screenshot(path=f"{S}/k_corner_focus.png", clip=dict(zip(("x", "y", "width", "height"), (0, 0, 1400, 900))))
    pg.keyboard.press("Enter")
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    check("Tab + Invio sull'angolo: avanti", cur(pg) == 1)
    pg.evaluate("document.activeElement.blur()")
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[2].classList.contains('is-current')", timeout=5000)
    check("tolto il focus lo scorrimento riprende", cur(pg) == 2)
    pg.close()

    # ------------------------------------------------------------------ 7. tocco (touch) simulato
    print("--- 7. tocco su telefono (eventi touch)")
    pg = open_page(b, "p_5_slow.html", w=375, h=800)
    pg.evaluate("""()=>{const v=document.querySelector('.ptfb__stage');const r=v.getBoundingClientRect();
      const ev=(t,x,y)=>v.dispatchEvent(new PointerEvent(t,{pointerType:'touch',pointerId:7,isPrimary:true,clientX:x,clientY:y,bubbles:true,cancelable:true}));
      const y=r.top+r.height/2; let x=r.left+r.width*0.8; ev('pointerdown',x,y);
      for(let i=1;i<=8;i++){ev('pointermove',x-i*22,y);} ev('pointerup',x-176,y);}""")
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    check("swipe a sinistra col dito: avanti", cur(pg) == 1)
    pg.wait_for_timeout(250)
    check("nessuno scroll orizzontale sul telefono", pg.evaluate("document.documentElement.scrollWidth<=window.innerWidth"))
    check("virgoletta sopra il testo sul telefono", pg.evaluate("getComputedStyle(document.querySelector('.ptfb__mark')).position")=="static")
    check("   e il nome e' allineato al commento", pg.evaluate("(()=>{const s=document.querySelector('.ptfb__slide.is-current');return Math.abs(s.querySelector('.ptfb__quote').getBoundingClientRect().left-s.querySelector('.ptfb__author').getBoundingClientRect().left)<1})()"))
    band = pg.locator(".ptfb").bounding_box()
    pg.screenshot(path=f"{S}/m_rest.png", clip={"x": 0, "y": band["y"], "width": 375, "height": band["height"]})
    box = pg.locator(".ptfb__stage").bounding_box()
    y = box["y"] + box["height"] / 2
    pg.mouse.move(box["x"] + box["width"] * 0.8, y); pg.mouse.down()
    pg.mouse.move(box["x"] + box["width"] * 0.8 - box["width"] * 0.3, y, steps=8); pg.wait_for_timeout(120)
    pg.screenshot(path=f"{S}/m_flip.png", clip={"x": 0, "y": band["y"], "width": 375, "height": band["height"]})
    pg.mouse.move(box["x"] + box["width"] * 0.8, y, steps=6); pg.mouse.up(); pg.wait_for_timeout(700)
    pg.close()

    # ------------------------------------------------------------------ 8. movimento ridotto
    print("--- 8. 'riduci movimento' del sistema")
    ctx = b.new_context(reduced_motion="reduce", viewport={"width": 1400, "height": 900})
    pg = open_page(b, "p_5_1000.html", ctx=ctx)
    pg.wait_for_timeout(3200)
    check("nessuno scorrimento automatico", cur(pg) == 0)
    drag(pg, -0.45, slow=False)
    pg.wait_for_timeout(120)
    check("il trascinamento cambia pagina all'istante, senza animazione", cur(pg) == 1 and leftovers(pg) == 0)
    pg.keyboard.press("Tab"); pg.keyboard.press("ArrowRight"); pg.wait_for_timeout(120)
    check("la tastiera funziona anche senza animazione", cur(pg) == 2 and leftovers(pg) == 0)
    ctx.close()

    # ------------------------------------------------------------------ 9. un solo feedback
    print("--- 9. un solo feedback")
    pg = open_page(b, "p_1.html")
    check("nessuna interattivita' con un solo feedback",
          not pg.evaluate("document.querySelector('.ptfb__stage').classList.contains('is-interactive')")
          and pg.locator(".ptfb__sr").count() == 0 and pg.locator(".ptfb__corner").count() == 0
          and pg.locator(".ptfb__flapwrap").count() == 0)
    check("la pagina e' visibile", visible_count(pg) == 1)
    drag(pg, -0.45)
    pg.wait_for_timeout(300)
    check("trascinare non fa nulla", cur(pg) == 0 and leftovers(pg) == 0)
    pg.close()

    # ------------------------------------------------------------------ 10. adattamento alla larghezza
    print("--- 10. ridimensionamento")
    pg = open_page(b, "p_5_slow.html")
    p1 = pg.evaluate("document.querySelector('.ptfb__slide.is-current').style.clipPath")
    pg.set_viewport_size({"width": 700, "height": 900}); pg.wait_for_timeout(300)
    p2 = pg.evaluate("document.querySelector('.ptfb__slide.is-current').style.clipPath")
    Wn = pg.evaluate("document.querySelector('.ptfb__stage').clientWidth")
    pts = poly(pg, ".ptfb__slide.is-current")
    check("la piega si ridisegna quando cambia la larghezza", p1 != p2 and max(x for x, y in pts) <= Wn + 0.5, f"W={Wn}")
    pg.close()

    # ------------------------------------------------------------------ 11. dopo un clic col mouse nessun contorno nero
    print("--- 11. nessun contorno sull'angolo dopo un clic")
    pg = open_page(b, "p_5_slow.html")
    box = pg.locator(".ptfb__stage").bounding_box()
    cx, cy = box["x"] + box["width"] - 14, box["y"] + box["height"] - 14
    pg.mouse.move(cx, cy); pg.mouse.down(); pg.mouse.up()
    pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current')", timeout=3000)
    pg.wait_for_timeout(1300)
    pg.mouse.move(box["x"] + 300, box["y"] + 60); pg.wait_for_timeout(500)
    info = pg.evaluate("""(()=>{const c=document.querySelector('.ptfb__corner');const cs=getComputedStyle(c);
        return {active: document.activeElement===c, outline: cs.outlineStyle, kbd: document.querySelector('.ptfb__stage').classList.contains('is-kbd-corner'),
        filter: getComputedStyle(document.querySelector('.ptfb__flapwrap.is-on')).filter}})()""")
    check("dopo il clic: nessun contorno, nessuna classe di focus da tastiera", info["outline"] == "none" and not info["kbd"] and "34, 34, 34" not in info["filter"], str(info))
    check("   e l'angolo e' tornato a riposo", abs(ear(pg) - 40) < 1.5, str(ear(pg)))
    pg.mouse.move(cx, cy); pg.mouse.down(); pg.mouse.move(cx - 80, cy - 3, steps=5); pg.mouse.up(); pg.wait_for_timeout(1500)
    info = pg.evaluate("document.querySelector('.ptfb__stage').classList.contains('is-kbd-corner')")
    check("dopo un trascinamento dall'angolo: nessun contorno", not info)
    pg.close()
    # tastiera, poi mouse: il contorno sparisce appena si usa il mouse
    pg = open_page(b, "p_5_slow.html")
    pg.keyboard.press("Tab"); pg.keyboard.press("Tab")
    pg.wait_for_timeout(300)
    kb = pg.evaluate("document.querySelector('.ptfb__stage').classList.contains('is-kbd-corner')")
    pg.mouse.click(box["x"] + 300, box["y"] + 100)
    pg.wait_for_timeout(300)
    after = pg.evaluate("document.querySelector('.ptfb__stage').classList.contains('is-kbd-corner')")
    check("con Tab il contorno compare; cliccando altrove sparisce", kb and not after, f"{kb} {after}")
    pg.close()

    # ------------------------------------------------------------------ 12. durata del giro pagina (impostazione)
    print("--- 12. durata del giro pagina")
    def turn_duration(name):
        pg = open_page(b, name)
        box = pg.locator(".ptfb__stage").bounding_box()
        pg.mouse.move(box["x"] + box["width"] - 14, box["y"] + box["height"] - 14)
        pg.wait_for_timeout(300)
        pg.evaluate("window.__t0 = performance.now(); document.querySelector('.ptfb__corner').click()")
        pg.wait_for_function("document.querySelectorAll('.ptfb__slide')[1].classList.contains('is-current') && !document.querySelector('.ptfb__slide.is-flip')", timeout=9000)
        d = pg.evaluate("performance.now() - window.__t0")
        pg.close()
        return d
    for name, want in (("p_anim06.html", 600), ("p_5_slow.html", 1100), ("p_animc.html", 1700), ("p_anim26.html", 2600)):
        d = turn_duration(name)
        check(f"giro pagina di {want} ms: misurato", abs(d - want) < 220, f"{d:.0f} ms")
    # il giro automatico usa la stessa durata
    pg = open_page(b, "p_anim06.html")
    pg.wait_for_timeout(100)
    pg.close()

    # ------------------------------------------------------------------ 13. allineamento
    print("--- 13. allineamento di commento e nome")
    def align_info(name):
        pg = open_page(b, name)
        r = pg.evaluate("""(()=>{
          const s=document.querySelector('.ptfb__slide.is-current');
          const q=s.querySelector('.ptfb__quote'), a=s.querySelector('.ptfb__author'), body=s.querySelector('.ptfb__body');
          const col=q.getBoundingClientRect();
          const rng=(el)=>{const r=document.createRange();r.selectNodeContents(el);return r.getBoundingClientRect()};
          const ar=rng(a), qr=rng(q.querySelector('p'));
          return {qa:getComputedStyle(q.querySelector('p')).textAlign, na:getComputedStyle(a).textAlign,
                  nameL:ar.left-col.left, nameR:col.right-ar.right, nameC:((ar.left+ar.right)/2)-((col.left+col.right)/2),
                  txtC:((qr.left+qr.right)/2)-((col.left+col.right)/2), txtR:col.right-qr.right}})()""")
        pg.close()
        return r
    r = align_info("p_align_default.html")
    check("predefinito: commento al centro e nome a sinistra (anche con un tema che allinea a destra)", r["qa"] == "center" and r["na"] == "left" and abs(r["nameL"]) < 40 and abs(r["txtC"]) < 12, str(r))
    r = align_info("p_anim26.html")
    check("nome al centro: il nome (trattino compreso) sta al centro della colonna", r["na"] == "center" and abs(r["nameC"]) < 20, str(r))
    r = align_info("p_anim06.html")
    check("commento e nome a destra: entrambi attaccati al bordo destro della colonna", r["qa"] == "right" and r["na"] == "right" and r["nameR"] < 12 and r["txtR"] < 12, str(r))

    # ------------------------------------------------------------------ 14. parole lunghissime
    print("--- 14. parole lunghissime e indirizzi senza spazi")
    def long_info(name, w):
        pg = open_page(b, name, w=w, h=900)
        r = pg.evaluate("""(()=>{
          const st=document.querySelector('.ptfb__stage').getBoundingClientRect();
          const slides=[...document.querySelectorAll('.ptfb__slide')];
          const widest=Math.max(...slides.map(s=>s.getBoundingClientRect().width));
          const bad=slides.filter(s=>{const q=s.querySelector('.ptfb__quote'); return q.scrollWidth>q.clientWidth+1;}).length;
          const cur=document.querySelector('.ptfb__slide.is-current');
          const q=cur.querySelector('.ptfb__quote'), a=cur.querySelector('.ptfb__author');
          const qr=q.getBoundingClientRect(), ar=(()=>{const r=document.createRange();r.selectNodeContents(a);return r.getBoundingClientRect()})();
          return {stage:st.width, widest:widest, overflowing:bad, quoteInside:(qr.left>=st.left-1 && qr.right<=st.right+1),
                  nameOffAxis:Math.abs(((ar.left+ar.right)/2)-((qr.left+qr.right)/2)), docOverflow:document.documentElement.scrollWidth>innerWidth}})()""")
        pg.close()
        return r
    for w in (1400, 390):
        r = long_info("p_longword.html", w)
        check(f"{w}px: nessuna pagina piu' larga della scheda", r["widest"] <= r["stage"] + 1, str(r))
        check(f"{w}px: il commento va a capo dentro la scheda (nessun testo tagliato)", r["overflowing"] == 0 and r["quoteInside"], str(r))
        # The range measures the name text only; the dash drawn by ::before (about 27px) is centered together with it, so the text sits ~13.6px right of the axis.
        check(f"{w}px: con commento e nome al centro il nome (trattino escluso, ~13.6px) sta sull'asse del commento", r["nameOffAxis"] < 20, str(r))
        check(f"{w}px: la pagina non scorre in orizzontale", not r["docOverflow"], str(r))

    b.close()

print("errori JS/console:", errs)
check("nessun errore JS", errs == [])
print("TUTTO OK" if ok else "CI SONO FALLIMENTI")
raise SystemExit(0 if ok else 1)
