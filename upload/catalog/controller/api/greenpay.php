<?php 

class ControllerApiGreenPay extends Controller {
    /**
     * Just test the given username and API key and make sure it works for this store. 
     * 
     * Route: /api/greenpay/testAuthentication
     * 
     * @param $username string
     * @param $key      string
     */
    public function testAuthentication(){
        $this->load->language('api/greenpay');
        $this->load->model('account/api');
        
        $json = array();
        $authenticated = false;
        if(isset($this->request->post['username'])) {
			$authenticated = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);
		} else {
			$authenticated = $this->model_account_api->login('Default', $this->request->post['key']);
        }
        
        if($authenticated){
            $json["success"] = true;
        } else {
            $json["success"] = false;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Returns an array of all orders under the Processing status that were made with the GreenPay extension
     * 
     * Route: /api/greenpay/orders
     * 
     * @param $username string
     * @param $key      string
     * @param $start    integer     The SQL query will start at this record
     * @param $limit    integer     And pull up to a maximum of this many records after $start  
     */
    public function orders(){
        $this->load->language('api/greenpay');
        $this->load->model('account/api');
        
        $json = array();

        $json["debug"] = "GreenPay extension orders function called...\r\n";
        if(isset($this->request)){
            $json["request"] = print_r($this->request, true); 
        }

        $authenticated = false;
        if(isset($this->request->post['username'])) {
			$authenticated = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);
		} else {
			$authenticated = $this->model_account_api->login('Default', $this->request->post['key']);
		}
    
        if (!$authenticated) {
            $json["debug"] .= "API Key not validated...\r\n";
            $json['error']['warning'] = $this->language->get('error_permission');
        } else {
            // load model
            $json["debug"] .= "Orders being loaded...\r\n";
            $this->load->model('extension/payment/greenpay');
        
            // get orders under the processing status
            $data = array();
            if(isset($this->request->post["start"])){
                $data["start"] = $this->request->post["start"];
            }
            if(isset($this->request->post["limit"])){
                $data["start"] = $this->request->post["limit"];
            }

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

    /**
     * Returns a single order assuming that order was made with the GreenPay payment 
     * 
     * Route: /api/greenpay/order
     * 
     * @param $username string
     * @param $key      string
     * @param $orderId  string      The internal store order ID of the order in question   
     */
    public function order(){
        $this->load->language('api/greenpay');
        $this->load->model('account/api');
        
        $json = array();

        $json["debug"] = "GreenPay extension order function called...\r\n";
        if(isset($this->request)){
            $json["request"] = print_r($this->request, true); 
        }

        $authenticated = false;
        if(isset($this->request->post['username'])) {
			$authenticated = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);
		} else {
			$authenticated = $this->model_account_api->login('Default', $this->request->post['key']);
		}
    
        if (!$authenticated) {
            $json["debug"] .= "API Key not validated...\r\n";
            $json['error']['warning'] = "Not authorized or invalid credentials";
        } else {
            if(!isset($this->request->post["orderId"])){
                $json["debug"] .= "orderId parameter not passed...\r\n";
                $json['error']['warning'] = "Required parameter missing.";
            } else {
                // load model
                $json["debug"] .= "Orders being loaded...\r\n";
                $this->load->model('extension/payment/greenpay');
            
                // get orders under the processing status            
                $orders = $this->model_extension_payment_greenpay->get_all_greenpay_order(array("filter_order_id" => $this->request->post["orderId"]));
                $json['success']['orders'] = $orders;
            }
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

    /**
     * Adds an order history item to a singular order
     * 
     * Route: /api/greenpay/addOrderHistory
     * 
     * @param $username string
     * @param $key      string
     * @param $orderId  string      The internal store order ID of the order in question
     */
    public function addOrderHistory(){
        $this->load->language('api/greenpay');
        $this->load->model('account/api');
        $this->load->model('checkout/order');

        $json = array();

        $json["debug"] = "GreenPay extension order function called...\r\n";
        if(isset($this->request)){
            $json["request"] = print_r($this->request, true); 
        }

        $authenticated = false;
        if(isset($this->request->post['username'])) {
			$authenticated = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);
		} else {
			$authenticated = $this->model_account_api->login('Default', $this->request->post['key']);
		}
    
        if (!$authenticated) {
            $json["debug"] .= "API Key not validated...\r\n";
            $json['error']['warning'] = "Not authorized or invalid credentials";
        } else {
            if(!isset($this->request->post["orderId"]) || !isset($this->request->post["comment"]) || !isset($this->request->post["orderStatusId"])){
                $json["debug"] .= "required parameter not passed...\r\n";
                $json['error']['warning'] = "Required parameter missing.";
            } else {
                // load model
                $json["debug"] .= "Orders being loaded...\r\n";
                $this->load->model('extension/payment/greenpay');
                $orderId = $this->request->post["orderId"];
                $orderInfo = $this->model_checkout_order->getOrder($orderId);

                if($orderInfo){
                    $this->model_checkout_order->addOrderHistory($orderId, $this->request->post["orderStatusId"], $this->request->post['comment'], false, false);
                    $json["success"] = true;
                } else {
                    $json['error']['warning'] = "Order not found.";
                }
            }
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