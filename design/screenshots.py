"""Takes the screenshots of the WordPress.org plugin page (screenshot-1 ... screenshot-7) from the throw-away site made
by design/wp-rig.sh, with the plugin and only invented feedback. They are the files the "== Screenshots ==" list of
readme.txt describes, in the same order.

Usage:  python3 design/screenshots.py <site-url> <output-dir> [name ...]
        (for example: python3 design/screenshots.py http://localhost:8080 .wordpress-org turn styles)
Names: turn, theme, settings, styles, edit, order, tools   (default: all)
Needs: playwright with Chromium, Pillow, the font Inter (for the labels of the styles image).
"""
import io
import os
import sys

from PIL import Image, ImageDraw, ImageFont
from playwright.sync_api import sync_playwright

URL = sys.argv[1].rstrip("/")
OUT = sys.argv[2]
ONLY = sys.argv[3:]
os.makedirs(OUT, exist_ok=True)

HIDE_ADMIN_NOISE = "#wp-admin-bar-sqlite-db-integration, #wpfooter, #wp-admin-bar-updates { display: none !important; }"


def wanted(name):
    return not ONLY or name in ONLY


def save_png(img, name, colors=None):
    """Saves a PNG; with colors=N the image is first reduced to N colours (smaller file, fine for flat screens)."""
    path = os.path.join(OUT, name)
    img = img.convert("RGB")
    if colors:
        img = img.quantize(colors=colors, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)
    img.save(path, optimize=True)
    print(f"{name}: {os.path.getsize(path) // 1024} KB, {img.size[0]}x{img.size[1]}")


def shot(page, **kwargs):
    return Image.open(io.BytesIO(page.screenshot(**kwargs)))


def login(browser, width=1280, height=800, scale=2):
    ctx = browser.new_context(viewport={"width": width, "height": height}, device_scale_factor=scale)
    page = ctx.new_page()
    page.goto(URL + "/wp-login.php")
    page.fill("#user_login", "admin")
    page.fill("#user_pass", "password")
    page.click("#wp-submit")
    page.wait_for_load_state("networkidle")
    return ctx, page


def admin_page(page, path, wait=700):
    page.goto(URL + "/wp-admin/" + path)
    page.wait_for_load_state("networkidle")
    page.add_style_tag(content=HIDE_ADMIN_NOISE)
    page.wait_for_timeout(wait)


def global_palette(frames, colors=200):
    """One palette for every frame (so the colours do not flicker): made from a sample of all of them."""
    sample = frames[:: max(1, len(frames) // 8)]
    w, h = sample[0].size
    sheet = Image.new("RGB", (w, h * len(sample)))
    for i, f in enumerate(sample):
        sheet.paste(f, (0, i * h))
    return sheet.quantize(colors=colors, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)


# --- 1. The card turning (animated GIF) ---------------------------------------------------------------------------
def turn(browser):
    ctx = browser.new_context(viewport={"width": 1280, "height": 800}, device_scale_factor=1)
    page = ctx.new_page()
    page.clock.install(time=1000)
    page.goto(URL + "/", wait_until="load")
    page.evaluate("document.fonts.ready")
    page.evaluate("document.querySelector('.ptfb').scrollIntoView({block:'center'})")
    page.wait_for_timeout(400)
    page.clock.pause_at(page.evaluate("Date.now()") + 50)   # from now on time only moves when we say so

    box = page.evaluate("(() => { const r = document.querySelector('.ptfb').getBoundingClientRect(); return {x: r.x, y: r.y, width: r.width, height: r.height}; })()")
    frames, delays = [], []

    def snap(ms):
        frames.append(shot(page, clip=box).convert("RGB"))
        delays.append(ms)

    snap(1200)                                           # the card at rest
    # The page turns by itself (autoplay, 1.1 s by default): move time on until the turn starts, then film it.
    for _ in range(80):
        if page.evaluate("!!document.querySelector('.ptfb__slide.is-flip')"):
            break
        page.clock.run_for(100)
    else:
        raise SystemExit("the page never started to turn")
    for _ in range(40):
        snap(40)
        page.clock.run_for(40)
        if not page.evaluate("!!document.querySelector('.ptfb__slide.is-flip')"):
            break
    snap(1500)                                           # the next feedback at rest
    ctx.close()

    w = 820
    size = (w, round(frames[0].size[1] * w / frames[0].size[0]))
    small = [f.resize(size, Image.Resampling.LANCZOS) for f in frames]
    pal = global_palette(small)
    pal_frames = [f.quantize(palette=pal, dither=Image.Dither.FLOYDSTEINBERG) for f in small]
    path = os.path.join(OUT, "screenshot-1.gif")
    pal_frames[0].save(path, save_all=True, append_images=pal_frames[1:], duration=delays, loop=0, optimize=False, disposal=1)
    print(f"screenshot-1.gif: {os.path.getsize(path) // 1024} KB, {len(frames)} frames, {size[0]}x{size[1]}")
    # a still from the middle of the turn, to look at while building (not published)
    frames[len(frames) // 2 + 4].save(os.path.join(OUT, "..", "turn-middle.png")) if os.environ.get("PTFB_DEBUG") else None


# --- 2. The card on a site (Twenty Twenty-Five) ------------------------------------------------------------------
def theme(browser):
    ctx = browser.new_context(viewport={"width": 1280, "height": 620}, device_scale_factor=1.5)
    page = ctx.new_page()
    page.goto(URL + "/", wait_until="networkidle")
    # a little white above, the whole section, a little white below: it ends before the footer text, which would be cut in half
    page.evaluate("window.scrollTo(0, document.querySelector('.ptfb').getBoundingClientRect().top + window.scrollY - 130)")
    page.wait_for_timeout(600)
    save_png(shot(page), "screenshot-2.png", colors=256)
    ctx.close()


# --- 3. The settings page with the live preview ------------------------------------------------------------------
def settings(browser):
    ctx, page = login(browser, 1280, 840)
    admin_page(page, "edit.php?post_type=ptfb_feedback&page=ptfb-settings")
    save_png(shot(page), "screenshot-3.png", colors=256)
    ctx.close()


# --- 4. The ready-made styles side by side ------------------------------------------------------------------------
def styles(browser):
    ctx, page = login(browser, 1280, 840)
    admin_page(page, "edit.php?post_type=ptfb_feedback&page=ptfb-settings")
    cells = []
    for key, label in (("default", "Original look"), ("paper", "Paper"), ("night", "Night"), ("ocean", "Ocean"), ("sunset", "Sunset"), ("minimal", "Minimal")):
        page.click(f'.ptfb-preset[data-preset="{key}"]')
        page.wait_for_timeout(500)
        cells.append((label, shot(page.locator("#ptfb-preview"))))
    ctx.close()

    cw = 640
    thumbs = [(label, img.resize((cw, round(img.size[1] * cw / img.size[0])), Image.Resampling.LANCZOS)) for label, img in cells]
    ch = max(t.size[1] for _, t in thumbs)
    gap, label_h, pad = 40, 64, 56
    cols, rows = 3, 2
    W = pad * 2 + cols * cw + (cols - 1) * gap
    H = pad * 2 + rows * (ch + label_h) + (rows - 1) * gap
    sheet = Image.new("RGB", (W, H), "#F4F1EC")
    draw = ImageDraw.Draw(sheet)
    font = ImageFont.truetype("/usr/share/fonts/opentype/inter/Inter-SemiBold.otf", 28)
    for i, (label, img) in enumerate(thumbs):
        x = pad + (i % cols) * (cw + gap)
        y = pad + (i // cols) * (ch + label_h + gap)
        sheet.paste(img, (x, y + (ch - img.size[1]) // 2))
        tw = draw.textlength(label, font=font)
        draw.text((x + (cw - tw) / 2, y + ch + 16), label, font=font, fill="#1E2B2F")
    save_png(sheet, "screenshot-4.png", colors=256)


# --- 5. Adding a feedback: a name and a comment ------------------------------------------------------------------
def edit(browser):
    ctx, page = login(browser, 1280, 620)
    admin_page(page, "post-new.php?post_type=ptfb_feedback", wait=1200)
    page.add_style_tag(content="#post-status-info, #screen-meta-links { display: none !important; } #content_ifr { height: 160px !important; }")
    page.fill("#title", "Eleanor")
    page.frame_locator("#content_ifr").locator("body").click()
    page.keyboard.type("A warm, honest blog with a gift for finding the right book at the right time. I always leave with a new favourite.", delay=0)
    page.wait_for_timeout(600)
    page.evaluate("window.scrollTo(0, 0)")
    save_png(shot(page), "screenshot-5.png", colors=256)
    ctx.close()


# --- 6. Choosing the order by hand --------------------------------------------------------------------------------
def order(browser):
    ctx, page = login(browser, 1280, 800)
    admin_page(page, "edit.php?post_type=ptfb_feedback&page=ptfb-order")
    save_png(shot(page), "screenshot-6.png", colors=256)
    ctx.close()


# --- 7. Import and export ------------------------------------------------------------------------------------------
def tools(browser):
    ctx, page = login(browser, 1280, 800)
    admin_page(page, "edit.php?post_type=ptfb_feedback&page=ptfb-tools")
    save_png(shot(page), "screenshot-7.png", colors=256)
    ctx.close()


with sync_playwright() as p:
    browser = p.chromium.launch()
    for name, fn in (("turn", turn), ("theme", theme), ("settings", settings), ("styles", styles), ("edit", edit), ("order", order), ("tools", tools)):
        if wanted(name):
            fn(browser)
    browser.close()
