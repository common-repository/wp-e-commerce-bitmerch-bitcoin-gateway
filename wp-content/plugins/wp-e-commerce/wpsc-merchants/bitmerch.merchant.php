<?php

$nzshpcrt_gateways[$num]['name'] = 'Bitcoins';
$nzshpcrt_gateways[$num]['internalname'] = 'bitmerch';
$nzshpcrt_gateways[$num]['function'] = 'gateway_bitmerch';
$nzshpcrt_gateways[$num]['form'] = 'form_bitmerch';
$nzshpcrt_gateways[$num]['submit_function'] = "submit_bitmerch";



function debuglog($contents)
{
	$file = 'wp-content/plugins/wp-e-commerce/wpsc-merchants/bitmerch/log.txt';
	file_put_contents($file, date('m-d H:i:s').": ", FILE_APPEND);
	if (is_array($contents))
		file_put_contents($file, var_export($contents, true)."\n", FILE_APPEND);
	else if (is_object($contents))
		file_put_contents($file, json_encode($contents)."\n", FILE_APPEND);
	else
		file_put_contents($file, $contents."\n", FILE_APPEND);
}

//settings form bitmerch
function form_bitmerch()
{
	$rows = array();

	// API key
	$rows[] = array('API key', '<input name="bitmerch_apikey" type="text" value="'.get_option('bitmerch_apikey').'" />');
    $rows[] = array('Merchant ID', '<input name="bitmerch_merchant_id" type="text" value="'.get_option('bitmerch_merchant_id').'" />');

	$currencies = array( "BTC","USD","EUR","JPY","CAD","GBP","CHF","RUB","AUD","SEK","DKK","HKD","PLN","CNY","SGD","THB","NZD","NOK");

    $currenciesSelect = '<select name="bitmerch_currency">';

    foreach ($currencies as $currency) {
        if(get_option('bitmerch_currency') == $currency) {
            $currenciesSelect .= '<option value="'.$currency.'" selected="selected">'.$currency.'</option>';
        } else {
            $currenciesSelect .= '<option value="'.$currency.'">'.$currency.'</option>';
        }
    }

    $currenciesSelect .= '</select>';

    $rows[] = array('Currency', $currenciesSelect);
    $output = '';
    foreach($rows as $r)
	{
		$output.= '<tr> <td>'.$r[0].'</td> <td>'.$r[1];
		if (isset($r[2]))
			$output .= '<BR/><small>'.$r[2].'</small></td> ';
		$output.= '</tr>';
	}

	return $output;
}

function submit_bitmerch()
{
	$params = array('bitmerch_apikey','bitmerch_merchant_id','bitmerch_currency');
	foreach($params as $p)
		if ($_POST[$p] != null)
			update_option($p, $_POST[$p]);
	return true;
}

function gateway_bitmerch($seperator, $sessionid)
{
	require('wp-content/plugins/wp-e-commerce/wpsc-merchants/bitmerch/bm_lib.php');
    require('wp-content/plugins/wp-e-commerce/wpsc-merchants/bitmerch/bm_options.php');


	//$wpdb is the database handle,
	//$wpsc_cart is the shopping cart object
	global $wpdb, $wpsc_cart;
    global $bmOptions;

	//This grabs the purchase log id from the database
	//that refers to the $sessionid
	$purchase_log = $wpdb->get_row(
		"SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS.
		"` WHERE `sessionid`= ".$sessionid." LIMIT 1"
		,ARRAY_A) ;

	//This grabs the users info using the $purchase_log
	// from the previous SQL query
	$usersql = "SELECT `".WPSC_TABLE_SUBMITED_FORM_DATA."`.value,
		`".WPSC_TABLE_CHECKOUT_FORMS."`.`name`,
		`".WPSC_TABLE_CHECKOUT_FORMS."`.`unique_name` FROM
		`".WPSC_TABLE_CHECKOUT_FORMS."` LEFT JOIN
		`".WPSC_TABLE_SUBMITED_FORM_DATA."` ON
		`".WPSC_TABLE_CHECKOUT_FORMS."`.id =
		`".WPSC_TABLE_SUBMITED_FORM_DATA."`.`form_id` WHERE
		`".WPSC_TABLE_SUBMITED_FORM_DATA."`.`log_id`=".$purchase_log['id'];
	$userinfo = $wpdb->get_results($usersql, ARRAY_A);
	// convert from awkward format
	foreach((array)$userinfo as $value)
		if (strlen($value['value']))
			$ui[$value['unique_name']] = $value['value'];
	$userinfo = $ui;


	// name
	if (isset($userinfo['billingfirstname']))
	{
		$options['first_name'] = $userinfo['billingfirstname'];
		if (isset($userinfo['billinglastname']))
			$options['last_name'] = $userinfo['billinglastname'];
	}

	//address -- remove newlines
	if (isset($userinfo['billingaddress']))
	{
		$newline = strpos($userinfo['billingaddress'],"\n");
		if ($newline !== FALSE)
		{
			$options['address_1'] = substr($userinfo['billingaddress'], 0, $newline);
			$options['address_2'] = substr($userinfo['billingaddress'], $newline+1);
			$options['address_2'] = preg_replace('/\r\n/', ' ', $options['buyerAddress2'], -1, $count);
		}
	}

	// state
	if (isset($userinfo['billingstate']))
		$options['state'] = wpsc_get_state_by_id($userinfo['billingstate'], 'code');

    if (isset($userinfo['billingcountry'])) {
        $country = wpsc_country_has_state($userinfo['billingcountry']);
        $options['country'] = $country['country'];
    }


    if (isset($userinfo['billingcity']))
        $options['city'] = $userinfo['billingcity'];

    if (isset($userinfo['billingpostcode']))
        $options['zip'] = $userinfo['billingpostcode'];


	$products = array();
	
	$itemTotal = 0;
	//$taxTotal = wpsc_tax_isincluded() ? 0 : $wpsc_cart->cart_tax;
	$taxTotal = $wpsc_cart->total_tax;
	$shippingTotal = number_format($wpsc_cart->base_shipping, 2);
	$shippingTotal += number_format($wpsc_cart->total_shipping, 2);
	
	foreach($wpsc_cart->cart_items as $item) {
		$products[] = $item->product_name.' x '.$item->quantity;
		
		$shippingTotal += number_format($item->shipping, 2);
		$itemTotal += number_format($item->unit_price, 2) * $item->quantity;
	}
	$options['product_name'] = implode(', ', $products);
	
	if ($wpsc_cart->has_discounts) {
			$discountValue = number_format($wpsc_cart->cart_discount_value, 2);

			$coupon = new wpsc_coupons($wpsc_cart->cart_discount_data);

			// free shipping
			if ( $coupon->is_percentage == 2 ) {
				$shippingTotal = 0;
				$discountValue = 0;
			} elseif ( $discount_value >= $item_total ) {
				$discountValue = $itemTotal - 0.01;
				$shippingTotal -= 0.01;
			}

		
			$itemTotal -= $discountValue;
	}
	
	$totalAmount = number_format($itemTotal, 2) + number_format($shippingTotal, 2) + number_format($taxTotal, 2);
	
	//currency
	$currencyId = get_option( 'currency_type' );
        $options['currency'] = get_option( 'bitmerch_currency' );
	$options['transaction_speed'] = get_option('bitmerch_transaction_speed');
	$options['apiKey'] = get_option('bitmerch_apikey');
	$options['order_id'] = $sessionid;
	$options['fullNotifications'] = true;
        $options['merchant_id'] = get_option( 'bitmerch_merchant_id' );
        $options['merchant_api_key'] = get_option( 'bitmerch_apikey' );

        //$options['price'] = number_format($wpsc_cart->total_price,2);
	
	$options['price'] = $totalAmount;
	
	$invoice = bmCreateInvoice($sessionid, $price, $sessionid, $options);


	if (isset($invoice['result']) && $invoice['result'] == 'failed') {
		// close order
		$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '5' WHERE `sessionid`=".$sessionid;
		$wpdb->query($sql);
		//redirect back to checkout page with errors
		$_SESSION['WpscGatewayErrorMessage'] = $invoice['error_message'];//__('Sorry your transaction did not go through successfully, please try again.');
		header("Location: ".get_option('checkout_url'));
	}elseif(isset($invoice['result']) && $invoice['result'] == 'success'){
		$wpsc_cart->empty_cart();
		unset($_SESSION['WpscGatewayErrorMessage']);
        $url = $bmOptions['bitmerch_url'].'/transaction/'.$invoice['transaction_key'];
		header("Location: ".$url);
		exit();
	}else{
        $_SESSION['WpscGatewayErrorMessage'] = __('Sorry your transaction did not go through successfully, please try again.');
        header("Location: ".get_option('checkout_url'));
    }
}

function bitmerch_callback()
{
	global $wpdb;

    if ($_POST['merchant_id'] == get_option('bitmerch_merchant_id') && $_POST['merchant_api_key'] == get_option('bitmerch_apikey')) {
        $sessionid = $_POST['order_id'];
        if ($_POST['result'] == 'success') {

            $sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS.
                "` SET `processed`= '2' WHERE `sessionid`=".$sessionid;
            if (is_numeric($sessionid))
                $wpdb->query($sql);
        }
    }
}

add_action('init', 'bitmerch_callback');

?>
