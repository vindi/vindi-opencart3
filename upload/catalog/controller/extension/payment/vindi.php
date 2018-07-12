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
        if (!array_key_exists('errors', $bill) && $bill['bill']['status'] === 'paid') {
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
}
