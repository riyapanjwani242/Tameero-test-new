/**
 * TAMEERO YouTube — block editor side (frozen UI 2026-11-20).
 * URL paste -> JS halki jaanch (sirf UX; asal validation PHP
 * parser karta hai) -> videoId attribute -> ServerSideRender se
 * asal facade preview. Ghalat URL par saaf error + recovery.
 */
(function (blocks, element, blockEditor, components, serverSideRender) {
	var el = element.createElement;
	var useState = element.useState;
	var ServerSideRender = serverSideRender.default || serverSideRender;
	var InspectorControls = blockEditor.InspectorControls;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var Button = components.Button;
	var PanelBody = components.PanelBody;

	// Halka JS parser — PHP tameero_youtube_parse_url ki copy
	// (UX ke liye; authoritative PHP hai).
	function parseUrl(raw) {
		raw = (raw || '').trim();
		if (/^[A-Za-z0-9_-]{11}$/.test(raw)) { return { id: raw, start: 0, shorts: false }; }
		var u;
		try { u = new URL(raw.indexOf('://') === -1 ? 'https://' + raw : raw); } catch (e) { return null; }
		var hosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
			'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];
		var host = u.hostname.toLowerCase().replace(/\.$/, '');
		if (hosts.indexOf(host) === -1) { return null; }
		function startOf() {
			var t = u.searchParams.get('t') || u.searchParams.get('start') || '';
			if (/^\d+$/.test(t)) { return parseInt(t, 10); }
			var m = /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/.exec(t);
			if (m && (m[1] || m[2] || m[3])) { return (parseInt(m[1] || '0', 10) * 3600) + (parseInt(m[2] || '0', 10) * 60) + parseInt(m[3] || '0', 10); }
			return 0;
		}
		var id = null, shorts = false;
		if (host.indexOf('youtu.be') !== -1) {
			id = (u.pathname.split('/')[1] || '');
		} else if (u.pathname === '/watch') {
			id = u.searchParams.get('v') || '';
		} else {
			var mm = /^\/(shorts|embed|live|v)\/([^/?&#]+)/.exec(u.pathname);
			if (mm) { id = mm[2]; shorts = mm[1] === 'shorts'; }
		}
		if (!id || !/^[A-Za-z0-9_-]{11}$/.test(id)) { return null; }
		return { id: id, start: startOf(), shorts: shorts };
	}

	blocks.registerBlockType('tameero/youtube', {
		title: 'YouTube Video (TAMEERO)',
		description: 'YouTube link paste karein — tez click-to-play video (page load par koi iframe nahi).',
		icon: 'video-alt3',
		category: 'media',
		supports: { html: false },
		attributes: {
			videoId: { type: 'string', default: '' },
			url: { type: 'string', default: '' },
			aspect: { type: 'string', default: '16-9' },
			title: { type: 'string', default: '' },
			start: { type: 'number', default: 0 }
		},
		edit: function (props) {
			var a = props.attributes;
			var state = useState(null);
			var ui = state[0] || { editing: !a.videoId, urlText: a.url || '', error: '' };
			function setUi(patch) { state[1](Object.assign({}, ui, patch)); }

			function apply() {
				var p = parseUrl(ui.urlText);
				if (!p) {
					setUi({ error: 'Ye YouTube ka durust link nahi hai. Sirf YouTube ke video links chalte hain, masalan: https://www.youtube.com/watch?v=… ya https://youtu.be/… — link theek karke dobara Lagao dabayein, aap ka baqi content mehfooz hai.' });
					return;
				}
				props.setAttributes({
					videoId: p.id,
					url: ui.urlText.trim(),
					start: p.start || a.start || 0,
					aspect: p.shorts ? '9-16' : a.aspect
				});
				setUi({ editing: false, error: '' });
			}

			var children = [];
			children.push(el(InspectorControls, { key: 'insp' },
				el(PanelBody, { title: 'Video Settings', initialOpen: true },
					el(SelectControl, {
						label: 'Video ki shakal',
						value: a.aspect,
						options: [
							{ label: '16:9 — Wide (aam video)', value: '16-9' },
							{ label: '9:16 — Shorts (vertical)', value: '9-16' }
						],
						onChange: function (v) { props.setAttributes({ aspect: v }); }
					}),
					el(TextControl, {
						label: 'Title (ikhtiyari)',
						value: a.title,
						help: 'Khali chhor dein to title nahi dikhe ga.',
						onChange: function (v) { props.setAttributes({ title: v }); }
					}),
					el(TextControl, {
						label: 'Shuru ka waqt (seconds, ikhtiyari)',
						type: 'number',
						value: a.start ? String(a.start) : '',
						help: 'Link mein t=90 ho to ye khud bhar jata hai.',
						onChange: function (v) { props.setAttributes({ start: parseInt(v || '0', 10) || 0 }); }
					})
				)
			));

			if (ui.editing || !a.videoId) {
				children.push(el('div', { key: 'form', className: 'ty-editor' + (ui.error ? ' ty-editor--error' : '') },
					el('label', { className: 'ty-editor__label' }, 'YouTube video ka link'),
					el('div', { className: 'ty-editor__row' },
						el('input', {
							className: 'ty-editor__input',
							type: 'text',
							dir: 'ltr',
							placeholder: 'https://www.youtube.com/watch?v=…',
							value: ui.urlText,
							onChange: function (e) { setUi({ urlText: e.target.value, error: '' }); },
							onKeyDown: function (e) { if (e.key === 'Enter') { e.preventDefault(); apply(); } }
						}),
						el(Button, { variant: 'primary', onClick: apply }, 'Lagao')
					),
					ui.error
						? el('div', { className: 'ty-editor__error' }, '✕ ' + ui.error)
						: el('div', { className: 'ty-editor__hint' }, 'Watch link, youtu.be short link, Shorts ya Live — koi bhi YouTube link chalega.')
				));
			} else {
				children.push(el('div', { key: 'prev', className: 'ty-editor-preview' },
					el(ServerSideRender, { block: 'tameero/youtube', attributes: a }),
					el(Button, {
						variant: 'link',
						onClick: function () { setUi({ editing: true, urlText: a.url || '', error: '' }); }
					}, 'Video badlo')
				));
			}
			return el('div', blockEditor.useBlockProps(), children);
		},
		save: function () { return null; } // dynamic block — render PHP se
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
