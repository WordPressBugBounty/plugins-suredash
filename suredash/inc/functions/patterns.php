<?php
/**
 * SureDash Patterns.
 *
 * @package SureDash
 * @since 1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Utils\Helper;

/**
 * Login default single column centered pattern.
 *
 * @since 1.4.0
 *
 * @return string pattern content.
 */
function suredash_login_single_column_centered_pattern() {
	// phpcs:disable WordPressVIPMinimum.Security.Mustache.OutputNotation
	return '<!-- wp:cover {"customOverlayColor":"#f9fafb","isUserOverlayColor":true,"minHeight":100,"minHeightUnit":"vh","metadata":{"categories":["suredash_auth"],"patternName":"suredash-login-centered-column","name":"Login Centered Layout"},"align":"full","layout":{"type":"constrained","contentSize":"448px"}} -->
<div class="wp-block-cover alignfull" style="min-height:100vh"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim" style="background-color:#f9fafb"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"style":{"border":{"color":"#e5e7eb","style":"solid","width":"1px","radius":"12px"},"spacing":{"blockGap":"12px","padding":{"right":"32px","left":"32px","top":"32px","bottom":"32px"}}},"backgroundColor":"white","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","verticalAlignment":"center"}} -->
<div class="wp-block-group has-border-color has-white-background-color has-background" style="border-color:#e5e7eb;border-style:solid;border-width:1px;border-radius:12px;padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px"><!-- wp:suredash/login {"block_id":"3a75e4b1","formBorderTopWidth":0,"formBorderLeftWidth":0,"formBorderRightWidth":0,"formBorderBottomWidth":0,"formBorderStyle":"none","fieldsBorderTopWidth":1,"fieldsBorderLeftWidth":1,"fieldsBorderRightWidth":1,"fieldsBorderBottomWidth":1,"fieldsBorderStyle":"solid","fieldsBorderColor":"#d1d5db","loginBorderTopLeftRadius":6,"loginBorderTopRightRadius":6,"loginBorderBottomLeftRadius":6,"loginBorderBottomRightRadius":6,"loginBorderStyle":"solid","formTopPadding":0,"formRightPadding":0,"formLeftPadding":0,"formBottomPadding":0,"labelColor":"#111827","linkColor":"","fieldsBackground":"","placeholderColor":"","fieldsColor":"","formHeadingText":"Login to Your Account","loginBackground":"#4338ca","loginColor":"#ffffff","loginHBackground":"","loginHColor":"","loginTransform":"none","registerInfo":"Don\'t have an account? ","registerButtonLabel":"Sign up","registerButtonLink":{"url":"' . esc_url( home_url( '/portal-register/' ) ) . '","id":390,"title":"Portal Register","type":"page","kind":"post-type"},"registerInfoColor":"#4b5563","facebookTopPadding":14,"facebookRightPadding":15,"facebookLeftPadding":15,"facebookBottomPadding":14,"googleBackground":"","googleColor":"","googleTopPadding":14,"googleRightPadding":15,"googleLeftPadding":15,"googleBottomPadding":14,"eyeIconSize":19,"eyeIconColor":""} /--></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
	';
}

/**
 * Login default two columns pattern.
 *
 * @since 1.4.0
 *
 * @return string pattern content.
 */
function suredash_login_two_columns_pattern() {
	return '<!-- wp:columns {"verticalAlignment":null,"metadata":{"categories":["suredash_auth"],"patternName":"suredash-login-two-column","name":"Two Column Login Layout"},"align":"full","style":{"spacing":{"blockGap":{"top":"0px","left":"var:preset|spacing|50"},"margin":{"top":"0px","bottom":"0px"},"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"dimensions":{"minHeight":"100vh"}},"backgroundColor":"white"} -->
<div class="wp-block-columns alignfull has-white-background-color has-background" style="min-height:100vh;margin-top:0px;margin-bottom:0px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:column {"verticalAlignment":"stretch","width":"60%","className":"portal-centered-content","style":{"spacing":{"padding":{"top":"60px","bottom":"60px","left":"60px","right":"60px"}},"color":{"background":"#f9fafb"},"border":{"radius":"24px"}},"layout":{"type":"default"}} -->
<div class="wp-block-column is-vertically-aligned-stretch portal-centered-content has-background" style="border-radius:24px;background-color:#f9fafb;padding-top:60px;padding-right:60px;padding-bottom:60px;padding-left:60px;flex-basis:60%"><!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"700","fontSize":"32px","lineHeight":"1.2","fontStyle":"normal"},"spacing":{"margin":{"bottom":"var:preset|spacing|30"}},"color":{"text":"#111827"}}} -->
<h1 class="wp-block-heading has-text-color" style="color:#111827;margin-bottom:var(--wp--preset--spacing--30);font-size:32px;font-style:normal;font-weight:700;line-height:1.2">Welcome Back!</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"},"color":{"text":"#4b5563"},"spacing":{"margin":{"top":"0","right":"0","left":"0","bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<p class="has-text-color" style="color:#4b5563;margin-top:0;margin-right:0;margin-bottom:var(--wp--preset--spacing--50);margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;font-size:18px;line-height:1.6">Sign in to your community account and connect with fellow members.</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Quick Access</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Jump straight into discussions and resources.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Community Connection</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Connect with like-minded community members.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"style":{"spacing":{"padding":{"right":"0","left":"0"},"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns" style="padding-right:0;padding-left:0"><!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Exclusive Content</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Have access member-only resources and content.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Stay Updated</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Get notified about new discussions and updates.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Premium Features</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Unlock advanced tools and member only benefits.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Growth Tracking</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Keep track on your progress and  achievements.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch","width":"40%","className":"portal-centered-content","style":{"spacing":{"padding":{"top":"60px","bottom":"60px","left":"60px","right":"60px"}},"border":{"radius":"8px"}}} -->
<div class="wp-block-column is-vertically-aligned-stretch portal-centered-content" style="border-radius:8px;padding-top:60px;padding-right:60px;padding-bottom:60px;padding-left:60px;flex-basis:40%"><!-- wp:suredash/login {"block_id":"auth_login_2col","formBorderTopWidth":0,"formBorderLeftWidth":0,"formBorderRightWidth":0,"formBorderBottomWidth":0,"formBorderTopLeftRadius":0,"formBorderTopRightRadius":0,"formBorderBottomLeftRadius":0,"formBorderBottomRightRadius":0,"formBorderStyle":"none","fieldsBorderTopWidth":1,"fieldsBorderLeftWidth":1,"fieldsBorderRightWidth":1,"fieldsBorderBottomWidth":1,"fieldsBorderStyle":"solid","fieldsBorderColor":"#d1d5db","loginBorderTopLeftRadius":6,"loginBorderTopRightRadius":6,"loginBorderBottomLeftRadius":6,"loginBorderBottomRightRadius":6,"loginBorderStyle":"solid","formTopPadding":0,"formRightPadding":0,"formLeftPadding":0,"formBottomPadding":0,"formTopPaddingMobile":0,"formRightPaddingMobile":0,"formLeftPaddingMobile":0,"formBottomPaddingMobile":0,"labelColor":"#111827","labelFontSize":null,"linkColor":"#4f46e5","fieldsBackground":"#ffffff","placeholderColor":"#9ca3af","fieldsColor":"#111827","formHeadingText":"Log in to your account","loginBackground":"#4338ca","loginColor":"#ffffff","loginHBackground":"#4338ca","loginHColor":"#ffffff","loginTransform":"none","registerInfo":"Don\'t have an account? ","registerButtonLabel":"Sign up","registerButtonLink":{"url":"' . esc_url( home_url( '/portal-register/' ) ) . '","id":390,"title":"Portal Register","type":"page","kind":"post-type"},"registerInfoColor":"#6b7280","facebookTopPadding":14,"facebookRightPadding":15,"facebookLeftPadding":15,"facebookBottomPadding":14,"googleBackground":"#ffffff","googleColor":"#374151","googleTopPadding":14,"googleRightPadding":15,"googleLeftPadding":15,"googleBottomPadding":14,"eyeIconSize":19,"eyeIconColor":"#6b7280","backgroundColor":""} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
}

/**
 * Register default single column centered pattern.
 *
 * @since 1.4.0
 *
 * @return string pattern content.
 */
function suredash_register_single_column_centered_pattern() {
	// phpcs:disable WordPressVIPMinimum.Security.Mustache.OutputNotation
	return '<!-- wp:cover {"customOverlayColor":"#f9fafb","isUserOverlayColor":true,"minHeight":100,"minHeightUnit":"vh","metadata":{"categories":["suredash_auth"],"patternName":"suredash-register-centered-column","name":"Register Centered Layout"},"align":"full","layout":{"type":"constrained","contentSize":"448px"}} -->
<div class="wp-block-cover alignfull" style="min-height:100vh"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim" style="background-color:#f9fafb"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"style":{"border":{"color":"#e5e7eb","style":"solid","width":"1px","radius":"12px"},"spacing":{"blockGap":"12px","padding":{"right":"32px","left":"32px","top":"32px","bottom":"32px"}}},"backgroundColor":"white","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","verticalAlignment":"center"}} -->
<div class="wp-block-group has-border-color has-white-background-color has-background" style="border-color:#e5e7eb;border-style:solid;border-width:1px;border-radius:12px;padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px"><!-- wp:suredash/register {"formBorderStyle":"none","block_id":"58185566","loginInfo":"Already have an account? ","formHeadingText":"Join The Community","btnLoginLink":{"url":"' . esc_url( home_url( '/portal-login/' ) ) . '","id":389,"title":"Portal Login","type":"page","kind":"post-type"},"btnSubmitLabel":"Sign Up","afterRegisterActions":["autoLogin","redirect"],"autoLoginRedirectURL":{"url":"/portal/"},"formTopPadding":0,"formRightPadding":0,"formLeftPadding":0,"formBottomPadding":0,"loginInfoColor":"#4b5563","labelColor":"#111827","fieldBorderTopWidth":1,"fieldBorderLeftWidth":1,"fieldBorderRightWidth":1,"fieldBorderBottomWidth":1,"fieldBorderTopLeftRadius":8,"fieldBorderTopRightRadius":8,"fieldBorderBottomLeftRadius":8,"fieldBorderBottomRightRadius":8,"fieldBorderStyle":"solid","fieldBorderColor":"#d1d5db","paddingFieldTop":12,"paddingFieldRight":12,"paddingFieldBottom":12,"paddingFieldLeft":12,"paddingFieldLink":true,"registerBtnColor":"#ffffff","registerBtnBgColor":"#4338ca","btnBorderTopLeftRadius":6,"btnBorderTopRightRadius":6,"btnBorderBottomLeftRadius":6,"btnBorderBottomRightRadius":6,"btnBorderStyle":"none","registerBtnTransform":"none","googlePaddingBtnTop":14,"googlePaddingBtnRight":15,"googlePaddingBtnBottom":14,"googlePaddingBtnLeft":15,"facebookPaddingBtnTop":14,"facebookPaddingBtnRight":15,"facebookPaddingBtnBottom":14,"facebookPaddingBtnLeft":15} -->
<div class="wp-block-suredash-register wp-block-spectra-pro-register uagb-block-58185566"><h3 class="spectra-pro-register-form__heading">Join The Community</h3><form action="#" class="spectra-pro-register-form" method="post" name="spectra-pro-register-form-58185566" id="spectra-pro-register-form-58185566"><input type="hidden" name="_nonce" value="ssr_nonce_replace"/><!-- wp:suredash/register-first-name {"block_id":"6c85d198"} -->
<div class="wp-block-suredash-register-first-name spectra-pro-register-form__name uagb-block-6c85d198"><label for="spectra-pro-register-form__first-name-input-6c85d198" class="spectra-pro-register-form__name-label " id="6c85d198">First Name</label><input id="spectra-pro-register-form__first-name-input-6c85d198" type="text" placeholder="First Name" class="spectra-pro-register-form__name-input" name="first_name"/></div>
<!-- /wp:suredash/register-first-name -->

<!-- wp:suredash/register-last-name {"block_id":"bacf5633"} -->
<div class="wp-block-suredash-register-last-name spectra-pro-register-form__name uagb-block-bacf5633"><label for="spectra-pro-register-form__last-name-input-bacf5633" class="spectra-pro-register-form__name-label " id="bacf5633">Last Name</label><input id="spectra-pro-register-form__last-name-input-bacf5633" type="text" placeholder="Last Name" class="spectra-pro-register-form__name-input" name="last_name"/></div>
<!-- /wp:suredash/register-last-name -->

<!-- wp:suredash/register-username {"block_id":"291286b8","name":"username","icon":"UserPen"} -->
<div class="wp-block-suredash-register-username spectra-pro-register-form__username uagb-block-291286b8"><label for="spectra-pro-register-form__username-input-291286b8" class="spectra-pro-register-form__username-label " id="291286b8">Username</label><input id="spectra-pro-register-form__username-input-291286b8" type="text" placeholder="Username" class="spectra-pro-register-form__username-input" name="username"/></div>
<!-- /wp:suredash/register-username -->

<!-- wp:suredash/register-email {"block_id":"08d2ab15","name":"email","icon":"Mail"} -->
<div class="wp-block-suredash-register-email spectra-pro-register-form__email uagb-block-08d2ab15"><label for="spectra-pro-register-form__email-input-08d2ab15" class="spectra-pro-register-form__email-label required" id="08d2ab15">Email</label><input id="spectra-pro-register-form__email-input-08d2ab15" type="email" class="spectra-pro-register-form__email-input" placeholder="Email" required name="email"/></div>
<!-- /wp:suredash/register-email -->

<!-- wp:suredash/register-password {"block_id":"a71f6c17"} -->
<div class="wp-block-suredash-register-password spectra-pro-register-form__password uagb-block-a71f6c17"><label for="spectra-pro-register-form__password-input-a71f6c17" class="spectra-pro-register-form__password-label required" id="a71f6c17">Password</label><input id="spectra-pro-register-form__password-input-a71f6c17" type="password" class="spectra-pro-register-form__password-input" placeholder="Password" required name="password"/></div>
<!-- /wp:suredash/register-password -->

<!-- wp:suredash/register-terms {"block_id":"5f2022e6","name":"terms"} -->
<div class="wp-block-suredash-register-terms spectra-pro-register-form__terms uagb-block-5f2022e6"><div class="spectra-pro-register-form__terms-wrap"><label for="rememberme-5f2022e6" class="spectra-pro-register-form__terms-checkbox"> <input id="rememberme-5f2022e6" type="checkbox" required class="spectra-pro-register-form__terms-checkbox-input" name="terms"/><span class="spectra-pro-register-form__terms-checkbox-checkmark"></span></label><label for="rememberme-5f2022e6" class="spectra-pro-register-form__terms-label required">I Accept the Terms and Conditions.</label></div></div>
<!-- /wp:suredash/register-terms --><div class="wp-block-button"><button class="spectra-pro-register-form__submit wp-block-button__link" type="submit"><span class="label-wrap">Sign Up</span></button></div></form><div class="spectra-pro-register-form-status"></div><div class="spectra-pro-register-form__footer"><p class="spectra-pro-register-login-info"> Already have an account?  <a rel="noopener" href="' . esc_url( home_url( '/portal-login/' ) ) . '" class="spectra-pro-register-form-link"> Login </a></p></div></div>
<!-- /wp:suredash/register --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->';
}

/**
 * Register default two columns pattern.
 *
 * @since 1.4.0
 *
 * @return string pattern content.
 */
function suredash_register_two_columns_pattern() {
	return '<!-- wp:columns {"metadata":{"categories":["suredash_auth"],"patternName":"suredash-register-two-column","name":"Two Column Registration Layout"},"align":"full","style":{"spacing":{"blockGap":{"top":"0px","left":"var:preset|spacing|50"},"margin":{"top":"0px","bottom":"0px"},"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"dimensions":{"minHeight":"100vh"}},"backgroundColor":"white"} -->
<div class="wp-block-columns alignfull has-white-background-color has-background" style="min-height:100vh;margin-top:0px;margin-bottom:0px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:column {"verticalAlignment":"stretch","width":"60%","className":"portal-centered-content","style":{"spacing":{"padding":{"top":"60px","bottom":"60px","left":"60px","right":"60px"}},"color":{"background":"#f9fafb"},"border":{"radius":"24px"}},"layout":{"type":"default"}} -->
<div class="wp-block-column is-vertically-aligned-stretch portal-centered-content has-background" style="border-radius:24px;background-color:#f9fafb;padding-top:60px;padding-right:60px;padding-bottom:60px;padding-left:60px;flex-basis:60%"><!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"700","fontSize":"32px","lineHeight":"1.2","fontStyle":"normal"},"spacing":{"margin":{"bottom":"var:preset|spacing|30"}},"color":{"text":"#111827"}}} -->
<h1 class="wp-block-heading has-text-color" style="color:#111827;margin-bottom:var(--wp--preset--spacing--30);font-size:32px;font-style:normal;font-weight:700;line-height:1.2">Join Our Community</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"},"color":{"text":"#4b5563"},"spacing":{"margin":{"top":"0","right":"0","left":"0","bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<p class="has-text-color" style="color:#4b5563;margin-top:0;margin-right:0;margin-bottom:var(--wp--preset--spacing--50);margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;font-size:18px;line-height:1.6">Create your account and become part of an amazing community of creators, learners, and innovators.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Easy Onboarding</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Sign up takes less than 2 minutes!</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">What You\'ll Get</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Jump straight into discussions and resources.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"},"spacing":{"padding":{"left":"20px"}}}} -->
<ul style="color:#4b5563;padding-left:20px;font-size:14px" class="wp-block-list has-text-color"><!-- wp:list-item -->
<li>Access to exclusive member content.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Direct connection with community experts.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Participate in discussions and forums.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Early access to new features and updates.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"600","fontSize":"16px","fontStyle":"normal"},"color":{"text":"#111827"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#111827;font-size:16px;font-style:normal;font-weight:600">Getting Started</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"color":{"text":"#4b5563"}}} -->
<p class="has-text-color" style="color:#4b5563;font-size:14px">Join thousands of members who are already part of this thriving community.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch","width":"40%","className":"portal-centered-content","style":{"spacing":{"padding":{"top":"60px","bottom":"60px","left":"60px","right":"60px"}},"border":{"radius":"8px"}}} -->
<div class="wp-block-column is-vertically-aligned-stretch portal-centered-content" style="border-radius:8px;padding-top:60px;padding-right:60px;padding-bottom:60px;padding-left:60px;flex-basis:40%"><!-- wp:suredash/register {"formBorderStyle":"none","block_id":"be9ab48a","loginInfo":"Already have an account? ","formHeadingText":"Join The Community","btnLoginLink":{"url":"' . esc_url( home_url( '/portal-login/' ) ) . '","id":14001,"title":"Portal Login","type":"page","kind":"post-type"},"formTopPadding":0,"formRightPadding":0,"formLeftPadding":0,"formBottomPadding":0,"formPaddingLink":false,"labelColor":"#111827","registerBtnColor":"#ffffff","registerBtnBgColor":"#4338ca","btnBorderTopLeftRadius":6,"btnBorderTopRightRadius":6,"btnBorderBottomLeftRadius":6,"btnBorderBottomRightRadius":6,"btnBorderStyle":"none","registerBtnTransform":"none","googlePaddingBtnTop":14,"googlePaddingBtnRight":15,"googlePaddingBtnBottom":14,"googlePaddingBtnLeft":15,"facebookPaddingBtnTop":14,"facebookPaddingBtnRight":15,"facebookPaddingBtnBottom":14,"facebookPaddingBtnLeft":15} -->
<div class="wp-block-suredash-register wp-block-spectra-pro-register uagb-block-be9ab48a"><h3 class="spectra-pro-register-form__heading">Join The Community</h3><form action="#" class="spectra-pro-register-form" method="post" name="spectra-pro-register-form-be9ab48a" id="spectra-pro-register-form-be9ab48a"><input type="hidden" name="_nonce" value="ssr_nonce_replace"/><!-- wp:suredash/register-first-name {"block_id":"c2c050d2"} -->
<div class="wp-block-suredash-register-first-name spectra-pro-register-form__name uagb-block-c2c050d2"><label for="spectra-pro-register-form__first-name-input-c2c050d2" class="spectra-pro-register-form__name-label " id="c2c050d2">First Name</label><input id="spectra-pro-register-form__first-name-input-c2c050d2" type="text" placeholder="First Name" class="spectra-pro-register-form__name-input" name="first_name"/></div>
<!-- /wp:suredash/register-first-name -->

<!-- wp:suredash/register-last-name {"block_id":"16fb0ccf"} -->
<div class="wp-block-suredash-register-last-name spectra-pro-register-form__name uagb-block-16fb0ccf"><label for="spectra-pro-register-form__last-name-input-16fb0ccf" class="spectra-pro-register-form__name-label " id="16fb0ccf">Last Name</label><input id="spectra-pro-register-form__last-name-input-16fb0ccf" type="text" placeholder="Last Name" class="spectra-pro-register-form__name-input" name="last_name"/></div>
<!-- /wp:suredash/register-last-name -->

<!-- wp:suredash/register-username {"block_id":"ba620570","name":"username"} -->
<div class="wp-block-suredash-register-username spectra-pro-register-form__username uagb-block-ba620570"><label for="spectra-pro-register-form__username-input-ba620570" class="spectra-pro-register-form__username-label " id="ba620570">Username</label><input id="spectra-pro-register-form__username-input-ba620570" type="text" placeholder="Username" class="spectra-pro-register-form__username-input" name="username"/></div>
<!-- /wp:suredash/register-username -->

<!-- wp:suredash/register-email {"block_id":"f6dff8b9","name":"email"} -->
<div class="wp-block-suredash-register-email spectra-pro-register-form__email uagb-block-f6dff8b9"><label for="spectra-pro-register-form__email-input-f6dff8b9" class="spectra-pro-register-form__email-label required" id="f6dff8b9">Email</label><input id="spectra-pro-register-form__email-input-f6dff8b9" type="email" class="spectra-pro-register-form__email-input" placeholder="Email" required name="email"/></div>
<!-- /wp:suredash/register-email -->

<!-- wp:suredash/register-password {"block_id":"8fec5e71"} -->
<div class="wp-block-suredash-register-password spectra-pro-register-form__password uagb-block-8fec5e71"><label for="spectra-pro-register-form__password-input-8fec5e71" class="spectra-pro-register-form__password-label required" id="8fec5e71">Password</label><input id="spectra-pro-register-form__password-input-8fec5e71" type="password" class="spectra-pro-register-form__password-input" placeholder="Password" required name="password"/></div>
<!-- /wp:suredash/register-password --><div class="wp-block-button"><button class="spectra-pro-register-form__submit wp-block-button__link" type="submit"><span class="label-wrap">Register</span></button></div></form><div class="spectra-pro-register-form-status"></div><div class="spectra-pro-register-form__footer"><p class="spectra-pro-register-login-info"> Already have an account?  <a rel="noopener" href="' . esc_url( home_url( '/portal-login/' ) ) . '" class="spectra-pro-register-form-link"> Login </a></p></div></div>
<!-- /wp:suredash/register --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
}
