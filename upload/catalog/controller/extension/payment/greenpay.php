<?php

define("GREENPAY_ENDPOINT", "https://cpsandbox.com/OpenCart.asmx/");

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
        $greenConfig['greenpay_store_id'] = $this->config->get($this->prefix() . 'greenpay_store_id');
        $greenConfig['greenpay_api_password'] = $this->config->get($this->prefix() . 'greenpay_api_password');
        $greenConfig['greenpay_oc_username'] = $this->config->get($this->prefix() . 'greenpay_oc_username');
        $greenConfig['greenpay_oc_key'] = $this->config->get($this->prefix() . 'greenpay_oc_key');
        $greenConfig['greenpay_domain'] = $this->config->get($this->prefix() . 'greenpay_domain');
        $greenConfig['greenpay_payment_mode'] = $this->config->get($this->prefix() . 'greenpay_payment_mode');

        if(strlen($this->config->get($this->prefix() . 'greenpay_domain')) == 0){
            $site_url = $this->config->get("site_ssl");
            $site_url_parts = explode("/", $site_url);
            //Site comes with /admin/ attached at the end and we only want the base OpenCart domain so we have to pop twice
            array_pop($site_url_parts);
            array_pop($site_url_parts);
            $site_url = implode("/", $site_url_parts);
            $greenConfig['greenpay_domain'] = $site_url;
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
        $data['button_continue'] = $this->language->get('button_continue');
        $data['text_loading'] = $this->language->get('text_loading');

        return $this->load->view('extension/payment/greenpay', $data);
    }

    /**
     * Handles the actual checkout process. After validating the payment, the http response is redirected to the correct spot, usually checkout/success
     */
    public function checkout()
    {
        error_log("Starting the checkout process...");
        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            //No products & no vouchers or the items aren't in stock and we require stock, redirect to the cart
            error_log("No products, no vouches, or the items aren't in stock...");
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        if (!isset($this->request->post['greenpay_account_number']) || !isset($this->request->post['greenpay_routing_number'])) {
            // If either the account or routing numbers are unset, then we need to redirect back to the cart and show errors
            error_log("Routing or account number are missing...");
            $this->session->data['error'] = $this->language->get('error_both_required');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $account_number = trim($this->request->post['greenpay_account_number']);
        $routing_number = trim($this->request->post['greenpay_routing_number']);
        
        if(strlen($account_number) == 0){
            //Don't just check for existence of keys, but trim them and make sure they have values
            error_log("account number empty..");
            $this->session->data['error'] = $this->language->get("error_account_empty");
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        }

        if(strlen($routing_number) == 0){
            error_log("routing number empty..");
            $this->session->data['error'] = $this->language->get("error_routing_empty");
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        }

        if(!is_numeric($account_number) || !is_numeric($routing_number)){
            //Additionally, check for whether or not both look like numbers
            error_log("non numeric routing or account..");
            $this->session->data['error'] = $this->language->get("error_routing_empty");
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        } 

        if(!is_numeric($routing_number)){
            //Let's check for Canadian format XXXXX-YYY
            $exploded = explode("-", $routing_number);
            if(count($exploded) <> 2 || !is_numeric($exploded[0]) || !is_numeric($exploded[1])){
                //It's not canadian format or one of the parts is not numeric.
                error_log("routing number non numeric and not canadian format..");
                $this->session->data['error'] = $this->language->get("error_routing_invalid");
                $this->response->redirect($this->url->link("checkout/checkout", "", true));
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
            error_log("Order not found in the store?..");
            //We couldn't find the order in the store
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $greenConfig = $this->getGreenmoneyConfig();
		$link = $greenConfig['greenpay_payment_mode'].'/CartCheck';        
        $check = array(
            "Client_ID" => $greenConfig['greenpay_client_id'],
            "APIPassword" => $greenConfig['greenpay_api_password'],
            "StoreID" => $greenConfig["greenpay_store_id"],
            "OrderID" => $order_id,
            "RoutingNumber" => $routing_number,
            "AccountNumber" => $account_number
        );

        error_log("data about to be sent to the api..");
        error_log(print_r($check, true));

        $response = $this->postGreenAPI("OneTimeDraft", $check);

        error_log("raw response from the API..");        
        error_log(print_r($response, true));
        if($response == null || !($response instanceof SimpleXMLElement)){
            //Error occurred
            error_log("[GreenPay] Error occurred while attempting to call to create check. Raw response: " . print_r($response, true));
            $this->session->data['error'] = "GreenPay Payment Error: " . $this->language->get('error_something_wrong');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        } else {
            //We've got the thing
            if((string)$response->Result->Result == "0"){
                //Success, check was made
                $this->model_extension_payment_greenpay->insertCheckData(array(
                    "order_id" => $order_info['order_id'],
                    "customer_id" => $order_info['customer_id'],
                    "eCommerceOrder_id" => (string)$response->ECommerceOrder_ID,
                    "check_id" => (string)$response->Check_ID,
                    "check_number" => (string)$response->CheckNumber
                ));

                $this->response->redirect($this->url->link('checkout/success', '', true));
            } else {
                //Code was non-zero meaning something occurred. Display the ResultDescription
                error_log("[GreenPay] Error occurred during call to create check. Raw response: " . print_r($response, true));
                $this->session->data["error"] = "GreenPay Payment Error: " . (string)$response->Result->ResultDescription;
                $this->response->redirect($this->url->link('checkout/checkout', '', true));
            }
        }
    }

    /**
     * Make a call to the Green API with the given data
     * 
     * @param string $method            The method at the API endpoint to be called
     * @param mixed $data               Either string or array. If given, will be added as a CURLOPT_POSTFIELDS to the request
     * 
     * @return SimpleXMLElement|null    The XML object returned by the API read into an array by simplexml library or null on error
     */
    private function postGreenAPI($method, $data){
        //DEBUG echo "<pre>";
        //DEBUG echo "Calling API: \r\n";
        //DEBUG echo "Endpoint: " . GREENPAY_ENDPOINT . $method . "\r\n";
        //DEBUG print_r($data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GREENPAY_ENDPOINT . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        if(isset($data)){
            $params = array();
            foreach($data as $key => $value){
                 $params[] = $key . "=" . urlencode($value);
            }
            //DEBUG echo "Query: " . implode("&", $params) . "\r\n";

            curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $params));
        }

        $response = null;
        try {
            $result = curl_exec($ch);
            //DEBUG echo "Raw: " . $result. "\r\n";
            $response = @simplexml_load_string($result); //@ specifies to ignore warnings thrown by this attempt to load the XML into an object
            //DEBUG print_r($response);
        } catch(Exception $e) {
            // Redirect to the cart and display error
            $this->lastAPIError = $e->getMessage();
        } finally {
            curl_close($ch);
        }

        //DEBUG echo "</pre>";
        return $response;
    }
}
