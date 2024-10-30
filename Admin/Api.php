<?php
namespace BCAPP\Admin;

use WP_REST_Response;
use WP_REST_Server;

class Api
{
    public static function register()
    {

        register_rest_route(BCAPP_api_namespace, 'template-mobile', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(self::class, 'get_template_config'),
                'permission_callback' => '__return_true',
            ],
        ]);
        register_rest_route(BCAPP_api_namespace, 'template-mobile', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(self::class, 'update_template_config'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'api_key' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'The client\'s api_key',
                    ),
                )
            ],
        ]);

        register_rest_route(BCAPP_api_namespace, 'configs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(self::class, 'get_configs'),
                'permission_callback' => '__return_true',
            ],
        ]);

    }

    public static function get_configs($request)
    {
        $configs = get_option('grind_mobile_app_configs', [
            "requireLogin" => false,
            "toggleSidebar" => false,
            "isBeforeNewProduct" => 5,
        ]);

        return new WP_REST_Response(maybe_unserialize($configs), 200);
    }

    public static function template_configs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . BCAPP_plugin_table_name;

        return $wpdb->get_results("SELECT * FROM $table_name", OBJECT);
    }

    /**
     * @param $request
     *
     * @return WP_REST_Response
     * @since    1.0.0
     */
    public static function get_template_config($request)
    {
        return new WP_REST_Response(self::template_configs(), 200);
    }

    /**
     * @param $request
     *
     * @return WP_REST_Response
     * @since    1.0.0
     */
    public static function update_template_config($request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . BCAPP_plugin_table_name;

        $data = $request->get_param('data');
        $settings = $request->get_param('settings');
        $api_key = $request->get_param('api_key');
        $configs = $request->get_param('configs');

        // ако ключа, който е подаден отговаря на ключа на проекта - само тогава да се презапишат данните
        if (get_option('grind_mobile_app_api_key') === $api_key) {
            $sql = "INSERT INTO {$table_name} ( data, settings, date_updated, date_created, id, name, status)
            VALUES (%s,%s, now(), now() , 1, 'Main', 1 )
            ON DUPLICATE KEY UPDATE data = VALUES(data), settings = VALUES(settings), date_updated = now() ";
            $sql = $wpdb->prepare($sql, $data, $settings);
            $results = $wpdb->query($sql);
            
            update_option('grind_mobile_app_configs', json_decode($configs));

            return new WP_REST_Response($results, 200);
        }
        
        return new WP_REST_Response('wrong api_key', 400);
        
    }
}
