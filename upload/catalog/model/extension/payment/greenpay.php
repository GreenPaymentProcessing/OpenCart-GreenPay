<?php
class ModelExtensionPaymentGreenPay extends Model
{
    private function prefix() {
        return (version_compare(VERSION, '3.0', '>=')) ? 'payment_' :  '';
    }

    /*
    This function is required for OpenCart to show the method in the checkout page
    */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/greenpay');

        $mode = $this->config->get($this->prefix() . 'greenpay_is_test_mode') ? "" : " (Test mode)";

        $method_data = array(
            'code'       => 'greenpay',
            'title'      => $this->language->get('text_card') . $mode,
            'terms'      => '',
            'sort_order' => ''
        );

        return $method_data;
    }

    /*
    Get the saved card of a customer
    */
    public function getCustomerCard($customerId){
    }

	/*
    Save or update the card of a customer
    */
    public function insertOrUpdateCard($data)
    {
	}
    /*
	*	Save or update the card of a customer
    */
    public function insertOrUpdateCheck($data)
    {
        if ($data) {
            $data['date_add'] = date('Y-m-d H:i:s');
            $query = "INSERT INTO `" . DB_PREFIX . "greenpay_payment` (`order_id`, `customer_id`, `check_id`, `check_number`)
                VALUES ( " .
                    $this->db->escape($data['order_id']) . ", " .
                    (int)$data['customer_id'] . ", " .
                    $this->db->escape($data['check_id']) . ",
                    '" . $this->db->escape($data['CheckNumber']) . "'
                )";
			$this->db->query($query);
		}
    }

    /*
    Private function to generate a random wkToken
    */
    private function generateUniqueToken($order_id)
    {
        return $order_id . "-" . time() . "-" . uniqid();
    }

	/*
	* Get all order which use Green money gateway with precessing status : 2
	*/
	public function get_all_greenpay_order(){
		return $this->db->query(
            "SELECT *
            FROM `" . DB_PREFIX . "greenpay_payment` a INNER JOIN `" . DB_PREFIX . "order` b ON a.order_id = b.order_id WHERE b.`order_status_id` = ". 2)->rows;
	}
}
