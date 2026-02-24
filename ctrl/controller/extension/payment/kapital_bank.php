<?php

class ControllerExtensionPaymentKapitalBank extends Controller {
    protected $error = [];

    public function index() {
        $this->load->language('extension/payment/kapital_bank');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->save_file('certificate');
            $this->save_file('certificate_key');
            $this->model_setting_setting->editSetting('payment_kapital_bank', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['username'])) {
            $data['error_username'] = $this->error['username'];
        } else {
            $data['error_username'] = '';
        }

        if (isset($this->error['password'])) {
            $data['error_password'] = $this->error['password'];
        } else {
            $data['error_password'] = '';
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/kapital_bank', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['action'] = $this->url->link('extension/payment/kapital_bank', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_kapital_bank_test'])) {
            $data['payment_kapital_bank_test'] = $this->request->post['payment_kapital_bank_test'];
        } else {
            $data['payment_kapital_bank_test'] = $this->config->get('payment_kapital_bank_test');
        }


        if (isset($this->request->post['payment_kapital_bank_taxes'])) {
            $data['payment_kapital_bank_taxes'] = $this->request->post['payment_kapital_bank_taxes'];
        } else {
            $data['payment_kapital_bank_taxes'] = $this->config->get('payment_kapital_bank_taxes');
        }

        if (isset($this->request->post['payment_kapital_bank_merchant_id'])) {
            $data['payment_kapital_bank_merchant_id'] = $this->request->post['payment_kapital_bank_merchant_id'];
        } else {
            $data['payment_kapital_bank_merchant_id'] = $this->config->get('payment_kapital_bank_merchant_id');
        }

        if (isset($this->request->post['payment_kapital_bank_taksit_check'])) {
            $data['payment_kapital_bank_taksit_check'] = $this->request->post['payment_kapital_bank_taksit_check'];
        } else {
            $data['payment_kapital_bank_taksit_check'] = $this->config->get('payment_kapital_bank_taksit_check');
        }

        if (isset($this->request->post['payment_kapital_bank_certificate'])) {
            $data['payment_kapital_bank_certificate'] = $this->request->post['payment_kapital_bank_certificate'];
        } else {
            $data['payment_kapital_bank_certificate'] = $this->config->get('payment_kapital_bank_certificate');
        }

        if (isset($this->request->post['payment_kapital_bank_certificate_key'])) {
            $data['payment_kapital_bank_certificate_key'] = $this->request->post['payment_kapital_bank_certificate_key'];
        } else {
            $data['payment_kapital_bank_certificate_key'] = $this->config->get('payment_kapital_bank_certificate_key');
        }


        if (isset($this->request->post['payment_kapital_bank_status'])) {
            $data['payment_kapital_bank_status'] = (int)$this->request->post['payment_kapital_bank_status'];
        } else {
            $data['payment_kapital_bank_status'] = $this->config->get('payment_kapital_bank_status');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['notice'] = 'The Advanced Kapital Bank gateway.<br> <i>To communicate with the Kapital Bank’s processing services the port 5443 on the server must be open.</i>';

        $this->response->setOutput($this->load->view('extension/payment/kapital_bank', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/kapital_bank')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $taksit_months = trim($this->request->post['payment_kapital_bank_taxes']);
        if (isset($this->request->post['payment_kapital_bank_taksit_check']) && !$taksit_months){
            $this->error['warning'] = $this->language->get('taksit_months_absent');
        }

        return !$this->error;
    }

    private function save_file($key) {
        $file = $this->request->files['payment_kapital_bank_' . $key];
        $current_file = $this->config->get('payment_kapital_bank_' . $key);
        if (is_uploaded_file($file['tmp_name'])) {
            if (!file_exists(DIR_CATALOG . 'model/extension/kapital_bank/certificates')) {
                mkdir(DIR_CATALOG . 'model/extension/kapital_bank/certificates', 755, true);
            }
            if ($current_file) {
                @unlink(DIR_CATALOG . 'model/extension/kapital_bank/certificates' . $current_file);
            }
            $name = $file['name'];
            if (move_uploaded_file($file['tmp_name'], DIR_CATALOG . 'model/extension/kapital_bank/certificates/' . $name)) {
                $this->request->post['payment_kapital_bank_' . $key] = $name;
            }
        } else {
            $this->request->post['payment_kapital_bank_' . $key] = $this->config->get('payment_kapital_bank_' . $key);
        }
    }

    public function install() {
        $this->load->model('extension/payment/kapital_bank');
        $this->model_extension_payment_kapital_bank->install();
    }

    public function uninstall() {
        $this->load->model('extension/payment/kapital_bank');
        $this->model_extension_payment_kapital_bank->uninstall();
    }

    public function order() {
        $this->load->model('extension/payment/kapital_bank');
        require_once DIR_APPLICATION . '../catalog/model/extension/kapital_bank/PaymentAdapter.php';
        require_once DIR_APPLICATION . '../catalog/model/extension/kapital_bank/KapitalBankPaymentAdapter.php';
        $order = $this->model_extension_payment_kapital_bank->getOrder($this->request->get['order_id']);
        $adapter = new KapitalBankPaymentAdapter('kapital_bank', $this->config->get('payment_kapital_bank_merchant_id'), $this->config->get('payment_kapital_bank_certificate'), $this->config->get('payment_kapital_bank_certificate_key'), $this->config->get('payment_kapital_bank_test'));
        $adapter->setKapitalModel($this->model_extension_payment_kapital_bank);
        $adapter->setOrder($this->request->get['order_id']);
        $data = [];
        $refunds = $this->model_extension_payment_kapital_bank->getRefunds($this->request->get['order_id']);
        try {
            $info = $adapter->getOrderInformation();
            $currency = $adapter->getCurrencyCode($info->Currency);
            $data = [
                'status'     => $info->Orderstatus,
                'taksit'     => $order['taksit'],
                'pay_date'   => $info->payDate,
                'pay_amount' => ($info->Amount / 100) . ' ' . $currency,
                'currency'   => $currency,
                'sessionid'  => $order['eccommerce_session_id'],
                'orderid'    => $order['eccommerce_order_id'],
                'user_token' => $this->session->data['user_token'],
                'order_id'   => $this->request->get['order_id'],
                'refunds'    => $refunds
            ];
            if ($info->RefundAmount != 0) {
                $data['refund_amount'] = ($info->RefundAmount / 100) . ' ' . $currency;
                $data['refund_date'] = $info->RefundDate;
            }
        } catch (\Exception $e) {

        }


        return $this->load->view('extension/payment/kapital_bank_transaction', $data);
    }

    public function refund() {
        $this->load->model('extension/payment/kapital_bank');
        $this->load->model('sale/order');
        require_once DIR_APPLICATION . '../catalog/model/extension/kapital_bank/PaymentAdapter.php';
        require_once DIR_APPLICATION . '../catalog/model/extension/kapital_bank/KapitalBankPaymentAdapter.php';
        $adapter = new KapitalBankPaymentAdapter('kapital_bank', $this->config->get('payment_kapital_bank_merchant_id'), $this->config->get('payment_kapital_bank_certificate'), $this->config->get('payment_kapital_bank_certificate_key'), $this->config->get('payment_kapital_bank_test'));
        $adapter->setKapitalModel($this->model_extension_payment_kapital_bank);
        $adapter->setOrder($this->request->get['order_id']);
        $adapter->setCurrency($this->request->post['currency']);
        $info = $adapter->getOrderInformation();
        $order_total = $info->Amount / 100;
        if ($info->RefundAmount != 0) {
            $order_total = $order_total - $info->RefundAmount / 100;
        }
        $this->response->addHeader('Content-Type: application/json');
        try {
            if ($this->request->post['refund_amount'] > 0 && ($order_total - $this->request->post['refund_amount']) >= 0) {
                if ($adapter->refundOrder(strip_tags($this->request->post['refund_desc']), $this->request->post['refund_amount'] * 100)) {
                    $this->model_extension_payment_kapital_bank->addRefund($this->request->get['order_id'], $this->request->post['refund_amount'], $this->request->post['currency'], strip_tags($this->request->post['refund_desc']));
                    $this->response->setOutput(json_encode(['error' => false]));

                    return;
                }
            }
        } catch (Exception $e){

        }

        $this->response->setOutput(json_encode(['error' => true]));
    }
}
