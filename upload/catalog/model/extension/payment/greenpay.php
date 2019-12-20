<?php
class ModelExtensionPaymentGreenPay extends Model
{
    private function prefix() {
        return (version_compare(VERSION, '3.0', '>=')) ? 'payment_' :  '';
    }

    /**
     * Returns the method information for the front end checkout page
     * 
     * @return array
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


    /**
     * Handle inserting a row into the database to store the payment information of a order
     */
    public function insertCheckData($data)
    {
        if ($data) {
            $data['date_add'] = date('Y-m-d H:i:s');
            $query = "INSERT INTO `" . DB_PREFIX . "greenpay_payment` (`order_id`, `customer_id`, `eCommerceOrder_id`, `check_id`, `check_number`)
                VALUES ( " .
                    $this->db->escape($data['order_id']) . ", " .
                    (int)$data['customer_id'] . ", " .
                    (int)$data['eCommerceOrder_id'] . ", " .
                    $this->db->escape($data['check_id']) . ",
                    '" . $this->db->escape($data['CheckNumber']) . "'
                )";
			$this->db->query($query);
		}
    }

    /**
     * Get all GreenPay taken orders with a given status
     * 
     * @param int $order_status_id  Defaults to 2 - Processing
     * 
     * @return array An array of rows from the database table greenpay_payment that match the criteria
     */
	public function get_all_greenpay_order($order_status_id = 2){
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "greenpay_payment` a INNER JOIN `" . DB_PREFIX . "order` b ON a.order_id = b.order_id WHERE b.`order_status_id` = ". (int)$order_status_id)->rows;
	}
}
