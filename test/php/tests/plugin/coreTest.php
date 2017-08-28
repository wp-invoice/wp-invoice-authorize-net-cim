<?php
/**
 * Be sure plugin's core is loaded.
 *
 * @class CoreTest
 */
class CoreTest extends UD_Plugin_WP_UnitTestCase {

  /**
   * 
   * @group core
   */
  function testGetInstance() {
    $this->assertTrue( function_exists( 'ud_get_wp_invoice_authorize_net_cim' ) );
    $data = ud_get_wp_invoice_authorize_net_cim();
    $this->assertTrue( is_object( $data ) && get_class( $data ) == 'UsabilityDynamics\WPI_A_CIM\Bootstrap' );
  }
  
  /**
   *
   * @group core
   */
  function testInstance() {
    $this->assertTrue( is_object( $this->instance ) );
  }
  
}
