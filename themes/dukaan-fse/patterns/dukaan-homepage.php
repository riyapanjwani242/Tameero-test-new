<?php
/**
 * Title: Dukaan Homepage
 * Slug: dukaan/homepage
 * Categories: dukaan
 * Keywords: dukaan, shop, homepage, storefront, urdu
 * Description: Dukaan ki homepage — hero, category tiles, naye products, deals aur promo banner (WooCommerce blocks).
 * Block Types: core/post-content
 * Post Types: page, wp_template
 * Viewport width: 1440
 *
 * @package dukaan
 */

?>

<!-- wp:group {"metadata":{"name":"Dukaan Hero"},"align":"full","backgroundColor":"theme-7","textColor":"theme-1","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem","left":"1.5rem","right":"1.5rem"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group alignfull has-theme-1-color has-theme-7-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:4rem;padding-right:1.5rem;padding-bottom:4rem;padding-left:1.5rem"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"theme-1","style":{"typography":{"lineHeight":"2"}}} -->
<h1 class="wp-block-heading has-text-align-center has-theme-1-color has-text-color" style="line-height:2"><?php esc_html_e( 'خوش آمدید — ہماری دکان', 'dukaan' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"2"}}} -->
<p class="has-text-align-center" style="line-height:2"><?php esc_html_e( 'بہترین کوالٹی بیڈ شیٹس اور کپڑے — واٹس ایپ پر آرڈر کریں، ڈیلیوری پورے پاکستان میں', 'dukaan' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"gold","textColor":"theme-2"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-theme-2-color has-gold-background-color has-text-color has-background wp-element-button" href="/shop/"><?php esc_html_e( 'ابھی خریداری کریں', 'dukaan' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Dukaan Categories"},"style":{"spacing":{"padding":{"top":"3rem","bottom":"1rem","left":"1.5rem","right":"1.5rem"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:3rem;padding-right:1.5rem;padding-bottom:1rem;padding-left:1.5rem"><!-- wp:heading {"textAlign":"center","style":{"typography":{"lineHeight":"2"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="line-height:2"><?php esc_html_e( 'کیٹیگریز', 'dukaan' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"dukaan-cat-tiles"} -->
<div class="wp-block-group dukaan-cat-tiles"><!-- wp:woocommerce/product-categories {"hasCount":true,"hasImage":false} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Dukaan New Products"},"style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.5rem","right":"1.5rem"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:2rem;padding-right:1.5rem;padding-bottom:2rem;padding-left:1.5rem"><!-- wp:heading {"textAlign":"center","style":{"typography":{"lineHeight":"2"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="line-height:2"><?php esc_html_e( 'ہماری کلیکشن', 'dukaan' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-collection {"queryId":101,"query":{"perPage":9,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"queryContextIncludes":["collection"]} -->
<div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true,"style":{"dimensions":{"aspectRatio":"1"}}} -->
<!-- wp:woocommerce/product-sale-badge {"align":"left"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->

<!-- wp:woocommerce/product-button /-->
<!-- /wp:woocommerce/product-template -->

<!-- wp:woocommerce/product-collection-no-results -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'جلد نئی پروڈکٹس آ رہی ہیں', 'dukaan' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:woocommerce/product-collection-no-results --></div>
<!-- /wp:woocommerce/product-collection --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Dukaan Deals"},"backgroundColor":"theme-5","style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.5rem","right":"1.5rem"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-theme-5-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:2rem;padding-right:1.5rem;padding-bottom:2rem;padding-left:1.5rem"><!-- wp:heading {"textAlign":"center","style":{"typography":{"lineHeight":"2"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="line-height:2"><?php esc_html_e( 'ڈیلز — سیل والی پروڈکٹس', 'dukaan' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-collection {"queryId":102,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":true,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"queryContextIncludes":["collection"]} -->
<div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true,"style":{"dimensions":{"aspectRatio":"1"}}} -->
<!-- wp:woocommerce/product-sale-badge {"align":"left"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->

<!-- wp:woocommerce/product-button /-->
<!-- /wp:woocommerce/product-template -->

<!-- wp:woocommerce/product-collection-no-results -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'ابھی کوئی پروڈکٹ سیل پر نہیں ہے', 'dukaan' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:woocommerce/product-collection-no-results --></div>
<!-- /wp:woocommerce/product-collection --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Dukaan Promo"},"align":"full","backgroundColor":"gold","style":{"spacing":{"padding":{"top":"2.5rem","bottom":"2.5rem","left":"1.5rem","right":"1.5rem"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group alignfull has-gold-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:2.5rem;padding-right:1.5rem;padding-bottom:2.5rem;padding-left:1.5rem"><!-- wp:heading {"textAlign":"center","style":{"typography":{"lineHeight":"2"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="line-height:2"><?php esc_html_e( 'پہلا آرڈر؟ واٹس ایپ پر رابطہ کریں', 'dukaan' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"2"}}} -->
<p class="has-text-align-center" style="line-height:2"><?php esc_html_e( 'کوئی سوال ہو، سائز یا رنگ کی تصدیق چاہیے — پروڈکٹ پیج سے سیدھا واٹس ایپ پر بات کریں — ایڈوانس ادائیگی پر آرڈر کنفرم ہوتا ہے', 'dukaan' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"purple/store-features-three-columns"} /-->
