/* TAMEERO Dashboard — panel.js (v2.8)
   1) Project search filter  2) Import upload progress (sathi server)
   3) AI chat rail (gateway -> MCP approval flow) */
(function () {
  'use strict';

  /* ---------- 1) Search filter (Home cards) ---------- */
  var q = document.getElementById('q');
  if (q) {
    q.addEventListener('input', function () {
      var v = q.value.toLowerCase();
      document.querySelectorAll('.card[data-name]').forEach(function (c) {
        c.style.display = (c.getAttribute('data-name') || '').indexOf(v) >= 0 ? '' : 'none';
      });
    });
  }

  /* ---------- Rail toggle (chhoti screen) ---------- */
  var rail = document.getElementById('chatRail');
  var tog = document.getElementById('railToggle');
  if (tog && rail) { tog.addEventListener('click', function () { rail.classList.toggle('open'); }); }

  /* ---------- 2) Import - chunked upload (v2.8.14) ---------- */
  /* Bari zip 8 MiB tukron mein jati hai (raw octet-stream, aik waqt
     mein 1 chunk, har chunk ka SHA-256). Aadha upload ruk jaye to
     status se resume hota hai; finalize par server zip jor kar
     worker chala deta hai. base khaali ho (Linux / companion band)
     to wahi raasta same-origin chalta hai. */
  var form = document.getElementById('importForm');
  if (form) {
    var base = form.getAttribute('data-upload-base') || '';
    var csrfInp = form.querySelector('input[name="csrf"]');
    var csrfTok = csrfInp ? csrfInp.value : '';
    form.addEventListener('submit', function (ev) {
      var fileInp = document.getElementById('importFile');
      if (!fileInp || !fileInp.files || !fileInp.files.length) { return; }
      ev.preventDefault();
      var file = fileInp.files[0];
      var nameInp = form.querySelector('input[name="name"]');
      var siteName = nameInp ? nameInp.value : '';
      var box = document.getElementById('importProgress');
      var bar = document.getElementById('importBar');
      var txt = document.getElementById('importProgressText');
      if (box) { box.style.display = 'block'; }
      function setPc(pc, msg) {
        if (bar) { bar.style.width = pc + '%'; }
        if (txt) { txt.textContent = msg; }
      }
      if (!window.crypto || !crypto.subtle) {
        setPc(0, 'Chunked upload ke liye is browser mein crypto nahi hai - import nahi ho sakta.');
        return;
      }
      var CHUNK = 8 * 1024 * 1024;
      var total = Math.max(1, Math.ceil(file.size / CHUNK));
      var storeKey = 'tameero-import-' + file.name + '-' + file.size;
      var uploadId = '';
      try { uploadId = window.localStorage.getItem(storeKey) || ''; } catch (e) {}
      if (!uploadId) {
        if (crypto.randomUUID) { uploadId = crypto.randomUUID(); }
        else { uploadId = 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12); }
        try { window.localStorage.setItem(storeKey, uploadId); } catch (e) {}
      }
      function hex(buf) {
        var b = new Uint8Array(buf), s = '';
        for (var i = 0; i < b.length; i++) { s += ('0' + b[i].toString(16)).slice(-2); }
        return s;
      }
      function chunkUrl() { return (base || '') + '/'; }
      function postChunk(idx, triesLeft) {
        var start = idx * CHUNK;
        var slice = file.slice(start, Math.min(file.size, start + CHUNK));
        return slice.arrayBuffer().then(function (buf) {
          return crypto.subtle.digest('SHA-256', buf);
        }).then(function (dig) {
          return fetch(chunkUrl(), {
            method: 'POST',
            credentials: 'include',
            headers: {
              'X-WDH-Action': 'import_chunk',
              'X-CSRF-Token': csrfTok,
              'X-Upload-Id': uploadId,
              'X-Chunk-Index': String(idx),
              'X-Chunk-Total': String(total),
              'X-Chunk-Sha256': hex(dig),
              'Content-Type': 'application/octet-stream'
            },
            body: slice
          }).then(function (r) {
            if (!r.ok) { throw new Error('chunk ' + idx + ' HTTP ' + r.status); }
            return r.json();
          }).then(function (j) {
            if (!j || !j.ok) { throw new Error('chunk ' + idx + ' reject hua'); }
            setPc(Math.round((idx + 1) / total * 100), 'Upload ho raha hai... ' + (idx + 1) + '/' + total + ' chunks');
          });
        }).catch(function (err) {
          if (triesLeft > 0) { return postChunk(idx, triesLeft - 1); }
          throw err;
        });
      }
      function sendFrom(idx, received) {
        if (idx >= total) { return Promise.resolve(); }
        if (received && received.indexOf(idx) >= 0) { return sendFrom(idx + 1, received); }
        return postChunk(idx, 2).then(function () { return sendFrom(idx + 1, received); });
      }
      fetch(chunkUrl(), {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-WDH-Action': 'import_status',
          'X-CSRF-Token': csrfTok,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ upload_id: uploadId })
      }).then(function (r) { return r.json(); }).catch(function () { return { received: [] }; }).then(function (st) {
        var received = (st && st.received) ? st.received : [];
        return sendFrom(0, received);
      }).then(function () {
        setPc(100, 'Chunks mukammal - zip jor raha hoon...');
        return fetch(chunkUrl(), {
          method: 'POST',
          credentials: 'include',
          headers: {
            'X-WDH-Action': 'import_finalize',
            'X-CSRF-Token': csrfTok,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ upload_id: uploadId, name: siteName, total_chunks: total, total_size: file.size })
        }).then(function (r) { return r.json(); });
      }).then(function (j) {
        if (!j || !j.redirect) { throw new Error((j && j.error) || 'Finalize reject hua'); }
        try { window.localStorage.removeItem(storeKey); } catch (e) {}
        if (txt) { txt.textContent = 'Upload mukammal - site ban rahi hai...'; }
        window.location = j.redirect;
      }).catch(function (err) {
        setPc(0, 'Upload mein kharabi: ' + (err && err.message ? err.message : 'dobara koshish karein.'));
      });
    });
  }

  /* ---------- 2b) AI Connection settings (v2.9.0) ---------- */
  var provSel = document.getElementById('chatProvider');
  if (provSel) {
    var urlInp = document.getElementById('chatGwUrl');
    var keyInp = document.getElementById('chatGwKey');
    var modelInp = document.getElementById('chatModel');
    var modelList = document.getElementById('chatModelList');
    var resultEl = document.getElementById('chatConnResult');
    var setForm = document.getElementById('chatSettingsForm');
    var setCsrf = '';
    if (setForm) { var ci = setForm.querySelector('input[name="csrf"]'); if (ci) { setCsrf = ci.value; } }
    function presetUrl() {
      var opt = provSel.options[provSel.selectedIndex];
      return opt ? (opt.getAttribute('data-url') || '') : '';
    }
    provSel.addEventListener('change', function () {
      var u = presetUrl();
      if (u && urlInp) { urlInp.value = u; }
      if (resultEl) { resultEl.textContent = ''; resultEl.className = 'meta'; }
    });
    if (urlInp) {
      urlInp.addEventListener('input', function () {
        var u = presetUrl();
        if (provSel.value !== 'custom' && u && urlInp.value.trim() !== u) { provSel.value = 'custom'; }
      });
    }
    function connPost(view, extra) {
      var payload = { csrf: setCsrf, url: urlInp ? urlInp.value.trim() : '', key: keyInp ? keyInp.value : '' };
      Object.keys(extra || {}).forEach(function (k) { payload[k] = extra[k]; });
      return fetch('/?view=' + view, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function (r) { return r.json(); });
    }
    function showResult(text, cls) {
      if (!resultEl) { return; }
      resultEl.textContent = text;
      resultEl.className = 'meta ' + (cls || '');
    }
    var modelsBtn = document.getElementById('chatModelsBtn');
    if (modelsBtn) {
      modelsBtn.addEventListener('click', function () {
        modelsBtn.disabled = true;
        showResult('Models load ho rahe hain…', '');
        connPost('chat_models', {}).then(function (j) {
          if (j.error) { showResult('⚠ ' + j.error, 'connbad'); return; }
          var models = j.models || [];
          if (modelList) {
            modelList.innerHTML = '';
            models.forEach(function (m) {
              var o = document.createElement('option');
              o.value = m;
              modelList.appendChild(o);
            });
          }
          showResult('✓ ' + models.length + ' models mil gaye — Model khane mein likhna shuru karein, list se chun lein.', 'connok');
          if (modelInp) { modelInp.focus(); }
        }).catch(function () {
          showResult('⚠ Models load nahi hue — dashboard ka connection check karein.', 'connbad');
        }).finally(function () { modelsBtn.disabled = false; });
      });
    }
    var testBtn = document.getElementById('chatTestBtn');
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        testBtn.disabled = true;
        showResult('Connection test ho raha hai…', '');
        connPost('chat_test', { model: modelInp ? modelInp.value.trim() : '' }).then(function (j) {
          if (j.error) { showResult('⚠ ' + j.error, 'connbad'); return; }
          showResult((j.ok ? '✓ ' : '✗ ') + (j.message || ''), j.ok ? (j.partial ? 'connpart' : 'connok') : 'connbad');
        }).catch(function () {
          showResult('⚠ Test nahi ho saka — dashboard ka connection check karein.', 'connbad');
        }).finally(function () { testBtn.disabled = false; });
      });
    }
  }

  /* ---------- 3) AI chat rail ---------- */
  if (!rail) { return; }
  var csrf = rail.getAttribute('data-csrf') || '';
  var msgs = document.getElementById('chatMsgs');
  var inp = document.getElementById('chatInp');
  var sendBtn = document.getElementById('chatSend');
  var stEl = document.getElementById('chatStatus');
  var pollTimers = {};

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function scrollDown() { if (msgs) { msgs.scrollTop = msgs.scrollHeight; } }
  function bubble(cls, html) {
    var d = document.createElement('div');
    d.className = 'm ' + cls;
    d.innerHTML = html;
    msgs.appendChild(d);
    scrollDown();
    return d;
  }

  function toolChip(ev) {
    var label = { done: '✓ ho gaya', pending: 'manzoori baqi', error: '✗ kharabi', rejected: '✗ reject' }[ev.status] || ev.status;
    return '<div class="tool" data-aid="' + esc(ev.action_id || '') + '">🔧 ' + esc(ev.name) +
      ' <span class="stt ' + esc(ev.status) + '">' + label + '</span>' +
      (ev.summary ? '<br><span style="color:#7d93b8">' + esc(ev.summary) + '</span>' : '') +
      '<span class="apprSlot"></span></div>';
  }

  function renderEvents(container, events) {
    (events || []).forEach(function (ev) {
      if (ev.type !== 'tool') { return; }
      var tmp = document.createElement('div');
      tmp.innerHTML = toolChip(ev);
      var chip = tmp.firstChild;
      container.appendChild(chip);
      if (ev.status === 'pending' && ev.action_id) { attachApproval(chip, ev.action_id); watchAction(ev.action_id); }
    });
  }

  function attachApproval(chip, aid) {
    var slot = chip.querySelector('.apprSlot');
    if (!slot || slot.dataset.done) { return; }
    slot.dataset.done = '1';
    // Pehle action ki tafseel lao — destructive ho to confirm khana lazmi hai
    fetch('/?view=chat_poll&ids=' + encodeURIComponent(aid), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var rec = (j.actions || [])[0] || {};
        if (rec.status !== 'pending') { return; }
        var html = '<span class="appr">';
        if (rec.class === 'destructive') {
          html += '<input type="text" class="confirmInp" placeholder="Confirm: site ka naam likhein" autocomplete="off">';
        }
        html += '<button class="ok" type="button">✓ Approve</button><button class="no" type="button">Reject</button></span>';
        slot.innerHTML = html;
        slot.querySelector('.ok').addEventListener('click', function () { decide(chip, aid, true); });
        slot.querySelector('.no').addEventListener('click', function () { decide(chip, aid, false); });
      })
      .catch(function () {});
  }

  function decide(chip, aid, approve) {
    var inpC = chip.querySelector('.confirmInp');
    var body = new URLSearchParams();
    body.set('csrf', csrf);
    body.set('action', approve ? 'mcp_approve' : 'mcp_reject');
    body.set('action_id', aid);
    if (inpC) { body.set('confirm', inpC.value); }
    fetch('/', { method: 'POST', credentials: 'same-origin', body: body })
      .then(function () { watchAction(aid); })
      .catch(function () {});
  }

  function watchAction(aid) {
    if (pollTimers[aid]) { return; }
    var tries = 0;
    pollTimers[aid] = setInterval(function () {
      tries++;
      fetch('/?view=chat_poll&ids=' + encodeURIComponent(aid), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var rec = (j.actions || [])[0];
          if (!rec) { return; }
          if (rec.status !== 'pending' || tries > 45) {
            clearInterval(pollTimers[aid]);
            delete pollTimers[aid];
            updateChip(aid, rec);
          }
        })
        .catch(function () {});
    }, 4000);
  }

  function updateChip(aid, rec) {
    document.querySelectorAll('.tool[data-aid="' + aid + '"]').forEach(function (chip) {
      var stt = chip.querySelector('.stt');
      if (stt) {
        stt.className = 'stt ' + rec.status;
        stt.textContent = { done: '✓ ho gaya', error: '✗ kharabi', rejected: '✗ reject', expired: '⏱ waqt khatam' }[rec.status] || rec.status;
      }
      var slot = chip.querySelector('.apprSlot');
      if (slot) { slot.innerHTML = ''; }
      if (rec.status === 'error' && rec.error) {
        var e = document.createElement('div');
        e.style.color = '#f0a8a2';
        e.textContent = rec.error;
        chip.appendChild(e);
      }
    });
    refreshPing();
  }

  function refreshPing() {
    fetch('/?view=chat_ping', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!stEl) { return; }
        if (!j.gateway) { stEl.textContent = '● AI band hai'; stEl.className = 'st off'; }
        else if (!j.mcp_enabled) { stEl.textContent = '● MCP off'; stEl.className = 'st off'; }
        else { stEl.textContent = '● MCP live'; stEl.className = 'st'; }
        var pill = document.getElementById('agentPill');
        if (pill) {
          pill.innerHTML = 'Agent: <b>' + (j.mcp_running ? '● Connected' : '○ Band') + '</b>' +
            (j.pending ? ' · <span class="pend">' + j.pending + ' manzoori baqi</span>' : '');
        }
        var bd = document.getElementById('agentBadge');
        if (bd) { bd.textContent = j.pending || ''; bd.style.display = j.pending ? '' : 'none'; }
        var bw = document.getElementById('chatBrainWarn');
        if (bw) { bw.hidden = j.brain !== false; }
      })
      .catch(function () {});
  }

  function loadHistory() {
    fetch('/?view=chat_history', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        (j.messages || []).forEach(function (m) {
          if (m.role === 'user') { bubble('u', esc(m.content)); }
          else if (m.role === 'assistant') {
            var b = bubble('a', '');
            renderEvents(b, m.events || []);
            if (m.content) { var t = document.createElement('div'); t.textContent = m.content; b.appendChild(t); }
          }
        });
        if (!j.messages || !j.messages.length) {
          bubble('sys', 'AI se kuch bhi kahen — projects ki list, site start/stop, nayi site ki tajveez. Likhne wale kaam manzoori ke baghair nahi hote.');
        }
        scrollDown();
      })
      .catch(function () {});
  }

  function send() {
    var text = (inp.value || '').trim();
    if (!text) { return; }
    inp.value = '';
    bubble('u', esc(text));
    sendBtn.disabled = true;
    var thinking = bubble('a', 'Socho raha hoon…');
    fetch('/?view=chat_send', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: csrf, text: text })
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        thinking.remove();
        if (j.error) { bubble('a', '⚠ ' + esc(j.error)); return; }
        var b = bubble('a', '');
        renderEvents(b, j.events || []);
        if (j.text) { var t = document.createElement('div'); t.textContent = j.text; b.appendChild(t); }
        scrollDown();
        refreshPing();
      })
      .catch(function () {
        thinking.remove();
        bubble('a', '⚠ Jawab nahi aya — dashboard ka connection check karein.');
      })
      .finally(function () { sendBtn.disabled = false; inp.focus(); });
  }

  if (sendBtn) { sendBtn.addEventListener('click', send); }
  if (inp) {
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { send(); } });
  }
  loadHistory();
  refreshPing();
  setInterval(refreshPing, 30000);
})();
