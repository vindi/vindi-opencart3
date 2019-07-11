<?php
define("ORDER_STATUSES", ['success' => 2, 'rejected' => 9]);
define("HTTP_STATUSES", ['success' => 'HTTP/1.1 200 Success', 'rejected' => 'HTTP/1.1 402 Fail']);

class ControllerExtensionPaymentVindi extends Controller
{
    public function index()
    {
        $payment_methods = null;

        if ($this->config->get('payment_vindi_status')) {
            $payment_methods = $this->loadCreditCard();
        }

        if (empty($this->session->data['payment_address']) && ! $this->customer->isLogged()) {
            $data['error_session'] = $this->language->get('error_session');
        }

        return $payment_methods;
    }

    public function send()
    {
        $this->loadCheckoutModules();

        $header = 'Content-Type: application/json';
        $order_status_id = ORDER_STATUSES['rejected'];
        $http = HTTP_STATUSES['rejected'];
        $message = ['error' => 'Houve um erro ao transacionar'];

        $bill = $this->model_extension_payment_vindi->createBill(
            $this->model_extension_payment_vindi->findOrCreateCustomer(),
            $this->model_checkout_order->getOrder($this->cart->session->data['order_id'])
        );

        if (!array_key_exists('errors', $bill) && $bill['bill']['status'] === 'paid') {
            $http = HTTP_STATUSES['success'];
            $order_status_id = ORDER_STATUSES['success'];
            $message = ['status' => 'success'];
        }

        $this->model_checkout_order->addOrderHistory(
            $this->cart->session->data['order_id'],
            $order_status_id,
            $this->language->get('text_callback')
        );
        $this->response->addHeader($http);
        $this->response->addHeader($header);
        $this->response->setOutput(json_encode($message));
    }

    private function loadCreditCard()
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
        $this->load->model('extension/payment/vindi');
        $data['payment_companies'] = $this->model_extension_payment_vindi->getPaymentCompanies(
            'credit_card'
        );

        return $this->load->view('extension/payment/vindi', $data);
    }

    private function loadCheckoutModules()
    {
        $this->load->model('checkout/order');
        $this->load->model('account/customer');
        $this->load->model('extension/payment/vindi');
        $this->load->language('extension/payment/vindi');
        $this->load->model('checkout/order');
    }
}
