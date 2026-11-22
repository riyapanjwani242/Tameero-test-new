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

  /* ---------- Mobile nav drawer (v2.10 Phase 6) ---------- */
  var navT = document.getElementById('navToggle');
  var navS = document.getElementById('navScrim');
  function navSet(open) { document.body.classList.toggle('navopen', !!open); }
  if (navT) { navT.addEventListener('click', function () { navSet(!document.body.classList.contains('navopen')); }); }
  if (navS) { navS.addEventListener('click', function () { navSet(false); }); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { navSet(false); } });
  document.querySelectorAll('.side a').forEach(function (a) { a.addEventListener('click', function () { navSet(false); }); });

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
  var S = {};
  try { S = JSON.parse(rail.getAttribute('data-strings') || '{}'); } catch (e) { S = {}; }
  function T(k, fb) { return (S && S[k]) ? S[k] : (fb || ''); }
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
    var label = { done: T('stDone'), pending: T('stPending'), error: T('stError'), rejected: T('stRejected') }[ev.status] || ev.status;
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
          html += '<input type="text" class="confirmInp" placeholder="' + esc(T('confirmPh')) + '" autocomplete="off">';
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
        stt.textContent = { done: T('stDone'), error: T('stError'), rejected: T('stRejected'), expired: T('stExpired') }[rec.status] || rec.status;
      }
      var slot = chip.querySelector('.apprSlot');
      if (slot) { slot.innerHTML = ''; }
      if (rec.tool === 'image_generate' && rec.status === 'done' && rec.result) {
        try {
          var res = JSON.parse(rec.result);
          if (res && res.generated && res.job) { showImgCard(chip, res); }
        } catch (e) {}
      }
      if (rec.status === 'error' && rec.error) {
        var e = document.createElement('div');
        e.style.color = '#f0a8a2';
        e.textContent = rec.error;
        chip.appendChild(e);
      }
    });
    refreshPing();
  }

  function showImgCard(chip, res) {
    if (chip.querySelector('.imgcard')) { return; }
    var card = document.createElement('div');
    card.className = 'imgcard';
    var provLabel = res.provider === 'gemini' ? 'Gemini' : 'Gateway (9Router)';
    var html = '<img src="/?view=img_file&job=' + encodeURIComponent(res.job) + '" alt="">' +
      '<div class="imeta"><span class="mdl">' + esc(res.model || '') + '</span><span>·</span><span>' + esc(res.size || '') + '</span><span>·</span><span>' + esc(res.seconds || '') + ' sec</span><span>·</span><span>' + esc(provLabel) + '</span></div>';
    if (!res.imported && res.note) { html += '<div class="inote">' + esc(res.note) + '</div>'; }
    html += '<div class="ibtns"><button type="button" class="ibtn gold act-attach"></button>' +
      '<button type="button" class="ibtn act-regen">' + esc(T('imgRegen')) + '</button>' +
      '<button type="button" class="ibtn act-dismiss">' + esc(T('imgDismiss')) + '</button></div>' +
      '<div class="itarget" hidden><label>' + esc(T('imgPick')) + '</label><select class="tsel"></select></div>' +
      '<div class="istatus"></div>';
    card.innerHTML = html;
    chip.appendChild(card);
    scrollDown();
    var purpose = res.purpose || 'media';
    var attachBtn = card.querySelector('.act-attach');
    attachBtn.textContent = purpose === 'post' ? T('imgAttachPost') : T('imgAttachProduct');
    var statusEl = card.querySelector('.istatus');
    function doAttach(targetId) {
      attachBtn.disabled = true;
      fetch('/?view=img_attach', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: csrf, site: res.site, image_id: res.attachment_id, target_id: targetId })
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) { card.innerHTML = '<div class="isuccess">' + esc(T('imgAttached')) + '</div>'; }
          else { statusEl.textContent = '⚠ ' + (j.error || ''); attachBtn.disabled = false; }
        })
        .catch(function () { statusEl.textContent = '⚠ network error'; attachBtn.disabled = false; });
    }
    attachBtn.addEventListener('click', function () {
      if (!res.attachment_id) { statusEl.textContent = T('imgSaved'); return; }
      if (res.target_id) { doAttach(res.target_id); return; }
      var tbox = card.querySelector('.itarget');
      var sel = card.querySelector('.tsel');
      if (!tbox.hidden) { if (sel.value) { doAttach(parseInt(sel.value, 10)); } return; }
      fetch('/?view=img_targets&site=' + encodeURIComponent(res.site || ''), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var items = purpose === 'post' ? (j.posts || []) : (j.products || []);
          if (purpose === 'media') { items = (j.products || []).concat(j.posts || []); }
          sel.innerHTML = '';
          items.forEach(function (it) {
            var o = document.createElement('option');
            o.value = it.id; o.textContent = it.name;
            sel.appendChild(o);
          });
          if (!items.length) { statusEl.textContent = T('imgSaved'); return; }
          tbox.hidden = false;
          attachBtn.textContent = '✔ OK';
        })
        .catch(function () {});
    });
    card.querySelector('.act-regen').addEventListener('click', function () {
      if (!res.prompt) { return; }
      inp.value = (T('imgRegenFmt') || 'Dobara banao: %s').replace('%s', res.prompt);
      send();
    });
    card.querySelector('.act-dismiss').addEventListener('click', function () {
      card.className = 'imgsaved';
      card.innerHTML = esc(T('imgSaved'));
    });
  }

  function refreshPing() {
    fetch('/?view=chat_ping', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!stEl) { return; }
        if (!j.gateway) { stEl.textContent = T('aiOff'); stEl.className = 'st off'; }
        else if (!j.mcp_enabled) { stEl.textContent = T('mcpOff'); stEl.className = 'st off'; }
        else { stEl.textContent = T('mcpLive'); stEl.className = 'st'; }
        var pill = document.getElementById('agentPill');
        if (pill) {
          pill.innerHTML = T('agentLabel') + '<b>' + (j.mcp_running ? T('agentOn') : T('agentOff')) + '</b>' +
            (j.pending ? ' · <span class="pend">' + j.pending + T('pendingSuffix') + '</span>' : '');
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
          bubble('sys', T('welcome'));
        }
        scrollDown();
      })
      .catch(function () {});
  }

  function send() {
    var text = (inp.value || '').trim();
    if (!text) { return; }
    // Edit Website mode (v2.21 M3): hidayat edit flow ko jaye, aam chat ko nahi.
    if (window.__editSend && window.__editSend(text)) { inp.value = ''; return; }
    inp.value = '';
    bubble('u', esc(text));
    sendBtn.disabled = true;
    var thinking = bubble('a', T('thinking'));
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
        bubble('a', T('noReply'));
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

  /* ---------- 4) AI panel collapse + shortcuts (v2.10 Phase 4 — sirf UI) ---------- */
  var appEl = document.querySelector('.app');
  var railTab = document.getElementById('railTab');
  var railX = document.getElementById('railCollapse');
  function railSetClosed(closed) {
    if (appEl) { appEl.classList.toggle('railclosed', !!closed); }
    try { localStorage.setItem('wdh_rail', closed ? 'closed' : 'open'); } catch (e) {}
  }
  try { if (localStorage.getItem('wdh_rail') === 'closed') { railSetClosed(true); } } catch (e) {}
  if (railX) { railX.addEventListener('click', function () { railSetClosed(true); }); }
  if (railTab) { railTab.addEventListener('click', function () { railSetClosed(false); }); }
  document.querySelectorAll('.scbtn').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = b.getAttribute('data-prompt') || '';
      if (!p || !inp || !sendBtn || sendBtn.disabled) { return; }
      inp.value = p;
      sendBtn.click();
    });
  });

  /* ---------- 3b) Edit Website mode (v2.21 M2): section targeting ---------- */
  var editFrame = document.getElementById('editFrame');
  var editDataEl = document.getElementById('editSections');
  if (editFrame && editDataEl && msgs && inp) {
    var editData = { sections: [] };
    try { editData = JSON.parse(editDataEl.textContent || '{}'); } catch (e) { editData = { sections: [] }; }
    var secById = {};
    (editData.sections || []).forEach(function (s) { secById[s.id] = s; });
    var inpWrap = inp.closest('.inp');
    var chipsRow = document.createElement('div');
    chipsRow.className = 'quickchips'; chipsRow.hidden = true;
    var pinbar = document.createElement('div');
    pinbar.className = 'pinbar'; pinbar.hidden = true;
    inpWrap.parentNode.insertBefore(chipsRow, inpWrap);
    inpWrap.parentNode.insertBefore(pinbar, inpWrap);
    var origPh = inp.getAttribute('placeholder') || '';
    var curSec = null;
    function fmt(tpl, a, b) {
      return String(tpl || '').split('%1$s').join(a || '').split('%2$s').join(b || '').split('%s').join(a || '');
    }
    function deselect() {
      curSec = null;
      pinbar.hidden = true; chipsRow.hidden = true;
      inp.setAttribute('placeholder', origPh);
      if (editFrame.contentWindow) { editFrame.contentWindow.postMessage({ t: 'te-deselect' }, '*'); }
    }
    function selectSection(id) {
      var s = secById[id];
      if (!s) { return; }
      curSec = s;
      pinbar.innerHTML = '<span class="editchip">\u270E ' + esc(T('editChipPrefix', 'Editing:')) + ' <b>' + esc(s.title) + '</b> <button type="button" class="pinx" title="\u00D7">\u00D7</button></span>';
      pinbar.hidden = false;
      pinbar.querySelector('.pinx').addEventListener('click', deselect);
      chipsRow.innerHTML = '';
      (s.chips || []).forEach(function (c) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'qchip'; b.textContent = c;
        b.addEventListener('click', function () { inp.value = c; inp.focus(); });
        chipsRow.appendChild(b);
      });
      chipsRow.hidden = !(s.chips && s.chips.length);
      inp.setAttribute('placeholder', fmt(T('editPhFmt', '%s'), s.title));
      bubble('ai', esc(fmt(T('editPickedFmt', '%1$s'), s.title, (s.chips || []).join(', '))));
    }
    window.addEventListener('message', function (e) {
      var d = e.data || {};
      if (d.t === 'te-ready') {
        if (editFrame.contentWindow) { editFrame.contentWindow.postMessage({ t: 'te-sections', sections: editData.sections || [] }, '*'); }
      } else if (d.t === 'te-pick') {
        selectSection(d.id);
      } else if (d.t === 'te-nopick') {
        bubble('ai', esc(T('editNopick', 'Is hisay ke liye control nahi hai.')));
      }
    });

    /* ---------- M3: proposal round-trip chat mein ---------- */
    var editSlug = '';
    try { editSlug = new URL(editFrame.src, location.href).searchParams.get('aiedit_home') || ''; } catch (e) { editSlug = ''; }
    var editCanvas = document.querySelector('.editcanvas');
    var pendingImg = null;
    var lastInstr = '';
    function editPost(op, extra) {
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('op', op);
      fd.append('site', editSlug);
      if (extra) { Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); }); }
      if (op === 'propose' && pendingImg) { fd.append('refimg', pendingImg.file); }
      var ctrl = new AbortController();
      var timer = setTimeout(function () { ctrl.abort(); }, 170000);
      return fetch('/?view=aiedit_chat', { method: 'POST', credentials: 'same-origin', body: fd, signal: ctrl.signal })
        .then(function (r) { return r.json(); })
        .finally(function () { clearTimeout(timer); });
    }
    function changeRow(c) {
      return '<li><b>' + esc(c.label || c.key) + '</b>: ' + esc(c.old_label || '') + ' &rarr; <b>' + esc(c.new_label || String(c.value)) + '</b>' + (c.reason ? ' <span class="aiprop-rsn">' + esc(c.reason) + '</span>' : '') + '</li>';
    }
    function renderProposal(prop) {
      var h = '<div class="aiprop"><div class="aiprop-h">\uD83D\uDCA1 ' + esc(T('editPropTitle', 'Tajweez')) + '</div>';
      if (prop.summary) { h += '<div class="aiprop-sum">' + esc(prop.summary) + '</div>'; }
      var ch = prop.changes || [];
      if (ch.length) {
        h += '<ul class="aiprop-list">' + ch.map(changeRow).join('') + '</ul>';
      } else {
        h += '<div class="aiprop-none">' + esc(T('editNoChanges', 'AI koi durust tabdeeli tajweez nahi kar saka.')) + '</div>';
      }
      (prop.gaps || []).forEach(function (g) { h += '<div class="aiprop-gap">\u26A0 ' + esc(g) + '</div>'; });
      h += '<div class="prop-btns">';
      if (ch.length) { h += '<button type="button" class="mb-btns goldbtn" data-eop="preview">' + esc(T('editPreviewBtn', 'Preview dekhein')) + '</button>'; }
      h += '<button type="button" class="mb-btns" data-eop="redo">' + esc(T('editRedoBtn', 'Nayi Tajweez')) + '</button>';
      h += '<button type="button" class="mb-btns" data-eop="discard">' + esc(T('editDiscardBtn', 'Hata dein')) + '</button></div></div>';
      return h;
    }
    function hideBar() { var b = document.getElementById('editBar'); if (b) { b.remove(); } }
    function showBar() {
      hideBar();
      if (!editCanvas) { return; }
      var bar = document.createElement('div');
      bar.className = 'aiba'; bar.id = 'editBar';
      bar.innerHTML = '<span class="aiba-t">' + esc(T('editBeforeAfter', 'Pehle / Baad')) + '</span>'
        + '<button type="button" data-eview="before">Pehle</button>'
        + '<button type="button" data-eview="after" class="on">Baad</button>'
        + '<button type="button" class="goldbtn" data-eop="apply">' + esc(T('editApplyBtn', 'Apply karein')) + '</button>'
        + '<button type="button" data-eop="redo">' + esc(T('editRedoBtn', 'Nayi Tajweez')) + '</button>'
        + '<button type="button" data-eop="discard">' + esc(T('editDiscardBtn', 'Hata dein')) + '</button>';
      editCanvas.appendChild(bar);
      bar.addEventListener('click', function (e) {
        var v = e.target.closest('[data-eview]');
        if (v) { setFrameView(v.getAttribute('data-eview')); return; }
        var b = e.target.closest('[data-eop]');
        if (b) { handleEop(b.getAttribute('data-eop'), b); }
      });
    }
    function setFrameView(which) {
      if (!editFrame) { return; }
      editFrame.src = which === 'before' ? '/?aiedit_home=' + encodeURIComponent(editSlug) : '/?aiedit_ph=' + encodeURIComponent(editSlug);
      var bar = document.getElementById('editBar');
      if (bar) { bar.querySelectorAll('[data-eview]').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-eview') === which); }); }
    }
    function doPropose(text) {
      lastInstr = text;
      var thinking = bubble('a', esc(T('editThinking', 'AI soch raha hai...')));
      sendBtn.disabled = true;
      editPost('propose', { instruction: text, section: curSec ? curSec.id : '' })
        .then(function (j) {
          thinking.remove();
          if (j.error) { bubble('a', '\u26A0 ' + esc(j.error)); return; }
          pendingImg = null;
          bubble('a', renderProposal(j.proposal || {}));
        })
        .catch(function (e) {
          thinking.remove();
          bubble('a', '\u26A0 ' + (e && e.name === 'AbortError' ? 'Gateway se jawab dair se aya — dobara koshish karein.' : 'Jawab nahi mila — dobara koshish karein.'));
        })
        .finally(function () { sendBtn.disabled = false; inp.focus(); });
    }
    function handleEop(op, btn) {
      if (op === 'preview') {
        var th = bubble('a', esc(T('editThinking', 'AI soch raha hai...')));
        editPost('preview', {}).then(function (j) {
          th.remove();
          if (j.error) { bubble('a', '\u26A0 ' + esc(j.error)); return; }
          setFrameView('after'); showBar();
        }).catch(function () { th.remove(); bubble('a', '\u26A0 Preview nahi ban saka.'); });
      } else if (op === 'apply') {
        var th2 = bubble('a', esc(T('editThinking', 'AI soch raha hai...')));
        editPost('apply', {}).then(function (j) {
          th2.remove();
          if (j.error) { bubble('a', '\u26A0 ' + esc(j.error)); return; }
          hideBar();
          setFrameView('before');
          var tx = j.tx || '';
          bubble('a', '<div class="aiprop applied">\u2713 ' + esc(T('editApplied', 'Tabdeeli LIVE ho gayi hai.')) + (tx ? ' <button type="button" class="mb-btns" data-eop="undo" data-tx="' + esc(tx) + '">' + esc(T('editUndoBtn', 'Undo karein')) + '</button>' : '') + '</div>');
        }).catch(function () { th2.remove(); bubble('a', '\u26A0 Apply nahi ho saka.'); });
      } else if (op === 'undo') {
        editPost('undo', { tx: btn ? btn.getAttribute('data-tx') || '' : '' }).then(function (j) {
          if (j.error) { bubble('a', '\u26A0 ' + esc(j.error)); return; }
          setFrameView('before');
          bubble('a', 'Undo ho gaya — pehli halat wapas aa gayi.');
          if (msgs.querySelector('[data-hist="1"]')) { showHistory(); }
        }).catch(function () { bubble('a', '\u26A0 Undo nahi ho saka.'); });
      } else if (op === 'redo') {
        var th3 = bubble('a', esc(T('editThinking', 'AI soch raha hai...')));
        editPost('redo', { tx: btn ? btn.getAttribute('data-tx') || '' : '' }).then(function (j) {
          th3.remove();
          if (j.error) { bubble('a', '\u26A0 ' + esc(j.error)); return; }
          setFrameView('before');
          bubble('a', '\u2713 Dobara lagoo ho gaya.');
          if (msgs.querySelector('[data-hist="1"]')) { showHistory(); }
        }).catch(function () { th3.remove(); bubble('a', '\u26A0 Dobara lagoo nahi ho saka.'); });
      } else if (op === 'discard') {
        editPost('discard', {}).then(function () { hideBar(); setFrameView('before'); bubble('a', 'Tajweez hata di.'); });
      } else if (op === 'redo') {
        if (lastInstr) { doPropose(lastInstr); }
      }
    }
    msgs.addEventListener('click', function (e) {
      var b = e.target.closest('[data-eop]');
      if (b) { handleEop(b.getAttribute('data-eop'), b); }
    });
    // Mic (Web Speech API) + image upload.
    var micBtn = document.getElementById('chatMic');
    var langSel = document.getElementById('chatLang');
    var imgBtn = document.getElementById('chatImg');
    var imgFile = document.getElementById('chatImgFile');
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (micBtn) {
      if (!SR) { micBtn.disabled = true; micBtn.title = T('editMicUnsupported', 'Is browser mein mic nahi chalta.'); }
      else {
        micBtn.addEventListener('click', function () {
          var rec = new SR();
          rec.lang = langSel ? langSel.value : 'ur-PK';
          rec.interimResults = false; rec.maxAlternatives = 1;
          micBtn.textContent = '\uD83D\uDD34';
          rec.onresult = function (ev) { inp.value = ev.results[0][0].transcript; inp.focus(); };
          rec.onend = function () { micBtn.textContent = '\uD83C\uDFA4'; };
          rec.onerror = function () { micBtn.textContent = '\uD83C\uDFA4'; };
          try { rec.start(); } catch (err) {}
        });
      }
    }
    if (imgBtn && imgFile) {
      imgBtn.addEventListener('click', function () { imgFile.click(); });
      imgFile.addEventListener('change', function () {
        if (imgFile.files && imgFile.files[0]) { pendingImg = { file: imgFile.files[0], name: imgFile.files[0].name }; bubble('ai', '\uD83D\uDDBC Tasveer lag gayi — hidayat likh kar bhejein.'); }
      });
    }
    /* ---------- M4: History chat mein ---------- */
    function histTime(at) {
      var d = new Date(at || '');
      if (isNaN(d.getTime())) { return esc(at || ''); }
      return esc(d.toLocaleString());
    }
    function renderHistory(items) {
      var h = '<div class="aiprop hist"><div class="aiprop-h">\uD83D\uDD50 ' + esc(T('editHistoryTitle', 'AI History')) + '</div>';
      if (!items || !items.length) { h += '<div class="aiprop-none">' + esc(T('editHistoryEmpty', 'Abhi koi tabdeeli nahi hui.')) + '</div>'; }
      (items || []).forEach(function (it) {
        var undone = (it.status || '') === 'undone';
        h += '<div class="hist-item"><div class="hist-meta">' + histTime(it.at) + ' \u00B7 ' + (undone ? esc(T('editHistUndone', 'Undo ho chuka hai')) : esc(T('editHistApplied', 'Lagoo hai'))) + '</div>';
        if (it.prompt) { h += '<div class="hist-prompt">' + esc(it.prompt) + '</div>'; }
        h += '<ul class="aiprop-list">';
        (it.changes || []).forEach(function (c) {
          h += '<li><b>' + esc(c.label || c.key) + '</b>: ' + esc(c.before == null ? '' : String(c.before)) + ' &rarr; <b>' + esc(c.after == null ? '' : String(c.after)) + '</b></li>';
        });
        h += '</ul>';
        if (it.id) {
          if (!undone) { h += '<button type="button" class="mb-btns" data-eop="undo" data-tx="' + esc(it.id) + '">' + esc(T('editUndoBtn', 'Undo karein')) + '</button>'; }
          else { h += '<button type="button" class="mb-btns goldbtn" data-eop="redo" data-tx="' + esc(it.id) + '">' + esc(T('editRedoApplyBtn', 'Dobara lagao')) + '</button>'; }
        }
        h += '</div>';
      });
      h += '</div>';
      return h;
    }
    function showHistory() {
      var thinking = bubble('a', esc(T('editThinking', 'AI soch raha hai...')));
      editPost('history', {}).then(function (j) {
        thinking.remove();
        if (j.error) { bubble('a', '\u26A0 ' + esc(j.error)); return; }
        var old = msgs.querySelector('[data-hist="1"]');
        if (old) { old.remove(); }
        var b = bubble('a', renderHistory(j.history || []));
        b.setAttribute('data-hist', '1');
      }).catch(function () { thinking.remove(); bubble('a', '\u26A0 History nahi mil saki.'); });
    }
    var histBtn = document.getElementById('editHistoryBtn');
    if (histBtn) { histBtn.addEventListener('click', showHistory); }
    // send() se hook: edit mode mein hidayat yahan aye.
    window.__editSend = function (text) {
      bubble('u', esc(text));
      doPropose(text);
      return true;
    };
  }
})();

/* v2.15 — Updates page: modal + apply progress poll */
(function () {
  var openBtn = document.getElementById('updOpenBtn');
  var modal = document.getElementById('updModal');
  if (openBtn && modal) {
    openBtn.addEventListener('click', function () { modal.hidden = false; });
    var cancel = document.getElementById('updCancelBtn');
    if (cancel) { cancel.addEventListener('click', function () { modal.hidden = true; }); }
    modal.addEventListener('click', function (e) { if (e.target === modal) { modal.hidden = true; } });
  }
  var prog = document.getElementById('updProgress');
  if (!prog) { return; }
  var job = prog.getAttribute('data-job');
  var result = document.getElementById('updResult');
  var order = ['backup', 'download', 'apply', 'verify'];
  function mark(step) {
    var upto = { queued: 0, download: 1, backup: 1, apply: 2, verify: 3, done: 4 }[step];
    if (upto === undefined) { return; }
    order.forEach(function (name, i) {
      var el = document.querySelector('.upd-step[data-step="' + name + '"]');
      if (!el) { return; }
      el.classList.toggle('done', i < upto || step === 'done');
      el.classList.toggle('doing', i === upto && step !== 'done');
    });
  }
  function poll() {
    fetch('/?view=upd_job&job=' + encodeURIComponent(job), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.step) { setTimeout(poll, 2500); return; }
        mark(j.step);
        if (j.step === 'done') {
          result.textContent = prog.getAttribute('data-done');
          result.className = 'upd-result ok';
          setTimeout(function () { location.href = '/?view=updates'; }, 4000);
        } else if (j.step === 'failed') {
          result.textContent = prog.getAttribute('data-fail') + (j.message ? ' (' + j.message + ')' : '');
          result.className = 'upd-result bad';
        } else {
          setTimeout(poll, 2000);
        }
      })
      .catch(function () { setTimeout(poll, 3000); });
  }
  mark('queued');
  poll();
})();
