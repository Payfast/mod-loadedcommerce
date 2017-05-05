<?php
/**
 *
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 *
 * @author     Brendon Posen
 */

class lC_Payment_payfast extends lC_Payment
{
    /**
     * The public title of the payment module
     *
     * @var string
     * @access protected
     */
    protected $_title;
    /**
     * The code of the payment module
     *
     * @var string
     * @access protected
     */
    protected $_code = 'payfast';
    /**
     * The status of the module
     *
     * @var boolean
     * @access protected
     */
    protected $_status = false;
    /**
     * The sort order of the module
     *
     * @var integer
     * @access protected
     */
    protected $_sort_order;
    /**
     * Constructor
     */
    public function lC_Payment_payfast()
    {
        global $lC_Database, $lC_Language, $lC_ShoppingCart;

        $this->_title = $lC_Language->get( 'payment_payfast_title' );
        $this->_method_title = $lC_Language->get( 'payment_payfast_method_title' );
        $this->_status = ( defined( 'ADDONS_PAYMENT_PAYFAST_STATUS' ) && ( ADDONS_PAYMENT_PAYFAST_STATUS == '1' ) ? true : false );
        $this->_sort_order = ( defined( 'ADDONS_PAYMENT_PAYFAST_SORT_ORDER' ) ? ADDONS_PAYMENT_PAYFAST_SORT_ORDER : null );

        if ( defined( 'ADDONS_PAYMENT_PAYFAST_STATUS' ) )
        {
            $this->initialize();
        }
    }

    public function initialize()
    {
        global $lC_Database, $lC_Language, $order;

        if ( ( int )ADDONS_PAYMENT_PAYFAST_ORDER_STATUS_ID > 0 )
        {
            $this->order_status = ADDONS_PAYMENT_PAYFAST_ORDER_STATUS_ID;
        }
        else
        {
            $this->order_status = 0;
        }

        if ( is_object( $order ) ) $this->update_status();
        if ( defined( 'ADDONS_PAYMENT_PAYFAST_TEST_MODE' ) && ADDONS_PAYMENT_PAYFAST_TEST_MODE == '1' )
        {
            $this->form_action_url = 'https://sandbox.payfast.co.za/eng/process';  // sandbox url
        }
        else
        {
            $this->form_action_url = 'https://www.payfast.co.za/eng/process';  // production url
        }
    }

    /**
     * Disable module if zone selected does not match billing zone
     *
     * @access public
     * @return void
     */
    public function update_status()
    {
        global $lC_Database, $order;

        if ( ( $this->_status === true ) && ( ( int )ADDONS_PAYMENT_PAYFAST_ZONE > 0 ) )
        {
            $check_flag = false;

            $Qcheck = $lC_Database->query( 'select zone_id from :table_zones_to_geo_zones where geo_zone_id = :geo_zone_id and zone_country_id = :zone_country_id order by zone_id' );
            $Qcheck->bindTable( ':table_zones_to_geo_zones', TABLE_ZONES_TO_GEO_ZONES );
            $Qcheck->bindInt( ':geo_zone_id', ADDONS_PAYMENT_PAYFAST_ZONE );
            $Qcheck->bindInt( ':zone_country_id', $order->billing['country']['id'] );
            $Qcheck->execute();

            while ( $Qcheck->next() )
            {
                if ( $Qcheck->valueInt( 'zone_id' ) < 1 )
                {
                    $check_flag = true;
                    break;
                }
                elseif ( $Qcheck->valueInt( 'zone_id' ) == $order->billing['zone_id'] )
                {
                    $check_flag = true;
                    break;
                }
            }

            if ( $check_flag == false )
            {
                $this->_status = false;
            }
        }
    }

    /**
     * Return the payment selections array
     *
     * @access public
     * @return array
     */
    public function selection()
    {
        global $lC_Language;

        $selection = array( 'id' => $this->_code,
            'module' => '<div class="payment-selection">' . $lC_Language->get('payment_payfast_method_title') . '<span style="margin-left:6px;">' . lc_image('addons/PayFast/images/payfast.png', null, null, null, 'style="vertical-align:middle;"') . '</span></div><div class="payment-selection-title"></div>');

        return $selection;
    }


    /**
     * Perform any pre-confirmation logic
     *
     * @access public
     * @return boolean
     */
    public function pre_confirmation_check()
    {
        return false;
    }

    /**
     * Perform any post-confirmation logic
     *
     * @access public
     * @return integer
     */
    public function confirmation()
    {
        return false;
    }

    /**
     * Return the confirmation button logic
     *
     * @access public
     * @return string
     */
    public function process_button()
    {

        if( isset( $_SESSION['cartSync'] ) )
        {
            lC_Order::remove( $_SESSION['cartSync']['orderID'] );
            unset( $_SESSION['cartSync']['paymentMethod'] );
            unset( $_SESSION['cartSync']['prepOrderID'] );
            unset( $_SESSION['cartSync']['orderCreated'] );
            unset( $_SESSION['cartSync']['orderID'] );
        }

        $order_id = lC_Order::insert( $this->order_status );
        $_SESSION['cartSync']['paymentMethod'] = $this->_code;
        // store the cartID info to match up on the return - to prevent multiple order IDs being created
        $_SESSION['cartSync']['cartID'] = $_SESSION['cartID'];
        $_SESSION['cartSync']['prepOrderID'] = $_SESSION['prepOrderID'];
        $_SESSION['cartSync']['orderCreated'] = TRUE;
        $_SESSION['cartSync']['orderID'] = $order_id;

        echo $this->_payfast_params();

        return false;
    }

    /**
     * Return the confirmation button logic
     *
     * @access public
     * @return string
     */
    private function _payfast_params()
    {
        global $lC_Language, $lC_ShoppingCart, $lC_Currencies, $lC_Customer;

        $upload         = 0;
        $no_shipping    = '1';
        $redirect_cmd   = '';
        $handling_cart  = '';
        $item_name      = '';
        $shipping       = '';

        // get the shipping amount
        $taxTotal       = 0;
        $shippingTotal  = 0;
        foreach ( $lC_ShoppingCart->getOrderTotals() as $ot )
        {
            if ( $ot['code'] == 'shipping' ) $shippingTotal = ( float )$ot['value'];
            if ( $ot['code'] == 'tax' ) $taxTotal = ( float )$ot['value'];
        }

        $shoppingcart_products = $lC_ShoppingCart->getProducts();

        $amount = $lC_Currencies->formatRaw( $lC_ShoppingCart->getSubTotal(), $lC_Currencies->getCode() );

        $discount_amount_cart = 0;
        foreach ( $lC_ShoppingCart->getOrderTotals() as $module )
        {
            if( $module['code'] == 'coupon' )
            {
                $discount_amount_cart = $module['value'];
            }
        }

        $order_id = ( isset($_SESSION['prepOrderID'] ) && $_SESSION['prepOrderID'] != NULL ) ? end( explode('-', $_SESSION['prepOrderID'] ) ) : 0;
        if ( $order_id == 0 ) $order_id = ( isset( $_SESSION['cartSync']['orderID'] ) && $_SESSION['cartSync']['orderID'] != NULL ) ? $_SESSION['cartSync']['orderID'] : 0;

        $return_href_link = lc_href_link( FILENAME_CHECKOUT, 'process', 'AUTO', true, true, true );
        $cancel_href_link = lc_href_link( FILENAME_CHECKOUT, 'cart', 'AUTO', true, true, true );
        $notify_href_link = lc_href_link( 'addons/PayFast/payfast_itn.php', 'itn_order_id=' . $order_id, 'AUTO', true, true, true );
        $merchant_id = ADDONS_PAYMENT_PAYFAST_TEST_MODE == 1 ? '10000100' : ADDONS_PAYMENT_PAYFAST_MERCHANT_ID;
        $merchant_key = ADDONS_PAYMENT_PAYFAST_TEST_MODE == 1 ? '46f0cd694581a' : ADDONS_PAYMENT_PAYFAST_MERCHANT_KEY;

        $payfast_params = array(
            'merchant_id' => $merchant_id,
            'merchant_key' => $merchant_key,
            'return_url' => $return_href_link,
            'cancel_url' => $cancel_href_link,
            'notify_url' => $notify_href_link,
            'name_first' => $lC_ShoppingCart->getBillingAddress('firstname'),
            'name_last' => $lC_ShoppingCart->getBillingAddress('lastname'),
            'email_address' => $lC_Customer->getEmailAddress(),
            'm_payment_id' => $order_id,
            'amount' => $amount,
            'item_name' => STORE_NAME
        );

        $pfOutput = '';
        // Create output string
        foreach( $payfast_params as $key => $value )
            $pfOutput .= $key .'='. urlencode( trim( $value ) ) .'&';

        $passPhrase = trim( ADDONS_PAYMENT_PAYFAST_PASSPHRASE );

        if ( empty( $passPhrase ) || ( ADDONS_PAYMENT_PAYFAST_TEST_MODE == 1 ) )
        {
            $pfOutput = substr( $pfOutput, 0, -1 );
        }
        else
        {
            $pfOutput = $pfOutput."passphrase=".urlencode( $passPhrase );
        }

        $payfast_params['signature'] = md5( $pfOutput );
        $payfast_params['user_agent'] = 'Loaded Commerce v7.0';

        foreach( $payfast_params as $name => $value )
        {
            $payfast_params .= lc_draw_hidden_field( $name, $value );
        }
        return $payfast_params;
    }

    /**
     * Parse the response from the processor
     *
     * @access public
     * @return string
     */
    public function process()
    {
        // performed by payfast_itn.php
    }

    public function setTransactionID( $amount )
    {
        global $lC_Language, $lC_ShoppingCart, $lC_Currencies, $lC_Customer;
        $my_currency = $lC_Currencies->getCode();
        $trans_id = STORE_NAME . date('Ymdhis');
        $digest = md5( $trans_id . number_format( $amount * $lC_Currencies->value( $my_currency ), $lC_Currencies->decimalPlaces( $my_currency ), '.', '' ) . ADDONS_PAYMENT_PAYFAST_ITN_DIGEST_KEY );
        return $digest;
    }
}
?>