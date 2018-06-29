<?php

class ControllerExtensionPaymentVindi extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/vindi');
        $data['text_credit_card'] = $this->language->get('text_credit_card');
        $data['text_start_date'] = $this->language->get('text_start_date');
        $data['text_wait'] = $this->language->get('text_wait');
        $data['text_loading'] = $this->language->get('text_loading');

        $data['entry_cc_type'] = $this->language->get('entry_cc_type');
        $data['entry_cc_number'] = $this->language->get('entry_cc_number');
        $data['entry_cc_start_date'] = $this->language->get('entry_cc_start_date');
        $data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
        $data['entry_cc_cvv2'] = $this->language->get('entry_cc_cvv2');
        $data['entry_cc_issue'] = $this->language->get('entry_cc_issue');

        $data['help_start_date'] = $this->language->get('help_start_date');
        $data['help_issue'] = $this->language->get('help_issue');

        $data['button_confirm'] = $this->language->get('button_confirm');

        if (empty($this->session->data['payment_address'])
            && !$this->customer->isLogged()) {
            $data['error_session'] = $this->language->get('error_session');
        }
        $this->load->model('extension/payment/vindi');
        $data['payment_companies'] = $this->model_extension_payment_vindi->getPaymentCompanies('credit_card');

        return $this->load->view('extension/payment/vindi', $data);
    }

    public function js()
    {
        $this->load->language('extension/payment/vindi');

        $mydata = [];

        $data['complete'] = $this->url->link('extension/payment/vindi/success', '', true);
        $data['cancel'] = $this->url->link('checkout/checkout', '', true);

        $this->load->model('extension/payment/vindi');

        $data['checkout_script'] = $this->model_extension_payment_vindi->getGateway().'/checkout/version/'.$this->model_extension_payment_vindi->getApiVersion().'/checkout.js';

        $this->response->addHeader('Content-Type:application/javascript');
        $this->response->setOutput($this->load->view('extension/payment/vindi_js', $data));
    }

    public function checkout()
    {
        $this->load->language('extension/payment/vindi');

        $this->load->model('extension/payment/vindi');

        $json = [
            'redirect' => $this->url->link('extension/payment/vindi/success', '', true),
        ];

        if ($this->request->server['REQUEST_METHOD'] != 'POST' || empty($this->request->post['token']) || @base64_decode($this->request->post['token']) === false) {
            $json['error'] = $this->language->get('error_invalid_request');
        } else {
            if (
                !empty($this->session->data['payment_address']) &&
                !empty($this->session->data['currency']) &&
                !empty($this->session->data['order_id']) &&
                $this->model_extension_payment_vindi->initCheckoutSession($this->session->data['order_id'],
                    $this->session->data['currency'])
            ) {
                $configuration['sourceOfFunds']['token'] = base64_decode($this->request->post['token']);

                if ($this->config->get('payment_vindi_checkout') == 'pay') {
                    $configuration['apiOperation'] = 'PAY';
                } else {
                    $configuration['apiOperation'] = 'AUTHORIZE';
                }

                $response = $this->model_extension_payment_vindi->api('order/'.$this->session->data['order_id'].'/transaction/1',
                    $configuration, 'PUT');

                if (!empty($response['result']) && $response['result'] == 'ERROR' && $response['error']['cause'] == 'INVALID_REQUEST') {
                    $json['error'] = sprintf($this->language->get('error_api'), $response['error']['explanation']);
                } else {
                    if (empty($response['result']) || $response['result'] != 'SUCCESS') {
                        $json['error'] = $this->language->get('error_unknown');
                    }
                }
            } else {
                $json['error'] = $this->language->get('error_session');
            }
        }

        $this->response->addHeader('Content-Type:application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function success()
    {
        $this->load->model('extension/payment/vindi');

        if (
            !empty($this->session->data['order_id']) &&
            !empty($this->session->data['vindi']['session']['id']) &&
            !empty($this->session->data['vindi']['successIndicator']) &&
            !empty($this->request->get['resultIndicator']) &&
            $this->session->data['vindi']['successIndicator'] === $this->request->get['resultIndicator']
        ) {
            $session_data = $this->model_extension_payment_vindi->api('session/'.$this->session->data['vindi']['session']['id']);

            if (!empty($session_data['billing']['address']) && !empty($session_data['interaction']['displayControl']['billingAddress']) && in_array($session_data['interaction']['displayControl']['billingAddress'],
                    ['MANDATORY', 'OPTIONAL'])) {
                $this->model_extension_payment_vindi->editOrderPaymentDetails($this->session->data['order_id'],
                    $session_data['billing']['address']);
            }

            if ($this->config->get('payment_vindi_tokenize') && empty($session_data['sourceOfFunds']['token'])) {
                $this->model_extension_payment_vindi->saveToken($this->session->data['order_id'], token(40),
                    $this->session->data['vindi']['session']['id'], $session_data['sourceOfFunds']);
            }
        }

        $this->model_extension_payment_vindi->clearCheckoutSession();

        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function callback()
    {
        $this->load->language('extension/payment/vindi');

        $this->load->model('extension/payment/vindi');

        if ($this->config->get('payment_vindi_debug_log')) {
            $log_data = $this->model_extension_payment_vindi->getNotificationLogData();
            $this->log->write($log_data);
        }

        if (!$this->validate_callback()) {
            http_response_code(400);

            return;
        }

        // log the notification and add an order transaction entry
        $transaction_info = $this->model_extension_payment_vindi->parseWebhookNotification();

        if ($transaction_info) {
            $transaction_info['partnerSolutionId'] = $this->model_extension_payment_vindi->getPartnerSolutionId();

            if (empty($transaction_info['risk']['response']['totalScore'])) {
                $transaction_info['risk']['response']['totalScore'] = 0;
            }

            if (empty($transaction_info['billing']['address']['company'])) {
                $transaction_info['billing']['address']['company'] = '';
            }

            if (empty($transaction_info['version'])) {
                $transaction_info['version'] = '';
            }

            $is_transaction_logged = $this->model_extension_payment_vindi->isTransactionLogged($transaction_info['transaction']['id'],
                $transaction_info['order']['reference']);

            if (!$is_transaction_logged) {
                $this->model_extension_payment_vindi->saveTransaction($transaction_info);

                // last check if everything went okay
                $is_transaction_logged = $this->model_extension_payment_vindi->isTransactionLogged($transaction_info['transaction']['id'],
                    $transaction_info['order']['reference']);
            } else {
                $this->model_extension_payment_vindi->updateTransaction($transaction_info);

                //$is_transaction_logged is obviously true
            }
        } else {
            // we are sure input cannot be parsed into a json
            $is_transaction_logged = false;
        }

        if (!$is_transaction_logged) {
            http_response_code(400);

            return;
        } else {
            $this->load->model('checkout/order');

            $risk_gateway_code = !empty($transaction_info['risk']['response']['gatewayCode']) ? $transaction_info['risk']['response']['gatewayCode'] : null;

            switch ($risk_gateway_code) {
                case 'REJECTED':
                    {
                        $this->model_extension_payment_vindi->addOrderHistory([
                            'order_id'        => (int) $transaction_info['order']['reference'],
                            'order_status_id' => (int) $this->config->get('payment_vindi_risk_rejected_order_status_id'),
                            'message'         => sprintf(
                                $this->language->get('text_callback'),
                                $transaction_info['transaction']['type'],
                                'RISK (REJECTED)',
                                $transaction_info['transaction']['id']
                            ),
                        ]);
                    }
                    break;
                case 'REVIEW_REQUIRED':
                    {
                        $decision_exists = !empty($transaction_info['risk']['response']['review']['decision']);
                        $decision_skipped = $decision_exists && in_array($transaction_info['risk']['response']['review']['decision'],
                                ['NOT_REQUIRED', 'ACCEPTED']);

                        if (!$decision_exists || $decision_skipped) {
                            $this->add_default_order_history($transaction_info);
                        } else {
                            $this->model_extension_payment_vindi->addOrderHistory([
                                'order_id'        => (int) $transaction_info['order']['reference'],
                                'order_status_id' => (int) $this->config->get('payment_vindi_risk_review_'.strtolower($transaction_info['risk']['response']['review']['decision']).'_order_status_id'),
                                'message'         => sprintf(
                                    $this->language->get('text_callback'),
                                    $transaction_info['transaction']['type'],
                                    'RISK_REVIEW ('.$transaction_info['risk']['response']['review']['decision'].')',
                                    $transaction_info['transaction']['id']
                                ),
                            ]);
                        }
                    }
                    break;
                default:
                    {
                        $this->add_default_order_history($transaction_info);
                    }
                    break;
            }
        }

        http_response_code(200);
    }

    protected function validate_callback()
    {
        $this->load->language('extension/payment/vindi');

        $this->load->model('extension/payment/vindi');

        // 1. Verify that the notification comes from an HTTPS connection.

        if (!isset($this->request->server['HTTPS']) || ($this->request->server['HTTPS'] != 'on' && $this->request->server['HTTPS'] != '1') || $this->request->server['SERVER_PORT'] != 443) {
            if ($this->config->get('payment_vindi_debug_log')) {
                $this->log->write($this->language->get('text_log_validate_callback_intro').$this->language->get('error_validate_protocol'));
            }

            return false;
        }

        // 2. Verify that the Webhook secret matches the Webhook secret that we have stored.

        if (!$this->config->get('payment_vindi_notification_secret') || $this->config->get('payment_vindi_notification_secret') != $this->model_extension_payment_vindi->getHeaderVar('HTTP_X_NOTIFICATION_SECRET')) {
            if ($this->config->get('payment_vindi_debug_log')) {
                $this->log->write($this->language->get('text_log_validate_callback_intro').$this->language->get('error_secret_mismatch'));
            }

            return false;
        }

        // 3. Parse the notification to get the Transaction info.

        $transaction_info = $this->model_extension_payment_vindi->parseWebhookNotification();

        if (!$transaction_info) {
            if ($this->config->get('payment_vindi_debug_log')) {
                $this->log->write($this->language->get('text_log_validate_callback_intro').$this->language->get('error_notification_parse'));
            }

            return false;
        }

        // 4. Verify that the Transaction ID is not already present on the order. This could happen because MPGS will retry several times to send the notification until it gets a 200 http response.

        $is_transaction_logged = $this->model_extension_payment_vindi->isTransactionLogged($transaction_info['transaction']['id'],
            $transaction_info['order']['reference']);

        $is_risk_review =
            !empty($transaction_info['risk']['response']['gatewayCode']) &&
            !empty($transaction_info['risk']['response']['review']['decision']) &&
            $transaction_info['risk']['response']['gatewayCode'] == 'REVIEW_REQUIRED' &&
            !in_array($transaction_info['risk']['response']['review']['decision'], ['NOT_REQUIRED', 'ACCEPTED']);

        if ($is_transaction_logged && !$is_risk_review) {
            if ($this->config->get('payment_vindi_debug_log')) {
                $this->log->write($this->language->get('text_log_validate_callback_intro').$this->language->get('error_transaction_logged'));
            }

            return false;
        }

        return true;
    }

    protected function add_default_order_history($transaction_info)
    {
        $this->load->language('extension/payment/vindi');

        $this->load->model('extension/payment/vindi');

        if ($transaction_info['response']['gatewayCode'] == 'APPROVED') {
            switch ($transaction_info['transaction']['type']) {
                case 'AUTHORIZATION':
                case 'AUTHORIZATION_UPDATE':
                    {
                        $order_status_id = $this->config->get('payment_vindi_approved_authorization_order_status_id');
                    }
                    break;
                case 'CAPTURE':
                    {
                        $order_status_id = $this->config->get('payment_vindi_approved_capture_order_status_id');
                    }
                    break;
                case 'PAYMENT':
                    {
                        $order_status_id = $this->config->get('payment_vindi_approved_payment_order_status_id');
                    }
                    break;
                case 'REFUND_REQUEST':
                case 'REFUND':
                    {
                        $order_status_id = $this->config->get('payment_vindi_approved_refund_order_status_id');
                    }
                    break;
                case 'VOID_AUTHORIZATION':
                case 'VOID_CAPTURE':
                case 'VOID_PAYMENT':
                case 'VOID_REFUND':
                    {
                        $order_status_id = $this->config->get('payment_vindi_approved_void_order_status_id');
                    }
                    break;
                case 'VERIFICATION':
                    {
                        $order_status_id = $this->config->get('payment_vindi_approved_verification_order_status_id');
                    }
                    break;
            }
        } else {
            $order_status_id = $this->config->get('payment_vindi_'.strtolower($transaction_info['response']['gatewayCode']).'_order_status_id');
        }

        $this->model_extension_payment_vindi->addOrderHistory([
            'order_id'        => (int) $transaction_info['order']['reference'],
            'order_status_id' => (int) $order_status_id,
            'message'         => sprintf(
                $this->language->get('text_callback'),
                $transaction_info['transaction']['type'],
                $transaction_info['result'],
                $transaction_info['transaction']['id']
            ),
        ]);
    }

    public function send()
    {
        $this->load->model('checkout/order');
        $this->load->model('account/customer');
        $this->load->model('extension/payment/vindi');
        $this->load->language('extension/payment/vindi');

        $orderStatusId = 7;
        $http = 'HTTP/1.1 402 Fail';
        $header = 'Content-Type: application/json';
        $message = ['error' => 'Houve um erro ao transacionar'];

        $bill = $this->model_extension_payment_vindi->createBill($this->model_extension_payment_vindi->addCustomer(),
            (float) $this->cart->session->data['shipping_method']['cost']);
        if (!array_key_exists('errors', $bill)) {
            $http = 'HTTP/1.1 200 Success';
            $header = 'Content-Type: application/json';
            $message = ['status' => 'ok'];
            $orderStatusId = 15;
        }
        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($this->cart->session->data['order_id'], $orderStatusId,
            $this->language->get('text_callback'));
        $this->response->addHeader($http);
        $this->response->addHeader($header);
        $this->response->setOutput(json_encode($message));
    }

    protected function configuration($hosted = true)
    {
        $c = [];

        $this->load->helper('utf8');

        $this->load->language('extension/payment/vindi');

        // Limitations taken from here: https://eu-gateway.mastercard.com/api/documentation/apiDocumentation/checkout/version/latest/function/configure.html?locale=en_US

        if (!empty($this->session->data['payment_address']['city'])) {
            $c['billing']['address']['city'] = utf8_substr($this->session->data['payment_address']['city'], 0, 100);
        }

        if (!empty($this->session->data['payment_address']['company'])) {
            $c['billing']['address']['company'] = utf8_substr($this->session->data['payment_address']['company'], 0,
                100);
        }

        if (!empty($this->session->data['payment_address']['iso_code_3'])) {
            $c['billing']['address']['country'] = $this->session->data['payment_address']['iso_code_3'];
        }

        if (!empty($this->session->data['payment_address']['postcode'])) {
            $c['billing']['address']['postcodeZip'] = utf8_substr($this->session->data['payment_address']['postcode'],
                0, 10);
        }

        if (!empty($this->session->data['payment_address']['zone'])) {
            $c['billing']['address']['stateProvince'] = utf8_substr($this->session->data['payment_address']['zone'], 0,
                20);
        }

        if (!empty($this->session->data['payment_address']['address_1'])) {
            $c['billing']['address']['street'] = utf8_substr($this->session->data['payment_address']['address_1'], 0,
                100);
        }

        if (!empty($this->session->data['payment_address']['address_2'])) {
            $c['billing']['address']['street2'] = utf8_substr($this->session->data['payment_address']['address_2'], 0,
                100);
        }

        if ($hosted) {
            $c['interaction']['displayControl']['billingAddress'] = 'OPTIONAL';
        }

        if (!empty($this->session->data['shipping_address'])) {
            $shipping_address = $this->session->data['shipping_address'];
        } else {
            $shipping_address = [];

            if ($hosted) {
                $c['interaction']['displayControl']['shipping'] = 'HIDE';
            }
        }

        if (!empty($shipping_address['city'])) {
            $c['shipping']['address']['city'] = utf8_substr($shipping_address['city'], 0, 100);
        }

        if (!empty($shipping_address['company'])) {
            $c['shipping']['address']['company'] = utf8_substr($shipping_address['company'], 0, 100);
        }

        if (!empty($shipping_address['iso_code_3'])) {
            $c['shipping']['address']['country'] = $shipping_address['iso_code_3'];
        }

        if (!empty($shipping_address['postcode'])) {
            $c['shipping']['address']['postcodeZip'] = utf8_substr($shipping_address['postcode'], 0, 10);
        }

        if (!empty($shipping_address['zone'])) {
            $c['shipping']['address']['stateProvince'] = utf8_substr($shipping_address['zone'], 0, 20);
        }

        if (!empty($shipping_address['address_1'])) {
            $c['shipping']['address']['street'] = utf8_substr($shipping_address['address_1'], 0, 100);
        }

        if (!empty($shipping_address['address_2'])) {
            $c['shipping']['address']['street2'] = utf8_substr($shipping_address['address_2'], 0, 100);
        }

        if (!empty($shipping_address['firstname'])) {
            $c['shipping']['contact']['firstName'] = utf8_substr($shipping_address['firstname'], 0, 50);
        }

        if (!empty($shipping_address['lastname'])) {
            $c['shipping']['contact']['lastName'] = utf8_substr($shipping_address['lastname'], 0, 50);
        }

        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');

            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

            if (!empty($customer_info['firstname'])) {
                $c['customer']['firstName'] = utf8_substr($customer_info['firstname'], 0, 50);
            }

            if (!empty($customer_info['lastname'])) {
                $c['customer']['lastName'] = utf8_substr($customer_info['lastname'], 0, 50);
            }

            if (!empty($customer_info['email']) && utf8_strlen($customer_info['email']) >= 3 && filter_var($customer_info['email'],
                    FILTER_VALIDATE_EMAIL)) {
                $c['customer']['email'] = $customer_info['email'];
                $c['shipping']['contact']['email'] = $customer_info['email'];
            }

            if (!empty($customer_info['telephone'])) {
                $c['customer']['phone'] = utf8_substr($customer_info['telephone'], 0, 20);
                $c['shipping']['contact']['phone'] = utf8_substr($customer_info['telephone'], 0, 20);
            }

            $c['order']['custom']['customerId'] = (int) $this->customer->getId();
        } elseif (isset($this->session->data['guest'])) {
            if (!empty($this->session->data['guest']['firstname'])) {
                $c['customer']['firstName'] = utf8_substr($this->session->data['guest']['firstname'], 0, 50);
            }

            if (!empty($this->session->data['guest']['lastname'])) {
                $c['customer']['lastName'] = utf8_substr($this->session->data['guest']['lastname'], 0, 50);
            }

            if (!empty($this->session->data['guest']['email']) && utf8_strlen($this->session->data['guest']['email']) >= 3 && filter_var($this->session->data['guest']['email'],
                    FILTER_VALIDATE_EMAIL)) {
                $c['customer']['email'] = $this->session->data['guest']['email'];
                $c['shipping']['contact']['email'] = $this->session->data['guest']['email'];
            }

            if (!empty($this->session->data['guest']['telephone'])) {
                $c['customer']['phone'] = utf8_substr($this->session->data['guest']['telephone'], 0, 20);
                $c['shipping']['contact']['phone'] = utf8_substr($this->session->data['guest']['telephone'], 0, 20);
            }
        }

        if ($hosted && $this->config->get('payment_vindi_google_analytics_property_id')) {
            $c['interaction']['googleAnalytics']['propertyId'] = $this->config->get('payment_vindi_google_analytics_property_id');
        }

        if ($hosted && utf8_strlen($this->language->get('code')) >= 2 && utf8_strlen($this->language->get('code')) <= 5) {
            $c['interaction']['locale'] = $this->language->get('code');
        }

        $store_address_raw = $this->config->get('config_address');
        $store_address = array_values(array_filter(explode(PHP_EOL, $this->config->get('config_address'))));

        if ($hosted && !empty($store_address[0])) {
            $c['interaction']['merchant']['address']['line1'] = utf8_substr($store_address[0], 0, 100);
        }

        if ($hosted && !empty($store_address[1])) {
            $c['interaction']['merchant']['address']['line2'] = utf8_substr($store_address[1], 0, 100);
        }

        if ($hosted && !empty($store_address[2])) {
            $c['interaction']['merchant']['address']['line3'] = utf8_substr($store_address[2], 0, 100);
        }

        if ($hosted && !empty($store_address[3])) {
            $c['interaction']['merchant']['address']['line4'] = utf8_substr($store_address[3], 0, 100);
        }

        if ($hosted && $this->config->get('config_email') && utf8_strlen($this->config->get('config_email')) >= 3 && filter_var($this->config->get('config_email'),
                FILTER_VALIDATE_EMAIL)) {
            $c['interaction']['merchant']['email'] = $this->config->get('config_email');
        }

        if ($hosted && $this->config->get('config_image')) {
            $this->load->model('tool/image');

            $c['interaction']['merchant']['logo'] = $this->model_tool_image->resize($this->config->get('config_image'),
                140, 140);
        }

        if ($hosted) {
            $c['interaction']['merchant']['name'] = utf8_substr($this->config->get('config_name'), 0, 40);
            $c['interaction']['merchant']['phone'] = utf8_substr($this->config->get('config_telephone'), 0, 20);

            $c['merchant'] = $this->config->get('payment_vindi_merchant');

            $c['order']['id'] = $this->session->data['order_id'];
        }

        if (!$hosted) {
            if (isset($this->request->server['HTTP_USER_AGENT'])) {
                $c['device']['browser'] = $this->request->server['HTTP_USER_AGENT'];
            } else {
                $c['device']['browser'] = 'Unknown';
            }

            $c['device']['ipAddress'] = $this->request->server['REMOTE_ADDR'];
        }

        $c['order']['reference'] = $this->session->data['order_id'];

        // Totals
        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        $total_data = [
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total,
        ];

        $this->load->model('setting/extension');

        $sort_order = [];

        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get($value['code'].'_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_'.$result['code'].'_status')) {
                $this->load->model('extension/total/'.$result['code']);

                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_'.$result['code']}->getTotal($total_data);
            }
        }

        $sort_order = [];

        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $totals);

        $skip_totals = [
            'sub_total',
            'total',
            'tax',
        ];

        $result_sub_total = 0;
        $result_tax = 0;
        $result_tax_data = [];
        $result_shipping_handling = 0;
        $result_total = round($total, 2);
        $result_actual_shipping = 0;
        $has_negative_value = false;

        foreach ($totals as $key => $value) {
            $rounded_val = round($value['value'], 2);

            if ($rounded_val < 0) {
                // The API does not support negative values. We must hide the products and provide only summarized info. This is done later.
                $has_negative_value = true;
            }

            if ($value['code'] == 'sub_total') {
                $result_sub_total += $rounded_val;
            }

            if ($value['code'] == 'tax') {
                $result_tax += $rounded_val;
                $result_tax_data[] = [
                    'amount' => $rounded_val,
                    'type'   => $value['title'],
                ];
            }

            if (!in_array($value['code'], $skip_totals)) {
                $result_shipping_handling += $rounded_val;
            }

            if ($value['code'] == 'shipping') {
                $result_actual_shipping = $rounded_val;
            }
        }

        if ($result_sub_total + $result_tax + $result_shipping_handling == $result_total && !$has_negative_value) {
            // All total values sum up to the order total, and there are no negative values.
            $c['order']['amount'] = $result_total;
            $c['order']['itemAmount'] = $result_sub_total;
            $c['order']['shippingAndHandlingAmount'] = $result_shipping_handling;
            $c['order']['taxAmount'] = $result_tax;

            $display_products = true;
        } else {
            // The totals do not sum up to the order total, or there is a negaitve value. We must hide the products.
            $sub_total = $result_total - $result_tax - $result_actual_shipping;

            $c['order']['amount'] = $result_total;
            $c['order']['itemAmount'] = $sub_total;
            $c['order']['shippingAndHandlingAmount'] = $result_actual_shipping;
            $c['order']['taxAmount'] = $result_tax;

            $display_products = false;
        }

        // End of taxes

        $c['order']['currency'] = $this->session->data['currency'];

        if (!empty($this->session->data['comment'])) {
            $c['order']['customerNote'] = utf8_substr($this->session->data['comment'], 0, 250);
        }

        $c['order']['customerOrderDate'] = date('Y-m-d');

        if ($display_products) {
            $most_expensive_sku = '';
            $most_expensive_max = 0;

            $this->load->model('catalog/product');

            foreach ($this->cart->getProducts() as $product) {
                $product['price'] = round($product['price'], 2);

                $option_data = [];
                $product_info = $this->model_catalog_product->getProduct($product['product_id']);

                foreach ($product['option'] as $option) {
                    if ($option['type'] != 'file') {
                        $value = isset($option['value']) ? $option['value'] : '';
                    } else {
                        $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

                        if ($upload_info) {
                            $value = $upload_info['name'];
                        } else {
                            $value = '';
                        }
                    }

                    $option_data[] = $option['name'].':'.(utf8_strlen($value) > 20 ? utf8_substr($value, 0,
                                20).'..' : $value);
                }

                $entry = [];

                $entry['name'] = utf8_substr($product['name'], 0, 127);
                $entry['quantity'] = $product['quantity'];
                $entry['unitPrice'] = $product['price'];

                if ($option_data) {
                    $entry['description'] = utf8_substr(implode(', ', $option_data), 0, 127);
                } else {
                    if ($product['model']) {
                        $entry['description'] = utf8_substr($product['model'], 0, 127);
                    }
                }

                if ($product['model']) {
                    $entry['sku'] = utf8_substr($product['model'], 0, 127);
                }

                if ($product_info['manufacturer']) {
                    $entry['brand'] = utf8_substr($product_info['manufacturer'], 0, 127);
                }

                $c['order']['item'][] = $entry;

                if ($most_expensive_max < $product['price']) {
                    $most_expensive_max = $product['price'];
                    $most_expensive_sku = utf8_substr($product['model'], 0, 15);
                }
            }

            $c['order']['productSKU'] = $most_expensive_sku;
        } else {
            $c['order']['item'][] = [
                'name'        => 'Order #'.$c['order']['reference'],
                'quantity'    => 1,
                'unitPrice'   => $sub_total,
                'description' => 'Order #'.$c['order']['reference'],
            ];
        }

        $c['order']['description'] = $this->language->get('text_items');

        if (!empty($result_tax_data)) {
            $c['order']['tax'] = $result_tax_data;
        }

        $c['partnerSolutionId'] = $this->model_extension_payment_vindi->getPartnerSolutionId();

        $c['session']['id'] = $this->session->data['vindi']['session']['id'];
        $c['session']['version'] = $this->session->data['vindi']['session']['version'];

        return $c;
    }
}
