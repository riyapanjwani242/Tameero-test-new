# Install — Windows

1. `WebDevHub_Setup_v2.0.0.exe` chalao (release ke sath SHA-256 diya jayega).
2. Wizard: folder choose karo → Install. Default per-user install hai,
   admin ki zaroorat nahi.
3. Abhi v2 **unsigned** hai (certificate owner ke faislay par pending) —
   SmartScreen aye to "More info → Run anyway" — ye maloom haalat hai,
   chhupai nahi gayi. Certificate lagte hi ye warning khatam ho jayegi.
4. Panel/launcher abhi baad ke phase mein hai; Windows packaging ka asal
   test GitHub Actions par hota hai (installer compile + silent install).

Note: Windows build ka full live test (site banana + chalana) release se
pehle owner/dost ke PC par ek manual nazar se hoga — CI us ka badal nahi.
