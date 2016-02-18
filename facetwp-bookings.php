<?php
/*
Plugin Name: FacetWP - Bookings Integration
Plugin URI: https://facetwp.com/
Description: WooCommerce Bookings support
Version: 0.1
Author: Matt Gibbs
GitHub Plugin URI: https://github.com/FacetWP/facetwp-bookings

Copyright 2016 Matt Gibbs

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) or exit;

include( dirname( __FILE__ ) . '/install-github-updater.php' );

/**
 * Register facet type
 */
add_filter( 'facetwp_facet_types', function( $facet_types ) {
    $facet_types['availability'] = new FacetWP_Facet_Availability();
    return $facet_types;
});


/**
 * Availability facet
 */
class FacetWP_Facet_Availability
{

    function __construct() {
        $this->label = __( 'Availability', 'fwp' );

        // Store unfiltered IDs
        add_filter( 'facetwp_store_unfiltered_post_ids', '__return_true' );
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {
        $value = $params['selected_values'];
        $value = empty( $value ) ? array( '', '', 1 ) : $value;

        $output = '';
        $output .= '<input type="text" class="facetwp-date facetwp-date-min" value="' . $value[0] . '" placeholder="' . __( 'Start Date', 'fwp' ) . '" />';
        $output .= '<input type="text" class="facetwp-date facetwp-date-max" value="' . $value[1] . '" placeholder="' . __( 'End Date', 'fwp' ) . '" />';
        $output .= '<input type="number" class="facetwp-quantity" value="1" min="' . $value[2] . '" max="20" placeholder="' . __( 'Quantity', 'fwp' ) . '" />';
        $output .= '<input type="submit" class="facetwp-availability-update" value="' . __( 'Update', 'fwp' ) . '" />';
        return $output;
    }


    /**
     * Filter the query based on selected values
     */
    function filter_posts( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $values = $params['selected_values'];

        $start_date = empty( $values[0] ) ? '' : $values[0];
        $end_date = empty( $values[1] ) ? '' : $values[1];
        $quantity = empty( $values[2] ) ? 1 : (int) $values[2];

        if ( $this->is_valid_date( $start_date ) && $this->is_valid_date( $end_date ) ) {
            return $this->get_available_bookings( $start_date, $end_date, $quantity );
        }

        return array();
    }


    /**
     * Get all available booking products
     *
     * @param string $start_date YYYY-MM-DD format
     * @param string $end_date YYYY-MM-DD format
     * @param int $quantity Number of people to book
     * @return array Available post IDs
     */
    function get_available_bookings( $start_date, $end_date, $quantity = 1 ) {
        $matches = array();

        $start = explode( '-', $start_date );
        $end = explode( '-', $end_date );

        $args = array(
            'wc_bookings_field_persons' => $quantity,
            'wc_bookings_field_duration' => 1,
            'wc_bookings_field_start_date_year' => $start[0],
            'wc_bookings_field_start_date_month' => $start[1],
            'wc_bookings_field_start_date_day' => $start[2],
            'wc_bookings_field_start_date_to_year' => $end[0],
            'wc_bookings_field_start_date_to_month' => $end[1],
            'wc_bookings_field_start_date_to_day' => $end[2],
        );

        foreach ( FWP()->unfiltered_post_ids as $post_id ) {
            if ( 'product' == get_post_type( $post_id ) ) {
                $product = wc_get_product( $post_id );
                if ( is_wc_booking_product( $product ) ) {

                    // Support WooCommerce Accomodation Bookings plugin
                    $unit = ( 'accommodation-booking' == $product->product_type ) ? 'night' : 'day';
                    $duration = $this->calculate_duration( $start_date, $end_date, $unit );
                    $args['wc_bookings_field_duration'] = $duration;

                    $booking_form = new WC_Booking_Form( $product );
                    $posted_data = $booking_form->get_posted_data( $args );

                    // returns WP_Error on fail
                    if ( true === $booking_form->is_bookable( $posted_data ) ) {
                        $matches[] = $post_id;
                    }
                }
            }
        }

        return $matches;
    }


    /**
     * Calculate days between 2 date intervals
     *
     * @requires PHP 5.3+
     */
    function calculate_duration( $start_date, $end_date, $unit = 'day' ) {
        if ( $start_date > $end_date ) {
            return 0;
        }
        if ( $start_date == $end_date ) {
            return 1;
        }

        $start = new DateTime( $start_date );
        $end = new DateTime( $end_date );
        $diff = (int) $end->diff( $start )->format( '%a' );
        return ( 'day' == $unit ) ? $diff + 1 : $diff;
    }


    /**
     * Validate date input
     */
    function is_valid_date( $date ) {
        if ( empty( $date ) ) {
            return false;
        }

        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) == $date;
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts() {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/change/availability', function($this) {
        $this.closest('.facetwp-row').find('.name-source').hide();
    });

    wp.hooks.addFilter('facetwp/save/availability', function($this, obj) {
        return obj;
    });
})(jQuery);
</script>
<?php
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts() {
?>
<link href="<?php echo FACETWP_URL; ?>/assets/js/bootstrap-datepicker/bootstrap-datepicker.css?ver=1.6.0" rel="stylesheet">
<script src="<?php echo FACETWP_URL; ?>/assets/js/bootstrap-datepicker/bootstrap-datepicker.min.js?ver=1.6.0"></script>
<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/availability', function($this, facet_name) {
        var min = $this.find('.facetwp-date-min').val() || '';
        var max = $this.find('.facetwp-date-max').val() || '';
        var quantity = $this.find('.facetwp-quantity').val() || 1;
        FWP.facets[facet_name] = ('' != min && '' != max) ? [min, max, quantity] : [];
    });

    wp.hooks.addFilter('facetwp/selections/availability', function(output, params) {
        return params.selected_values[0] + ' - ' + params.selected_values[1];
    });

    wp.hooks.addAction('facetwp/ready', function() {
        $(document).on('facetwp-loaded', function() {
            $('.facetwp-date').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                clearBtn: true
            });
        });

        $(document).on('click', '.facetwp-availability-update', function() {
            FWP.refresh();
        });
    });
})(jQuery);
</script>
<?php
    }
}
