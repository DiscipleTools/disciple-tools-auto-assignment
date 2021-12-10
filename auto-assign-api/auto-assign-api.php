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

        // Only auto assign if we have at least one weighted hit!
        if ( ! empty( $multipliers ) && ( count( $multipliers ) > 0 ) && ( $multipliers[0]['weight'] > 0 ) ) {
            DT_Posts::update_post( 'contacts', $contact['ID'], [ 'assigned_to' => 'user-' . $multipliers[0]['multiplier']['id'] ], false, false );
        }
    }

    private static function is_minor( $contact ): bool {
        return isset( $contact['age'] ) && $contact['age'] === '<19';
    }

    private static function is_enforced( $settings, $setting_id ): bool {
        return isset( $settings[ $setting_id ] ) && json_decode( $settings[ $setting_id ] ) === true;
    }

    private static function fetch_multipliers( $contact ): array {
        $multipliers    = [];
        $user_genders   = self::fetch_user_genders();
        $user_locations = self::fetch_user_locations( self::fetch_contact_location_ids( $contact ) );

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

                $multipliers[] = $u;
            }
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
        $ranked_multipliers = [];
        foreach ( $multipliers ?? [] as $multiplier ) {

            $weight = 0.0;

            if ( $enforce_gender ) {
                if ( isset( $multiplier['gender'], $contact['gender']['key'] ) ) {
                    if ( strtolower( trim( $multiplier['gender'] ) ) === strtolower( trim( $contact['gender']['key'] ) ) ) {
                        $weight += self::$weights_gender;
                    }
                }
            }

            if ( $enforce_location ) {
                if ( isset( $multiplier['best_location_match'], $contact['location_grid'] ) && is_array( $contact['location_grid'] ) ) {
                    if ( self::location_match( $multiplier['best_location_match'], $contact['location_grid'] ) ) {
                        $weight += self::$weights_location;
                    }
                }
            }

            if ( $enforce_language ) {
                if ( isset( $multiplier['languages'], $contact['languages'] ) && is_array( $multiplier['languages'] ) && is_array( $contact['languages'] ) ) {
                    if ( self::language_match( $multiplier['languages'], $contact['languages'] ) ) {
                        $weight += self::$weights_language;
                    }
                }
            }

            // Add to pre-sorted ranked array
            $ranked_multipliers[] = [
                'weight'     => $weight,
                'multiplier' => $multiplier
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

    private static function location_match( $key, $locations ): bool {
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

    private static function language_match( $multiplier_lang, $contact_lang ): bool {
        $matched = false;
        foreach ( $multiplier_lang ?? [] as $lang ) {
            if ( in_array( $lang, $contact_lang ) ) {
                $matched = true;
            }
        }

        return $matched;
    }
}
