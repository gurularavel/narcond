<?php
class ControllerExtensionPaymentKapital extends Controller {
    public function index() {
        $this->load->language('extension/payment/kapital');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        // Create order request
        $order_data = array(
            'order' => array(
                'typeRid' => 'Order_SMS',
                'amount' => $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false),
                'currency' => $order_info['currency_code'],
                'language' => 'az',
                'title' => $this->config->get('config_name'),
                'description' => sprintf($this->language->get('text_order_description'), $order_info['order_id']),
                'hppRedirectUrl' => $this->url->link('extension/payment/kapital/callback', '', true),
                'hppCofCapturePurposes' => array(
                    'UnspecifiedMit',
                    'Cit',
                    'Recurring'
                )
            )
        );

        // Debug: Log request data
        $this->log->write('Kapital Bank Request Data: ' . json_encode($order_data));

        // Send create order request
        $response = $this->sendRequest('/order', $order_data);

        // Debug: Log response
        $this->log->write('Kapital Bank Response: ' . json_encode($response));

        if (isset($response['order']) && isset($response['order']['hppUrl'])) {
            // Get the base URL without any paths
            $baseUrl = parse_url($response['order']['hppUrl'], PHP_URL_SCHEME) . '://' . parse_url($response['order']['hppUrl'], PHP_URL_HOST);
            
            // Construct the payment URL with just one /flex
            $data['payment_url'] = $baseUrl . '/flex?id=' . $response['order']['id'] . '&password=' . $response['order']['password'];
            
            // Debug: Log the constructed URL
            $this->log->write('Kapital Bank Base URL: ' . $baseUrl);
            $this->log->write('Kapital Bank Payment URL: ' . $data['payment_url']);
        } else {
            $data['error'] = $this->language->get('error_create_order');
            // Debug: Log error
            $this->log->write('Kapital Bank Error: Invalid response format');
        }

        return $this->load->view('extension/payment/kapital', $data);
    }

    public function callback() {
        $this->load->language('extension/payment/kapital');
        $this->load->model('checkout/order');

        // Debug: Log callback parameters
        $this->log->write('Kapital Bank Callback Parameters: ' . json_encode($this->request->get));

        if (isset($this->request->get['ID'])) {
            $bank_order_id = $this->request->get['ID'];
            
            // Get order details with full transaction information
            $response = $this->sendRequest('/order/' . $bank_order_id . '?tranDetailLevel=2');
            
            // Debug: Log full response details
            $this->log->write('Kapital Bank Full Response: ' . json_encode($response, JSON_PRETTY_PRINT));
            
            if (isset($response['order']) && isset($response['order']['status'])) {
                $status = $response['order']['status'];
                
                // Log status and additional response details
                $this->log->write('Kapital Bank Order Status: ' . $status);
                if (isset($response['order']['errorCode'])) {
                    $this->log->write('Kapital Bank Error Code: ' . $response['order']['errorCode']);
                }
                if (isset($response['order']['errorMessage'])) {
                    $this->log->write('Kapital Bank Error Message: ' . $response['order']['errorMessage']);
                }
                if (isset($response['order']['responseCode'])) {
                    $this->log->write('Kapital Bank Response Code: ' . $response['order']['responseCode']);
                }
                if (isset($response['order']['responseMessage'])) {
                    $this->log->write('Kapital Bank Response Message: ' . $response['order']['responseMessage']);
                }
                
                // Get the OpenCart order ID from the bank order description
                $order_id = 0;
                
                // First try to get order ID from session
                if (isset($this->session->data['order_id'])) {
                    $order_id = (int)$this->session->data['order_id'];
                    $this->log->write('Kapital Bank: Found order ID in session: ' . $order_id);
                }
                
                // If not found in session, try to get from bank response
                if (!$order_id && isset($response['order']['description'])) {
                    preg_match('/Order #(\d+)/', $response['order']['description'], $matches);
                    if (isset($matches[1])) {
                        $order_id = (int)$matches[1];
                        $this->log->write('Kapital Bank: Found order ID in bank response: ' . $order_id);
                    }
                }
                
                // If still not found, try to get from transaction description
                if (!$order_id && isset($response['order']['trans'][0]['description'])) {
                    preg_match('/Order #(\d+)/', $response['order']['trans'][0]['description'], $matches);
                    if (isset($matches[1])) {
                        $order_id = (int)$matches[1];
                        $this->log->write('Kapital Bank: Found order ID in transaction description: ' . $order_id);
                    }
                }
                
                if ($order_id > 0) {
                    // Verify the order exists
                    $order_info = $this->model_checkout_order->getOrder($order_id);
                    
                    if ($order_info) {
                        if ($status == 'FullyPaid') {
                            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_kapital_order_status_id'), 'Payment successful via Kapital Bank. Bank Order ID: ' . $bank_order_id, true);
                            $this->log->write('Kapital Bank: Order #' . $order_id . ' marked as paid');
                            
                            // Clear the cart
                            $this->cart->clear();
                            
                            // Add success message to session
                            $this->session->data['success'] = $this->language->get('text_success');
                            
                            $this->response->redirect($this->url->link('checkout/success', '', true));
                        } else {
                            $error_message = 'Payment failed via Kapital Bank. Bank Order ID: ' . $bank_order_id;
                            if (isset($response['order']['errorMessage'])) {
                                $error_message .= '. Error: ' . $response['order']['errorMessage'];
                            }
                            if (isset($response['order']['responseMessage'])) {
                                $error_message .= '. Response: ' . $response['order']['responseMessage'];
                            }
                            
                            $this->model_checkout_order->addOrderHistory($order_id, 10, $error_message, true);
                            $this->log->write('Kapital Bank: Order #' . $order_id . ' payment failed - ' . $error_message);
                            
                            // Add error message to session
                            $this->session->data['error'] = $this->language->get('text_failed_message');
                            
                            $this->response->redirect($this->url->link('checkout/failure', '', true));
                        }
                    } else {
                        $error_message = 'Order #' . $order_id . ' not found in OpenCart';
                        $this->log->write('Kapital Bank Error: ' . $error_message);
                        
                        // Add error message to session
                        $this->session->data['error'] = $error_message;
                        
                        $this->response->redirect($this->url->link('checkout/failure', '', true));
                    }
                } else {
                    $error_message = 'Could not extract order ID from bank response or session';
                    $this->log->write('Kapital Bank Error: ' . $error_message);
                    
                    // Add error message to session
                    $this->session->data['error'] = $error_message;
                    
                    $this->response->redirect($this->url->link('checkout/failure', '', true));
                }
            } else {
                $error_message = 'Invalid order details response from bank';
                $this->log->write('Kapital Bank Error: ' . $error_message);
                
                // Add error message to session
                $this->session->data['error'] = $error_message;
                
                $this->response->redirect($this->url->link('checkout/failure', '', true));
            }
        } else {
            $error_message = 'Missing ID parameter in bank callback';
            $this->log->write('Kapital Bank Error: ' . $error_message);
            
            // Add error message to session
            $this->session->data['error'] = $error_message;
            
            $this->response->redirect($this->url->link('checkout/failure', '', true));
        }
    }

    private function sendRequest($endpoint, $data = array()) {
        $url = 'https://' . ($this->config->get('payment_kapital_test') ? 'txpgtst' : 'e-commerce') . '.kapitalbank.az/api' . $endpoint;
        
        // Debug: Log request URL
        $this->log->write('Kapital Bank Request URL: ' . $url);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->config->get('payment_kapital_merchant_id') . ':' . $this->config->get('payment_kapital_merchant_key'))
        ));
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        
        // Debug: Log curl info and errors
        if (curl_errno($ch)) {
            $this->log->write('Kapital Bank Curl Error: ' . curl_error($ch));
        }
        $this->log->write('Kapital Bank Curl Info: ' . json_encode(curl_getinfo($ch)));
        
        curl_close($ch);
        
        return json_decode($response, true);
    }
} 