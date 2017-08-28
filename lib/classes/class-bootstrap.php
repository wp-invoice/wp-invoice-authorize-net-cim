<?php
/**
 * Bootstrap
 *
 * @since 1.0.0
 */
namespace UsabilityDynamics\WPI_A_CIM {

  if( !class_exists( 'UsabilityDynamics\WPI_A_CIM\Bootstrap' ) ) {

    final class Bootstrap extends \UsabilityDynamics\WP\Bootstrap_Plugin {
      
      /**
       * Singleton Instance Reference.
       *
       * @protected
       * @static
       * @property $instance
       * @type UsabilityDynamics\WPI_A_CIM\Bootstrap object
       */
      protected static $instance = null;

      /**
       * @var array
       */
      private $apply_for = array(
        'wpi_authorize', 'wpi_echeck'
      );
      
      /**
       * Instantaite class.
       */
      public function init() {

        add_action( 'wpi_before_process_payment', array( $this, 'before_payment' ) );
        add_action( 'wpi_after_payment_fields', array( $this, 'after_fields' ) );
        add_action( 'wpi_authorize_payment_success', array( $this, 'authorize_success_payment' ), 10, 2 );

        add_action( 'wp_ajax_wpi_cim_determine_payment_profile', array( $this, 'ajax_determine_payment_profile' ) );
        add_action( 'wp_ajax_nopriv_wpi_cim_determine_payment_profile', array( $this, 'ajax_determine_payment_profile' ) );

      }

      /**
       * @param $transaction
       * @param $_invoice
       */
      public function authorize_success_payment( $transaction, $_invoice ) {

        // We don't need to do it for recurring invoices
        if ( $_invoice['type'] == 'recurring' ) return;

        if ( !$transaction ) return;

        die( $transaction->getTransactionID() );

      }

      /**
       *
       */
      public function ajax_determine_payment_profile() {
        // Do this only for specific gateways
        if ( empty( $_POST['g'] ) || !in_array( $_POST['g'], $this->apply_for ) ) {
          wp_send_json_error( __( 'No payment profile for this gateways.', ud_get_wp_invoice_authorize_net_cim()->domain ) );
        }

        $invoice_id = !empty( $_POST['i'] ) ? absint( $_POST['i'] ) : false;

        if ( !$invoice_id ) wp_send_json_error( __( 'Invoice ID is required.', ud_get_wp_invoice_authorize_net_cim()->domain ) );

        // Here determine if user has payment profile
        wp_send_json_success( 'OK' );
      }

      /**
       * @param $_invoice
       */
      public function before_payment( $_invoice ) {

        // Do this only for specific gateways
        if ( empty( $_REQUEST['type'] ) || !in_array( $_REQUEST['type'], $this->apply_for ) ) return;

        // Here custom payment action goes
        //die( 'ok' );

      }

      /**
       * @param $_invoice
       */
      public function after_fields( $_invoice ) {
        ob_start();
        ?>
        <script type="text/javascript">
          jQuery(document).ready(function(){
            jQuery.post(wpi_ajax.url, {
              action: 'wpi_cim_determine_payment_profile',
              g: jQuery( '#wpi_form_type', 'form.online_payment_form' ).val(),
              i: jQuery( '#wpi_form_invoice_id', 'form.online_payment_form' ).val()
            }).then(function(res) {
              console.log( res );
              if ( res.success ) {}
            });
          });
        </script>
        <?php
        echo ob_get_clean();
      }
      
      /**
       * Plugin Activation
       *
       */
      public function activate() {}
      
      /**
       * Plugin Deactivation
       *
       */
      public function deactivate() {}

    }

  }

}
