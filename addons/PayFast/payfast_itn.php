<?php
/**
 *
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 *
 * @author     Brendon Posen
  */

require( '../../includes/application_top.php' );
require_once( $lC_Vqmod->modCheck( DIR_FS_CATALOG . 'includes/classes/order.php' ) );
require_once( $lC_Vqmod->modCheck( DIR_FS_CATALOG . 'includes/classes/xml.php' ) );
require_once( 'payfast_common.inc' );

ini_set( 'log_errors', true );
ini_set( 'error_log', DIR_FS_WORK . 'logs/payfast.log' );

$itn_order_id = $_POST['m_payment_id'];

$order = new lC_Order( $itn_order_id );
$orderAmount = $order->info['total'];
$amount = ltrim( $orderAmount, 'R' );
$currency = 'ZAR';

$pfError = false;
$pfErrMsg = '';
$pfDone = false;
$pfData = array();
$pfHost = ( ( ADDONS_PAYMENT_PAYFAST_TEST_MODE == 1 ) ? 'sandbox' : 'www' ) . '.payfast.co.za';
$pfOrderId = '';
$pfParamString = '';

pflog( 'PayFast ITN call received' );

//// Notify PayFast that information has been received
if( !$pfError && !$pfDone )
{
    header( 'HTTP/1.0 200 OK' );
    flush();
}

//// Get data sent by PayFast
if( !$pfError && !$pfDone )
{
    pflog( 'Get posted data' );

    // Posted variables from ITN
    $pfData = pfGetData();

    pflog( 'PayFast Data: '. print_r( $pfData, true ) );

    if( $pfData === false )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_BAD_ACCESS;
    }
}

//// Verify security signature
if( !$pfError && !$pfDone )
{
    pflog( 'Verify security signature' );

    $passPhrase = trim( ADDONS_PAYMENT_PAYFAST_PASSPHRASE );
    $pfPassPhrase = empty( $passPhrase ) ? null : $passPhrase;

    // If signature different, log for debugging
    if( !pfValidSignature( $pfData, $pfParamString, $pfPassPhrase ) )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
    }
}

//// Verify source IP (If not in debug mode)
if( !$pfError && !$pfDone )
{
    pflog( 'Verify source IP' );

    if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
    }
}

//// Verify data received
    if( !$pfError )
    {
        pflog( 'Verify data received' );

        $pfValid = pfValidData( $pfHost, $pfParamString );

       if( !$pfValid )
       {
           $pfError = true;
           $pfErrMsg = PF_ERR_BAD_ACCESS;
       }
    }

//// Check amounts
if( !$pfError && !$pfDone )
{
     pflog( 'Check amounts');

    if( $_POST['amount_gross'] != $amount )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
    }
}

if( $pfError )
{
    pflog( 'Error occurred: '. $pfErrMsg );
}

//// Check status and update order
if( /*!$pfError &&*/ !$pfDone )
{
    pflog('Check Status and Update Order');

   // $paymentStatus = $listener->paymentStatus();
    $paymentStatus = $pfData['payment_status'];
    // update order status
    switch ( $paymentStatus )
    {
        case 'COMPLETE':
                $_order_status = ADDONS_PAYMENT_PAYFAST_ORDER_DEFAULT_STATUS_ID;
            break;
        case 'Pending':
        case 'Failed':
            $_order_status = ADDONS_PAYMENT_PAYFAST_ONHOLD_STATUS_ID;
            break;
        case 'Denied':
            $_order_status = ADDONS_PAYMENT_PAYFAST_CANCELED_STATUS_ID;
            break;
        default:
            $_order_status = ADDONS_PAYMENT_PAYFAST_PROCESSING_STATUS_ID;
    }

    lC_Order::process( $itn_order_id, $_order_status );
    $response_array['root']['transaction_response'] = 'VERIFIED';
    $itn_transaction_response = 'VERIFIED';
    if ( defined( 'ADDONS_PAYMENT_PAYFAST_ITN_DEBUG') && ADDONS_PAYMENT_PAYFAST_ITN_DEBUG == 'Yes')
    {
        @mail( ADDONS_PAYMENT_PAYFAST_ITN_DEBUG_EMAIL, 'Verified ITN', $listener->getTextReport() );
    }
}
else
{
    //An Invalid ITN *may* be caused by a fraudulent transaction attempt. It's a good idea to have a developer or sys admin manually investigate any invalid ITN.
    lC_Order::process($itn_order_id, ADDONS_PAYMENT_PAYFAST_ORDER_CANCELED_STATUS_ID);
    $response_array['root']['transaction_response'] = 'INVALID';
    $itn_transaction_response = 'INVALID';
    if ( defined( 'ADDONS_PAYMENT_PAYFAST_ITN_DEBUG') && ADDONS_PAYMENT_PAYFAST_ITN_DEBUG == 'Yes')
    {
        @mail( ADDONS_PAYMENT_PAYFAST_ITN_DEBUG_EMAIL, 'Invalid ITN', $listener->getTextReport() );
    }
}

$lC_XML = new lC_XML( $response_array );

$Qtransaction = $lC_Database->query('insert into :table_orders_transactions_history (orders_id, transaction_code, transaction_return_value, transaction_return_status, date_added) values (:orders_id, :transaction_code, :transaction_return_value, :transaction_return_status, now())');
$Qtransaction->bindTable(':table_orders_transactions_history', TABLE_ORDERS_TRANSACTIONS_HISTORY);
$Qtransaction->bindInt(':orders_id', $itn_order_id);
$Qtransaction->bindInt(':transaction_code', 1);
$Qtransaction->bindValue(':transaction_return_value', $lC_XML->toXML());
$Qtransaction->bindInt(':transaction_return_status', (strtoupper(trim($itn_transaction_response)) == 'VERIFIED') ? 1 : 0);
$Qtransaction->execute();

?>