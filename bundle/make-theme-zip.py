#!/usr/bin/env python3
"""Dukaan theme ka bundle zip banata hai — fetch-bundle.sh (Linux) aur
windows-build.yml ka PowerShell fetch step (Windows CI) DONO isi ko
chalate hain taake har build mein ek jaisa zip bane.

Usage: python3 make-theme-zip.py <source-dir> <out-zip> <top-folder>

Shamil NAHI hota: test-shots* folders (test screenshots), *.zip files,
.git — ye sirf repo/test ke liye hain, product ka hissa nahi.
"""
import os
import sys
import zipfile

src, out, top = sys.argv[1], sys.argv[2], sys.argv[3]

def excluded(rel_parts):
    for part in rel_parts:
        if part.startswith("test-shots") or part == ".git" or part == "BRIEF.md":
            return True
    return rel_parts[-1].endswith(".zip")

entries = []
for root, dirs, files in os.walk(src):
    dirs.sort()
    for name in sorted(files):
        full = os.path.join(root, name)
        rel = os.path.relpath(full, src)
        parts = rel.split(os.sep)
        if excluded(parts):
            continue
        entries.append((full, top + "/" + "/".join(parts)))

os.makedirs(os.path.dirname(out), exist_ok=True)
if os.path.exists(out):
    os.remove(out)
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for full, arc in entries:
        z.write(full, arc)
print(f"Theme zip tayyar: {out} ({len(entries)} files)")
