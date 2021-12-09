<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Test that DT_Module_Base has loaded
 */
if ( ! class_exists( 'DT_Module_Base' ) ) {
    dt_write_log( 'Disciple.Tools System not loaded. Cannot load custom post type.' );
    return;
}

/**
 * Add any modules required or added for the post type
 */
add_filter( 'dt_post_type_modules', function( $modules ){

    /**
     * @todo Update the starter in the array below 'starter_base'. Follow the pattern.
     * @todo Add more modules by adding a new array element. i.e. 'starter_base_two'.
     */
    $modules["auto_assign_base"] = [
        "name" => "Auto Assignment",
        "enabled" => false,
        "locked" => true,
        "prerequisites" => [ "contacts_base" ],
        "post_type" => "auto_assign_post_type",
        "description" => "Default auto assignment functionality"
    ];

    return $modules;
}, 20, 1 );

require_once 'module-base.php';
Disciple_Tools_Auto_Assignment_Base::instance();

/**
 * @todo require_once and load additional modules
 */
