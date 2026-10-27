# Dashboard Vision — Owner ki wazahat (2026-10-27)

Owner ke apne alfaaz mein is software ka asal maqsad:

> "Ye software is liye banaya ja raha hai ke WordPress direct PC par run
> kiya jaye — XAMPP ya htdocs folder mein jana na pare — direct dashboard
> par WordPress ke saare projects aa jayen aur un par kaam kare."

## Is ka matlab hamare design ke liye

1. **Dashboard product ka DIL hai** — koi baad wala extra nahi. Saare
   WordPress projects ek dashboard par: list, open, start/stop, create —
   sab wahi se.
2. Bundle/installer dashboard ki **khidmat** ke liye hai — engine peeche,
   dashboard aage.
3. XAMPP/htdocs model khatam: sites WebDevHub ki apni per-user folder
   mein, SQLite (koi MySQL service nahi), har site ka apna local server.

## Class wale version se taaluq (note)

Owner ki Zoom class (teacher: Hania Khan) mein **Siddeeque Qasmi** bilkul
yahi cheez bana raha hai: dashboard software + `launch_hub.vbs` silent
launcher + "universal WebDevHub installer" (2026-10-27 ko class mein
compile hua; hidayat: tool universal ho, kisi bhi computer par asani se
install ho).

Pattern ek hi hai: **silent launcher + post-install script + dashboard**.
Hamara v2 bundle/installer isi pattern par dashboard ke sath jurega.
Class version aur hamare version ke **sync/merge ka faisla owner ka hai** —
tab tak hum apna dashboard apne hi bundle par banate hain.
