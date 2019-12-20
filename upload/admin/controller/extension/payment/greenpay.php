<?php
define('GREENPAY_VERSION', '2.0.0');

class ControllerExtensionPaymentGreenPay extends Controller {
	private $variables = array();
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
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            // Edit settings
            $this->model_setting_setting->editSetting($this->prefix() . 'greenpay', $this->request->post);
           
            //TODO - check for Green API credentials validation
            //TODO - check for OpenCart API access validation
            if($this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_client_id') != "3326"){
                $this->variables['green_api_error'] = true;
                $this->variables['oc_api_error'] = true;
            }
        }

        // Load default layout
        $this->variables['header'] = $this->load->controller('common/header');
        $this->variables['column_left'] = $this->load->controller('common/column_left');
        $this->variables['footer'] = $this->load->controller('common/footer');

        $this->variables['cancel_link'] = (version_compare(VERSION, '3.0', '>=')) ? $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true) : $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);

        // Load setting values
        $this->variables['greenpay_client_id'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_client_id');
        $this->variables['greenpay_api_password'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_api_password');
        $this->variables['greenpay_oc_key'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_oc_key');
        $this->variables['greenpay_payment_mode'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_payment_mode');
        $this->variables['greenpay_status'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_status');
        $this->variables['greenpay_is_test_mode'] = $this->model_setting_setting->getSettingValue($this->prefix() . 'greenpay_is_test_mode');


        // Alerts
        $this->variables['no_permission'] = false;
        $this->variables['no_method'] = false;
        $this->variables['success'] = false;


        if ($this->variables['greenpay_status']) { // If enabled
            // If Test mode
            if ($this->variables['greenpay_is_test_mode']) {
				$this->variables['success'] = true;
            }
        } else { // If no method enabled
            $this->variables['no_method'] = true;
        }

        // Load tabs
        // Configuration
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
        //  Test mode
		if( $this->request->post['greenpay_payment_mode'] =='https://cpsandbox.com/OpenCart.asmx'){
			$this->request->post['greenpay_is_test_mode'] = 0;
		}else{
			$this->request->post['greenpay_is_test_mode'] = 1;
        }
        
		if($this->request->post['greenpay_client_id'] == "" || $this->request->post['greenpay_api_password'] == "" || $this->request->post['greenpay_oc_key'] == ""){
			$this->variables['error_form_req'] = true;
			$error = true;
        }
        foreach ($this->request->post as $key => $value) {
            unset($this->request->post[$key]);
            $this->request->post[$this->prefix() . $key] = $value; //concatinate your existing array with new one
        }
		//echo '<pre>';print_r($this->variables);
        return !$error; // If no error => validated
    }

	public function install()
    {
        $this->load->model('extension/payment/greenpay');
        $this->model_extension_payment_greenpay->install();
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/greenpay');
        $this->model_extension_payment_greenpay->uninstall();
    }

}
