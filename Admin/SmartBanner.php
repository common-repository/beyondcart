<?php

namespace BCAPP\Admin;

if (!defined('BCAPP_plugin_dir')) exit; // Exit if accessed directly

class SmartBanner
{

    public static function init()
    {
        // SmartBanner - removed since 1.7.2
        // add_action( 'wp_enqueue_scripts', [self::class, 'enqueSmartBannerStyleAndScripts'] );
        // add_action( 'wp_head', [self::class, 'addBannerWithLinksToAppStoresOnWebiste'] ); // Inject SmartBanner on mobile
        
        // Custom banner + Safari native
        add_action( 'wp_enqueue_scripts', [self::class, 'enqueOurCustomBannerStyleAndScripts'] );
        add_action( 'wp_head', [self::class, 'addSafariNativeBanner'] );
        add_action( 'wp_footer', [self::class, 'addOurCustomBanner'] ); // Inject custom made banner on desktop

    }

    public static function addSmartBanner() {
        
        if(get_transient('beyondcart_settings_banner')) {
            $settings = get_transient('beyondcart_settings_banner');
        } else {
            $settings['banner_app_active'] = get_option('grind_mobile_app_banner_app_active', null);
            $settings['banner_app_logo'] = get_option('grind_mobile_app_banner_app_logo', null);
            $settings['banner_app_url_apple'] = get_option('grind_mobile_app_banner_app_url_apple', null);
            $settings['banner_app_url_google'] = get_option('grind_mobile_app_banner_app_url_google', null);
            $settings['banner_app_title'] = get_option('grind_mobile_app_banner_app_title', null);
            $settings['banner_app_desc'] = get_option('grind_mobile_app_banner_app_desc', null);
            $settings['banner_app_button'] = get_option('grind_mobile_app_banner_app_button', null);
            $settings['banner_app_hide_on_desktop'] = get_option('grind_mobile_app_banner_app_button', null);
        }
        
        if( $settings['banner_app_active']) {

            if ( !wp_is_mobile() && isset($settings['banner_app_hide_on_desktop']) && $settings['banner_app_hide_on_desktop'] ) {
                return;
            }

            echo '<meta name="smartbanner:disable-positioning" content="true">' . "\n";
            echo '<meta name="smartbanner:title" content="'. esc_attr($settings['banner_app_title']) . '">' . "\n";
            echo '<meta name="smartbanner:author" content="'. esc_attr($settings['banner_app_desc']) . '">' . "\n";
            echo '<meta name="smartbanner:price" content=" ">' . "\n";
            echo '<meta name="smartbanner:price-suffix-apple" content=" ">' . "\n";
            echo '<meta name="smartbanner:price-suffix-google" content=" ">' . "\n";
            echo '<meta name="smartbanner:icon-apple" content="'. esc_url($settings['banner_app_logo']) . '">' . "\n";
            echo '<meta name="smartbanner:icon-google" content="'. esc_url($settings['banner_app_logo']) . '">' . "\n";
            echo '<meta name="smartbanner:button" content="'. esc_attr($settings['banner_app_button']) . '">' . "\n";
            echo '<meta name="smartbanner:button-url-apple" content="'. esc_url($settings['banner_app_url_apple']) . '">' . "\n";
            echo '<meta name="smartbanner:button-url-google" content="'. esc_url($settings['banner_app_url_google']) . '">' . "\n";
            echo '<meta name="smartbanner:enabled-platforms" content="android,ios">' . "\n";
            echo '<meta name="smartbanner:exclude-user-agent-regex" content="^.*(Version).*Safari">' . "\n";
            
            set_transient('beyondcart_settings_banner', $settings, 600);
        }
    }
    
     public static function addSafariNativeBanner() {
        
        $settings['banner_app_active'] = get_option('grind_mobile_app_banner_app_active', null);
        $settings['banner_app_logo'] = get_option('grind_mobile_app_banner_app_logo', null);
        $settings['banner_app_url_apple'] = get_option('grind_mobile_app_banner_app_url_apple', null);
        $settings['banner_app_url_google'] = get_option('grind_mobile_app_banner_app_url_google', null);
        $settings['banner_app_title'] = get_option('grind_mobile_app_banner_app_title', null);
        $settings['banner_app_desc'] = get_option('grind_mobile_app_banner_app_desc', null);
        $settings['banner_app_button'] = get_option('grind_mobile_app_banner_app_button', null);
        $settings['banner_app_hide_on_desktop'] = get_option('grind_mobile_app_banner_app_button', null);
    
        if( $settings['banner_app_active']) {
            $app_store_url = esc_url($settings['banner_app_url_apple']);
            if (preg_match('/\d+$/', $app_store_url, $matches)) {
                $app_store_id = $matches[0]; // Get App store id: 161336000 from the url
            }
            if($app_store_id){
                echo '<meta name="apple-itunes-app" content="app-id='. $app_store_id .'">' . "\n";
            }
        }

    }
    
    public static function enqueSmartBannerStyleAndScripts() {
        wp_enqueue_style( 'grind-mobile-app-smartbanner-css', BCAPP_plugin_dir_url. '/Public/smartbanner/smartbanner.min.css' );
        wp_enqueue_script( 'grind-mobile-app-smartbanner-js', BCAPP_plugin_dir_url. '/Public/smartbanner/smartbanner.min.js' );
    }
    
    public static function enqueOurCustomBannerStyleAndScripts() {
        wp_enqueue_style( 'grind-mobile-app-smartbanner-css', BCAPP_plugin_dir_url. '/Public/smartbanner/appdesktopbanner.css' );
        wp_enqueue_script( 'grind-mobile-app-smartbanner-js', BCAPP_plugin_dir_url. '/Public/smartbanner/appdesktopbanner.js' );
    }
    
    
    public static function addOurCustomBanner() {
        
        if(self::isSafari()) {
            return;
        }

        if(is_checkout()) {
            return;
        }
        
        $settings['banner_app_active'] = get_option('grind_mobile_app_banner_app_active', null);
        $settings['banner_app_logo'] = get_option('grind_mobile_app_banner_app_logo', null);
        $settings['banner_app_url_apple'] = get_option('grind_mobile_app_banner_app_url_apple', null);
        $settings['banner_app_url_google'] = get_option('grind_mobile_app_banner_app_url_google', null);
        $settings['banner_app_title'] = get_option('grind_mobile_app_banner_app_title', null);
        $settings['banner_app_desc'] = get_option('grind_mobile_app_banner_app_desc', null);
        $settings['banner_app_button'] = get_option('grind_mobile_app_banner_app_button', null);
        $settings['banner_app_hide_on_desktop'] = get_option('grind_mobile_app_banner_app_hide_desktop', null);
        
        if( $settings['banner_app_active']) {

            $close_icon_url = BCAPP_plugin_dir_url. '/Public/smartbanner/close.svg';
            $google_play_url = BCAPP_plugin_dir_url. '/Public/smartbanner/google-play.svg';
            $apple_store_url = BCAPP_plugin_dir_url. '/Public/smartbanner/apple-store.svg';
            
            if ( !wp_is_mobile() && isset($settings['banner_app_hide_on_desktop']) && $settings['banner_app_hide_on_desktop'] ) {
                return;
            }
            
            echo '<div class="beyondcart popup-app popup-desktop" style="display:none">';
            echo '    <div class="popup-app__wrapper flex">';
            echo '        <div class="flex">';
            echo '            <div class="popup-app__logo-container">';
            echo '                <img src="' . esc_url($settings['banner_app_logo']) . '" class="lazyloaded">';
            echo '            </div>';
            echo '            <p><span class="popup-app__text">'. esc_attr($settings['banner_app_title']) .'</span><span class="popup-app__text-break">' . esc_attr($settings['banner_app_desc']) . '</span></p>';
            echo '        </div>';
            echo '        <div class="flex">';
            echo '            <div class="popup-app__stores">';
            echo '                <div><a href="' . esc_url($settings['banner_app_url_apple']) .'"><img src="' . esc_url($apple_store_url) . '" alt="Download on the App Store" class="lazyloaded"></a></div>';
            echo '                <div><a href="' . esc_url($settings['banner_app_url_google']) . '"><img src="' . esc_url($google_play_url) . '" alt="Get it on Google Play" class="lazyloaded"></a></div>';
            echo '            </div>';
            echo '            <div class="popup-app__close"><img src="' . esc_url($close_icon_url) . '" alt="Close" class="lazyloaded"></div>';
            echo '        </div>';
            echo '    </div>';
            echo '</div>';
        }
        
    }
    
    public static function isSafari () {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        // Check for Safari: Safari user agent contains 'Safari' and does not contain 'Chrome' or 'Chromium'
        // since Chrome and Chromium-based browsers include 'Safari' in their user agent strings.
        $isSafari = preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent) && !preg_match('/Chromium/i', $userAgent);
        return $isSafari;
    }
    
}
