<?php

class ControllerExtensionPaymentGreenPay extends Controller
{
    // Constants
    const SUPPORTED_LANGS = array(
        'en' => 'en' // English
    );
    const DEFAULT_LANG = 'en';

    private $money_in_trans_details;

    /**
     * Versions prior to 3.0 didn't need a prefix, those after do. This handles returning the prefix everywhere in the class
     * 
     * @return string
     */
    private function prefix() {
        return (version_compare(VERSION, '3.0', '>=')) ? 'payment_' :  '';
    }

    /**
     * Test whether the current request was called via GET
     * 
     * @return bool
     */
    private function isGet()
    {
        return (strtoupper($this->request->server['REQUEST_METHOD']) == 'GET');
    }

    /**
     * Returns a given GET parameter 
     * 
     * @param string $key   The key we are looking for in the POST parameters.
     * 
     * @return string 
     */
    private function getValue($key)
    {
        return (isset($this->request->get[$key]) ? $this->request->get[$key] : null);
    }

    /**
     * Test whether the current request was called via POST
     * 
     * @return bool
     */
    private function isPost()
    {
        return (strtoupper($this->request->server['REQUEST_METHOD']) == 'POST');
    }

    /**
     * Returns a given POST parameter 
     * 
     * @param string $key   The key we are looking for in the POST parameters.
     * 
     * @return string 
     */
    private function postValue($key)
    {
        return (isset($this->request->post[$key]) ? $this->request->post[$key] : null);
    }

    /**
     * Get the configuration object
     * 
     * @return array
     */
    private function getGreenmoneyConfig()
    {
        $greenConfig = array();
        $greenConfig['greenpay_client_id'] = $this->config->get($this->prefix() . 'greenpay_client_id');
        $greenConfig['greenpay_api_password'] = $this->config->get($this->prefix() . 'greenpay_api_password');
        $greenConfig['greenpay_oc_key'] = $this->config->get($this->prefix() . 'greenpay_oc_key');

        if ($this->config->get($this->prefix() . 'greenpay_is_test_mode')) {
            $greenConfig['greenpay_payment_mode'] = "https://cpsandbox.com/OpenCart.asmx";
        } else {
            $greenConfig['greenpay_payment_mode'] = "https://greenbyphone.com/OpenCart.asmx";
        }

        return $greenConfig;
    }

    /**
     * This returns the view for selecting the payment option during checkout. I believe this returns /catalog/view/theme/default/template/extension/payment/greenpay.twig
     * 
     * @return object The View object loaded from the twig after setting data for the various links
     */
    public function index()
    {
        // Load language
        $this->load->language('extension/payment/greenpay');

        // Load Model
        $this->load->model('extension/payment/greenpay');

        $data['text_card'] = $this->language->get('text_card');
        $data['link_checkout'] = $this->url->link('extension/payment/greenpay/checkout', '', true);

        // // If card saved
        // $data['text_use_card'] = $this->language->get('text_use_card');
        // $data['text_save_new_card'] = $this->language->get('text_save_new_card');
        // $data['text_not_use_card'] = $this->language->get('text_not_use_card');
        // // If no card saved
        // $data['text_save_card'] = $this->language->get('text_save_card');

        $data['button_continue'] = $this->language->get('button_continue');
        $data['text_loading'] = $this->language->get('text_loading');

        return $this->load->view('extension/payment/greenpay', $data);
    }

    /**
     * Handles the actual checkout process. After validating the payment, the http response is redirected to the correct spot, usually checkout/success
     */
    public function checkout()
    {
        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            //No products & no vouchers or the items aren't in stock and we require stock, redirect to the cart
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        if (!isset($this->request->post['greenpay_account_number']) || !isset($this->request->post['greenpay_routing_number'])) {
            // If either the account or routing numbers are unset, then we need to redirect back to the cart and show errors
            $this->session->data['error'] = $this->language->get('error_both_required');
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        $account_number = trim($this->request->post['greenpay_account_number']);
        $routing_number = trim($this->request->post['greenpay_routing_number']);
        
        if(strlen($account_number) == 0){
            //Don't just check for existence of keys, but trim them and make sure they have values
            $this->session->data['error'] = $this->language->get("error_account_empty");
            $this->response->redirect($this->url->link("checkout/cart", "", true));
        }

        if(strlen($routing_number) == 0){
            $this->session->data['error'] = $this->language->get("error_routing_empty");
            $this->response->redirect($this->url->link("checkout/cart", "", true));
        }

        if(!is_numeric($account_number) || !is_numeric($routing_number)){
            //Additionally, check for whether or not both look like numbers
            $this->session->data['error'] = $this->language->get("error_routing_empty");
            $this->response->redirect($this->url->link("checkout/cart", "", true));
        } 

        if(!is_numeric($routing_number)){
            //Let's check for Canadian format XXXXX-YYY
            $exploded = explode("-", $routing_number);
            if(count($exploded) <> 2 || !is_numeric($exploded[0]) || !is_numeric($exploded[1])){
                //It's not canadian format or one of the parts is not numeric.
                $this->session->data['error'] = $this->language->get("error_routing_invalid");
                $this->response->redirect($this->url->link("checkout/cart", "", true));
            } 
        }

        //Load Language
        $this->load->language('extension/payment/greenpay');

        // Load Model
        $this->load->model('extension/payment/greenpay');
        $this->load->model('checkout/order');

        // Order info
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            //We couldn't find the order in the store
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        
        $greenConfig = $this->getGreenmoneyConfig();

		$link = $greenConfig['greenpay_payment_mode'].'/CartCheck';        
        $check = array(
            "Client_ID" => $greenConfig['greenpay_client_id'],
            "APIPassword" => $greenConfig['greenpay_api_password'],
            "Name" => $order_info['firstname'].' '.$order_info['lastname'],
            "EmailAddress" => $order_info['email'],
            "Phone" => str_replace(['(', ')', '-',' '], null, trim($order_info['telephone'])),
            "PhoneExtension" => "",
            "Address1" => $order_info["payment_address_1"],
            "Address2" => $order_info["payment_address_2"],
            "City" => $order_info["payment_city"],
            "State" => $order_info["payment_zone"],
            "Zip" => $order_info["payment_postcode"],
            "Country" => $order_info["payment_iso_code_2"],
            "RoutingNumber" => $routing_number,
            "AccountNumber" => $account_number,
            "Memo" => "",
            "Amount" => $order_info["total"],
            "Date" => date('m/d/Y', strtotime($order_info['date_added']))
        );

		// Curl Hit to generate check
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $check);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        try {
            $result = curl_exec($ch);
            curl_close($ch);
            $xml = @simplexml_load_string($result); //@ specifies to ignore warnings thrown by this attempt to load the XML into an object
            $json = json_encode($xml);
            $result_array = json_decode($json, TRUE);
            $error_msg = "";

            //Insert check if generated
            if( isset($result_array['Result']) && $result_array['Result']==0){
                $data['order_id'] = $order_info['order_id'];
                $data['customer_id'] = $order_info['customer_id'];
                $data["eCommerceOrder_id"] = $result_array["ECommerceOrder_ID"];
                $data['check_id'] = $result_array['Check_ID'];
                $data['CheckNumber'] = $result_array['CheckNumber'];

                $this->model_extension_payment_greenpay->insertCheckData($data);
                // Success => Order status 2 : Processing
                $this->model_checkout_order->addOrderHistory($order_info['order_id'], 2);
                $this->response->redirect($this->url->link('checkout/success', '', true));
            } elseif(isset($result_array["ResultDescription"])) {
                //An error occurred but we did parse it so it's a valid error
                $this->session->data['error'] = $result_array['ResultDescription'];
                $this->response->redirect($this->url->link('checkout/cart', '', true));
            } else{
                // completely unknown problem. WTF mate
                error_log("[GreenPay] Error occurred while attempting to decode and read result of call to create check. Raw response: " & $result);
                $this->session->data['error'] = "An unknown error occurred while attempting to retrieve response from payment provider. Please try again later.";
                $this->response->redirect($this->url->link('checkout/cart', '', true));
            }
        } catch(Exception $e) {
            curl_close($ch);
            // Redirect to the cart and display error
            $this->session->data['error'] = $this->language->get('error_something_wrong');
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        } finally {
            curl_close($ch);
        }
    }

	/**
     * This function should be called by a CRON Job on a regular basis. If we can get rid of this in favor of using real push updates, we absolutely should.
     */
	public function method_cron(){
		// Load Model
        $this->load->model('extension/payment/greenpay');
		$this->load->model('checkout/order');

		$greenpayOrders = $this->model_extension_payment_greenpay->get_all_greenpay_order();
		if( $greenpayOrders ){
            $greenConfig = $this->getGreenmoneyConfig();
			foreach($greenpayOrders as $order){
                $order_id = $order['order_id'];
                
                $status = array(
                    "Client_ID" => $greenConfig['greenpay_client_id'],
                    "APIPassword" => $greenConfig['greenpay_api_password'],
                    "Check_ID" => $order['check_id']
                );

				// Curl Hit to generate check
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $greenConfig['greenpay_payment_mode'] . '/CartCheckStatus');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $status);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				$result = curl_exec($ch);
				curl_close($ch);

				$xml = @simplexml_load_string($result);
				$json = json_encode($xml);
				$result_array = json_decode($json, TRUE);
				$error_msg = "";
				if(isset($result_array['Result']) && $result_array['Result'] == 0 ){
					if(isset($result_array['VerifyResult']) && $result_array['VerifyResult'] == 0){
						// Update Order status to completed : 5
						$this->model_checkout_order->addOrderHistory($order['order_id'], 5, @$result_array['VerifyResultDescription']);
					}
				}
			}
		}
	}
}
