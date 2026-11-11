/* TAMEERO Edit Mode — front-end shell (sirf logged-in admin ko load hota hai)
 *
 * Model: staged preview. Panel ki tabdeeliyan pehle `staged` mein jati
 * hain (canvas par echo + "غیر محفوظ" nishan), Save par aik atomic REST
 * batch lagta hai. Nakami par canvas committed halat par wapas, panel
 * inputs mehfooz rehte hain.
 */
(function () {
	'use strict';

	var L = window.TameeroEdit;
	if (!L) { return; }

	var committed = {};
	Object.keys(L.values || {}).forEach(function (k) { committed[k] = L.values[k]; });
	var staged = {};          // theme_mod -> staged value (sirf dirty)
	var dirtySections = {};   // section key -> true
	var selectedKey = null;   // canvas section key ya 'global' ya 'list'
	var modeOn = false;
	var pendingExit = false;

	var root = document.getElementById('te-root');
	var drawer = document.getElementById('te-drawer');
	var panelTitle = document.getElementById('te-panel-title');
	var panelBody = document.getElementById('te-panel-body');
	var panelBack = document.getElementById('te-panel-back');
	var panelNote = document.getElementById('te-panel-note');
	var saveBtn = document.getElementById('te-save');
	var resetBtn = document.getElementById('te-reset');
	var modal = document.getElementById('te-modal');
	var toast = document.getElementById('te-toast');
	if (!root || !drawer) { return; }
	root.hidden = false;

	/* ---------------- helpers ---------------- */

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function sectionDefs() { return (L.config && L.config.sections) || []; }

	function defForKey(key) {
		var defs = sectionDefs();
		for (var i = 0; i < defs.length; i++) {
			if (defs[i].id === key) { return defs[i]; }
			if (defs[i].id === 'category' && key.indexOf('category-') === 0) { return defs[i]; }
		}
		return null;
	}

	function sectionElements(key) {
		var def = defForKey(key);
		if (def && def.selector) {
			return Array.prototype.slice.call(document.querySelectorAll(def.selector));
		}
		return Array.prototype.slice.call(document.querySelectorAll('[data-tameero-section="' + key + '"]'));
	}

	function allSectionKeys() {
		var keys = [];
		document.querySelectorAll('[data-tameero-section]').forEach(function (el) {
			var k = el.getAttribute('data-tameero-section');
			if (keys.indexOf(k) === -1) { keys.push(k); }
		});
		// Manifest sections jo is waqt DOM mein NAHI (band sections) — toggle on ke liye list mein chahiyein
		sectionDefs().forEach(function (def) {
			if (def.id !== 'category' && keys.indexOf(def.id) === -1) { keys.push(def.id); }
		});
		return keys;
	}

	function controlOwner(mod) {
		// mod kis block mein hai: 'global' ya section def id
		var g = L.config && L.config.global;
		if (g && (g.controls || []).some(function (c) { return c.theme_mod === mod; })) { return 'global'; }
		var found = null;
		sectionDefs().forEach(function (def) {
			if ((def.controls || []).some(function (c) { return c.theme_mod === mod; })) { found = def.id; }
		});
		return found;
	}

	function valueOf(mod, def) {
		if (Object.prototype.hasOwnProperty.call(staged, mod)) { return staged[mod]; }
		if (Object.prototype.hasOwnProperty.call(committed, mod)) { return committed[mod]; }
		return def ? def.default : null;
	}

	function isDirty() { return Object.keys(staged).length > 0; }

	/* ---------------- mode on/off ---------------- */

	var abToggle = document.querySelector('#wp-admin-bar-tameero-edit-mode a');
	var abState = document.querySelector('#wp-admin-bar-tameero-edit-mode .te-ab-state');

	function setMode(on, skipGuard) {
		if (on === modeOn) { return; }
		if (!on && isDirty() && !skipGuard) {
			pendingExit = true;
			modal.hidden = false;
			return;
		}
		modeOn = on;
		document.body.classList.toggle('te-on', on);
		if (abState) { abState.textContent = on ? L.i18n.on : L.i18n.off; }
		try { sessionStorage.setItem('te_on', on ? '1' : '0'); } catch (e) {}
		if (on) {
			decorateAll();
			showListView();
			acquireLock();
			offerDraft();
		} else {
			stopLock();
			document.body.classList.remove('te-panel-open');
			selectedKey = null;
			document.querySelectorAll('.te-chip, .te-unsaved-badge').forEach(function (n) { n.remove(); });
			document.querySelectorAll('.te-selected').forEach(function (n) { n.classList.remove('te-selected'); });
		}
	}

	if (abToggle) {
		abToggle.addEventListener('click', function (e) {
			e.preventDefault();
			setMode(!modeOn);
		});
	}

	/* ---------------- chips / outlines ---------------- */

	function decorate(el) {
		var key = el.getAttribute('data-tameero-section');
		if (!key) { return; }
		var def = defForKey(key);
		if (!def) { return; } // Manifest mein declared nahi — outline/chip nahi lagega
		el.classList.add('te-editable');
		if (el.querySelector(':scope > .te-chip')) { return; }
		var chip = document.createElement('button');
		chip.type = 'button';
		chip.className = 'te-chip';
		chip.innerHTML = '<span aria-hidden="true">✎</span> ' + esc(def ? def.title : key);
		chip.addEventListener('click', function (e) {
			e.stopPropagation();
			selectSection(key, true);
		});
		el.appendChild(chip);
		refreshDirtyMarks();
	}

	function decorateAll() {
		document.querySelectorAll('[data-tameero-section]').forEach(decorate);
	}

	// Edit mode mein section par click = select (links navigate NAHI karte)
	document.addEventListener('click', function (e) {
		if (!modeOn) { return; }
		var sec = e.target.closest ? e.target.closest('[data-tameero-section]') : null;
		if (sec && !e.target.closest('.te-chip')) {
			e.preventDefault();
			selectSection(sec.getAttribute('data-tameero-section'), false);
		}
	}, true);

	function refreshDirtyMarks() {
		document.querySelectorAll('[data-tameero-section]').forEach(function (el) {
			var key = el.getAttribute('data-tameero-section');
			var dirty = !!dirtySections[key];
			el.classList.toggle('te-dirty-section', dirty);
			var badge = el.querySelector(':scope > .te-unsaved-badge');
			if (dirty && !badge) {
				badge = document.createElement('span');
				badge.className = 'te-unsaved-badge';
				badge.textContent = L.i18n.unsaved;
				el.appendChild(badge);
			} else if (!dirty && badge) {
				badge.remove();
			}
		});
		updateSaveUI();
	}

	function updateSaveUI() {
		saveBtn.disabled = !isDirty();
		resetBtn.disabled = !isDirty();
		var structural = Object.keys(staged).some(function (mod) {
			var owner = controlOwner(mod);
			var blocks = sectionDefs().concat(L.config.global ? [L.config.global] : []);
			var ctrl = null;
			blocks.forEach(function (b) {
				(b.controls || []).forEach(function (c) { if (c.theme_mod === mod) { ctrl = c; } });
			});
			return ctrl && ['category_select', 'stepper', 'layout_picker', 'select', 'toggle'].indexOf(ctrl.type) !== -1;
		});
		panelNote.hidden = !(isDirty() && structural);
		panelNote.textContent = structural ? L.i18n.appliesOnSave : '';
	}

	/* ---------------- panel views ---------------- */

	function openPanel() {
		document.body.classList.add('te-panel-open');
		if (window.innerWidth < 1024) { drawer.classList.add('te-snap-half'); }
	}
	function closePanel() {
		document.body.classList.remove('te-panel-open');
		selectedKey = null;
		document.querySelectorAll('.te-selected').forEach(function (n) { n.classList.remove('te-selected'); });
	}

	document.getElementById('te-panel-close').addEventListener('click', closePanel);
	panelBack.addEventListener('click', showListView);
	document.getElementById('te-sheet-handle').addEventListener('click', function () {
		drawer.classList.toggle('te-snap-full');
		drawer.classList.toggle('te-snap-half');
	});

	function showListView() {
		selectedKey = 'list';
		panelBack.hidden = true;
		panelTitle.textContent = 'ایڈٹ موڈ';
		var html = '';
		if (L.config.global) {
			html += '<button type="button" class="te-global-entry" id="te-open-global">⚙ ' + esc(L.config.global.title || L.i18n.globalTitle) + '</button>';
		}
		html += '<button type="button" class="te-global-entry" id="te-open-cats">🗂 کیٹیگری مینیجر</button>';
		html += '<p class="te-panel-intro">کسی سیکشن کو منتخب کریں — صفحے پر اس کے اوپر والے بٹن سے یا نیچے کی فہرست سے۔</p>';
		allSectionKeys().forEach(function (key) {
			var def = defForKey(key);
			if (!def) { return; }
			var label = key.indexOf('category-') === 0 ? key.replace('category-', '') : def.title;
			html += '<button type="button" class="te-global-entry te-section-entry" data-key="' + esc(key) + '">✎ ' + esc(label) + '</button>';
		});
		panelBody.innerHTML = html;
		var g = document.getElementById('te-open-global');
		if (g) { g.addEventListener('click', function () { showControlsView('global'); }); }
		document.getElementById('te-open-cats').addEventListener('click', showCategoriesView);
		panelBody.querySelectorAll('.te-section-entry').forEach(function (btn) {
			btn.addEventListener('click', function () { selectSection(btn.getAttribute('data-key'), true); });
		});
		openPanel();
		refreshDirtyMarks();
	}

	function selectSection(key, scroll) {
		selectedKey = key;
		document.querySelectorAll('.te-selected').forEach(function (n) { n.classList.remove('te-selected'); });
		sectionElements(key).forEach(function (el) {
			el.classList.add('te-selected');
			if (scroll) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
		});
		showControlsView(key);
	}

	/* ---------------- Phase 4: lock / draft / backup ---------------- */

	var lockState = 'free';
	var heartbeatTimer = null;
	var lockbar = document.getElementById('te-lockbar');
	var draftbar = document.getElementById('te-draftbar');
	var draftKey = 'te_draft_' + (L.restRoot || window.location.host);

	function applyLockUI(res) {
		lockState = res.state || 'free';
		document.body.classList.toggle('te-locked', lockState === 'held');
		if (lockState === 'held') {
			lockbar.hidden = false;
			lockbar.innerHTML = '<span>🔒 <strong>' + esc((res.holder && res.holder.name) || '') + '</strong> اس وقت ایڈٹ کر رہے ہیں — آپ صرف دیکھ سکتے ہیں۔</span>' +
				'<button type="button" id="te-takeover" class="te-btn te-btn-quiet">کنٹرول حاصل کریں</button>';
			document.getElementById('te-takeover').addEventListener('click', function () {
				restPost('lock', { op: 'takeover' }).then(applyLockUI);
			});
		} else {
			lockbar.hidden = true;
			lockbar.innerHTML = '';
		}
		updateSaveUI();
	}

	function acquireLock() {
		restPost('lock', { op: 'acquire' }).then(function (res) {
			applyLockUI(res);
			if (heartbeatTimer) { clearInterval(heartbeatTimer); }
			heartbeatTimer = setInterval(function () {
				restPost('lock', { op: 'heartbeat' }).then(applyLockUI).catch(function () {});
			}, 30000);
		}).catch(function () {});
	}

	function stopLock() {
		if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
		if (lockState === 'mine') {
			try {
				fetch(L.restUrl + 'lock', {
					method: 'POST', credentials: 'same-origin', keepalive: true,
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': L.nonce },
					body: JSON.stringify({ op: 'release' })
				});
			} catch (e) {}
		}
		lockState = 'free';
		document.body.classList.remove('te-locked');
		lockbar.hidden = true;
	}

	/* Draft (localStorage, 24 ghante) */
	function persistDraft() {
		try {
			if (isDirty()) {
				localStorage.setItem(draftKey, JSON.stringify({ ts: Date.now(), values: staged }));
			} else {
				localStorage.removeItem(draftKey);
			}
		} catch (e) {}
	}

	function clearDraft() {
		try { localStorage.removeItem(draftKey); } catch (e) {}
		draftbar.hidden = true;
	}

	function offerDraft() {
		var raw = null;
		try { raw = localStorage.getItem(draftKey); } catch (e) {}
		if (!raw) { return; }
		var draft = null;
		try { draft = JSON.parse(raw); } catch (e) {}
		if (!draft || !draft.values || (Date.now() - draft.ts) > 24 * 3600 * 1000) { clearDraft(); return; }
		var mods = Object.keys(draft.values);
		if (!mods.length) { return; }
		var when = new Date(draft.ts).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
		draftbar.hidden = false;
		draftbar.innerHTML = '<span>آپ کی غیر محفوظ تبدیلیاں ملیں (' + esc(when) + ')</span>' +
			'<span class="te-draft-actions"><button type="button" id="te-draft-restore" class="te-btn te-btn-primary">بحال کریں</button>' +
			'<button type="button" id="te-draft-discard" class="te-btn te-btn-quiet">ختم کریں</button></span>';
		document.getElementById('te-draft-restore').addEventListener('click', function () {
			mods.forEach(function (mod) {
				staged[mod] = draft.values[mod];
				var owner = controlOwner(mod);
				if (owner && owner !== 'global') { dirtySections[owner] = true; }
				// Echo: control def dhoondo aur lagu karo
				var def = null;
				[L.config.global].concat(sectionDefs()).forEach(function (b) {
					(b && b.controls || []).forEach(function (c) { if (c.theme_mod === mod) { def = c; } });
				});
				if (def) { applyEcho(mod, draft.values[mod], def); }
			});
			draftbar.hidden = true;
			refreshDirtyMarks();
			updateSaveUI();
			showToast('تبدیلیاں بحال ہو گئیں', false);
		});
		document.getElementById('te-draft-discard').addEventListener('click', clearDraft);
	}

	/* Backup card (global view ke neeche) */
	function appendBackupCard() {
		var card = document.createElement('div');
		card.className = 'te-backup-card';
		card.innerHTML = '<h3 class="te-backup-title">بیک اپ</h3>' +
			'<p class="te-hint" id="te-backup-status">ہر محفوظ کرنے سے پہلے خودکار بیک اپ بنتا ہے (آخری 3)۔</p>' +
			'<div id="te-backup-list"></div>' +
			'<div class="te-backup-actions">' +
			'<button type="button" id="te-export" class="te-btn te-btn-quiet">📥 ترتیبات ڈاؤن لوڈ (JSON)</button>' +
			'<button type="button" id="te-import-btn" class="te-btn te-btn-quiet">📤 ترتیبات درآمد کریں</button>' +
			'<input type="file" id="te-import-file" accept=".json,application/json" hidden>' +
			'</div>';
		panelBody.appendChild(card);
		restGet('backups').then(function (res) {
			var list = document.getElementById('te-backup-list');
			if (!list) { return; }
			if (res.backups && res.backups.length) {
				list.innerHTML = res.backups.map(function (b) {
					var d = new Date(b.time * 1000);
					return '<div class="te-backup-row"><span>' + esc(d.toLocaleString()) + '</span>' +
						'<button type="button" class="te-btn te-btn-quiet te-restore" data-idx="' + b.index + '">بحال کریں</button></div>';
				}).join('');
				list.querySelectorAll('.te-restore').forEach(function (btn) {
					btn.addEventListener('click', function () {
						restPost('backups/restore', { index: parseInt(btn.getAttribute('data-idx'), 10) }).then(function () {
							window.location.reload();
						}).catch(function (err) { showToast((err && err.message) || L.i18n.saveError, true); });
					});
				});
			}
		}).catch(function () {});
		document.getElementById('te-export').addEventListener('click', function () {
			restGet('settings-export').then(function (res) {
				var blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
				var a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = 'tameero-settings.json';
				a.click();
				setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
			});
		});
		var fileInput = document.getElementById('te-import-file');
		document.getElementById('te-import-btn').addEventListener('click', function () { fileInput.click(); });
		fileInput.addEventListener('change', function () {
			var f = fileInput.files[0];
			if (!f) { return; }
			f.text().then(function (text) {
				var data = JSON.parse(text);
				var mods = data.mods || data;
				return restPost('settings-import', { mods: mods });
			}).then(function () {
				window.location.reload();
			}).catch(function (err) {
				showToast((err && err.message) || 'درآمد ناکام — file durust nahi.', true);
			});
		});
	}

	/* Advanced: section ke defaults par reset (stage hoga, save par lagega) */
	function appendAdvancedReset(block) {
		var det = document.createElement('details');
		det.className = 'te-advanced';
		det.innerHTML = '<summary>ایڈوانسڈ اختیارات — صرف ماہرین کے لیے</summary>' +
			'<button type="button" class="te-btn te-btn-quiet" id="te-section-defaults">اس سیکشن کی ترتیبات اصل حالت پر واپس کریں</button>';
		panelBody.appendChild(det);
		det.querySelector('#te-section-defaults').addEventListener('click', function () {
			(block.controls || []).forEach(function (ctrl) {
				if (Object.prototype.hasOwnProperty.call(ctrl, 'default')) {
					setStaged(ctrl.theme_mod, ctrl.default, ctrl);
				}
			});
			showControlsView(selectedKey);
		});
	}

	/* ---------------- categories manager ---------------- */

	function showCategoriesView() {
		selectedKey = 'categories';
		panelBack.hidden = false;
		panelTitle.textContent = 'کیٹیگری مینیجر';
		panelBody.innerHTML = '<p class="te-panel-intro">لوڈ ہو رہا ہے…</p>';
		openPanel();
		restGet('categories').then(function (res) {
			L.categories = res.categories || [];
			renderCategories();
		}).catch(function () {
			panelBody.innerHTML = '<p class="te-panel-intro">' + esc(L.i18n.saveError) + '</p>';
		});
	}

	function renderCategories() {
		var cats = L.categories || [];
		var html = '<div class="te-cat-add"><input type="text" id="te-cat-name" placeholder="نئی کیٹیگری کا نام">' +
			'<button type="button" id="te-cat-add-btn" class="te-btn te-btn-primary">کیٹیگری شامل کریں</button></div>' +
			'<p class="te-cat-banner">⚠ کیٹیگری حذف کرنے سے اس کی تحریریں ضائع نہیں ہوں گی — وہ دوسری کیٹیگری میں چلی جائیں گی۔</p>' +
			'<div id="te-cat-list">';
		cats.forEach(function (c) {
			html += '<div class="te-cat-row" data-id="' + c.id + '">' +
				'<span class="te-cat-name">' + esc(c.name) + '</span>' +
				'<span class="te-cat-count">' + c.count + '</span>' +
				'<button type="button" class="te-cat-icon te-cat-rename" aria-label="نام بدلیں">✎</button>' +
				'<button type="button" class="te-cat-icon te-cat-delete" aria-label="حذف کریں">🗑</button>' +
				'</div>';
		});
		html += '</div>';
		panelBody.innerHTML = html;

		document.getElementById('te-cat-add-btn').addEventListener('click', function () {
			var input = document.getElementById('te-cat-name');
			var name = input.value.trim();
			if (!name) { input.focus(); return; }
			restPost('categories', { name: name }).then(function (res) {
				L.categories = res.categories || [];
				renderCategories();
				showToast('کیٹیگری بن گئی', false);
			}).catch(function (err) {
				showToast((err && err.message) || L.i18n.saveError, true);
			});
		});

		panelBody.querySelectorAll('.te-cat-row').forEach(function (row) {
			var id = parseInt(row.getAttribute('data-id'), 10);
			row.querySelector('.te-cat-rename').addEventListener('click', function () { renderRename(row, id); });
			row.querySelector('.te-cat-delete').addEventListener('click', function () { renderDelete(row, id); });
		});
	}

	function catById(id) {
		return (L.categories || []).filter(function (c) { return c.id === id; })[0];
	}

	function renderRename(row, id) {
		var cat = catById(id);
		if (!cat) { return; }
		row.innerHTML = '<div class="te-cat-edit">' +
			'<input type="text" class="te-cat-rename-input" value="' + esc(cat.name) + '">' +
			'<label class="te-cat-slug-check"><input type="checkbox" class="te-cat-slug-flag"> URL بھی بدلیں — پرانا پتہ نئے پتے پر خود چلا جائے گا</label>' +
			'<div class="te-cat-edit-row">' +
			'<button type="button" class="te-btn te-btn-primary te-cat-do-rename">محفوظ کریں</button>' +
			'<button type="button" class="te-btn te-btn-quiet te-cat-cancel">منسوخ کریں</button>' +
			'</div></div>';
		row.querySelector('.te-cat-cancel').addEventListener('click', renderCategories);
		row.querySelector('.te-cat-do-rename').addEventListener('click', function () {
			var name = row.querySelector('.te-cat-rename-input').value.trim();
			var changeSlug = row.querySelector('.te-cat-slug-flag').checked;
			if (!name) { return; }
			restPost('categories/rename', { id: id, name: name, change_slug: changeSlug }).then(function (res) {
				L.categories = res.categories || [];
				renderCategories();
				showToast(L.i18n.saved, false);
			}).catch(function (err) {
				showToast((err && err.message) || L.i18n.saveError, true);
			});
		});
	}

	function renderDelete(row, id) {
		var cat = catById(id);
		if (!cat) { return; }
		var others = (L.categories || []).filter(function (c) { return c.id !== id; });
		var html = '<div class="te-cat-edit"><p class="te-cat-confirm">"' + esc(cat.name) + '" حذف کریں؟</p>';
		if (cat.count > 0) {
			html += '<label>اس کی ' + cat.count + ' تحریریں کس کیٹیگری میں جائیں؟</label>' +
				'<select class="te-cat-target"><option value="0">— سائٹ کی ڈیفالٹ کیٹیگری —</option>' +
				others.map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + ' (' + c.count + ')</option>'; }).join('') +
				'</select>';
		}
		html += '<div class="te-cat-edit-row">' +
			'<button type="button" class="te-btn te-btn-primary te-cat-do-delete">حذف کریں</button>' +
			'<button type="button" class="te-btn te-btn-quiet te-cat-cancel">منسوخ کریں</button>' +
			'</div></div>';
		row.innerHTML = html;
		row.querySelector('.te-cat-cancel').addEventListener('click', renderCategories);
		row.querySelector('.te-cat-do-delete').addEventListener('click', function () {
			var sel = row.querySelector('.te-cat-target');
			var target = sel ? parseInt(sel.value, 10) : 0;
			restPost('categories/delete', { id: id, reassign_to: target }).then(function (res) {
				L.categories = res.categories || [];
				renderCategories();
				showToast('کیٹیگری حذف ہو گئی — تحریریں محفوظ ہیں', false);
			}).catch(function (err) {
				showToast((err && err.message) || L.i18n.saveError, true);
			});
		});
	}

	function showControlsView(key) {
		var block = key === 'global' ? L.config.global : defForKey(key);
		if (!block) { showListView(); return; }
		selectedKey = key;
		panelBack.hidden = false;
		panelTitle.textContent = block.title || (key === 'global' ? L.i18n.globalTitle : key);
		panelBody.innerHTML = '';
		(block.controls || []).forEach(function (ctrl) {
			panelBody.appendChild(buildControl(ctrl));
		});
		if (key === 'global') { appendBackupCard(); }
		if (key !== 'global') { appendAdvancedReset(block); }
		openPanel();
		refreshDirtyMarks();
	}

	/* ---------------- controls ---------------- */

	function buildControl(ctrl) {
		var wrap = document.createElement('div');
		wrap.className = 'te-field';
		var mod = ctrl.theme_mod;
		var val = valueOf(mod, ctrl);

		function changed(newVal) { setStaged(mod, newVal, ctrl); }

		if (ctrl.type === 'toggle') {
			wrap.innerHTML = '<div class="te-toggle-row"><label>' + esc(ctrl.label) + '</label>' +
				'<span class="te-toggle"><input type="checkbox"' + (val ? ' checked' : '') + '><span class="te-track"></span></span></div>';
			wrap.querySelector('input').addEventListener('change', function (e) { changed(e.target.checked); });
			return wrap;
		}

		var labelHtml = '<label>' + esc(ctrl.label) + '</label>';

		if (ctrl.type === 'text' || ctrl.type === 'url') {
			wrap.innerHTML = labelHtml + '<input type="' + (ctrl.type === 'url' ? 'url' : 'text') + '" dir="auto" value="' + esc(val == null ? '' : val) + '">';
			wrap.querySelector('input').addEventListener('input', function (e) { changed(e.target.value); });
		} else if (ctrl.type === 'textarea') {
			wrap.innerHTML = labelHtml + '<textarea rows="4">' + esc(val == null ? '' : val) + '</textarea>';
			wrap.querySelector('textarea').addEventListener('input', function (e) { changed(e.target.value); });
		} else if (ctrl.type === 'color') {
			var presets = ctrl.presets || ['#C8102E', '#006341', '#22303f', '#d97706', '#475569'];
			var sw = presets.map(function (p) {
				return '<button type="button" class="te-swatch' + (String(val).toLowerCase() === p.toLowerCase() ? ' te-swatch-active' : '') + '" data-color="' + p + '" style="background:' + p + '" aria-label="' + p + '"></button>';
			}).join('');
			wrap.innerHTML = labelHtml + '<div class="te-swatches">' + sw +
				'<input type="text" class="te-hex" dir="ltr" value="' + esc(val == null ? '' : val) + '"></div>';
			wrap.querySelectorAll('.te-swatch').forEach(function (b) {
				b.addEventListener('click', function () {
					wrap.querySelectorAll('.te-swatch').forEach(function (x) { x.classList.remove('te-swatch-active'); });
					b.classList.add('te-swatch-active');
					wrap.querySelector('.te-hex').value = b.getAttribute('data-color');
					changed(b.getAttribute('data-color'));
				});
			});
			wrap.querySelector('.te-hex').addEventListener('input', function (e) { changed(e.target.value); });
		} else if (ctrl.type === 'stepper') {
			var min = ctrl.min != null ? ctrl.min : 1, max = ctrl.max != null ? ctrl.max : 12;
			wrap.innerHTML = labelHtml + '<div class="te-stepper"><button type="button" data-d="-1">−</button>' +
				'<input type="text" inputmode="numeric" value="' + esc(val == null ? min : val) + '"><button type="button" data-d="1">+</button></div>';
			var input = wrap.querySelector('input');
			function setNum(n) {
				n = Math.max(min, Math.min(max, parseInt(n, 10) || min));
				input.value = n;
				changed(n);
			}
			wrap.querySelectorAll('.te-stepper button').forEach(function (b) {
				b.addEventListener('click', function () { setNum((parseInt(input.value, 10) || 0) + parseInt(b.getAttribute('data-d'), 10)); });
			});
			input.addEventListener('change', function () { setNum(input.value); });
		} else if (ctrl.type === 'category_select') {
			var opts = '<option value="0">— کوئی نہیں —</option>' + (L.categories || []).map(function (c) {
				return '<option value="' + c.id + '"' + (parseInt(val, 10) === c.id ? ' selected' : '') + '>' + esc(c.name) + ' (' + c.count + ')</option>';
			}).join('');
			wrap.innerHTML = labelHtml + '<select>' + opts + '</select>';
			wrap.querySelector('select').addEventListener('change', function (e) { changed(parseInt(e.target.value, 10)); });
		} else if (ctrl.type === 'select' || ctrl.type === 'layout_picker') {
			var cards = (ctrl.options || []).map(function (o) {
				return '<button type="button" class="te-layout-card' + (String(val) === String(o.key) ? ' te-active' : '') + '" data-key="' + esc(o.key) + '">' + esc(o.label) + '</button>';
			}).join('');
			wrap.innerHTML = labelHtml + '<div class="te-layouts">' + cards + '</div>';
			wrap.querySelectorAll('.te-layout-card').forEach(function (b) {
				b.addEventListener('click', function () {
					wrap.querySelectorAll('.te-layout-card').forEach(function (x) { x.classList.remove('te-active'); });
					b.classList.add('te-active');
					changed(b.getAttribute('data-key'));
				});
			});
		} else {
			wrap.innerHTML = labelHtml + '<p class="te-hint">(' + esc(ctrl.type) + ')</p>';
		}
		return wrap;
	}

	/* ---------------- staged model + echo ---------------- */

	function setStaged(mod, value, ctrl) {
		var committedVal = committed[mod];
		if (JSON.stringify(value) === JSON.stringify(committedVal)) {
			delete staged[mod];
		} else {
			staged[mod] = value;
		}
		var owner = controlOwner(mod);
		if (owner && owner !== 'global') {
			// Category pattern: tamam category sections nishan-zada
			allSectionKeys().forEach(function (key) {
				var def = defForKey(key);
				if (def && def.id === owner) { dirtySections[key] = isDirtyFor(def); }
			});
		}
		applyEcho(mod, value, ctrl);
		refreshDirtyMarks();
		persistDraft();
	}

	function isDirtyFor(def) {
		return (def.controls || []).some(function (c) {
			return Object.prototype.hasOwnProperty.call(staged, c.theme_mod);
		});
	}

	function applyEcho(mod, value, ctrl) {
		if (!ctrl) { return; }
		if (ctrl.type === 'color' && ctrl.echo_vars) {
			ctrl.echo_vars.forEach(function (v) {
				document.documentElement.style.setProperty(v, value);
			});
		}
		if (ctrl.type === 'text' && ctrl.echo_selector && selectedKey && selectedKey !== 'global' && selectedKey !== 'list') {
			sectionElements(selectedKey).forEach(function (el) {
				var target = el.querySelector(ctrl.echo_selector);
				if (target) { target.textContent = value; }
			});
		}
	}

	function rollbackEchoes() {
		Object.keys(staged).forEach(function (mod) {
			var blocks = sectionDefs().concat(L.config.global ? [L.config.global] : []);
			blocks.forEach(function (b) {
				(b.controls || []).forEach(function (c) {
					if (c.theme_mod === mod) { applyEcho(mod, committed[mod], c); }
				});
			});
		});
	}

	/* ---------------- save / render / reset ---------------- */

	function restGet(path) {
		return fetch(L.restUrl + path, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': L.nonce }
		}).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok) { throw j; }
				return j;
			});
		});
	}

	function restPost(path, data) {
		return fetch(L.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': L.nonce },
			body: JSON.stringify(data)
		}).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok) { throw j; }
				return j;
			});
		});
	}

	function renderSectionInto(key) {
		return restPost('render-section', { section: key }).then(function (res) {
			var els = sectionElements(key);
			if (els.length && res.html) {
				var tmp = document.createElement('div');
				tmp.innerHTML = res.html;
				var fresh = tmp.firstElementChild;
				if (fresh) {
					els[0].replaceWith(fresh);
					decorate(fresh);
					if (selectedKey === key) { fresh.classList.add('te-selected'); }
				}
			}
		}).catch(function (err) {
			// Section ab render nahi hoti (masalan toggle band) — DOM se hata do
			if (err && err.code === 'te_not_found') {
				sectionElements(key).forEach(function (el) { el.remove(); });
			}
		});
	}

	function insertMissingSection(key) {
		return restPost('render-section', { section: key }).then(function (res) {
			if (!res.html) { return; }
			var tmp = document.createElement('div');
			tmp.innerHTML = res.html;
			var fresh = tmp.firstElementChild;
			if (!fresh) { return; }
			// Manifest order mein agli mojood section se pehle lagao
			var defs = sectionDefs().map(function (d) { return d.id; });
			var myIdx = defs.indexOf((defForKey(key) || {}).id);
			var anchor = null;
			allSectionKeys().forEach(function (k) {
				var d = defForKey(k);
				if (d && defs.indexOf(d.id) > myIdx && !anchor) {
					anchor = sectionElements(k)[0];
				}
			});
			if (anchor && anchor.parentNode) {
				anchor.parentNode.insertBefore(fresh, anchor);
			} else {
				var last = document.querySelector('[data-tameero-section]:last-of-type');
				if (last && last.parentNode) { last.parentNode.appendChild(fresh); }
			}
			decorate(fresh);
		}).catch(function () {});
	}

	function refreshSections(keys) {
		var chain = Promise.resolve();
		keys.forEach(function (key) {
			chain = chain.then(function () {
				if (sectionElements(key).length) { return renderSectionInto(key); }
				return insertMissingSection(key);
			});
		});
		return chain;
	}

	function doSave() {
		if (lockState === 'held') { showToast('آپ کے پاس اس وقت کنٹرول نہیں ہے۔', true); return Promise.resolve(); }
		if (!isDirty()) { return Promise.resolve(); }
		var mods = Object.keys(staged);
		var hadGlobal = mods.some(function (m) { return controlOwner(m) === 'global'; });
		var keys = Object.keys(dirtySections).filter(function (k) { return dirtySections[k]; });
		saveBtn.disabled = true;
		saveBtn.textContent = '…';
		return restPost('save-settings', { values: staged }).then(function (res) {
			Object.keys(res.saved || {}).forEach(function (k) { committed[k] = res.saved[k]; });
			staged = {};
			clearDraft();
			dirtySections = {};
			saveBtn.textContent = L.i18n.save;
			saveBtn.classList.remove('te-btn-error');
			showToast(L.i18n.saved, false);
			if (selectedKey && selectedKey !== 'list') { showControlsView(selectedKey); } else { showListView(); }
			if (hadGlobal) {
				// Header/footer sections ke bahir hain — un ke liye safha taza karna padta hai
				window.location.reload();
				return;
			}
			refreshSections(keys);
			refreshDirtyMarks();
		}).catch(function () {
			// Nakami: canvas committed par wapas, inputs mehfooz
			rollbackEchoes();
			refreshSections(keys);
			saveBtn.disabled = false;
			saveBtn.textContent = L.i18n.retry;
			saveBtn.classList.add('te-btn-error', 'te-shake');
			setTimeout(function () { saveBtn.classList.remove('te-shake'); }, 350);
			panelNote.hidden = false;
			panelNote.textContent = L.i18n.saveError;
			showToast(L.i18n.saveError, true);
		});
	}

	saveBtn.addEventListener('click', doSave);

	resetBtn.addEventListener('click', function () {
		var keys = Object.keys(dirtySections).filter(function (k) { return dirtySections[k]; });
		rollbackEchoes();
		staged = {};
		dirtySections = {};
		refreshSections(keys);
		if (selectedKey === 'global') { showControlsView('global'); }
		else if (selectedKey && selectedKey !== 'list') { showControlsView(selectedKey); }
		else { showListView(); }
		refreshDirtyMarks();
	});

	/* ---------------- exit guard ---------------- */

	document.getElementById('te-modal-save').addEventListener('click', function () {
		modal.hidden = true;
		doSave().then(function () {
			if (!isDirty()) { pendingExit = false; setMode(false, true); }
		});
	});
	document.getElementById('te-modal-leave').addEventListener('click', function () {
		modal.hidden = true;
		rollbackEchoes();
		staged = {};
		dirtySections = {};
		pendingExit = false;
		setMode(false, true);
	});
	document.getElementById('te-modal-cancel').addEventListener('click', function () {
		modal.hidden = true;
		pendingExit = false;
	});

	window.addEventListener('beforeunload', function (e) {
		if (modeOn && isDirty()) {
			e.preventDefault();
			e.returnValue = '';
		}
	});

	/* ---------------- toast ---------------- */

	var toastTimer = null;
	function showToast(msg, isError) {
		toast.textContent = msg;
		toast.classList.toggle('te-toast-error', !!isError);
		toast.hidden = false;
		toast.classList.add('te-show');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			toast.classList.remove('te-show');
			toast.hidden = true;
		}, 3000);
	}

	/* ---------------- boot ---------------- */

	var startOn = false;
	try { startOn = sessionStorage.getItem('te_on') === '1'; } catch (e) {}
	if (L.autoOn || startOn) {
		setMode(true);
		if (L.autoSection === 'categories') { showCategoriesView(); }
		else if (L.autoSection) { selectSection(L.autoSection, false); }
	}
})();
