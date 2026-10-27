# Install — Linux (Reference)

```bash
bash bundle/fetch-bundle.sh
bash installer-linux/install.sh --site mysite --blueprint business --port 8080
bash installer-linux/webdevhub.sh start mysite
```

- Browser: `http://127.0.0.1:8080` — Admin: `/wp-admin/`
- Admin password install ke waqt terminal par khud set hota hai.
- Sites default `~/workspace/webdevhub/sites/` ke andar banti hain
  (env `WDH_SITES_DIR` se badal sakte hain).
- Band karna: `bash installer-linux/webdevhub.sh stop mysite`
