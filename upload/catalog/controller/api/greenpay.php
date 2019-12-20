<?php 

class ControllerApiGreenPay extends Controller {
    /**
     * Returns an array of all orders under the Processing status that were made with the GreenPay extension
     * 
     * Route: /api/greenpay/orders
     */
    public function orders(){
        $this->load->language('api/greenpay');
        $json = array();

        $json["debug"] = "GreenPay extension orders function called...\r\n";
        $json["debug"]["request"] = print_r($this->request, true); 
    
        if (!isset($this->session->data['api_id'])) {
            $json["debug"] = "this->session->data[api_id] was not set...\r\n";
            $json['error']['warning'] = $this->language->get('error_permission');
        } else {
            // load model
            $json["debug"] = "Orders being loaded...\r\n";
            $this->load->model('extension/payment/greenpay');
        
            // get orders under the processing status
            $orders = $this->model_extension_payment_greenpay->get_all_greenpay_order();
            $json['success']['orders'] = $orders;
        }
        
        if (isset($this->request->server['HTTP_ORIGIN'])) {
            $this->response->addHeader('Access-Control-Allow-Origin: ' . $this->request->server['HTTP_ORIGIN']);
            $this->response->addHeader('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
            $this->response->addHeader('Access-Control-Max-Age: 1000');
            $this->response->addHeader('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }
    
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}