<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class Disciple_Tools_Auto_Assignment_Endpoints {
    /**
     * @todo Set the permissions your endpoint needs
     * @link https://github.com/DiscipleTools/Documentation/blob/master/theme-core/capabilities.md
     * @var string[]
     */
    public $permissions = [ 'manage_dt' ];

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function has_permission() {
        $pass = false;
        foreach ( $this->permissions as $permission ) {
            if ( current_user_can( $permission ) ) {
                $pass = true;
            }
        }

        return $pass;
    }

    /**
     * @todo define the name of the $namespace
     * @todo define the name of the rest route
     * @todo defne method (CREATABLE, READABLE)
     * @todo apply permission strategy. '__return_true' essentially skips the permission check.
     */
    //See https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
    public function add_api_routes() {
        $namespace = 'disciple_tools_auto_assignment/v1';

        register_rest_route(
            $namespace, '/load', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'load' ],
                'permission_callback' => function ( WP_REST_Request $request ) {
                    return $this->has_permission();
                },
            ]
        );
        register_rest_route(
            $namespace, '/save', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save' ],
                'permission_callback' => function ( WP_REST_Request $request ) {
                    return $this->has_permission();
                },
            ]
        );
    }

    public function load( WP_REST_Request $request ): array {

        // Prepare response payload
        $response = [];

        $params = $request->get_params();
        if ( isset( $params['action'] ) ) {

            // Execute accordingly, based on specified action
            switch ( $params['action'] ) {
                case 'general':
                    $response['has_settings'] = Disciple_Tools_Auto_Assignment_API::option_exists( Disciple_Tools_Auto_Assignment_API::$option_dt_auto_assign_general_settings );
                    $response['settings']     = Disciple_Tools_Auto_Assignment_API::fetch_option( Disciple_Tools_Auto_Assignment_API::$option_dt_auto_assign_general_settings );

                    $response['success'] = true;
                    break;
                default:
                    $response['success'] = false;
                    break;
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'Unable to execute action, due to missing parameters.';
        }

        return $response;
    }

    public function save( WP_REST_Request $request ): array {

        // Prepare response payload
        $response = [];

        $params = $request->get_params();
        if ( isset( $params['action'], $params['data'] ) ) {

            // Execute accordingly, based on specified action
            switch ( $params['action'] ) {
                case 'general':
                    Disciple_Tools_Auto_Assignment_API::update_option( Disciple_Tools_Auto_Assignment_API::$option_dt_auto_assign_general_settings, json_encode( $params['data'] ) );
                    break;
                default:
                    break;
            }
            $response['success'] = true;

        } else {
            $response['success'] = false;
            $response['message'] = 'Unable to execute action, due to missing parameters.';
        }

        return $response;
    }
}

Disciple_Tools_Auto_Assignment_Endpoints::instance();
