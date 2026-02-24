<?php

class ModelExtensionPaymentKapitalBank extends Model {

    public function install() {
        $this->db->query("
			CREATE TABLE `" . DB_PREFIX . "kapital_bank_order` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`order_id` int(11) NOT NULL,
				`eccommerce_session_id` varchar(255) DEFAULT NULL,
				`eccommerce_order_id` varchar(255) DEFAULT NULL,
				`taksit` int(2) NOT NULL,
				`date` DATETIME NOT NULL,
				KEY `order_id` (`order_id`),
				PRIMARY KEY `id` (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
		");
        $this->db->query("
			CREATE TABLE `" . DB_PREFIX . "kapital_bank_refunds` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`order_id` int(11) NOT NULL,
				`refund` float DEFAULT NULL,
				`currency` varchar(5) NOT NULL,
				`refund_text` TEXT DEFAULT NULL,
				`date` DATETIME NOT NULL,
				KEY `order_id` (`order_id`),
				PRIMARY KEY `id` (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
		");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "kapital_bank_order`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "kapital_bank_refunds`;");
    }

    public function getOrder($order_id) {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "kapital_bank_order` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");


        if ($qry->num_rows) {
            return $qry->row;
        }

        return false;
    }

    public function addRefund($order_id, $refund_amount, $currency, $refund_text) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "kapital_bank_refunds` set order_id=" . (int)$order_id . ", `currency`='".$this->db->escape($currency)."', refund=" . (float)$refund_amount . ", refund_text='" . $this->db->escape($refund_text ?: 'null') . "', date='" . date('Y-m-d H:i:s') . "'");
    }

    public function getRefunds($order_id) {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "kapital_bank_refunds` WHERE `order_id` = '" . (int)$order_id . "'");

        return $qry->rows;
    }
}
