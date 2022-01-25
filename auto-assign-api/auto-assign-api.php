<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Auto_Assignment_API
 */
class Disciple_Tools_Auto_Assignment_API {

    public static $option_dt_auto_assign_general_settings = 'dt_auto_assign_general_settings';

    public static $weights_gender = 1.0;
    public static $weights_location = 1.0;
    public static $weights_language = 1.0;
    public static $weights_user_status = 1.0;
    public static $weights_workload_status = 1.0;

    public static function fetch_endpoint_save_options_url(): string {
        return trailingslashit( site_url() ) . 'wp-json/disciple_tools_auto_assignment/v1/save';
    }

    public static function fetch_endpoint_load_options_url(): string {
        return trailingslashit( site_url() ) . 'wp-json/disciple_tools_auto_assignment/v1/load';
    }

    public static function fetch_option( $option ) {
        return get_option( $option );
    }

    public static function update_option( $option, $value ) {
        update_option( $option, $value );
    }

    public static function option_exists( $option ): bool {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

        return is_object( $row );
    }

    public static function auto_assign( $contact ) {
        $settings = json_decode( self::fetch_option( self::$option_dt_auto_assign_general_settings ), true );
        if ( empty( $settings ) ) {
            return;
        }

        // Exit process, if auto assignment is disabled for minors
        if ( self::is_minor( $contact ) && isset( $settings['auto_assign_minors'] ) && ! json_decode( $settings['auto_assign_minors'] ) ) {
            return;
        }

        // Determine various enforcement states
        $enforce_gender   = self::is_enforced( $settings, 'enforce_gender' );
        $enforce_location = self::is_enforced( $settings, 'enforce_location' );
        $enforce_language = self::is_enforced( $settings, 'enforce_language' );

        // Fetch relevant multipliers
        $multipliers = self::apply_weights( $contact, self::fetch_multipliers( $contact ), $enforce_gender, $enforce_location, $enforce_language );

        // Only auto assign if we have a suitable auto-assignment candidate!
        $assigned_multiplier = self::fetch_assigned_multiplier( $multipliers );
        if ( ! empty( $assigned_multiplier ) ) {
            DT_Posts::update_post( 'contacts', $contact['ID'], [ 'assigned_to' => 'user-' . $assigned_multiplier['id'] ], false, false );
        }
    }

    private static function is_minor( $contact ): bool {
        return isset( $contact['age'] ) && $contact['age'] === '<19';
    }

    private static function is_enforced( $settings, $setting_id ): bool {
        return isset( $settings[ $setting_id ] ) && json_decode( $settings[ $setting_id ] ) === true;
    }

    private static function fetch_assigned_multiplier( $multipliers ) {
        foreach ( $multipliers ?? [] as $multiplier ) {

            // Both weighting and enforcements must be acceptable, in order to be a suitable auto-assignment candidate!
            if ( ( $multiplier['weight'] > 0 ) && ( $multiplier['all_enforced'] === true ) ) {
                return $multiplier['multiplier'];
            }
        }

        return null;
    }

    private static function fetch_multipliers( $contact ): array {
        $multipliers    = [];
        $user_genders   = self::fetch_user_genders();
        $user_locations = self::fetch_user_locations( self::fetch_contact_location_ids( $contact ) );

        // Obtain handle to current user.
        $current_user = wp_get_current_user();

        // Ensure automated agents have sufficient permissions.
        if ( $current_user->ID === 0 ) {
            $current_user->add_cap( "list_users" );
        }

        foreach ( DT_User_Management::get_users( true ) ?? [] as $user ) {
            $roles = maybe_unserialize( $user['roles'] );
            if ( isset( $roles['multiplier'] ) ) {

                $u = [
                    'id'    => $user['ID'],
                    'name'  => wp_specialchars_decode( $user['display_name'] ),
                    'roles' => array_keys( $roles )
                ];

                // Gender
                $u['gender'] = $user_genders[ $user['ID'] ] ?? null;

                // Locations
                $u['location']            = $user_locations[ $user['ID'] ]['level'] ?? null;
                $u['best_location_match'] = $user_locations[ $user['ID'] ]['match_name'] ?? null;

                // Languages
                $u['languages'] = self::fetch_user_languages( $user['ID'] ) ?? [];

                // Assigned To Count
                $u['assigned_to_count'] = isset( $user['number_assigned_to'] ) ? intval( $user['number_assigned_to'] ) : 0;

                // Update Needed Count
                $u['update_needed_count'] = self::fetch_user_update_needed_count( $user['ID'] );

                // User Status
                $u['user_status'] = $user['user_status'] ?? '';

                // Workload Status
                $u['workload_status'] = $user['workload_status'] ?? '';

                // Last Activity
                $u['last_activity'] = $user['last_activity'] ?? '';

                $multipliers[] = $u;
            }
        }

        // Ensure to remove any previously assigned permissions.
        if ( $current_user->ID === 0 ) {
            $current_user->remove_cap( "list_users" );
        }

        return $multipliers;
    }

    private static function fetch_contact_location_ids( $contact ): array {
        $location_ids = [];
        foreach ( $contact['location_grid'] ?? [] as $grid ) {
            if ( isset( $grid['id'] ) ) {
                $location_ids[] = $grid['id'];
            }
        }

        return $location_ids;
    }

    private static function fetch_user_update_needed_count( $user_id ): int {
        global $wpdb;

        $update_needed_value = $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(update_needed.post_id) as count
        FROM $wpdb->postmeta pm
        INNER JOIN $wpdb->postmeta as update_needed on (update_needed.post_id = pm.post_id and update_needed.meta_key = 'requires_update' and update_needed.meta_value = '1' )
        WHERE pm.meta_key = 'assigned_to' and pm.meta_value IN ( %s )
        GROUP BY pm.meta_value
        LIMIT 1", 'user-' . $user_id ) );

        return ! empty( $update_needed_value ) ? intval( $update_needed_value ) : 0;
    }

    private static function fetch_user_genders(): array {
        global $wpdb;
        $gender_data = [];

        $gender_query = $wpdb->get_results( $wpdb->prepare( "
            SELECT user_id, meta_value as gender
            from $wpdb->usermeta
            WHERE meta_key = %s", "{$wpdb->prefix}user_gender" ), ARRAY_A );

        foreach ( $gender_query as $data ) {
            $gender_data[ $data["user_id"] ] = $data["gender"];
        }

        return $gender_data;
    }

    private static function fetch_user_locations( $location_ids ): array {
        global $wpdb;

        $location_data = [];
        if ( isset( $location_ids ) ) {
            foreach ( $location_ids as $grid_id ) {
                $location = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->dt_location_grid WHERE grid_id = %s", esc_sql( $grid_id ) ), ARRAY_A );
                $levels   = [];

                if ( $grid_id === "1" ) {
                    $match_location_ids = "( 1 )";
                } else {
                    $match_location_ids = "( ";
                    for ( $i = 0; $i <= ( (int) $location["level"] ); $i ++ ) {
                        $levels[ $location[ "admin" . $i . "_grid_id" ] ] = [ "level" => $i ];
                        $match_location_ids                               .= $location[ "admin" . $i . "_grid_id" ] . ', ';
                    }
                    $match_location_ids .= ')';

                }

                $match_location_ids = str_replace( ', )', ' )', $match_location_ids );
                //phpcs:disable
                //already sanitized IN value
                $location_names = $wpdb->get_results( "
                    SELECT alt_name, grid_id
                    FROM $wpdb->dt_location_grid
                    WHERE grid_id IN $match_location_ids
                ", ARRAY_A );

                //get users with the same location grid.
                $users_in_location = $wpdb->get_results( $wpdb->prepare( "
                    SELECT user_id, meta_value as grid_id
                    FROM $wpdb->usermeta um
                    WHERE um.meta_key = %s
                    AND um.meta_value IN $match_location_ids
                ", "{$wpdb->prefix}location_grid" ), ARRAY_A );
                //phpcs:enable

                foreach ( $location_names as $l ) {
                    if ( isset( $levels[ $l["grid_id"] ] ) ) {
                        $levels[ $l["grid_id"] ]["name"] = $l["alt_name"];
                    }
                }

                //0 if the location is exact match. 1 if the matched location is the parent etc
                foreach ( $users_in_location as $l ) {
                    $level = (int) $location["level"] - $levels[ $l["grid_id"] ]["level"];
                    if ( ! isset( $location_data[ $l["user_id"] ] ) || $location_data[ $l["user_id"] ]["level"] > $level ) {
                        $location_data[ $l["user_id"] ] = [
                            "level"      => $level,
                            "match_name" => $levels[ $l["grid_id"] ]["name"]
                        ];
                    }
                }
            }
        }

        return $location_data;
    }

    private static function fetch_user_languages( $user_id ) {
        return get_user_option( "user_languages", $user_id ) ?? [];
    }

    private static function apply_weights( $contact, $multipliers, $enforce_gender, $enforce_location, $enforce_language ): array {
        $ranked_multipliers          = [];
        $ranked_assigned_to_counts   = self::rank_counts( $multipliers, 'assigned_to_count' );
        $ranked_update_needed_counts = self::rank_counts( $multipliers, 'update_needed_count' );

        foreach ( $multipliers ?? [] as $multiplier ) {

            $weight       = 0.0;
            $enforcements = [];

            // Gender
            if ( $enforce_gender ) {
                $gender_enforced = false;
                if ( isset( $multiplier['gender'], $contact['gender']['key'] ) ) {
                    if ( strtolower( trim( $multiplier['gender'] ) ) === strtolower( trim( $contact['gender']['key'] ) ) ) {
                        $weight          += self::$weights_gender;
                        $gender_enforced = true;
                    }
                }
                $enforcements['gender'] = $gender_enforced;
            }

            // Locations
            if ( $enforce_location ) {
                $location_enforced = false;
                if ( isset( $multiplier['best_location_match'], $contact['location_grid'] ) && is_array( $contact['location_grid'] ) ) {
                    if ( self::location_match( $multiplier['best_location_match'], $contact['location_grid'] ) ) {
                        $weight            += self::$weights_location;
                        $location_enforced = true;
                    }
                }
                $enforcements['location'] = $location_enforced;
            }

            // Languages
            if ( $enforce_language ) {
                $language_enforced = false;
                if ( isset( $multiplier['languages'], $contact['languages'] ) && is_array( $multiplier['languages'] ) && is_array( $contact['languages'] ) ) {
                    if ( self::language_match( $multiplier['languages'], $contact['languages'] ) ) {
                        $weight            += self::$weights_language;
                        $language_enforced = true;
                    }
                }
                $enforcements['language'] = $language_enforced;
            }

            // Assigned To Count
            if ( isset( $multiplier['assigned_to_count'] ) ) {
                $weight += self::apply_weights_for_counts( $multiplier['assigned_to_count'], $ranked_assigned_to_counts );
            }

            // Update Needed Count
            if ( isset( $multiplier['update_needed_count'] ) ) {
                $weight += self::apply_weights_for_counts( $multiplier['update_needed_count'], $ranked_update_needed_counts );
            }

            // User Status
            $weight += self::apply_weights_for_status( $multiplier['user_status'], [ 'active' ], self::$weights_user_status );

            // Workload Status
            $weight += self::apply_weights_for_status( $multiplier['workload_status'], [ 'active' ], self::$weights_workload_status );

            // Last Activity
            $weight += self::apply_weights_for_last_activity( $multiplier['last_activity'] );

            // Add to pre-sorted ranked array
            $ranked_multipliers[] = [
                'weight'       => $weight,
                'all_enforced' => self::all_enforcements_satisfied( $enforcements ),
                'multiplier'   => $multiplier
            ];
        }

        // Sort ranked array by weight score
        usort( $ranked_multipliers, function ( $a, $b ) {
            if ( $a['weight'] === $b['weight'] ) {
                return 0;

            } else {
                return ( $a['weight'] > $b['weight'] ) ? - 1 : 1;
            }
        } );

        return $ranked_multipliers;
    }

    public static function rank_counts( $multipliers, $key ): array {
        $ranked_counts = [];
        foreach ( $multipliers ?? [] as $multiplier ) {
            if ( isset( $multiplier[ $key ] ) ) {
                $ranked_counts[] = $multiplier[ $key ];
            }
        }

        // Sort ranked array by descending count values
        usort( $ranked_counts, function ( $a, $b ) {
            if ( $a === $b ) {
                return 0;

            } else {
                return ( $a > $b ) ? - 1 : 1;
            }
        } );

        return $ranked_counts;
    }

    public static function location_match( $key, $locations ): bool {
        $matched = false;
        foreach ( $locations ?? [] as $location ) {
            if ( isset( $location['label'] ) ) {
                if ( strtolower( trim( $key ) ) === strtolower( trim( $location['label'] ) ) ) {
                    $matched = true;
                }
            }
        }

        return $matched;
    }

    public static function language_match( $multiplier_lang, $contact_lang ): bool {
        $matched = false;
        foreach ( $multiplier_lang ?? [] as $lang ) {
            if ( in_array( $lang, $contact_lang ) ) {
                $matched = true;
            }
        }

        return $matched;
    }

    public static function apply_weights_for_counts( $count, $ranked_counts ): float {
        $idx = array_search( $count, $ranked_counts );

        if ( $idx === false ) {
            return 0.0;
        }

        // Normalize....
        $min = 1;
        $max = count( $ranked_counts );
        $val = $idx + 1; // Ignore zero array index offset!

        $weight = ( $val - $min ) / ( $max - $min );

        return $weight;
    }

    public static function apply_weights_for_status( $status, $keys, $weight ): int {
        return ( empty( $status ) || in_array( strtolower( trim( $status ) ), $keys ) ) ? $weight : 0;
    }

    private static function apply_weights_for_last_activity( $last_activity ): float {
        if ( empty( $last_activity ) || intval( $last_activity ) === 0 ) {
            return 0.0;
        }

        $elapsed_days = ( time() - intval( $last_activity ) ) / 86400; // secs-in-day

        // Normalize....
        $min = 0; // min-days
        $max = 365; // max-days

        $normalized = ( $elapsed_days - $min ) / ( $max - $min );
        $weight     = 1 - $normalized; // fewer days to have larger weights!

        return $weight;
    }

    private static function all_enforcements_satisfied( $enforcements ): bool {
        if ( empty( $enforcements ) ) {
            return false;
        }

        $all_satisfied = true;
        foreach ( $enforcements as $key => $enforced ) {
            if ( $enforced === false ) {
                $all_satisfied = false;
            }
        }

        return $all_satisfied;
    }
}
