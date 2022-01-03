<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Auto_Assignment_Workflows
 *
 * @since  1.11.0
 */
class Disciple_Tools_Auto_Assignment_Workflows {

    private static $custom_action_auto_assign_contacts = [
        'id'    => 'contacts_00001_custom_action_auto_assign',
        'label' => 'Auto-Assign Contacts'
    ];

    /**
     * Disciple_Tools_Auto_Assignment_Workflows The single instance of Disciple_Tools_Auto_Assignment_Workflows.
     *
     * @var    object
     * @access private
     * @since  1.11.0
     */
    private static $_instance = null;

    /**
     * Main Disciple_Tools_Auto_Assignment_Workflows Instance
     *
     * Ensures only one instance of Disciple_Tools_Auto_Assignment_Workflows is loaded or can be loaded.
     *
     * @return Disciple_Tools_Auto_Assignment_Workflows instance
     * @since  1.11.0
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Disciple_Tools_Auto_Assignment_Workflows constructor.
     */
    public function __construct() {
        add_filter( 'dt_workflows', [ $this, 'fetch_default_workflows_filter' ], 10, 2 );
        add_filter( 'dt_workflows_custom_actions', function ( $actions ) {
            $actions[] = (object) [
                'id'        => self::$custom_action_auto_assign_contacts['id'],
                'name'      => self::$custom_action_auto_assign_contacts['label'],
                'displayed' => true // Within admin workflow builder view?
            ];

            return $actions;
        }, 10, 1 );

        add_action( self::$custom_action_auto_assign_contacts['id'], [
            $this,
            'custom_action_auto_assign_contacts'
        ], 10, 3 );
    }

    public function fetch_default_workflows_filter( $workflows, $post_type ) {
        /*
         * Please ensure workflow ids are both static and unique; as they
         * will be used further downstream within admin view and execution handler.
         * Dynamically generated timestamps will not work, as they will regularly
         * change. Therefore, maybe a plugin id prefix, followed by post type and then a constant: E.g: starter_groups_00001
         *
         * Also, review /themes/disciple-tools-theme/dt-core/admin/js/dt-utilities-workflows.js;
         * so, as to determine which condition and action event types can be assigned to which field type!
         */

        switch ( $post_type ) {
            case 'contacts':
                $this->build_default_workflows_contacts( $workflows );
                break;
            case 'groups':
                $this->build_default_workflows_groups( $workflows );
                break;
        }

        return $workflows;
    }

    private function build_default_workflows_contacts( &$workflows ) {
        $dt_fields = DT_Posts::get_post_field_settings( 'contacts' );

        $workflows[] = (object) [
            'id'         => 'contacts_00001',
            'name'       => self::$custom_action_auto_assign_contacts['label'],
            'enabled'    => true, // Can be enabled/disabled via admin view
            'trigger'    => Disciple_Tools_Workflows_Defaults::$trigger_created['id'],
            'conditions' => [
                Disciple_Tools_Workflows_Defaults::new_condition( Disciple_Tools_Workflows_Defaults::$condition_is_set,
                    [
                        'id'    => 'sources',
                        'label' => $dt_fields['sources']['name']
                    ], [
                        'id'    => '',
                        'label' => ''
                    ]
                )
            ],
            'actions'    => [
                Disciple_Tools_Workflows_Defaults::new_action( Disciple_Tools_Workflows_Defaults::$action_custom,
                    [
                        'id'    => 'assigned_to', // Field to be updated or an arbitrary selection!
                        'label' => $dt_fields['assigned_to']['name']
                    ], [
                        'id'    => self::$custom_action_auto_assign_contacts['id'], // Action Hook
                        'label' => self::$custom_action_auto_assign_contacts['label']
                    ]
                )
            ]
        ];
    }

    private function build_default_workflows_groups( &$workflows ) {
    }

    /**
     * Workflow custom action self-contained function to handle following
     * use case:
     *
     * Auto-assigning of new contacts to the relevant multiplier, based on
     * location, age, gender and/or language.
     *
     * @param post
     * @param field
     * @param value
     *
     * @access public
     * @since  1.11.0
     */
    public function custom_action_auto_assign_contacts( $post, $field, $value ) {
        if ( ! empty( $post ) && isset( $post['sources'] ) && self::sources_match( $post['sources'] ) ) {
            Disciple_Tools_Auto_Assignment_API::auto_assign( $post );
        }
    }

    private function sources_match( $post_sources ): bool {
        // Automated agents only!
        if ( get_current_user_id() !== 0 ) {
            return false;
        }

        // All sources to be supported by default?
        $settings_config = json_decode( Disciple_Tools_Auto_Assignment_API::fetch_option( Disciple_Tools_Auto_Assignment_API::$option_dt_auto_assign_general_settings ), true );
        if ( ! empty( $settings_config ) && isset( $settings_config['support_all_sources'] ) && json_decode( $settings_config['support_all_sources'] ) === true ) {
            return true;
        }

        // Determine which sources are to be supported?
        $matched = false;
        if ( ! empty( $settings_config ) ) {
            foreach ( $post_sources ?? [] as $post_source ) {
                foreach ( $settings_config['sources'] ?? [] as $config_source ) {
                    if ( strtolower( trim( $post_source ) ) === strtolower( trim( $config_source['key'] ) ) ) {
                        $matched = true;
                    }
                }
            }
        }

        return $matched;
    }
}

Disciple_Tools_Auto_Assignment_Workflows::instance();
