"""Draws the plugin icon for the WordPress.org page: a page whose bottom-right corner folds over and reveals the next
page (the gesture of the plugin), as an animated GIF in the two sizes WordPress.org asks for.
Frames are drawn as SVG, rendered with Chromium at 512 px and scaled down (needs: playwright with Chromium, Pillow,
the fonts Lora and Inter). Usage: python3 design/gen_icon.py .wordpress-org"""
import math, sys, os
from playwright.sync_api import sync_playwright
from PIL import Image

S = 512          # canvas
M = 64           # margin around the page
P = S - 2 * M    # page size (384)
R = 34           # page corner radius
BG = "#1E2B2F"
PAGE = "#FFFFFF"
INK = "#C9D0D2"      # text lines
ACC = "#D2603E"      # quote mark (terracotta of the Velvet Leap mark)
FLAP_A, FLAP_B = "#E9795A", "#F7C7B6"  # back of the page: from the fold to the tip

def rrect_poly(n=14):
    pts = []
    for cx, cy, a0 in ((R, R, 180), (P - R, R, 270), (P - R, P - R, 0), (R, P - R, 90)):
        for i in range(n + 1):
            a = math.radians(a0 + 90 * i / n)
            pts.append((cx + R * math.cos(a), cy + R * math.sin(a)))
    return pts

def clip(poly, keep_le, c):
    """Keeps the part of poly where u+v <= c (keep_le) or >= c."""
    f = (lambda p: p[0] + p[1] - c) if keep_le else (lambda p: c - p[0] - p[1])
    out = []
    for i, a in enumerate(poly):
        b = poly[(i + 1) % len(poly)]
        fa, fb = f(a), f(b)
        if fa <= 0: out.append(a)
        if (fa < 0 < fb) or (fb < 0 < fa):
            t = fa / (fa - fb)
            out.append((a[0] + t * (b[0] - a[0]), a[1] + t * (b[1] - a[1])))
    return out

def pts(poly): return " ".join(f"{M + x:.1f},{M + y:.1f}" for x, y in poly)

def content(kind):
    # a big opening quote mark and the lines of a short comment
    lines = {"a": (230, 262, 150), "b": (262, 190, 238)}[kind]
    out = f'<text x="{M + 44}" y="{M + 196}" font-family="Lora" font-weight="700" font-size="236" fill="{ACC}">&#8220;</text>'
    for i, w in enumerate(lines):
        out += f'<rect x="{M + 48}" y="{M + 232 + i * 46}" width="{w}" height="22" rx="11" fill="{INK}"/>'
    return out

def page(kind): return f'<rect x="{M}" y="{M}" width="{P}" height="{P}" rx="{R}" fill="{PAGE}"/>' + content(kind)

def frame(top, under, c, bg=True):
    """top = the page being turned, under = the page below, c = position of the fold line u+v=c (2P = no fold)."""
    base = rrect_poly()
    svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{S}" height="{S}" viewBox="0 0 {S} {S}">',
           '<defs><filter id="s1" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="5" dy="5" stdDeviation="9" flood-color="#000" flood-opacity=".38"/></filter>'
           '<filter id="s2" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="3" dy="3" stdDeviation="5" flood-color="#000" flood-opacity=".30"/></filter>',
           f'<linearGradient id="g" gradientUnits="userSpaceOnUse" x1="{M + c / 2:.1f}" y1="{M + c / 2:.1f}" x2="{M + c - P:.1f}" y2="{M + c - P:.1f}"><stop offset="0" stop-color="{FLAP_A}"/><stop offset="1" stop-color="{FLAP_B}"/></linearGradient>',
           '</defs>', f'<rect width="{S}" height="{S}" fill="{BG}"/>' if bg else '']
    svg.append(page(under))
    flat = clip(base, True, c)
    if len(flat) >= 3:
        svg.append(f'<clipPath id="cf"><polygon points="{pts(flat)}"/></clipPath>')
        svg.append(f'<g filter="url(#s1)"><g clip-path="url(#cf)">{page(top)}</g></g>')
    beyond = clip(base, False, c)
    if len(beyond) >= 3 and c < 2 * P - 1:
        flap = [(c - y, c - x) for x, y in beyond]   # mirror image of the corner across the fold line
        svg.append(f'<polygon points="{pts(flap)}" fill="url(#g)" stroke="url(#g)" stroke-width="3" stroke-linejoin="round" filter="url(#s2)"/>')
    svg.append('</svg>')
    return "".join(svg)

def ease(t): return t * t * (3 - 2 * t)


def build_icons(out):
    import tempfile
    os.makedirs(out, exist_ok=True)
    c0 = 1.80 * P    # the small dog-ear a page rests with
    jobs = []        # (name, top, under, c, ms)
    for turn, (top, under) in enumerate((("a", "b"), ("b", "a"))):
        jobs.append((f"t{turn}_hold", top, under, c0, 1100))
        n = 16
        for i in range(1, n + 1):
            c = c0 + (-0.14 * P - c0) * ease(i / n)             # the fold travels past the page
            jobs.append((f"t{turn}_{i:02d}", top, under, c, 45))
        for i in range(1, 4):                                    # the next page grows its own dog-ear
            jobs.append((f"t{turn}_g{i}", under, top, 2 * P - (2 * P - c0) * i / 3, 70))
    with tempfile.TemporaryDirectory() as tmp, sync_playwright() as p:
        b = p.chromium.launch(); pg = b.new_page(viewport={"width": S, "height": S})
        for name, top, under, c, ms in jobs:
            pg.set_content(f'<body style="margin:0;background:{BG}">' + frame(top, under, c) + "</body>")
            pg.screenshot(path=f"{tmp}/{name}.png")
        b.close()
        for sz in (256, 128):
            frames = [Image.open(f"{tmp}/{n}.png").convert("RGB").resize((sz, sz), Image.LANCZOS) for n, *_ in jobs]
            dur = [j[4] for j in jobs]
            # one palette for the whole animation, so the colours do not flicker
            sample = frames[::max(1, len(frames) // 12)]
            mont = Image.new("RGB", (sz * len(sample), sz))
            for i, f in enumerate(sample): mont.paste(f, (i * sz, 0))
            pal = mont.quantize(colors=96, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)
            q = [f.quantize(palette=pal, dither=Image.Dither.FLOYDSTEINBERG) for f in frames]
            q[0].save(f"{out}/icon-{sz}x{sz}.gif", save_all=True, append_images=q[1:], duration=dur, loop=0, optimize=True, disposal=1)
    for f in sorted(os.listdir(out)): print(f, os.path.getsize(f"{out}/{f}") // 1024, "KB")


if __name__ == "__main__":
    build_icons(sys.argv[1])
