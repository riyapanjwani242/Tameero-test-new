# Code-Signing ki Tayyari

## Haalat abhi
- v1 unsigned tha — Windows SmartScreen "unknown publisher" warning deta tha.
- v2 ka installer **signing-ready** hai: mukammal version metadata, fixed
  AppId, aur `.iss` mein SignTool wali jagah tayyar (commented) hai.

## Jo tayyar hai (bina certificate ke ho sakta tha)
- `installer-windows/webdevhub.iss` mein:
  - `VersionInfoVersion / Company / Description` mukammal
  - `; SignTool=webdevhub` aur `; SignedUninstaller=yes` — enable karna baki hai
- Release ke waqt installer ke sath **SHA-256 hash publish** hoga taake
  user file verify kar sake (Drive-password wala tareeqa khatam).

## Jo OWNER ka faisla hai (agent khud nahi karega)
Asal code-signing certificate khareedna (OV/EV) — ye paisay + identity
verification wala mamla hai, owner/dost partnership ke naam par hoga.
Certificate mil jaye to steps:
1. Inno Setup mein Sign Tool register: naam `webdevhub`
2. `.iss` ki do commented lines enable karna
3. CI (GitHub Actions) mein certificate secret ke tor par — secret owner
   khud GitHub settings mein dalega.

Tab tak har Windows build saaf **"unsigned"** label ke sath jayega.
