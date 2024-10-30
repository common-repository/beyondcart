<?php

namespace BCAPP\Admin;

if (!defined('BCAPP_plugin_dir')) exit; // Exit if accessed directly

class Nav
{

    public static function add_links()
    {
        add_filter("plugin_action_links_" . BCAPP_plugin_basename, [self::class, 'settings_link']);
        add_action('admin_menu', [self::class, 'add_menu']);
    }

    public static function add_menu(): void
    {
        $hookname = add_menu_page(
            'BeyondCart',
            'BeyondCart',
            'manage_options',
            'grind_mobile_app',
            [Pages::class, 'get']
        );
        if ('POST' === $_SERVER['REQUEST_METHOD']
            && (isset($_GET['page']) && $_GET['page'] == 'grind_mobile_app' )
            ) {
            add_action('load-' . $hookname, [\BCAPP\Admin\Pages::class, 'post']);
        }
    }

    public static function settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=grind_mobile_app&tab=settings">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
