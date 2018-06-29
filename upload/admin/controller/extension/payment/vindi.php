<?php

class ControllerExtensionPaymentVindi extends Controller
{
    private $error = [];

    public function index()
    {
        $this->load->language('extension/payment/vindi');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/vindi');

        if (('POST' === $this->request->server['REQUEST_METHOD']) && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_vindi', $this->request->post);
            $this->model_extension_payment_vindi->addProduct();
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension',
                'user_token='.$this->session->data['user_token'].'&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token='.$this->session->data['user_token'], true),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension',
                'user_token='.$this->session->data['user_token'].'&type=payment', true),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/vindi', 'user_token='.$this->session->data['user_token'],
                true),
        ];

        $data['action'] = $this->url->link('extension/payment/vindi',
            'user_token='.$this->session->data['user_token'], true);
        $data['rehook_events'] = $this->url->link('extension/payment/vindi/hook_events',
            'user_token='.$this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension',
            'user_token='.$this->session->data['user_token'].'&type=payment', true);

        $data['url_list_transactions'] = html_entity_decode($this->url->link('extension/payment/vindi/transactions',
            'user_token='.$this->session->data['user_token'].'&page={PAGE}', true));

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_vindi_status'])) {
            $data['payment_vindi_status'] = $this->request->post['payment_vindi_status'];
        } else {
            $data['payment_vindi_status'] = $this->config->get('payment_vindi_status');
        }

        if (isset($this->request->post['payment_vindi_sort_order'])) {
            $data['payment_vindi_sort_order'] = $this->request->post['payment_vindi_sort_order'];
        } else {
            $data['payment_vindi_sort_order'] = $this->config->get('payment_vindi_sort_order');
        }

        if (isset($this->request->post['payment_vindi_gateway'])) {
            $data['payment_vindi_gateway'] = $this->request->post['payment_vindi_gateway'];
        } else {
            $data['payment_vindi_gateway'] = $this->config->get('payment_vindi_gateway');
        }

        $data['gateways'] = [];

        $data['gateways'][] = [
            'code' => 'sandbox-app',
            'text' => $this->language->get('text_gateway_sandbox'),
        ];

        $data['gateways'][] = [
            'code' => 'app',
            'text' => $this->language->get('text_gateway_production'),
        ];

        if (isset($this->request->post['payment_vindi_api_key'])) {
            $data['payment_vindi_api_key'] = $this->request->post['payment_vindi_api_key'];
        } else {
            $data['payment_vindi_api_key'] = $this->config->get('payment_vindi_api_key');
        }

        if (isset($this->error['api_key'])) {
            $data['error_api_key'] = $this->error['api_key'];
        } else {
            $data['error_api_key'] = '';
        }

        if (isset($this->request->post['payment_vindi_debug_log'])) {
            $data['payment_vindi_debug_log'] = $this->request->post['payment_vindi_debug_log'];
        } else {
            $data['payment_vindi_debug_log'] = $this->config->get('payment_vindi_debug_log');
        }

        if (isset($this->request->post['payment_vindi_display_name'])) {
            $data['payment_vindi_display_name'] = $this->request->post['payment_vindi_display_name'];
        } else {
            $data['payment_vindi_display_name'] = $this->config->get('payment_vindi_display_name');
        }

        $data['default_display_name'] = $this->language->get('heading_title');

        $this->load->model('localisation/language');
        $data['languages'] = [];

        foreach ($this->model_localisation_language->getLanguages() as $language) {
            $data['languages'][] = [
                'language_id' => $language['language_id'],
                'name'        => $language['name'].($language['code'] == $this->config->get('config_language') ? $this->language->get('text_default') : ''),
                'image'       => 'language/'.$language['code'].'/'.$language['code'].'.png',
            ];
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/vindi', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/vindi')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_vindi_api_key']) || strlen($this->request->post['payment_vindi_api_key']) < 43) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }
}
