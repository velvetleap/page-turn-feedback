# Browser test of the 1.8.0 admin features: ready-made styles, live preview (settings page) and the Order page.
# The pages are written by make_pages.sh (the real PHP code of the plugin); jQuery is faked, so the WordPress color picker
# and the drag and drop of the Order page are not exercised here (they are checked in a real WordPress, see CLAUDE.md).
import json, os, re, subprocess, sys
from playwright.sync_api import sync_playwright
T = os.path.dirname(os.path.abspath(__file__))
S = os.path.join(T, "out")
ok = True
def check(label, cond, extra=""):
    global ok
    print(("PASS " if cond else "FAIL ") + label + (" " + str(extra) if extra and not cond else ""))
    ok = ok and bool(cond)

errors = []
FAKE_JQ = """
  window.__cp = [];
  window.jQuery = function (sel) { return { wpColorPicker: function () { window.__cp.push([].slice.call(arguments)); } }; };
  window.jQuery.fn = { wpColorPicker: function () {} };
"""
SELECTORS = ["section.ptfb", ".ptfb__title", ".ptfb__body", ".ptfb__quote", ".ptfb__quote p", ".ptfb__author", ".ptfb__mark", ".ptfb__corner"]
LOOK_JS = """(sels) => sels.map(function (s) {
  var e = document.querySelector(s);
  if (!e) { return [s, null]; }
  var c = getComputedStyle(e);
  return [s, [c.color, c.backgroundColor, c.fontFamily, c.textAlign, c.fontSize, c.borderTopColor]];
})"""

def preview_frame(pg):
    for f in pg.frames:
        if f != pg.main_frame:
            return f
    return None

def wait_preview(pg, timeout=4000):
    pg.wait_for_function("(function(){var f=document.getElementById('ptfb-preview');return !!(f&&f.contentDocument&&f.contentDocument.querySelector('section.ptfb')&&f.contentDocument.readyState==='complete');})()", timeout=timeout)

def form_style(pg):
    """Colors, fonts and alignment as typed in the form, as the PHP sanitizer wants them."""
    return pg.evaluate("""(function(){var f=document.querySelector('.ptfb-settings__main form'),o={};
      Array.prototype.forEach.call(f.elements,function(e){var m=/^ptfb_style\\[(.+)\\]$/.exec(e.name||'');if(m){o[m[1]]=e.value;}});return o;})()""")

def php_css(style):
    return subprocess.run(["php", os.path.join(T, "css_for_style.php"), json.dumps(style)], capture_output=True, text=True, check=True).stdout

def same_look(b, pg, label, tmp="ref_preview.html"):
    """The preview must draw what the site draws: the same srcdoc with the CSS written by PHP instead of the CSS written by the script."""
    style = form_style(pg)
    src = pg.evaluate("document.getElementById('ptfb-preview').srcdoc")
    ref = re.sub(r'(<style id="ptfb-live">).*?(</style>)', lambda m: m.group(1) + php_css(style) + m.group(2), src, flags=re.S)
    open(os.path.join(S, tmp), "w", encoding="utf-8").write(ref)
    rp = b.new_page(viewport={"width": 460, "height": 600})
    rp.on("pageerror", lambda e: errors.append("ref: " + str(e)))
    rp.goto("file://" + os.path.join(S, tmp))
    rp.wait_for_timeout(300)
    a = preview_frame(pg).evaluate(LOOK_JS, SELECTORS)
    r = rp.evaluate(LOOK_JS, SELECTORS)
    rp.close()
    check(label + ": the preview draws the same as the site (colors, fonts, alignment)", a == r, [(x, y) for x, y in zip(a, r) if x != y])

with sync_playwright() as p:
    b = p.chromium.launch()

    for lang in ("en", "it"):
        print("--- settings page", lang)
        pg = b.new_page(viewport={"width": 1400, "height": 1000})
        pg.on("pageerror", lambda e: errors.append(str(e)))
        pg.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
        pg.add_init_script(FAKE_JQ)
        pg.goto(f"file://{S}/admin_settings_{lang}.html")
        wait_preview(pg)
        check("ready-made styles and preview are shown once the script runs", pg.evaluate("!document.getElementById('ptfb-presets-wrap').hidden && !document.getElementById('ptfb-preview-box').hidden"))
        check("six buttons: five styles and the original look", pg.evaluate("document.querySelectorAll('.ptfb-preset').length") == 6)
        fr = preview_frame(pg)
        check("preview: the real card (two example texts, container of the plugin)", fr.evaluate("document.querySelectorAll('figure.ptfb__slide').length") == 2 and fr.evaluate("!!document.querySelector('.ptfb__container .ptfb__inner')"))
        check("preview: the stylesheet and the script of the plugin are loaded", fr.evaluate("document.querySelector('link[rel=stylesheet]').sheet !== null && getComputedStyle(document.querySelector('.ptfb__stage')).position !== ''"))
        check("preview: frame as tall as the card (not the default 150px)", pg.evaluate("document.getElementById('ptfb-preview').getBoundingClientRect().height") > 200)
        check("preview: side by side with the form on a wide screen", pg.evaluate("(function(){var a=document.querySelector('.ptfb-settings__main').getBoundingClientRect(),b=document.getElementById('ptfb-preview-box').getBoundingClientRect();return b.left>=a.right-1;})()"))

        # every ready-made style: fills the form, the preview follows, and it equals what the site would draw
        names = {"paper": ("Paper", "Carta"), "night": ("Night", "Notte"), "ocean": ("Ocean", "Oceano"), "sunset": ("Sunset", "Tramonto"), "minimal": ("Minimal", "Minimale")}
        presets = pg.evaluate("window.ptfbAdmin.presets")
        pg.select_option("#ptfb_speed", "off")      # the page must not turn while we compare
        pg.wait_for_timeout(600); wait_preview(pg)
        for key in ("paper", "night", "ocean", "sunset", "minimal"):
            pg.click(f'.ptfb-preset[data-preset="{key}"]')
            pg.wait_for_timeout(150)
            st = presets[key]["style"]
            got = form_style(pg)
            check(f"{key}: the form is filled with the whole style", all(got[k] == v for k, v in st.items()), {k: (got[k], v) for k, v in st.items() if got[k] != v})
            live = fr.evaluate("document.getElementById('ptfb-live').textContent")
            check(f"{key}: the preview style has the colors of the style", ("--ptfb-bg:" + st["bg"]) in live and ("--ptfb-card:" + st["card"]) in live)
            note = pg.evaluate("document.getElementById('ptfb-preset-note').textContent")
            check(f"{key}: the note names the style (screen reader status)", names[key][0 if lang == "en" else 1] in note and "%s" not in note, note)
            same_look(b, pg, key)
        pg.click('.ptfb-preset[data-preset="default"]')
        pg.wait_for_timeout(150)
        check("original look: the form is back to green and white and left alignment of the name", form_style(pg)["bg"] == "#a5cca7" and form_style(pg)["font_title"] == "theme" and form_style(pg)["align_text"] == "center" and form_style(pg)["align_name"] == "left")
        same_look(b, pg, "original look")
        check("color picker: told about every color set by a style", pg.evaluate("window.__cp.filter(function(a){return a[0]==='color';}).length") >= 6 * 6)

        # hand-made changes
        custom = {"ptfb_style[bg]": "#123456", "ptfb_style[title]": "FEDCBA", "ptfb_style[card]": "#EEE", "ptfb_style[text]": "zzz", "ptfb_style[name]": "#202020", "ptfb_style[accent]": "ff0000",
                  "ptfb_style[font_title]": "palatino", "ptfb_style[font_text]": "custom", "ptfb_style[font_text_custom]": "Poppins; } body{x", "ptfb_style[font_name]": "heading",
                  "ptfb_style[align_text]": "right", "ptfb_style[align_name]": "center"}
        pg.evaluate("""(function(v){var f=document.querySelector('.ptfb-settings__main form');
          Object.keys(v).forEach(function(n){var e=f.elements[n];e.value=v[n];e.dispatchEvent(new Event(e.tagName==='SELECT'?'change':'input',{bubbles:true}));});})""", custom)
        pg.wait_for_timeout(200)
        live = fr.evaluate("document.getElementById('ptfb-live').textContent")
        check("typed colors: valid ones used, without # accepted, invalid one replaced by the default", "--ptfb-bg:#123456" in live and "--ptfb-title:#fedcba" in live and "--ptfb-card:#eee" in live and "--ptfb-text:#333333" in live and "--ptfb-accent:#ff0000" in live, live)
        check("hand-typed font name: only harmless characters reach the style", "Poppins  body" not in live and ";" not in re.sub(r"[^;]*:[^;}]*;?", "", live.replace("}", "")) or "{x" not in live, live)
        same_look(b, pg, "mixed hand-made style")

        # text, speed and page-turn duration rebuild the preview
        pg.select_option("#ptfb_speed", "3")
        pg.select_option("#ptfb_anim", "2.6")
        pg.fill("#ptfb_title", "Dicono di me")
        pg.wait_for_timeout(800); wait_preview(pg)
        fr = preview_frame(pg)
        check("title typed in the form appears in the preview", fr.evaluate("document.querySelector('.ptfb__title').textContent") == "Dicono di me")
        check("speed and page-turn duration reach the preview (3 s, 2.6 s)", fr.evaluate("document.querySelector('section.ptfb').getAttribute('data-interval')") == "3000" and fr.evaluate("document.querySelector('section.ptfb').getAttribute('data-turn')") == "2600")
        check("the style typed before is still there after the rebuild", "--ptfb-bg:#123456" in fr.evaluate("document.getElementById('ptfb-live').textContent"))
        pg.fill("#ptfb_title", "")
        pg.wait_for_timeout(800); wait_preview(pg)
        fr = preview_frame(pg)
        check("empty title: no heading in the preview (like on the site)", fr.evaluate("document.querySelector('.ptfb__title')") is None)
        pg.select_option("#ptfb_speed", "custom"); pg.fill("#ptfb_custom", "40")
        pg.select_option("#ptfb_anim", "custom"); pg.fill("#ptfb_anim_custom", "0.8")
        pg.wait_for_timeout(800); wait_preview(pg)
        fr = preview_frame(pg)
        check("custom seconds: 40 s and 0.8 s", fr.evaluate("document.querySelector('section.ptfb').getAttribute('data-interval')") == "40000" and fr.evaluate("document.querySelector('section.ptfb').getAttribute('data-turn')") == "800")
        pg.select_option("#ptfb_speed", "off"); pg.wait_for_timeout(800); wait_preview(pg)
        check("no automatic sliding: interval 0", preview_frame(pg).evaluate("document.querySelector('section.ptfb').getAttribute('data-interval')") == "0")

        # the preview turns by itself like the site
        pg.select_option("#ptfb_speed", "3"); pg.select_option("#ptfb_anim", "0.6")
        pg.wait_for_timeout(800); wait_preview(pg)
        pg.wait_for_function("(function(){var f=document.getElementById('ptfb-preview').contentDocument;var s=f.querySelectorAll('figure.ptfb__slide');return s.length>1&&s[1].classList.contains('is-current');})()", timeout=9000)
        check("the preview turns the page by itself", True)
        pg.close()

    print("--- settings page with real feedback on the site")
    pg = b.new_page(viewport={"width": 1400, "height": 1000})
    pg.on("pageerror", lambda e: errors.append(str(e)))
    pg.add_init_script(FAKE_JQ)
    pg.goto(f"file://{S}/admin_settings_items.html")
    wait_preview(pg)
    fr = preview_frame(pg)
    check("the preview shows the first three real feedback (not the examples)", fr.evaluate("document.querySelectorAll('figure.ptfb__slide').length") == 3 and "Commento numero 1" in fr.evaluate("document.querySelector('.ptfb__quote').textContent") and "Maria" not in fr.evaluate("document.body.textContent"))
    check("no example note when the feedback are real", "examples" not in pg.evaluate("document.getElementById('ptfb-preview-box').textContent"))
    pg.close()

    print("--- narrow screen: preview above the form")
    pg = b.new_page(viewport={"width": 800, "height": 900})
    pg.add_init_script(FAKE_JQ)
    pg.goto(f"file://{S}/admin_settings_en.html"); wait_preview(pg)
    check("below 1100px the preview comes first, full width", pg.evaluate("(function(){var a=document.querySelector('.ptfb-settings__main').getBoundingClientRect(),b=document.getElementById('ptfb-preview-box').getBoundingClientRect();return b.bottom<=a.top+1&&b.width>500;})()"))
    pg.close()

    print("--- without JavaScript the new blocks stay hidden (they would do nothing)")
    ctx = b.new_context(java_script_enabled=False)
    pg = ctx.new_page(); pg.goto(f"file://{S}/admin_settings_en.html")
    check("no script: ready-made styles and preview hidden", pg.evaluate("1") == 1 and pg.locator("#ptfb-presets-wrap").is_hidden() and pg.locator("#ptfb-preview-box").is_hidden())
    ctx.close()

    # ------------------------------------------------------------------ Order page
    for lang in ("en", "it"):
        print("--- order page", lang)
        pg = b.new_page(viewport={"width": 1200, "height": 900})
        pg.on("pageerror", lambda e: errors.append(str(e)))
        pg.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
        posts = []
        pg.route("http://example.test/**", lambda route: (posts.append(route.request.post_data), route.fulfill(status=200, content_type="text/html", body="<p>saved</p>")))
        pg.goto(f"file://{S}/admin_order_{lang}.html")
        ids = lambda: pg.evaluate("Array.prototype.map.call(document.querySelectorAll('#ptfb-order-list input[name=\"ptfb_order[]\"]'),function(i){return i.value;})")
        beyond = lambda: pg.evaluate("Array.prototype.map.call(document.querySelectorAll('#ptfb-order-list li'),function(l){return l.classList.contains('ptfb-order__row--beyond');})")
        check("five rows in the order of the site, the last two beyond the limit of three", ids() == ["10", "11", "12", "13", "14"] and beyond() == [False, False, False, True, True])
        check("the tag 'not shown' is visible only on those two", pg.evaluate("Array.prototype.map.call(document.querySelectorAll('.ptfb-order__tag'),function(t){return getComputedStyle(t).display!=='none';})") == [False, False, False, True, True])
        check("numbers 1 to 5 come from a CSS counter (they follow the rows when these move)", pg.evaluate("getComputedStyle(document.querySelector('#ptfb-order-list li:nth-child(3)'),'::before').content") == "counter(ptfb-pos)")
        check("arrows: up disabled on the first row, down disabled on the last", pg.evaluate("document.querySelector('#ptfb-order-list li:first-child [data-move=up]').disabled") and pg.evaluate("document.querySelector('#ptfb-order-list li:last-child [data-move=down]').disabled") and not pg.evaluate("document.querySelector('#ptfb-order-list li:first-child [data-move=down]').disabled"))
        check("arrows have a name for screen readers", pg.evaluate("document.querySelector('[data-move=up]').getAttribute('aria-label')") == ("Move “Giulia R.” up" if lang == "en" else "Sposta “Giulia R.” in su"))
        pg.click("#ptfb-order-list li:nth-child(1) [data-move=down]")
        check("down arrow: first row goes to second place, focus stays on the arrow", ids() == ["11", "10", "12", "13", "14"] and pg.evaluate("document.activeElement.getAttribute('data-move')") == "down" and pg.evaluate("document.activeElement.closest('li').querySelector('input').value") == "10")
        pg.click("#ptfb-order-list li:nth-child(2) [data-move=down]"); pg.click("#ptfb-order-list li:nth-child(3) [data-move=down]")
        check("moving a row to the 4th place hides it from the site (marked), the one that came up is shown", ids() == ["11", "12", "13", "10", "14"] and beyond() == [False, False, False, True, True])
        pg.click("#ptfb-order-list li:nth-child(4) [data-move=up]")
        check("up arrow brings it back; the marks follow the places", ids() == ["11", "12", "10", "13", "14"] and beyond() == [False, False, False, True, True])
        pg.click("#ptfb-order-list li:nth-child(2) [data-move=up]")
        check("a row that reaches the top: its up arrow is disabled and the focus moves to the other arrow", ids() == ["12", "11", "10", "13", "14"] and pg.evaluate("document.activeElement.getAttribute('data-move')") == "down" and not pg.evaluate("document.querySelector('#ptfb-order-list li:first-child [data-move=down]').disabled"))
        # keyboard: Enter on a focused arrow works too (it is a real button)
        pg.focus("#ptfb-order-list li:nth-child(5) [data-move=up]"); pg.keyboard.press("Enter")
        check("keyboard: Enter on an arrow moves the row", ids() == ["12", "11", "10", "14", "13"])
        # Save order: the form posts the ids in the new order
        pg.click("button.button-primary")
        pg.wait_for_timeout(300)
        body = posts[-1] if posts else ""
        sent = re.findall(r"ptfb_order%5B%5D=(\d+)", body or "")
        check("Save order posts the ids in the order on the screen, with the action and the nonce", sent == ["12", "11", "10", "14", "13"] and "action=ptfb_save_order" in body and "_wpnonce=" in body, body)
        # Reset asks first
        pg.goto(f"file://{S}/admin_order_{lang}.html")
        dialogs = []
        def on_dialog(d):
            dialogs.append(d.message); d.dismiss()
        pg.on("dialog", on_dialog)
        n_before = len(posts)
        pg.click("button[name=ptfb_reset]"); pg.wait_for_timeout(300)
        check("reset: asks for confirmation and, if refused, sends nothing", len(dialogs) == 1 and len(posts) == n_before and len(dialogs[0]) > 20, dialogs)
        pg.remove_listener("dialog", on_dialog)
        pg.on("dialog", lambda d: d.accept())
        pg.click("button[name=ptfb_reset]"); pg.wait_for_timeout(300)
        check("reset: confirmed, the form is sent with ptfb_reset", len(posts) == n_before + 1 and "ptfb_reset=1" in (posts[-1] or ""), posts[-1] if posts else None)
        pg.close()

    b.close()

check("no JS errors", not errors, errors)
print("TUTTO OK" if ok else "CI SONO FALLIMENTI")
sys.exit(0 if ok else 1)
