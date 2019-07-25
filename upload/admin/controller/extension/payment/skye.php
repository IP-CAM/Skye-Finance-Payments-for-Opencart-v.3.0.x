<?php

class ControllerExtensionPaymentSkye extends Controller {
    private $error = [];

    /**
     * @return string
     */
    public function index() {
        $language_data = $this->load->language( 'extension/payment/skye' );

        $this->document->setTitle( $this->language->get( 'heading_title' ) );

        $this->load->model( 'setting/setting' );

        if ( ( $this->request->server['REQUEST_METHOD'] == 'POST' ) && $this->validate() ) {
            $this->model_setting_setting->editSetting( 'payment_skye', $this->request->post );

            $this->session->data['success'] = $this->language->get( 'text_success' );

            $this->response->redirect( $this->url->link( 'marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true ) );
        }

        // Error Strings
        $keys = [
            'skye_warning',
            'skye_region',
            'skye_gateway_environment',
            'skye_gateway_url',
            'skye_merchant_id',
            'skye_secret_key',
            'skye_operator_id',
            'skye_operator_password',
        ];

        foreach ( $keys as $key ) {
            if ( isset( $this->error[ $key ] ) ) {
                $data[ 'error_' . $key ] = $this->error[ $key ];
            } else {
                $data[ 'error_' . $key ] = '';
            }
        }

        // Language Strings
        foreach ( $language_data as $key => $value ) {
            $data[ $key ] = $value;
        }

        // Breadcrumbs
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get( 'text_home' ),
                'href' => $this->url->link( 'common/dashboard', 'user_token=' . $this->session->data['user_token'], true ),
            ],
            [
                'text' => $this->language->get( 'text_extension' ),
                'href' => $this->url->link( 'marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true ),
            ],
            [
                'text' => $this->language->get( 'heading_title' ),
                'href' => $this->url->link( 'extension/payment/skye', 'user_token=' . $this->session->data['user_token'], true ),
            ],
        ];

        // Actions / Links
        $data['action'] = $this->url->link( 'extension/payment/skye', 'user_token=' . $this->session->data['user_token'], true );
        $data['cancel'] = $this->url->link( 'marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true );

        // Dropdown Data
        $this->load->model( 'localisation/geo_zone' );
        $this->load->model( 'localisation/order_status' );

        $data['geo_zones']            = $this->model_localisation_geo_zone->getGeoZones();
        $data['order_statuses']       = $this->model_localisation_order_status->getOrderStatuses();
        $data['regions']              = $this->getRegions();
        $data['gateway_environments'] = $this->getGatewayEnvironments();

        // Form Values
        $keys = [
            'payment_skye_title',
            'payment_skye_shop_name',
            'payment_skye_region',
            'payment_skye_gateway_environment',
            'payment_skye_gateway_url',
            'payment_skye_merchant_id',
            'payment_skye_secret_key',
            'payment_skye_operator_id',
            'payment_skye_operator_password',
            'payment_skye_product_code',
            'payment_skye_product_code',
            'payment_skye_order_status_completed_id',            
            'payment_skye_order_status_pending_id',           
            'payment_skye_order_status_failed_id',
            'payment_skye_order_status_failed_message',
            'payment_skye_order_status_cancelled_message',
            'payment_skye_order_status_declined_message',
            'payment_skye_order_status_referred_message',
            'payment_skye_geo_zone_id',
            'payment_skye_status',
            'payment_skye_min_amt',
            'payment_skye_max_amt',
            'payment_skye_sort_order',
        ];

        $defaults = [
            'payment_skye_title'                     => 'Skye',
            'payment_skye_order_status_completed_id' => 5,            
            'payment_skye_order_status_pending_id'   => 1,
            'payment_skye_order_status_failed_id'    => 10,
            'payment_skye_order_status_failed_message' => 'Your payment with Skye Finance failed.',
            'payment_skye_order_status_cancelled_message' => 'Your payment with Skye Finance has been cancelled.',
            'payment_skye_order_status_declined_message' => 'Your payment with Skye Finance was declined. Please try another payment option for your purchase.',
            'payment_skye_order_status_referred_message' => 'Your payment with Skye Finance has been referred. Your order is pending and will be updated accordingly.',
            'payment_skye_min_amt'                   => 250,
            'payment_skye_max_amt'                   => 99999
        ];

        foreach ( $keys as $key ) {
            if ( isset( $this->request->post[ $key ] ) ) {
                $data[ $key ] = $this->request->post[ $key ];
            } else if ( ! $this->config->has( $key ) && isset( $defaults[ $key ] ) ) {
                $data[ $key ] = $defaults[ $key ];
            } else if ( $this->config->has( $key ) ) {
                $data[ $key ] = $this->config->get( $key );
            } else {
                $data[ $key ] = "";
            }
        }

        // Layout
        $data['header']      = $this->load->controller( 'common/header' );
        $data['column_left'] = $this->load->controller( 'common/column_left' );
        $data['footer']      = $this->load->controller( 'common/footer' );

        // Render Output
        $this->response->setOutput( $this->load->view( 'extension/payment/skye', $data ) );
    }

    /**
     * @return bool
     */
    protected function validate() {
        if ( ! $this->user->hasPermission( 'modify', 'extension/payment/skye' ) ) {
            $this->error['skye_warning'] = $this->language->get( 'error_permission' );
        }

        $keys = [
            'payment_skye_title'       => 'Title',
            'payment_skye_region'      => 'Region',
            'payment_skye_merchant_id' => 'Merchant ID',
            'payment_skye_secret_key'  => 'Secret Key',
            'payment_skye_operator_id' => 'Operator ID',
            'payment_skye_operator_password' => 'Operator Password',
            'payment_skye_product_code' => 'Skye Mastercard® term',
        ];

        foreach ( $keys as $key => $name ) {
            if ( ! isset( $this->request->post[ $key ] ) || empty( $this->request->post[ $key ] ) ) {
                $this->error[ $key ] = sprintf( $this->language->get( 'error_required' ), $name );
            }
        }

        if (
            $this->request->post['payment_skye_gateway_environment'] == 'other' && ( ! isset( $this->request->post['payment_skye_gateway_url'] ) || preg_match( '@^https://@', $this->request->post['payment_skye_gateway_url'] ) !== 1 )
        ) {
            $this->error['skye_gateway_url'] = $this->language->get( 'error_gateway_url_format' );
        }

        return ! $this->error;
    }

    /**
     * @return mixed[]
     */
    private function getRegions() {
        return [
            [
                'code' => 'AU',
                'name' => 'Australia',
            ]
        ];
    }

    /**
     * @return mixed[]
     */
    private function getGatewayEnvironments() {
        return [
            [
                'code' => 'sandbox',
                'name' => 'Sandbox',
            ],
            [
                'code' => 'live',
                'name' => 'Live',
            ],
            [
                'code' => 'other',
                'name' => 'Other',
            ],
        ];
    }
}