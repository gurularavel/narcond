<?php

/**
 * KapitalPaymentAdapter.php
 *
 * Author: Kapital Bank
 * Copyright: (c) 2021 Kapital Bank
 * Date: 4/3/2021 10:23
 */


class KapitalBankPaymentAdapter extends PaymentAdapter {
    private $pay_details, $mode, $locale = 'EN', $model_checkout_order, $order_info = [], $kapital_bank_model;

    public function __construct($plugin_id, $merchant, $certificate, $key, $test_mode = true) {
        $this->plugin_id = $plugin_id;
        $dir = defined('DIR_CATALOG') ? DIR_CATALOG : DIR_APPLICATION;
        $this->cert = $test_mode ? $dir . 'model/extension/kapital_bank/certificates/test/test.crt' : $dir . 'model/extension/kapital_bank/certificates/' . $certificate;
        $this->key = $test_mode ? $dir . 'model/extension/kapital_bank/certificates/test/test.key' : $dir . 'model/extension/kapital_bank/certificates/' . $key;
        $this->merchant = $test_mode ? 'E1000010' : $merchant;
        $this->mode = $test_mode ? 'test' : 'prod';
        $this->merchant_handler = "https://3dsrv.kapitalbank.az:5443/Exec";
        $this->client_handler = "https://3dsrv.kapitalbank.az/index.jsp";
    }

    /**
     * @param mixed $kapital_bank_model
     */
    public function setKapitalModel($kapital_bank_model)
    : void {
        $this->kapital_bank_model = $kapital_bank_model;
    }

    /**
     * @param mixed $model_checkout_order
     */
    public function setModelCheckoutOrder($model_checkout_order)
    : void {
        $this->model_checkout_order = $model_checkout_order;
    }

    /**
     * @param string $locale
     */
    public function setLocale(string $locale)
    : void {
        $this->locale = $locale;
    }

    public function checkExecuteReady() {
        if (isset($_POST['xmlmsg']) && $_POST['xmlmsg']) {

            return true;
        }

        return false;
    }

    public function execute() {
        $this->state = $this->getECommerceOrderStatus();

        if ($this->state === false) {
            return false;
        }
        $this->state = strtolower($this->state);
        switch ($this->state) {
            case 'canceled':
            case 'declined':
            case 'error':
                $this->state = 'failed';
                break;
            case 'on-lock':
            case 'on-payment':
            case 'created':
                $this->state = 'created';
                break;
            case 'approved':
                $this->state = 'approved';
                break;
        }

    }

    private function getECommerceOrderStatus() {
        $language = strtoupper($this->locale);
        $xaml = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>
<TKKPG>
  <Request>
   <Operation>GetOrderStatus</Operation>
   <Language>' . $language . '</Language>
   <Order>
     <Merchant>' . $this->merchant . '</Merchant>
     <OrderID>' . $this->pay_details['ecommerce_orderid'] . '</OrderID>
   </Order>
   <SessionID>' . $this->pay_details['ecommerce_sessionid'] . '</SessionID>
  </Request>
</TKKPG>';

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_URL            => $this->merchant_handler,
            CURLOPT_SSLCERT        => $this->cert,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/xml'
            ],
        ];
        if ($this->key) {
            $options[CURLOPT_SSLKEY] = $this->key;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xaml);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        if (curl_error($ch)) {
            $this->model_checkout_order->addOrderHistory($this->order, 10, 'Error: ' . curl_error($ch));
            throw new \Exception('Curl Error : ' . curl_error($ch));
        }

        if ($result !== false) {
            $result = new \SimpleXMLElement($result);

            return $result->Response->Order->OrderStatus;
        }

        return false;
    }

    public function setCurrency($currency) {
        parent::setCurrency($currency);
        $this->currency_number = $this->currency_codes[$currency];
    }

    public function getDetails() {
        return $this->pay_details;
    }

    public function toPayment() {
        return $this->client_handler . '?SessionID=' . $this->pay_details['ecommerce_sessionid'] . '&OrderID=' . $this->pay_details['ecommerce_orderid'];
    }

    public function setOrder($order_id) {
        parent::setOrder($order_id);
        if ($this->model_checkout_order) {
            $this->order_info = $this->model_checkout_order->getOrder($order_id);
        }
        $order = $this->kapital_bank_model->getOrder($order_id);
        if ($order) {
            $this->pay_details = [
                'ecommerce_sessionid' => $order['eccommerce_session_id'],
                'ecommerce_orderid'   => $order['eccommerce_order_id'],
                'taksit'              => $order['taksit'],
            ];
        }
    }

    public function create() {
        $xml_post_string = $this->createOrderXML();
        $xmlObj = $this->curl($xml_post_string);

        if ($xmlObj->Response->Order->SessionID) {
            $this->pay_details['ecommerce_sessionid'] = (string)$xmlObj->Response->Order->SessionID;
            $this->pay_details['ecommerce_orderid'] = (string)$xmlObj->Response->Order->OrderID;
            $this->kapital_bank_model->updateOrder($this->order, $this->pay_details['ecommerce_sessionid'], $this->pay_details['ecommerce_orderid'], $this->pay_details['taksit']);
            $this->model_checkout_order->addOrderHistory($this->order, 2, 'Processing order');
        } else {
            $this->model_checkout_order->addOrderHistory($this->order, 10, 'Error processing: ' . htmlspecialchars($xmlObj));
            throw new \Exception(htmlspecialchars($xmlObj));
        }
    }

    public function createOrderXML() {
        $language = $this->locale;

        $cost = $this->order_info['total'] * 100;
        $taksit = $this->pay_details['taksit'];
        $taksit_desc = $taksit != 0 ? ';TAKSIT=' . $taksit : '';

        return '<?xml version="1.0" encoding="UTF-8"?>
                <TKKPG>
                    <Request>
                        <Operation>CreateOrder</Operation>
                        <Language>' . $language . '</Language>
                        <Order>
                            <OrderType>Purchase</OrderType>
                            <Merchant>' . $this->merchant . '</Merchant>
                            <Amount>' . $cost . '</Amount>
                            <Currency>' . $this->currency_number . '</Currency>
                            <Description>' . $this->order . $taksit_desc . '</Description>
                            <ApproveURL><![CDATA[' . HTTPS_SERVER . 'index.php?route=extension/payment/kapital_bank/callback&action=approve&id=' . $this->order . ']]></ApproveURL>
                            <CancelURL><![CDATA[' . HTTPS_SERVER . 'index.php?route=extension/payment/kapital_bank/callback&action=cancel&id=' . $this->order . ']]></CancelURL>
                            <DeclineURL><![CDATA[' . HTTPS_SERVER . 'index.php?route=extension/payment/kapital_bank/callback&action=decline&id=' . $this->order . ']]></DeclineURL>
                        </Order>
                    </Request>
                </TKKPG>';

    }

    private function curl($xml_string) {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_URL            => $this->merchant_handler,
            CURLOPT_SSLCERT        => $this->cert,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml'
            ],
        ];
        if ($this->key) {
            $options[CURLOPT_SSLKEY] = $this->key;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_string);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        if (curl_error($ch)) {
            if ($this->model_checkout_order) {
                $this->model_checkout_order->addOrderHistory($this->order . 10, 'Error: ' . curl_error($ch));
            }
            throw new \Exception('Curl Error : ' . curl_error($ch));
        }

        return simplexml_load_string($result);
    }

    public function refundOrder($description, $amount) {
        $language = $this->locale;
        $xml_post_string = '<?xml version="1.0" encoding="UTF-8"?>
                                <TKKPG>
                                    <Request>
                                      <Operation>Refund</Operation>
                                      <Language>' . $language . '</Language>
                                      <Order>
                                        <Merchant>' . $this->merchant . '</Merchant>
                                        <OrderID>' . $this->pay_details['ecommerce_orderid'] . '</OrderID>
                                          <Positions>
                                            <Position>
                                              <PaymentSubjectType>1</PaymentSubjectType>
                                              <Quantity>1</Quantity>
                                              <Price>13.50</Price>
                                              <Tax>1</Tax>
                                              <Text>name position</Text>
                                              <PaymentType>2</PaymentType>
                                              <PaymentMethodType>1</PaymentMethodType>
                                            </Position>
                                          </Positions>
                                      </Order>
                                      <Description>' . $description . '</Description>
                                      <SessionID>' . $this->pay_details['ecommerce_sessionid'] . '</SessionID>
                                      <Refund>
                                        <Amount>' . $amount . '</Amount>
                                        <Currency>' . $this->currency_number . '</Currency>
                                        <WithFee>false</WithFee>
                                      </Refund>
                                      <Source>1</Source>
                                    </Request>
                                </TKKPG>';

        $refund = $this->curl($xml_post_string);

        return $refund->Response->Status == '00';
    }

    public function getOrderInformation() {
        $language = $this->locale;
        $xml_post_string = '<?xml version="1.0" encoding="UTF-8"?>
            <TKKPG>
                <Request>
                    <Operation>GetOrderInformation</Operation>
                    <Language>' . $language . '</Language>
                    <Order>
                        <Merchant>' . $this->merchant . '</Merchant>
                        <OrderID>' . $this->pay_details['ecommerce_orderid'] . '</OrderID>
                    </Order>
                    <SessionID>' . $this->pay_details['ecommerce_sessionid'] . '</SessionID>
                </Request>
            </TKKPG>';

        return $this->curl($xml_post_string)->row;

    }
}
