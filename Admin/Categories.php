<?php

namespace BCAPP\Admin;

use DateTime;

class Categories
{


  public function __construct()
  {
    $this->add_category_app_image_field();

    // Schedule a Cron job once a day to update cached terms by category
    add_action('wp', array($this, 'schedule_update_cached_terms_by_category'));
    // Register a hook to trigger our class to update the terms
    add_action('cron_update_cached_terms_by_category', function () {
      $this->updateCachedTermsByCategory();
    });

    // Custom links to force update all terms by category
    add_action('init', function () {
      if ($_SERVER['REQUEST_URI'] == '/n32vdk48yr2hfjj45kq23rdfkjs3f25q3' && current_user_can('administrator')) {
        do_action('cron_update_cached_terms_by_category');
      }
    });

    add_filter( 'woocommerce_rest_product_tag_query', function( $prepared_args, $request ){
      $max = max( 10, $request->get_param( 'custom_per_page' ) ? (int) $request->get_param( 'custom_per_page' ) : (int) $request->get_param( 'custom_per_page' ) );
      $prepared_args['number'] = $max;
      return $prepared_args;
    }, 2, 10 );

  }

  public function add_category_app_image_field()
  {
    if (function_exists('acf_add_local_field_group')) :

      acf_add_local_field_group(array(
        'key' => 'group_beyondcart_product_cat_image',
        'title' => 'Beyondcart Product Cateogry Image',
        'fields' => array(
          array(
            'key' => 'field_app_image',
            'label' => 'App Image',
            'name' => 'app_image',
            'type' => 'image',
            'return_format' => 'id',
            'preview_size' => 'medium',
          )
        ),
        'location' => array(
          array(
            array(
              'param' => 'taxonomy',
              'operator' => '==',
              'value' => 'product_cat',
            ),
          ),
        ),
      ));

    endif;
  }

  /**
   * Go through all categories and check which terms are not empty
   * Store them in our DB (custom tables) once per day and provide endpoint for the app
   * to be able to show them as a filters in BeyondCart
   */
  public function updateCachedTermsByCategory()
  {
    global $wpdb;

    $start = microtime(true);

    // Get all Available Product Categories
    $category_ids = $this->getAllProductCategories();
    // Get all Available Attributes (pa_color, pa_size, ...)
    $get_all_available_attributes = $this->getAllAttributesTaxonomies();

    $data = [];
    if (!empty($category_ids)) {
      //Loop through every category
      foreach ($category_ids as $term_id) {
        $data[$term_id] = [];
        //Loop through all attributes
        if (!empty($get_all_available_attributes)) {
          foreach ($get_all_available_attributes as $taxonomy) {
            $resultTax = array_values($this->getAllTermsByTaxonomyAndCategory($taxonomy, $term_id));
            if (!empty($resultTax)) {
              $data[$term_id][$taxonomy] = $resultTax;
            }
          }
        }
        //Get All Product Tag terms as well
        $resultTag = array_values($this->getAllTermsByTaxonomyAndCategory('product_tag', $term_id));
        if (!empty($resultTag)) {
          $data[$term_id]['product_tag'] = $resultTag;
        }

        // Create/Update a terms row for every category_id in _terms table 
        $result = $this->updateOrCreateTermsByCategory($term_id, $data[$term_id]);
      }
    }

    if (empty($data)) {
      return false;
    }

    $time_elapsed_secs = microtime(true) - $start;

    var_dump($time_elapsed_secs);
  }

  /**
   * Query to Get all product categories
   * @return array (term_ids)
   */
  public function getAllProductCategories()
  {
    global $wpdb;

    $query_get_all_cats = "
      SELECT term_id FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy LIKE 'product_cat'
    ";
    return $wpdb->get_col($query_get_all_cats);
  }

  /**
   * Query to Get all Attributes taxonomies 
   * @return array (pa_color, pa_size, ...)
   */
  public function getAllAttributesTaxonomies()
  {
    global $wpdb;

    $query_all_atribute_taxonomies = "
    SELECT DISTINCT taxonomy FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy LIKE 'pa_%'
    ";
    return $wpdb->get_col($query_all_atribute_taxonomies);
  }

  /**
   * Query to update Terms in DB table all wp_grind_mobile_app 
   * @return array (pa_color, pa_size, ...)
   */
  public function updateOrCreateTermsByCategory($category_id, $data)
  {
    global $wpdb;

    $data_to_insert = json_encode($data, JSON_UNESCAPED_UNICODE);

    // Update record
    $query_update = "UPDATE {$wpdb->prefix}grind_mobile_app_terms SET data='{$data_to_insert}', date_updated=now() WHERE category_id={$category_id}";
    $result = $wpdb->query($query_update);

    //If nothing found to update, try and create the record.
    if (empty($result) || $result < 1) {
      $query_create = "
        INSERT INTO {$wpdb->prefix}grind_mobile_app_terms (category_id, data, date_created)
        VALUES ({$category_id}, '{$data_to_insert}', now())
      ";
      $result = $wpdb->query($query_create);
    }

    if (!empty($result)) {
      //TODO update in plugins page - last updated date - with green color
    }
    if (empty($result)) {
      //TODO update in plugins page - red color and message that something is not ok 
    }
  }

  /**
   * Query to Get all terms_ids available from specific category and specific taxonomy.
   * For example get all term_ids attached to a product from category "Shoes" and taxonomy "pa_color"  
   * Then we need to combine all the rows from the query, remove empty and duplicated ids.
   * @return array (term_ids [123,546,764])
   */
  public function getAllTermsByTaxonomyAndCategory($taxonomy_name, $product_category_id)
  {
    global $wpdb;

    $query = "
      SELECT
          GROUP_CONCAT(t.term_id ORDER BY t.term_id) term_ids
      FROM {$wpdb->prefix}posts p
      LEFT JOIN {$wpdb->prefix}term_relationships cr
          on (p.id=cr.object_id)
      LEFT JOIN {$wpdb->prefix}term_taxonomy ct
          on (ct.term_taxonomy_id=cr.term_taxonomy_id
          and ct.taxonomy='product_cat')
      LEFT JOIN {$wpdb->prefix}terms c on
          (ct.term_id=c.term_id)
      LEFT JOIN {$wpdb->prefix}term_relationships tr
          on (p.id=tr.object_id)
      LEFT JOIN {$wpdb->prefix}term_taxonomy tt
          on (tt.term_taxonomy_id=tr.term_taxonomy_id
          and tt.taxonomy='{$taxonomy_name}')
      LEFT JOIN {$wpdb->prefix}terms t
          on (tt.term_id=t.term_id)
      WHERE p.post_type = 'product' AND p.post_status = 'publish'
        AND  c.term_id = {$product_category_id} && ct.taxonomy = 'product_cat'
      GROUP BY p.id, p.post_name  
      ORDER BY term_ids  DESC
    ";
    $result = $wpdb->get_col($query);

    if (empty($result)) {
      return [];
    }

    $term_ids = [];
    foreach ($result as $row) {
      if (!empty($row)) {
        $exploded = explode(',', $row);
        foreach ($exploded as $term_id) {
          $term_ids[] = (int) $term_id;
          // array_push($term_ids, (int) $term_id);
        }
      }
    }
    return array_unique($term_ids);
  }

  /**
   * Cron Job to update cached terms by category
   */
  public function schedule_update_cached_terms_by_category()
  {
    if (!wp_next_scheduled('cron_update_cached_terms_by_category')) {
      $dt = new DateTime('tomorrow');
      $dt->setTime(3, 11, 0);
      wp_schedule_event($dt->getTimestamp(), 'hourly', 'cron_update_cached_terms_by_category');
    }
  }
}
