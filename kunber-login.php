<?php
/**
 * Plugin Name: Kunber Login
 * Description: Add "Login with Kunber" button.
 * Version: 1.0
 * Author: <a href="https://gx1.org" target="_blank">PT. Geksa Eksplorasi Satu</a>
 */

if (!defined('ABSPATH')) {
    exit; // Menghindari akses langsung
}

const KUNBER_APP_ID = "2iptmiq";
const KUNBER_APP_SECRET = "Tj4aNjlYdcDea3OZ6KrACKOrRXqAJZRU";

// Tambah tombol di halaman login WordPress
function kunber_login_button() {
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $kunber_auth_url = 'https://kunber.zone.id/onboarding/'.KUNBER_APP_ID.'/?callback_url='.$current_url;
    echo '<div id="kunber-btn" style="margin-top:70px;text-align:center"><a href="' . esc_url($kunber_auth_url) . '" class="button-secondary" style="width:100%">Login with Kunber &nearr;</a></div><script>document.querySelector("form#loginform p.submit").insertAdjacentElement("afterend", document.querySelector("#kunber-btn"))</script>';
}
add_action('login_footer', 'kunber_login_button');

// Proses callback setelah pengguna login dengan Kunber
function kunber_handle_callback() {
    if (isset($_GET['auth_code'])) {
        $code = sanitize_text_field($_GET['auth_code']);
        
        // Tukar kode dengan data user
        $response = wp_remote_post('https://kunber.zone.id/api/client/'.KUNBER_APP_ID.'/exchange', [
            'body' => wp_json_encode([
                'code' => $code,
            ]),
            'headers' => [
                'Authorization' => KUNBER_APP_SECRET,
                'Content-Type' => 'application/json',
            ]
        ]);

        if (is_wp_error($response)) {
            wp_die('Gagal mendapatkan token');
        }

        $user_data = json_decode(wp_remote_retrieve_body($response), true);
        $email = $user_data['data']['email'];
        $name = $user_data['data']['name'];

        $user = get_user_by('email', $email);
        if (!$user) {
            $user_id = wp_create_user($name, wp_generate_password(), $email);
            $user = get_user_by('id', $user_id);
        }

        // Login user ke WordPress
        wp_set_auth_cookie($user->ID);
        wp_redirect(home_url().'/wp-admin');
        exit;
    }
}
add_action('init', 'kunber_handle_callback');
