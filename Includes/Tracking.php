<?php
namespace BCAPP\Includes;

defined('ABSPATH') or die('This page may not be accessed directly.');

class Tracking
{
    const version = '1.1.2';
    
    public static function init()
    {
        // ако не е сетнато site_id не ползвай уебтракинга
        $site_id = get_option('grind_mobile_app_site_id', null);
        if(!$site_id) {
            return;
        }

        $expiration = time() + 31536000;//3600*24*365 = 1 година
        
        // ако е маркирано за изключване
        if(isset($_GET['beyondcart']) && $_GET['beyondcart'] == 'off') {
            setcookie( 'beyondcart_off', 1, $expiration, '/', '', true);
        }
        else if(isset($_GET['beyondcart']) && $_GET['beyondcart'] == 'on') {
            $expired = time() - 1;
            setcookie( 'beyondcart_off', 0, $expired, '/', '', true);
        }
        if ( isset( $_COOKIE['beyondcart_off'] ) ) {
            $cookie_value = sanitize_text_field( wp_unslash( $_COOKIE['beyondcart_off'] ) );
            if($cookie_value === '1') {
                return;
            }
        }
        
        // не е маркирано за изключване
        
        if($site_id) {
            $user_id = get_current_user_id();
            $tc = isset($_GET['tc']) ? $_GET['tc'] : null;
            
            // site_id
            setcookie( 'beyondcart_site_id', $site_id, $expiration, '/', '', true);
            
            // user_id
            setcookie( 'beyondcart_user_id', $user_id > 0 ? $user_id : null, $expiration, '/', '', true);
            
            // tc
            if($tc) {
                $tcExpireTime = isset($_GET['tcExpireTime']) ? $_GET['tcExpireTime'] : 172800; // 3600 секунди * 48 часа 
                setcookie( 'beyondcart_tc', $tc, time() + $tcExpireTime, '/', '', true);
            }
        }
        
        add_action('wp_head', array(__CLASS__, 'addJavascript'), 10);
    
        add_action('wp_footer', [__CLASS__, 'add_to_cart_event']);
        
    }

    public static function add_to_cart_event() {
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            <?php
            if( isset($_POST['add-to-cart']) && isset($_POST['quantity']) ) {
                echo 'beyondcart.push({
                    event: "add_to_cart",
                    product_id: "'. $_POST['add-to-cart'] .'",
                    quantity: "'. $_POST['quantity'] .'"
                    });';
                    
            }
            ?>
            
            $('body').on('added_to_cart', function(e, fragments, cart_hash, $button) {
                var product_id = $button.data('product_id');
                beyondcart.push({
                    event: "add_to_cart",
                    product_id: product_id,
                    quantity: "1"
                });
            });
            
        });
        </script>
        <?php
    }

    public static function addJavascript()
    {
        global $post;
        
        $user_id = get_current_user_id();
        $site_id = get_option('grind_mobile_app_site_id', null);
        $onesignal_api_id = get_option('grind_mobile_app_onesignal_app_id', null);
        $onesignal_safari_web_id = get_option('grind_mobile_app_onesignal_safari_web_id', null);
        
        $tc = isset($_GET['tc']) ? $_GET['tc'] : null;

        if ($onesignal_api_id) {
            ?>
            <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js"></script>
            <script>
                var OneSignal = window.OneSignal || [];
                var initConfig = {
                    appId: "<?php echo $onesignal_api_id; ?>",
                    <?php
                        echo 'safari_web_id: "' . $onesignal_safari_web_id . '",';
                    ?>
                    notifyButton: { // https://documentation.onesignal.com/docs/bell-prompt
                        enable: true,
                        displayPredicate: function () {
                          return OneSignal.isPushNotificationsEnabled()
                            .then(function (isPushEnabled) {
                              /* The user is subscribed, so we want to return "false" to hide the Subscription Bell */
                              return !isPushEnabled;
                            });
                        },
                    },

                };
                OneSignal.push(function () {
                    OneSignal.SERVICE_WORKER_UPDATER_PATH = "wp-content/plugins/beyondcart/sdk_files/OneSignalSDKUpdaterWorker.js";
                    OneSignal.SERVICE_WORKER_PATH = "wp-content/plugins/beyondcart/sdk_files/OneSignalSDKWorker.js";
                    OneSignal.SERVICE_WORKER_PARAM = { scope: "/wp-content/plugins/beyondcart/sdk_files/" };
                    OneSignal.init(initConfig);
                    OneSignal.sendTag("status", "<?php echo ($user_id != 0) ? "registered" : "guest" ?>");
                    OneSignal.sendTag("source", "web");
                });

            <?php
            if ($user_id != 0) {
                ?>
                OneSignal.push(function() {
                    OneSignal.isPushNotificationsEnabled(function(isEnabled) {
                        if (isEnabled) {
                            let externalUserId = "<?php echo $user_id; ?>";
                            OneSignal.setExternalUserId(externalUserId);
                        }
                    });
                });
                <?php
            }
            ?>
            </script>
            <?php 
        }
        
        if($site_id) {
            
            ?>
            <script src="/wp-content/plugins/beyondcart/sdk_files/beyondcart-tracking.js?v=<?php echo self::version; ?>"></script>
            <script>
            <?php
                
            // pageview
            $event = [];
            
            $event['event'] = 'pageview';
            $event['uri'] = '';
            
            if(is_shop() || is_front_page()) {
                // начална на магазина
                $event['uri'] = 'home';
                
            } elseif (is_product()) {
                // продукт
                $event['uri'] = 'product';
                $event['product_id'] = $post->ID;
                
            } elseif (is_product_category()) {
                // категория
                // uri
                $event['uri'] = 'category';
                
                // category_id
                $category = get_queried_object(); // Get the queried object
                if ($category instanceof \WP_Term && isset($category->taxonomy) && $category->taxonomy === 'product_cat') {
                    $event['category_id'] = $category->term_id; 
                }
                
                // product_ids
                $event['products'] = [];
                while (have_posts()) {
                  the_post();
                  $event['products'][] = get_the_ID();
                }
                
                // params
                $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
                $order = wc_get_loop_prop('order'); 
                $params = [];
                $params['sortBy'] = ['orderBy' => $order];
                $params['page'] = $paged;
                $event['params'] = $params;
                
                
            } elseif (is_search()) {
                // търсене
                $event['uri'] = 'search';
                
                $event['params'] = ['s' => get_search_query()];
                
            } elseif (is_cart()) {
                // кошница
                
                
            } elseif (is_checkout()) {
                
                $params = [];
                
                if(is_wc_endpoint_url('order-received')) {
                    // thak-you page
                
                    $order_id = absint( get_query_var( 'order-received' ) ); // Get the order ID from the URL
                    $order = wc_get_order( $order_id ); // Get the order object
                    
                    if ( $order ) {
                        
                        $event['uri'] = 'thank-you';
                        $params['order_id'] = $order_id;
                        $params['total'] = $order->get_total();
                        
                        $order_items = $order->get_items();
                        $items = [];
                    
                        foreach ($order_items as $item ) {
                            $items[] = [
                                'id' => $item->get_id(),
                                'variant' => $item->get_variation_id(),
                                'quantity' => $item->get_quantity(),
                                'price' => $item->get_total(),
                                ];
                        }
                        $params['items'] = $items;
                    }

                }
                else {
                    // чекаут
                    
                    $event['uri'] = 'checkout';
                    
                    $cart = WC()->cart;
                    
                    $params['total'] = $cart->get_total('raw');
                    $params['items'] = [];
                    
                    // Get the cart items
                    $items = $cart->get_cart();
                    
                    foreach ($items as $item) {
                        $params['items'][] = [
                            'id' => $item['data']->get_id(), 
                            'quantity' => $item['quantity']
                        ];
                    }
                }
                
                $event['params'] = $params;
                
            } elseif ($post->post_type === 'page') {
                //page 
                
            }
            if($event['uri'] != '') {
            ?>
                beyondcart.push(
                    <?php echo json_encode($event, JSON_UNESCAPED_UNICODE); ?>
                    );
            <?php
            }
            
            if ($tc) {
                ?>
                beyondcart.push({"event": "tc"});
                <?php
            }
            ?>
            </script>
        <?php
        
        }
        
    }
}