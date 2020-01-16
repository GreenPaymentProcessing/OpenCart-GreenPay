<?php

if(!defined("GREENPAY_WEBSITE")){
	define("GREENPAY_WEBSITE", "https://greenbyphone.com/");
}
if(!defined("GREENPAY_ENDPOINT")){
	define("GREENPAY_ENDPOINT", GREENPAY_WEBSITE . "OpenCart.asmx" . "/");
}

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

        $mode = $this->config->get($this->prefix() . 'greenpay_payment_mode') ? "" : " (Test mode)";

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
            $query = "INSERT INTO `" . DB_PREFIX . "greenpay_payment` (`order_id`, `customer_id`, `eCommerceOrder_id`, `check_id`, `check_number`)
                VALUES ( " .
                    $this->db->escape($data['order_id']) . ", " .
                    (int)$data['customer_id'] . ", " .
                    (int)$data['eCommerceOrder_id'] . ", " .
                    $this->db->escape($data['check_id']) . ",
                    '" . $this->db->escape($data['check_number']) . "'
                )";
			$this->db->query($query);
		}
    }

    /**
     * Get all GreenPay taken orders with a given status
     * 
     * @param array $data  An optional array of paramaters to further filter or sort the SQL query. 
     * Possible filters include filter_order_status, filter_order_id, filter_customer, filter_date_added, filter_date_modified, filter_total 
     * Other keys include "sort", "order", "start", and "limit"
     * 
     * @return array An array of rows from the database table greenpay_payment that match the criteria
     */
	public function get_all_greenpay_order($data = array()){
        $sql = "SELECT * FROM `" . DB_PREFIX . "order` o";

		if (!empty($data['filter_order_status'])) {
			$implode = array();

			$order_statuses = explode(',', $data['filter_order_status']);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "o.order_status_id = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
            $sql .= " WHERE o.order_status_id = o.order_status_id";
        }
        
        $sql .= " AND o.payment_code = 'greenpay'";

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND o.order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(o.firstname, ' ', o.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(o.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(o.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND o.total = '" . (float)$data['filter_total'] . "'";
		}

		$sort_data = array(
			'o.order_id',
			'customer',
			'order_status',
			'o.date_added',
			'o.date_modified',
			'o.total'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY o.order_id";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
		//return $this->db->query("SELECT * FROM `" . DB_PREFIX . "greenpay_payment` a INNER JOIN `" . DB_PREFIX . "order` b ON a.order_id = b.order_id WHERE b.`order_status_id` = ". (int)$order_status_id)->rows;
	}

	/**
     * Function will make an API call to our API that will register the session in our server
     * 
     * @return boolean True   If the call was made
     */
    public function start_session($clientId, $sessionId)
    {

        $options = array(
            "s" => $sessionId,
            "c" => $clientId
        );

		$debug = $this->config->get($this->prefix() . 'greenpay_debug');
        if($debug){
            error_log("[GreenPay] Beginning call to StartSession with data: \r\n" . print_r($options, true));
        }

        try {
            $ch = curl_init();

            if ($ch === FALSE) {
                throw new \Exception('Failed to initialize cURL');
            }
            
            $data_string = json_encode($options);
            curl_setopt_array($ch, array(
                CURLOPT_URL => GREENPAY_WEBSITE . "/FTFTokenizer.asmx/StartSession",
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string)
                ),
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_POSTFIELDS => $data_string
            ));
            $response = curl_exec($ch);
            curl_close($ch);
            return true;
        } catch (\Exception $e) {
            if($debug){
                error_log("[GreenPay] Exception occurred: " . $e->getMessage());
            }
            return false;
        }
    }
}
