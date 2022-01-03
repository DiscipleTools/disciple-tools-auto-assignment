<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

add_filter( 'dt_get_dispatch_list', 'dt_get_dispatch_list', 10, 3 );
function dt_get_dispatch_list( $list, $post_type, $post_id ): array {
    $users = [];
    $post = DT_Posts::get_post( $post_type, $post_id );

    if ( ! empty( $list ) && ! empty( $post ) && ! is_wp_error( $post ) ) {
        $ranked_update_needed_counts = Disciple_Tools_Auto_Assignment_API::rank_counts( $list, 'update_needed' );

        // Irrespective of user role, apply weights to all/any users matching assignment criteria.
        foreach ( $list as $user ) {

            $weight = 0.0;

            // Gender
            if ( isset( $user['gender'], $post['gender']['key'] ) ) {
                if ( strtolower( trim( $user['gender'] ) ) === strtolower( trim( $post['gender']['key'] ) ) ) {
                    $weight += Disciple_Tools_Auto_Assignment_API::$weights_gender;
                }
            }

            // Locations
            if ( isset( $user['best_location_match'], $post['location_grid'] ) && is_array( $post['location_grid'] ) ) {
                if ( Disciple_Tools_Auto_Assignment_API::location_match( $user['best_location_match'], $post['location_grid'] ) ) {
                    $weight += Disciple_Tools_Auto_Assignment_API::$weights_location;
                }
            }

            // Languages
            if ( isset( $user['languages'], $post['languages'] ) && is_array( $user['languages'] ) && is_array( $post['languages'] ) ) {
                if ( Disciple_Tools_Auto_Assignment_API::language_match( $user['languages'], $post['languages'] ) ) {
                    $weight += Disciple_Tools_Auto_Assignment_API::$weights_language;
                }
            }

            // Update Needed Count
            if ( isset( $user['update_needed'] ) ) {
                $weight += Disciple_Tools_Auto_Assignment_API::apply_weights_for_counts( $user['update_needed'], $ranked_update_needed_counts );
            }

            // Status
            $weight += Disciple_Tools_Auto_Assignment_API::apply_weights_for_status( $user['status'] ?? null, [ 'active' ], Disciple_Tools_Auto_Assignment_API::$weights_workload_status );

            // Update user weighting
            $user['weight'] = $weight;
            $users[]        = $user;
        }

        // Sort ranked array by weight score
        usort( $users, function ( $a, $b ) {
            if ( ! isset( $a['weight'], $b['weight'] ) || $a['weight'] === $b['weight'] ) {
                return 0;

            } else {
                return ( $a['weight'] > $b['weight'] ) ? - 1 : 1;
            }
        } );
    }

    return ( ! empty( $users ) ) ? $users : $list;
}
