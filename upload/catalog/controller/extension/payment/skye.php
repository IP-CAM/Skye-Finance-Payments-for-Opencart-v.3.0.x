<?php

class ControllerExtensionPaymentSkye extends Controller {
    const IS_DEBUG = true;   

    /**
     * @param object $registry
     *
     * @return void
     */
    public function __construct( $registry ) {
        parent::__construct( $registry );

        $this->load->language( 'extension/payment/skye' );
        $this->load->model( 'extension/payment/skye' );
        $this->load->model( 'checkout/order' );
    }

    /**
     * @return string
     */
    public function index() {
        $checkout_order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $order_total = $checkout_order_info['total'];
        $this->debugLogIncoming( 'Order total:'.$order_total );
        if ( $order_total >= $this->config->get( 'payment_skye_min_amt' ) ) {
            $data['button_confirm'] = $this->language->get( 'button_confirm' );

            $data['text_loading'] = $this->language->get( 'text_loading' );

            $transaction_id = $this->model_extension_payment_skye->getTransactionId();

            if (sizeof($transaction_id) == 0)
            {
                $data['error'] = $this->session->data['error'];
            } else {
                $data['params'] = $transaction_id;

                $data['action'] = $this->model_extension_payment_skye->getGatewayUrl();
            }
            
            
        } else {
            $data['error'] = sprintf( $this->language->get( 'error_amount' ), $this->currency->format( $this->config->get( 'payment_skye_min_amt' ), $this->session->data['currency'], 1 ) );
        }

        return $this->load->view( 'extension/payment/skye', $data );
    }

    /**
     * @return void
     */
    public function complete() {        
        $this->debugLogIncoming( 'Complete' );
        $this->debugLogIncoming( 'Order No:'.$this->request->get['order']);
        $order_no = $this->request->get['order'];
        // Validate Response
        try {
            $order_info = $this->getOrderAndVerifyResponse( $this->request->get, 'complete' );
            $this->debugLogIncoming('Order id:'.$order_info['order_id']);
            $order_details = $this->model_checkout_order->getOrder( $order_no );
        } catch ( \Exception $e ) {
            // Give the customer a general error
            $this->session->data['error'] = $this->language->get( 'text_transaction_verification' );

            $this->response->redirect( $this->url->link( 'checkout/checkout', '', true ) );

            return;
        }        

        $this->updateOrder( $order_details, $order_info );

        // Failed transaction outcome
        if ( $order_info['order_status'] == 'failed' ) {
            $this->session->data['error'] = $this->config->get( 'payment_skye_order_status_failed_message' ) . ' Skye Application id:' . $order_info['skye_id'];
            $this->response->redirect( $this->url->link( 'checkout/checkout', '', true ) );
        }

        // Success!
        $this->response->redirect( $this->url->link( 'checkout/success', '', true ) );
    }

    /**
     * @return void
     */
    public function cancel() {
        $this->debugLogIncoming( 'Cancel' );
        $this->debugLogIncoming( 'Order No:'.$this->request->get['order']);

        $this->session->data['error'] = $this->config->get( 'payment_skye_order_status_cancelled_message' );

        $this->response->redirect( $this->url->link( 'checkout/checkout', '', true ) );
    }

    /**
     * @return void
     */
    public function decline() {
        $this->debugLogIncoming( 'Decline' );

        $this->session->data['error'] = $this->config->get( 'payment_skye_order_status_declined_message' );

        $this->response->redirect( $this->url->link( 'checkout/checkout', '', true ) );
    }

    /**
     * @return void
     */
    public function refer() {
        $this->debugLogIncoming( 'Refer' );
        $order_no = $this->request->get['order'];
         // Validate Response
         try {
            $order_info = $this->getOrderAndVerifyResponse( $this->request->get, 'refer' );
            $order_details = $this->model_checkout_order->getOrder( $order_no );
        } catch ( \Exception $e ) {
            // Give the customer a general error
            $this->session->data['error'] = $this->language->get( 'text_transaction_verification' );

            $this->response->redirect( $this->url->link( 'checkout/checkout', '', true ) );

            return;
        }
        
        $pending = array( 
            'order_id' => $order_no,
            'order_status' => 'pending',
            'order_amount' => $order_info['order_amount'],
            'ifol_id' => $order_info['ifol_id'],
            'skye_id' => $order_info['skye_id'],
            'message' => 'Skye Referred'
        );

        $this->updateOrder( $order_details, $pending );
        $this->session->data['success'] = $this->config->get( 'payment_skye_order_status_referred_message' ) . ' Skye Finance ref#: ' . $order_info['skye_id'];

        $this->response->redirect( $this->url->link( 'checkout/checkout', '', true ) );
    }

    /**
     * @param mixed[] $request
     *
     * @return mixed
     */
    private function getOrderAndVerifyResponse( $request, $orderStatus ) {
        $this->debugLogIncoming( 'getOrderAndVerifyResponse' );
        $required = [
            'order',
            'transaction'
        ];

        // Required
        foreach ( $required as $key => $value ) {
            if ( ! isset( $request[ $key ] ) || empty( $request[ $key ] ) ) {
                unset( $required[ $key ] );
            }
        }

        if ( ! empty( $required ) ) {
            throw new \Exception( 'Bad Request. Missing required fields: ' . implode( ', ', $required ) . '.' );
        }

        $wsdl_url = $this->model_extension_payment_skye->getSoapUrl();
        
        $transaction_result = $this->getIPLtransaction($request['transaction'], $request['order'], $wsdl_url, $orderStatus);
        

        if(empty( $transaction_result )) {
            throw new \Exception( $this->session->data['error'] );
            
        }
        
        return $transaction_result;
    }

    /**
   * Get IPL Transaction Status   
   *
   * @access private
   * @param  string $address_parts
   * $param  WC_Order $order
   */
  private function getIPLtransaction ($transaction_id, $order_id, $wsdl_url, $orderStatus){
    $this->debugLogIncoming( 'getIPLtransaction' );     
    $get_ipl_transaction = array (
      'TransactionID' => $transaction_id,
      'MerchantId' => $this->config->get( 'payment_skye_merchant_id' )
    );

    if (class_exists('SoapClient'))
    {
        $this->debugLogIncoming( 'soap' );
        $soapclient = new SoapClient($wsdl_url, ['trace' => true, 'exceptions' => true]);
        try{
            $response = $soapclient->__soapCall('GetIPLTransaction',[$get_ipl_transaction]);
            $ipl_transaction_result = $response->GetIPLTransactionResult->Status;            
            $order_no = $response->GetIPLTransactionResult->OrderNumber;            
            $order_amount = $response->GetIPLTransactionResult->Amount;            
            $skye_app_id = $response->GetIPLTransactionResult->ApplicationId;            
            if ($ipl_transaction_result == 'ACCEPTED'){
                $commitTransaction = $this->commitIPLTransaction($transaction_id, $wsdl_url);
                if ($commitTransaction) {
                    $response = $order_no;
                    $transaction_info = array (
                        'order_id' => $order_no,
                        'order_status' => 'completed',
                        'order_amount' => $order_amount,
                        'ifol_id' => $transaction_id,
                        'skye_id' => $skye_app_id,
                        'message' => 'IPL Success'
                    );
                }else{
                    $transaction_info = array(
                        'order_id' => $order_no,
                        'order_status' => 'failed',
                        'order_amount' => $order_amount,
                        'ifol_id' => $transaction_id,
                        'skye_id' => $skye_app_id,
                        'message' => 'IPL Commit error'
                    );
                }
            }else{  
                if (($ipl_transaction_result == 'PENDING' || $ipl_transaction_result == 'Pending') && $orderStatus == 'complete')
                {
                        $response = $order_id;
                        $transaction_info = array (
                            'order_id' => $order_id,
                            'order_status' => 'completed',
                            'order_amount' => $order_amount,
                            'ifol_id' => $transaction_id,
                            'skye_id' => $skye_app_id,
                            'message' => 'IPL Success'
                        );
                }else{             
                    $transaction_info = array(
                    'order_id' => $order_id,
                    'order_status' => 'pending',
                    'order_amount' => $order_amount,
                    'ifol_id' => $transaction_id,
                    'skye_id' => $skye_app_id,
                    'message' => 'Pending'
                    );
                }
            }      
        }catch(Exception $ex){
            $this->log->write( 'Skye Error: ' . $ex->getMessage());
            $this->log->write( 'Skye Error: ' . $soap_client->__getLastRequest() . ' ' . $ex->getMessage() );
            $this->log->write( 'Skye Error: ' . $soap_client->__getLastResponse() . ' ' . $ex->getMessage() );         
            // Give the customer a general error
            $this->session->data['error'] = $this->language->get( 'skye_error'. $ex->getMessage() );
            $transaction_info = array(
                'order_id' => $order_id,
                'order_status' => 'failed',
                'order_amount' => $order_amount,
                'ifol_id' => $transaction_id,
                'skye_id' => $skye_app_id,
                'message' => $ex->getMessage()
            );
        }        
    }else{   
        $this->debugLogIncoming( 'no soap' );     
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <GetIPLTransaction xmlns="http://mybuyonline.com.au/">
               <TransactionID>'.$transaction_id.'</TransactionID>
              <MerchantId>'.$this->config->get( 'payment_skye_merchant_id' ).'</MerchantId>
            </GetIPLTransaction>
          </soap:Body>
        </soap:Envelope>';
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache"
        );     
        $curl = curl_init();        
        curl_setopt($curl, CURLOPT_URL, $wsdl_url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);                
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml); // the SOAP request
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);              
        //curl_setopt($curl, CURLOPT_VERBOSE, true);                			                    
        $response = curl_exec($curl);                        
        $xmlResponse = new SimpleXMLElement($response);   
       
        $xmlResponse->registerXPathNamespace('soapResponse', 'http://mybuyonline.com.au/');        
        // Get value of first "GetListResponse" element
        $responseCount = sizeof($xmlResponse->xpath('//soapResponse:GetIPLTransactionResult'));
        if ($responseCount > 0)
        {
            //$result = $xmlResponse->xpath('//soapResponse:GetIPLTransactionResult'); 
                        
            $ipl_transaction_result = $xmlResponse->xpath('//soapResponse:GetIPLTransactionResult')[0];
                                    
            $skye_status = (Array)$ipl_transaction_result->Status;
            
            $order_no = (Array)$ipl_transaction_result->OrderNumber;
            
            $order_amount = (Array)$ipl_transaction_result->Amount;
            
            $skye_app_id = (Array)$ipl_transaction_result->ApplicationId;
            $this->debugLogIncoming( 'Skye status:'.$skye_status['0'].' order status:'.$orderStatus );
            if ($skye_status['0'] == 'ACCEPTED'){
                $commitTransaction = $this->commitIPLTransaction($transaction_id, $wsdl_url);                
                if ($commitTransaction) {
                    $response = $order_no['0'];
                    $transaction_info = array (
                        'order_id' => $order_no['0'],
                        'order_status' => 'completed',
                        'order_amount' => $order_amount['0'],
                        'ifol_id' => $transaction_id,
                        'skye_id' => $skye_app_id['0'],
                        'message' => 'IPL Success'
                    );
                }else{
                    $transaction_info = array(
                        'order_id' => $order_no['0'],
                        'order_status' => 'failed',
                        'order_amount' => $order_amount['0'],
                        'ifol_id' => $transaction_id,
                        'skye_id' => $skye_app_id['0'],
                        'message' => 'IPL Commit error'
                    );
                }
            }else{      
                $this->debugLogIncoming( 'Skye status:'.$skye_status['0'].' order status:'.$orderStatus );
                if (($skye_status['0'] == 'PENDING' || $skye_status['0'] == 'Pending') && $orderStatus == 'complete'){
                    $this->debugLogIncoming( 'Skye true!!!' );                
                        $response = $order_id;
                        $transaction_info = array (
                            'order_id' => $order_id,
                            'order_status' => 'completed',
                            'order_amount' => $order_amount['0'],
                            'ifol_id' => $transaction_id,
                            'skye_id' => $skye_app_id['0'],
                            'message' => 'IPL Success'
                        );
                }else{
                    $this->debugLogIncoming( 'Skye false!!!' );
                    $transaction_info = array(
                    'order_id' => $order_id,
                    'order_status' => 'pending',
                    'order_amount' => $order_amount['0'],
                    'ifol_id' => $transaction_id,
                    'skye_id' => $skye_app_id['0'],
                    'message' => 'Pending'
                    );
                }
            }                     
        } else {
            $result = (string)$xmlResponse->xpath('//faultstring')[0];                                        
            $this->log->write( 'Skye Error: ' . $result);                
            $this->session->data['error'] = $this->language->get( 'skye_error'.' '.$result);
            // Give the customer a general error    
            $transaction_info = array(
                'order_id' => $order_id,
                'order_status' => 'failed',
                'order_amount' => '',
                'ifol_id' => $transaction_id,
                'skye_id' => '',
                'message' => 'Error'
            );
        }
        curl_close($curl);                     
    }    
    return $transaction_info;
  }
  /**
   * Commit IPL Transaction
   * Commit transaction in Skye
   *
   * @access private
   * @param  string $transaction_id
   */
  private function commitIPLTransaction($transaction_id, $wsdl_url) {  
    $this->debugLogIncoming( 'commitIPLTransaction' );        
    $commit_ipl_transaction = array (
      'TransactionID' => $transaction_id,
      'MerchantId' => $this->config->get( 'payment_skye_merchant_id' )
    );
    $commit_ipl_transaction_result = false;
    if (class_exists('SoapClient'))
    {
        $this->debugLogIncoming( 'soap' );
        $soapclient = new SoapClient($wsdl_url, ['trace' => true, 'exceptions' => true]);
        try{
            $response = $soapclient->__soapCall('CommitIPLTransaction',[$commit_ipl_transaction]);
            $commit_ipl_transaction_result = $response->CommitIPLTransactionResult;
        }catch(Exception $ex){
            $this->log->write( 'Skye Error: ' . $ex->getMessage());
            $this->log->write( 'Skye Error: ' . $soap_client->__getLastRequest() . ' ' . $ex->getMessage() );
            $this->log->write( 'Skye Error: ' . $soap_client->__getLastResponse() . ' ' . $ex->getMessage() );      
            $this->session->data['error'] = $this->language->get( 'skye_error'. $ex->getMessage() );   
        }
    }else{
        $this->debugLogIncoming( 'no soap' );
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <CommitIPLTransaction xmlns="http://mybuyonline.com.au/">
              <TransactionID>'.$transaction_id.'</TransactionID>
              <MerchantId>'.$this->config->get( 'payment_skye_merchant_id' ).'</MerchantId>
            </CommitIPLTransaction>
          </soap:Body>
        </soap:Envelope>';
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache"
        );     
        $curl = curl_init();        
        curl_setopt($curl, CURLOPT_URL, $wsdl_url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);                
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml); // the SOAP request
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);              
        //curl_setopt($curl, CURLOPT_VERBOSE, true);                			                    
        $response = curl_exec($curl);                       
        $xmlResponse = new SimpleXMLElement($response);   
        $xmlResponse->registerXPathNamespace('soapResponse', 'http://mybuyonline.com.au/');        
        // Get value of first "GetListResponse" element
        $responseCount = sizeof($xmlResponse->xpath('//soapResponse:CommitIPLTransactionResult'));
        if ($responseCount > 0)
        {
            $result = (string)$xmlResponse->xpath('//soapResponse:CommitIPLTransactionResult')[0];            
            $commit_ipl_transaction_result = $result;
        }
    }    
    return $commit_ipl_transaction_result;
  }
    /**
     * @param mixed[] $request
     */
    private function updateOrder( $order_info, $request ) {
        $this->debugLogIncoming( 'updateOrder' );
        
        $order_status_id = $this->model_extension_payment_skye->getStatus( $request['order_status'] );        
        $this->debugLogIncoming( 'order status:'.$order_status_id.' order status id:'.$order_info['order_status_id'] );
        $this->debugLogIncoming( 'skye order:'.$request['ifol_id'] );
        $this->debugLogIncoming( 'opencart order:'.$request['order_id']);
        if ( $order_status_id == $order_info['order_status_id'] ) {
            return;
        }

        $comment = '';        
        $comment .= 'Result: ' . $request['order_status'] . "\n";
        $comment .= 'Amount: ' . $request['order_amount'] . "\n";
        $comment .= 'Skye Application Id: ' . $request['skye_id'] . "\n";
        $comment .= 'Skye IFOL Id: ' . $request['ifol_id'] . "\n";
        $comment .= 'Gateway Reference: ' . $request['order_id'] . "\n";        
        $comment .= $request['message'] . "\n";
        $comment = strip_tags( $comment );

        $this->model_checkout_order->addOrderHistory( $order_info['order_id'], $order_status_id, $comment, false );

        return $request['order_status'];
    }

    /**
     * @param string $type
     */
    private function debugLogIncoming( $type ) {
        if ( static::IS_DEBUG ) {
            $str = var_export( [
                'get'  => $_GET,
                'post' => $_POST,
            ], true );

            $this->log->write( 'Skye ' . $type . ' Debug: ' . $str );
        }
    }
}
