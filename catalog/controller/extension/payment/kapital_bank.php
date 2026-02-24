<?php

class ControllerExtensionPaymentKapitalBank extends Controller {
    public function index() {
        $data = [
            'taksit_enabled' => $this->config->get('payment_kapital_bank_taksit_check'),
            'taksits'        => explode(',', $this->config->get('payment_kapital_bank_taxes'))
        ];

        return $this->load->view('extension/payment/kapital_bank', $data);
    }

    public function confirm() {
        $json = [];

        if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] == 'kapital_bank') {

            $this->load->model('checkout/order');
            $this->load->model('extension/payment/kapital_bank');
            $taksit = 0;
            if ($this->config->get('payment_kapital_bank_taksit_check')) {
                $taksits = explode(',', $this->config->get('payment_kapital_bank_taxes'));
                $taksit = isset($this->request->get['kapital_bank_taksit']) ? $this->request->get['kapital_bank_taksit'] : 0;
                if ($taksit != 0 && !in_array($taksit, $taksits)) {
                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode([
                                                               'error'   => true,
                                                               'message' => 'Invalid taksit amount'
                                                           ]));
                    return;
                }
            }

            $order = $this->model_extension_payment_kapital_bank->getOrder($this->session->data['order_id']);
            if ($order) {
                $this->model_extension_payment_kapital_bank->updateOrder($this->session->data['order_id'], null, null, $taksit);
            } else {
                $this->model_extension_payment_kapital_bank->insertOrder($this->session->data['order_id'], null, null, $taksit);
            }
            require_once DIR_APPLICATION . 'model/extension/kapital_bank/PaymentAdapter.php';
            require_once DIR_APPLICATION . 'model/extension/kapital_bank/KapitalBankPaymentAdapter.php';
            $adapter = new KapitalBankPaymentAdapter('kapital_bank', $this->config->get('payment_kapital_bank_merchant_id'), $this->config->get('payment_kapital_bank_certificate'), $this->config->get('payment_kapital_bank_certificate_key'), $this->config->get('payment_kapital_bank_test'));
            $adapter->setLocale(strtoupper($this->language->get('code')));
            $adapter->setModelCheckoutOrder($this->model_checkout_order);
            $adapter->setKapitalModel($this->model_extension_payment_kapital_bank);
            $adapter->setOrder($this->session->data['order_id']);
            $adapter->setCurrency($this->session->data['currency']);
            try {
                $adapter->create();
                $url = $adapter->toPayment();
                $json['error'] = false;
                $json['redirect'] = $url;
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
            } catch (Exception $e){
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode([
                                                           'error'   => true,
                                                           'message' => 'An error occurred while sending request to bank'
                                                       ]));
            }

        }
    }

    public function callback() {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/kapital_bank');
        require_once DIR_APPLICATION . 'model/extension/kapital_bank/PaymentAdapter.php';
        require_once DIR_APPLICATION . 'model/extension/kapital_bank/KapitalBankPaymentAdapter.php';
        $adapter = new KapitalBankPaymentAdapter('kapital_bank', $this->config->get('payment_kapital_bank_merchant_id'), $this->config->get('payment_kapital_bank_certificate'), $this->config->get('payment_kapital_bank_certificate_key'), $this->config->get('payment_kapital_bank_test'));
        $adapter->setLocale(strtoupper($this->language->get('code')));
        $adapter->setModelCheckoutOrder($this->model_checkout_order);
        $adapter->setKapitalModel($this->model_extension_payment_kapital_bank);
        $adapter->setOrder($_GET['id']);
        try {
            if ($adapter->checkExecuteReady()) {
                $adapter->execute();
                if ($adapter->getState() == 'approved') {
                    $this->model_checkout_order->addOrderHistory($_GET['id'], 15, 'Payment received');
                    header('Location: ' . $this->url->link('checkout/success'));
                    exit();
                }

                $this->model_checkout_order->addOrderHistory($_GET['id'], 10, 'payment failed');
                header('Location: ' . $this->url->link('checkout/failure'));
            } else {
                $this->model_checkout_order->addOrderHistory($_GET['id'], 7, 'payment cancelled');
                header('Location: ' . $this->url->link('checkout/failure'));
            }
        } catch (Exception $e){
            header('Location: ' . $this->url->link('checkout/failure'));
        }

        header('Location: ' . $this->url->link('checkout/failure'));
    }
}
