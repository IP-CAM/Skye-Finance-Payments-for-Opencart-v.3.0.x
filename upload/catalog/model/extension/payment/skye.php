<?php

const SKYE_VERSION       = '1.5.8-OC3.0';
const SKYE_DESCRIPTION   = "Buy now, Breathe easy";

class ModelExtensionPaymentSkye extends Model {
    /**
     * @param mixed[] $address
     * @param double $total
     *
     * @return mixed[]
     */
    public function getMethod( $address, $total ) {
        $this->load->language( 'extension/payment/skye' );

        $query = $this->db->query( "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get( 'payment_skye_geo_zone_id' ) . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')" );

        $status = false;
        if ( ! $this->config->get( 'payment_skye_geo_zone_id' ) ) {
            $status = true;
        } elseif ( $query->num_rows ) {
            $status = true;
        }

        $method_data = [];
        if ( $status ) {
            $method_data = [
                'code'       => 'skye',
                'title'      => $this->config->get( 'payment_skye_title' ),
                'terms'      => $this->getDescription(),
                'sort_order' => $this->config->get( 'payment_skye_sort_order' ),
                'text_image' => $this->language->get( 'text_image' )
            ];
        }

        return $method_data;
    }

     /**
     * @return mixed[] 
     */
    public function getTransactionId(){
        $this->load->model( 'checkout/order' );

        $environment = $this->config->get( 'payment_skye_gateway_environment' );

        if ($environment != 'live'){
            $middle_name = 'TWOPPPPPPPPPPPPPP';
        }else{
            $middle_name = '';
        }

        $order_info            = $this->model_checkout_order->getOrder( $this->session->data['order_id'] );
        $payment_country_info  = $this->db->query( "SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int) $order_info['payment_country_id'] . "' AND status = 1 LIMIT 1" )->row;
        $payment_zone_info     = $this->db->query( "SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int) $order_info['payment_zone_id'] . "' AND status = 1 AND country_id = '" . (int) $order_info['payment_country_id'] . "' LIMIT 1" )->row;
        $shipping_country_info = $this->db->query( "SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int) $order_info['shipping_country_id'] . "' AND status = 1 LIMIT 1" )->row;
        $shipping_zone_info    = $this->db->query( "SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int) $order_info['shipping_zone_id'] . "' AND status = 1 AND country_id = '" . (int) $order_info['shipping_country_id'] . "' LIMIT 1" )->row;
                

        $format_shipping_address = $order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2'];
        $format_shipping_address_group = $this->formatAddress($format_shipping_address, $order_info['shipping_city'], $order_info['shipping_postcode'], $order_info['shipping_zone_code']);
        $format_billing_address = $order_info['payment_address_1'] . ' ' . $order_info['payment_address_2'];
        $format_billing_address_group = $this->formatAddress($format_billing_address, $order_info['payment_city'], $order_info['payment_postcode'],  $order_info['payment_zone_code']);

        if (class_exists('SoapClient'))
        {
            $transaction_information = Array(
                'MerchantId' => $this->config->get( 'payment_skye_merchant_id' ),
                'OperatorId' => $this->config->get( 'payment_skye_operator_id' ),
                'Password' => $this->config->get( 'payment_skye_operator_password' ),
                'EncPassword' => '',
                'Offer' => $this->config->get( 'payment_skye_product_code' ),
                'CreditProduct'=> 'MyBuy_Online',
                'NoApps' => '',
                'OrderNumber' => $this->session->data['order_id'],
                'ApplicationId' => '',
                'Description' => $order_info['store_name'],
                'Amount' => $this->currency->format( $order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false ),
                'ExistingCustomer' => '0',
                'Title' => '',
                'FirstName' => $order_info['payment_firstname'],
                'MiddleName' => $middle_name,
                'Surname' => $order_info['payment_lastname'],
                'Gender' => '',
                'BillingAddress' => $format_billing_address_group,
                'DeliveryAddress' => $format_shipping_address_group,
                'WorkPhoneArea' => '',
                'WorkPhoneNumber' => '',
                'HomePhoneArea' => '',
                'HomePhoneNumber' => '',
                'MobilePhoneNumber' => preg_replace('/\D+/', '', $order_info['telephone']),
                'EmailAddress' => $order_info['email'],
                'Status' => '',
                'ReturnApprovedUrl' => $this->url->link( 'extension/payment/skye/complete&order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', '', true ),
                'ReturnDeclineUrl' => $this->url->link( 'extension/payment/skye/decline&order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', '', true ),
                'ReturnWithdrawUrl' => $this->url->link( 'extension/payment/skye/cancel&order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', '', true ),
                'ReturnReferUrl' => $this->url->link( 'extension/payment/skye/refer&order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', '', true ),
                'SuccessPurch' => '',
                'SuccessAmt' => '',
                'DateLastPurch' => '',
                'PayLastPurch' => '',
                'DateFirstPurch' => '',
                'AcctOpen' => '',
                'CCDets' => '',
                'CCDeclines' => '',
                'CCDeclineNum' => '',
                'DeliveryAddressVal' => '',
                'Fraud' => '',
                'EmailVal' => '',
                'MobileVal' => '',
                'PhoneVal' => '',
                'TransType' => '',
                'UserField1' => '',
                'UserField2' => '',
                'UserField3' => '',
                'UserField4' => '',
                'SMSCustLink' => '',
                'EmailCustLink' => '',
                'SMSCustTemplate' => '',
                'EmailCustTemplate' => '',
                'SMSCustTemplate' => '',
                'EmailDealerTemplate' => '',
                'EmailDealerSubject' => '',
                'EmailCustSubject' => '',
                'DealerEmail' => '',
                'DealerSMS' => '',
                'CreditLimit' => ''
            );
      
            $skye_params = Array (
                'TransactionInformation' => $transaction_information,
                'SecretKey' => $this->config->get( 'payment_skye_secret_key' )
            );
            $transaction_id = '';
            $soap_client = new SoapClient($this->getSoapUrl().'?wsdl', ['trace' => true, 'exceptions' => true]);
            try{
                $response = $soap_client->__soapCall('BeginIPLTransaction',[$skye_params]);
                $transaction_id = $response->BeginIPLTransactionResult;
                $params = [
                    'seller' => $this->config->get( 'payment_skye_merchant_id' ),
                    'transactionId' => $transaction_id,
                    'ifol' => 'true'
                ];
            }catch(Exception $ex){
                $this->log->write( 'Skye Error: ' . $ex->getMessage());
                $this->log->write( 'Skye Error: ' . $soap_client->__getLastRequest() . ' ' . $ex->getMessage() );
                $this->log->write( 'Skye Error: ' . $soap_client->__getLastResponse() . ' ' . $ex->getMessage() );         
                // Give the customer a general error               
                $this->session->data['error'] = $this->language->get( 'Skye system error: Please try again later. No orders were created.');
                $params = [];
            }        
            
        } else {                    
            $url = $this->getSoapUrl();            
            $xml  = '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <BeginIPLTransaction xmlns="http://mybuyonline.com.au/">
                  <TransactionInformation>
                    <MerchantId>'.$this->config->get( 'payment_skye_merchant_id' ).'</MerchantId>
                    <OperatorId>'.$this->config->get( 'payment_skye_operator_id' ).'</OperatorId>
                    <Password>'.$this->config->get( 'payment_skye_operator_password' ).'</Password>
                    <EncPassword/>
                    <Campaign/>
                    <Offer>'.$this->config->get( 'payment_skye_product_code' ).'</Offer>
                    <CreditProduct>MyBuy_Online</CreditProduct>
                    <NoApps/>
                    <OrderNumber>'.$this->session->data['order_id'].'</OrderNumber>
                    <ApplicationId/>
                    <Description>'.$order_info['store_name'].'</Description>
                    <Amount>'.$this->currency->format( $order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false ).'</Amount>
                    <ExistingCustomer>0</ExistingCustomer>
                    <Title/>
                    <FirstName>'.$order_info['payment_firstname'].'</FirstName>
                    <MiddleName>'.$middle_name.'</MiddleName>
                    <Surname>'.$order_info['payment_lastname'].'</Surname>
                    <Gender/>
                    <BillingAddress>
                      <AddressType>'.$format_billing_address_group['AddressType'].'</AddressType>
                      <UnitNumber/>
                      <StreetNumber>'.$format_billing_address_group['StreetNumber'].'</StreetNumber>
                      <StreetName>'.$format_billing_address_group['StreetName'].'</StreetName>
                      <StreetType>'.$format_billing_address_group['StreetType'].'</StreetType>
                      <Suburb>'.$format_billing_address_group['Suburb'].'</Suburb>
                      <City>'.$format_billing_address_group['City'].'</City>
                      <State>'.$format_billing_address_group['State'].'</State>
                      <Postcode>'.$format_billing_address_group['Postcode'].'</Postcode>
                      <DPID/>
                    </BillingAddress>
                    <DeliveryAddress>
                      <AddressType>'.$format_shipping_address_group['AddressType'].'</AddressType>
                      <UnitNumber/>
                      <StreetNumber>'.$format_shipping_address_group['StreetNumber'].'</StreetNumber>
                      <StreetName>'.$format_shipping_address_group['StreetName'].'</StreetName>
                      <StreetType>'.$format_shipping_address_group['StreetType'].'</StreetType>
                      <Suburb>'.$format_shipping_address_group['Suburb'].'</Suburb>
                      <City>'.$format_shipping_address_group['City'].'</City>
                      <State>'.$format_shipping_address_group['State'].'</State>
                      <Postcode>'.$format_shipping_address_group['Postcode'].'</Postcode>
                      <DPID/>
                    </DeliveryAddress>
                    <WorkPhoneArea/>
                    <WorkPhoneNumber/>
                    <HomePhoneArea/>
                    <HomePhoneNumber/>
                    <MobilePhoneNumber>'.preg_replace('/\D+/', '', $order_info['telephone']).'</MobilePhoneNumber>
                    <EmailAddress>'.$order_info['email'].'</EmailAddress>
                    <Status/>
                    <ReturnApprovedUrl>'.$this->url->link( 'extension/payment/skye/complete', 'order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', true ).'</ReturnApprovedUrl>
                    <ReturnDeclineUrl>'.$this->url->link( 'extension/payment/skye/decline', 'order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', true ).'</ReturnDeclineUrl>
                    <ReturnWithdrawUrl>'.$this->url->link( 'extension/payment/skye/cancel', 'order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', true ).'</ReturnWithdrawUrl>
                    <ReturnReferUrl>'.$this->url->link( 'extension/payment/skye/refer', 'order='.$this->session->data['order_id'].'&transaction=[TRANSACTIONID]', true ).'</ReturnReferUrl>
                    <CustomerID/>
                    <SuccessPurch/>
                    <SuccessAmt/>
                    <DateLastPurch/>
                    <PayLastPurch/>
                    <DateFirstPurch/>
                    <AcctOpen/>
                    <CCDets/>
                    <CCDeclines/>
                    <CCDeclineNum/>
                    <DeliveryAddressVal/>
                    <Fraud/>
                    <EmailVal/>
                    <MobileVal/>
                    <PhoneVal/>
                    <TransType/>
                    <UserField1/>
                    <UserField2/>
                    <UserField3/>
                    <UserField4/>
                    <SMSCustLink/>
                    <EmailCustLink/>
                    <SMSCustTemplate/>
                    <EmailCustTemplate/>
                    <SMSDealerTemplate/>
                    <EmailDealerTemplate/>
                    <EmailDealerSubject/>
                    <EmailCustSubject/>
                    <DealerEmail/>
                    <DealerSMS/>
                    <CreditLimit/>
                  </TransactionInformation>
                  <SecretKey>'.$this->config->get( 'payment_skye_secret_key' ).'</SecretKey>
                </BeginIPLTransaction>
              </soap:Body>
            </soap:Envelope>';
                        
            $headers = array(
                "Content-type: text/xml;charset=\"utf-8\"",
                "Accept: text/xml",
                "Cache-Control: no-cache",
                "Pragma: no-cache"
            );     
            $curl = curl_init();            
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);                
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $xml); // the SOAP request
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);                          
            $response = curl_exec($curl);                     
            $this->log->write($response);                      
            $xmlResponse = new SimpleXMLElement($response);   
            $xmlResponse->registerXPathNamespace('soapResponse', 'http://mybuyonline.com.au/');
            // Get value of first "GetListResponse" element
            $responseCount = sizeof($xmlResponse->xpath('//soapResponse:BeginIPLTransactionResult'));
            if ($responseCount > 0)
            {
                $result = (string)$xmlResponse->xpath('//soapResponse:BeginIPLTransactionResult')[0];                                    
                $params = [
                    'seller' => $this->config->get( 'payment_skye_merchant_id' ),
                    'transactionId' => $result,
                    'ifol' => 'true'
                ];                    
            } else {
                $result = (string)$xmlResponse->xpath('//faultstring')[0];                                        
                $this->log->write( 'Skye Error: ' . $result);                
                $this->session->data['error'] = $this->language->get( 'Skye system error: Please try again later. No orders were created.');
                $params = [];
            }
            curl_close($curl);                                
        }

        return $params;
    }
    /** 
     * @param string $address_parts
     * @param string $order
     * 
     * @return string
    */
    private function formatAddress($address_parts, $city, $postcode, $state) {        
        $address_no_str = "";
        $address_name_str = "";
        $address_type_str = "";
        $address_street0 = explode(' ', $address_parts);
        $address_street_count = count($address_street0);        
        foreach ($address_street0 as $address_value0) {
          if (is_numeric($address_value0))
          {
            $address_no_str = $address_value0;
          }
        }
        if ($address_street_count == 4)
        {
          $address_name_str = $address_street0[$address_street_count - 3];
          $address_type_str = $address_street0[$address_street_count - 2];
        }
        //$address_type_str = WC_Skye_Online_Street_Type()->parse($address_type_str);
        $address_type_str = $this->getStreetType($address_type_str);              
        $formatted_address = Array(
          'AddressType' => 'Residential',
          'UnitNumber' => '',
          'StreetNumber' => $address_no_str? $address_no_str : '',
          'StreetName' => $address_name_str? $address_name_str : '',
          'StreetType' => $address_type_str? $address_type_str : '',
          'Suburb' => $city,
          'City' => $city,
          'State' => $state,
          'Postcode' => $postcode,
          'DPID' => ''
        );
        return $formatted_address;
    }

    /**
     * Fix the street type (SKYE is very sensitive)
     * @param  String $street_type
     * @return String
     */
    private function getStreetType($street_type) {
        $street_type = strtolower($street_type);

        $street_types = array(
            'ave' => 'avenue',
            'acc' => 'access',
            'al' => 'alley',
            'awy' => 'alleyway',
            'amb' => 'amble',
            'anc' => 'anchorage',
            'app' => 'approach',
            'arc' => 'arcade',
            'art' => 'artery',
            'bk' => 'bank',
            'bas' => 'basin',
            'by' => 'bay',
            'bch' => 'beach',
            'bt' => 'belt',
            'bnd' => 'bend',
            'blk' => 'block',
            'blv' => 'boulevard',
            'br' => 'brace',
            'bra' => 'brae',
            'brk' => 'break',
            'bs' => 'briars',
            'brg' => 'bridge',
            'bwy' => 'broadway',
            'brw' => 'brow',
            'bps' => 'bypass',
            'byw' => 'byway',
            'cwy' => 'causeway',
            'ctr' => 'centre',
            'cnwy' => 'centreway',
            'ch' => 'chase',
            'cir' => 'circle',
            'clt' => 'circlet',
            'cct' => 'circuit',
            'crc' => 'circus',
            'cl' => 'close',
            'cld' => 'colonnade',
            'cmn' => 'common',
            'cnc' => 'concourse',
            'cps' => 'copse',
            'cnr' => 'corner',
            'cso' => 'corso',
            'crse' => 'course',
            'ct' => 'court',
            'cts' => 'courts',
            'cyd' => 'courtyard',
            'cve' => 'cove',
            'crs' => 'crescent',
            'cs' => 'crest',
            'crft' => 'croft',
            'crss' => 'cross',
            'csg' => 'crossing',
            'crd' => 'crossroad',
            'crw' => 'crossway',
            'csw' => 'cruiseway',
            'cds' => 'cul-de-sac',
            'cut' => 'cutting',
            'dal' => 'dale',
            'dl' => 'dell',
            'dvn' => 'deviation',
            'dip' => 'dip',
            'dtr' => 'distributor',
            'dn' => 'downs',
            'dr' => 'drive',
            'dry' => 'driveway',
            'ea' => 'east',
            'ege' => 'edge',
            'elb' => 'elbow',
            'elm' => 'elm',
            'ed' => 'end',
            'ent' => 'entrance',
            'esp' => 'esplanade',
            'est' => 'estate',
            'exp' => 'expressway',
            'extn' => 'extension',
            'fay' => 'fairway',
            'ftk' => 'fire track',
            'ftl' => 'firetrail',
            'flt' => 'flat',
            'flats' => 'flats',
            'flw' => 'follow',
            'ftw' => 'footway',
            'fsr' => 'foreshore',
            'fmn' => 'formation',
            'fwy' => 'freeway',
            'frt' => 'front',
            'fr' => 'frontage',
            'gap' => 'gap',
            'gdn' => 'garden',
            'gds' => 'gardens',
            'gte' => 'gate',
            'gts' => 'gates',
            'gl' => 'glade',
            'gln' => 'glen',
            'gra' => 'grange',
            'ge' => 'great',
            'grn' => 'green',
            'grd' => 'ground',
            'gve' => 'grove',
            'gly' => 'gully',
            'havn' => 'haven',
            'head' => 'head',
            'hth' => 'heath',
            'hts' => 'heights',
            'hrd' => 'highroad',
            'hwy' => 'highway',
            'hl' => 'hill',
            'hub' => 'hub',
            'itc' => 'interchange',
            'its' => 'intersection',
            'is' => 'island',
            'jn' => 'junction',
            'key' => 'key',
            'ldg' => 'landing',
            'la' => 'lane',
            'lwy' => 'laneway',
            'largo' => 'largo',
            'lee' => 'lees',
            'li' => 'line',
            'lk' => 'link',
            'lt' => 'little',
            'loch' => 'loch',
            'lkt' => 'lookout',
            'lp' => 'loop',
            'lwr' => 'lower',
            'ma' => 'mall',
            'md' => 'mead',
            'mw' => 'meadows',
            'mdr' => 'meander',
            'mew' => 'mew',
            'mws' => 'mews',
            'ml' => 'mile',
            'mwy' => 'motorway',
            'mt' => 'mount',
            'nk' => 'nook',
            'out' => 'outlook',
            'pde' => 'parade',
            'pk' => 'park',
            'pkld' => 'parklands',
            'pwy' => 'parkway',
            'prt' => 'part',
            'pss' => 'pass',
            'pth' => 'path',
            'phwy' => 'pathway',
            'pza' => 'piazza',
            'pl' => 'place',
            'plat' => 'plateau',
            'plz' => 'plaza',
            'pkt' => 'pocket',
            'pnt' => 'point',
            'pts' => 'points',
            'pt' => 'port',
            'prm' => 'promenade',
            'purs' => 'pursuit',
            'qud' => 'quad',
            'qdg' => 'quadrangle',
            'qd' => 'quadrant',
            'qy' => 'quay',
            'qys' => 'quays',
            'ra' => 'ramble',
            'rmp' => 'ramp',
            'rge' => 'range',
            'rch' => 'reach',
            'res' => 'reserve',
            'rst' => 'rest',
            'ret' => 'ret',
            'rt' => 'retreat',
            'retn' => 'return',
            'rid' => 'ride',
            'rdg' => 'ridge',
            'rdw' => 'ridgeway',
            'rtw' => 'right of way',
            'rng' => 'ring',
            'ri' => 'rise',
            'rvr' => 'river',
            'rvw' => 'riverway',
            'rva' => 'riviera',
            'rd' => 'road',
            'rds' => 'roads',
            'rsd' => 'roadside',
            'rdy' => 'roadway',
            'rde' => 'ronde',
            'rbl' => 'rosebowl',
            'rty' => 'rotary',
            'rnd' => 'round',
            'rte' => 'route',
            'row' => 'row',
            'rue' => 'rue',
            'run' => 'run',
            'swy' => 'service way',
            'shop' => 'shopping centre',
            'sdg' => 'siding',
            'slp' => 'slope',
            'snd' => 'sound',
            'spr' => 'spur',
            'sq' => 'square',
            'sta' => 'stairs',
            'shw' => 'state highway',
            'stp' => 'steps',
            'strait' => 'strait',
            'stra' => 'strand',
            'st' => 'street',
            'sp' => 'strip',
            'sy' => 'subway',
            'smmt' => 'summit',
            'ta' => 'tailings',
            'tarn' => 'tarn',
            'tce' => 'terrace',
            'thf' => 'thoroughfare',
            'twy' => 'tollway',
            'top' => 'top',
            'tor' => 'tor',
            'twr' => 'towers',
            'tr' => 'track',
            'trl' => 'trail',
            'trr' => 'trailer',
            'ty' => 'tramway',
            'tri' => 'triangle',
            'tkw' => 'trunkway',
            'tn' => 'tunnel',
            'tur' => 'turn',
            'unp' => 'underpass',
            'upr' => 'upper',
            'val' => 'vale',
            'vy' => 'valley',
            'vdt' => 'viaduct',
            'vw' => 'view',
            've' => 'village',
            'vlls' => 'villas',
            'vst' => 'vista',
            'wd' => 'wade',
            'wk' => 'walk',
            'wky' => 'walkway',
            'waters' => 'waters',
            'way' => 'way',
            'wt' => 'west',
            'wf' => 'wharf',
            'wood' => 'wood',
            'wnd' => 'wynd',
            'yrd' => 'yard',
        );

        if (isset($street_types[$street_type])) {
            return ucwords($street_type);
        }
        $key = array_search($street_type, $street_types);
        if ($key) {
          return ucwords($key);
        }
        return null;
    }

    /**
     * @return mixed[]
     */
    public function getStatuses() {
        return [
            'completed' => $this->config->get( 'payment_skye_order_status_completed_id' ),
            'pending'   => $this->config->get( 'payment_skye_order_status_pending_id' ),
            'failed'    => $this->config->get( 'payment_skye_order_status_failed_id' ),
        ];
    }

    /**
     * @param string $outcome
     *
     * @return string|null
     */
    public function getStatus( $outcome ) {
        $statuses = $this->getStatuses();

        return (
        isset( $statuses[ $outcome ] )
            ? $statuses[ $outcome ]
            : 0
        );
    }

    /**
     * @return string
     */
    public function getGatewayUrl() {
        $environment = $this->config->get( 'payment_skye_gateway_environment' );
        if ( $environment == 'other' ) {
            return $this->config->get( 'payment_skye_gateway_url' );
        }

        $region         = $this->config->get( 'payment_skye_region' );
        $country_domain = 'com.au';
        $title          = $this->config->get( 'payment_skye_title' );
        $domainsTest    = array(
            'Skye'   => 'cxskyeuat.flexicards.'
        );
        $domains        = array(
            'Skye'   => 'apply.flexicards.'
        );

        return 'https://' . ( $environment == 'live' ? $domains[ $title ] : $domainsTest[ $title ] ) . $country_domain . '/PromotionSelector?';
    }

    /**
     * @return string
     */
    public function getSoapUrl() {
        $environment = $this->config->get( 'payment_skye_gateway_environment' );
        if ( $environment == 'other' ) {
            return $this->config->get( 'payment_skye_gateway_url' );
        }

        $region         = $this->config->get( 'payment_skye_region' );
        $country_domain = 'com.au';
        $title          = $this->config->get( 'payment_skye_title' );
        $domainsTest    = array(
            'Skye'   => 'captureuat.onceonline.'
        );
        $domains        = array(
            'Skye'   => 'applications.flexicards.'
        );

        return 'https://' . ( $environment == 'live' ? $domains[ $title ] : $domainsTest[ $title ] ) . $country_domain . '/IPL_service/ipltransaction.asmx';
    }

    public function getDescription() {
        $title       = $this->config->get( 'payment_skye_title' );
        $description = SKYE_DESCRIPTION;

        return $description;
    }    

    
}
