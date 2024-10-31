<?php
if ( ! defined( 'MYCRED_CREDLY_SLUG' ) ) exit;

if ( ! class_exists('myCRED_Credly_Setting') ):
	class myCRED_Credly_Setting {

        protected $api_auth_url = 'https://api.credly.com/v1.1/authenticate';

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

            add_action( 'mycred_after_core_prefs',         array( $this, 'after_general_settings') );
            add_filter( 'mycred_save_core_prefs',          array( $this, 'save_credly_settings' ), 90, 3 );
            add_action( 'wp_ajax_mycred_credly_authorize', array( $this, 'credly_authenticate') );

		}

        /*
         * add credly api general settings
         */

        public function after_general_settings( $mycred = NULL ) {

            $credly_key = '';
            $credly_access_token = '';

            $prefs = mycred_get_option('mycred_credly_auth');

            if ( ! empty( $prefs ) ) {
                $credly_key          = ! empty( $prefs['key'] ) ? $prefs['key'] : '';
                $credly_access_token = ! empty( $prefs['access_token'] ) ? $prefs['access_token'] : '';
            }
            ?>
            <h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e('Credly Badges Integration', 'mycred_credly'); ?></h4>
            <div class="body" style="display:none;">
                <?php 
                if ( $credly_access_token != '' ): 
                    $prefs_core = mycred_get_option('mycred_pref_core');
                    $msg = ! empty( $prefs_core['credly']['not_connected_msg'] ) ? $prefs_core['credly']['not_connected_msg'] : 'Your account is not connected with Credly.';
                    $email_label = !empty( $prefs_core['credly']['not_connected_email_label'] ) ? $prefs_core['credly']['not_connected_email_label'] : 'Please Enter Your Credly Email';
                    $btn_txt = ! empty( $prefs_core['credly']['not_connected_btn_txt'] ) ? $prefs_core['credly']['not_connected_btn_txt'] : 'Connect Credly';
                    $invalid_email_msg = ! empty( $prefs_core['credly']['invalid_email_msg'] ) ? $prefs_core['credly']['invalid_email_msg'] : 'Could not find your Credly Account.';
                ?>
                <div class="row">
                    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="credly_key"><?php _e('API Key', 'mycred_credly'); ?></label>
                            <input type="text" class="large-text" id="credly_key" value="<?php echo esc_attr( $credly_key );?>" disabled />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="mycred_pref_core[credly][not_connected_msg]">
                                <?php _e('Message for not connected account', 'mycred_credly'); ?>
                            </label>
                            <input type="text" name="mycred_pref_core[credly][not_connected_msg]" id="mycred_pref_core[credly][not_connected_msg]" value="<?php echo $msg; ?>" class="large-text"/>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="mycred_pref_core[credly][not_connected_email_label]"><?php _e("Credly's Email Label", 'mycred_credly'); ?></label>
                            <input type="text" name="mycred_pref_core[credly][not_connected_email_label]" id="mycred_pref_core[credly][not_connected_email_label]" class="large-text" value="<?php echo $email_label; ?>"/>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="mycred_pref_core[credly][not_connected_btn_txt]"><?php _e('Button Text', 'mycred_credly'); ?></label>
                            <input type="text" name="mycred_pref_core[credly][not_connected_btn_txt]" id="mycred_pref_core[credly][not_connected_btn_txt]" class="large-text" value="<?php echo $btn_txt; ?>"/>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="mycred_pref_core[credly][invalid_email_msg]">
                                <?php _e('Message for invalid email', 'mycred_credly'); ?>
                            </label>
                            <input type="text" name="mycred_pref_core[credly][invalid_email_msg]" id="mycred_pref_core[credly][invalid_email_msg]" value="<?php echo $invalid_email_msg; ?>" class="large-text"/>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ( $credly_access_token == '' ): ?>
                <div id="mycred_credly_authorization_container">
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <label for="mycred_credly_key"><?php _e('API Key', 'mycred_credly'); ?></label>
                                <input type="text" id="mycred_credly_key"/>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <label for="mycred_credly_secret"><?php _e('API Secret', 'mycred_credly'); ?></label>
                                <input type="password" id="mycred_credly_secret"/>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="mycred-credly-credentials">
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <label for="mycred_credly_email"><?php _e('Credly Email', 'mycred_credly'); ?></label>
                                <input type="email" id="mycred_credly_email"/>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <label for="mycred_credly_password"><?php _e('Credly Password', 'mycred_credly'); ?></label>
                                <input type="password" id="mycred_credly_password"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <div id="mycred_credly_setting_notice"></div>
                            <button type="button" id="mycred_credly_authorize" class="button button-primary button-large">
                                <?php _e('Authorize', 'mycred_credly'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }

        public function save_credly_settings( $prefs, $post, $general ) {

            $prefs['credly']['not_connected_msg'] = sanitize_text_field( $post['credly']['not_connected_msg'] );
            $prefs['credly']['not_connected_email_label'] = sanitize_text_field( $post['credly']['not_connected_email_label'] );
            $prefs['credly']['not_connected_btn_txt'] = sanitize_text_field( $post['credly']['not_connected_btn_txt'] );
            $prefs['credly']['invalid_email_msg'] = sanitize_text_field( $post['credly']['invalid_email_msg'] );

            return $prefs;
        }

        /*
         *  authentication on credly
         */

        public function credly_authenticate() {

            $credly_key = sanitize_text_field( $_POST['mycred_credly_key'] );
            $credly_secret = sanitize_text_field( $_POST['mycred_credly_secret'] );
            $credly_auth = sanitize_text_field( $_POST['mycred_credly_auth'] );


            $credly_response = wp_remote_post( $this->api_auth_url, array(
                'headers' => array(
                    'X-Api-Key' => $credly_key,
                    'X-Api-Secret' => $credly_secret,
                    'Authorization' => 'Basic ' . $credly_auth,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'sslverify' => false
            ) );

            $response = array();

            if ( is_wp_error( $credly_response ) ) {

                $response['status'] = 'error';
                $response['message'] = __( 'Error: Something went wrong.', 'mycred_credly' );
            }
            else {

                $response_data = json_decode( $credly_response['body'] );
                
                if ( ! empty( $response_data->meta->status_code ) && $response_data->meta->status_code == 200 ) {

                    mycred_update_option( 'mycred_credly_auth', array(
                        'key' => $credly_key,
                        'secret' => $credly_secret,
                        'access_token' => $response_data->data->token,
                        'refresh_token' => $response_data->data->refresh_token
                    ) );

                    $response['status'] = 'success';
                    $response['message'] = __( 'Authentication success.' );
                    $response['api_key'] = $credly_key;

                } 
                else {

                    $response['status'] = 'error';
                    $response['message'] = sprintf( __( 'Error: %s', 'mycred_credly' ), $response_data->meta->message );
                }
            }
            echo json_encode( $response );
            wp_die();
        }

    }
endif;

if ( ! function_exists( 'mycred_credly_settings' ) ) :
	function mycred_credly_settings() {
		return new myCRED_Credly_Setting();
	}
endif;

mycred_credly_settings();