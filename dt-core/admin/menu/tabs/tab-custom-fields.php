<?php

/**
 * Disciple Tools
 *
 * @class      Disciple_Tools_Tab_Custom_Fields
 * @version    0.1.0
 * @since      0.1.0
 * @package    Disciple.Tools
 * @author     Disciple.Tools
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Disciple_Tools_Tab_Custom_Fields
 */
class Disciple_Tools_Tab_Custom_Fields extends Disciple_Tools_Abstract_Menu_Base
{

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    /**
     * Constructor function.
     *
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_submenu' ], 99 );
        add_action( 'dt_settings_tab_menu', [ $this, 'add_tab' ], 10, 1 );
        add_action( 'dt_settings_tab_content', [ $this, 'content' ], 99, 1 );

        parent::__construct();
    } // End __construct()

    public function add_submenu() {
        add_submenu_page( 'dt_options', __( 'Fields', 'disciple_tools' ), __( 'Fields', 'disciple_tools' ), 'manage_dt', 'dt_options&tab=custom-fields', [ 'Disciple_Tools_Settings_Menu', 'content' ] );
    }

    public function add_tab( $tab ) {
        ?>
        <a href="<?php echo esc_url( admin_url() ) ?>admin.php?page=dt_options&tab=custom-fields"
           class="nav-tab <?php echo esc_html( $tab == 'custom-fields' ? 'nav-tab-active' : '' ) ?>">
            <?php echo esc_html__( 'Fields' ) ?>
        </a>
        <?php
    }

    private function get_post_fields( $post_type ){
        return DT_Posts::get_post_field_settings( $post_type, false, true );
    }

    /**
     * Packages and prints tab page
     *
     * @param $tab
     */
    public function content( $tab ) {
        global $wp_post_types;

        if ( 'custom-fields' == $tab ) :
            $show_add_field = false;
            $field_key = false;
            if ( isset( $_POST['field_key'] ) ){
                $field_key = sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) ?: false;
            }

            /*
             * Check for post types
             */
            if ( isset( $_POST['post_type'] ) ) {
                $post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ) );
                $_GET = null; // Prioritize $_POST over $_GET in order to avoid conflicts when switching post types
            } else if ( isset( $_GET['post_type'] ) ) {
                $post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
            } else {
                $post_type = null;
            }

            $this->template( 'begin', 1 );

            if ( !empty( $_GET["field-select"] ) ){
                $field = explode( "_", sanitize_text_field( wp_unslash( $_GET["field-select"] ) ), 2 );
                $field_key = $field[1];
                $post_type = $field[0];
            }
            if ( isset( $_GET['field_select_nonce'] ) ){
                if ( !wp_verify_nonce( sanitize_key( $_GET['field_select_nonce'] ), 'field_select' ) ) {
                    return;
                }
                if ( isset( $_GET["show_add_new_field"] ) ){
                    $show_add_field = true;
                } else if ( !empty( $_GET["field-select"] ) ){
                    $field = explode( "_", sanitize_text_field( wp_unslash( $_GET["field-select"] ) ), 2 );
                    $field_key = $field[1];
                    $post_type = $field[0];
                }
            }


            /*
             * Process Add field
             */
            if ( isset( $_POST["new_field_type"], $_POST['field_add_nonce'] ) ){
                if ( !wp_verify_nonce( sanitize_key( $_POST['field_add_nonce'] ), 'field_add' ) ) {
                    return;
                }
                $post_submission = [];
                foreach ( $_POST as $key => $value ){
                    $post_submission[sanitize_text_field( wp_unslash( $key ) )] = sanitize_text_field( wp_unslash( $value ) );
                }
                $field_key = $this->process_add_field( $post_submission );
                $post_type = $post_submission['post_type'];
                $show_add_field = $field_key === false;
            }
            /*
             * Process Edit field
             */
            if ( isset( $_POST["field_edit_nonce"] ) ){
                if ( !wp_verify_nonce( sanitize_key( $_POST['field_edit_nonce'] ), 'field_edit' ) ) {
                    return;
                }
                $post_submission = dt_recursive_sanitize_array( $_POST );
                $this->process_edit_field( $post_submission );
            }

            /*
             * Process Extra fields
             */
            if ( isset( $_POST["field_extras_nonce"] ) ){
                if ( !wp_verify_nonce( sanitize_key( $_POST['field_extras_nonce'] ), 'field_extras' ) ) {
                    return;
                }
                $post_submission = dt_recursive_sanitize_array( $_POST );

                $this->process_extra_field( $post_submission );
            }

            $this->box( 'top', __( 'Edit Fields', 'disciple_tools' ) );
            $this->post_type_select( $post_type );
            $this->box( 'bottom' );

            if ( empty( $post_type ) ) {
                return;
            }

            $this->box( 'top', __( 'Add new fields or modify existing ones on ', 'disciple_tools' ) . $wp_post_types[$post_type]->label );
            $this->field_select( $post_type );
            $this->box( 'bottom' );

            if ( empty( $field_key ) && $show_add_field ){
                $this->box( 'top', __( "Create new field", 'disciple_tools' ) );
                $this->add_field( $post_type );
                $this->box( 'bottom' );
            }
            if ( $post_type && isset( $this->get_post_fields( $post_type )[$field_key] ) ){
                $this->box( 'top', $this->get_post_fields( $post_type )[$field_key]["name"] );
                $this->edit_field( $field_key, $post_type );
                $this->box( 'bottom' );
            }

            $this->template( 'right_column' );

            $this->template( 'end' );
        endif;
    }

    private function post_type_select( $selected_post_type ) {
        global $wp_post_types;
        $post_types = DT_Posts::get_post_types();
        ?>
        <form method="post">
            <input type="hidden" name="post_type_select_nonce" id="post_type_select_nonce" value="<?php echo esc_attr( wp_create_nonce( 'post_type_select' ) ) ?>" />
            <table>
                <tr>
                    <td style="vertical-align: middle">
                        <label for="tile-select"><?php esc_html_e( "For what post type?", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                        <?php foreach ( $post_types as $post_type ) : ?>
                            <button type="submit" name="post_type" class="button <?php echo esc_html( $selected_post_type === $post_type ? 'button-primary' : '' ); ?>" value="<?php echo esc_html( $post_type ); ?>">
                                <?php echo esc_html( $wp_post_types[$post_type]->label ); ?>
                            </button>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <br>
        </form>
        <?php
    }

    private function field_select( $selected_post_type ){
        global $wp_post_types;
        $select_options = [];
        $selected_post_type = sanitize_text_field( wp_unslash( $selected_post_type ) );
        $fields = $this->get_post_fields( $selected_post_type );
        uasort($fields, function( $a, $b ) {
            return $a['name'] <=> $b['name'];
        });
        if ( $fields ){
            foreach ( $fields as $field_key => $field_value ){
                if ( ( isset( $field_value["customizable"] ) && $field_value["customizable"] !== false ) || ( !isset( $field_value["customizable"] ) && empty( $field_value["hidden"] ) ) ) {
                    $select_options[ $field_key ] = $field_value;
                }
            }
        }

        ?>
        <form method="get">
            <input type="hidden" name="field_select_nonce" id="field_select_nonce" value="<?php echo esc_attr( wp_create_nonce( 'field_select' ) ) ?>" />
            <input type="hidden" name="page" value="dt_options" />
            <input type="hidden" name="tab" value="custom-fields" />
            <input type="hidden" name="post_type" value="<?php echo esc_attr( $selected_post_type ); ?>" />
            <table>
                <tr>
                    <td style="vertical-align: middle">
                        <label for="field-select"><?php esc_html_e( "Modify an existing field", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                        <select id="field-select" name="field-select">
                            <option></option>
                                <option disabled>---<?php echo esc_html( $wp_post_types[$selected_post_type]->label ); ?> Fields---</option>
                                <?php foreach ( $select_options as $option_key => $option_value ) : ?>

                                <option value="<?php echo esc_html( $selected_post_type . '_' . $option_key ) ?>">
                                    <?php echo esc_html( $option_value["name"] ?? $option_key ) ?>
                                    <span> - (<?php echo esc_html( $option_key ) ?>)</span>
                                </option>
                                <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button" name="field_selected"><?php esc_html_e( "Select", 'disciple_tools' ) ?></button>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: middle">
                        <?php esc_html_e( "Create a new field", 'disciple_tools' ) ?>
                    </td>
                    <td>
                        <button type="submit" class="button" name="show_add_new_field"><?php esc_html_e( "Create new field", 'disciple_tools' ) ?></button>
                    </td>
                </tr>
            </table>

            <br>
        </form>

    <?php }

    private function edit_field( $field_key, $post_type ){

        $post_fields = $this->get_post_fields( $post_type );
        if ( !isset( $post_fields[$field_key] ) ) {
            wp_die( 'Failed to get find field' );
        }
        $field = $post_fields[$field_key];

        $core_fields = [ "languages" ];

        if ( isset( $field["customizable"] ) && $field["customizable"] === false ){
            ?>
            <p>
                <strong>This field is not customizable</strong>
            </p>
            <?php
            return;
        }

        $post_settings = DT_Posts::get_post_settings( $post_type );
        $base_fields = Disciple_Tools_Post_Type_Template::get_base_post_type_fields();
        $defaults = apply_filters( 'dt_custom_fields_settings', $base_fields, $post_type );

        $field_options = $field["default"] ?? [];
        $first = true;
        $tile_options = DT_Posts::get_post_tiles( $post_type );

        $langs = dt_get_available_languages();
        ?>
        <form method="post" name="field_edit_form">
        <input type="hidden" name="field_key" value="<?php echo esc_html( $field_key )?>">
        <input type="hidden" name="post_type" value="<?php echo esc_html( $post_type )?>">
        <input type="hidden" name="field-select" value="<?php echo esc_html( $post_type . "_" . $field_key )?>">
        <input type="hidden" name="field_select_nonce" id="field_select_nonce" value="<?php echo esc_attr( wp_create_nonce( 'field_select' ) ) ?>" />
        <input type="hidden" name="field_edit_nonce" id="field_edit_nonce" value="<?php echo esc_attr( wp_create_nonce( 'field_edit' ) ) ?>" />

        <h3><?php esc_html_e( "Field Settings", 'disciple_tools' ) ?></h3>
        <table class="widefat">
            <thead>
            <tr>
                <td><?php esc_html_e( "Key", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Default Name", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Custom Name", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Private Field", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Translation", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Tile", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Description", 'disciple_tools' ) ?></td>
                <td><?php esc_html_e( "Description Translation", 'disciple_tools' ) ?></td>
                <td></td>
                <td></td>
            </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php echo esc_html( $field_key ) ?>
                    </td>
                    <td>
                        <?php if ( !empty( $defaults[$field_key]["name"] ) ) : ?>
                            <?php echo esc_html( $defaults[$field_key]["name"] ); ?> <br>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $name = ( !isset( $defaults[$field_key] ) || ( isset( $defaults[$field_key]["name"] ) && $field["name"] !== $defaults[$field_key]["name"] ) ) ? $field["name"] : ''; ?>
                        <input name="field_key_<?php echo esc_html( $field_key )?>" type="text" value="<?php echo esc_html( $name ) ?>"/>
                        <?php if ( isset( $defaults[$field_key] ) && !empty( $name ) ) : ?>
                        <button title="submit" class="button" name="delete_custom_label">Remove Label</button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $private_field_disabled = isset( $defaults[$field_key] ) || $field["type"] === 'connection' ?>
                        <input name="field_private" id="field_private" type="checkbox" <?php echo esc_html( ( isset( $field['private'] ) && $field['private'] ) ? "checked" : '' );?> <?php echo esc_html( ( $private_field_disabled ) ? "disabled" : '' ); ?>>
                    </td>
                    <td>
                        <button class="button small expand_translations">
                            <?php
                            $number_of_translations = 0;
                            foreach ( $langs as $lang => $val ){
                                if ( !empty( $field["translations"][$val['language']] ) ){
                                    $number_of_translations++;
                                }
                            }
                            ?>
                            <img style="height: 15px; vertical-align: middle" src="<?php echo esc_html( get_template_directory_uri() . "/dt-assets/images/languages.svg" ); ?>">
                            (<?php echo esc_html( $number_of_translations ); ?>)
                        </button>
                        <div class="translation_container hide">
                        <table>

                            <?php foreach ( $langs as $lang => $val ) : ?>
                                <tr>
                                    <td><label for="field_key_<?php echo esc_html( $field_key )?>_translation-<?php echo esc_html( $val['language'] )?>"><?php echo esc_html( $val['native_name'] )?></label></td>
                                    <td><input name="field_key_<?php echo esc_html( $field_key )?>_translation-<?php echo esc_html( $val['language'] )?>" type="text" value="<?php echo esc_html( $field["translations"][$val['language']] ?? "" );?>"/></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        </div>
                    </td>
                    <td>
                        <select name="tile_select">
                            <option><?php esc_html_e( "No tile / hidden", 'disciple_tools' ) ?></option>
                            <?php foreach ( $tile_options as $tile_key => $tile_option ) :
                                $select = isset( $field["tile"] ) && $field["tile"] === $tile_key;
                                ?>
                                <option value="<?php echo esc_html( $tile_key ) ?>" <?php echo esc_html( $select ? "selected" : "" )?>>
                                    <?php echo esc_html( $tile_option["label"] ?? $tile_key ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <td>
                        <input style="width: 100%" type="text" name="field_description" value="<?php echo esc_html( $field["description"] ?? "" )?>">
                    </td>
                    <td>
                        <button class="button small expand_translations">
                            <?php
                            $number_of_translations = 0;
                            foreach ( $langs as $lang => $val ){
                                if ( !empty( $field["description_translations"][$val['language']] ) ){
                                    $number_of_translations++;
                                }
                            }
                            ?>
                            <img style="height: 15px; vertical-align: middle" src="<?php echo esc_html( get_template_directory_uri() . "/dt-assets/images/languages.svg" ); ?>">
                            (<?php echo esc_html( $number_of_translations ); ?>)
                        </button>
                        <div class="translation_container hide">
                            <table>
                                <?php foreach ( $langs as $lang => $val ) : ?>
                                    <tr>
                                        <td><label for="field_description_translation-<?php echo esc_html( $val['language'] )?>"><?php echo esc_html( $val['native_name'] )?></label></td>
                                        <td><input name="field_description_translation-<?php echo esc_html( $val['language'] )?>" type="text" value="<?php echo esc_html( $field["description_translations"][$val['language']] ?? "" );?>"/></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </td>
                    <td>
                        <button type="submit" name="save" class="button"><?php esc_html_e( "Save", 'disciple_tools' ) ?></button>
                    </td>
                    <td>
                    <?php
                    $custom_fields = dt_get_option( "dt_field_customizations" );
                    $custom_field = $custom_fields[$post_type][$field_key] ?? [];
                    if ( isset( $custom_field["customizable"] ) && $custom_field["customizable"] == "all" ) : ?>
                        <button type="submit" name="delete"  class="button"><?php esc_html_e( "Delete", 'disciple_tools' ) ?></button>
                    <?php endif ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <br>

        <?php if ( $field["type"] === "key_select" || $field["type"] === "multi_select" ){
            if ( in_array( $field_key, $core_fields ) ){
                ?>
                <p>
                    <strong>This is a core field. <a href="<?php echo esc_url( admin_url() ) ?>edit.php?page=dt_options&tab=custom-lists#<?php echo esc_attr( $field_key ) ?>"  class="">Go to Custom Lists page to edit options.</a></strong>
                </p>
                <?php
                return;
            } ?>

            <h3><?php esc_html_e( "Field Options", 'disciple_tools' ) ?></h3>
            <table id="add_option" style="">
                <tr>
                    <td style="vertical-align: middle">
                        <?php esc_html_e( "Add new option", 'disciple_tools' ) ?>
                    </td>
                    <td>
                        <input type="text" name="add_option" placeholder="label" />
                        <button type="submit" class="button"><?php echo esc_html( __( 'Add', 'disciple_tools' ) ) ?></button>
                    </td>
                </tr>
            </table>
            <br>
            <table class="widefat">
                <thead>
                <tr>
                    <td><?php esc_html_e( "Key", 'disciple_tools' ) ?></td>
                    <td><?php esc_html_e( "Default Label", 'disciple_tools' ) ?></td>
                    <td><?php esc_html_e( "Custom Label", 'disciple_tools' ) ?></td>
                    <?php
                    if ( $field["type"] === "multi_select" ):
                        ?>
                        <td><?php esc_html_e( "Icon", 'disciple_tools' ) ?></td>
                        <td><?php esc_html_e( "Icon Link", 'disciple_tools' ) ?></td>
                        <td></td>
                        <?php
                    endif;
                    ?>
                    <td><?php esc_html_e( "Translation", 'disciple_tools' ) ?></td>
                    <td><?php esc_html_e( "Move", 'disciple_tools' ) ?></td>
                    <td><?php esc_html_e( "Hide/Archive", 'disciple_tools' ) ?></td>
                    <td><?php esc_html_e( "Description", 'disciple_tools' ) ?></td>
                    <td><?php esc_html_e( "Description Translation", 'disciple_tools' ) ?></td>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $field_options as $key => $option ) :

                    if ( !( isset( $option["deleted"] ) && $option["deleted"] == true ) ):
                        $label = $option["label"] ?? "";
                        $in_defaults = isset( $defaults[$field_key]["default"][$key] );
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html( $key ) ?>
                            </td>
                            <td>
                                <?php if ( !empty( $defaults[$field_key]["default"][$key]["label"] ) ) : ?>
                                    <?php echo esc_html( $defaults[$field_key]["default"][$key]["label"] ); ?> <br>
                                <?php endif; ?>
                            </td>
                            <td>

                                <?php $name = ( isset( $defaults[$field_key]["default"][$key]["label"] ) && $label === $defaults[$field_key]["default"][$key]["label"] ) ? '' : $label ?>
                                <input name="field_option_<?php echo esc_html( $key )?>" type="text" value="<?php echo esc_html( $name ) ?>"/>
                                <?php if ( isset( $defaults[$field_key]["default"][$key]["label"] ) && !empty( $name ) ) : ?>
                                <button title="submit" class="button" name="delete_option_label" value="<?php echo esc_html( $key ) ?>">Remove Label</button>
                                <?php endif; ?>
                            </td>
                            <?php if ( $field["type"] === "multi_select" ): ?>
                                <td>
                                    <?php if ( isset( $option["icon"] ) && ! empty( $option["icon"] ) ): ?>
                                        <img src="<?php echo esc_attr( $option["icon"] ); ?>" style="width: 20px; vertical-align: middle;">
                                    <?php else : ?>
                                        <div style="width: 20px; display: inline-block">&nbsp;</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" name="field_option_icon_<?php echo esc_html( $key ) ?>" placeholder="<?php esc_html_e( 'Icon url', 'disciple_tools' ); ?>"
                                           value="<?php echo esc_attr( isset( $option["icon"] ) ? $option["icon"] : '' ); ?>">
                                </td>
                                <td>
                                    <?php if ( isset( $defaults[ $field_key ]["default"][ $key ]["icon"] ) && $defaults[ $field_key ]["default"][ $key ]["icon"] !== $option["icon"] ): ?>
                                        <button type="submit" class="button" name="restore_icon"
                                                value="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Restore to Default', 'disciple_tools' ); ?></button>
                                    <?php elseif ( isset( $option["icon"] ) && !empty( $option["icon"] ) ) : ?>
                                        <button type="submit" class="button" name="delete_icon" value="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Remove Icon', 'disciple_tools' ); ?></button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <button class="button small expand_translations">
                                    <?php
                                    $number_of_translations = 0;
                                    foreach ( $langs as $lang => $val ){
                                        if ( !empty( $field["default"][$key]["translations"][$val['language']] ) ){
                                            $number_of_translations++;
                                        }
                                    }
                                    ?>
                                    <img style="height: 15px; vertical-align: middle" src="<?php echo esc_html( get_template_directory_uri() . "/dt-assets/images/languages.svg" ); ?>">
                                    (<?php echo esc_html( $number_of_translations ); ?>)
                                </button>
                                <div class="translation_container hide">
                                    <table>
                                        <?php foreach ( $langs as $lang => $val ) : ?>
                                            <tr>
                                                <td><label for="field_option_<?php echo esc_html( $key )?>_translation-<?php echo esc_html( $val['language'] )?>"><?php echo esc_html( $val['native_name'] )?></label></td>
                                                <td><input name="field_option_<?php echo esc_html( $key )?>_translation-<?php echo esc_html( $val['language'] )?>" type="text" value="<?php echo esc_html( $field["default"][$key]["translations"][$val['language']] ?? "" );?>"/></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            </td>

                            <td>
                                <?php if ( !$first ) : ?>
                                    <button type="submit" name="move_up" value="<?php echo esc_html( $key ) ?>" class="button small" >↑</button>
                                    <button type="submit" name="move_down" value="<?php echo esc_html( $key ) ?>" class="button small" >↓</button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( !isset( $field["customizable"] ) || ( isset( $field["customizable"] ) && ( $field["customizable"] === "all" || ( $field["customizable"] === 'add_only' && !$in_defaults ) ) )
                                    || !isset( $field["default"][$key] ) ) : ?>
                                <button type="submit" name="delete_option" value="<?php echo esc_html( $key ) ?>" class="button small" ><?php esc_html_e( "Hide", 'disciple_tools' ) ?></button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $description = isset( $field["default"][$key]["description"] ) ? $field["default"][$key]["description"] : ""; ?>
                                <input name="option_description_<?php echo esc_html( $key )?>" type="text" value="<?php echo esc_html( $description ) ?>">
                            </td>
                            <td>
                                <button class="button small expand_translations">
                                    <?php
                                    $number_of_translations = 0;
                                    foreach ( $langs as $lang => $val ){
                                        if ( !empty( $field["default"][$key]["description_translations"][$val['language']] ) ){
                                            $number_of_translations++;
                                        }
                                    }
                                    ?>
                                    <img style="height: 15px; vertical-align: middle" src="<?php echo esc_html( get_template_directory_uri() . "/dt-assets/images/languages.svg" ); ?>">
                                    (<?php echo esc_html( $number_of_translations ); ?>)
                                </button>
                                <div class="translation_container hide">
                                    <table>
                                        <?php foreach ( $langs as $lang => $val ) : ?>
                                            <tr>
                                                <td><label for="option_description_<?php echo esc_html( $key )?>_translation-<?php echo esc_html( $val['language'] )?>"><?php echo esc_html( $val['native_name'] )?></label></td>
                                                <td><input name="option_description_<?php echo esc_html( $key )?>_translation-<?php echo esc_html( $val['language'] )?>" type="text" value="<?php echo esc_html( $field["default"][$key]["description_translations"][$val['language']] ?? "" );?>"/></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php $first = false;
                    endif;
                endforeach; ?>
                <?php foreach ( $field_options as $key => $option ) :
                    $label = $option["label"] ?? "";
                    if ( isset( $option["deleted"] ) && $option["deleted"] == true ): ?>
                        <tr style="background-color: #eee">
                            <td><?php echo esc_html( $key ) ?></td>
                            <td><?php echo esc_html( $label ) ?></td>
                            <td colspan="5"></td>
                            <td>
                                <button type="submit" name="restore_option" value="<?php echo esc_html( $key ) ?>" class="button small" ><?php esc_html_e( "Restore", 'disciple_tools' ) ?></button>
                            </td>
                        </tr>
                    <?php endif;
                endforeach; ?>
                </tbody>
            </table>

            <br>
            <button type="submit" style="float:right;" class="button"><?php esc_html_e( "Save", 'disciple_tools' ) ?></button>


        <?php } ?>
        </form>

        <br>
        <?php
        /**
         * Visibility controls
         */
        ?>
        <?php if ( isset( $post_fields["type"]["default"] ) ) : ?>
            <form method="post" name="field_extras_form">
            <input type="hidden" name="field_key" value="<?php echo esc_html( $field_key )?>">
            <input type="hidden" name="post_type" value="<?php echo esc_html( $post_type )?>">
            <input type="hidden" name="field-select" value="<?php echo esc_html( $post_type . "_" . $field_key )?>">
            <input type="hidden" name="field_select_nonce" id="field_select_nonce" value="<?php echo esc_attr( wp_create_nonce( 'field_select' ) ) ?>" />
            <input type="hidden" name="field_extras_nonce" id="field_extras_nonce" value="<?php echo esc_attr( wp_create_nonce( 'field_extras' ) ) ?>" />
            <!-- field visibility on record -->
            <h3><?php echo esc_html( sprintf( __( 'Visible on %s records', 'disciple_tools' ), strtolower( $post_settings["label_singular"] ) ) ) ?></h3>

            <p><?php echo esc_html( sprintf( __( 'This field will show up on %s records of type:', 'disciple_tools' ), strtolower( $post_settings["label_singular"] ) ) ) ?></p>
            <?php foreach ( $post_fields["type"]["default"] as $type_key => $type ) :
                $checked = dt_field_enabled_for_record_type( $field, [ "type" => [ "key" => $type_key ] ] );
                ?>
                <label style="margin-right:10px">
                    <input type="checkbox" name="field_type[]" value="<?php echo esc_html( $type_key ); ?>" <?php checked( $checked ) ?>>
                    <?php echo esc_html( $type["label"] ); ?>
                </label>
            <?php endforeach; ?>
            <br>
            <br>
            <button type="submit" name="save_types" class="button"><?php esc_html_e( "Save", 'disciple_tools' ) ?></button>

            <!-- create record form -->
            <h3><?php echo esc_html( sprintf( __( 'Visible when creating %s', 'disciple_tools' ), strtolower( $post_settings["label_plural"] ) ) ) ?></h3>
            <p> <?php echo esc_html( sprintf( __( 'When creating a %1$s, this field will be displayed at the top (in the non hidden section), for these %2$s types:', 'disciple_tools' ), strtolower( $post_settings["label_singular"] ), strtolower( $post_settings["label_singular"] ) ) ) ?></p>
            <?php foreach ( $post_fields["type"]["default"] as $type_key => $type ) :
                if ( !empty( $type["hidden"] ) ){
                    continue;
                }
                $checked = isset( $field["in_create_form"] ) && !empty( $field["in_create_form"] ) && ( $field["in_create_form"] === true || in_array( $type_key, $field["in_create_form"], true ) )
                ?>
                <label style="margin-right:10px">
                    <input name="create_form_options[]" type="checkbox" <?php checked( $checked ) ?> value="<?php echo esc_html( $type_key ); ?>">
                    <?php echo esc_html( $type["label"] ); ?>
                </label>
            <?php endforeach; ?>
            <br>
            <br>
            <button type="submit" name="save_create_form" class="button"><?php esc_html_e( "Save", 'disciple_tools' ) ?></button>
            </form>
        <?php endif; ?>
    <?php }

    private static function moveElement( &$array, $a, $b ) {
        $out = array_splice( $array, $a, 1 );
        array_splice( $array, $b, 0, $out );
    }


    private function process_extra_field( $post_submission ){
        $post_type = $post_submission["post_type"];
        $post_fields = $this->get_post_fields( $post_type );
        $field_customizations = dt_get_option( "dt_field_customizations" );
        $field_key = $post_submission["field_key"];

        if ( isset( $post_submission["save_types"] ) ){
            $types = $post_fields["type"]["default"];
            if ( !isset( $post_submission["field_type"] ) ){
                $field_customizations[$post_type][$field_key]["only_for_types"] = false;
            } else if ( sizeof( $post_submission["field_type"] ) === sizeof( $types ) ){
                $field_customizations[$post_type][$field_key]["only_for_types"] = true;
            } else {
                $field_customizations[$post_type][$field_key]["only_for_types"] = $post_submission["field_type"];
            }
            update_option( "dt_field_customizations", $field_customizations );
            wp_cache_delete( $post_type . "_field_settings" );
        }

        if ( isset( $post_submission["save_create_form"] ) ){
            $types_options = $post_fields["type"]["default"];
            $non_hidden_types = array_filter( $types_options, function ( $type ){
                return !isset( $type["hidden"] ) || empty( $type["hidden"] );
            });
            $create_form_options = isset( $post_submission["create_form_options"] ) ? $post_submission["create_form_options"] : false;

            if ( !isset( $post_submission["create_form_options"] ) ){
                $field_customizations[$post_type][$field_key]["in_create_form"] = [ 'hidden' ];
            } else if ( sizeof( $create_form_options ) === count( $non_hidden_types ) ){
                $field_customizations[$post_type][$field_key]["in_create_form"] = true;
            } else {
                $field_customizations[$post_type][$field_key]["in_create_form"] = $create_form_options;
            }

            update_option( "dt_field_customizations", $field_customizations );
            wp_cache_delete( $post_type . "_field_settings" );
        }
    }

    private function process_edit_field( $post_submission ){
        //save values
        $post_type = $post_submission["post_type"];
        $post_fields = $this->get_post_fields( $post_type );
        $field_customizations = dt_get_option( "dt_field_customizations" );
        $field_key = $post_submission["field_key"];
        $langs = dt_get_available_languages();
        if ( isset( $post_submission["delete"] ) ){
            if ( isset( $field_customizations[$post_type][$field_key] ) ){
                unset( $field_customizations[$post_type][$field_key] );
            }
            update_option( "dt_field_customizations", $field_customizations );
            wp_cache_delete( $post_type . "_field_settings" );
            return;
        }

        if ( isset( $post_fields[$post_submission["field_key"]] ) ){
            if ( !isset( $field_customizations[$post_type][$field_key] ) ){
                $field_customizations[$post_type][$field_key] = [];
            }
            $custom_field = $field_customizations[$post_type][$field_key];
            $field = $post_fields[$field_key];

            //update name
            if ( isset( $post_submission["field_key_" . $field_key] ) ){
                $custom_field["name"] = $post_submission["field_key_" . $field_key];
                foreach ( $langs as $lang => $val ){
                    $langcode = $val['language'];
                    $translation_key = "field_key_" . $field_key . "_translation-" . $langcode;
                    if ( isset( $post_submission[$translation_key] ) ) {
                        if ( empty( $post_submission[$translation_key] ) && isset( $custom_field["translations"][$langcode] ) ){
                            unset( $post_submission[$translation_key] );
                        } elseif ( !empty( $post_submission[$translation_key] ) ) {
                            $custom_field["translations"][$langcode] = $post_submission[$translation_key];
                        }
                    }
                }
            }
            if ( isset( $post_submission["delete_custom_label"], $custom_field["name"] ) ){
                unset( $custom_field["name"] );
            }
            //field privacy
            if ( isset( $post_submission["field_private"] ) && $post_submission["field_private"] ) {
                $custom_field["private"] = true;
            } else if ( !isset( $post_submission["field_private"] ) || !$post_submission["field_private"] ) {
                $custom_field["private"] = false;
            }
            //field description
            if ( isset( $post_submission["field_description"] ) && $post_submission["field_description"] != ( $custom_field["description"] ?? "" ) ){
                $custom_field["description"] = $post_submission["field_description"];
            }
            //field description translation
            foreach ( $post_submission as $key => $val ){
                if ( strpos( $key, "field_description_translation" ) === 0 ){
                    $language_code = substr( $key, strlen( "field_description_translation-" ) );
                    if ( ( !isset( $custom_field["description_translations"] ) || empty( $custom_field["description_translations"] ) ) && !empty( $val ) ){
                        $custom_field["description_translations"][$language_code] = $val;
                    }
                    if ( empty( $val ) && isset( $custom_field["description_translations"][$language_code] ) ){
                        unset( $custom_field["description_translations"][$language_code] );
                    }
                }
            }

            //field tile
            if ( isset( $post_submission["tile_select"] ) ){
                $custom_field["tile"] = $post_submission["tile_select"];
            }
            // key_select and multi_options
            if ( isset( $post_fields[$field_key]["default"] ) && ( $field["type"] === 'multi_select' || $field["type"] === "key_select" ) ){
                $field_options = $field["default"];
                foreach ( $post_submission as $key => $val ){
                    if ( strpos( $key, "field_option_" ) === 0 ) {
                        if ( strpos( $key, 'translation' ) !== false ) {
                            $option_key           = substr( $key, 13, strpos( $key, 'translation' ) - 14 );
                            $translation_langcode = substr( $key, strpos( $key, 'translation' ) + strlen( 'translation-' ) );
                            if ( strpos( $translation_langcode, '-' ) !== false ) {
                                $translation_langcode = substr( $translation_langcode, 3 );
                            }
                            if ( empty( $val ) && isset( $custom_field["default"][ $option_key ]["translations"][ $translation_langcode ] ) ) {
                                unset( $custom_field["default"][ $option_key ]["translations"][ $translation_langcode ] );
                            } elseif ( ! empty( $val ) ) {
                                $custom_field["default"][ $option_key ]["translations"][ $translation_langcode ] = $val;
                            }
                        } elseif ( strpos( $key, 'icon' ) !== false ) {
                            $option_key = substr( $key, 18 );

                            if ( !empty( $val ) ){
                                if ( ! isset( $field_options[ $option_key ]["icon"] ) || $field_options[ $option_key ]["icon"] != $val ) {
                                    $custom_field["default"][$option_key]["icon"] = $val;
                                }
                                $field_options[$option_key]['icon'] = $val;
                            }
                        } else {
                            $option_key = substr( $key, 13 );

                            if ( !empty( $val ) ){
                                if ( !isset( $field_options[$option_key]["label"] ) || $field_options[$option_key]["label"] != $val ){
                                    $custom_field["default"][$option_key]["label"] = $val;
                                }
                                $field_options[$option_key]["label"] = $val;
                            }
                        }
                    }

                    if ( strpos( $key, "option_description_" ) === 0 ) {
                        if ( strpos( $key, 'translation' ) !== false ) {
                            $option_key = substr( $key, strlen( "option_description_" ), strpos( $key, 'translation' ) - ( strlen( "option_description_" ) + 1 ) );
                            $translation_langcode = substr( $key, strpos( $key, 'translation' ) + strlen( 'translation-' ) );
                            if ( strpos( $translation_langcode, '-' ) !== false ) {
                                $translation_langcode = substr( $translation_langcode, 3 );
                            }
                            if ( empty( $val ) && isset( $custom_field["default"][$option_key]["description_translations"][$translation_langcode] ) ){
                                unset( $custom_field["default"][$option_key]["description_translations"][$translation_langcode] );
                            } elseif ( !empty( $val ) ) {
                                $custom_field["default"][$option_key]["description_translations"][$translation_langcode] = $val;
                            }
                        } else {
                            $option_key = substr( $key, strlen( "option_description_" ) );
                            //if the description isn't set and the value is not empty, or if the value changed.
                            if ( ( !isset( $field_options[$option_key]["description"] ) && !empty( $val ) ) || ( isset( $field_options[$option_key]["description"] ) && $field_options[$option_key]["description"] !== $val ) ){
                                $custom_field["default"][$option_key]["description"] = $val;
                            }
                            $field_options[$option_key]["description"] = $val;
                        }
                    }
                }
                //delete icon
                if ( isset( $post_submission["delete_icon"] ) ) {
                    $custom_field["default"][ $post_submission["delete_icon"] ]["icon"] = '';
                    $field_options[ $post_submission["delete_icon"] ]["icon"]           = '';
                }
                //restore icon
                if ( isset( $post_submission["restore_icon"] ) ) {
                    $restore_icon_defaults                                               = apply_filters( 'dt_custom_fields_settings', [], $post_type );
                    $custom_field["default"][ $post_submission["restore_icon"] ]["icon"] = $restore_icon_defaults[ $field_key ]["default"][ $post_submission["restore_icon"] ]["icon"];
                    $field_options[ $post_submission["restore_icon"] ]["icon"]           = $restore_icon_defaults[ $field_key ]["default"][ $post_submission["restore_icon"] ]["icon"];
                }
                //delete option
                if ( isset( $post_submission["delete_option"] ) ){
                    $custom_field["default"][$post_submission["delete_option"]]["deleted"] = true;
                    $field_options[ $post_submission["delete_option"] ]["deleted"] = true;
                }
                //delete custom label
                if ( isset( $post_submission["delete_option_label"] ) ){
                    unset( $custom_field["default"][$post_submission["delete_option_label"]]["label"] );
                    unset( $field_options["default"][$post_submission["delete_option_label"]]["label"] );
                }
                //delete option
                if ( isset( $post_submission["restore_option"] ) ){
                    $custom_field["default"][$post_submission["restore_option"]]["deleted"] = false;
                    $field_options[ $post_submission["restore_option"] ]["deleted"] = false;
                }
                //move option  up or down
                if ( isset( $post_submission["move_up"] ) || isset( $post_submission["move_down"] ) ){
                    $option_key = $post_submission["move_up"] ?? $post_submission["move_down"];
                    $direction = isset( $post_submission["move_up"] ) ? -1 : 1;
                    $active_field_options = [];
                    foreach ( $field_options as $field_option_key => $field_option_val ){
                        if ( !( isset( $field_option_val["deleted"] ) && $field_option_val["deleted"] == true ) ){
                            $active_field_options[strval( $field_option_key )] = $field_option_val;
                        }
                    }
                    $pos = (int) array_search( $option_key, array_keys( $active_field_options ) );
                    $option_keys = array_keys( $active_field_options );
                    self::moveElement( $option_keys, $pos, $pos + $direction );
                    $custom_field["order"] = $option_keys;
                }
                /*
                 * add option
                 */
                if ( !empty( $post_submission["add_option"] ) ){
                    $option_key = dt_create_field_key( $post_submission["add_option"] );
                    if ( !isset( $field_options[$option_key] ) ){
                        if ( !empty( $option_key ) && !empty( $post_submission["add_option"] ) ){
                            $field_options[ $option_key ] = [ "label" => $post_submission["add_option"] ];
                            $custom_field["default"][$option_key] = [ "label" => $post_submission["add_option"] ];
                        }
                    } else {
                        self::admin_notice( __( "This option already exists", 'disciple_tools' ), "error" );
                    }
                }
            }
            $field_customizations[$post_type][$field_key] = $custom_field;
            update_option( "dt_field_customizations", $field_customizations );
            wp_cache_delete( $post_type . "_field_settings" );
        }
    }


    private function add_field( $post_type ){
        global $wp_post_types;
        $post_type = sanitize_text_field( wp_unslash( $post_type ) );
        $tile_options = DT_Posts::get_post_tiles( $post_type );
        $post_types = DT_Posts::get_post_types();
        ?>
        <form method="post">
            <input type="hidden" name="field_add_nonce" id="field_add_nonce" value="<?php echo esc_attr( wp_create_nonce( 'field_add' ) ) ?>" />
            <table>
                <tr>
                    <td style="vertical-align: middle; min-width:250px">
                        <?php esc_html_e( "Post type", 'disciple_tools' ) ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html( $wp_post_types[$post_type]->label ); ?></strong>
                        <input type="hidden" name="post_type" id="current_post_type" value="<?php echo esc_html( $post_type ); ?>">
                    </td>
                <tr>
                    <td style="vertical-align: middle">
                        <label for="new_field_name"><?php esc_html_e( "New Field Name", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                        <input name="new_field_name" id="new_field_name" required>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: middle">
                        <label><?php esc_html_e( "Field type", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                        <select id="new_field_type_select" name="new_field_type" required>
                            <option></option>
                            <option value="key_select"><?php esc_html_e( "Dropdown", 'disciple_tools' ) ?></option>
                            <option value="multi_select"><?php esc_html_e( "Multi Select", 'disciple_tools' ) ?></option>
                            <option value="tags"><?php esc_html_e( "Tags", 'disciple_tools' ) ?></option>
                            <option value="text"><?php esc_html_e( "Text", 'disciple_tools' ) ?></option>
                            <option value="textarea"><?php esc_html_e( "Text Area", 'disciple_tools' ) ?></option>
                            <option value="date"><?php esc_html_e( "Date", 'disciple_tools' ) ?></option>
                            <option value="connection"><?php esc_html_e( "Connection", 'disciple_tools' ) ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="connection_field_target_row" style="display: none">
                    <td style="vertical-align: middle">
                        <label><?php esc_html_e( "Connected to", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                    <select name="connection_target" id="connection_field_target">
                        <option></option>
                        <?php foreach ( $post_types as $post_type_key ) : ?>
                            <option value="<?php echo esc_html( $post_type_key ); ?>">
                                <?php echo esc_html( $wp_post_types[$post_type_key]->label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    </td>
                </tr>


                <tr class="same_post_type_row" style="display: none">
                    <td>
                        Bi-directional
                    </td>
                    <td>
                         <input type="checkbox" id="multidirectional_checkbox" name="multidirectional" checked>
                    </td>
                </tr>
                <tr class="same_post_type_other_field_name" style="display: none">
                    <td style="vertical-align: middle">
                        Reverse connection field name
                        <br>
                        See connection instructions bellow.
                    </td>
                    <td>
                        <input name="reverse_connection_name" id="connection_field_reverse_name">
                    </td>
                </tr>
                <tr class="same_post_type_other_field_name" style="display: none">
                    <td>
                        Hide reverse connection field on <span class="connected_post_type"></span>
                    </td>
                    <td>
                        <input type="checkbox" name="disable_reverse_connection">
                    </td>
                </tr>


                <tr class="connection_field_reverse_row" style="display: none">
                    <td style="vertical-align: middle">
                        Field name when shown on: <span class="connected_post_type"></span>
                        <br>
                        See connection instructions bellow.
                    </td>
                    <td>
                        <input name="other_field_name" id="other_field_name">
                    </td>
                </tr>
                <tr class="connection_field_reverse_row" style="display: none">
                    <td>
                        Hide field on <span class="connected_post_type"></span>
                    </td>
                    <td>
                        <input type="checkbox" name="disable_other_post_type_field">
                    </td>
                </tr>

                <tr id="private_field_row">
                    <td style="vertical-align: middle">
                        <label><?php esc_html_e( "Private Field", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                        <input name="new_field_private" id="new_field_private" type="checkbox" <?php echo esc_html( ( isset( $field['private'] ) && $field['private'] ) ? "checked" : '' );?>>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: middle">
                        <label><?php esc_html_e( "Tile", 'disciple_tools' ) ?></label>
                    </td>
                    <td>
                        <select name="new_field_tile">
                            <option><?php esc_html_e( "No tile", 'disciple_tools' ) ?></option>
                                <option disabled>---<?php echo esc_html( $wp_post_types[$post_type]->label ); ?> tiles---</option>
                                <?php foreach ( $tile_options as $option_key => $option_value ) : ?>
                                    <option value="<?php echo esc_html( $option_key ) ?>">
                                        <?php echo esc_html( $option_value["label"] ?? $option_key ) ?>
                                    </option>
                                <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: middle">
                    </td>
                    <td>
                        <button type="submit" class="button"><?php esc_html_e( "Create Field", 'disciple_tools' ) ?></button>
                    </td>
                </tr>
            </table>
        </form>
        <br>
        <strong><?php esc_html_e( "Field types:", 'disciple_tools' ) ?></strong>
        <ul style="list-style: disc; padding-left:40px">
            <li><?php esc_html_e( "Dropdown: Select an option for a dropdown list", 'disciple_tools' ) ?></li>
            <li><?php esc_html_e( "Multi Select: A field like the milestones to track items like course progress", 'disciple_tools' ) ?></li>
            <li><?php esc_html_e( "Tags: A field allowing entry of any custom tags or values", 'disciple_tools' ) ?></li>
            <li><?php esc_html_e( "Text: This is just a normal text field", 'disciple_tools' ) ?></li>
            <li><?php esc_html_e( "Text Area: This is just a multi-line text area", 'disciple_tools' ) ?></li>
            <li><?php esc_html_e( "Date: A field that uses a date picker to choose dates (like baptism date)", 'disciple_tools' ) ?></li>
            <li><?php esc_html_e( "Connection: An autocomplete picker to connect to another record.", 'disciple_tools' ) ?></li>
        </ul>
        <strong><?php esc_html_e( "Private Field:", 'disciple_tools' ) ?></strong>
        <ul style="list-style: disc; padding-left:40px">
            <li><?php esc_html_e( "The content of private fields can only be seen by the user who creates it and will not be shared with other DT users.", 'disciple_tools' ) ?></li>
        </ul>
        <strong><?php esc_html_e( "Connection Field:", 'disciple_tools' ) ?></strong>
        <ul style="list-style: disc; padding-left:40px">
            <li>Connects one post type to another post type (or to the some post type). Ex: contacts to groups, contacts to contacts, etc</li>
            <li><strong>Connecting to the same post type</strong>
                <ul style="list-style: disc; padding-left:40px">
                    <li>By default a connection will be bi-directional, meaning that one field is created and a connection on one record will show on the other.</li>
                    <li>Make the connection one-directional by unchecking the bi-directional checkbox will allow connections like the coaching field or the sub-assigned field.
                        This splits the connection into 2 fields (coaching and coached by). </li>
                    <li>You can hide the second field to not have it show up by checking the "Hide reverse connection" box.</li>
                </ul>
            </li>
            <li><strong>Connection to another post type</strong>
                <ul style="list-style: disc; padding-left:40px">
                    <li>This creates 2 fields. One on the current (<?php echo esc_html( $wp_post_types[$post_type]->label ); ?>) post type, the other of the "connected to" post type</li>
                    <li>Choose the field name that shows up on the connected post type by filling the "Field name when shown on: X" box</li>
                    <li>If you want the field to only show up on the current (<?php echo esc_html( $wp_post_types[$post_type]->label ); ?>) post type, the check the "Hide field" checkbox</li>
                </ul>
            </li>
        </ul>
        <?php
    }

    private function process_add_field( $post_submission ){
        if ( isset( $post_submission["new_field_name"], $post_submission["new_field_type"], $post_submission["post_type"] ) ){
            $post_type = $post_submission["post_type"];
            $field_type = $post_submission["new_field_type"];
            $field_tile = $post_submission["new_field_tile"] ?? '';
            $field_key = dt_create_field_key( $post_submission["new_field_name"] );
            $custom_field_options = dt_get_option( "dt_field_customizations" );

            if ( !$field_key ){
                return false;
            }

            //field privacy
            if ( isset( $post_submission["new_field_private"] ) && $post_submission["new_field_private"] ) {
                $field_private = true;
            } else {
                $field_private = false;
            }
            $post_fields = $this->get_post_fields( $post_type );
            if ( isset( $post_fields[ $field_key ] ) ){
                self::admin_notice( __( "Field already exists", 'disciple_tools' ), "error" );
                return false;
            }
            $new_field = [];
            if ( $field_type === "key_select" ){
                $new_field = [
                    'name' => $post_submission["new_field_name"],
                    'default' => [],
                    'type' => 'key_select',
                    'tile' => $field_tile,
                    'customizable' => 'all',
                    'private' => $field_private
                ];
            } elseif ( $field_type === "multi_select" ){
                $new_field = [
                    'name' => $post_submission["new_field_name"],
                    'default' => [],
                    'type' => 'multi_select',
                    'tile' => $field_tile,
                    'customizable' => 'all',
                    'private' => $field_private,
                ];
            } elseif ( $field_type === "tags" ){
                $new_field = [
                    'name' => $post_submission["new_field_name"],
                    'default' => [],
                    'type' => 'tags',
                    'tile' => $field_tile,
                    'customizable' => 'all',
                    'private' => $field_private
                ];
            } elseif ( $field_type === "date" ){
                $new_field = [
                    'name'        => $post_submission["new_field_name"],
                    'type'        => 'date',
                    'default'     => '',
                    'tile'     => $field_tile,
                    'customizable' => 'all',
                    'private' => $field_private
                ];
            } elseif ( $field_type === "text" ){
                $new_field = [
                    'name'        => $post_submission["new_field_name"],
                    'type'        => 'text',
                    'default'     => '',
                    'tile'     => $field_tile,
                    'customizable' => 'all',
                    'private' => $field_private
                ];
            } elseif ( $field_type === "textarea" ){
                $new_field = [
                    'name'        => $post_submission["new_field_name"],
                    'type'        => 'textarea',
                    'default'     => '',
                    'tile'     => $field_tile,
                    'customizable' => 'all',
                    'private' => $field_private
                ];
            } elseif ( $field_type === "connection" ){
                if ( !$post_submission["connection_target"] ){
                    self::admin_notice( __( "Please select a connection target", 'disciple_tools' ), "error" );
                    return false;
                }
                $p2p_key = $post_type . "_to_" . $post_submission["connection_target"];
                if ( p2p_type( $p2p_key ) !== false ){
                    $p2p_key = dt_create_field_key( $p2p_key, true );
                }

                // connection field to the same post type
                if ( $post_type === $post_submission["connection_target"] ){
                    //default direction to "any". If not multidirectional, then from
                    $direction = isset( $post_submission["multidirectional"] ) ? "any" : "from";
                    $custom_field_options[$post_type][$field_key] = [
                        'name'        => $post_submission["new_field_name"],
                        'type'        => 'connection',
                        "post_type" => $post_submission["connection_target"],
                        "p2p_direction" => $direction,
                        "p2p_key" => $p2p_key,
                        'tile'     => $field_tile,
                        'customizable' => 'all',
                    ];
                    //if not multidirectional, create the reverse direction field
                    if ( !isset( $post_submission["multidirectional"] ) ){
                        $reverse_name = $post_submission["reverse_connection_name"] ?? $post_submission["new_field_name"];
                        $custom_field_options[$post_type][$field_key . "_reverse"]  = [
                            'name'        => $reverse_name,
                            'type'        => 'connection',
                            "post_type" => $post_type,
                            "p2p_direction" => "to",
                            "p2p_key" => $p2p_key,
                            'tile'     => "other",
                            'customizable' => 'all',
                            'hidden' => isset( $post_submission["disable_reverse_connection"] )
                        ];
                    }
                } else {
                    $direction = "from";
                    $custom_field_options[$post_type][$field_key] = [
                        'name'        => $post_submission["new_field_name"],
                        'type'        => 'connection',
                        "post_type" => $post_submission["connection_target"],
                        "p2p_direction" => $direction,
                        "p2p_key" => $p2p_key,
                        'tile'     => $field_tile,
                        'customizable' => 'all',
                    ];
                    //create the reverse fields on the connection post type
                    $reverse_name = $post_submission["other_field_name"] ?? $post_submission["new_field_name"];
                    $custom_field_options[$post_submission["connection_target"]][$field_key]  = [
                        'name'        => $reverse_name,
                        'type'        => 'connection',
                        "post_type" => $post_type,
                        "p2p_direction" => "to",
                        "p2p_key" => $p2p_key,
                        'tile'     => "other",
                        'customizable' => 'all',
                        'hidden' => isset( $post_submission["disable_other_post_type_field"] )
                    ];
                }
            }
            if ( !empty( $new_field ) ){
                $custom_field_options[$post_type][$field_key] = $new_field;
            }
            update_option( "dt_field_customizations", $custom_field_options );
            wp_cache_delete( $post_type . "_field_settings" );
            self::admin_notice( __( "Field added successfully", 'disciple_tools' ), "success" );
            return $field_key;
        }
        return false;
    }

    /**
     * Display admin notice
     * @param $notice string
     * @param $type string error|success|warning
     */
    public static function admin_notice( string $notice, string $type ) {
        ?>
        <div class="notice notice-<?php echo esc_attr( $type ) ?> is-dismissible">
            <p><?php echo esc_html( $notice ) ?></p>
        </div>
        <?php
    }
}
Disciple_Tools_Tab_Custom_Fields::instance();

