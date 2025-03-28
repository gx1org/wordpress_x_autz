<?php

/**
 * Plugin Name: Kunber Login
 * Plugin URI:  https://kunber.gx1.org
 * Description: Add "Login with Kunber" button.
 * Version: 2.0.0
 * Author: PT. Geksa Eksplorasi Satu
 * Author URI:  https://gx1.org
 */

if (!defined('ABSPATH')) {
  exit; // Avoid direct access
}

// Render the button
function kunber_render_login_button()
{
  $kunber_app_id = get_option('kunber_app_id');
  if (empty($kunber_app_id)) {
    echo '<p style="color: red; text-align: center;">Kunber Login is not configured. Go to Settings > Kunber Login.</p>';
    return;
  }
  $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  $kunber_auth_url = "https://kunber.zone.id/onboarding/$kunber_app_id/?callback_url=$current_url";
  $btn_label = get_option('kunber_button_label');
  $btn_class = get_option('kunber_button_class');
  $show_normal_login = get_option('kunber_normal_login_show');
  $script = 'document.querySelector("form#loginform p.submit").insertAdjacentElement("afterend", document.querySelector("#kunber-btn"))';
  $margin = 'margin-top:70px;';
  if ($show_normal_login == 'no') {
    $script = 'document.querySelector("p#nav").remove();document.querySelector("form#loginform").innerHTML = "";document.querySelector("form#loginform").appendChild(document.querySelector("#kunber-btn"))';
    $margin = '0px;';
  }
  echo '<div id="kunber-btn" style="'.$margin.'text-align:center"><a href="' . esc_url($kunber_auth_url) . '" class="'.$btn_class.'" style="width:100%">'.$btn_label.'</a></div><script>'.$script.'</script>';
}
add_action('login_footer', 'kunber_render_login_button');

// Handle callback from kunber web
function kunber_handle_callback()
{
  $kunber_app_id = get_option('kunber_app_id');
  $kunber_app_secret = get_option('kunber_app_secret');
  if (isset($_GET['auth_code'])) {
    $code = sanitize_text_field($_GET['auth_code']);

    // Exchange auth code with user data
    $url = "https://kunber.zone.id/api/client/$kunber_app_id/exchange";
    $response = wp_remote_post($url, [
      'body' => wp_json_encode([
        'code' => $code,
      ]),
      'headers' => [
        'Authorization' => $kunber_app_secret,
        'Content-Type' => 'application/json',
      ]
    ]);

    if (is_wp_error($response)) {
      wp_die('Failed exchange code');
    }

    $user_data = json_decode(wp_remote_retrieve_body($response), true);
    $email = $user_data['data']['email'];
    $name = $user_data['data']['name'];

    $user = get_user_by('email', $email);
    if (!$user) {
      $user_id = wp_create_user($name, wp_generate_password(), $email);
      $user = get_user_by('id', $user_id);
    }

    // Login user to WordPress
    wp_set_auth_cookie($user->ID);
    wp_redirect(home_url() . '/wp-admin');
    exit;
  }
}
add_action('init', 'kunber_handle_callback');

// Add submenu under Settings
function kunber_add_settings_menu()
{
  add_options_page(
    'Kunber Login Settings', // Page title
    'Kunber Login', // Menu title
    'manage_options', // Capability
    'kunber-login-settings', // Menu slug
    'kunber_render_settings_page' // Callback function
  );
}
add_action('admin_menu', 'kunber_add_settings_menu');

// Render kunber settings page
function kunber_render_settings_page()
{
?>
  <div class="wrap">
    <h1>Kunber Login Settings</h1>
    <form method="post" action="options.php">
      <?php
      settings_fields('kunber_settings_group'); // Group settings name
      do_settings_sections('kunber-login-settings'); // Slug settings
      submit_button(); // Save button
      ?>
    </form>
  </div>
<?php
}

function kunber_register_settings()
{
  register_setting('kunber_settings_group', 'kunber_app_id');
  register_setting('kunber_settings_group', 'kunber_app_secret');
  register_setting('kunber_settings_group', 'kunber_button_label');
  register_setting('kunber_settings_group', 'kunber_button_class');
  register_setting('kunber_settings_group', 'kunber_normal_login_show');

  add_settings_section(
    'kunber_settings_section',
    'Kunber OAuth Settings',
    null,
    'kunber-login-settings'
  );

  add_settings_field(
    'kunber_app_id',
    'Kunber App ID',
    'kunber_app_id_callback',
    'kunber-login-settings',
    'kunber_settings_section'
  );

  add_settings_field(
    'kunber_app_secret',
    'Kunber App Secret',
    'kunber_app_secret_callback',
    'kunber-login-settings',
    'kunber_settings_section'
  );

  add_settings_field(
    'kunber_button_label',
    'Button Label',
    'kunber_button_label_callback',
    'kunber-login-settings',
    'kunber_settings_section'
  );

  add_settings_field(
    'kunber_button_class',
    'Button Classes',
    'kunber_button_class_callback',
    'kunber-login-settings',
    'kunber_settings_section'
  );

  add_settings_field(
    'kunber_normal_login_show',
    'Show Normal Login?',
    'kunber_normal_login_show_callback',
    'kunber-login-settings',
    'kunber_settings_section'
  );
}
add_action('admin_init', 'kunber_register_settings');

function kunber_app_id_callback()
{
  $value = get_option('kunber_app_id', '');
  echo '<input type="text" name="kunber_app_id" value="' . esc_attr($value) . '" class="regular-text">';
}

function kunber_app_secret_callback()
{
  $value = get_option('kunber_app_secret', '');
  echo '<input type="password" name="kunber_app_secret" value="' . esc_attr($value) . '" class="regular-text">';
}

function kunber_button_label_callback()
{
  $value = get_option('kunber_button_label', 'Login with Kunber');
  echo '<input type="text" name="kunber_button_label" value="' . esc_attr($value) . '" class="regular-text">';
}

function kunber_button_class_callback()
{
  $value = get_option('kunber_button_class', 'button-secondary');
  echo '<input type="text" name="kunber_button_class" value="' . esc_attr($value) . '" class="regular-text">';
}

function kunber_normal_login_show_callback()
{
  $value = get_option('kunber_normal_login_show', 'yes');
  $selected_yes = esc_attr($value) == 'yes' ? 'selected' : '';
  $selected_no = esc_attr($value) == 'no' ? 'selected' : '';
  echo '<select name="kunber_normal_login_show" class="">
    <option value="yes" '.$selected_yes.'>Yes</option>
    <option value="no" '.$selected_no.'>No</option>
  </select>';
}

// Add Manage link, next to deactivate plugin link
function kunber_add_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('options-general.php?page=kunber-login-settings') . '">Manage</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kunber_add_plugin_action_links');
