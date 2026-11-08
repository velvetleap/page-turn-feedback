# Test of assets/page-turn-feedback-admin.js (settings toggles, color picker call, live character counter).
import os, sys
from playwright.sync_api import sync_playwright
T = os.path.dirname(os.path.abspath(__file__))
S = os.path.join(T, "out")                 # pages written by make_pages.sh
ROOT = os.path.dirname(T)                  # the plugin folder
ok = True
def check(label, cond, extra=""):
    global ok
    print(("PASS " if cond else "FAIL ") + label + (" " + str(extra) if extra and not cond else ""))
    ok = ok and bool(cond)

errors = []
with sync_playwright() as p:
    b = p.chromium.launch()
    for lang in ("en", "it"):
        print("--- settings page", lang)
        pg = b.new_page()
        pg.on("pageerror", lambda e: errors.append(str(e)))
        pg.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
        # fake jQuery + wpColorPicker to see the call (WordPress provides the real one on the settings page)
        pg.add_init_script("""
          window.__cp = [];
          window.jQuery = function (sel) { return { wpColorPicker: function () { window.__cp.push(sel); } }; };
          window.jQuery.fn = { wpColorPicker: function () {} };
        """)
        pg.goto(f"file://{S}/admin_settings_{lang}.html")
        hid = lambda i: pg.evaluate(f"document.getElementById('{i}').hidden")
        check("custom seconds hidden at start", hid("ptfb_custom_wrap") and hid("ptfb_anim_wrap") and hid("ptfb_font_wrap_title"))
        pg.select_option("#ptfb_speed", "custom")
        check("speed: choosing custom shows the field and focuses it", not hid("ptfb_custom_wrap") and pg.evaluate("document.activeElement.id") == "ptfb_custom")
        pg.select_option("#ptfb_speed", "10")
        check("speed: choosing 10 hides it again", hid("ptfb_custom_wrap"))
        pg.select_option("#ptfb_anim", "custom")
        check("duration: custom shows the field and focuses it", not hid("ptfb_anim_wrap") and pg.evaluate("document.activeElement.id") == "ptfb_anim_custom")
        pg.select_option("#ptfb_anim", "1.8")
        check("duration: another choice hides it", hid("ptfb_anim_wrap"))
        for role in ("title", "text", "name"):
            pg.select_option(f"#ptfb_style_font_{role}", "custom")
            check(f"font {role}: custom shows the text field and focuses it", not hid(f"ptfb_font_wrap_{role}") and pg.evaluate("document.activeElement.tagName") == "INPUT")
            pg.select_option(f"#ptfb_style_font_{role}", "georgia")
            check(f"font {role}: georgia hides it", hid(f"ptfb_font_wrap_{role}"))
        check("color picker initialised on .ptfb-color", pg.evaluate("window.__cp") == [".ptfb-color"])
        pg.close()

    # without jQuery the script must not throw
    pg = b.new_page()
    pg.on("pageerror", lambda e: errors.append("nojq: " + str(e)))
    pg.goto(f"file://{S}/admin_settings_en.html")
    check("no jQuery: toggles still work", pg.evaluate("(function(){var s=document.getElementById('ptfb_speed');s.value='custom';s.dispatchEvent(new Event('change'));return !document.getElementById('ptfb_custom_wrap').hidden;})()"))
    pg.close()

    # editor counter
    TXT = {"en": ("Characters: %d / 300", "That is %d characters: better to shorten it a little."),
           "it": ("Caratteri: %d / 300", "Ora sono %d caratteri: meglio accorciare un po'.")}
    for lang in ("en", "it"):
        print("--- editor counter", lang)
        count_fmt, long_fmt = TXT[lang]
        strings = {"en": ("That is %d characters: better to shorten it a little.", "Characters: %1$d / %2$d"),
                   "it": ("Ora sono %d caratteri: meglio accorciare un po'.", "Caratteri: %1$d / %2$d")}[lang]
        html = ("<!doctype html><meta charset=utf-8><textarea id='content'></textarea><span id='ptfb-count' data-limit='300'></span>"
                "<script>window.ptfbAdmin=" + __import__("json").dumps({"limit": 300, "tooLong": strings[0], "count": strings[1]}) + ";</script>"
                f"<script src='file://{ROOT}/assets/page-turn-feedback-admin.js'></script>")
        open(f"{S}/admin_editor_{lang}.html", "w", encoding="utf-8").write(html)
        pg = b.new_page()
        pg.on("pageerror", lambda e: errors.append(str(e)))
        pg.goto(f"file://{S}/admin_editor_{lang}.html")
        out = lambda: pg.evaluate("document.getElementById('ptfb-count').textContent")
        col = lambda: pg.evaluate("document.getElementById('ptfb-count').style.color")
        check("starts at 0", out() == count_fmt % 0, out())
        pg.fill("#content", "a" * 100); pg.wait_for_timeout(700)
        check("100 characters: grey counter", out() == count_fmt % 100 and col() == "rgb(80, 87, 94)", (out(), col()))
        pg.fill("#content", "a" * 270); pg.wait_for_timeout(700)
        check("270 characters (>85%): amber counter", out() == count_fmt % 270 and col() == "rgb(153, 104, 0)", (out(), col()))
        pg.fill("#content", "a" * 301); pg.wait_for_timeout(700)
        check("301 characters: red warning with the number", out() == long_fmt % 301 and col() == "rgb(179, 45, 46)", (out(), col()))
        pg.fill("#content", "<p>ciao&nbsp;<strong>mondo</strong></p>"); pg.wait_for_timeout(700)
        check("HTML tags and &nbsp; are not counted ('ciao mondo' = 10)", out() == count_fmt % 10, out())
        pg.close()
    b.close()

check("no JS errors", not errors, errors)
print("TUTTO OK" if ok else "CI SONO FALLIMENTI")
sys.exit(0 if ok else 1)
