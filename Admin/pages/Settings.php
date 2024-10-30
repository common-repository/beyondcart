<div class="wrap">
      <style>
        .nav-tab-active {
          background-color: #fff;
        }

        .files-table td {
          text-align: center;
        }

        .files-table tr td:first-child {
          text-align: left;
        }
      </style>
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <nav class="nav-tab-wrapper">
        <a class="nav-tab nav-tab-active"><?php echo __('Settings', 'grind-mobile-app'); ?></a>
      </nav>
      <div class="tab-content" style="background-color: #fff; padding: 10px 20px; border: solid 1px #ccc; border-top: none">
      <form action="<?php menu_page_url('grind_mobile_app')?>" method="post">
        <table class="form-table">
            <tr>
                <td><label for="api_key">Beyondcart Api Key (required)</label></td>
                <td>
                    <input type="text" name="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" id="api_key" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="api_key">Woocommerce API Consumer Key (required)</label></td>
                <td>
                    <input type="text" name="woo_consumer_api_key" value="<?php echo esc_attr($settings['woo_consumer_api_key']); ?>" id="woo_consumer_api_key" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="api_key">Woocommerce API Consumer Secret (required)</label></td>
                <td>
                    <input type="text" name="woo_consumer_api_secret" value="<?php echo esc_attr($settings['woo_consumer_api_secret']); ?>" id="woo_consumer_api_secret" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="api_key">Site id (used for Webtracking)</label></td>
                <td>
                    <input type="text" name="site_id" value="<?php echo esc_attr($settings['site_id']); ?>" id="site_id" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="api_key">Onesignal app_id (used for Webtracking)</label></td>
                <td>
                    <input type="text" name="onesignal_app_id" value="<?php echo esc_attr($settings['onesignal_app_id']); ?>" id="onesignal_app_id" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="api_key">Onesignal safari_web_id (used for Webtracking)</label></td>
                <td>
                    <input type="text" name="onesignal_api_key" value="<?php echo esc_attr($settings['onesignal_api_key']); ?>" id="onesignal_api_key" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="facebook_app_id">Facebook App Id (deprecated - use BC Settings)</label></td>
                <td>
                    <input type="text" name="facebook_app_id" value="<?php echo esc_attr($settings['facebook_app_id']); ?>" id="facebook_app_id" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="facebook_app_secret">Facebook (deprecated - use BC Settings)</label></td>
                <td>
                    <input type="text" name="facebook_app_secret" value="<?php echo esc_attr($settings['facebook_app_secret']); ?>" id="facebook_app_secret" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="facebook_app_secret">Cron Cached Terms Last Update:</label></td>
                <td>
                    <?php 
                        global $wpdb;
                        $result = $wpdb->get_var("SELECT date_updated FROM {$wpdb->prefix}grind_mobile_app_terms ORDER BY date_updated DESC LIMIT 1");
                        if(empty($result)) {
                            $result = $wpdb->get_var("SELECT date_created FROM {$wpdb->prefix}grind_mobile_app_terms ORDER BY date_created DESC LIMIT 1");
                        }
                        echo esc_html( $result );
                    ?>
                    <a href="/n32vdk48yr2hfjj45kq23rdfkjs3f25q3" target="_blank">Re-create Terms Cache</a>
                </td>
            </tr>
            <tr>
                <td><h3>Mobile App Banner Settings</h3></td>
            </tr>
           <tr>
                <td><label for="banner_app_active">Activate App Banner</label></td>
                <td>
                    <input type="checkbox" name="banner_app_active" id="banner_app_active"  class="regular-text code" <?php if($settings['banner_app_active']) : ?> checked <?php endif; ?>>
                </td>
            </tr>
            <tr>
                <td><label for="banner_app_active">Hide on Dekstop</label></td>
                <td>
                    <input type="checkbox" name="banner_app_hide_desktop" id="banner_app_hide_desktop"  class="regular-text code" <?php if($settings['banner_app_hide_desktop']) : ?> checked <?php endif; ?>>
                </td>
            </tr>
            <tr>
                <td><label for="banner_app_logo">Logo URL (100x100px)</label></td>
                <td>
                    <input type="text" name="banner_app_logo" value="<?php echo esc_url($settings['banner_app_logo']); ?>" id="banner_app_logo" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="banner_app_url_apple">App Store URL</label></td>
                <td>
                    <input type="text" name="banner_app_url_apple" value="<?php echo esc_url($settings['banner_app_url_apple']); ?>" id="banner_app_url_apple" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="banner_app_url_google">Google Play URL</label></td>
                <td>
                    <input type="text" name="banner_app_url_google" value="<?php echo esc_url($settings['banner_app_url_google']); ?>" id="banner_app_url_google" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="banner_app_title">Banner Title</label></td>
                <td>
                    <input type="text" name="banner_app_title" value="<?php echo esc_attr($settings['banner_app_title']); ?>" id="banner_app_title" class="regular-text code">
                </td>
            </tr>
            <tr>
                <td><label for="banner_app_desc">Banner Description</label></td>
                <td>
                    <input type="text" name="banner_app_desc" value="<?php echo esc_attr($settings['banner_app_desc']); ?>" id="banner_app_desc" class="regular-text code">
                </td>
            </tr>
            <!-- <tr>
                <td><label for="banner_app_button">Banner Button Text</label></td>
                <td>
                    <input type="text" name="banner_app_button" value="<?php echo esc_attr($settings['banner_app_button']); ?>" id="banner_app_button" class="regular-text code">
                </td>
            </tr> -->
        </table>
        <?php submit_button(__('Save', 'grind-mobile-app'));?>
    </form>
    </div>
</div>