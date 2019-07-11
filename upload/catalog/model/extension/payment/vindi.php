<?php

class ModelExtensionPaymentVindi extends Model
{
    private $extension_version = '1.0.0';

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

    public function host()
    {
       return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''; 
    }

    public function api($endpoint, $body = [], $method = 'GET')
    {
        $this->load->language('extension/payment/vindi');
        $gateway = $this->getGateway();
        $url = $this->getGateway() . $endpoint;
        $headers = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->config->get('payment_vindi_api_key') . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "User-Agent: OpenCart/$this->extension_version; " . $this->host(),
            ],
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ];

        $put_fd = null;

        if ($method == 'POST') {
            $headers[CURLOPT_POST] = true;
            $headers[CURLOPT_POSTFIELDS] = json_encode($body);
        } elseif ($method == 'PUT') {
            $headers[CURLOPT_PUT] = true;

            $write_data = json_encode($body);

            $put_fd = tmpfile();
            fwrite($put_fd, $write_data);
            fseek($put_fd, 0);

            $headers[CURLOPT_INFILE] = $put_fd;
            $headers[CURLOPT_INFILESIZE] = strlen($write_data);
        } else {
            $url .= '?' . http_build_query($body);
            $headers[CURLOPT_URL] = $url;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $headers);
        $response = curl_exec($curl);
        curl_close($curl);

        if ($this->config->get('payment_vindi_debug_log')) {
            $this->requesterLog($headers, $body, $response);
        }

        if (is_resource($put_fd)) {
            fclose($put_fd);
        }

        return json_decode($response, true);
    }

    public function getGateway()
    {
        $enviroment = $this->config->get('payment_vindi_gateway');
        return "https://$enviroment.vindi.com.br/api/v1/";
    }

    public function requesterLog($headers, $body, $response)
    {
        $text  = PHP_EOL.sprintf($this->language->get('text_log_api_intro'), 'REQUEST').PHP_EOL;
        $text .= var_export($headers, true).PHP_EOL;
        $text .= sprintf($this->language->get('text_log_api_intro'), 'DATA').PHP_EOL;
        $text .= json_encode($body).PHP_EOL;
        $text .= sprintf($this->language->get('text_log_api_intro'), 'RESPONSE').PHP_EOL;
        $text .= var_export($response, true).PHP_EOL;
        $text .= '=================================='.PHP_EOL;

        $this->log->write($text);
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
        $internal_customer_id = $this->customer->getId();
        $customer_code  = $this->vindiCode($internal_customer_id);
        $vindi_customer        = $this->api(
            "customers/", array(
                'page'        => 1,
                'query'       => "code=$customer_code"
            )
        )['customers'];

        if (empty(reset($vindi_customer))) {
            $vindi_customer    = $this->api(
                'customers/', array(
                    'name'    => $this->customer->getFirstName() . 
                    ' ' . $this->customer->getLastName(),
                    'email'   => $this->customer->getEmail(),
                    'code'    => $customer_code,
                ),
            'POST');
        }

        return reset($vindi_customer);
    }

    public function createBill(array $customer, $order)
    {
        return $this->api('bills/', [
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

    public function getPaymentCompanies($payment_method)
    {
        if (! empty($available_payment_methods = $this->api("payment_methods/", 
            array(
                'page'   => 1,
                'query'  => "code=$payment_method"
            ))['payment_methods'])) {
            return reset($available_payment_methods)['payment_companies'];
        }
        return array(
            array(
                'name' => 'Erro ao retornar companhias',
                'code' => 'erro',
            )
        );
    }

    private function shippingAmount()
    {
        return (float) $this->cart->session->data['shipping_method']['cost'];
    }

    private function vindiCode($unique_id)
    {
        $chars = [".", ",", "-", "/"];
        $prefix = str_replace($chars, "_", $this->host());
        return "$prefix-$unique_id";
    }
}
