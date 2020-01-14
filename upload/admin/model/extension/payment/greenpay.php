<?php
class ModelExtensionPaymentGreenPay extends Model
{
	public function install()
    {
       $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "greenpay_payment` (
            `id` int(11) AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `customer_id` int(11) NOT NULL,
            `eCommerceOrder_id` int(11) NOT NULL,
            `check_id` varchar(255) NOT NULL,
            `check_number` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}
    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "greenpay_payment`");
    }
}
