"""Draws the WordPress.org banner (1544x500 and the 772x250 reduction) in the same style as the icon.
Needs: playwright with Chromium, Pillow, the fonts Lora and Inter. Usage: python3 design/banner.py .wordpress-org"""
import sys, os
from playwright.sync_api import sync_playwright
from PIL import Image
import gen_icon as G

W, H = 1544, 500
BG, WHITE, ACC, ACC_LIGHT, MUTED = "#1E2B2F", "#FFFFFF", "#D2603E", "#F2A58C", "#AEB9BC"

def tiles():
    """Faint grid of rounded tiles (the Velvet Leap mark) that fades out towards the left."""
    out = ['<defs><linearGradient id="fade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".55" stop-color="#fff" stop-opacity="1"/></linearGradient>'
           f'<mask id="m"><rect x="700" y="0" width="{W - 700}" height="{H}" fill="url(#fade)"/></mask></defs><g mask="url(#m)" opacity=".055">']
    size, gap = 56, 12
    for ix in range(0, 17):
        for iy in range(0, 9):
            x = 700 + ix * (size + gap); y = -14 + iy * (size + gap)
            out.append(f'<rect x="{x}" y="{y}" width="{size}" height="{size}" rx="10" fill="#fff"/>')
    out.append('</g>')
    return "".join(out)

def mark(x, y):
    """3x3 tiles like the Velvet Leap avatar: the middle one in salmon, the last one dashed."""
    t, g, out = 13, 4, []
    for r in range(3):
        for c in range(3):
            px, py = x + c * (t + g), y + r * (t + g)
            if (r, c) == (1, 1): out.append(f'<rect x="{px}" y="{py}" width="{t}" height="{t}" rx="3" fill="{ACC_LIGHT}"/>')
            elif (r, c) == (2, 2): out.append(f'<rect x="{px + .75}" y="{py + .75}" width="{t - 1.5}" height="{t - 1.5}" rx="3" fill="none" stroke="#BDC3C5" stroke-width="1.5" stroke-dasharray="3 3"/>')
            else: out.append(f'<rect x="{px}" y="{py}" width="{t}" height="{t}" rx="3" fill="{WHITE}"/>')
    return "".join(out)

def banner_svg():
    page = 340                      # size of the front card
    k = page / G.P                  # scale of the icon drawing
    cx, cy = 1190, 250              # centre of the card stack
    fx, fy = cx - page / 2, cy - page / 2   # top-left of the front page
    front = G.frame("a", "b", 1.30 * G.P, bg=False)
    inner = front.split('>', 1)[1].rsplit('</svg>', 1)[0]     # the drawing without the outer <svg> tag
    r = 34 * k
    def card(angle, dx, dy, fill):
        return (f'<g transform="rotate({angle} {cx + dx} {cy + dy + page / 2 - 10})">'
                f'<rect x="{fx + dx}" y="{fy + dy}" width="{page}" height="{page}" rx="{r}" fill="{fill}" filter="url(#sh)"/></g>')
    s = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">',
         '<defs><filter id="sh" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="10" stdDeviation="14" flood-color="#000" flood-opacity=".35"/></filter>'
         f'<radialGradient id="glow" cx="{cx}" cy="{cy}" r="520" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#2F454B"/><stop offset="1" stop-color="{BG}" stop-opacity="0"/></radialGradient></defs>',
         f'<rect width="{W}" height="{H}" fill="{BG}"/>', f'<rect width="{W}" height="{H}" fill="url(#glow)"/>', tiles(),
         card(-9, -34, 6, "#B9C6CA"), card(6, 30, -2, "#E3EAEC"),
         f'<g transform="translate({fx - 64 * k:.1f} {fy - 64 * k:.1f}) scale({k:.4f})">{inner}</g>',
         mark(120, 84),
         f'<text x="188" y="115" font-family="Inter" font-weight="600" font-size="21" letter-spacing="6" fill="#C9D0D2">VELVET LEAP</text>',
         f'<text x="116" y="232" font-family="Lora" font-weight="700" font-size="72" fill="{WHITE}">Feedback that turns</text>',
         f'<text x="116" y="316" font-family="Lora" font-weight="700" font-size="72" fill="{WHITE}">like a <tspan fill="{ACC_LIGHT}">page.</tspan></text>',
         f'<text x="120" y="378" font-family="Inter" font-weight="400" font-size="29" fill="{MUTED}">Show testimonials one at a time, on a card</text>',
         f'<text x="120" y="418" font-family="Inter" font-weight="400" font-size="29" fill="{MUTED}">that folds like the page of a book.</text>',
         '</svg>']
    return "".join(s)

if __name__ == "__main__":
    out = sys.argv[1]; os.makedirs(out, exist_ok=True)
    with sync_playwright() as p:
        b = p.chromium.launch(); pg = b.new_page(viewport={"width": W, "height": H})
        pg.set_content(f'<body style="margin:0;background:{BG}">' + banner_svg() + "</body>")
        pg.wait_for_timeout(300)
        pg.screenshot(path=f"{out}/banner-1544x500.png"); b.close()
    im = Image.open(f"{out}/banner-1544x500.png").convert("RGB")
    im.save(f"{out}/banner-1544x500.png", optimize=True)
    im.resize((772, 250), Image.LANCZOS).save(f"{out}/banner-772x250.png", optimize=True)
    for f in sorted(os.listdir(out)): print(f, os.path.getsize(f"{out}/{f}") // 1024, "KB")
