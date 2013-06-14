<?php
/*
	Plugin Name: Quickpay Payment Gateway for WP e-Commerce
	Plugin URI: http://quickpay.net/modules/wordpress/
	Description: Adds the Quickpay payment option into WP e-Commerce. Quickpay is a Danish online payment gateway. <strong>If you need support, please <a href="http://quickpay.net/contact/" target="_new">contact Quickpay</a>.</strong>
	Version: 1.3.2
	Author: Uffe Fey, WordPress consultant
	Author URI: http://wpkonsulent.dk
*/
	$wpkonsulent_wpecquickpay = new WPkonsulentWPECQuickPay();
	
	class WPkonsulentWPECQuickPay
	{
		function WPkonsulentWPECQuickPay()
		{
			$this->__construct();
		}

		function __construct()
		{
			add_action('init', array(&$this, 'callback'));
			
			// Set default values.
			if(!get_option('quickpay_language') <> '')
				update_option('quickpay_language', 'en');
			
			if(!get_option('quickpay_currency') <> '')
				update_option('quickpay_currency', 'EUR');
				
			if(!get_option('quickpay_keepbasket') <> '')
				update_option('quickpay_keepbasket', '0');
				
			if(!get_option('quickpay_autocapture') <> '')
				update_option('quickpay_autocapture', '0');
				
			if(!get_option('quickpay_testmode') <> '')
				update_option('quickpay_testmode', '0');
				
			if(!get_option('quickpay_protocol') <> '')
				update_option('quickpay_protocol', '7');
				
			if(!get_option('quickpay_cardtypelock') <> '')
				update_option('quickpay_cardtypelock', 'creditcard');
				
			if(!get_option('quickpay_mailerrors') <> '')
				update_option('quickpay_mailerrors', '0');
			
			global $nzshpcrt_gateways;
			
			$nzshpcrt_gateways[$num]['name'] = 'Quickpay';
			$nzshpcrt_gateways[$num]['internalname'] = 'Quickpay';
			
			// Hate having to do this, but WPEC expects a string. It won't accept arguments like array(&$this, 'function').
			// I would have preferred to keep all functions inside this class.
			$nzshpcrt_gateways[$num]['function'] = 'wpkonsulent_wpecquickpay_gateway';
			$nzshpcrt_gateways[$num]['form'] = 'wpkonsulent_wpecquickpay_form';
			$nzshpcrt_gateways[$num]['submit_function'] = 'wpkonsulent_wpecquickpay_submit';
		}
		
		// Process the callback and replies from QuickPay.
		function callback()
		{
			global $wpdb;

			$transaction_id = trim(stripslashes($_GET['transaction_id']));
			$sessionid = trim(stripslashes($_GET['sessionid']));
			
			$mailerrors = get_option('quickpay_mailerrors');
			
			$is_callback = false;
			
			if((isset($_GET['quickpay_callback']) && $_GET['quickpay_callback'] == '1'))
				$is_callback = true;

			// Process the callback.
			if($is_callback == true)
			{
				// Only enter this block if status code from QuickPay is 000 = Approved.
				if($_POST['qpstat'] == '000')
				{
					$md5 = $_POST['md5check'];
					
					// Check if MD5 check has been posted back to us. If so, validate it.
					if(!empty($md5))
					{
						$protocol = (int)get_option('quickpay_protocol');
					
						$new_transaction = $_POST['transaction'];
						$currency = get_option('quickpay_currency');
						$secret = get_option('quickpay_md5secret');
						$ordernumber = "WPEC" . $wpdb->get_var("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '$sessionid' LIMIT 1;");
						$amount = $_POST['amount'];
						$msgtype = $_POST['msgtype'];
						$time = $_POST['time'];
						$state = $_POST['state'];
						$qpstat = $_POST['qpstat'];
						$qpstatmsg = $_POST['qpstatmsg'];
						$chstat = $_POST['chstat'];
						$chstatmsg  = $_POST['chstatmsg'];
						$merchant = $_POST['merchant'];
						$merchantemail = $_POST['merchantemail'];
						$cardtype = $_POST['cardtype'];
						$cardnumber = $_POST['cardnumber'];
						$cardhash = $_POST['cardhash'];
						$cardexpire = $_POST['cardexpire'];
						
						if($protocol >= 7)
							$acquirer = $_POST['acquirer'];
							
						$splitpayment = $_POST['splitpayment'];
						$fraudprobability = $_POST['fraudprobability'];
						$fraudremarks = $_POST['fraudremarks'];
						$fraudreport = $_POST['fraudreport'];
						$fee = $_POST['fee'];
						
						$md5tmp = $msgtype . $ordernumber . $amount . $currency . $time . $state . $qpstat . $qpstatmsg . $chstat . $chstatmsg . $merchant . $merchantemail . $new_transaction . $cardtype . $cardnumber . $cardhash . $cardexpire;

						if($protocol >= 7)
							$md5tmp .= $acquirer;

						$md5tmp .= $splitpayment . $fraudprobability . $fraudremarks . $fraudreport . $fee . $secret;
													
						$md5check = md5($md5tmp);
					
						if($md5 == $md5check)
						{
							// Order is accepted.
							$notes = "Payment approved at Quickpay:\ntransaction id: " . $new_transaction . "\ncard type: " . $cardtype . "\namount: " . $amount . "\nfraud probability: " . $fraudprobability . "\nfraud remarks: " . $fraudremarks . "\nfraud report: " . $fraudreport;
							
							// old way of doing it..
							//$wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed = '3', transactid = '" . $new_transaction . "', date = '" . time() . "', notes = '" . $notes . "' WHERE sessionid = " . $sessionid . " LIMIT 1");
							
							$purchase_log = new WPSC_Purchase_Log($sessionid, 'sessionid');
							
							if(!$purchase_log->exists() || $purchase_log->is_transaction_completed())
								return;

							$purchase_log->set('processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT);
							$purchase_log->set('transactid', $new_transaction);
							$purchase_log->set('notes', $notes);
							$purchase_log->save();
						}
						else
						{
							if($mailerrors == '1')
							{
								$message = "Hello,\n\nThis is your WordPress site at " . get_bloginfo('url') . " speaking.\n\n";
								$message .= "I've received this callback from Quickpay, and I couldn't approve it because the MD5 checksum is incorrect.\n\n";
								$message .= "The checksum is " . $md5 . " but it should have been " . $md5check . ".\n\n";
								$message .= "These are the fields that were sent to me from Quickpay:\n\n";
								
								foreach($_POST as $key => $value)
									$message .= $key . " = " . $value . "\n";
									
								$message .= "\nYou can disable these notifications by changing the \"Enable error logging by email?\" setting within the Quickpay merchant setup.";
								
								wp_mail(get_bloginfo('admin_email'), 'Quickpay callback failed!', $message);
							}
						}
					}
					else
					{
						if($mailerrors == '1')
						{
							$message = "Hello,\n\nThis is your WordPress site at " . get_bloginfo('url') . " speaking.\n\n";
							$message .= "I've received this callback from Quickpay, and I couldn't approve it because the MD5 checksum was empty.\n\n";
							$message .= "These are the fields that were sent to me from Quickpay:\n\n";
							
							foreach($_POST as $key => $value)
								$message .= $key . " = " . $value . "\n";
								
							$message .= "\nYou can disable these notifications by changing the \"Enable error logging by email?\" setting within the Quickpay merchant setup.";
							
							wp_mail(get_bloginfo('admin_email'), 'Quickpay callback failed!', $message);
						}
					}
				}
				
				exit();
			}
			
			// Process cancellation.
			if((isset($_GET['quickpay_cancel']) && $_GET['quickpay_cancel'] == '1'))
			{
				// Check and process "Keep contents of basket on failure?".
				if(get_option('quickpay_keepbasket') != '1')
				{ 
					$log_id = $wpdb->get_var("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '$sessionid' LIMIT 1");
					$delete_log_form_sql = "SELECT * FROM " . WPSC_TABLE_CART_CONTENTS . " WHERE purchaseid = '$log_id'";
				
					$cart_content = $wpdb->get_results($delete_log_form_sql, ARRAY_A);
					
					foreach((array)$cart_content as $cart_item)
					{
						$wpdb->query("DELETE FROM " . WPSC_TABLE_CART_ITEM_VARIATIONS . " WHERE cart_id = '" . $cart_item['id'] . "'");
					}
					
					$wpdb->query("DELETE FROM " . WPSC_TABLE_CART_CONTENTS . " WHERE purchaseid = '$log_id'");
					$wpdb->query("DELETE FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " WHERE log_id IN ('$log_id')");
					$wpdb->query("DELETE FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE id = '$log_id' LIMIT 1");
				}
			}
		}
	}
	
	function wpkonsulent_wpecquickpay_form_hint($s)
	{
		return '<small style="line-height:14px;display:block;padding:2px 0 6px;">' . $s . '</small>';
	}
	
	// Displays the settings from within the WPEC control panel.
	function wpkonsulent_wpecquickpay_form()
	{
		// Get stored values.
		$merchantid = get_option('quickpay_merchantid');
		$md5secret = get_option('quickpay_md5secret');
		$language = get_option('quickpay_language');
		$autocapture = get_option('quickpay_autocapture');
		$currency = get_option('quickpay_currency');
		$keepbasket = get_option('quickpay_keepbasket');
		$testmode = get_option('quickpay_testmode');
		$protocol = get_option('quickpay_protocol');
		$cardtypelock = get_option('quickpay_cardtypelock');
		$mailerrors = get_option('quickpay_mailerrors');
		
		// Generate output.
		$output = '<tr><td colspan="2" style="text-align:center;"><br/><a href="http://quickpay.dk/?setlang=en" target="_new"><img src="http://quickpay.dk/gfx/quickpay.gif"/></a></td></tr>';
		$output .= '<tr><td colspan="2"><strong>Merchant configuration</strong></td></tr>';
				
		// Merchant ID.
		$output .= '<tr><td><label for="quickpay_merchantid">Merchant ID</label></td>';
		$output .= '<td><input name="quickpay_merchantid" id="quickpay_merchantid" type="text" value="' . $merchantid . '"/><br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('This is your unique ID, used to identify you.');
		$output .= '</td></tr>';

		// MD5 Secret.
		$output .= '<tr><td><label for="quickpay_md5secret">MD5 Secret</label></td>';
		$output .= '<td><input name="quickpay_md5secret" id="quickpay_md5secret" type="text" value="' . $md5secret . '"/><br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('This is the unique MD5 secret key, used to verify your transactions.');
		$output .= '</td></tr>';
					
		// Protocol version.
		$output .= '<tr><td><label for="quickpay_protocol">Protocol version</label></td><td>';
		$output .= '<input type="text" name="quickpay_protocol" id="quickpay_protocol" value="' . $protocol . '"/><br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('If you don\'t know what this is, just leave it as it is.');
		$output .= '</td></tr>';
		
		// Automatic capture on/off.
		$output .= '<tr><td><label for="quickpay_autocapture">Automatic capture</label></td><td>';
		$output .= '<input name="quickpay_autocapture" id="quickpay_autocapture" value="0"' . ($autocapture == '0' ? ' checked="checked"' : '') . ' type="radio"/> Off<br/>';
		$output .= '<input name="quickpay_autocapture" id="quickpay_autocapture_1" value="1"' . ($autocapture == '1' ? ' checked="checked"' : '') . ' type="radio"/> On<br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('Automatic capture means you will automatically deduct the amount from the customer.');
		$output .= '</td></tr>';
		
		// Test mode on/off.
		$output .= '<tr><td><label for="quickpay_testmode">Test mode</label></td><td>';
		$output .= '<input name="quickpay_testmode" id="quickpay_testmode" value="0"' . ($testmode == '0' ? ' checked="checked"' : '') . ' type="radio"/> Off<br/>';
		$output .= '<input name="quickpay_testmode" id="quickpay_testmode_1" value="1"' . ($testmode == '1' ? ' checked="checked"' : '') . ' type="radio"/> On<br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('Enables or disables test mode. Remember to disable when your shop goes live!');
		$output .= '</td></tr>';
				
		$output .= '<tr><td colspan="2"><strong>Payment window</strong></td></tr>';
		
		// Language.
		$languages = array();
		$languages['da'] = 'Danish';
		$languages['de'] = 'German';
		$languages['en'] = 'English';
		$languages['fr'] = 'French';
		$languages['it'] = 'Italian';
		$languages['no'] = 'Norwegian';
		$languages['nl'] = 'Dutch';
		$languages['pl'] = 'Polish';
		$languages['se'] = 'Swedish';
		
		$output .= '<tr><td><label for="quickpay_language">Language</label></td><td>';
		$output .= "<select name='quickpay_language'>";
		
		foreach($languages as $key => $value)
		{
			$output .= '<option value="' . $key . '"';
			
			if($language == $key)
				$output .= ' selected="selected"';
				
			$output .= '>' . $value . '</option>';
		}
		
		$output .= '</select><br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('Choose which language the payment window will use.');	
		$output .= '</td></tr>';

		// Currency.
		$currencies = array('EUR', 'DKK', 'USD');
		$output .= '<tr><td><label for="quickpay_currency">Currency</label></td><td>';
		$output .= '<select name="quickpay_currency" id="quickpay_currency">';
		
		foreach($currencies as $curr)
		{
			$output .= '<option value="' . $curr . '"';
			
			if($currency == $curr)
				$output .= ' selected="selected"';
				
			$output .= '>' . $curr . '</option>';
		}
		
		$output .= '</select><br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('Choose your currency. Please make sure to use the same currency as in your WP E-Commerce currency settings.');
		$output .= '</td></tr>';
		
		// Lock card type.
		/*$cardtypes = array();
		$cardtypes['creditcard'] = 'All cards, but NOT net banks, payex, edankort, paypal or 3D-secure.';
		$cardtypes['american-express'] = 'American Express credit card';
		$cardtypes['american-express-dk'] = 'American Express credit card (issued in Denmark)';
		$cardtypes['dankort'] = 'Dankort credit card';
		$cardtypes['danske-dk'] = 'Danske Net Bank';
		$cardtypes['diners'] = 'Diners Club credit card';
		$cardtypes['diners-dk'] = 'Diners Club credit card (issued in Denmark)';
		$cardtypes['edankort'] = 'eDankort credit card';
		$cardtypes['fbg1886'] = 'Forbrugsforeningen af 1886';
		$cardtypes['jcb'] = 'JCB credit card';
		$cardtypes['mastercard'] = 'Mastercard credit card';
		$cardtypes['mastercard-dk'] = 'Mastercard credit card (issued in Denmark)';
		$cardtypes['mastercard-debet-dk'] = 'Mastercard debet card (issued in Denmark)';
		$cardtypes['nordea-dk'] = 'Nordea Net Bank';
		$cardtypes['visa'] = 'Visa credit card';
		$cardtypes['visa-dk'] = 'Visa credit card (issued in Denmark)';
		$cardtypes['visa-electron'] = 'Visa Electron credit card';
		$cardtypes['visa-electron-dk'] = 'Visa Electron credit card (issued in Denmark)';
		$cardtypes['paypal'] = 'PayPal';
		$cardtypes['sofort'] = 'Sofort';
		$cardtypes['ibill'] = 'iBill';*/
		
		$output .= '<tr><td><label for="quickpay_cardtypelock">Lock payment options</label></td><td>';
		$output .= '<input name="quickpay_cardtypelock" id="quickpay_cardtypelock" type="text" value="' . $cardtypelock . '"/><br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('Read more here: <a href="http://quickpay.net/features/cardtypelock/" target="_new">Lock payments to given card types</a>.');
		$output .= '</td></tr>';
				
		// Other settings.
		$output .= '<tr><td colspan="2" style="padding-top:6px;"><strong>Other settings</strong></td></tr>';
		
		$output .= '<tr><td><label for="quickpay_keepbasket">Keep contents of basket on failure?</label></td><td>';
		$output .= '<input name="quickpay_keepbasket" id="quickpay_keepbasket" value="1"' . ($keepbasket == '1' ? ' checked="checked"' : '') . ' type="checkbox"/> Yes<br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('If a transaction fails or is cancelled and the user returns to your webshop, do you wish the contents of the users shopping basket to be kept? Otherwise it will be emptied.');
		$output .= '</td></tr>';
		
		$output .= '<tr><td><label for="quickpay_mailerrors">Enable error logging by email?</label></td><td>';
		$output .= '<input name="quickpay_mailerrors" id="quickpay_mailerrors" value="1"' . ($mailerrors == '1' ? ' checked="checked"' : '') . ' type="checkbox"/> Yes<br/>';
		$output .= wpkonsulent_wpecquickpay_form_hint('If checked, an email will be sent to ' . get_bloginfo('admin_email') . ' whenever a callback fails.');
		$output .= '</td></tr>';
	
		return $output;
	}
	
	// Validates and saves the settings from within the WPEC control panel.
	function wpkonsulent_wpecquickpay_submit()
	{
		if($_POST['quickpay_merchantid'] != null)
			update_option('quickpay_merchantid', $_POST['quickpay_merchantid']);
			 
		if($_POST['quickpay_md5secret'] != null)
			update_option('quickpay_md5secret', $_POST['quickpay_md5secret']);
		
		if($_POST['quickpay_language'] != null)
			update_option('quickpay_language', $_POST['quickpay_language']);
				
		if($_POST['quickpay_autocapture'] != null)
			update_option('quickpay_autocapture', $_POST['quickpay_autocapture']);
		
		if($_POST['quickpay_currency'] != null)
			update_option('quickpay_currency', $_POST['quickpay_currency']);
		
		if($_POST['quickpay_keepbasket'] == '1')
			update_option('quickpay_keepbasket', '1');
		else
			update_option('quickpay_keepbasket', '0');
			
		if($_POST['quickpay_testmode'] == '1')
			update_option('quickpay_testmode', '1');
		else
			update_option('quickpay_testmode', '0');
		
		if($_POST['quickpay_protocol'] != null)
			update_option('quickpay_protocol', $_POST['quickpay_protocol']);
			
		if($_POST['quickpay_cardtypelock'] != null)
			update_option('quickpay_cardtypelock', $_POST['quickpay_cardtypelock']);
		
		if($_POST['quickpay_mailerrors'] == '1')
			update_option('quickpay_mailerrors', '1');
		else
			update_option('quickpay_mailerrors', '0');

		return true;
	}
	
	function wpkonsulent_wpecquickpay_gateway($seperator, $sessionid)
	{
		global $wpdb, $wpsc_cart;

		$payurl = 'https://secure.quickpay.dk/form/'; 
		$protocol = get_option('quickpay_protocol');
		$msgtype = 'authorize';
		$merchant = get_option('quickpay_merchantid');
		$language = get_option('quickpay_language');
		$currency = get_option('quickpay_currency');
		$autocapture = get_option('quickpay_autocapture');
		$md5secret = get_option('quickpay_md5secret');
		$cardtypelock = get_option('quickpay_cardtypelock');
		$testmode = get_option('quickpay_testmode');
		
		$ordernumber = 'WPEC' . $wpdb->get_var("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '$sessionid' LIMIT 1;");
		 
		if(strlen($ordernumber) > 20)
			$ordernumber = time();

		$amount	= round($wpsc_cart->total_price, 2) * 100;
		$transaction_id = uniqid(md5(rand(1, 666)), true); // Set the transaction id to a unique value for reference in the system.
		
		$wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed = '1', transactid = '" . $transaction_id . "', date = '" . time() . "' WHERE sessionid = " . $sessionid . " LIMIT 1");
		
		$callbackurl = wpkonsulent_wpecquickpay_callbackurl($transaction_id, $sessionid);
		$continueurl = wpkonsulent_wpecquickpay_accepturl($transaction_id, $sessionid);
		$cancelurl = wpkonsulent_wpecquickpay_cancelurl($transaction_id, $sessionid);
		
		$md5check = md5($protocol . $msgtype . $merchant . $language . $ordernumber . $amount . $currency . $continueurl . $cancelurl . $callbackurl . $autocapture . $cardtypelock . $testmode . $md5secret);
		
		// Generate the form output.
		$output = "<div style=\"display:none;\">
		<form id=\"quickpay_form\" name=\"quickpay_form\" action=\"$payurl\" method=\"post\">
		<input type=\"hidden\" name=\"protocol\" value=\"$protocol\"/>
		<input type=\"hidden\" name=\"msgtype\" value=\"$msgtype\"/>
		<input type=\"hidden\" name=\"merchant\" value=\"$merchant\"/>
		<input type=\"hidden\" name=\"language\" value=\"$language\"/>
		<input type=\"hidden\" name=\"ordernumber\" value=\"$ordernumber\"/>
		<input type=\"hidden\" name=\"amount\" value=\"$amount\"/>
		<input type=\"hidden\" name=\"currency\" value=\"$currency\"/>
		<input type=\"hidden\" name=\"continueurl\" value=\"$continueurl\"/>
		<input type=\"hidden\" name=\"cancelurl\" value=\"$cancelurl\"/>
		<input type=\"hidden\" name=\"callbackurl\" value=\"$callbackurl\"/>
		<input type=\"hidden\" name=\"autocapture\" value=\"$autocapture\"/>
		<input type=\"hidden\" name=\"cardtypelock\" value=\"$cardtypelock\"/>
		<input type=\"hidden\" name=\"testmode\" value=\"$testmode\"/>
		<input type=\"hidden\" name=\"md5check\" value=\"$md5check\"/>
		<input type=\"submit\" value=\"Pay\"/>
		</form>
		</div>";

		echo $output;
		echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('quickpay_form').submit();</script>";
		echo "Please wait..";
		exit();
	}
	
	function wpkonsulent_wpecquickpay_cancelurl($transaction_id, $session_id)
	{
		$cancelurl = get_option('shopping_cart_url');
		
		$params = array('quickpay_cancel' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
		return add_query_arg($params, $cancelurl);
	}
	
	function wpkonsulent_wpecquickpay_accepturl($transaction_id, $session_id)
	{
		$accepturl = get_option('transact_url');
		
		$params = array('quickpay_accept' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
		return add_query_arg($params, $accepturl);
	}
	
	function wpkonsulent_wpecquickpay_callbackurl($transaction_id, $session_id)
	{
		$callbackurl = get_option('siteurl');
		
		$string_end = substr($callbackurl, strlen($callbackurl) - 1);
 
		if($string_end != '/')
			$callbackurl .= '/';
		
		$params = array('quickpay_callback' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
		return add_query_arg($params, $callbackurl);
	}
?>