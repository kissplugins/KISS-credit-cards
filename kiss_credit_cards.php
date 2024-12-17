<?php
/**
 * Plugin Name: Accepted Credit Cards
 * Description: Lets site admin select which credit card icons (Visa, Mastercard, American Express, Discover, PayPal) to display via a shortcode [accepted-cards].
 * Version: 1.0.0
 * Author: Hypercart
 * Author URI: https://kissplugins.com
 * Text Domain: hypercart-accepted-cards
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Accepted_Credit_Cards_Plugin {
    private $options;
    private $option_name = 'accepted_credit_cards_settings';

    public function __construct() {
        // Load stored options
        $this->options = get_option( $this->option_name );

        // Add admin menu page
        add_action( 'admin_menu', array( $this, 'add_admin_page' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'page_init' ) );

        // Add shortcode
        add_shortcode( 'accepted-cards', array( $this, 'render_accepted_cards' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'load_enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_enqueue_scripts' ) );
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_settings_link'));
    }

    public function plugin_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=accepted-credit-cards">' . __('Settings', 'hypercart-accepted-cards') . '</a>';
        $links['settings'] = $settings_link; // Add the settings link at the end
        return $links;
    }

    public function add_admin_page() {
        add_options_page(
            'Accepted Credit Cards',
            'Accepted Credit Cards',
            'manage_options',
            'accepted-credit-cards',
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        // Set class property
        $this->options = get_option( $this->option_name ); 
        ?>
        <div class="wrap">
            <h1>Accepted Credit Cards Settings</h1>
            <form method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'accepted_cards_group' );
                    do_settings_sections( 'accepted-credit-cards' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'accepted_cards_group', // Option group
            $this->option_name,     // Option name
            array( $this, 'sanitize' ) // Sanitize callback
        );

        add_settings_section(
            'accepted_cards_section', // ID
            'Select Accepted Cards',  // Title
            null,                     // Callback
            'accepted-credit-cards'   // Page
        );

        // Cards
        $cards = array(
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'discover' => 'Discover',
            'paypal' => 'PayPal'
        );

        foreach ( $cards as $key => $label ) {
            add_settings_field(
                $key.'_field', // ID
                $label,        // Title
                array( $this, 'checkbox_callback' ), // Callback
                'accepted-credit-cards', // Page
                'accepted_cards_section', // Section
                array(
                    'label_for' => $key.'_field',
                    'option_key' => $key
                )
            );
        }

        // Size field
        add_settings_field(
            'icon_size', // ID
            'Icon Size', // Title
            array( $this, 'icon_size_callback' ), // Callback
            'accepted-credit-cards', // Page
            'accepted_cards_section', // Section
            array( 'label_for' => 'icon_size' ) // Arguments
        );
        

        // Color field
        add_settings_field(
            'icon_color',
            'Icon Color',
            array( $this, 'color_picker_callback' ),
            'accepted-credit-cards',
            'accepted_cards_section',
            array(
                'label_for' => 'icon_color'
            )
        );
    }

    public function sanitize( $input ) {
        $new_input = array();
        $allowed_keys = array('visa','mastercard','amex','discover','paypal','icon_color','icon_size');
        foreach ($allowed_keys as $key) {
            if ( isset( $input[ $key ] ) ) {
                if ( $key == 'icon_color' ) {
                    // Sanitize color values to a valid HEX
                    $new_input[$key] = preg_match('/^#[a-f0-9]{6}$/i', $input[$key]) ? $input[$key] : '#000000';
                } elseif ( $key == 'icon_size' ) {
                    $new_input[ $key ] = preg_match( '/^\d+(px|em|rem|%)$/', $input[ $key ] ) ? $input[ $key ] : '32px';
                } else {
                    $new_input[$key] = (bool)$input[$key];
                }
            } else {
                // If not set and it's not color (boolean fields), set to false
                if ($key !== 'icon_color' || $key !== 'icon_size') {
                    $new_input[$key] = false;
                }
            }
        }

        return $new_input;
    }

    public function checkbox_callback( $args ) {
        $option_key = $args['option_key'];
        $checked = ( isset( $this->options[$option_key] ) && $this->options[$option_key] ) ? 'checked' : '';
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[%3$s]" %4$s value="1" />',
            esc_attr($args['label_for']),
            esc_attr($this->option_name),
            esc_attr($option_key),
            $checked
        );
    }

    public function icon_size_callback( $args ) {
        $icon_size = isset( $this->options['icon_size'] ) ? $this->options['icon_size'] : '32px';
        printf(
            '<input type="text" id="%1$s" name="%2$s[icon_size]" value="%3$s" class="kiss-size-field" data-default-size="32px" />',
            esc_attr( $args['label_for'] ),
            esc_attr( $this->option_name ),
            esc_attr( $icon_size )
        );
    }
    

    public function color_picker_callback( $args ) {
        $color = ( isset( $this->options['icon_color'] ) ) ? $this->options['icon_color'] : '#000000';
        printf(
            '<input type="text" id="%1$s" name="%2$s[icon_color]" value="%3$s" class="kiss-color-field" data-default-color="#000000" />',
            esc_attr($args['label_for']),
            esc_attr($this->option_name),
            esc_attr($color)
        );
    }

    public function load_enqueue_scripts() {
        wp_enqueue_style( 
            'kiss-font-awesome', 
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css', 
            array(), 
            '6.5.0' 
        );
    }
    public function load_admin_enqueue_scripts($hook) {
        // Only load on our settings page
        if ( 'settings_page_accepted-credit-cards' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'accepted-cards-color-picker', plugins_url( 'accepted-cards-color.js', __FILE__ ), array( 'wp-color-picker' ), false, true );
    }

    public function render_accepted_cards() {
        $this->options = get_option( $this->option_name );
        $icon_color = isset( $this->options['icon_color'] ) ? $this->options['icon_color'] : '#000000';
        $icon_size = isset( $this->options['icon_size'] ) ? $this->options['icon_size'] : '32px';

        $cards = array(
            'visa'       => 'fa-cc-visa',
            'mastercard' => 'fa-cc-mastercard',
            'amex'       => 'fa-cc-amex',
            'discover'   => 'fa-cc-discover',
            'paypal'     => 'fa-cc-paypal'
        );

        $output = '<div class="accepted-cards" style="display:flex; gap:10px; align-items:center;">';

        foreach ( $cards as $key => $fa_class ) {
            if ( isset( $this->options[$key] ) && $this->options[$key] ) {
                $output .= '<span class="fab ' . esc_attr($fa_class) . '" style="color:' . esc_attr($icon_color) . '; font-size:' . esc_attr($icon_size) . ';"></span>';
            }
        }

        $output .= '</div>';

        return $output;
    }
}

//if ( is_admin() ) {
    new Accepted_Credit_Cards_Plugin();
//}

// Enqueue color picker script initialization
add_action( 'admin_print_footer_scripts', 'accepted_cards_color_picker_script' );
function accepted_cards_color_picker_script() {
    ?>
    <script type="text/javascript">
        (function($){
            $(function(){
                $('.kiss-color-field').wpColorPicker();
            });
        })(jQuery);
    </script>
    <?php
}