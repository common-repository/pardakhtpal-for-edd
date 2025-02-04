<?php
/**
	Plugin Name: درگاه پرداخت پرداخت پال برای Easy Digital Download
	Version: 2.0
	Description: درگاه کامل پرداخت پال برای Easy Digital Download
	Plugin URI: http://www.pardakhtpal.com/
	Author: Pardakhtpal
    Author URI: http://www.pardakhtpal.com
**/
@session_start();

function ckw_edd_rial ($formatted, $currency, $price) {
	return $price . 'ریال';
}
add_filter( 'edd_rial_currency_filter_after', 'edd_rial', 10, 3 );

function ck_wg_add_gateway ($gateways) {
	$gateways['pardakhtpal'] = array('admin_label' => 'پرداخت پال', 'checkout_label' => 'پرداخت آنلاین با پرداخت پال');
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'ck_wg_add_gateway' );

function ck_wg_cc_form () {
	do_action( 'ck_wg_cc_form_action' );
}
add_filter( 'edd_pardakhtpal_cc_form', 'ck_wg_cc_form' );

function ck_wg_process_payment ($purchase_data) {
	global $edd_options;
	
	if (edd_is_test_mode()) {
		$api = '1';
	} else {
		$api = $edd_options['pardakhtpal_api'];
	}
	
	$payment_data = array( 
		'price' => $purchase_data['price'], 
		'date' => $purchase_data['date'], 
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);
	$payment = edd_insert_payment($payment_data);
	
	if ($payment) {
		$_SESSION['pardakhtpal_payment'] = $payment;
		$return = urlencode(add_query_arg('order', 'pardakhtpal', get_permalink($edd_options['success_page'])));
		$price = $payment_data['price'] ;
		$_SESSION['pardakhtpal_fi'] = $price;
		$desc='پرداخت سفارش شماره '.$payment ;
		$wsdl_url = "http://pardakhtpal.com/WebService/WebService.asmx?wsdl";
		$client = new SoapClient($wsdl_url);
		$params = array('API' => $api , 'Amount' => $price, 'CallBack' => urldecode($return), 'OrderId' => $payment, 'Text' => $desc);
		$result = $client->requestpayment($params);
		$res = $result->requestpaymentResult;
		if(strlen($res) == 8){
			$redirect_page = 'http://pardakhtpal.com/payment/pay_invoice/' . $res; 
			wp_redirect($redirect_page);
		exit;
		}else{
			echo'ERR: '.$res;
		}

	} else {
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_pardakhtpal', 'ck_wg_process_payment');

function ck_wg_verify() {

	global $edd_options;
	if (isset($_POST['au']) and isset($_POST['order_id'])) {
		$payment = $_SESSION['pardakhtpal_payment'];
		if (edd_is_test_mode()) {
			$api = '1';
		} else {
			$api = $edd_options['pardakhtpal_api'];
		}
		$au = $_POST['au'];
		$getedorder =  $_POST['order_id'];
		$amount = $_SESSION['pardakhtpal_fi'];
		$wsdl_url = "http://pardakhtpal.com/WebService/WebService.asmx?wsdl";
		$client = new SoapClient($wsdl_url);
		$params = array('API' => $api , 'Amount' => $amount, 'InvoiceId' => $au);
		$res = $client->verifypayment($params);
		$result = $res->verifypaymentResult;
		
		if (intval($result) == 1) {

			edd_update_payment_status($payment, 'publish');
		}else{
			echo'ERR: '.$result;
		}
	}
}
add_action('init', 'ck_wg_verify');

function ck_wg_add_settings ($settings) {
	$pardakhtpal_settings = array (
		array (
			'id'		=>	'pardakhtpal-zg_settings',
			'name'		=>	'<strong>پیکربندی پرداخت پال</strong>',
			'desc'		=>	'پیکربندی پرداخت پال با تنظیمات فروشگاه',
			'type'		=>	'header'
		),
		array (
			'id'		=>	'pardakhtpal_api',
			'name'		=>	'کد API ',
			'desc'		=>	'کد API که در قسمت درگاه های شما قرار دارد .',
			'type'		=>	'text',
			'size'		=>	'regular'
		)
	);
	return array_merge( $settings, $pardakhtpal_settings );
}
add_filter('edd_settings_gateways', 'ck_wg_add_settings');
?>
