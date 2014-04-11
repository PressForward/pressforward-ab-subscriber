<?php

/*
Plugin Name: PressForward Academic Blogs Subscriber
Plugin URI: http://pressforward.org/
Description: This plugin is an adds subscriptions from the Academic Blogs directory to PressForward.
Version: 1.0.1
Author: Aram Zucker-Scharff, Boone B Gorges
Author URI: http://aramzs.me, http://boone.gorg.es/
License: GPL2
*/

/*  Developed for the Center for History and New Media

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PF_AB_ROOT', dirname(__FILE__) );
define( 'PF_AB_FILE_PATH', PF_AB_ROOT . '/' . basename(__FILE__) );
define( 'PF_AB_URL', plugins_url('/', __FILE__) );

require_once(PF_AB_ROOT . "/includes/linkfinder/AB_subscription_builder.php");

if ( class_exists( 'PressForward' ) ) :


    class PF_AB_Subscriber {
       var $id;
	   var $module_dir;
	   var $module_url;
        
        /**
         * Constructor
         */
        public function __construct() {
            self::start();
            add_action( 'wp_ajax_refresh_ab_feeds', array( $this, 'refresh_ab_feeds_callback' ) );
            add_action( 'wp_ajax_finish_ab_feeds', array( $this, 'finish_ab_feeds_callback' ) );
            if (is_admin()){
                add_action( 'wp_ajax_ab_add_validator', array( $this, 'ab_add_validator' ) );
            }
        }
        
        function start() {
            $this->setup_hooks();
        }

        function setup_hooks() {
            // Once modules are registered, set up some basic module info
            add_action( 'pf_setup_modules', array( $this, 'setup_module_info' ) );
            add_action( 'admin_init', array($this, 'module_setup') );
            // Set up the admin panels and save methods
            add_action( 'pf_admin_op_page', array( $this, 'admin_op_page' ) );
            add_action( 'pf_admin_op_page_save', array( $this, 'admin_op_page_save' ) );

        }
        
        function setup_module_hooked($modules){
            
            $modules[] = array(
					'slug' => $module_dir,
					'class' => get_called_class()
            );
            
            return $modules;
        }
        
        /**
         * Determine some helpful info about this module
         *
         * Sets the module ID based on the key used to register the module in
         * the $pf global
         *
         * Also sets up the module_dir and module_url for use throughout
         */
        function setup_module_info() {
            $pf = pressforward();

            // Determine the ID by checking which module this class belongs to
            $module_class = get_class( $this );
            foreach ( $pf->modules as $module_id => $module ) {
                if ( is_a( $module, $module_class ) ) {
                    $this->id = $module_id;
                    break;
                }
            }

            // If we've found an id, use it to create some paths
            if ( $this->id ) {
                $this->module_dir = trailingslashit( PF_AB_ROOT );
                $this->module_url = trailingslashit( PF_AB_URL );
            }

            $enabled = get_option( PF_SLUG . '_' . $this->id . '_enable' );
            if ( ! in_array( $enabled, array( 'yes', 'no' ) ) ) {
                $enabled = 'yes';
            }

            if ( 'yes' == $enabled ) {
                add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
                add_action( 'feeder_menu', array( $this, 'add_to_feeder' ) );
            }

            if ( method_exists( $this, 'post_setup_module_info' ) ) {
                $this->post_setup_module_info();
            }
        }
        
        function post_setup_module_info(){
            add_filter('pressforward_register_modules', array($this,'setup_module_hooked'));
        }
        
        function module_setup(){
            $mod_settings = array(
                'name' => $this->id . ' Module',
                'slug' => $this->id,
                'description' => 'This module provides the ability to subscribe to blogs listed on the academic blogs wiki.',
                'thumbnail' => '',
                'options' => ''
            );

            update_option( PF_SLUG . '_' . $this->id . '_settings', $mod_settings );

            //return $test;
        }        

        public function admin_op_page() {
            //Module enable option code originated in https://github.com/boonebgorges/participad
            $modsetup = get_option(PF_SLUG . '_' . $this->id . '_settings');
            $modId = $this->id;
            //print_r(PF_SLUG . '_' . $modId . '_enable');
            $enabled = get_option(PF_SLUG . '_' . $modId . '_enable');
            if ( ! in_array( $enabled, array( 'yes', 'no' ) ) ) {
                $enabled = 'yes';
            }
                //print_r( $this->is_enabled() );
            ?>
                <h4><?php _e( $modsetup['name'], PF_SLUG ) ?></h4>

                <p class="description"><?php _e( $modsetup['description'], PF_SLUG ) ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="participad-dashboard-enable"><?php _e( 'Enable '. $modsetup['name'], PF_SLUG ) ?></label>
                        </th>

                        <td>
                            <select id="<?php echo PF_SLUG . '_' . $modId . '_enable'; ?>" name="<?php echo PF_SLUG . '_' . $modId . '_enable'; ?>">
                                <option value="yes" <?php selected( $enabled, 'yes' ) ?>><?php _e( 'Yes', PF_SLUG ) ?></option>
                                <option value="no" <?php selected( $enabled, 'no' ) ?>><?php _e( 'No', PF_SLUG ) ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            <?php
        }

        public function admin_op_page_save() {
            $modId = $this->id;
            $enabled = isset( $_POST[PF_SLUG . '_' . $modId . '_enable'] ) && 'no' == $_POST[PF_SLUG . '_' . $modId . '_enable'] ? 'no' : 'yes';
            update_option( PF_SLUG . '_' . $modId . '_enable', $enabled );

        }               
        
        function add_to_feeder(){
            settings_fields( PF_SLUG . '_ab_list_group' );
            echo '<div class="ab-opt-group">';
            echo '<h3>academicblogs.org</h3>';
            $this->render_refresh_ui();
            echo $this->build_ab_item_selector();
            echo '<br /><input type="submit" class="btn btn-info" id="academic-sub" value="Subscribe to Academic Sites">';
            echo '</div>';
        }

        public function build_ab_item_selector() {		
            $ca = 0;
            $cb = 0;
            $cc = 0;
            $ABLinksArray = get_option( 'pf_ab_categories' );
            if ( ! $ABLinksArray ) {
                return __('No blogs found. Try refreshing the academicblogs.org list.', 'pf');
            }

            // Echo the ABLinksArray array into a JS object
            $ab_items_selector  = '<script type="text/javascript">';
            $ab_items_selector .= 'var ABLinksArray = "' . addslashes( json_encode( $ABLinksArray ) ) . '";';
            $ab_items_selector .= '</script>';

            // Build the top-level dropdown
            $ab_items_selector .= '<label for="ab-cats">' . __( 'Category', 'pf' ) . '</label>';
            $ab_items_selector .= '<select class="ab-dropdown" name="ab-cats" id="ab-cats">';
            foreach ( (array) $ABLinksArray['categories'] as $cat_slug => $cat ) {
                $ab_items_selector .= '<option value="' . esc_attr( $cat_slug ) . '">' . esc_html( $cat['text'] ) . '</option>';
            }
            $ab_items_selector .= '</select>';

            // Add dummy dropdowns for subcategories
            $ab_items_selector .= '<label for="ab-subcats">' . __( 'Subcategory', 'pf' ) . '</label>';
            $ab_items_selector .= '<select class="ab-dropdown" name="ab-subcats" id="ab-subcats" disabled="disabled"><option>-</option></select>';

            // Add dummy dropdowns for blogs
            $ab_items_selector .= '<label for="ab-blogs">' . __( 'Blog', 'pf' ) . '</label>';
            $ab_items_selector .= '<select class="ab-dropdown" name="ab-blogs" id="ab-blogs" disabled="disabled"><option>-</option></select>';

    /*
            foreach ( (array) $ABLinksArray['categories'] as $genSubject){
                if ($ca == 0){
                    $ab_items_selector .= '<option disabled="disabled" value="0">----topic----<hr /></option>';
                }

                $ab_items_selector .= '<option value="' . $genSubject['slug'] . '">' . $genSubject['text'] . ' - ' . $ca . '</option>';
                if ($ca == 0){
                    $ab_items_selector .= '<option disabled="disabled" value="0">--------<hr /></option>';
                    $cb = 0;
                }
                $ca++;

                foreach ( (array) $genSubject['links'] as $subject){
                    //if ($cb == 0){
                        $ab_items_selector .= '<option disabled="disabled" value="0">----section----<hr /></option>';
                    //}
                    $ab_items_selector .= '<option value="' . $subject['slug'] . '">&nbsp;&nbsp;&nbsp;' . $subject['title'] . ' - ' . $cb . '</option>';

                    $ab_items_selector .= '<option disabled="disabled" value="0">--------<hr /></option>';
                    if ($cb == 0){
                        $ca = 0;
                        $cc = 0;
                    }
                    $cb++;

                    if ( isset( $subject['blogs'] ) ) {
                        foreach ($subject['blogs'] as $blogObj){

                            $ab_items_selector .= '<option value="' . $blogObj['slug'] . '">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $blogObj['title'] . ' - ' . $cc . '</option>';
                            if ($cc == 0){
                                //$ab_items_selector .= '<option disabled="disabled" value="0"><hr /></option>';

                                $cb = 0;
                            }
                            $cc++;

                        }
                    }
                }

            }
    */
            $ab_items_selector .= '</select>';

            return $ab_items_selector;
        }

        /**
         * Renders the UI for the "refresh feeds" section
         */
        public function render_refresh_ui() {
            $cats = get_option( 'pf_ab_categories' );
            $last_refreshed = isset( $cats['last_updated'] ) ? date( 'M j, Y H:i', $cats['last_updated'] ) : 'never';
            ?>

            <div id="refresh-ab-feeds">

                <p>
                    <?php 
                    $ab_refresh_when = sprintf(__('The list of feeds from academicblogs.org was last refreshed at <strong>%d</strong>. Click the Refresh button to do a refresh right now.', 'pf'), $last_refreshed);
                    echo $ab_refresh_when;
                    ?>
                </p>

                <a class="button" id="calc_submit"><?php _e('Refresh', 'pf') ?></a>

                <br />
                <div id="calc_progress"></div>
                <br />
            </div>

            <?php
        }

        /**
         * A function to take the last selected value and turn it into blog subscriptions.
         */	
        public function get_subs_by_value($values){
            $cats = get_option( 'pf_ab_categories' );
            $cats = $cats["categories"];
            $r = array();
            $c = 0;
            if (!empty($cats[$values['cat']]['links'][$values['subcat']]['blogs'][$values['blog']])){
                #$r[] = 1;
                $r[0]['url'] = $cats[$values['cat']]['links'][$values['subcat']]['blogs'][$values['blog']]['url'];
                $r[0]['feedUrl'] = $cats[$values['cat']]['links'][$values['subcat']]['blogs'][$values['blog']]['url'];
                $r[0]['title'] = $cats[$values['cat']]['links'][$values['subcat']]['blogs'][$values['blog']]['title'];
                return $r;
            }
            if (!empty($cats[$values['cat']]['links'][$values['subcat']])){
                foreach ($cats[$values['cat']]['links'][$values['subcat']]['blogs'] as $blog){

                    $r[$c]['url'] = $blog['url'];
                    $r[$c]['feedUrl'] = $blog['url'];
                    $r[$c]['title'] = $blog['title'];
                    $c++;
                }
                return $r;
            }
            if (!empty($cats[$values['cat']])){
                #foreach ($cats[$values['cat']] as $cat){
                    foreach ($cats[$values['cat']]['links'] as $subcat){
                        foreach ($subcat['blogs'] as $blog){

                            $r[$c]['url'] = $blog['url'];
                            $r[$c]['feedUrl'] = $blog['url'];
                            $r[$c]['title'] = $blog['title'];
                            $c++;
                        }
                    }
                #}			
                return $r;
            }

        }

        function register_settings(){
            register_setting(PF_SLUG . '_ab_list_group', PF_SLUG . '_ab_categories', array('PF_AB_Subscribe', 'ab_add_validator'));
        }	

        /* 
         * Function to handle submissions from the pulldown menus
         */
        public function ab_add_validator($input = false){
            ob_start();
            set_time_limit(0);
            pf_log('Add Feed Process Invoked: PF_AB_Subscribe::ab_add_validator');

            $ab['cat'] = $_POST['ab_cats'];
            $ab['subcat'] = $_POST['ab_subcats'];
            $ab['blog'] = $_POST['ab_blogs'];
            foreach($ab as $k=>$e){
                if ('-' == $e){
                    $ab[$k] = NULL;   
                }
            }

            pf_log($ab);

            if ( current_user_can('edit_post') ) {
                pf_log('Yes, the current user can edit posts.');
            } else {
                pf_log('No, the current user can not edit posts.');
            }
            $c = 0;
            if(!empty($ab['cat'])){
                $feed_obj = pressforward()->pf_feeds;
                $subs = $this->get_subs_by_value($ab);
                foreach ($subs as $sub){
                    $current_user = wp_get_current_user();
                    $sub['user_added'] = $current_user->user_login;
                    $sub['module_added'] = 'ab-subscribe';
                    $feed_obj->create($sub['url'], $sub); 
                    $c++;
                }
            }
            #var_dump($input); die();

            #return $input;
            $response = array(
               'what'=>'ab_add_validator',
               'action'=>'add_feeds',
               'id'=>$current_user->ID,
               'data'=> $c.' feeds added.',
               'supplemental' => array(
                    'buffered' => ob_get_contents()
                )
            );
            $xmlResponse = new WP_Ajax_Response($response);
            $xmlResponse->send();
            ob_end_clean();
            die();
        }

        /**
         * The AJAX callback function for refreshing the academicblogs.org feeds
         *
         * This is called by the ajax request to 'refresh_ab_feeds' in
         * modules/ab-subscribe/js/progressbar.js
         *
         * The value echoed from this function should be a percentage between
         * 0 and 100. This value is used by the progressbar javascript to show
         * the progress level
         */
        public function refresh_ab_feeds_callback() {

            if ( ! isset( $_POST['start'] ) ) {
                return;
            }

            $start = intval( $_POST['start'] );

            if ( 0 === $start ) {
                // This is the beginning of a routine. Clear out previous caches
                // and refetch top-level cats
                delete_option( 'pf_ab_categories' );
                $cats = AB_subscription_builder::get_blog_categories();
                update_option( 'pf_ab_categories', $cats );

                // Set the percentage to 1%. This is sort of a fib
                $pct = 1;
            } else {
                // Anything but zero: Pull up the categories and pick
                // up where last left off
                $cats = get_option( 'pf_ab_categories' );
                $cats = AB_subscription_builder::add_category_links( $cats, 1 );

                if ( $cats['nodes_populated'] >= $cats['node_count'] ) {
                    $cats['last_updated'] = time();
                }

                update_option( 'pf_ab_categories', $cats );

                // Calculate progress
                $pct = intval( 100 * ( $cats['nodes_populated'] / $cats['node_count'] ) );
            }

            echo $pct;
            die();
        }

        /**
         * Manually set the last_updated key for the ab categories option
         *
         * Called by the ajax request to 'finish_ab_feeds'
         *
         * This is necessary because of a weird bug in the way the progressbar
         * script works - I can't always tell on the server side whether we've
         * hit 100%. So at the end of the process, the browser manually sends
         * the request to finish up the process
         */
        public function finish_ab_feeds() {
            $cats = get_option( 'pf_ab_categories' );
            $cats['last_updated'] = time();
            update_option( 'pf_ab_categories', $cats );
            die();
        }

        /**
         * Enqueue our scripts and styles for the progressbar to work
         */
        public function admin_enqueue_scripts() {
            global $pagenow;

            $pf = pressforward();

            $hook = 0 != func_num_args() ? func_get_arg( 0 ) : '';

            if ( !in_array( $pagenow, array( 'admin.php' ) ) )
                return;

            if(!in_array($hook, array('pressforward_page_pf-feeder')) )
                return;

            wp_enqueue_script( 'jquery-ui' );
            wp_enqueue_script( 'jquery-ui-progressbar' );
            wp_enqueue_script( 'ab-refresh-progressbar', PF_AB_FILE_PATH . 'assets/js/progressbar.js', array( 'jquery', 'jquery-ui-progressbar') );
            wp_enqueue_script( 'ab-dropdowns', PF_AB_FILE_PATH . 'assets/js/dropdowns.js', array( 'jquery' ) );
            wp_enqueue_script( 'handle-ab-subs', PF_AB_FILE_PATH . 'assets/js/handle-ab-subs.js', array( 'jquery' ) );
            wp_enqueue_style( 'ab-refresh-progressbar', PF_AB_FILE_PATH . 'assets/css/progressbar.css' );
        }
    }
/**
 * Bootstrap
 *
 * You can also use this to get a value out of the global, eg
 *
 *    $foo = pressforward_ab_subscriber()->bar;
 *
 * @since 1.0
 */
function pressforward_ab_subscriber() {
	return PF_AB_Subscribe::start();
}

add_action('pressforward_init', 'pressforward_ab_subscriber');
endif;