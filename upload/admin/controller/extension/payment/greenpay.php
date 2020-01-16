<?php

if(!defined("GREENPAY_WEBSITE")){
	define("GREENPAY_WEBSITE", "https://greenbyphone.com/");
}
if(!defined("GREENPAY_ENDPOINT")){
	define("GREENPAY_ENDPOINT", GREENPAY_WEBSITE . "OpenCart.asmx" . "/");
}

if(!defined("GREENPAY_VERSION")){
	define("GREENPAY_VERSION", '2.0.0');
}

class ControllerExtensionPaymentGreenPay extends Controller {
    private $variables = array();
    private $lastAPIError = "";

	public function index()
    {
        // Load settings
        $this->load->model('setting/setting');

        // Load language
        $this->variables = $this->load->language('extension/payment/greenpay');
		$this->variables['error_form_req'] = false;

        $this->document->setTitle($this->language->get('heading_title'));

        // If POST request => validate the data before saving
        $this->variables['green_api_error'] = false;
        $this->variables['oc_api_error'] = false;
        $this->variables['success'] = false;
        $this->variables['failedSave'] = false;
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            // Edit settings
            $this->request->post[$this->prefix() . 'greenpay_saved'] = false;
           
            //Check for Green API credentials and OpenCart store validation 
            $storeId = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_store_id');
            if($storeId == null || strlen($storeId) == 0){
                $storeId = 0;
            }

            $enabled =  $this->request->post[$this->prefix() . 'greenpay_status'];
            if($enabled == null || strlen($enabled) == 0){
                $enabled = "false";
            } else {
                $enabled = "true";
            }

            $data = $this->postGreenAPI('RegisterStore', array(
                "Client_ID" =>  $this->request->post[$this->prefix() . 'greenpay_client_id'],
                "ApiPassword" =>  $this->request->post[$this->prefix() . 'greenpay_api_password'],
                "StoreID" => $storeId,
                "OCUsername" =>  $this->request->post[$this->prefix() . 'greenpay_oc_username'],
                "OCKey" =>  $this->request->post[$this->prefix() . 'greenpay_oc_key'],
                "Domain" =>  $this->request->post[$this->prefix() . 'greenpay_domain'],
                "Enabled" => $enabled,
                "PaymentMode" =>  $this->request->post[$this->prefix() . 'greenpay_payment_mode'],
                "VerificationMode" =>  $this->request->post[$this->prefix() . 'greenpay_verification_mode'],
                "PluginVersion" => GREENPAY_VERSION
            ), $this->request->post[$this->prefix() . 'greenpay_debug']);

            if($data == null || !($data instanceof SimpleXMLElement) || (string)$data->CredentialResult->Result != "0"){
                $this->variables["green_api_error"] = true;
                $this->variables['failedSave'] = true;
            }
            
            if($data == null || !($data instanceof SimpleXMLElement) || (string)$data->OpenCartResult->Result != "0"){
                $this->variables["oc_api_error"] = true;
                $this->variables['failedSave'] = true;
            }

            if($data != null && $data instanceof SimpleXMLElement && (int)$data->StoreConfigurationID != 0){
                //We received a store ID back which means our API was able to store this information. Let's save that store ID in our configuration
                $this->request->post[$this->prefix() . 'greenpay_store_id'] = (int)$data->StoreConfigurationID;
                $this->request->post[$this->prefix() . 'greenpay_saved'] = true;
                $this->request->post[$this->prefix() . 'greenpay_verde_allowed'] = (boolean)$data->VerdeCapable;
                $this->variables['success'] = true;
            }

            $this->model_setting_setting->editSetting($this->prefix() . 'greenpay', $this->request->post);
        }

        // Load default layout
        $this->variables['header'] = $this->load->controller('common/header');
        $this->variables['column_left'] = $this->load->controller('common/column_left');
        $this->variables['footer'] = $this->load->controller('common/footer');

        $this->variables['cancel_link'] = (version_compare(VERSION, '3.0', '>=')) ? $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true) : $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);

        // Load setting values
        $this->variables['greenpay_client_id'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_client_id');
        $this->variables['greenpay_api_password'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_api_password');
        $this->variables['greenpay_domain'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_domain');
        if(strlen($this->config->get($this->prefix() . 'greenpay_domain')) == 0){
            $site_url = $this->config->get("site_ssl");
            $site_url_parts = explode("/", $site_url);
            //Site comes with /admin/ attached at the end and we only want the base OpenCart domain so we have to pop twice
            array_pop($site_url_parts);
            array_pop($site_url_parts);
            $site_url = implode("/", $site_url_parts);
            $this->variables['greenpay_domain'] = $site_url;
        }
        $this->variables['greenpay_oc_username'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_oc_username');
        $this->variables['greenpay_oc_key'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_oc_key');
        $this->variables['greenpay_payment_mode'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_payment_mode');
        $this->variables['greenpay_status'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_status');
        $this->variables['greenpay_saved'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_saved');
        $this->variables['greenpay_verification_mode'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_verification_mode');
        $this->variables['greenpay_verde_allowed'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_verde_allowed');
        $this->variables['greenpay_verde_enabled'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_verde_enabled');
        $this->variables['greenpay_debug'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_debug');

        // Alerts
        $this->variables['no_permission'] = false;
        $this->variables['no_method'] = false;
        

        if (!$this->variables['greenpay_status'] || $this->variables['greenpay_status'] != "true") { 
            //If no method available
            $this->variables['no_method'] = true;
        }

        // Load tabs
        // Configuration
        $this->variables['verde'] = "";
        if($this->variables['greenpay_verde_allowed']){
            $this->variables["verde"] = $this->load->view('extension/payment/greenpay_config_verde', $this->variables);
        }
        $this->variables['config'] = $this->load->view('extension/payment/greenpay_config', $this->variables);

        $this->response->setOutput($this->load->view('extension/payment/greenpay', $this->variables));
    }

	private function prefix() {
        return (version_compare(VERSION, '3.0', '>=')) ? 'payment_' :  '';
    }

    /**
     * Validate the configuration before it is saved to the database in opencart.
     * 
     * This function is only responsible for determining the actual keys existence and seeming accuracy.
     * The index() function that calls this will attempt to validate the actual API information and display errors as necessary.
     * 
     * @return bool
     */
	private function validate()
    {
        $error = false;

        if (!$this->user->hasPermission('modify', 'extension/payment/greenpay')) {
            $this->variables['no_permission'] = true;
            $error = true;
        }
        
        if($this->request->post['greenpay_client_id'] == "" || 
            $this->request->post['greenpay_api_password'] == "" || 
            $this->request->post['greenpay_domain'] == "" || 
            $this->request->post['greenpay_oc_key'] == "" || 
            $this->request->post['greenpay_oc_username'] == ""){
			$this->variables['error_form_req'] = true;
			$error = true;
        }
        foreach ($this->request->post as $key => $value) {
            unset($this->request->post[$key]);
            $this->request->post[$this->prefix() . $key] = $value; //concatinate your existing array with new one
        }
        
        return !$error; // If no error => validated
    }

    /**
     * Make a call to the Green API with the given data
     * 
     * @param string $method            The method at the API endpoint to be called
     * @param mixed $data               Either string or array. If given, will be added as a CURLOPT_POSTFIELDS to the request
     * @param bool $debug               Whether we should output the call info to the error log
     * 
     * @return SimpleXMLElement|null    The XML object returned by the API read into an array by simplexml library or null on error
     */
    private function postGreenAPI($method, $data, $debug = false){
        if($debug == true){
            error_log("[GreenPay] Beginning API call to " . GREENPAY_ENDPOINT . $method );
            error_log("[GreenPay] Raw data being sent: \r\n" . print_r($data, true));
        }

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

            if($debug == true){
                error_log("[GreenPay] Query string: " . implode("&", $params));
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $params));
        }

        $response = null;
        try {
            $result = curl_exec($ch);
            $response = @simplexml_load_string($result); //@ specifies to ignore warnings thrown by this attempt to load the XML into an object

            if($debug == true){
                error_log("[GreenPay] Raw response from API: \r\n" . $result );
                error_log("[GreenPay] Decoded response from API: \r\n" . print_r($response, true));
            }
        } catch(Exception $e) {
            // Redirect to the cart and display error
            error_log("[GreenPay] Exception occurered while attempting to decode response from API call: " . $e->getMessage());
            $this->lastAPIError = $e->getMessage();
        } finally {
            curl_close($ch);
        }

        return $response;
    }

	public function install()
    {
        $this->load->model('extension/payment/greenpay');
        $this->model_extension_payment_greenpay->install();
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/greenpay');
        $this->postGreenAPI("Uninstall", array(
            "Client_ID" => $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_client_id'),
            "ApiPassword" => $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_api_password'),
            "StoreID" => $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_store_id'),
        ));
        $this->model_extension_payment_greenpay->uninstall();
    }

}
