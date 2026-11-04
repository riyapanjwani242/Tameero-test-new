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

  /* ---------- 2) Import progress ---------- */
  var form = document.getElementById('importForm');
  if (form) {
    var base = form.getAttribute('data-upload-base') || '';
    if (base) {
      form.addEventListener('submit', function (ev) {
        var fileInp = document.getElementById('importFile');
        if (!fileInp || !fileInp.files || !fileInp.files.length) { return; }
        ev.preventDefault();
        var box = document.getElementById('importProgress');
        var bar = document.getElementById('importBar');
        var txt = document.getElementById('importProgressText');
        if (box) { box.style.display = 'block'; }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', base + '/', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', function (e) {
          if (e.lengthComputable && bar) {
            var pc = Math.round(e.loaded / e.total * 100);
            bar.style.width = pc + '%';
            if (txt) { txt.textContent = 'Upload ho raha hai… ' + pc + '%'; }
          }
        });
        xhr.addEventListener('load', function () {
          if (txt) { txt.textContent = 'Upload mukammal — site ban rahi hai…'; }
          var dest = '/';
          try { var j = JSON.parse(xhr.responseText); if (j && j.redirect) { dest = j.redirect; } } catch (e) {}
          window.location = dest;
        });
        xhr.addEventListener('error', function () {
          if (txt) { txt.textContent = 'Upload mein kharabi — aam tareeqe se bhej raha hoon…'; }
          form.submit();
        });
        xhr.send(new FormData(form));
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
