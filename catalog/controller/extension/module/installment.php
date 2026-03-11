<?php
class ControllerExtensionModuleInstallment extends Controller {

	public function index() {
		$installment_config = require(DIR_SYSTEM . 'config/installment.php');

		if (empty($installment_config['enabled'])) {
			return '';
		}

		if (!isset($this->request->get['product_id'])) {
			return '';
		}

		if (!$this->customer->isLogged() && $this->config->get('config_customer_price')) {
			return '';
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct((int)$this->request->get['product_id']);

		if (!$product_info) {
			return '';
		}

		$effective_price = (!is_null($product_info['special']) && (float)$product_info['special'] >= 0)
			? (float)$product_info['special']
			: (float)$product_info['price'];

		$raw_price     = (float)$this->tax->calculate($effective_price, $product_info['tax_class_id'], $this->config->get('config_tax'));
		$display_total = $this->currency->format($raw_price, $this->session->data['currency']);

		$banks = array();

		foreach ($installment_config['banks'] as $bank_id => $bank) {
			$plans = array();
			foreach ($bank['plans'] as $months => $interest) {
				$plans[] = array(
					'months' => (int)$months,
					'rate'   => (float)$interest,
				);
			}
			$banks[] = array(
				'id'    => $bank_id,
				'name'  => $bank['name'],
				'logo'  => HTTP_SERVER . ltrim($bank['logo'], '/'),
				'plans' => $plans,
				'total' => $display_total,
			);
		}

		return $this->load->view('extension/module/installment', array(
			'raw_price' => $raw_price,
			'banks'     => $banks,
		));
	}
}
