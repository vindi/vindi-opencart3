<?php

class ModelExtensionPaymentVindi extends Model
{
    private $api_version = 'v1';
    private $extension_version = '1.0.0';
    private $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    public function getMethod()
    {
        $method_data = [];

        if ($this->config->get('payment_vindi_status')) {
            $title_config = $this->config->get('payment_vindi_display_name');

            $method_data = [
                'code'       => 'vindi',
                'title'      => ! empty(
                    $title_config[$this->config->get('config_language_id')]
                ) ? $title_config[$this->config->get('config_language_id')] : 'Vindi OpenCart',
                'terms'      => '',
                'sort_order' => $this->config->get('payment_vindi_sort_order'),
            ];
        }

        return $method_data;
    }

    public function api($api_method, $data = [], $method = 'GET')
    {
        $this->load->language('extension/payment/vindi');
        $gateway = "$this->getGateway()/api/$this->getApiVersion()";
        $url = "$gateway$this->config->get('payment_vindi_merchant')/$api_method";
        $curl_options = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "$this->config->get('payment_vindi_api_key'):",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "User-Agent: OpenCart/$this->extension_version; $this->host",
            ],
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ];

        $put_fd = null;

        if ($method == 'POST') {
            $curl_options[CURLOPT_POST] = true;
            $curl_options[CURLOPT_POSTFIELDS] = json_encode($data);
        } else {
            if ($method == 'PUT') {
                $curl_options[CURLOPT_PUT] = true;

                $write_data = json_encode($data);

                $put_fd = tmpfile();
                fwrite($put_fd, $write_data);
                fseek($put_fd, 0);

                $curl_options[CURLOPT_INFILE] = $put_fd;
                $curl_options[CURLOPT_INFILESIZE] = strlen($write_data);
            }
        }

        $curl = curl_init();
        curl_setopt_array($curl, $curl_options);
        $output = curl_exec($curl);
        curl_close($curl);
        if ($this->config->get('payment_vindi_debug_log')) {
            $text = PHP_EOL.sprintf($this->language->get('text_log_api_intro'), 'REQUEST').PHP_EOL;
            $text .= var_export($curl_options, true).PHP_EOL;
            $text .= sprintf($this->language->get('text_log_api_intro'), 'DATA').PHP_EOL;
            $text .= json_encode($data).PHP_EOL;
            $text .= sprintf($this->language->get('text_log_api_intro'), 'RESPONSE').PHP_EOL;
            $text .= var_export($output, true).PHP_EOL;
            $text .= '=================================='.PHP_EOL;

            $this->log->write($text);
        }

        if (is_resource($put_fd)) {
            fclose($put_fd);
        }

        return json_decode($output, true);
    }

    public function getGateway()
    {
        return 'https://'.$this->config->get('payment_vindi_gateway').'.vindi.com.br';
    }

    public function getApiVersion()
    {
        return $this->api_version;
    }

    public function addOrderHistory($order_history)
    {
        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory(
            $order_history['order_id'],
            $order_history['order_status_id'],
            $order_history['message']
        );
    }

    public function findOrCreateCustomer()
    {
        $internalCustomerId = $this->customer->getId();
        $vindiCustomerCode = $this->vindiCode($internalCustomerId);
        $customerVindi = $this->api(
            "customers/?page=1&query=code%3D$vindiCustomerCode"
        )['customers'];

        if (empty(reset($customerVindi))) {
            $customerVindi = $this->api(
                'customers/', [
                    'name'  => "$this->customer->getFirstName() $this->customer->getLastName()",
                    'email' => $this->customer->getEmail(),
                    'code'  => $vindiCustomerCode,
                ],
            'POST');
        }

        return reset($customerVindi);
    }

    public function createBill(array $customer, $order)
    {
        return $this->api('bills', [
            'customer_id'         => $customer['id'],
            'installments'        => 1,
            'payment_method_code' => 'credit_card',
            'bill_items'          => [
                [
                    'product_code' => 'opencart_product',
                    'amount'       => ($order['total'] - $this->shippingAmount()),
                ],
                [
                    'product_code' => 'opencart_shipping',
                    'amount'       => $this->shippingAmount(),
                ],
            ],
            'payment_profile' => [
                'holder_name'          => $this->request->post['holder_name'],
                'card_expiration'      => $this->request->post['card_expiration'],
                'card_number'          => $this->request->post['card_number'],
                'card_cvv'             => $this->request->post['card_cvv'],
                'payment_method_code'  => 'credit_card',
                'payment_company_code' => $this->request->post['payment_company_code'],
            ],
        ], 'POST');
    }

    public function getPaymentCompanies($paymentMethod)
    {
        return ! empty(
            $paymentMethods = $this->api(
                "payment_methods/?page=1&query=code%3D$paymentMethod['payment_methods']"
            ) ? reset($paymentMethods)['payment_companies'] : array(array(
                'name' => 'Erro ao retornar companhias',
                'code' => 'erro',
            ));
        );
    }

    private function shippingAmount()
    {
        return (float) $this->cart->session->data['shipping_method']['cost'];
    }

    private function vindiCode($uniqueId)
    {
        $chars = [".", ",", "-", "/"];
        $prefix = str_replace($chars, "_", $this->host);
        return "$prefix-$uniqueId";
    }
}
