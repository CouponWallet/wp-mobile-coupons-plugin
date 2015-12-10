<?php
/*
Plugin Name: Mobile Coupons
Plugin URI:  http://couponwallet.com
Description: Add mobile coupon capabilities to your website
Version:     0.1
Author:      CouponWallet
Author URI:  http://couponwallet.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /coupons
Text Domain: mobile-coupons
*/

// Exit if accessed directly
if( !defined('ABSPATH') ) {
  exit;
}

function cw_register_mobile_coupon_post_type() {
  $args = array(
    'public'=>true,
    'label'=>'Mobile Coupon'
    );
  register_post_type('mobile-coupon', $args);
}
add_action('init', 'cw_register_mobile_coupon_post_type');
