<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Auto_Assignment_Tab_General
 */
class Disciple_Tools_Auto_Assignment_Tab_General {

    public function __construct() {

        // Load scripts and styles
        $this->process_scripts();

    }

    private function process_scripts() {
        wp_enqueue_script( 'dt_auto_assign_general_script', plugin_dir_url( __FILE__ ) . 'js/general-tab.js', [
            'jquery',
            'lodash'
        ], filemtime( dirname( __FILE__ ) . '/js/general-tab.js' ), true );

        wp_localize_script(
            "dt_auto_assign_general_script", "dt_auto_assign", array(
                'dt_endpoint_nonce' => wp_create_nonce( 'wp_rest' ),
                'dt_endpoint_load'  => Disciple_Tools_Auto_Assignment_API::fetch_endpoint_load_options_url(),
                'dt_endpoint_save'  => Disciple_Tools_Auto_Assignment_API::fetch_endpoint_save_options_url()
            )
        );
    }

    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Settings</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_settings(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->

        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>
                    Sources [<a href="#" class="auto-assign-docs"
                                data-title="aa_right_docs_sources_title"
                                data-content="aa_right_docs_sources_content">&#63;</a>]
                </th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_sources(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <span style="float:left; display: none; font-weight: bold;" id="aa_general_main_col_msg"></span>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table style="display: none;" id="aa_right_docs_section" class="widefat striped">
            <thead>
            <tr>
                <th id="aa_right_docs_title"></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td id="aa_right_docs_content"></td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php

        // Include helper documentation
        include 'general-tab-docs.php';
    }

    private function main_column_settings() {
        ?>
        <table class="widefat striped">
            <tr>
                <td style="vertical-align: middle;">
                    Auto Assign Minors? [<a href="#" class="auto-assign-docs"
                                            data-title="aa_right_docs_auto_assign_minors_title"
                                            data-content="aa_right_docs_auto_assign_minors_content">&#63;</a>]
                </td>
                <td>
                    <input type="checkbox" id="aa_general_main_col_settings_auto_assign_minors"/>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: middle;">
                    Enforce Match On Gender? [<a href="#" class="auto-assign-docs"
                                                 data-title="aa_right_docs_enforce_gender_title"
                                                 data-content="aa_right_docs_enforce_gender_content">&#63;</a>]
                </td>
                <td>
                    <input type="checkbox" id="aa_general_main_col_settings_enforce_match_on_gender"/>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: middle;">
                    Enforce Match On Location? [<a href="#" class="auto-assign-docs"
                                                   data-title="aa_right_docs_enforce_location_title"
                                                   data-content="aa_right_docs_enforce_location_content">&#63;</a>]
                </td>
                <td>
                    <input type="checkbox" id="aa_general_main_col_settings_enforce_match_on_location"/>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: middle;">
                    Enforce Match On Language? [<a href="#" class="auto-assign-docs"
                                                   data-title="aa_right_docs_enforce_language_title"
                                                   data-content="aa_right_docs_enforce_language_content">&#63;</a>]
                </td>
                <td>
                    <input type="checkbox" id="aa_general_main_col_settings_enforce_match_on_language"/>
                </td>
            </tr>
        </table>

        <br>
        <span style="float:right;">
            <button type="submit" id="aa_general_main_col_settings_update_but"
                    class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></button>
        </span>

        <?php
    }

    private function main_column_sources() {
        ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th>
                    <select style="min-width: 90%;" id="aa_general_main_col_sources_current_list_select">
                        <option disabled selected value>-- select sources to be processed --</option>
                        <option value="all">All Sources</option>

                        <?php
                        $field_settings = DT_Posts::get_post_field_settings( 'contacts' );
                        foreach ( $field_settings['sources']['default'] ?? [] as $source ) {
                            echo '<option value="' . esc_attr( $source['key'] ) . '">' . esc_attr( $source['label'] ) . '</option>';
                        }
                        ?>

                    </select>
                </th>
                <th>
                    <span style="float:right;">
                        <button id="aa_general_main_col_sources_current_list_select_add" type="submit"
                                class="button float-right"><?php esc_html_e( "Add", 'disciple_tools' ) ?></button>
                    </span>
                </th>
            </tr>
            </thead>
        </table>
        <br>

        <table class="widefat striped" id="aa_general_main_col_sources_table">
            <thead>
            <tr>
                <th>Key</th>
                <th>Label</th>
                <th></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        <br>

        <span style="float:right;">
            <button type="submit" id="aa_general_main_col_sources_update_but"
                    class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></button>
        </span>

        <?php
    }
}
