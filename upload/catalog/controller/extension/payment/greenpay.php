<?php
//require_once DIR_SYSTEM . '/library/greenpay/GreenmoneyService.php'; // SEND REQUEST

class ControllerExtensionPaymentGreenPay extends Controller
{
    // Constants
    const SUPPORTED_LANGS = array(
        'da' => 'da', // Danish
        'de' => 'ge', // German
        'en' => 'en', // English
        'es' => 'sp', // Spanish
        'fi' => 'fi', // Finnish
        'fr' => 'fr', // French
        'it' => 'it', // Italian
        'ko' => 'ko', // Korean
        'no' => 'no', // Norwegian
        'pt' => 'po', // Portuguese
        'sv' => 'sw' // Swedish
    );
    const DEFAULT_LANG = 'en';

    private $money_in_trans_details;

    private function prefix() {
        return (version_compare(VERSION, '3.0', '>=')) ? 'payment_' :  '';
    }

    /*
    Check if there are GET params
    */
    private function isGet()
    {
        return (strtoupper($this->request->server['REQUEST_METHOD']) == 'GET');
    }

    /*
    Get GET param with $key
    */
    private function getValue($key)
    {
        return (isset($this->request->get[$key]) ? $this->request->get[$key] : null);
    }

    /*
    Check if there are POST params
    */
    private function isPost()
    {
        return (strtoupper($this->request->server['REQUEST_METHOD']) == 'POST');
    }

    /*
    Get POST param with $key
    */
    private function postValue($key)
    {
        return (isset($this->request->post[$key]) ? $this->request->post[$key] : null);
    }

    /*
    Get the config
    */
    private function getGreenmoneyConfig()
    {
        $config = array();
        $config['greenpay_client_id'] = $this->config->get($this->prefix() . 'greenpay_client_id');
        $config['greenpay_api_password'] = $this->config->get($this->prefix() . 'greenpay_api_password');

        if ($this->config->get($this->prefix() . 'greenpay_is_test_mode')) {
            $config['greenpay_payment_mode'] = "https://cpsandbox.com/OpenCart.asmx";
        } else {
            $config['greenpay_payment_mode'] = "https://greenbyphone.com/OpenCart.asmx";
        }

        return $config;
    }

    /*
    The view in the checkout page with the card choosing.
    By clicking on "Continue" a payment will be created in checkout()
    */
    public function index()
    {
        // Load language
        $this->load->language('extension/payment/greenpay');

        // Load Model
        $this->load->model('extension/payment/greenpay');

        $data['text_card'] = $this->language->get('text_card');
        $data['link_checkout'] = $this->url->link('extension/payment/greenpay/checkout', '', true);

        // If card saved
        $data['text_use_card'] = $this->language->get('text_use_card');
        $data['text_save_new_card'] = $this->language->get('text_save_new_card');
        $data['text_not_use_card'] = $this->language->get('text_not_use_card');
        // If no card saved
        $data['text_save_card'] = $this->language->get('text_save_card');

        $data['button_continue'] = $this->language->get('button_continue');
        $data['text_loading'] = $this->language->get('text_loading');

        return $this->load->view('extension/payment/greenpay', $data);
    }

    /*
    A payment is created here, the user is redirected to the payment page.
    After putting the card information, the user will be redirected to checkoutReturn()
    */
    public function checkout()
    {
        $available_cards = array('CB', 'VISA', 'MASTERCARD');

        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            // Redirect to the cart
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        if (!isset($this->request->post['greenpay_account_number']) && !isset($this->request->post['greenpay_routing_number'])) {
            // Redirect to the cart and display error
            $this->session->data['error'] = $this->language->get('error_card_type');
            $this->response->redirect($this->url->link('checkout/cart', '', true));
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
            // Redirect to the cart and display error
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        // Greenmoney config
        $config = $this->getGreenmoneyConfig();

		$link = $config['greenpay_payment_mode'].'/CartCheck';
		$client_id = $config['greenpay_client_id'];
		$api_pass = $config['greenpay_api_password'];
		$name = $order_info['firstname'].' '.$order_info['lastname'];
		$phone = str_replace(['(', ')', '-',' '], null, trim($order_info['telephone']));
		$PhoneExtension = "";
		$CheckMemo = "";
		$account_number = $this->request->post['greenpay_account_number'];
		$routing_number = $this->request->post['greenpay_routing_number'];
		$date = date('m/d/Y', strtotime($order_info['date_added']));

		$url_data = "Client_ID=".$client_id."&ApiPassword=".$api_pass."&Name=".$name."&EmailAddress=".$order_info['email']."&Phone=".$phone."&PhoneExtension=".$PhoneExtension."&Address1=".$order_info['payment_address_1']."&Address2=".$order_info['payment_address_2']."&City=".$order_info['payment_city']."&State=".$order_info['payment_zone']."&Zip=".$order_info['payment_postcode']."&Country=".$order_info['payment_iso_code_2']."&RoutingNumber=".$routing_number."&AccountNumber=".$account_number."&CheckMemo=".$CheckMemo."&CheckAmount=".$order_info['total']."&CheckDate=".$date;

		// Curl Hit to generate check
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$url_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        curl_close($ch);

        $xml = @simplexml_load_string($result);
        $json = json_encode($xml);
        $result_array = json_decode($json, TRUE);
        $error_msg = "";

		//Insert check if generated
		if( isset($result_array['Result']) && $result_array['Result']==0){
			$data['order_id'] = $order_info['order_id'];
			$data['customer_id'] = $order_info['customer_id'];
			$data['check_id'] = $result_array['Check_ID'];
			$data['CheckNumber'] = $result_array['CheckNumber'];

			$this->model_extension_payment_greenpay->insertOrUpdateCheck($data);
			// Success => Order status 2 : Processing
			$this->model_checkout_order->addOrderHistory($order_info['order_id'], 2);
			$this->response->redirect($this->url->link('checkout/success', '', true));
		} else{
			// if error
			$this->session->data['error'] = $result_array['ResultDescription'];
			$this->response->redirect($this->url->link('checkout/cart', '', true));
		}
		// Redirect to the cart and display error
		$this->session->data['error'] = $this->language->get('error_something_wrong');
		$this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    /*
    This page receive the response of Green Money about the payment then redirect the user to the appropriate order page
    (success, failure or back to checkout)
    */
    public function checkoutReturn(){
    }

	/*
	* Cron function
	*/
	public function method_cron(){
		// Load Model
        $this->load->model('extension/payment/greenpay');
		$this->load->model('checkout/order');

		$greenpayOrders = $this->model_extension_payment_greenpay->get_all_greenpay_order();
		if( $greenpayOrders ){
            $config = $this->getGreenmoneyConfig();
			$link = $config['greenpay_payment_mode'] . '/CartCheckStatus';
			$client_id = $config['greenpay_client_id'];
			$api_pass = $config['greenpay_api_password'];
			foreach($greenpayOrders as $order){
				$Check_ID = $order['check_id'];
				$order_id = $order['order_id'];
				$url_data = "Client_ID=".$client_id."&ApiPassword=".$api_pass."&Check_ID=".$Check_ID;
				// Curl Hit to generate check
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $link);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS,$url_data);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				$result = curl_exec($ch);
				curl_close($ch);

				$xml = @simplexml_load_string($result);
				$json = json_encode($xml);
				$result_array = json_decode($json, TRUE);
				$error_msg = "";
				if(isset($result_array['Result']) && $result_array['Result'] == 0 ){
					if(isset($result_array['VerifyResult']) && $result_array['VerifyResult'] == 0){
						echo $result_array['ResultDescription'].'('.$Check_ID.')'.' -  '.$result_array['VerifyResultDescription'].'('.$order_id.')';
						// Update Order status to completed : 5
						$this->model_checkout_order->addOrderHistory($order_id, 5, @$result_array['VerifyResultDescription']);
					}
				}
			}
		}
	} // End cron Function
}
