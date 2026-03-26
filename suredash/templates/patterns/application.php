<?php
/**
 * Application portal block pattern.
 *
 * @package SureDash
 * @since 1.5.0
 */

defined( 'ABSPATH' ) || exit;

$portal_menu_id = suredash_get_portal_menu_id();

return [
	'title'      => __( 'Application Layout', 'suredash' ),
	'categories' => [ 'suredash_portal' ],
	'blockTypes' => [ 'suredash/portal' ],
	'content'    => '<!-- wp:suredash/portal {"sidebartopoffset":"0px","metadata":{"categories":["suredash_portal"],"patternName":"suredash-application","name":"Application Layout"},"className":"portal-application-layout"} -->
<!-- wp:group {"metadata":{"name":"Responsive Header"},"className":"portal-hide-on-desktop hidden-on-lessons","style":{"position":{"type":"sticky","top":"0px"},"spacing":{"padding":{"top":"16px","bottom":"16px","left":"20px","right":"20px"}},"border":{"bottom":{"color":"var:preset|color|portal-global-color-8","width":"1px"}}},"backgroundColor":"portal-global-color-5","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group portal-hide-on-desktop hidden-on-lessons has-portal-global-color-5-background-color has-background" style="border-bottom-color:var(--wp--preset--color--portal-global-color-8);border-bottom-width:1px;padding-top:16px;padding-right:20px;padding-bottom:16px;padding-left:20px"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:suredash/identity {"elements":"title","width":"30px","responsivesidenavigation":true,"style":{"typography":{"fontWeight":"600","fontStyle":"normal"}},"fontSize":"medium"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:suredash/color-switcher {"style":{"elements":{"link":{"color":{"text":"var:preset|color|portal-global-color-4"}}},"color":{"background":"#ffffff00"}},"textColor":"portal-global-color-4"} /-->

<!-- wp:suredash/profile {"onlyavatar":true,"avatarsize":"40px","menuopenhorposition":"right","menuhorpositionoffset":"0px","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"metadata":{"name":"Content Part"},"align":"full","className":"portal-application-content-part","style":{"spacing":{"margin":{"top":"0","bottom":"0"},"blockGap":{"top":"0px","left":"0px"},"padding":{"top":"10px","bottom":"10px","left":"10px","right":"10px"}}}} -->
<div class="wp-block-columns alignfull portal-application-content-part" style="margin-top:0;margin-bottom:0;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px"><!-- wp:column {"width":"280px","metadata":{"name":"Sidebar"},"className":"portal-sidebar portal-hide-on-responsive sd-force-mt-20","style":{"spacing":{"padding":{"bottom":"20px","right":"15px","top":"0px","left":"10px"},"blockGap":"20px"},"color":{"background":"#ffffff00"}}} -->
<div class="wp-block-column portal-sidebar portal-hide-on-responsive sd-force-mt-20 portal-no-space-wrapper has-background" style="background-color:#ffffff00;padding-top:0px;padding-right:15px;padding-bottom:20px;padding-left:10px;flex-basis:280px"><!-- wp:suredash/identity {"elements":"title","width":"30px","responsivesidenavigation":true,"className":"sd-m-0","style":{"typography":{"fontWeight":"600","fontStyle":"normal"}},"fontSize":"medium"} /-->

<!-- wp:suredash/search {"inputborderradius":"4px","responsiveonlyicon":true,"className":"sd-pb-10 sd-pt-20","style":{"layout":{"columnSpan":1,"rowSpan":1}}} /-->

<!-- wp:suredash/navigation {"spacegroupsgap":"16px","spacegrouptitlefirstspacegap":"8px","spacesgap":"8px","style":{"elements":{"spaceactivebackground":{"color":{"background":"var(\u002d\u002dportal-global-color-1, #2563eb)"}},"spaceactivetext":{"color":{"color":"var(\u002d\u002dportal-global-color-5, #FFFFFF)"}},"link":{"color":{"text":"var:preset|color|portal-global-color-9"}}}},"textColor":"portal-global-color-9"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"","metadata":{"name":"Entry Container"},"className":"portal-no-space-wrapper has-background portal-application-content-wrapper","style":{"spacing":{"blockGap":"36px","padding":{"top":"0","bottom":"0"}},"border":{"width":"1px","color":"var(\u002d\u002dportal-global-color-8, #e5e5e5)","radius":"12px"}},"backgroundColor":"portal-global-color-5"} -->
<div class="wp-block-column portal-no-space-wrapper has-background portal-application-content-wrapper has-border-color has-portal-global-color-5-background-color" style="border-color:var(--portal-global-color-8, #e5e5e5);border-width:1px;border-radius:12px;padding-top:0;padding-bottom:0"><!-- wp:columns {"verticalAlignment":"center","align":"full","className":"portal-hide-on-responsive","style":{"spacing":{"padding":{"top":"16px","bottom":"16px","left":"20px","right":"20px"},"margin":{"top":"0","bottom":"0"}},"border":{"bottom":{"color":"var(\u002d\u002dportal-global-color-8, #E4E7EB)","width":"1px"},"radius":{"topLeft":"12px","topRight":"12px"}}}} -->
<div class="wp-block-columns alignfull are-vertically-aligned-center portal-hide-on-responsive" style="border-top-left-radius:12px;border-top-right-radius:12px;border-bottom-color:var(--portal-global-color-8, #E4E7EB);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:16px;padding-right:20px;padding-bottom:16px;padding-left:20px"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:suredash/title {"className":"sd-m-0","style":{"typography":{"fontWeight":"600","fontStyle":"normal"},"elements":{"link":{"color":{"text":"var:preset|color|portal-global-color-3"}}}},"textColor":"portal-global-color-3","fontSize":"medium"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","className":"hidden-on-lessons"} -->
<div class="wp-block-column is-vertically-aligned-center hidden-on-lessons"><!-- wp:navigation {"ref":"' . esc_attr( strval( $portal_menu_id ) ) . '","textColor":"portal-global-color-9","style":{"typography":{"textDecoration":"none","fontSize":"14px"},"spacing":{"blockGap":"var:preset|spacing|40"},"layout":{"columnSpan":1,"rowSpan":1}},"layout":{"type":"flex","justifyContent":"center"}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","className":"hidden-on-lessons"} -->
<div class="wp-block-column is-vertically-aligned-center hidden-on-lessons"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:suredash/color-switcher {"style":{"color":{"background":"#ffffff00"},"elements":{"link":{"color":{"text":"var:preset|color|portal-global-color-3"}}}},"textColor":"portal-global-color-3"} /-->

<!-- wp:suredash/notification {"drawerhorpositionoffset":"0px","drawerverpositionoffset":"50px","style":{"elements":{"iconcolor":{"color":{"stroke":"var(\u002d\u002dportal-global-color-3, #FFFFFF)"}}}}} /-->

<!-- wp:suredash/profile {"onlyavatar":true,"avatarsize":"40px","menuopenhorposition":"right","menuhorpositionoffset":"0px","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"className":"portal-content-section","style":{"border":{"radius":"12px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group portal-content-section" style="border-radius:12px"><!-- wp:suredash/content /--></div>
<!-- /wp:group --></div>
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
