<?php
/*
Plugin Name: Coupons
Plugin URI:  http://couponwallet.com
Description: Add printable and mobile coupon capabilities to your website
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

// the options to set in settings page
function cw_add_options(){
	add_option('api_key');
	add_option('business_id');
	add_option('locations');
}

//adds settings to the cw_settings group
function cw_register_setting() {
	register_setting( 'cw_settings', 'api_key', 'cw_sanitize_api_key'); 
	register_setting( 'cw_settings', 'business_id', 'cw_sanitize_id');
	register_setting( 'cw_settings', 'locations');  
} 

if(is_admin()){
	add_action( 'admin_init', 'cw_register_setting' );
	add_action( 'admin_init', 'cw_add_options' );
}


// ----------  Short Code(s)  ------------
// [coupons max="6" mobile="true" printable="true" qr="false" barcode="false"]
function coupons_func( $atts ) {
    $a = shortcode_atts( array(
        'max' => 12,
        'printable' => true,
        'mobile' => true,
        'qr' => false,
        'barcode' => false,
    ), $atts );

  $args=array(
    'post_type' => 'mobile-coupon',
    'post_status' => 'publish',
    'posts_per_page' => $a['max'],
    'caller_get_posts'=> 1
  );

  $my_query = null;
  $my_query = new WP_Query($args);
  if( $my_query->have_posts() ) { ?>
    <div class="coupons"> <?php
      while ($my_query->have_posts()){ 
        $my_query->the_post(); ?>
        <div class="coupon" id="coupon<?php echo get_the_ID(); ?>">      
	    <!--<div class="title"><?php the_title(); ?></div>-->
	    <div class="discount"><?php 
			$discount = get_post_meta(get_the_ID(), 'discount', true);
			$cents = str_pad($discount*100%100, 2, '0', STR_PAD_LEFT);
			echo floor($discount).'<span class="cents">'.$cents.'</span>'; ?></div>
	    <div class="pos_text"><?php echo get_post_meta(get_the_ID(), 'pos_text', true); ?></div>
        <div class="expires"><?php echo get_post_meta(get_the_ID(), 'expires', true); ?></div>
        <div class="fine_text"></div>
        <?php
        if($a['barcode'] && $a['barcode']!='false') {
            echo "<div class=\"barcode\"><img src=\"http://www.barcode-generator.org/zint/api.php?bc_number=20&bc_data=".get_post_meta(get_the_ID(), 'sku', true)."\" /></barcode>";
        }
        ?>
        <div class="controls">
            <?php
            if(($a['mobile'] && $a['mobile']!='false') || ($a['qr'] && $a['qr']!='false')) {
            	if($a['qr'] && $a['qr']!='false') {
            		echo "<div class=\"mobile-control\"><a class=\"button\" onClick=\"$('#qr".get_the_ID()."').toggle();\"><i class=\"fa fa-mobile\"></i><span class=\"label\">Mobile</span></a></div>";
            		echo "<div class=\"mobile-qr\" id=\"qr".get_the_ID()."\" onClick=\"$('#qr".get_the_ID()."').toggle();\"><img src=\"https://api.qrserver.com/v1/create-qr-code/?data=http%3A%2F%2Fcouponwallet.com%2Fonline%2Flocal%2Fcoupon%2Fview%2F".get_post_meta(get_the_ID(), 'q', true)."&size=220x220&margin=10\" /></div>";
            	}
            }
            if($a['printable'] && $a['printable']!='false') {
            	echo "<div class=\"print-control\"><a class=\"button\" id=\"printButton".get_the_ID()."\"><i class=\"fa fa-print\"></i><span class=\"label\">Print</span></a></div>";
            	echo "<script>\$(document).ready(function() {\$(\"#printButton".get_the_ID()."\").click(function(){\$(\"#coupon".get_the_ID()."\").printElement();});});</script>";
            }
            ?>
        </div>
	</div>
        <?php
      }
    ?>
    </div> <?php
  } else {
    ?><div class="no_coupon_text">Check back later for more coupons!</div> <?php
  }
  wp_reset_query();  // Restore global post data stomped by the_post().
}
add_shortcode( 'coupons', 'coupons_func' );
// ---------------------------------------

// ---------  Coupon Post Type  -----------
function cw_register_mobile_coupon_post_type() {
  
	//set UI labels
	$labels = array(
		"name" 			=> "Coupons",
		"singular_name" 	=> "Coupon",
		"parent_item_colon"	=> "Parent Coupon",
		"menu_item" 		=> "Coupons",
		"all_items" 		=> "All Coupons",
		"view_item"		=> "View Coupon",
		"add_new_item"		=> "Add New Coupon",
		"add_new"		=> "Add New",
		"edit_item"		=> "Edit Coupon",
		"update_item"		=> "Update Coupon",
		"search_items"		=> "Search Coupons",
		"not_found"		=> "Not Found",
		"not_found_in_trash" 	=> "Not Found in Trash",
	);

	//set other options for custom post type
	$args = array(
        	'description'         => __( 'Mobile Coupons', 'twentythirteen'),
       		'labels'              => $labels,
        	'supports'            => array( 'title', 'thumbnail' ),
        	'taxonomies'          => array( 'genres' ),
        	'hierarchical'        => false,
        	'public'              => true,
        	'show_ui'             => true,
        	'show_in_menu'        => true,
        	'show_in_nav_menus'   => true,
        	'show_in_admin_bar'   => true,
        	'menu_position'       => 5,
        	'can_export'          => true,
        	'has_archive'         => true,
        	'exclude_from_search' => false,
        	'publicly_queryable'  => true,
        	'capability_type'     => 'page',
	);
  register_post_type('mobile-coupon', $args);
}
add_action('init', 'cw_register_mobile_coupon_post_type');
// ---------------------------------------


// ----------  Admin Coupon Hooks  ------------
function cw_save_coupon_to_cloud($new_status, $old_status, $post){
  if($_POST['post_type'] == "mobile-coupon" && $new_status == "publish"){
    // Make sure cURL is installed before making an API call
    if(function_exists("curl_version")){
      $api_url = 'http://partner.cpw.bz/';
      $api_key = get_option('api_key');
      $url = $api_url."?API_KEY=".$api_key."&method=update_coupon";
      $q = get_post_meta($_POST['post_ID'], 'q', true);
      //if($q) $url = $url . "&q=" . $q;
      $send_date = strtotime($_POST['expires']);
	$locations = '';
	$content = json_decode(get_option('locations'));
	foreach($content as $key=>$location){
		$locations = $locations . $location->id_location_id . ','; 
	}
	$locations = rtrim($locations, ",");

      // Append all the needed coupon data
      $url = $url . "&product=" . $_POST['product'];
      $url = $url . "&category=" . $_POST['category'];
      $url = $url . "&subcategory=" . $_POST['subcategory'];
      $url = $url . "&type=" . urlencode($_POST['type']);
      $url = $url . "&discount=" . $_POST['discount'];
      $url = $url . "&pos_text=" . urlencode($_POST['pos_text']);
      $url = $url . "&expires=" . $send_date;
      $url = $url . "&accepted_businesses=" . get_option('business_id');//$_POST['accepted_businesses'];
      $url = $url . "&accepted_locations=" . $locations;
      //$url = $url . "&u=1";

      echo $url;
      $ch = curl_init();
      curl_setopt( $ch, CURLOPT_URL, $url );
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      //curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
      $content = json_decode(curl_exec($ch));
      //$response = curl_getinfo( $ch );
      curl_close ( $ch );
      echo "<hr/>";
      $keys = array_keys((array)$content);
      add_post_meta($_POST['post_ID'], 'q', $keys[0], TRUE);
      echo get_post_meta($_POST['post_ID'], 'q', true);
      //var_dump$response);
      echo "<hr/>";
      var_dump($_POST);
      wp_die();
    }
  }
}
add_action("transition_post_status", "cw_save_coupon_to_cloud");

function cw_coupon_meta_init() {
  //This method is the one that actually adds the
  //write panel, named 'Coupon Information' to the
  //post type 'coupons'
  add_meta_box(
    'cw_coupon_meta',
    'Coupon Information',
    'cw_coupon_meta',
    'coupons',
    'advanced',
    'high'
  );
}
add_action('admin_init','cw_coupon_meta_init');


// The function below links the panel
// to the custom fields
// ---------------------------------
function cw_coupon_meta() {
  global $post;

  if($post->post_type == "mobile-coupon") {
    $product = get_post_meta($post->ID,'product',TRUE);
    $category = get_post_meta($post->ID,'category',TRUE);
    $subcategory = get_post_meta($post->ID,'subcategory',TRUE);
    $type = get_post_meta($post->ID,'type',TRUE);
    $discount = get_post_meta($post->ID,'discount',TRUE);
    $pos_text = get_post_meta($post->ID,'pos_text',TRUE);
    $punches_needed = get_post_meta($post->ID,'punches_needed',TRUE);
    $expires = get_post_meta($post->ID,'expires',TRUE);
    $sku = get_post_meta($post->ID,'sku',TRUE);
    $volume = get_post_meta($post->ID,'volume',TRUE);
    $redeem_style = get_post_meta($post->ID,'redeem_style',TRUE);
    $accepted_businesses = get_post_meta($post->ID,'accepted_businesses',TRUE);
    $accepted_locations = get_post_meta($post->ID,'accepted_locations',TRUE);
    $image = get_post_meta($post->ID,'image',TRUE);
    $url = get_post_meta($post->ID,'url',TRUE);
    $upc = get_post_meta($post->ID,'upc',TRUE);

    //Call the write panel HTML
    ?>
  <style>
    .coupon_panel label {
      clear: both;
      float: left;
      width: 200px;
      height: 30px;
    }
    .coupon_panel input {
      float: left;
      width: 200px;
      height: 30px;
    }
    .coupon_panel select {
      float: left;
      width: 200px;
      height: 30px;
    }
  </style>

  <div class="coupon_panel">

  <p>This panel contains information
  for displaying the coupon</p>

  <label>Product</label>
    <select name="product">
	<option value="0">
	Create New Product
	</option>
      <option value="82574"<?php
        if(!empty($product) && $product == 82574){ 
	      echo " selected";
        }
      ?>>Fun & Entertainment</option>
    </select>

	<label>Product Details</label>
		<input type="text" name="product details"/>


  <!-- <label>Category</label> -->
    <input type="hidden" name="category" value="<?php
      if(!empty($category)){ 
	      echo $category;
      } else {
	      echo 5;
      }
    ?>"/>

  <!-- <label>Subcategory</label> -->
    <input type="hidden" name="subcategory" value="<?php
      if(!empty($subcategory)){ 
	      echo $subcategory;
      } else {
	      echo 342;
      }
    ?>"/>

  <!--<label>type</label>+--->
    <input type="hidden" name="type" value="<?php
      if(!empty($type)){ 
	      echo $type;
      } else {
	      echo '?';
      }
    ?>"/>

  <label>Discount</label>
    <input type="text" name="discount" value="<?php
      if(!empty($discount)){ 
	      echo $discount;
      } else {
	      echo '0.00';
      }
    ?>"/>

  <label>Point of Sale text</label>
    <input type="text" name="pos_text" value="<?php
      if(!empty($pos_text)){ 
	      echo $pos_text;
      } else {
	      echo '';
      }
    ?>"/>

  <!--
  <label>Punches Needed</label>
    <input type="text" name="punches_needed" value="<?php
      if(!empty($punches_needed)){ 
	      echo $punches_needed;
      } else {
	      echo '';
      }
    ?>"/>
    -->

  <label>Expiration Date (YYYY-MM-DD)</label>
    <input type="Date" name="expires" value="<?php
      if(!empty($expires)){ 
	      echo $expires;
      } else {
	      echo '2016-12-31';
      }
    ?>"/>

  <!--<label>Sku</label>-->
    <input type="hidden" name="sku" value="<?php
      if(!empty($sku)){ 
	      echo $sku;
      } else {
	      echo '';
      }
    ?>"/>

  <!--<label>Volume</label>-->
    <input type="hidden" name="volume" value="<?php
      if(!empty($volume)){ 
	      echo $volume;
      } else {
	      echo '';
      }
    ?>"/>

  <!--<label>Redeem Style (Normal/No Scan)</label>-->
    <input type="hidden" name="redeem_style" value="<?php
      if(!empty($redeem_style)){ 
	      echo $redeem_style;
      } else {
	      echo 'Normal';
      }
    ?>"/>

  <!--<label>Accepted Businesses</label>-->
    <input type="hidden" name="accepted_businesses" value="<?php
        if(!empty($accepted_businesses)){ 
	        echo $accepted_businesses;
        } else {
	        echo '20169';
        }
      ?>"/>

  <!--<label>Accepted Locations</label>-->
    <input type="hidden" name="accepted_locations" value="<?php
        if(!empty($accepted_locations)){ 
	        echo $accepted_locations;
        } else {
	        echo '22422';
        }
      ?>"/>

  <label>Image</label>
    <input type="text" name="image" value="<?php
        if(!empty($image)){ 
	        echo $image;
        } else {
	        echo '/wp-content/themes/jeepers-theme/images/jeepers.png';
        }
      ?>"/>

  <!--<label>url</label>
    <input type="text" name="url" value="<?php
      if(!empty($url)){ 
	      echo $url;
      } else {
	      echo '';
      }
    ?>"/>-->

  <!--<label>Upc</label>
  <input type="text" name="upc" value="<?php
    if(!empty($upc)){ 
	    echo $upc;
    } else {
	    echo '';
    }
  ?>"/>-->
</div>
	<?php
}

  // create a custom nonce for submit
  // verification later
  echo '';
}
add_action('edit_form_after_title', 'cw_coupon_meta');

//The function below checks the
//authentication via the nonce, and saves
//it to the database.
function cw_meta_save($post_id) {
  // authentication checks
  // make sure data came from our meta box
  //if(!wp_verify_nonce($_POST['my_meta_noncename',__FILE__))
  //  return $post_id;
  //if (!current_user_can('edit_post', $post_id)) {
  //  return $post_id;
  //}
  // The array of accepted fields for Books
  $post_type_id = $_POST['post_type'];
  $accepted_fields[$post_type_id] = array(
    'product',
    'category',
    'subcategory',
    'type',
    'discount',
    'pos_text',
    'punches_needed',
    'expires',
    'sku',
    'volume',
    'redeem_style',
    'accepted_businesses',
    'accepted_locations',
    'image',
    'url',
    'upc',
  );
  //We loop through the list of fields,
  //and save 'em!
  foreach($accepted_fields[$post_type_id] as $key){
    // Set it to a variable, so it's
    // easier to deal with.
    $custom_field = $_POST[$key];

    //If no data is entered
    if(is_null($custom_field)) {

      //delete the field. No point saving it.
      delete_post_meta($post_id, $key);

      // If it is set (there was already data),
      // and the new data isn't empty, update it.
    }
    elseif(isset($custom_field) && !is_null($custom_field))
    {
      // update
     update_post_meta($post_id,$key,$custom_field);
	echo "<script>alert('saving $key = $custom_field');</script>";

      //Just add the data.
    } else {
      // Add?
      add_post_meta($post_id, $key, $custom_field, TRUE);
    }
  }
  return $post_id;
}
add_action('save_post', 'cw_meta_save');

// ------- DISMISSABLE NOTICE FOR cURL NOT INSTALLED --------
function cw_curl_not_installed_notice(){
  global $current_user;
  // Only show this notice if user hasn't already dismissed it
  if ( !get_user_meta( $current_user->ID, 'cw_ignore_curl_not_installed_notice' ) ) {
  ?>
    <div class="update-nag notice">WP Mobile Coupons: Install cURL to utilize the full functionality of this plugin. <a href="?dismiss_curl_not_installed=yes">Dismiss</a>.</div>';
  <?php
  }
}
if( true || !function_exists("curl_version") ) add_action( 'admin_notices', 'cw_curl_not_installed_notice' );

function cw_dismiss_curl_not_installed_notice() {
  global $current_user;
  if ( isset( $_GET['dismiss_curl_not_installed'] ) && $_GET['dismiss_curl_not_installed'] == 'yes' ) {
    add_user_meta( $userid, 'ignore_sample_error_notice', 'yes', true );
  }
}
if( true || !function_exists("curl_version") ) add_action( 'admin_init', 'cw_dismiss_curl_not_installed_notice' );
// -----------------------------------------------

// --------- THEME INTEGRATION ----------
function cw_theme_hook_javascript() {
  $output="<script src=\"https://code.jquery.com/jquery-migrate-1.2.1.js\"></script>\n";
  //$output.="<script src=\"/wp-content/plugins/wp-mobile-coupons-plugin/js/jquery.printElement.js\"></script>\n";
  $output.="<script src=\"https://raw.githubusercontent.com/erikzaadi/jQueryPlugins/master/jQuery.printElement/jquery.printElement.min.js\"></script>\n";
  //echo $output;
}
add_action('wp_head','cw_theme_hook_javascript');

//creates settings submenu for coupons
function cw_add_admin_menu_page(){
	add_submenu_page(
		'edit.php?post_type=mobile-coupon',
		'Coupon Settings',
		'Settings',
		'manage_options',
		'cw_settings',
		'cw_settings_page'
	);	
}
add_action('admin_menu', 'cw_add_admin_menu_page');

// the coupon settings page
function cw_settings_page(){
	$api_key = get_option('api_key');
	$business_id = get_option('business_id');
	if(isset($_GET['settings-updated']) && $_GET['settings-updated'] && $api_key && $business_id){
		cw_get_locations();
	}
	//$api_key = "1fDtlEz81XfjY9W49JvgsJ2mqK";
	?> 
	<div class= "wrap">
	<h2>Coupon Settings</h2>
		<form action="options.php" method="post"><?php
			settings_fields( 'cw_settings' );
			do_settings_sections( 'cw_settings' );
			?>
			<label>API Key</label>
    			<input type="text" name="api_key" value="<?php
      				if(!empty($api_key)){ 
	      				echo $api_key;
      				} else {
	     				 echo '';
      				}
    			?>"/>
			<br/>
			<label>Business ID</label>
    			<input type="text" name="business_id" value="<?php
      				if(!empty($business_id)){ 
	      				echo $business_id;
      				} else {
	     				 echo '';
      				}
    			?>"/>
			<br/>
			<label>Locations Info</label>
			<?php 
			$content = json_decode(get_option('locations'));
			//print_r($content);
			//echo "<hr/>";
			foreach($content as $key=>$location){
				?>
					<div class="location">
						<?php
						echo $location->name . "<br/>";
						echo $location->address . "<br/>";
						echo $location->city . "<br/>";
						echo $location->state;
						?>
					</div> 
				<?php
			}
			submit_button();
			?>
		</form>
	</div>
	<?php
}


function cw_sanitize_api_key($key){return preg_replace('/\s+/', '', $key);}

function cw_sanitize_id($id){return filter_var($id, FILTER_SANITIZE_NUMBER_INT);}

function cw_get_locations(){
	// Make sure cURL is installed before making an API call
    if(function_exists("curl_version")){
      	$api_url = 'http://partner.sandbox.cw.cm/';
      	$api_key = get_option('api_key');
      	$url = $api_url."?API_KEY=".$api_key."&method=get_business_locations";

      	// Append all the needed coupon data
	$url = $url . '&b=' . get_option('business_id');

      	echo $url;
      	$ch = curl_init();
      	curl_setopt( $ch, CURLOPT_URL, $url );
      	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      	//curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	$content = curl_exec($ch);
	
      	update_option('locations', $content);
      	//$response = curl_getinfo( $ch );
      	curl_close ( $ch );
	echo "<br/>";
	var_dump($content);
	echo "<br/>";

	
	//die();
    }
}

?>
