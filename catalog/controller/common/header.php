<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		// Analytics
		$this->load->model('setting/extension');

		$data['analytics'] = array();

		$analytics = $this->model_setting_extension->getExtensions('analytics');

		foreach ($analytics as $analytic) {
			if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
				$data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
			}
		}

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}

		$data['title'] = $this->document->getTitle();

		$data['base'] = $server;
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->config->get('config_name');

		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist());
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));
		
		$data['home'] = $this->url->link('common/home');
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['logged'] = $this->customer->isLogged();
		$data['account'] = $this->url->link('account/account', '', true);
		$data['register'] = $this->url->link('account/register', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['shopping_cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout', '', true);
		$data['contact'] = $this->url->link('information/contact');
		$data['telephone'] = $this->config->get('config_telephone');
		
		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');
		
		$installment_config = array(
			'enabled' => true,
			'label'   => 'Ayliq:',
			'plans'   => array(
				array('months' => 3, 'rate' => 10),
				array('months' => 6, 'rate' => 10),
				array('months' => 9, 'rate' => 10),
			),
			'banks'   => array(),
		);

		$installment_config_file = DIR_SYSTEM . 'config/installment.php';

		if (is_file($installment_config_file)) {
			$custom_installment_config = require($installment_config_file);

			if (is_array($custom_installment_config)) {
				if (isset($custom_installment_config['enabled'])) {
					$installment_config['enabled'] = (bool)$custom_installment_config['enabled'];
				}

				if (isset($custom_installment_config['label']) && is_string($custom_installment_config['label'])) {
					$installment_config['label'] = $custom_installment_config['label'];
				}

				if (isset($custom_installment_config['plans']) && is_array($custom_installment_config['plans'])) {
					$normalized_plans = array();

					foreach ($custom_installment_config['plans'] as $month_key => $plan_value) {
						$months = 0;
						$rate = 0.0;

						if (is_array($plan_value)) {
							$months = isset($plan_value['months']) ? (int)$plan_value['months'] : (int)$month_key;
							$rate = isset($plan_value['rate']) ? (float)$plan_value['rate'] : 0.0;
						} else {
							$months = (int)$month_key;
							$rate = (float)$plan_value;
						}

						if ($months > 0) {
							$normalized_plans[] = array(
								'months' => $months,
								'rate'   => $rate,
							);
						}
					}

					if ($normalized_plans) {
						usort($normalized_plans, function($a, $b) {
							return (int)$a['months'] - (int)$b['months'];
						});

						$installment_config['plans'] = $normalized_plans;
					}
				}

				if (isset($custom_installment_config['banks']) && is_array($custom_installment_config['banks'])) {
					$normalized_banks = array();

					foreach ($custom_installment_config['banks'] as $bank_id => $bank) {
						if (!is_array($bank) || empty($bank['plans']) || !is_array($bank['plans'])) {
							continue;
						}

						$bank_name = isset($bank['name']) && is_string($bank['name']) ? $bank['name'] : (string)$bank_id;
						$bank_logo = isset($bank['logo']) && is_string($bank['logo']) ? $server . ltrim($bank['logo'], '/') : '';
						$bank_plans = array();

						foreach ($bank['plans'] as $months => $rate) {
							$months = (int)$months;

							if ($months <= 0) {
								continue;
							}

							$bank_plans[] = array(
								'months' => $months,
								'rate'   => (float)$rate,
							);
						}

						if (!$bank_plans) {
							continue;
						}

						usort($bank_plans, function($a, $b) {
							return (int)$a['months'] - (int)$b['months'];
						});

						$normalized_banks[] = array(
							'id'    => (string)$bank_id,
							'name'  => $bank_name,
							'logo'  => $bank_logo,
							'plans' => $bank_plans,
						);
					}

					$installment_config['banks'] = $normalized_banks;

					if ($normalized_banks && !empty($normalized_banks[0]['plans'])) {
						$installment_config['plans'] = $normalized_banks[0]['plans'];
					}
				}
			}
		}

		$data['installment_config'] = $installment_config;

		return $this->load->view('common/header', $data);
	}
}
