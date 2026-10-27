# GitHub Repo Setup — Owner ke steps

Repo **private** hoga, account: `sahilali2550`. Ye steps owner khud karega
(login/credentials agent kabhi nahi chhoota).

1. github.com par login karke **New repository** dabao.
2. Name ye rakho:

```
webdevhub
```

3. **Private** select karo, "Create repository" dabao (README/gitignore wahan
   add NAHI karna — wo is project mein pehle se hain).
4. Dost **gulambarkhan1** ko collaborator add karo:
   Repo → Settings → Collaborators → Add people → dost ka GitHub username.
5. Repo ban jaye to bas itna bata do ke "repo ban gaya" — pehla push
   (saara code + structure) main isi chat se kar dunga, jis ke liye
   GitHub ka connected access istemal hoga.

Local project isi structure ke sath tayyar hai:

```
bundle/  installer-windows/  installer-linux/  panel/  docs/  tests/
.github/workflows/  (Linux smoke + Windows build CI)
```
