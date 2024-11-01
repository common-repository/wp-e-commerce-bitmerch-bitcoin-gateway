<?php

require_once 'bm_options.php';

function bmCurl($url, $apiKey, $post = false) {
	global $bmOptions;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result, true);

	return $result;
}


function bmCreateInvoice($orderId, $price, $posData, $options = array()) {
	global $bmOptions;

	$options = array_merge($bmOptions, $options);	// $options override any options found in bp_options.php

	$pos = array('pos_data' => $posData);
	if ($bmOptions['verifyPos'])
		$pos['hash'] = crypt(serialize($posData), $options['apiKey']);
	$options['pos_data'] = json_encode($pos);

	$postOptions = array('order_id', 'product_name',
		'price', 'currency','first_name','last_name','address_1','address_2','country',
		'city', 'state', 'zip','merchant_id', 'merchant_api_key');
	foreach($postOptions as $o)
		if (array_key_exists($o, $options))
			$post[$o] = $options[$o];

	$response = bmCurl($bmOptions['bitmerch_url'].'/api/x1', $options['apiKey'], $post);


	return $response;
}

// Call from your notification handler to convert $_POST data to an object containing invoice data
function bmVerifyNotification($apiKey = false) {
	global $bmOptions;
    global $wpdb;
	if (!$apiKey)
		$apiKey = $bmOptions['apiKey'];

	$post = file_get_contents("php://input");
	if (!$post)
		return array('error' => 'No post data');

	$json = json_decode($post, true);
	if (is_string($json))
		return array('error' => $json); // error


    $usersql = "SELECT `".WPSC_TABLE_SUBMITED_FORM_DATA."`.value,
		`".WPSC_TABLE_CHECKOUT_FORMS."`.`name`,
		`".WPSC_TABLE_CHECKOUT_FORMS."`.`unique_name` FROM
		`".WPSC_TABLE_CHECKOUT_FORMS."` LEFT JOIN
		`".WPSC_TABLE_SUBMITED_FORM_DATA."` ON
		`".WPSC_TABLE_CHECKOUT_FORMS."`.id =
		`".WPSC_TABLE_SUBMITED_FORM_DATA."`.`form_id` WHERE
		`".WPSC_TABLE_SUBMITED_FORM_DATA."`.`log_id`=".$$json['order_id'];
    $orderinfo = $wpdb->get_results($usersql, ARRAY_A);

    if($json['merchant_id'] != get_option('bitmerch_merchant_id') &&
       $json['merchant_api_key'] != get_option('bitmerch_apikey') &&
       $json['price'] != $orderinfo['price'] &&
       $json['currency'] != get_option('bitmerch_currency')) {
         return array('error' => $json); // error
    }

	return $json;
}
