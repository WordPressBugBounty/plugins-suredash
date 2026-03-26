<?php
/**
 * Modern portal block pattern.
 *
 * @package SureDash
 * @since 0.0.6
 */

defined( 'ABSPATH' ) || exit;

$portal_menu_id = suredash_get_portal_menu_id();

return [
	'title'      => __( 'Modern Layout', 'suredash' ),
	'categories' => [ 'suredash_portal' ],
	'blockTypes' => [ 'suredash/portal' ],
	'content'    => '<!-- wp:suredash/portal {"sidebartopoffset":"72px","metadata":{"categories":["suredash_portal"],"patternName":"suredash-modern","name":"Modern Layout"}} -->
<!-- wp:group {"metadata":{"name":"Responsive Header"},"className":"portal-hide-on-desktop hidden-on-lessons","style":{"position":{"type":"sticky","top":"0px"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}},"border":{"bottom":{"color":"var:preset|color|portal-global-color-8","width":"1px"}}},"backgroundColor":"portal-global-color-5","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group portal-hide-on-desktop hidden-on-lessons has-portal-global-color-5-background-color has-background" style="border-bottom-color:var(--wp--preset--color--portal-global-color-8);border-bottom-width:1px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:suredash/identity {"elements":"title","width":"40px","responsivesidenavigation":true,"style":{"typography":{"fontWeight":"600","fontStyle":"normal"}},"fontSize":"medium"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:suredash/color-switcher {"style":{"elements":{"link":{"color":{"text":"var:preset|color|portal-global-color-3"}}},"color":{"background":"#ffffff00"}},"textColor":"portal-global-color-3"} /-->

<!-- wp:suredash/notification {"style":{"elements":{"iconcolor":{"color":{"stroke":"var(\u002d\u002dportal-global-color-3, #FFFFFF)"}}}}} /-->

<!-- wp:suredash/profile {"onlyavatar":true,"avatarsize":"40px","menuopenhorposition":"right","menuhorpositionoffset":"0px","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"metadata":{"name":"Desktop Header"},"className":"portal-sticky-header portal-hide-on-responsive hidden-on-lessons","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"0"}},"border":{"bottom":{"color":"var:preset|color|portal-global-color-8","width":"1px"}}},"backgroundColor":"portal-global-color-5"} -->
<div class="wp-block-columns portal-sticky-header portal-hide-on-responsive hidden-on-lessons has-portal-global-color-5-background-color has-background" style="border-bottom-color:var(--wp--preset--color--portal-global-color-8);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:column {"verticalAlignment":"center","width":"280px","style":{"spacing":{"padding":{"top":"24px","bottom":"24px","left":"36px","right":"12px"},"blockGap":"0"},"border":{"right":{"color":"var:preset|color|portal-global-color-8","width":"1px"},"top":[],"bottom":[],"left":[]}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="border-right-color:var(--wp--preset--color--portal-global-color-8);border-right-width:1px;padding-top:24px;padding-right:12px;padding-bottom:24px;padding-left:36px;flex-basis:280px"><!-- wp:suredash/identity {"elements":"title","width":"40px","responsivesidenavigation":true,"style":{"typography":{"fontWeight":"600","fontStyle":"normal"}},"fontSize":"medium"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"16px","bottom":"16px","left":"20px","right":"20px"},"blockGap":"0"}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-top:16px;padding-right:20px;padding-bottom:16px;padding-left:20px"><!-- wp:group {"className":"sd-items-center","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
<div class="wp-block-group sd-items-center" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"constrained","contentSize":"200px","justifyContent":"left"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:suredash/search {"inputborderradius":"99px","responsiveonlyicon":true,"style":{"layout":{"columnSpan":1,"rowSpan":1}}} /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"ref":"' . esc_attr( strval( $portal_menu_id ) ) . '","textColor":"portal-global-color-9","style":{"typography":{"textDecoration":"none","fontSize":"14px"},"spacing":{"blockGap":"var:preset|spacing|40"},"layout":{"columnSpan":1,"rowSpan":1}},"layout":{"type":"flex","justifyContent":"center"}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:suredash/color-switcher {"style":{"elements":{"link":{"color":{"text":"var:preset|color|portal-global-color-3"}}},"color":{"background":"#ffffff00"}},"textColor":"portal-global-color-3"} /-->

<!-- wp:suredash/notification {"drawerhorpositionoffset":"0px","drawerverpositionoffset":"50px","style":{"elements":{"iconcolor":{"color":{"stroke":"var(\u002d\u002dportal-global-color-3, #FFFFFF)"}}}}} /-->

<!-- wp:suredash/profile {"onlyavatar":true,"avatarsize":"40px","menuopenhorposition":"right","menuhorpositionoffset":"0px","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"metadata":{"name":"Content Part"},"align":"full","className":"sd-gap-0","style":{"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":{"top":"0px","left":"0px"}}}} -->
<div class="wp-block-columns alignfull sd-gap-0" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:column {"width":"280px","metadata":{"name":"Sidebar"},"className":"portal-sidebar portal-hide-on-responsive","style":{"spacing":{"padding":{"top":"24px","bottom":"24px","left":"24px","right":"24px"}},"border":{"right":{"color":"var:preset|color|portal-global-color-8","style":"solid","width":"1px"},"top":[],"bottom":[],"left":[]}}} -->
<div class="wp-block-column portal-sidebar portal-hide-on-responsive" style="border-right-color:var(--wp--preset--color--portal-global-color-8);border-right-style:solid;border-right-width:1px;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px;flex-basis:280px"><!-- wp:suredash/navigation {"spacegroupsgap":"16px","spacegrouptitlefirstspacegap":"8px","spacesgap":"8px","style":{"elements":{"spaceactivebackground":{"color":{"background":"var(\u002d\u002dportal-global-color-1, #2563eb)"}},"spaceactivetext":{"color":{"color":"var(\u002d\u002dportal-global-color-5, #FFFFFF)"}},"link":{"color":{"text":"var:preset|color|portal-global-color-9"}}}},"textColor":"portal-global-color-9"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"","metadata":{"name":"Entry Container"},"className":"portal-no-space-wrapper has-background","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0px","right":"0px"},"blockGap":"36px"}}} -->
<div class="wp-block-column portal-no-space-wrapper has-background" style="padding-top:0;padding-right:0px;padding-bottom:0;padding-left:0px"><!-- wp:group {"metadata":{"name":"Sub Header"},"style":{"border":{"bottom":{"color":"var:preset|color|portal-global-color-8","width":"1px"}},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"},"blockGap":"0","margin":{"top":"0","bottom":"0"}}},"backgroundColor":"portal-global-color-5","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group has-portal-global-color-5-background-color has-background" style="border-bottom-color:var(--wp--preset--color--portal-global-color-8);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><!-- wp:suredash/title {"style":{"typography":{"fontWeight":"600","fontStyle":"normal"},"elements":{"link":{"color":{"text":"var:preset|color|portal-global-color-3"}}}},"textColor":"portal-global-color-3","fontSize":"medium"} /--></div>
<!-- /wp:group -->

<!-- wp:columns {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns" style="margin-top:0;margin-bottom:0"><!-- wp:column {"style":{"spacing":{"padding":{"top":"0px","bottom":"0px","left":"0px","right":"0px"}}},"backgroundColor":"portal-global-color-6"} -->
<div class="wp-block-column has-portal-global-color-6-background-color has-background" style="padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px"><!-- wp:suredash/content /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:group {"metadata":{"name":"Responsive Application Footer"},"className":"portal-hide-on-desktop portal-application-footer","style":{"position":{"type":""},"spacing":{"padding":{"top":"12px","bottom":"12px","left":"24px","right":"24px"}},"border":{"top":{"color":"var:preset|color|portal-global-color-8","width":"1px"},"right":[],"bottom":[],"left":[]},"shadow":"var:preset|shadow|deep"},"backgroundColor":"portal-global-color-5","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group portal-hide-on-desktop portal-application-footer has-portal-global-color-5-background-color has-background" style="border-top-color:var(--wp--preset--color--portal-global-color-8);border-top-width:1px;padding-top:12px;padding-right:24px;padding-bottom:12px;padding-left:24px;box-shadow:var(--wp--preset--shadow--deep)"><!-- wp:suredash/dynamic-icons {"iconColor":"portal-global-color-4","iconColorValue":"var(\u002d\u002dportal-global-color-4, #E7F6FF)","className":"is-style-default","layout":{"type":"flex","justifyContent":"space-between"}} -->
<ul class="wp-block-suredash-dynamic-icons portal-icon-md has-icon-color is-style-default"><!-- wp:suredash/dynamic-icon {"url":"/portal/","service":"House","label":"Home"} /-->

<!-- wp:suredash/dynamic-icon {"url":"#portal-search-trigger","service":"Search","label":"Search","className":"portal-header-search-trigger"} /-->

<!-- wp:suredash/dynamic-icon {"url":"#portal-write-a-post","service":"Plus","label":"Post"} /-->

<!-- wp:suredash/dynamic-icon {"url":"/portal/screen/notification/","service":"Bell","label":"Notification"} /-->

<!-- wp:suredash/dynamic-icon {"url":"/portal/user-profile/","service":"User","label":"Profile"} /--></ul>
<!-- /wp:suredash/dynamic-icons --></div>
<!-- /wp:group -->
<!-- /wp:suredash/portal -->',
];
