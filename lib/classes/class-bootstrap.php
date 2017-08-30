<?php
/**
 * Bootstrap
 *
 * @since 1.0.0
 */
namespace UsabilityDynamics\WPI_A_CIM {

  use net\authorize\api\contract\v1 as AnetAPI;
  use net\authorize\api\controller as AnetController;

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
       * @var string
       */
      private $payment_profile_meta_key = 'wpi_authorize_cim_profile_id';
      
      /**
       * Instantaite class.
       */
      public function init() {

        add_action( 'wpi_before_process_payment', array( $this, 'before_payment' ) );
        add_action( 'wpi_after_payment_fields', array( $this, 'after_fields' ) );
        add_action( 'wpi_authorize_payment_success', array( $this, 'authorize_success_payment' ), 10, 2 );
        add_action( 'wpi_echeck_payment_success', array( $this, 'echeck_success_payment' ), 10, 2 );

      }

      /**
       * @param $transaction
       * @param $_invoice
       */
      public function echeck_success_payment( $transaction, $_invoice ) {

        if ( empty( $_POST['wpi_cim_save_payment_data'] ) || $_POST['wpi_cim_save_payment_data'] != 'true' ) return;

        // We don't need to do it for recurring invoices
        if ( $_invoice['type'] == 'recurring' ) return;

        if ( empty( $transaction ) ) return;

        $transactionID = $transaction->getTransId();

        if ( empty( $transactionID ) ) return;

        $settings = $_invoice[ 'billing' ][ 'wpi_echeck' ][ 'settings' ];

        $mode = $settings['gateway_test_mode']['value'] == 'TRUE'
            ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
            : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName( $settings['gateway_username']['value'] );
        $merchantAuthentication->setTransactionKey( $settings['gateway_tran_key']['value'] );

        if ( $existingProfileID = get_user_meta( $_invoice[ 'user_data' ][ 'ID' ], $this->payment_profile_meta_key, 1 ) ) {
          $bankAccount = new AnetAPI\BankAccountType();
          $bankAccount->setAccountNumber( $_POST['cc_data']['bank_acct_num'] );
          $bankAccount->setAccountType( $_POST['cc_data']['bank_acct_type'] );
          $bankAccount->setEcheckType( $_POST['cc_data']['echeck_type'] );
          $bankAccount->setNameOnAccount( $_POST['cc_data']['bank_acct_name'] );
          $bankAccount->setRoutingNumber( $_POST['cc_data']['bank_aba_code'] );
          $paymentBankAccount = new AnetAPI\PaymentType();
          $paymentBankAccount->setBankAccount($bankAccount);

          $paymentprofile = new AnetAPI\CustomerPaymentProfileType();
          $paymentprofile->setPayment($paymentBankAccount);

          $paymentprofilerequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
          $paymentprofilerequest->setMerchantAuthentication($merchantAuthentication);

          $paymentprofilerequest->setCustomerProfileId($existingProfileID);
          $paymentprofilerequest->setPaymentProfile($paymentprofile);

          $controller = new AnetController\CreateCustomerPaymentProfileController($paymentprofilerequest);
          $response = $controller->executeWithApiResponse($mode);
        } else {

          $customerProfile = new AnetAPI\CustomerProfileBaseType();
          $customerProfile->setMerchantCustomerId($_invoice['user_data']['ID']);
          $customerProfile->setEmail($_invoice['user_data']['user_email']);
          $customerProfile->setDescription("Created from WP-Invoice Authorize.net CIM. Invoice ID: {$_invoice['invoice_id']}.");

          $request = new AnetAPI\CreateCustomerProfileFromTransactionRequest();
          $request->setMerchantAuthentication($merchantAuthentication);
          $request->setTransId($transactionID);

          $request->setCustomer($customerProfile);

          $controller = new AnetController\CreateCustomerProfileFromTransactionController($request);

          $response = $controller->executeWithApiResponse($mode);

          if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            if ($response->getCustomerProfileId()) {
              update_user_meta($_invoice['user_data']['ID'], $this->payment_profile_meta_key, $response->getCustomerProfileId());
            }
          }
        }
      }

      /**
       * On Authorize successful payment - create customer profile based on last transaction
       * @param $transaction
       * @param $_invoice
       */
      public function authorize_success_payment( $transaction, $_invoice ) {

        if ( empty( $_POST['wpi_cim_save_payment_data'] ) || $_POST['wpi_cim_save_payment_data'] != 'true' ) return;

        // We don't need to do it for recurring invoices
        if ( $_invoice['type'] == 'recurring' ) return;

        if ( empty( $transaction ) ) return;

        $transactionID = $transaction->getTransactionID();

        if ( empty( $transactionID ) ) return;

        $settings = $_invoice[ 'billing' ][ 'wpi_authorize' ][ 'settings' ];

        $mode = strstr($settings['gateway_url']['value'], 'test')
            ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
            : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName( $settings['gateway_username']['value'] );
        $merchantAuthentication->setTransactionKey( $settings['gateway_tran_key']['value'] );

        if ( $existingProfileID = get_user_meta( $_invoice[ 'user_data' ][ 'ID' ], $this->payment_profile_meta_key, 1 ) ) {
          $creditCard = new AnetAPI\CreditCardType();
          $creditCard->setCardNumber($_POST['cc_data']['card_num']);
          $creditCard->setExpirationDate("{$_POST['cc_data']['exp_year']}-{$_POST['cc_data']['exp_month']}");
          $creditCard->setCardCode($_POST['cc_data']['card_code']);
          $paymentCreditCard = new AnetAPI\PaymentType();
          $paymentCreditCard->setCreditCard($creditCard);

          $paymentprofile = new AnetAPI\CustomerPaymentProfileType();
          $paymentprofile->setPayment($paymentCreditCard);

          $paymentprofilerequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
          $paymentprofilerequest->setMerchantAuthentication($merchantAuthentication);

          $paymentprofilerequest->setCustomerProfileId($existingProfileID);
          $paymentprofilerequest->setPaymentProfile($paymentprofile);

          $controller = new AnetController\CreateCustomerPaymentProfileController($paymentprofilerequest);
          $response = $controller->executeWithApiResponse($mode);
        } else {
          $customerProfile = new AnetAPI\CustomerProfileBaseType();
          $customerProfile->setMerchantCustomerId( $_invoice[ 'user_data' ][ 'ID' ] );
          $customerProfile->setEmail( $_invoice[ 'user_data' ][ 'user_email' ] );
          $customerProfile->setDescription( "Created from WP-Invoice Authorize.net CIM. Invoice ID: {$_invoice['invoice_id']}." );

          $request = new AnetAPI\CreateCustomerProfileFromTransactionRequest();
          $request->setMerchantAuthentication($merchantAuthentication);
          $request->setTransId($transactionID);

          $request->setCustomer($customerProfile);

          $controller = new AnetController\CreateCustomerProfileFromTransactionController($request);

          $response = $controller->executeWithApiResponse( $mode );

          if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") ) {
            if ( $response->getCustomerProfileId() ) {
              update_user_meta( $_invoice[ 'user_data' ][ 'ID' ], $this->payment_profile_meta_key, $response->getCustomerProfileId() );
            }
          }
        }

      }

      /**
       * Get payment profile for current invoice and user.
       */
      public function get_payment_profiles( $type, $invoice_id ) {

        // Do this only for specific gateways
        if ( empty( $type ) || !in_array( $type, $this->apply_for ) ) {
          return false;
        }

        $the_invoice = new \WPI_Invoice();
        $the_invoice->load_invoice(array('id' => $invoice_id));

        if ( empty( $the_invoice->data['user_data']['user_email'] ) ) return;

        $settings = $the_invoice->data[ 'billing' ][ $type ][ 'settings' ];

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName( $settings['gateway_username']['value'] );
        $merchantAuthentication->setTransactionKey( $settings['gateway_tran_key']['value'] );

        $request = new AnetAPI\GetCustomerProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setEmail($the_invoice->data['user_email']);

        $controller = new AnetController\GetCustomerProfileController($request);

        switch( $type ) {

          case 'wpi_authorize':
            $mode = strstr($settings['gateway_url']['value'], 'test')
                ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
            break;

          case 'wpi_echeck':
            $mode = $settings['gateway_test_mode']['value'] == 'TRUE'
                ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                : \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
            break;

          default:
            $mode = \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
            break;
        }

        $response = $controller->executeWithApiResponse( $mode );

        $paymentProfiles = array();
        if ( ( $response != null ) && ( $response->getMessages()->getResultCode() == "Ok" ) ) {
          $responsePaymentProfiles = $response->getProfile()->getPaymentProfiles();
          if ( !empty( $responsePaymentProfiles ) && is_array( $responsePaymentProfiles ) ) {
            foreach( $responsePaymentProfiles as $paymentProfile ) {
              $creditCard = $paymentProfile->getPayment()->getCreditCard();
              $bankAccount = $paymentProfile->getPayment()->getBankAccount();
              $paymentProfiles[$paymentProfile->getCustomerPaymentProfileId()] = array(
                'credit_card' => $creditCard ? new CreditCardPaymentAccount($creditCard) : false,
                'bank_account' => $bankAccount ? new BankAccountPaymentProfile($bankAccount) : false
              );
            }
          }
        } else {
          return false;
        }

        if ( empty( $paymentProfiles ) ) return false;

        return $paymentProfiles;
      }

      /**
       * @param $_invoice
       */
      public function before_payment( $_invoice ) {

        // Do this only for specific gateways
        if ( empty( $_REQUEST['type'] ) || !in_array( $_REQUEST['type'], $this->apply_for ) ) return;

        if ( empty( $_POST['payment_profile'] ) || $_POST['payment_profile'] == '0' ) return;


        // Here custom payment action goes
        die( 'ok' );

      }

      /**
       * @param $_invoice
       */
      public function after_fields( $_invoice ) {

        $gateway = !empty( $_POST['type'] ) ? $_POST['type'] : $_invoice['default_payment_method'];

        if ( !in_array( $gateway, $this->apply_for ) ) return;

        $profiles = $this->get_payment_profiles( $gateway, $_invoice['invoice_id'] );

        ob_start();

        $options = array();
        if ( !empty( $profiles ) && is_array( $profiles ) ) {
          foreach( $profiles as $profile_id => $profile_data ) {
            switch( $gateway ) {
              case 'wpi_echeck':
                if ( !empty( $profile_data['bank_account'] ) )
                  $options[$profile_id] = $profile_data['bank_account'];
                break;
              case 'wpi_authorize':
                if ( !empty( $profile_data['credit_card'] ) )
                  $options[$profile_id] = $profile_data['credit_card'];
                break;
            }
          }
        }

        if ( !empty( $options ) && is_array( $options ) ):

          ?>
          <div class="wpi_checkout_row">
            <label>
              <input id="wpi_cim_save_payment_data" type="checkbox" value="true" name="wpi_cim_save_payment_data" />
              <?php _e( 'Securely save for future use', ud_get_wp_invoice_authorize_net_cim()->domain ); ?>
            </label>
          </div>

          <ul class="wpi_checkout_block">
            <li class="wpi_checkout_row">
              <div class="wpi_authorize_cim">
                <div class="control-group">
                  <label class="control-label" for="saved_method"><?php _e( 'Saved method', ud_get_wp_invoice_authorize_net_cim()->domain ); ?></label>
                  <div class="controls">
                    <select id="saved_method" name="payment_profile">
                      <option value="0"><?php _e( 'Select option', ud_get_wp_invoice_authorize_net_cim()->domain ); ?></option>
                      <?php
                      foreach( $options as $profile_id => $option ):
                        ?>
                        <option value="<?php echo $profile_id ?>"><?php echo implode( ' / ', array_filter((array)$option) ); ?></option>
                        <?php
                      endforeach;
                      ?>
                    </select>
                  </div>
                </div>
              </div>
            </li>
          </ul>

          <script type="text/javascript">
            jQuery(document).ready(function(){
              var ids_to_disable = [
                  '#wpi_cim_save_payment_data',
                  '#bank_aba_code',
                  '#bank_acct_num',
                  '#bank_acct_type',
                  '#bank_acct_name',
                  '#echeck_type',
                  '.credit_card_number',
                  '.exp_month',
                  '.exp_year',
                  '[name="cc_data[card_code]"]'
              ];
              jQuery('body').off('change').on( 'change', '#saved_method', function() {
                if ( jQuery( this).val() == '0' ) {
                  for(var i in ids_to_disable) {
                    jQuery( ids_to_disable[i] ).removeAttr('disabled').parents('.wpi_checkout_row').show();
                  }
                } else {
                  for(var i in ids_to_disable) {
                    jQuery( ids_to_disable[i] ).attr('disabled', 'disabled').parents('.wpi_checkout_row').hide();
                  }
                }
              });
              jQuery('#saved_method', 'body').change();
            });
          </script>

          <?php
        endif;

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

    /**
     * Class BankAccountPaymentProfile
     * @package UsabilityDynamics\WPI_A_CIM
     */
    class BankAccountPaymentProfile {

      public $accountType;
      public $routingNumber;
      public $accountNumber;
      public $nameOnAccount;
      public $echeckType;
      public $bankName;

      /**
       * BankAccountPaymentProfile constructor.
       * @param $profile
       */
      public function __construct( $profile ) {
        $this->accountType = $profile->getAccountType();
        $this->routingNumber = $profile->getRoutingNumber();
        $this->accountNumber = $profile->getAccountNumber();
        $this->nameOnAccount = $profile->getNameOnAccount();
        $this->echeckType = $profile->getEcheckType();
        $this->bankName = $profile->getBankName();
      }
    }

    /**
     * Class CreditCardPaymentAccount
     * @package UsabilityDynamics\WPI_A_CIM
     */
    class CreditCardPaymentAccount {

      public $cardNumber;
      public $expirationDate;
      public $cardType;
      public $cardArt;

      /**
       * CreditCardPaymentAccount constructor.
       * @param $profile
       */
      public function __construct( $profile ) {
        $this->cardNumber = $profile->getCardNumber();
        $this->expirationDate = $profile->getExpirationDate();
        $this->cardType = $profile->getCardType();
        $this->cardArt = $profile->getCardArt();
      }
    }

  }

}
