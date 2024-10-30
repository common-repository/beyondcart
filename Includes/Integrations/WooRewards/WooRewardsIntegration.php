<?php
namespace BCAPP\Includes\Integrations\WooRewards;

use WP_REST_Server;

if (!defined('ABSPATH')) exit;
// Exit if accessed directly

class WooRewardsIntegration
{
     /**
     * Registers a REST API route
     *
     * @since 1.0.0
     */
    public function add_api_routes()
    {
        register_rest_route(BCAPP_api_namespace, 'loyalty_get_points_history', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_points_history'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(BCAPP_api_namespace, 'loyalty_get_points_history_total', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_points_history_total'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(BCAPP_api_namespace, 'loyalty_get_points_history_html', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_points_history_html'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(BCAPP_api_namespace, 'loyalty_how_to_earn_points', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'how_to_earn_points'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(BCAPP_api_namespace, 'loyalty_product_points', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'product_points'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(BCAPP_api_namespace, 'loyalty_referral_link', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'referral_link'),
            'permission_callback' => '__return_true',
        ));

    }

	/**
	 * Get points total for a user using Plugin's methods
	 * @email
	 */
    public function get_points_history_total($request) {

        $pool = $request->get_param('pool');  // name or pool_id [OPTIONAL]
        $user_email = $request->get_param('email');
        $user = \get_user_by('email', $user_email);
		if( !$user || !$user->ID)
			return new \WP_Error('no_user', __('Unknown User or missing params (pool_id, user_id, email)', 'woorewards-pro'), array('status' => 404));
	
	    //TODO add security who can check points history
        // 		if (\get_current_user_id() != $user->ID && !$this->currentUserCan('lws_wr_read_other_points'))
        // 			return new \WP_Error('rest_forbidden', __('Cannot view others data', 'woorewards-pro'), array('status' => 403));

		$points = array();
 		if( !isset($pool) || empty($pool) )
 		{
			foreach( \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep'=>false))->asArray() as $pool )
			{
				$points[] = array(
					'id' => $pool->getName(),
					'points' => $pool->getStackId(),
					'value'  => $pool->getPoints($user->ID),
				);
			}
		}
		else
		{
			$pool = $this->getThePool($pool_id);
			if( !$pool )
				return new \WP_Error('no_pool', __('Unknown Loyalty System', 'woorewards-pro'), array('status' => 404));

			$points[] = array(
				'id' => $pool->getName(),
				'points' => $pool->getStackId(),
				'value'  => $pool->getPoints($user->ID),
			);
		}

        $response = new \WP_REST_Response($points);
		return $response;
    }

	/** get the pool */
	protected function getThePool($pool_id='id', $deep=false)
	{
		return \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($pool_id, $deep);
	}
	
	/**
	 * Get points history and divided by pool
	 * from wp_lws_wr_historic table in DB
	 */
    public function get_points_history($request) {
        
        //TODO: add security
        $user_email = $request->get_param('email');
        
        $user = \get_user_by('email', $user_email);
        $user_id = $user->ID;

        global $wpdb;
    
        $table_name = $wpdb->prefix . "lws_wr_historic";
        $query = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY stack, mvt_date DESC", $user_id);
        $results = $wpdb->get_results($query);
    
        if (!$results) return [];
    
        $history_data = [];
        $current_stack = '';
        $current_entries = [];
    
        foreach ($results as $entry) {
            // Deserialize the commentar column
            $comment_data = maybe_unserialize($entry->commentar);
    
            // Process the comment text
            if (is_array($comment_data) && isset($comment_data[0])) {
                $format = $comment_data[0];
                $args = array_slice($comment_data, 1);
                $comment_text = vsprintf($format, $args);
            } else {
                $comment_text = '';
            }
    
            if ($current_stack != $entry->stack) {
                if ($current_stack != '') {
                    $history_data[$current_stack] = $current_entries;
                }
                $current_stack = $entry->stack;
                $current_entries = [];
            }
            
            $current_entries[] = [
                'date' => $entry->mvt_date,
                'points' => $entry->points_moved,
                'new_total' => $entry->new_total,
                'comment' => $comment_text,
                'origin' => $entry->origin
            ];
        }
    
        if ($current_stack != '') {
            $history_data[$current_stack] = $current_entries;
        }
    
        return $history_data;
    }
    
	/**
	 * Returns user's history, formatted
	 */
    public function get_points_history_formatted($request) {
       $user_id = $request->get_param('user_id');
        
        wp_set_current_user($user_id);
        $shortcode = do_shortcode('[get_points_history_html]');
        return $shortcode;
    }

	/**
	 * Returns info from how to earn points
	 */
    public function how_to_earn_points($request) {
       $user_id = $request->get_param('user_id');
        
        wp_set_current_user($user_id);
        $shortcode = do_shortcode('[wr_points_balance]');
        return $shortcode;
    }
    
	/**
	 * Returns info from how to earn points
	 */
    public function product_points($request) {
        $user_id = $request->get_param('user_id');
        $product_id = $request->get_param('product_id');
        
        wp_set_current_user($user_id);

        $shortcode = do_shortcode('[wr_product_points id="' . $product_id . '"]');
        return $shortcode;
    }
    
	/**
	 * Get the refferal link
	 */
    public function referral_link($request) {
        $user_id = $request->get_param('user_id');
        wp_set_current_user($user_id);

        $shortcode = do_shortcode('[wr_referral_link mode="link" showbutton="false" showlink="true" button="" copied=""]');
        
        // Create a new DOMDocument instance and load the HTML.
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true); // Suppress warnings
        $doc->loadHTML($shortcode);
        libxml_clear_errors(); // Clear any errors from the loading process
        
        // Create a new XPath instance and query the document.
        $xpath = new \DOMXPath($doc);
        $nodeList = $xpath->query("//div[contains(@class, 'link-url')]");
        
        // The URL is the nodeValue of the first node in the nodeList.
        $url = $nodeList->item(0)->nodeValue;
        
        return $url;
    }
    
}
