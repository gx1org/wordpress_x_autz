<?php

/**
 * Plugin Name: Autz.org Login
 * Plugin URI:  https://about.autz.org
 * Description: Add "Login with Autz.org" button.
 * Version: 2.0.0
 * Author: PT. Geksa Eksplorasi Satu
 * Author URI:  https://gx1.org
 */

if (!defined('ABSPATH')) {
  exit; // Avoid direct access
}

// Render the button
function autzorg_render_login_button()
{
  $autzorg_app_id = get_option('autzorg_app_id');
  if (empty($autzorg_app_id)) {
    echo '<p style="color: red; text-align: center;">Autz.org Login is not configured. Go to Settings > Autz.org Login.</p>';
    return;
  }
  $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  $autzorg_auth_url = "https://autz.org/onboarding/$autzorg_app_id/?callback_url=$current_url";
  $btn_label = get_option('autzorg_button_label');
  $btn_class = get_option('autzorg_button_class');
  $show_normal_login = get_option('autzorg_normal_login_show');
  $script = 'document.querySelector("form#loginform p.submit").insertAdjacentElement("afterend", document.querySelector("#autzorg-btn"))';
  $margin = 'margin-top:70px;';
  if ($show_normal_login == 'no') {
    $script = 'document.querySelector("p#nav").remove();document.querySelector("form#loginform").innerHTML = "";document.querySelector("form#loginform").appendChild(document.querySelector("#autzorg-btn"))';
    $margin = '0px;';
  }
  echo '<div id="autzorg-btn" style="'.$margin.'text-align:center"><a href="' . esc_url($autzorg_auth_url) . '" class="'.$btn_class.'" style="width:100%">'.$btn_label.'</a></div><script>'.$script.'</script>';
}
add_action('login_footer', 'autzorg_render_login_button');

// Handle callback from autzorg web
function autzorg_handle_callback()
{
  $autzorg_app_id = get_option('autzorg_app_id');
  if (isset($_GET['auth_code'])) {
    $code = sanitize_text_field($_GET['auth_code']);

    // Exchange auth code with user data
    $url = "https://autz.org/api/client/$autzorg_app_id/userinfo?code=$code";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
      wp_die('Failed getting userinfo');
    }

    $user_data = json_decode(wp_remote_retrieve_body($response), true);
    $email = $user_data['user']['email'];
    $name = $user_data['user']['name'];

    $user = get_user_by('email', $email);
    if (!$user) {
      $user_id = wp_create_user($email, wp_generate_password(), $email);
      $user = get_user_by('id', $user_id);
      $user->display_name = $name;
      wp_update_user($user);
    }

    // Login user to WordPress
    wp_set_auth_cookie($user->ID);
    wp_redirect(home_url() . '/wp-admin');
    exit;
  }
}
add_action('init', 'autzorg_handle_callback');

// Add submenu under Settings
function autzorg_add_settings_menu()
{
  add_options_page(
    'Autz.org Settings', // Page title
    'Autz.org Login', // Menu title
    'manage_options', // Capability
    'autzorg-login-settings', // Menu slug
    'autzorg_render_settings_page' // Callback function
  );
}
add_action('admin_menu', 'autzorg_add_settings_menu');

// Render autzorg settings page
function autzorg_render_settings_page()
{
?>
  <div class="wrap">
    <h1>Autz.org Login Settings</h1>
    <form method="post" action="options.php">
      <?php
      settings_fields('autzorg_settings_group'); // Group settings name
      do_settings_sections('autzorg-login-settings'); // Slug settings
      submit_button(); // Save button
      ?>
    </form>
  </div>
<?php
}

function autzorg_register_settings()
{
  register_setting('autzorg_settings_group', 'autzorg_app_id');
  register_setting('autzorg_settings_group', 'autzorg_button_label');
  register_setting('autzorg_settings_group', 'autzorg_button_class');
  register_setting('autzorg_settings_group', 'autzorg_normal_login_show');

  add_settings_section(
    'autzorg_settings_section',
    'Autz.org OAuth Settings',
    null,
    'autzorg-login-settings'
  );

  add_settings_field(
    'autzorg_app_id',
    'Autz.org App ID',
    'autzorg_app_id_callback',
    'autzorg-login-settings',
    'autzorg_settings_section'
  );

  add_settings_field(
    'autzorg_button_label',
    'Button Label',
    'autzorg_button_label_callback',
    'autzorg-login-settings',
    'autzorg_settings_section'
  );

  add_settings_field(
    'autzorg_button_class',
    'Button Classes',
    'autzorg_button_class_callback',
    'autzorg-login-settings',
    'autzorg_settings_section'
  );

  add_settings_field(
    'autzorg_normal_login_show',
    'Show Normal Login?',
    'autzorg_normal_login_show_callback',
    'autzorg-login-settings',
    'autzorg_settings_section'
  );
}
add_action('admin_init', 'autzorg_register_settings');

function autzorg_app_id_callback()
{
  $value = get_option('autzorg_app_id', '');
  echo '<input type="text" name="autzorg_app_id" value="' . esc_attr($value) . '" class="regular-text">';
}

function autzorg_app_secret_callback()
{
  $value = get_option('autzorg_app_secret', '');
  echo '<input type="password" name="autzorg_app_secret" value="' . esc_attr($value) . '" class="regular-text">';
}

function autzorg_button_label_callback()
{
  $value = get_option('autzorg_button_label', 'Login with Autz.org');
  echo '<input type="text" name="autzorg_button_label" value="' . esc_attr($value) . '" class="regular-text">';
}

function autzorg_button_class_callback()
{
  $value = get_option('autzorg_button_class', 'button-secondary');
  echo '<input type="text" name="autzorg_button_class" value="' . esc_attr($value) . '" class="regular-text">';
}

function autzorg_normal_login_show_callback()
{
  $value = get_option('autzorg_normal_login_show', 'yes');
  $selected_yes = esc_attr($value) == 'yes' ? 'selected' : '';
  $selected_no = esc_attr($value) == 'no' ? 'selected' : '';
  echo '<select name="autzorg_normal_login_show" class="">
    <option value="yes" '.$selected_yes.'>Yes</option>
    <option value="no" '.$selected_no.'>No</option>
  </select>';
}

// Add Manage link, next to deactivate plugin link
function autzorg_add_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('options-general.php?page=autzorg-login-settings') . '">Manage</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'autzorg_add_plugin_action_links');
