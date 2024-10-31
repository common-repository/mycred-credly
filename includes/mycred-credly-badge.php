<?php
if ( ! defined( 'MYCRED_CREDLY_SLUG' ) ) exit;

if ( ! class_exists('myCRED_Credly_Badge') ):
    class myCRED_Credly_Badge extends myCRED_Badge_Module {

        protected $api_auth_url = 'https://api.credly.com/v1.1/badges';
        public $credly_auth;

        /**
         * Construct
         * @since 1.0
         * @version 1.0
         */
        public function __construct() {

            $this->credly_auth = get_option('mycred_credly_auth');

            add_action( 'add_meta_boxes', array( $this, 'add_credly_metaboxes' ) );     
            add_action( 'wp_ajax_search_credly_categories', array( $this, 'credly_category_search' ) );
            add_action( 'wp_ajax_get-credly-badge-builder', array( $this, 'get_credly_badge_builder' ) );
            add_action( 'wp_ajax_credly-save-badge', array( $this, 'ajax_save_badge' ) );
            add_filter( 'manage_' . MYCRED_BADGE_KEY . '_posts_columns', array( $this, 'adjust_column_headers' ), 20 );
            add_action( 'mycred_save_badge', array( $this, 'save_credly_badge' ), 10, 1 );
            add_action( 'mycred_after_badge_assign', array( $this, 'assign_credly_badge' ), 10, 3 );
            add_filter( 'mycred_badges', array( $this, 'filter_badge_shortcode' ), 10, 1 );
            add_filter( 'mycred_my_badges', array( $this, 'filter_my_badges_shortcode' ), 10, 2 );
            add_action( 'manage_posts_extra_tablenav', array( $this, 'connect_existing_credly_badge'), 20, 1 );
            add_action( 'wp_ajax_get-mycred-credly-badges-list', array( $this, 'get_mycred_credly_badges_list' ) );
            add_action( 'wp_ajax_get-mycred-connect-credly-badge', array( $this, 'connect_credly_badge' ) );
            add_action( 'before_delete_post', array( $this, 'delete_badge_id' ) );

        }

        /*
         * add metaboxes in mycred-badge post type
         */
        public function add_credly_metaboxes( $post ) {
            add_meta_box(
                'mycred-credly-badge-setup', 
                __( 'Credly Badge Builder Setup', 'mycred_credly' ), 
                array($this, 'metabox_credly_badge_builder_sdk_setup'), 
                MYCRED_BADGE_KEY, 
                'normal', 
                'high'
            );
            add_meta_box(
                'mycred-credly-short-description', 
                __( 'Short Description', 'mycred_credly' ), 
                array($this, 'metabox_credly_badge_description'), 
                MYCRED_BADGE_KEY, 
                'normal', 
                'high'
            );
        }

        /*
         *   badge builder SDK metabox
         */

        public function metabox_credly_badge_builder_sdk_setup( $post ) {

            $credly_expiration = mycred_get_post_meta( $post->ID, 'credly_expiration', true ) ? mycred_get_post_meta( $post->ID, 'credly_expiration', true ) : 0;
            $credly_categories = maybe_unserialize( mycred_get_post_meta( $post->ID, 'mycred_credly_categories', true ) );

            $badge_link_text = __('Credly Badge Builder', 'mycred_credly');
            ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Upload Badge Image'); ?></th>
                    <td><?php
                        _e('To set an image use the metabox to the right. '
                                . 'Or, design a badge using ' .
                                $this->get_badge_builder_link(array('link_text' => $badge_link_text ))
                                , 'mycred_credly');
                        ?>
                    </td>
                </tr> 
                <tr valign="top">
                    <th scope="row">
                        <label><?php _e('Expiration in Days '); ?><span> <?php _e('(0 means never)'); ?></span></label></th>
                    <td>
                        <input type="number" name="credly_expiration" min="0" value="<?php echo $credly_expiration ?>"/>                     
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label> <?php _e('Search Categories'); ?></label></th>
                    <td>
                        <input type="text" id="credly_category_search" name="credly_category_search" value="" size="50" />
                    </td>
                <tr valign="top">
                    <th scope="row" valign="top" id="credly_search_results" <?php if (!is_array( $credly_categories )) { ?>style="display:none"<?php } ?> >
                        <label><?php _e('Badge Categories', 'mycred_credly'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <?php echo $this->credly_existing_category_output( $credly_categories ); ?>

                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php
        }

        public function metabox_credly_badge_description( $post ) {
            $description = mycred_get_post_meta( $post->ID, 'mycred_credly_badge_description', true );
            $description = ( ! empty( $description ) ? $description : '' );
        ?>
            <textarea name="mycred_credly_badge_description" rows="3" maxlength="128" id="mycred_credly_badge_description"><?php echo $description; ?></textarea>
        <?php
        }

        private function credly_existing_category_output($categories = array()) {
            
            // Return if we don't have any categories saved in post meta
            if ( !is_array( $categories ) ) return;

            $markup = '';
            foreach ( $categories as $id => $name ) {
                $markup .= '<label for="mycred_credly_categories[' . $id . ']">
                                <input type="checkbox" name="mycred_credly_categories[' . $id . ']" id="mycred_credly_categories[' . $id . ']" value="' . esc_attr($name) . '" checked="checked" /> ' . ucwords($name) . '
                            </label>
                            <br />';
            }
            return $markup;
        }

        /*
         *  render badge builder sdk link
         */

        public function get_badge_builder_link( $args ) {

            global $post;

            // Setup and parse our default args
            $defaults = array(
                'attachment_id' => ! empty( $post ) ? get_post_thumbnail_id() : 0,
                'width' => '960',
                'height' => '540',
                'continue' => null,
                'link_text' => __('Use Credly Badge Builder', 'mycred_credly'),
            );
            $args = wp_parse_args( $args, $defaults );

            $link = '#TB_inline?width=' . $args['width'] . '&height=' . $args['width'] . '&inlineId=teaser';;

            // Build our link tag
            if ( ! empty( $this->credly_auth ) ) {
                $link = admin_url( 'admin-ajax.php?action=get-credly-badge-builder&attachment_id=' . $args['attachment_id'] . '&TB_iframe=true' );
            }

            $output = sprintf( '<a href="%s" class="thickbox badge-builder-link" data-width="%s" data-height="%s" data-attachment_id="%s">%s</a>', $link, $args['width'], $args['height'], $args['attachment_id'], $args['link_text'] );

            // Return our markup
            return $output;
        }


        public function get_credly_badge_builder() {

            // Build continue param
            $attachment_id = empty( $_REQUEST['attachment_id'] ) ? absint( $_REQUEST['attachment_id'] ) : 0;
            $continue = $attachment_id ? get_post_meta( $attachment_id, '_credly_badge_meta', true ) : null;
            
            wp_redirect( $this->get_credly_badge_builder_link( array( 'continue' => $continue ) ) );
            die();
        }

        /*
         *  iframe of badge builder sdk 
         */
        public function get_credly_badge_builder_link( $args = array() ) {

            $defaults = apply_filters('credly_badge_builder_generate_link_defaults', array(
                'continue' => null,
            ));
            $args = wp_parse_args( $args, $defaults );
            
            $link = '#TB_inline?width=' . $args['width'] . '&height=' . $args['width'] . '&inlineId=teaser';

            if ( ! empty( $this->credly_auth ) ) {

                $sdk_url = 'https://credly.com/badge-builder/code/';

                $credly_response = wp_remote_post( $sdk_url, array(
                    'headers' => array(
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Content-Length' => 141
                    ),
                    'body' => array(
                        'access_token' => $this->credly_auth['access_token']
                    )
                ) );

                if ( ! is_wp_error( $credly_response ) ) {
                    
                    $response_data = json_decode( $credly_response['body'] );

                    if ( $response_data->success == true ) {
                        $link = add_query_arg(
                            array( 'continue' => rawurlencode( json_encode( $args['continue'] ) ) ), 
                            esc_url( trailingslashit( "https://credly.com/badge-builder/embed/" ) . $response_data->temp_token )
                        );
                    }
                }

            }
            // Return our generated link
            return $link;
        }

        /*
         * search in credly categories
         */

        public function credly_category_search() {

            if ( ! empty( $this->credly_auth ) ) {

                $category_url = 'https://api.credly.com/v1.1/badges/categories';
                $search_query = sanitize_text_field( $_REQUEST['search_terms'] );

                $credly_response = wp_remote_get( $category_url, array(
                    'headers' => array(
                        'X-Api-Key' => $this->credly_auth['key'],
                        'X-Api-Secret' => $this->credly_auth['secret'],
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ),
                    'body' => array(
                        'query' => $search_query,
                        'page' => 1,
                        'per_page' => 10
                    )
                ) );

                if ( ! is_wp_error( $credly_response ) ) {
                    
                    $response_data = json_decode( $credly_response['body'] );

                    if ( $response_data->meta->status_code == 200 ) {
                        wp_send_json( $response_data->data );
                    }
                }
            }
            wp_die();
        }

        public function ajax_save_badge() {

            if ( !defined('DOING_AJAX') || ! defined('WP_ADMIN')) wp_send_json_error();
            // Grab all our data
            $post_id = intval( $_REQUEST['post_id'] );
            $image   = esc_url( $_REQUEST['image'] );

            // Upload the image
            $attachment_id = $this->media_sideload_image( $image, $post_id );
            // Set as featured image
            set_post_thumbnail( $post_id, $attachment_id, __( 'Badge created with Credly Badge Builder', 'mycred_credly' ) );
            // Build new markup for the featured image metabox
            $metabox_html = _wp_post_thumbnail_html( $attachment_id, $post_id );
            // Return our success response
            wp_send_json_success( array( 'attachment_id' => $attachment_id, 'metabox_html' => $metabox_html ) );

        }

        public function media_sideload_image( $file, $post_id, $desc = null) {
            // file example https://credlyapp.s3.amazonaws.com/badges/standalone/file.png
            if (!empty($file)) {
                // images should be updates as a file so i use download_url() to Downloads a URL to a local temporary file.
                $tmp = download_url($file);
                // fix file filename for query strings
                preg_match('/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches);
                $file_array['name'] = basename($matches[0]);
                $file_array['tmp_name'] = $tmp;
                // If error storing temporarily, unlink
                if (is_wp_error($tmp)) {
                    @unlink($file_array['tmp_name']);
                    $file_array['tmp_name'] = '';
                }

                $id = media_handle_sideload($file_array, $post_id, $desc); //Handles a side-loaded file in the same way as an uploaded file is handled by media_handle_upload().
                // If error storing permanently, unlink
                if (is_wp_error($id)) {
                    @unlink($file_array['tmp_name']);
                }
                // Send back the attachment ID
                return $id;
            }
        }

        public function adjust_column_headers( $defaults ) {

            $columns                        = array();
            $columns['cb']                  = $defaults['cb'];

            // Add / Adjust
            $columns['title']               = __( 'Badge Name', 'mycred_credly' );
            $columns['badge-default-image'] = __( 'Badge Image', 'mycred_credly' );
            $columns['badge-reqs']          = __( 'Requirement', 'mycred_credly' );
            $columns['badge-users']         = __( 'Users', 'mycred_credly' );

            unset( $columns['badge-earned-image'] );

            // Return
            return $columns;

        }


        /*
         * Save Badge Data Created with Credly
         */

        public function save_credly_badge( $post_id ) {

            $mycred = mycred( $this->mycred_type );

            if ( ! $mycred->user_is_point_editor() || ! isset( $_POST['mycred_badge'] ) ) return $post_id;

            $credly_expiration = intval( $_POST['credly_expiration'] );
            $credly_badge_description = sanitize_textarea_field( $_POST['mycred_credly_badge_description'] );
            $credly_categories = array();

            if( ! empty( $_POST['mycred_credly_categories'] ) ) {
                foreach ( $_POST['mycred_credly_categories'] as $key => $value ) {
                    $fitered_key = intval( $key );
                    $fitered_value = sanitize_text_field( $value );
                    $credly_categories[ $fitered_key ] = $fitered_value;
                }
            }

            mycred_update_post_meta( $post_id, 'credly_expiration', $credly_expiration );  
            mycred_update_post_meta( $post_id, 'mycred_credly_categories', $credly_categories );
            mycred_update_post_meta( $post_id, 'mycred_credly_badge_description', $credly_badge_description );
            $badge_id = mycred_get_post_meta( $post_id, 'mycred_credly_badge_id', 1 );
            $this->create_mycred_credly_badge( $post_id, $badge_id, $credly_expiration, $credly_categories, $credly_badge_description );

        }

        /*
         * Create & Update Credly Badge
         */

        public function create_mycred_credly_badge( $post_id, $badge_id, $credly_expiration, $credly_categories, $credly_badge_description ) {

            $default_image = MYCRED_CREDLY_ASSETS . 'images/default-badge.png';
            
            $attach_id  = ( ! empty( $_POST['mycred_badge']['main_image'] ) ? intval( $_POST['mycred_badge']['main_image'] ) : '' );

            $attachment_url = ( $attach_id !='' ? wp_get_attachment_url( $attach_id ) : $default_image );

            $image_data = base64_encode( file_get_contents( $attachment_url ) );
            
            $categories = '';

            if ( ! empty( $credly_categories ) ) {
                
                foreach ( $credly_categories as $key => $value ) {
                    $categories .= $key.',';
                }

                $categories = substr( $categories, 0, -1 );
            }

            if ( ! empty( $this->credly_auth ) ) {

                if ( $badge_id != '' ) {

                    $params['id'] = $badge_id;
                    $url = "https://api.credly.com/v1.1/badges/$badge_id";
                } 
                else {

                    $url = "https://api.credly.com/v1.1/badges";
                }

                $credly_response = wp_remote_post( $url, array(
                    'headers' => array(
                        'X-Api-Key'    => $this->credly_auth['key'],
                        'X-Api-Secret' => $this->credly_auth['secret'],
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ),
                    'body' => array(
                        'access_token'      => $this->credly_auth['access_token'],
                        'attachment'        => $image_data,
                        'title'             => get_the_title( $post_id ),
                        'expires_in'        => $credly_expiration*60*60*24,
                        'categories'        => $categories,
                        'short_description' => $credly_badge_description
                    )
                ) );

                if ( ! is_wp_error( $credly_response ) ) {

                    $response_data = json_decode( $credly_response['body'] );

                    if ( $response_data->meta->status_code == 200 ) {
                        delete_option( 'mycred_credly_notice_error' );
                        mycred_update_post_meta( $post_id, 'mycred_credly_badge_id', $response_data->data );
                        if ( empty( $badge_id ) ) {   
                            $mycred_connected_credly_badges = mycred_get_option( 'mycred_connected_credly_badges', array() );
                            array_push( $mycred_connected_credly_badges, $response_data->data );
                            mycred_update_option( 'mycred_connected_credly_badges' , $mycred_connected_credly_badges );
                        }
                    }
                    else {
                        if ( 
                            ! wp_is_post_revision( $post_id ) && 
                            get_post_status( $post_id ) == 'publish' && 
                            get_post_type( $post_id ) == 'mycred_badge'
                        ) {
                            update_option( 'mycred_credly_notice_error', "Can not Create Badge Number $post_id on credly : " . $response_data->meta->message ); 
                            //update that option to display as notice error
                            wp_update_post( array(
                                'ID' => $post_id,
                                'post_status' => 'draft'
                            ) );
                        }
                    }   
                }
            }

        }

        /*
         * Assign user's achieved badge to Credly
         */

        public function assign_credly_badge( $user_id, $badge_id, $level ) {

            $credly_badge_id = mycred_get_post_meta( $badge_id, 'mycred_credly_badge_id', 1 );

            if ( ! empty( $credly_badge_id ) && ! empty( $this->credly_auth ) ) {

                $user_data = get_userdata( $user_id );

                $user_email = mycred_get_user_meta( $user_id, 'user_credly_email', '', true );

                if ( ! is_email( $user_email ) ) $user_email = $user_data->user_email;

                $is_credly_member = mycred_get_user_meta( $user_id, 'verified_credly_member', '', true );

                if( empty( $is_credly_member ) || $is_credly_member['status'] != true ) {

                    $member_url = 'https://api.credly.com/v1.1/members';

                    $credly_response = $this->get_credly_member( $user_email );

                    if ( ! is_wp_error( $credly_response ) ) {
                        $response_data = json_decode( $credly_response['body'] );

                        if ( $response_data->meta->status_code == 200 ) {
                            mycred_update_user_meta( $user_id, 'verified_credly_member', '', array(
                                'status' => true,
                                'credly_member_id' => $response_data->data[0]->id
                            ) );
                        }
                        else {
                            $pending_badges = mycred_get_user_meta( $user_id, 'credly_pending_badges', '', true );

                            if ( ! empty( $pending_badges ) ) {
                                $pending_badges[] = $badge_id;
                            }
                            else {
                                $pending_badges = array( $badge_id );
                            }
                            
                            mycred_update_user_meta( $user_id, 'credly_pending_badges', '', $pending_badges );

                            return false;
                        }
                    }

                }

                $args = array(
                    'email' => $user_email,
                    'first_name' => $user_data->first_name,
                    'last_name' => $user_data->last_name,
                    'badge_id' => $credly_badge_id,
                    'evidence_file' => get_site_url()
                );
                
                $this->send_badge_to_credly( $args, $user_id, $badge_id );
            }

        }


        public function send_badge_to_credly( $args, $user_id, $badge_id ) {

            $url = 'https://api.credly.com/v1.1/member_badges';

            $args['access_token'] = $this->credly_auth['access_token'];

            $credly_response = wp_remote_post( $url, array(
                'headers' => array(
                    'X-Api-Key' => $this->credly_auth['key'],
                    'X-Api-Secret' => $this->credly_auth['secret'],
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => $args
            ) );
            
            $pending_badges = mycred_get_user_meta( $user_id, 'credly_pending_badges', '', true );

            $response_data = ! is_wp_error( $credly_response ) ? json_decode( $credly_response['body'] ) : '';

            if ( !is_wp_error( $credly_response ) && $response_data->meta->status_code == 200 ) {
                if( ! empty( $pending_badges ) ) {
                    $badge_id_key = array_search ( $args['badge_id'], $pending_badges );
                    unset( $pending_badges[ $badge_id_key ] );
                    $pending_badges = array_values( $pending_badges );
                }
            }
            else {

                if ( ! empty( $pending_badges ) ) {
                    $pending_badges[] = $badge_id;
                }
                else {
                    $pending_badges = array( $pending_badges );
                }
            }
            mycred_update_user_meta( $user_id, 'credly_pending_badges', '', $pending_badges );

        }

        public function filter_badge_shortcode( $output ) {

            $pending_badges = mycred_get_user_meta( get_current_user_id(), 'credly_pending_badges', '', true );
            
            $email_not_found = false;

            if( 
                ! empty( $_POST['credly_member_email'] ) && 
                ! empty( $_POST['credly_member_nounce'] ) &&
                wp_verify_nonce( $_POST['credly_member_nounce'], 'mycred_nounce_credly' ) &&

                is_email( $_POST['credly_member_email'] ) 
            ){
                $credly_email = sanitize_email( $_POST['credly_member_email'] );
                $credly_response = $this->get_credly_member( $credly_email );

                if ( ! is_wp_error( $credly_response ) ) {

                    $response_data = json_decode( $credly_response['body'] );
                    if ( $response_data->meta->status_code == 200 ) {

                        mycred_update_user_meta( get_current_user_id(), 'verified_credly_member', '', array(
                            'status' => true,
                            'credly_member_id' => $response_data->data[0]->id
                        ) );

                        mycred_update_user_meta( get_current_user_id(), 'user_credly_email', '', $credly_email );

                        if( ! empty( $pending_badges ) ) {
                            foreach ( $pending_badges as $key => $value) {
                                $this->assign_credly_badge( get_current_user_id(), $value, 0 );
                            }
                        }

                        $pending_badges = array();
                    }
                    else {
                        $email_not_found = true;   
                    }
                }
            }

            if( ! empty( $pending_badges ) && count( $pending_badges ) > 0 ) {

                $prefs_core = mycred_get_option('mycred_pref_core');

                if ( empty( $prefs_core['credly'] ) ) {
                    $prefs_core['credly']['not_connected_msg'] = 'Your account is not connected with Credly.';
                    $prefs_core['credly']['not_connected_email_label'] = 'Please Enter Your Credly Email';
                    $prefs_core['credly']['not_connected_btn_txt'] = 'Connect Credly';
                    $prefs_core['credly']['invalid_email_msg'] = 'Could not find your Credly Account';
                }
                
                $email_error_msg = '';
                
                if( $email_not_found === true ) {
                    $email_error_msg = '<p class="mycred-credly-email-msg">'.$prefs_core['credly']['invalid_email_msg'].'</p>';
                }

                $output = '<p>'.$prefs_core['credly']['not_connected_msg'].'</p>'.$email_error_msg.'
                <form method="post" action="#">
                    <label>'.$prefs_core['credly']['not_connected_email_label'].'</label>
                    <input type="email" name="credly_member_email"/>'.
                    wp_nonce_field( 'mycred_nounce_credly', 'credly_member_nounce' ).
                    '<button>'.$prefs_core['credly']['not_connected_btn_txt'].'</button>
                </form>' . $output;
            }
            return $output;
        }

        public function filter_my_badges_shortcode( $output, $user_id ) {

            return $this->filter_badge_shortcode( $output );
        }

        public function get_credly_member( $user_email ) {

            $member_url = 'https://api.credly.com/v1.1/members';

            return wp_remote_get( $member_url, array(
                'headers' => array(
                    'X-Api-Key' => $this->credly_auth['key'],
                    'X-Api-Secret' => $this->credly_auth['secret'],
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => array(
                    'access_token' => $this->credly_auth['access_token'],
                    'email' => $user_email
                )
            ) );
        }

        public function connect_existing_credly_badge( $which ) {
            global $typenow;

            if ( 'mycred_badge' === $typenow && 'top' === $which ) { 
                add_thickbox();
            ?>
                <div class="alignleft actions custom">
                    <button type="button" class="button" id="mycred_credly_connect_badge">
                        <?php echo __( 'Get Existing Credly Badge', 'mycred_credly' ); ?>
                        <img src="<?php echo home_url().'/wp-admin/images/wpspin_light.gif'; ?>">
                    </button>
                    <input type="hidden" id="mycred-credly-connect-badge-nonce" value="<?php echo wp_create_nonce( 'mycred_nounce_credly_badge_list' ); ?>">
                </div>
                <div id="mycred-credly-badge-modal" style="display:none;">
                    <div id="mycred-credly-badge-modal-wraper">
                        <h2>Connect Existing Credly Badge</h2>
                        <table>
                            <tr>
                                <td>            
                                    <select id="mycred-credly-badge-list"></select>
                                    <input type="hidden" id="mycred-credly-badge-list-nonce" value="<?php echo wp_create_nonce( 'mycred_nonce_credly_badge_list' ); ?>">
                                </td>
                                <td>
                                    <button type="button" id="get-mycred-credly_badge" class="button">
                                        <?php echo __( 'Connect Badge', 'mycred_credly' ); ?>
                                        <img src="<?php echo home_url().'/wp-admin/images/wpspin_light.gif'; ?>">
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php
            }
        }

        public function get_mycred_credly_badges_list() {

            $response = array( 'status' => 'failed' );

            if( ! empty( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'mycred_nounce_credly_badge_list' ) ) {

                if ( ! empty( $this->credly_auth ) ) {
                    $member_url = 'https://api.credly.com/v1.1/me/badges/created';

                    $credly_response = wp_remote_get( $member_url, array(
                        'headers' => array(
                            'X-Api-Key' => $this->credly_auth['key'],
                            'X-Api-Secret' => $this->credly_auth['secret'],
                            'Content-Type' => 'application/x-www-form-urlencoded'
                        ),
                        'body' => array(
                            'access_token' => $this->credly_auth['access_token'],
                            'page' => 1,
                            'per_page' => 999
                        )
                    ) );

                    if ( ! is_wp_error( $credly_response ) ) {

                        $response_data = json_decode( $credly_response['body'] );
                        if ( $response_data->meta->status_code == 200 ) {
                            $response['status'] = 'success';
                            $response['data'] = $response_data->data;
                        }
                    }
                }

            }
            wp_send_json( $response );
            wp_die();
        }


        public function connect_credly_badge() {

            $response = array( 'status' => 'failed' );

            if( ! empty( $_POST['badge_id'] ) &&
                ! empty( $_POST['badge_title'] ) &&
                ! empty( $_POST['badge_img'] ) &&
                wp_http_validate_url( $_POST['badge_img'] ) &&
                isset( $_POST['badge_desc'] ) &&
                ! empty( $_POST['nonce'] ) &&
                wp_verify_nonce( $_POST['nonce'], 'mycred_nonce_credly_badge_list' ) 
            ) {

                $badge_id    = intval( $_POST['badge_id'] );
                $badge_title = sanitize_text_field( $_POST['badge_title'] );
                $badge_img   = esc_url( $_POST['badge_img'] );
                $badge_desc  = sanitize_text_field( $_POST['badge_desc'] );

                if ( ! empty( $this->credly_auth ) ) {

                    $mycred_connected_credly_badges = mycred_get_option( 'mycred_connected_credly_badges', array() );

                    if ( ! in_array( $badge_id, $mycred_connected_credly_badges ) ) {
                        $badge_data = array(
                            'post_title'  => $badge_title,
                            'post_type'   => 'mycred_badge',
                            'meta_input'  => array(
                                'mycred_credly_badge_id' => $badge_id,
                                'mycred_credly_badge_description' => $badge_desc
                            )
                        );
                        $mycred_badge_id = wp_insert_post( $badge_data );
                        $attachment_id   = $this->media_sideload_image( $badge_img, $mycred_badge_id );
                        if ( ! is_wp_error( $attachment_id ) ) {
                            mycred_update_post_meta( $mycred_badge_id, 'main_image', $attachment_id );
                        }
                        array_push( $mycred_connected_credly_badges, $badge_id );
                        mycred_update_option( 'mycred_connected_credly_badges' , $mycred_connected_credly_badges );
                        $response['status'] = 'success';
                    }
                    else {
                        $response['message'] = __('Selected Badge is already connected.', 'mycred_credly');
                    }
                }

            }
            wp_send_json( $response );
            wp_die();
        }

        public function delete_badge_id( $post_id ) {

            if ( get_post_type( $post_id ) != MYCRED_BADGE_KEY ) return $post_id;

            $mycred_credly_badge_id = mycred_get_post_meta( $post_id, 'mycred_credly_badge_id', true );

            if( ! empty( $mycred_credly_badge_id ) ) {
                $mycred_connected_credly_badges = mycred_get_option( 'mycred_connected_credly_badges', array() );
                $badge_id_key = array_search ( $mycred_credly_badge_id, $mycred_connected_credly_badges );
                unset( $mycred_connected_credly_badges[ $badge_id_key ] );
                $mycred_connected_credly_badges = array_values( $mycred_connected_credly_badges );
                mycred_update_option( 'mycred_connected_credly_badges' , $mycred_connected_credly_badges );
            }

        }


    }
endif;

if ( ! function_exists( 'mycred_credly_badge' ) ) :
    function mycred_credly_badge() {
        return new myCRED_Credly_Badge();
    }
endif;

mycred_credly_badge();