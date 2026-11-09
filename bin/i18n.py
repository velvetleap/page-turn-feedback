#!/usr/bin/env python3
"""Rebuilds the translation files from the PHP code.

  python3 bin/i18n.py [--new new_translations.json]

- reads every string wrapped in __(), esc_html__(), esc_attr__(), esc_html_e(), esc_attr_e() with the
  text domain "page-turn-feedback" from page-turn-feedback.php and includes/*.php;
- keeps the Italian translations already in languages/page-turn-feedback-it_IT.po;
- takes the translation of every new string from the JSON file given with --new
  ({"English text": "Testo italiano", ...}) and stops with a list if one is missing;
- writes languages/page-turn-feedback.pot, languages/page-turn-feedback-it_IT.po and the compiled .mo.

Needs the "polib" package (pip install polib). The version is read from the plugin header.
"""
import argparse, datetime, glob, json, os, re, sys
import polib

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG = os.path.join(ROOT, "languages")
SQ = r"'((?:[^'\\]|\\.)*)'"
CALL = re.compile(r"\b(__|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*" + SQ + r"\s*,\s*'page-turn-feedback'\s*\)")
COMMENT = re.compile(r"/\*\s*translators:\s*(.*?)\s*\*/", re.S)


def php_files():
    files = [os.path.join(ROOT, "page-turn-feedback.php")]
    files += sorted(glob.glob(os.path.join(ROOT, "includes", "*.php")))
    return files


def extract():
    items, order = {}, []
    for path in php_files():
        rel = os.path.relpath(path, ROOT)
        src = open(path, encoding="utf-8").read()
        for m in CALL.finditer(src):
            msgid = m.group(2).replace("\\'", "'").replace("\\\\", "\\")
            line = src.count("\n", 0, m.start()) + 1
            before = src[max(0, m.start() - 260):m.start()]
            found = list(COMMENT.finditer(before))
            note = ""
            if found and not re.search(r"__\(|esc_", before[found[-1].end():]):
                note = found[-1].group(1)
            if msgid not in items:
                items[msgid] = {"where": [], "comment": note}
                order.append(msgid)
            items[msgid]["where"].append((rel, line))
            if note and not items[msgid]["comment"]:
                items[msgid]["comment"] = note
    return order, items


def plugin_version():
    head = open(os.path.join(ROOT, "page-turn-feedback.php"), encoding="utf-8").read(2000)
    return re.search(r"^\s*\*\s*Version:\s*(\S+)", head, re.M).group(1)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--new", help="JSON file with the Italian text of the new strings")
    args = ap.parse_args()

    order, items = extract()
    old = {}
    po_path = os.path.join(LANG, "page-turn-feedback-it_IT.po")
    if os.path.exists(po_path):
        old = {e.msgid: e.msgstr for e in polib.pofile(po_path) if e.msgstr}
    new = json.load(open(args.new, encoding="utf-8")) if args.new else {}
    italian = {}
    missing = []
    for msgid in order:
        if msgid in new:
            italian[msgid] = new[msgid]
        elif msgid in old:
            italian[msgid] = old[msgid]
        else:
            missing.append(msgid)
    if missing:
        print("Missing Italian translation for:")
        for m in missing:
            print("  ", repr(m))
        sys.exit(1)

    today = datetime.date.today().strftime("%Y-%m-%d") + " 00:00+0000"

    def build(path, lang):
        po = polib.POFile(wrapwidth=0)
        po.metadata = {
            "Project-Id-Version": "Page Turn Feedback " + plugin_version(),
            "POT-Creation-Date": today,
            "PO-Revision-Date": today,
            "Last-Translator": "Mattia Scudellaro",
            "Language-Team": "Velvet Leap",
            "MIME-Version": "1.0",
            "Content-Type": "text/plain; charset=UTF-8",
            "Content-Transfer-Encoding": "8bit",
            "Plural-Forms": "nplurals=2; plural=n != 1;",
            "X-Domain": "page-turn-feedback",
        }
        if lang:
            po.metadata["Language"] = lang
        po.header = ("Page Turn Feedback\nCopyright (C) 2026 Mattia Scudellaro\n"
                     "This file is distributed under the same license as the Page Turn Feedback plugin (GPL-2.0-or-later).")
        for msgid in order:
            entry = polib.POEntry(
                msgid=msgid,
                msgstr=italian[msgid] if lang else "",
                occurrences=[(f, str(n)) for f, n in items[msgid]["where"]],
                comment=("translators: " + items[msgid]["comment"]) if items[msgid]["comment"] else "",
            )
            if re.search(r"%(\d+\$)?[sd]", msgid):
                entry.flags.append("php-format")
            po.append(entry)
        po.save(path)
        return po

    build(os.path.join(LANG, "page-turn-feedback.pot"), None)
    po = build(po_path, "it_IT")
    po.save_as_mofile(os.path.join(LANG, "page-turn-feedback-it_IT.mo"))

    # The placeholders (%d, %s, %1$d...) must be the same in the English and Italian text.
    bad = 0
    for msgid in order:
        placeholders = lambda t: sorted(re.findall(r"%(?:\d+\$)?[sd]", t))
        if placeholders(msgid) != placeholders(italian[msgid]):
            print("Placeholders differ:", repr(msgid), "->", repr(italian[msgid]))
            bad += 1
    print("%d strings, %d new, placeholders differing: %d" % (len(order), len([m for m in order if m not in old]), bad))
    sys.exit(1 if bad else 0)


main()
