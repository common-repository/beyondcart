<?php
namespace BCAPP\Admin;

class Pages
{
    public static function get()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        $default_tab = null;
        $settings = [];
    
        $settings['site_id'] = esc_attr(get_option('grind_mobile_app_site_id', null));
        $settings['api_key'] = esc_attr(get_option('grind_mobile_app_api_key', null));
        $settings['woo_consumer_api_key'] = esc_attr(get_option('grind_mobile_app_woo_consumer_api_key', null));
        $settings['woo_consumer_api_secret'] = esc_attr(get_option('grind_mobile_app_woo_consumer_api_secret', null));
        $settings['onesignal_app_id'] = esc_attr(get_option('grind_mobile_app_onesignal_app_id', null));
        $settings['onesignal_api_key'] = esc_attr(get_option('grind_mobile_app_onesignal_api_key', null));
        $settings['firebase_server_key'] = esc_attr(get_option('grind_mobile_app_firebase_server_key', null));
        $settings['facebook_app_id'] = esc_attr(get_option('grind_mobile_app_facebook_app_id', null));
        $settings['facebook_app_secret'] = esc_attr(get_option('grind_mobile_app_facebook_app_secret', null));
    
        $settings['banner_app_active'] = esc_attr(get_option('grind_mobile_app_banner_app_active', null));
        $settings['banner_app_hide_desktop'] = esc_attr(get_option('grind_mobile_app_banner_app_hide_desktop', null));
        $settings['banner_app_logo'] = esc_url(get_option('grind_mobile_app_banner_app_logo', null));
        $settings['banner_app_url_apple'] = esc_url(get_option('grind_mobile_app_banner_app_url_apple', null));
        $settings['banner_app_url_google'] = esc_url(get_option('grind_mobile_app_banner_app_url_google', null));
        $settings['banner_app_title'] = esc_html(get_option('grind_mobile_app_banner_app_title', null));
        $settings['banner_app_desc'] = esc_html(get_option('grind_mobile_app_banner_app_desc', null));
        $settings['banner_app_button'] = esc_html(get_option('grind_mobile_app_banner_app_button', null));
    
        include_once __DIR__ . '/pages/Settings.php';
    }

    public static function post()
    {
        if (isset($_POST['api_key'])) {
            // Update the settings
            update_option('grind_mobile_app_site_id', sanitize_text_field($_POST['site_id']));
            update_option('grind_mobile_app_api_key', sanitize_text_field($_POST['api_key']));
            update_option('grind_mobile_app_woo_consumer_api_key', sanitize_text_field($_POST['woo_consumer_api_key']));
            update_option('grind_mobile_app_woo_consumer_api_secret', sanitize_text_field($_POST['woo_consumer_api_secret']));
            update_option('grind_mobile_app_onesignal_app_id', sanitize_text_field($_POST['onesignal_app_id']));
            update_option('grind_mobile_app_onesignal_api_key', sanitize_text_field($_POST['onesignal_api_key']));
            update_option('grind_mobile_app_facebook_app_id', sanitize_text_field($_POST['facebook_app_id']));
            update_option('grind_mobile_app_facebook_app_secret', sanitize_text_field($_POST['facebook_app_secret']));
    
            update_option('grind_mobile_app_banner_app_active', isset($_POST['banner_app_active']) ? '1' : '0');
            update_option('grind_mobile_app_banner_app_hide_desktop', isset($_POST['banner_app_hide_desktop']) ? '1' : '0');
            update_option('grind_mobile_app_banner_app_logo', esc_url_raw($_POST['banner_app_logo']));
            update_option('grind_mobile_app_banner_app_url_apple', esc_url_raw($_POST['banner_app_url_apple']));
            update_option('grind_mobile_app_banner_app_url_google', esc_url_raw($_POST['banner_app_url_google']));
            update_option('grind_mobile_app_banner_app_title', sanitize_text_field($_POST['banner_app_title']));
            update_option('grind_mobile_app_banner_app_desc', sanitize_text_field($_POST['banner_app_desc']));
            
            // Alert message
            add_action('admin_notices', function () {echo '<div class="update notice"><p>Настройките са обновени</p></div>';});
            return;
        }
    }
}
