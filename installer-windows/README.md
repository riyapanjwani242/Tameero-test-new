# Windows Installer (Inno Setup)

- Script: `webdevhub.iss` — Inno Setup 6.x
- Build/test: **GitHub Actions** `windows-build.yml` (Windows runner par
  compile + silent-install smoke). Owner/dost ke PC par har fix ke liye
  test NAHI hota — sirf release se pehle ek manual nazar.
- Jo Windows par test na ho, usay "complete" nahi kaha jayega.
- Manual build (Windows PC par): Inno Setup 6 kholo, `webdevhub.iss`
  compile karo — pehle `bundle/` tayyar hona chahiye (fetch-bundle).
- Panel/launcher EXE (`WebDevHub.exe`) baad ke phase ka kaam hai; tab tak
  script ka `[Files]` panel section placeholder hai — CI sirf bundle
  portion verify karta hai, launcher nahi.
- Signing: `docs/code-signing.md` dekho.
