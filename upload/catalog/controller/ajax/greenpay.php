<?php

class ControllerAjaxGreenPay extends Controller
{
    public function index()
    {
        $this->load->model('extension/payment/greenpay');
        $this->response->setOutput(true);
    }

    public function startSession()
    {
        $this->load->model('extension/payment/greenpay');

        if (isset($this->request->post["s"]) && isset($this->request->post["c"])) {
            $started = $this->model_extension_payment_greenpay->start_session($this->request->post["c"], $this->request->post["s"]);
            if($started){
                $this->response->setOutput($started);
            } else {
                throw new Exception("Unable to start login widget session.", 500);
            }
        } else {
            throw new Exception("Missing required parameter.", 500);
        }
    }

    public function test(){
        $this->load->model('extension/payment/greenpay');

        $this->response->setOutput(print_r($this, true));
    }
}
