<?php
class ModelExtensionPaymentKapitalBank extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/kapital_bank');

        $status = true;


		$method_data = [];

        if(!in_array($this->session->data['currency'], ['AZN'])){
            $status = false;
        }

		if ($status) {
			$method_data = [
				'code'       => 'kapital_bank',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_kapital_bank_sort_order'),
			];
		}

		return $method_data;
	}

    public function getOrder($order_id) {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "kapital_bank_order` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");


        if ($qry->num_rows) {
            return $qry->row;
        }

        return false;
    }

    public function updateOrder($order_id, $session_id, $ecomm_order_id, $taksit = 0){
        $session_id = $session_id ?:'null';
        $ecomm_order_id = $ecomm_order_id?:'null';
        $this->db->query("UPDATE `".DB_PREFIX."kapital_bank_order` set `eccommerce_session_id`='".$this->db->escape($session_id)."', `eccommerce_order_id`='".$this->db->escape($ecomm_order_id)."', taksit=".(int)$taksit.", `date`='".date('Y-m-d H:i:s')."'"." where order_id = ".$order_id);
    }
    public function insertOrder($order_id, $session_id, $ecomm_order_id, $taksit = 0){
	    $session_id = $session_id ?:'null';
	    $ecomm_order_id = $ecomm_order_id?:'null';
        $this->db->query("INSERT INTO `".DB_PREFIX."kapital_bank_order` set `eccommerce_session_id`='".$this->db->escape($session_id)."', `eccommerce_order_id`='".$this->db->escape($ecomm_order_id)."', taksit=".(int)$taksit.", order_id = ".$order_id.", `date`='".date('Y-m-d H:i:s')."'");
    }
}
