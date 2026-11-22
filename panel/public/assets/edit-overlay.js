/* TAMEERO Edit Mode v2 — preview overlay (v2.21)
 * Panel-served preview iframe ke ANDAR chalta hai (same-origin).
 * Parent (panel) se postMessage par editable sections ki fehrist
 * milti hai; sirf un par hover outline + "Edit with AI" pill ata
 * hai. Poora section clickable hai — pill sirf nishan hai.
 * Koi tabdeeli yahan nahi hoti; click ki khabar parent ko jati hai. */
(function () {
  var editable = {}; // blockId -> title

  var css = document.createElement('style');
  css.textContent =
    '[data-tameero-section].te-editable{position:relative;cursor:pointer}' +
    '[data-tameero-section].te-editable:hover{outline:3px solid #C9A227;outline-offset:-3px}' +
    '[data-tameero-section].te-editable:hover::after{content:"\\270E Edit with AI";position:absolute;top:8px;right:8px;z-index:9999;background:#C9A227;color:#14100a;font:700 13px/1 sans-serif;padding:7px 12px;border-radius:99px;pointer-events:none}' +
    '[data-tameero-section].te-sel{outline:3px solid #C9A227!important;outline-offset:-3px}';
  document.head.appendChild(css);

  function blockKey(id) {
    if (editable[id]) { return id; }
    if (id.indexOf('category-') === 0 && editable['category']) { return 'category'; }
    return null;
  }

  function mark() {
    var els = document.querySelectorAll('[data-tameero-section]');
    for (var i = 0; i < els.length; i++) {
      var id = els[i].getAttribute('data-tameero-section');
      var key = blockKey(id);
      if (key) {
        els[i].classList.add('te-editable');
        els[i].setAttribute('data-te-key', key);
      }
    }
  }

  function select(el) {
    var prev = document.querySelectorAll('.te-sel');
    for (var i = 0; i < prev.length; i++) { prev[i].classList.remove('te-sel'); }
    if (el) { el.classList.add('te-sel'); }
  }

  document.addEventListener('click', function (e) {
    var sec = e.target && e.target.closest ? e.target.closest('[data-tameero-section]') : null;
    var a = e.target && e.target.closest ? e.target.closest('a') : null;
    if (a) { e.preventDefault(); }
    if (!sec) { return; }
    e.preventDefault();
    var key = sec.getAttribute('data-te-key');
    if (key) {
      select(sec);
      window.parent.postMessage({ t: 'te-pick', id: key, title: editable[key] || key }, '*');
    } else {
      window.parent.postMessage({ t: 'te-nopick', id: sec.getAttribute('data-tameero-section') }, '*');
    }
  }, true);

  window.addEventListener('message', function (e) {
    var d = e.data || {};
    if (d.t === 'te-sections') {
      (d.sections || []).forEach(function (s) { editable[s.id] = s.title; });
      mark();
    }
    if (d.t === 'te-deselect') { select(null); }
  });

  window.parent.postMessage({ t: 'te-ready' }, '*');
})();
