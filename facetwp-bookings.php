<?php
/*
Plugin Name: FacetWP - Bookings Integration
Plugin URI: https://facetwp.com/
Description: WooCommerce Bookings support
Version: 0.1
Author: Matt Gibbs

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
        $where = '';

        $start_date = empty( $values[0] ) ? false : $values[0];
        $end_date = empty( $values[1] ) ? false : $values[1];
        $quantity = empty( $values[2] ) ? false : $values[2];

        return $this->get_available_bookings( $start_date, $end_date, $quantity );
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
        return array();
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
<link href="<?php echo FACETWP_URL; ?>/assets/js/bootstrap-datepicker/bootstrap-datepicker.css?ver=1.5.1" rel="stylesheet">
<script src="<?php echo FACETWP_URL; ?>/assets/js/bootstrap-datepicker/bootstrap-datepicker.min.js?ver=1.5.1"></script>
<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/availability', function($this, facet_name) {
        var min = $this.find('.facetwp-date-min').val() || '';
        var max = $this.find('.facetwp-date-max').val() || '';
        var quantity = $this.find('.facetwp-quantity').val() || 1;
        FWP.facets[facet_name] = ('' != min || '' != max) ? [min, max, quantity] : [];
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
