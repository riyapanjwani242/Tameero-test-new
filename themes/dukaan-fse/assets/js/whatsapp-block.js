/**
 * Dukaan — WhatsApp Order block ka editor side.
 * Asal render PHP se hota hai (inc/dukaan.php); editor mein
 * ServerSideRender se wahi preview nazar ata hai. Product page ke
 * bahar block khali render hota hai — ye jaan boojh kar hai.
 */
( function ( blocks, element, serverSideRender, blockEditor ) {
	var el = element.createElement;
	var ServerSideRender = serverSideRender.default || serverSideRender;

	blocks.registerBlockType( 'dukaan/whatsapp-order', {
		title: 'WhatsApp Order Button (Dukaan)',
		description: 'Single product page par Add to Cart ke paas WhatsApp order button. Number: Settings → Dukaan.',
		icon: 'format-chat',
		category: 'widgets',
		supports: { html: false },
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( ServerSideRender, {
					block: 'dukaan/whatsapp-order',
					EmptyResponsePlaceholder: function () {
						return el(
							'p',
							{ style: { border: '1px dashed #7b1e2b', padding: '10px', textAlign: 'center' } },
							'WhatsApp Order button — sirf product page par dikhega (number: Settings → Dukaan)'
						);
					}
				} )
			);
		},
		save: function () {
			return null; // dynamic block — render PHP se
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.blockEditor );
