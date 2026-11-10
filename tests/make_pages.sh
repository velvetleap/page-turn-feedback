#!/usr/bin/env bash
# Writes the test pages to tests/out/ (ignored by git).
#   - front-end pages: the section rendered by the real plugin code inside a copy of the
#     Fascinate theme structure (container, footer, aggressive theme styles in fixtures/theme.css);
#   - admin pages: the real settings screen, once in English and once in Italian.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p out

# page NAME ITEMS INTERVAL_MS STYLE_JSON   (extra settings in LSA_OPTS, long texts with PTFB_LONG=1)
page() {
	local name=$1 n=$2 interval=$3 style=$4
	{
		echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../fixtures/theme.css"><link rel="stylesheet" href="../../assets/page-turn-feedback.css"></head><body><div class="content"><div class="fb-container"><div class="posts">(elenco articoli)</div></div></div>'
		php render.php "$n" "$interval" "$style"
		echo '<footer class="footer"><div class="fb-container"><div class="row"><div class="col-lg-4"><h3>Articoli recenti</h3><p>Esempio di articolo recente</p></div><div class="col-lg-4"><h3>Facebook box</h3><p>Nome del sito di esempio</p></div><div class="col-lg-4"><h3>Collegamenti rapidi</h3><p>Contatti</p></div></div></div></footer><script src="../../assets/page-turn-feedback.js"></script></body></html>'
	} > "out/$name.html"
}

page p_5_slow        5 60000 '{}'
page p_align_default 5 60000 '{}'
page p_5_1000        5 1000  '{}'
page p_1             1 60000 '{}'
LSA_OPTS='{"ptfb_anim":"0.6"}' page p_anim06 5 60000 '{"align_text":"right","align_name":"right"}'
LSA_OPTS='{"ptfb_anim":"2.6"}' page p_anim26 5 60000 '{"align_name":"center"}'
LSA_OPTS='{"ptfb_anim":"custom","ptfb_anim_custom":"1.7"}' page p_animc 5 60000 '{}'
PTFB_LONG=1 page p_longword 4 60000 '{"align_name":"center"}'

PTFB_LANG=en php render_settings.php > out/admin_settings_en.html
PTFB_LANG=it php render_settings.php > out/admin_settings_it.html
# settings with feedback on the site (the preview shows them) and the Order page (5 feedback, the site shows 3)
PTFB_ITEMS=4 PTFB_LANG=en php render_settings.php > out/admin_settings_items.html
PTFB_PAGE=order PTFB_ITEMS=5 PTFB_MAX=3 PTFB_LANG=en php render_settings.php > out/admin_order_en.html
PTFB_PAGE=order PTFB_ITEMS=5 PTFB_MAX=3 PTFB_LANG=it php render_settings.php > out/admin_order_it.html
echo "pages written to tests/out/"
