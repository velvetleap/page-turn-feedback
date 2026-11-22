"""Checks the Playground blueprint behind the "Live Preview" button of the WordPress.org plugin page
(.wordpress-org/blueprints/blueprint.json): it is the file design/blueprint.py writes, it installs this plugin
from the directory, and its PHP is valid. Optionally validates it against the official schema when the file
named by BLUEPRINT_SCHEMA exists (package @wp-playground/blueprints, file blueprint-schema.json) and jsonschema is installed."""
import importlib.util
import json
import os
import subprocess
import sys
import tempfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
failed = 0


def check(label, ok):
    global failed
    print(("PASS " if ok else "FAIL ") + label)
    if not ok:
        failed = 1


committed_path = os.path.join(ROOT, ".wordpress-org", "blueprints", "blueprint.json")
check("the blueprint file exists", os.path.isfile(committed_path))
with open(committed_path, encoding="utf-8") as f:
    raw = f.read()
bp = json.loads(raw)

# The plugin folder name is the slug (always equal to the main file name).
slug = next(n[:-4] for n in os.listdir(ROOT) if n.endswith(".php") and n != "uninstall.php")

# It is exactly what design/blueprint.py writes: nobody edited it by hand.
spec = importlib.util.spec_from_file_location("ptfb_blueprint", os.path.join(ROOT, "design", "blueprint.py"))
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
with tempfile.TemporaryDirectory() as tmp:
    old_argv, sys.argv = sys.argv, ["blueprint.py", tmp]
    try:
        out = os.path.join(tmp, "blueprints")
        os.makedirs(out)
        with open(os.path.join(out, "blueprint.json"), "w", encoding="utf-8") as f:
            json.dump(mod.blueprint(), f, indent=2, ensure_ascii=False)
            f.write("\n")
        with open(os.path.join(out, "blueprint.json"), encoding="utf-8") as f:
            check("the file is what design/blueprint.py writes", f.read() == raw)
    finally:
        sys.argv = old_argv

steps = bp.get("steps", [])
check("the landing page is a path of the test site", str(bp.get("landingPage", "")).startswith("/"))
check("the first step logs in", bool(steps) and steps[0].get("step") == "login")
installs = [s for s in steps if s.get("step") == "installPlugin"]
check("it installs one plugin", len(installs) == 1)
if installs:
    data = installs[0].get("pluginData", {})
    check("it installs this plugin from the WordPress.org directory",
          data.get("resource") == "wordpress.org/plugins" and data.get("slug") == slug)
    check("and activates it", installs[0].get("options", {}).get("activate") is True)
check("the plugin is installed before the sample content is added",
      [s["step"] for s in steps].index("installPlugin") < [s["step"] for s in steps].index("runPHP"))

code = next((s.get("code", "") for s in steps if s.get("step") == "runPHP"), "")
check("the sample content step exists", code.startswith("<?php"))
with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False) as t:
    t.write(code)
res = subprocess.run(["php", "-l", t.name], capture_output=True, text=True)
os.unlink(t.name)
check("its PHP has no syntax error", res.returncode == 0)
check("it adds six feedback and a front page with the shortcode",
      code.count("array(") >= 7 and "[page_turn_feedback]" in code and "page_on_front" in code)
check("no remote address in the sample content", "http://" not in code and "https://" not in code)

schema_file = os.environ.get("BLUEPRINT_SCHEMA", "")
if schema_file and os.path.isfile(schema_file):
    try:
        import jsonschema
        with open(schema_file, encoding="utf-8") as f:
            schema = json.load(f)
        errors = list(jsonschema.Draft7Validator(schema).iter_errors(bp))
        check("it is valid against the official Blueprint schema", not errors)
    except ImportError:
        print("SKIP the schema check (jsonschema is not installed)")
else:
    print("SKIP the schema check (set BLUEPRINT_SCHEMA to the blueprint-schema.json file)")

print()
print("blueprint test: " + ("FAILED" if failed else "all checks passed"))
sys.exit(failed)
