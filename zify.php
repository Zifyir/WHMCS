<?php
/*
Plugin Name: ساخت و پرداخت سفارش زیفای
Version: 0.1.0
Description:  ماژول ساخت و پرداخت سفارش زیفای برای WHMCS
Plugin URI: https://www.zify.ir/
Author: Mahdi Sarani
Author URI: https://mahdisarani.ir

*/
function zify_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "FriendlyName" => "عنوان درگاه", "Value"=>"زیفای"),
     "tokenCode"    => array("FriendlyName" => "کد توکن اختصاصی", "Type" => "text", "Size" => "80", ),
     "Currencies"   => array("FriendlyName" => "واحد مالی", "Type" => "dropdown", "Options" => "ریال,تومان", ),
     );
	return $configarray;
}

function zify_link($params){

	# Gateway Specific Variables
	$tokenCode  = $params['tokenCode'];
    $currencies = $params['Currencies'];
	$baseurl    = "https://zify.ir";
    $items      = $params['cart']->items;

	# Invoice Variables
	$invoiceid   = $params['invoiceid'];
	$description = $params["description"];
    $amount      = $params['amount']; # Format: ##.##
    $currency    = $params['currency']; # Currency Code

	# Client Variables
	$firstname = $params['clientdetails']['firstname'];
	$lastname  = $params['clientdetails']['lastname'];
	$email     = $params['clientdetails']['email'];
	$address1  = $params['clientdetails']['address1'];
	$address2  = $params['clientdetails']['address2'];
	$city      = $params['clientdetails']['city'];
	$state     = $params['clientdetails']['state'];
	$postcode  = $params['clientdetails']['postcode'];
	$country   = $params['clientdetails']['country'];
	$phone     = $params['clientdetails']['phonenumber'];

	# System Variables
	$companyname = $params['companyname'];
	$systemurl   = $params['systemurl'];
	$currency    = $params['currency'];
    
    $products = [];
    foreach($items as $key => $item){
    	if($currencies == 'ریال'){
    		$price = $item->amount->toNumeric() / 10;
    	}else{
    	    $price = $item->amount->toNumeric();
    	}
    	
    	$id = $item->id;
    	if( $item->id == 'generic'){
    	    $id = $key.'_'.$item->id;
    	}

        $products[] = array(
            "code"         => "$id",
            "sellQuantity" => $item->qty,
            "title"        => "$item->name",
            "amount"       => intval($price),
            "unlimited"    => "true",
            "quantity"     => "",
            "description"  => ""
        );
    }
    
    if(empty($products)){
        $products[] = array(
            "code"         => "lisence",
            "sellQuantity" => 1,
            "title"        => "lisence",
            "amount"       => intval($amount),
            "unlimited"    => "true",
            "quantity"     => "",
            "description"  => ""
        );
    }

	if($currencies == 'ریال'){
		$amount = intval($amount/10);
	}else{
	    $amount = intval($amount);
	}
	$CallbackURL = $systemurl.'/modules/gateways/callback/zify.php?Amount='.$amount;
	
    $payer = array(
        "first_name" => $firstname,
        "last_name" => $lastname,
        //"phone" => $phone,
        "email" => $email,
        "state" => $state,
        "city" => $city,
        "address_1" => $address1,
        "address_2" => $address2
    );
    $data = array(
        "payer"     => $payer,
        "products"  => $products,
        "returnUrl" => $CallbackURL,
        "clientRefId" => $invoiceid,
        "total"      => $amount,
        "shipping_total" => "",
        "off_total" => "",
        "tax_total" => ""
    );
    
	try {
		$curl = curl_init();
		curl_setopt_array($curl, array(CURLOPT_URL => $baseurl."/api/order/v2/create",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_HTTPHEADER => array(
				"accept: application/json",
				"authorization: Bearer " . $tokenCode,
				"cache-control: no-cache",
				"content-type: application/json"),
			)
		);

		$response = curl_exec($curl);
		$header = curl_getinfo($curl);
		$err = curl_error($curl);
		curl_close($curl);
        
		if ($err) {
			$return =  "cURL Error #:" . $err;
		} else {
			if ($header['http_code'] == 200) {
				$response = json_decode($response, true);
				if(isset($response['data']["order"]) and $response['data']["order"] != '') {
				    $zify_order_code = $response['data']["order"];
				    @session_start();
				    $_SESSION['zify_order_code'] = $zify_order_code; 
					$link = $baseurl."/order/accept/".$zify_order_code;
					$return = '<form method="get" action="'.$link.'"><input type="submit" value=" پرداخت " /></form>';
				}else{
					$return = ' ساخت سفارش ناموفق بود- شرح خطا: عدم وجود کد سفارش ';
				}
			}elseif ($header['http_code'] == 400) {
				$return = ' ساخت سفارش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true)));
			}else{
				$return = "ساخت سفارش ناموفق بود.";
			}
		}
	} catch (Exception $e){
		$return = ' ساخت سفارش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
	}

	return $return;
}