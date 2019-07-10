<?php

class ModelExtensionPaymentVindi extends Model
{
    private $api_version = 'v1';
    private $extension_version = '1.0.0';

    public function api($api_method, $data = [], $method = 'GET')
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $this->load->language('extension/payment/vindi');
        $gateway = $this->getGateway().'/api/'.$this->getApiVersion();
        $url = $gateway.$this->config->get('payment_vindi_merchant').'/'.$api_method;
        $curl_options = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->config->get('payment_vindi_api_key').':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: '.trim('OpenCart/'.$this->extension_version."; {$host}"),
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

    public function addProduct()
    {
        $this->api('products/', [
            'name'           => 'OpenCart Produto',
            'code'           => 'opencart_product',
            'status'         => 'active',
            'pricing_schema' => ['price' => 0],
        ], 'POST');

        $this->api('products/', [
            'name'           => 'OpenCart Frete',
            'code'           => 'opencart_shipping',
            'status'         => 'active',
            'pricing_schema' => ['price' => 0],
        ], 'POST');
    }
}
