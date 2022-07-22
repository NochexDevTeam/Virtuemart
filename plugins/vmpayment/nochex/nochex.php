<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 *
 * Nochex Payment Module
 * @author: Nochex LTD
 * @package VirtueMart
 * @subpackage payment
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin'))
require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentNochex extends vmPSPlugin {

	// instance of class
	public static $_this = false;

	function __construct(& $subject, $config) {
		parent::__construct($subject, $config);

		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id'; //virtuemart_nochex_id';
		$this->_tableId = 'id'; //'virtuemart_nochex_id';
		
		$varsToPush = array('nochex_merchant_email' => array('', 'char'),
	    'test_mode' => array('', 'int'),
	    'hide_billing_details' => array('', 'int'),
		'xmlCollection' => array('', 'int'),
		'postage' => array('', 'int'),
	    'send_delivery_address' => array('', 'int'),
	    'payment_currency' => array('', 'int'),
	    'debug' => array('', 'int'),
	    'status_pending' => array('', 'char'),
	    'status_success' => array('', 'char'),
	    'status_canceled' => array('', 'char'),
	    'countries' => array('', 'char'),
	    'min_amount' => array('', 'int'),
	    'max_amount' => array('', 'int'),
	    'secure_post' => array('', 'int'),
	    'cost_per_transaction' => array('', 'int'),
	    'cost_percent_total' => array('', 'int'),
	    'tax_id' => array(0, 'int')
		);

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
		
		
	}
	
	
	
	public function plgVmDeclarePluginParamsPaymentVM3( &$data) {
      return $this->declarePluginParams('payment', $data);
	}
	
	public function getVmPluginCreateTableSQL() {

		return $this->createTableSQL('Payment Nochex Table');
	}

	function getTableSQLFields() {

		$SQLfields = array(
	    'id' => ' INT(11) unsigned NOT NULL AUTO_INCREMENT ',
	    'virtuemart_order_id' => ' int(1) UNSIGNED DEFAULT NULL',
	    'order_number' => ' char(32) DEFAULT NULL',
	    'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED DEFAULT NULL',
	    'payment_name' => 'varchar(5000)',
	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
	    'payment_currency' => 'char(3) ',
	    'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
	    'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
	    'tax_id' => ' smallint(1) DEFAULT NULL',
	    'nochex_custom' => ' varchar(255)  ',
	    'nochex_response_amount' => ' decimal(10,2) DEFAULT NULL ',
	    'nochex_response_order_id' => ' char(32) DEFAULT NULL',
	    'nochex_response_transaction_date' => ' char(28) DEFAULT NULL',
	    'nochex_response_to_email' => ' char(128) DEFAULT NULL',
	    'nochex_response_from_email' => ' char(128) DEFAULT NULL',
	    'nochex_response_status' => ' char(64) DEFAULT NULL',
	    'nochexresponse_raw' => ' varchar(512) DEFAULT NULL'
		);
		return $SQLfields;
	}

	function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$session = JFactory::getSession();
		$return_context = $session->getId();
		$this->_debug = $method->debug;
		$this->debugLog('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message', 'debug');

		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		if (!class_exists('VirtueMartModelCurrency'))
		require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

		$new_status = '';

		$usrBT = $order['details']['BT'];
		$address = $order['details']['BT'];
		$shipaddress = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		if (!class_exists('TableVendors'))
		require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
		$vendorModel = VmModel::getModel('Vendor');
		$vendorModel->setId(1);
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages($vendor, 1);
		$this->getPaymentCurrency($method);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();

		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		//$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
		
		// Get the billing address dependent upon which fields have been filled in
		$billingaddress = $address->address_1;
		if ($address->address_2 != ""){
		$billingaddress = $billingaddress . ", " . $address->address_1;
		}
		
		if (ShopFunctions::getStateByID($address->virtuemart_state_id) != ""){
		$billingaddress = $billingaddress . ", " . ShopFunctions::getStateByID($address->virtuemart_state_id);
		}
		
		// Get the shipping address dependent upon which fields have been filled in
		$shippingaddress = $shipaddress ->address_1;
		if ($shipaddress ->address_2 != ""){
		$shippingaddress = $shippingaddress . ", " . $shipaddress ->address_1;
		}
		
		if (ShopFunctions::getStateByID($shipaddress ->virtuemart_state_id) != ""){
		$shippingaddress = $shippingaddress . ", " . ShopFunctions::getStateByID($shipaddress ->virtuemart_state_id);
		}
		
		/*print_r($order['details']['BT']);*/
			
		if ($method->postage == "1"){	
			$delPostage = $order['details']['BT']->order_shipment;
			$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2) - $delPostage;
		}else{	
			$delPostage = "";
			$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		}
		
		
		
		//$xmlCollectionDetails
		if ($method->xmlCollection == "1"){
		
		$xmlCollectionDetails = "<items>";
	   
		$itemqty = 0;
	    	foreach($order['items'] as $item){
	        	$itemqty++;
	        	if ($itemqty > 1){
	          		$xmlCollectionDetails .= ", ";
	       		}
	       	$xmlCollectionDetails .= "<item><id>". $item->virtuemart_order_item_id ."</id><name>" .$item->order_item_name . "</name><description>". $item->order_item_name ."</description><quantity>" . $item->product_quantity . "</quantity><price>" . number_format($item->product_final_price,2) . "</price></item>";
	       }
		
		$xmlCollectionDetails .= "</items>";
		$description = "Order created for - " . $order['details']['BT']->order_number;
		
		}else{
			
		$xmlCollectionDetails = "";
		// Get the products ordered and their quantity for the description parameter
		$description = '';
	    	$itemqty = 0;
	    	foreach($order['items'] as $item){
	        	$itemqty++;
	        	if ($itemqty > 1){
	          		$description .= ", ";
	       		}
				
	       	$description .= $item->order_item_name . ", quantity: " . $item->product_quantity . ", Price: " . number_format($item->product_final_price,2) . " ";
			
	       }
		
		}
				
		$testReq = $method->debug == 1 ? 'YES' : 'NO';
		$post_variables = Array(
	    'merchant_id' => $method->nochex_merchant_email,
	    'order_id' => $order['details']['BT']->order_number,
	    'custom' => $return_context,
	    'optional_2' => "En",
	    'description' => $description,
	    "amount" => number_format($totalInPaymentCurrency,2),
	    "postage" => number_format($delPostage,2),
	    "billing_fullname" => $address->first_name . " " . $address->last_name,
	    "billing_address" => $billingaddress,
	    "billing_city" => $address->city,
	    "billing_postcode" => $address->zip,
		"delivery_fullname" => $shipaddress->first_name . " " . $shipaddress->last_name,
		"delivery_address" => $shippingaddress,
		"delivery_city" => $shipaddress ->city,
		"delivery_postcode" => $shipaddress->zip,
		"xml_item_collection" => $xmlCollectionDetails,
	    "email_address" => $order['details']['BT']->email,
	    "customer_phone_number" => $address->phone_1,
	    "callback_url" => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component',
	    "cancel_url" => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id,
	    );

	    	// If in test mode add test parameters
	    if ($method->test_mode == "1"){
			$post_variables['test_transaction'] = '100';
			$post_variables['test_success_url'] = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id;
		} else {
			$post_variables['success_url'] = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id;
		}
		
		// Hide Billing Details and Send Delivery Address Check
		if ($method->hide_billing_details == "1"){
			$post_variables['hide_billing_details'] = 'true';
		}
		

		// Prepare data that should be stored in the database
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['nochex_custom'] = $return_context;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);
		
		$html = '<form action="https://secure.nochex.com/default.aspx" method="post" name="vm_nochex_form" >';
		$html.= '<input type="submit"  value="Secure Payment" />';
		foreach ($post_variables as $name => $value) {
			$html.= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
		}
		$html.= '</form></div>';
		$html.= ' <script type="text/javascript">';
		$html.= ' document.vm_nochex_form.submit();';
		$html.= ' </script>';

		return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $new_status);
		
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
	}

	function plgVmOnPaymentResponseReceived(&$html) {

		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
		$order_R_number = vRequest::getVar('on', 0);
		$vendorId = 0;
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		if (!class_exists('VirtueMartCart'))
		require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		if (!class_exists('shopFunctionsF'))
		require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		$nochex_data = vRequest::get('post');
		$payment_name = $this->renderPluginName($method);

		if (!empty($nochex_data)) {
			vmdebug('plgVmOnPaymentResponseReceived', $nochex_data);
			$order_number = $nochex_data['order_id'];
			$return_context = $nochex_data['custom'];
			if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
			$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
			$payment_name = $this->renderPluginName($method);
			if ($virtuemart_order_id) {
				$order['customer_notified']=0;
				$order['order_status'] = $this->_getPaymentStatus($method, "");
				$order['comments'] = JText::sprintf('VMPAYMENT_NOCHEX_PAYMENT_STATUS_CONFIRMED', $order_number);
				// send the email ONLY if payment has been accepted
				$modelOrder = VmModel::getModel('orders');
				$orderitems = $modelOrder->getOrder($virtuemart_order_id);
				$nb_history = count($orderitems['history']);
				if ($orderitems['history'][$nb_history - 1]->order_status_code != $order['order_status']) {
					//$this->_storeNochexInternalData($method, $nochex_data, $virtuemart_order_id);
					$this->debugLog('plgVmOnPaymentResponseReceived, sentOrderConfirmedEmail ' . $order_number, 'message', 'debug');
					$order['virtuemart_order_id'] = $virtuemart_order_id;
					$order['comments'] = JText::sprintf('VMPAYMENT_NOCHEX_EMAIL_SENT');
					$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
				}
			} else {
				vmError('Nochex data received, but no order number');
				return;
			}
		} else {
			$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		}
		/*if (!($paymentTable = $this->_getNochexInternalData($virtuemart_order_id, $order_number) )) {
			return '';
		}
		$html = $this->_getPaymentResponseHtml($paymentTable, $payment_name);*/
		
		$html = '<table>' . "\n";
		$html .= '<tr><td>Order updated for '.$order_R_number.' </td></tr>';
		$html .= '</table>' . "\n";
		
		//We delete the old stuff
		// get the correct cart / session
		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return true;
	}

	function plgVmOnUserPaymentCancel() {

		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		$order_number = vRequest::getVar('on');
		if (!$order_number)
		return false;
		$db = JFactory::getDBO();
		$query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";

		$db->setQuery($query);
		$virtuemart_order_id = $db->loadResult();

		if (!$virtuemart_order_id) {
			return null;
		}
		$this->handlePaymentUserCancel($virtuemart_order_id);

		return true;
	}

	/*
	 *   plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
	* Return:
	* Parameters:
	*  None
	*  @author Valerie Isaksen
	*/

	function plgVmOnPaymentNotification() {

		if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		$nochex_data = $_POST;/*vRequest::get('post');*/
		//$this->debugLog('nochex_data ' . json_encode($nochex_data), 'message', 'debug');
		if (!isset($nochex_data['order_id'])) {
			return;
		}
		$order_number = $nochex_data['order_id'];
		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($nochex_data['order_id']);
		$this->debugLog('1.' . $virtuemart_order_id,  'message', 'debug');
		if (!$virtuemart_order_id) {
			return;
		}
		$vendorId = 0;
		$payment = $this->getDataByOrderId($virtuemart_order_id);
		$this->debugLog('2.' . print_r($payment),  'message', 'debug');
		
		$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		$this->_debug = $method->debug;
		if (!$payment) {
			$this->debugLog('getDataByOrderId payment not found: exit ', 'ERROR');
			return null;
		}

		//$response = $this->_processAPC($nochex_data, $method, $virtuemart_order_id);
		
		if(!empty($nochex_data['optional_2'])) {
		
			if($nochex_data['optional_2'] == "En" ){		
				$response = $this->_processCallback($nochex_data, $method, $virtuemart_order_id);	
				
				$this->debugLog('process Callback ' . $response, 'message', 'debug');
				if (!strstr($response, "AUTHORISED")) {
					$new_status = $method->status_canceled;
					$this->debugLog('process Callback ' . $response  . ' ' . $new_status, 'ERROR');
					$transactionStatus = "Callback Declined";
				} else {
					$this->debugLog('process Callback OK', 'message', 'debug');
					$transactionStatus = "Callback Authorised";
				}
		
			} else {
				$response = $this->_processAPC($nochex_data, $method, $virtuemart_order_id);	$this->debugLog('process APC ' . $response, 'message', 'debug');
		if (!strstr($response, "AUTHORISED")) {
			$new_status = $method->status_canceled;
			$this->debugLog('process APC ' . $response  . ' ' . $new_status, 'ERROR');
			$transactionStatus = "APC Declined";
		} else {
			$this->debugLog('process APC OK', 'message', 'debug');
			$transactionStatus = "APC Authorised";
		}
			}
		
		} else {	
			$response = $this->_processAPC($nochex_data, $method, $virtuemart_order_id);	$this->debugLog('process APC ' . $response, 'message', 'debug');
		if (!strstr($response, "AUTHORISED")) {
			$new_status = $method->status_canceled;
			$this->debugLog('process APC ' . $response  . ' ' . $new_status, 'ERROR');
			$transactionStatus = "APC Declined";
		} else {
			$this->debugLog('process APC OK', 'message', 'debug');
			$transactionStatus = "APC Authorised";
		}
		}
		
		$new_status = $this->_getPaymentStatus($method, $response);

		$this->debugLog('plgVmOnPaymentNotification return new_status:' . $new_status, 'message', 'debug');

		$modelOrder = VmModel::getModel('orders');

		$order = array();
		$order['order_status'] = $new_status;
		$order['customer_notified'] =1;
		$order['comments'] = "<ul style=\"text-align:left\"><li> Order ID: " . $nochex_data['order_id'] . "</li><li>Transaction ID: " . $nochex_data['transaction_id'] . "</li><li>Transaction Status: " . $transactionStatus . "</li></ul>";
		$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

		$this->debugLog('Notification, sentOrderConfirmedEmail ' . $order_number . ' ' . $new_status, 'message', 'debug');

		$this->emptyCart($return_context);
	}

	function _getPaymentStatus($method, $nochex_status) {
		// Use the response from APC
		$new_status = '';
		if (strcmp($nochex_status, 'AUTHORISED') == 0) {
			$new_status = $method->status_success;
		} elseif (strcmp($nochex_status, 'DECLINED') == 0) {
			$new_status = $method->status_canceled;
		} else {
			$new_status = $method->status_pending;
		}
		return $new_status;
	}

	/**
	 * Complete APC process 
	 *
	 * @param array $data
	 * @return string DECLINED or AUTHORISED from Nochex server
	 * @access protected
	 */
	function _processAPC($nochex_data, $method) {
	
		$secure_post = $method->secure_post;
		$nochex_url = "www.nochex.com";

		$post_msg = http_build_query($nochex_data);
		// post back to Nochex system to validate
		$header = "POST /apcnet/apc.aspx HTTP/1.0\r\n";
		$header .= "Host: www.nochex.com\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($post_msg) . "\r\n\r\n";

		if ($secure_post) {
			// If possible, securely post back to Nochex using HTTPS
			// Your PHP server will need to be SSL enabled
			$fps = fsockopen('ssl://' . $nochex_url, 443, $errno, $errstr, 30);
		} else {
			$fps = fsockopen($nochex_url, 80, $errno, $errstr, 30);
		}


		if (!$fps) {
			$this->sendEmailToVendorAndAdmins("error with nochex", JText::sprintf('VMPAYMENT_NOCHEX_ERROR_POSTING_APC', $errstr, $errno));
			return JText::sprintf('VMPAYMENT_NOCHEX_ERROR_POSTING_APC', $errstr, $errno); // send email
		} else {
			fputs($fps, $header . $post_msg);
			while (!feof($fps)) {
				$res = fgets($fps, 1024);
				if (strcmp($res, 'AUTHORISED') == 0) {
					return 'AUTHORISED';
				} elseif (strcmp($res, 'DECLINED') == 0) {
					$this->sendEmailToVendorAndAdmins("error with nochex APC NOTIFICATION", JText::_('VMPAYMENT_NOCHEX_ERROR_APC_VALIDATION') . $res);
					return 'DECLINED';
				}
			}
		}

		fclose($fps);
		return '';
	}
	
	
	/**
	 * Complete Callback process 
	 *
	 * @param array $data
	 * @return string DECLINED or AUTHORISED from Nochex server
	 * @access protected
	 */
	
	function _processCallback($nochex_data, $method) {
	
		$secure_post = $method->secure_post;
		$nochex_url = "secure.nochex.com";

		$post_msg = http_build_query($nochex_data);
		// post back to Nochex system to validate
		$header = "POST /callback/callback.aspx HTTP/1.0\r\n";
		$header .= "Host: secure.nochex.com\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($post_msg) . "\r\n\r\n";

		// Your PHP server will need to be SSL enabled
		$fps = fsockopen('ssl://' . $nochex_url, 443, $errno, $errstr, 30);

		if (!$fps) {
			$this->sendEmailToVendorAndAdmins("error with nochex", JText::sprintf('VMPAYMENT_NOCHEX_ERROR_POSTING_APC', $errstr, $errno));
			return JText::sprintf('VMPAYMENT_NOCHEX_ERROR_POSTING_APC', $errstr, $errno); // send email
		} else {
			fputs($fps, $header . $post_msg);
			while (!feof($fps)) {
				$res = fgets($fps, 1024);
				if (strcmp($res, 'AUTHORISED') == 0) {
					return 'AUTHORISED';
				} elseif (strcmp($res, 'DECLINED') == 0) {
					$this->sendEmailToVendorAndAdmins("error with nochex APC NOTIFICATION", JText::_('VMPAYMENT_NOCHEX_ERROR_APC_VALIDATION') . $res);
					return 'DECLINED';
				}
			}
		}

		fclose($fps);
		return '';
	}
 

	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
	
	
		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/*
	 * plgVmonSelectedCalculatePricePayment
	* Calculate the price (value, tax_id) of the selected method
	* It is called by the calculator
	* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	* @author Valerie Isaksen
	* @cart: VirtueMartCart the current cart
	* @cart_prices: array the new cart prices
	* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	*
	*
	*/

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {		
		return $this->onCheckAutomaticSelected($cart, $cart_prices);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

}

// No closing tag
