<?php
/**
 * ShurjoPay Payment Gateway v2
 *   Error fixes for post payment redirect
 */
defined('ABSPATH') OR exit('Direct access not allowed');
if (!class_exists("WC_Shurjopay")) {

    class WC_Shurjopay extends WC_Payment_Gateway
    {
        public static $log_enabled = false;
        public static $log = false;
        protected $templateFields = array();
        private $domainName = "";
        private $test_mode = false;
        private $merchant_id = "";
        private $api_login = "";
        private $trans_key = "";
        private $api_ip = "";
        private $api_return_url = "";
        private $api_unique_id = "";
        private $after_payment_status = "";
        private $gw_api_url = "";
        private $gw_bank_api_url = "";
        private $decrypt_url = "";
        private $msg = array();
        private $order_status_messege = array(
            "wc-processing" => "Awaiting admin confirmation.",
            "wc-on-hold" => "Awaiting admin confirmation.",
            "wc-cancelled" => "Order Cancelled",
            "wc-completed" => "Successful",
            "wc-pending" => "Awaiting admin confirmation.",
            "wc-failed" => "Order Failed",
            "wc-refunded" => "Payment Refunded."
        );

        public function __construct()
        {
            $this->id = 'wc_shurjopay';
            $this->icon = plugins_url('shurjoPay/template/images/logo.png', SHURJOPAY_PATH);
            $this->method_title = __('ShurjoPay', 'shurjopay');
            $this->method_description = __('ShurjoPay is most popular payment gateway for online shopping in Bangladesh.', 'shurjopay');
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->test_mode = 'yes' === $this->get_option('environment', 'no');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->api_login = $this->get_option('api_login');
            $this->trans_key = $this->get_option('trans_key');
            $this->api_ip = $this->get_option('api_ip', $_SERVER['REMOTE_ADDR']);
            $this->api_return_url = $this->get_option('api_return_url');
            $this->api_unique_id = $this->get_option('api_unique_id');
            $this->after_payment_status = $this->get_option('after_payment_status');

            if ($this->test_mode) {
                self::$log_enabled = true;
                $this->description .= ' ' . sprintf(__('TEST MODE ENABLED. You can use test credentials. See the <a href="%s">Testing Guide</a> for more details.', 'shurjopay'), 'https://shurjopay.com');
                $this->description = trim($this->description);
                $this->domainName = "http://shurjotest.com";
            } else {
                $this->domainName = "https://shurjopay.com";
            }

            $this->gw_api_url = $this->domainName . "/sp-data.php";
            $this->gw_bank_api_url = $this->domainName . "/api/shurjoPayApi.php";
            $this->decrypt_url = $this->domainName . "/merchant/decrypt.php";

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            if ($this->is_valid_for_use()) {
                //IPN actions
                $this->notify_url = str_replace('https:', 'http:', home_url('/wc-api/WC_Shurjopay'));
                add_action('woocommerce_api_wc_shurjopay', array($this, 'check_shurjopay_response'));
                add_action('valid-shurjopay-request', array($this, 'successful_request'));
            } else {
                $this->enabled = 'no';
            }

            //save admin settings
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        }

        /**
         * Admin Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable / Disable', 'shurjopay'),
                    'label' => __('Enable this payment gateway', 'shurjopay'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'shurjopay'),
                    'type' => 'text',
                    'desc_tip' => __('Payment title the customer will see during the checkout process.', 'shurjopay'),
                    'default' => __('ShurjoPay', 'shurjopay'),
                ),
                'description' => array(
                    'title' => __('Description', 'shurjopay'),
                    'type' => 'textarea',
                    'desc_tip' => __('Payment description the customer will see during the checkout process.', 'shurjopay'),
                    'default' => __('Pay securely using ShurjoPay', 'shurjopay'),
                    'css' => 'max-width:350px;'
                ),
                'api_login' => array(
                    'title' => __('Merchant User Name', 'shurjopay'),
                    'type' => 'text',
                    'desc_tip' => __('This is the API Login provided by ShurjoPay when you signed up for an account.', 'shurjopay'),
                ),
                'api_ip' => array(
                    'title' => __('Merchant IP', 'shurjopay'),
                    'type' => 'text',
                    'desc_tip' => __(' api ip This is the API Login provided by ShurjoPay when you signed up for an account.', 'shurjopay'),
                ),
                'api_return_url' => array(
                    'title' => __('Merchant Return Url', 'shurjopay'),
                    'type' => 'text',
                    'desc_tip' => __('return url This is the API Login provided by ShurjoPay when you signed up for an account.', 'shurjopay'),
                ),
                'trans_key' => array(
                    'title' => __('Merchant Password', 'shurjopay'),
                    'type' => 'password',
                    'desc_tip' => __('This is the Transaction Key provided by ShurjoPay when you signed up for an account.', 'shurjopay'),
                ),
                'api_unique_id' => array(
                    'title' => __('api unique id', 'shurjopay'),
                    'type' => 'text',
                    'desc_tip' => __('This is the Transaction Key provided by ShurjoPay when you signed up for an account.', 'shurjopay'),
                ),
                'after_payment_status' => array(
                    'title' => __('After Successful Payment Order Status', 'shurjopay'),
                    'type' => 'select',
                    'description' => __('Change Order Status', 'shurjopay'),
                    'options' => array(
                        "wc-processing" => "Processing",
                        "wc-on-hold" => "On-Hold",
                        "wc-cancelled" => "Cancelled",
                        "wc-completed" => "Completed",
                        "wc-pending" => "Pending",
                        "wc-failed" => "Failed",
                        "wc-refunded" => "Refunded"
                    ),
                    'default' => 'wc-completed',
                ),
                'environment' => array(
                    'title' => __('Test Mode', 'shurjopay'),
                    'label' => __('Enable Test Mode', 'shurjopay'),
                    'type' => 'checkbox',
                    'description' => __('Place the payment gateway in test mode.', 'shurjopay'),
                    'default' => 'no',
                )
            );
        }

        /**
         * Only allowed for only BDT currency
         */
        public function is_valid_for_use()
        {
            return in_array(get_woocommerce_currency(), array('BDT','USD'), true);
        }

        /**
         * Logger for ShurjoPay
         * @param $message
         * @param string $level
         */
        public static function log($message, $level = 'info')
        {
            if (self::$log_enabled) {
                if (empty(self::$log)) {
                    self::$log = wc_get_logger();
                }
                self::$log->log($level, $message, array('source' => 'shurjopay'));
            }
        }

        /**
         * Processes and saves options.
         * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
         *
         * @return bool was anything saved?
         */
        public function process_admin_options()
        {
            $saved = parent::process_admin_options();
            return $saved;
        }

        /**
         * Admin Panel Options.
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options()
        {
            if ($this->is_valid_for_use()) {
                parent::admin_options();
            } else {
                ?>
                <div class="shurjopay_error">
                    <p>
                        <strong><?php esc_html_e('Gateway disabled', 'shurjopay'); ?></strong>: <?php esc_html_e('ShurjoPay does not support your store currency.', 'shurjopay'); ?>
                    </p>
                </div>
                <?php
            }
        }

        /**
         *  There are no payment fields for ShurjoPay, but we want to show the description if set.
         **/
        public function payment_fields()
        {
            if ($this->description) echo wpautop(wptexturize($this->description));
        }

        /**
         * Receipt Page.
         * @param $order
         */
        public function receipt_page($order)
        {
            echo '<p>' . __('Thank you for your order, please click the button below to pay with shurjoPay.', 'shurjopay') . '</p>';
            $this->generate_shurjopay_form($order);
        }

        /**
         * Process the payment and return the result.
         * @param $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
        }

        /**
         * Check for valid ShurjoPay server callback
         **/
        public function check_shurjopay_response()
        {
            global $woocommerce;
            $this->msg['class'] = 'error';
            $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
            if (!isset($_REQUEST['spdata']) || empty($_REQUEST['spdata'])) {
                $this->msg['class'] = 'error';
                $this->msg['message'] = "Payment data not found.";
                return $this->redirect_with_msg(false);
            }
            $encResponse = $_REQUEST["spdata"];
            $decryptValues = $this->decrypt_and_validate($encResponse);
            //var_dump($decryptValues);exit;
            if ($decryptValues == false) {
                $this->msg['class'] = 'error';
                $this->msg['message'] = "Payment data not found.";
                return $this->redirect_with_msg(false);
            }
            $order = $this->get_order_from_response($decryptValues);
            if ($order == false) {
                $this->msg['class'] = 'error';
                $this->msg['message'] = "Order not found.";
                return $this->redirect_with_msg(false);
            }
            /*if ((string)$decryptValues->txnAmount != $order->get_total()) {
                $this->msg['class'] = 'error';
                $this->msg['message'] = "Unauthorized data access.";
                return $this->redirect_with_msg(false);
            }*/
            try {
                if (strtolower($order->get_status()) != 'completed') {
                    switch (strtolower($decryptValues->bankTxStatus)) {
                        case "success":
                            $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                            $this->msg['class'] = 'success';
                            //$order->payment_complete();//Most of the time this should mark an order as 'processing' so that admin can process/post the items.
                            $woocommerce->cart->empty_cart();
                            $order->add_order_note("ShurjoPay payment successful.<br/> Bank Ref Number: " . $decryptValues->bankTxID);
                            $order->update_status($this->after_payment_status, $this->order_status_messege[$this->after_payment_status]);
                            do_action( 'woocommerce_reduce_order_stock', $order );
                            break;
                        case "cancel":
                            $this->msg['message'] = "Transaction canceled.<br/>Bank Ref Number: '" . $decryptValues->bankTxID . "'.<br/>We will keep you posted regarding the status of your order through e-mail";
                            $this->msg['class'] = 'error';
                            $order->add_order_note("Transaction canceled by client.");
                            $order->update_status('cancelled');
                            break;
                        case "fail":
                            $this->msg['class'] = 'error';
                            // $this->msg['message'] = "Thank you for shopping with us.<br/>Bank Ref Number: '" . $decryptValues->bankTxID . "'.<br/>However, the transaction has been failed.";
                            $this->msg['message'] = "Transaction Failed.<br/>Bank Ref Number: '" . $decryptValues->bankTxID . "'.<br/>Thank you for shopping with us.";

                            $order->add_order_note("Transaction failed.");
                            $order->update_status('failed');
                            break;                                                  
                        default:
                            $this->msg['class'] = 'error';
                            $this->msg['message'] = "Thank you for shopping with us.<br/>Bank Ref Number: '" . $decryptValues->bankTxID . "'.<br/>!!However, the transaction has been declined.";
                            $order->add_order_note("Bank transaction not successful.");
                            break;
                    };
                }
            } catch (Exception $e) {
                $order->add_order_note("Exception occurred during transaction");
                $this->msg['class'] = 'error';
                $this->msg['message'] = "Thank you for shopping with us.<br/>Bank Ref Number: '" . $decryptValues->bankTxID . "'.<br/>However, the transaction has been failed/declined.";
            }
            return $this->redirect_with_msg($order, $decryptValues->bankTxStatus);
        }

        private function redirect_with_msg($order, $bankTxStatus)
        {
            global $woocommerce;
            // $redirect = home_url('checkout/order-received/');
            $woocommerce->session->set( 'wc_notices', array() );
            if (function_exists('wc_add_notice')) {
                wc_add_notice($this->msg['message'], $this->msg['class']);
            } else {
                if ($this->msg['class'] == 'success') {
                    $woocommerce->add_message($this->msg['message']);
                } else {
                    $woocommerce->add_error($this->msg['message']);
                }
                $woocommerce->set_messages();
            }

            if ($order) 
            {
                if($order->status == 'completed' || ( strtolower($bankTxStatus) == 'success') )
                {
                    $redirect = home_url('checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key());
                }

                //if($order->status == 'processing')
                if($order->status == 'processing' || ( strtolower($bankTxStatus) == 'success') )
                {
                    $redirect = home_url('checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key());
                }
                elseif($order->status == 'cancelled')
                {
                    // $redirect = $order->get_cancel_order_url_raw(); 
                    $redirect = wc_get_checkout_url();                   
                }
                elseif($order->status == 'fail' or $order->status == 'pending')
                {
                    $redirect = wc_get_checkout_url();
                    // $redirect = home_url('checkout/order-pay/' . $order->get_id() . '/?key=' . $order->get_order_key());
                }
                else
                {
                    // $redirect = home_url('checkout/order-pay/' . $order->get_id() . '/?key=' . $order->get_order_key());
                    $redirect = wc_get_checkout_url();
                }
          }



            wp_redirect($redirect);
        }

        /**
         * @param string $data
         * @return bool|WC_Order
         */
        private function get_order_from_response($data = "")
        {
            if (empty($data)) return false;
            if (!isset($data->txID)) return false;
            $order_id_time = (string)$data->txID;
            $order_id = explode('_', $order_id_time);
            $order_id = (int)str_replace($this->api_unique_id, '', $order_id[0]);
            $order = wc_get_order($order_id);
            if (empty($order)) return false;
            return $order;
        }

        /**
         * Generate ShurjoPay button link
         * @param $order
         * @return null
         */
        private function generate_shurjopay_form($order)
        {
            if (empty($order)) return null;
            if (!is_object($order)) {
                $order = new WC_Order($order);
            }
            
            $totalamount=$order->get_total();
            $currency = get_woocommerce_currency();
            if($currency=="BDT")
            {
                $totalamount=$order->get_total();
            }
            else
            {
                $totalamount=($order->get_total()*85);
            }
				$shipping_first_name = $order->get_billing_first_name();
				$shipping_last_name  = $order->get_billing_last_name();
				
				$shipping_address_1  = $customer->get_billing_address_1();
				$shipping_address_2  = $customer->get_billing_address_2();
				$shipping_order   = $order->get_billing_phone();
				$shipping_email   = $order->get_billing_email();


            $uniq_transaction_key = $this->api_unique_id . $order->get_id() . '_' . date("ymds");
            $payload = 'spdata=<?xml version="1.0" encoding="utf-8"?>
                            <shurjoPay><merchantName>' . $this->api_login . '</merchantName>
                            <merchantPass>' . $this->trans_key . '</merchantPass>
                            <userIP>' . $this->api_ip . '</userIP>
                            <uniqID>' . $uniq_transaction_key . '</uniqID>
                            <totalAmount>' . $totalamount . '</totalAmount>
                            <paymentOption>shurjopay</paymentOption>
							<custom1>' . $shipping_first_name." ".$shipping_last_name . '</custom1>
							<custom2>' . $shipping_order . '</custom2>
							<custom3>' . $shipping_email . '</custom3>
							<custom4>' . $shipping_address_1." ".$shipping_address_2 . '</custom4>

                            <returnURL>' . $this->api_return_url . '</returnURL></shurjoPay>';
            $response = $this->shurjopay_submit_data($this->gw_api_url, $payload);
            print_r($response);
            exit;
        }

        /**
         * @param string $data
         * @return bool|SimpleXMLElement
         */
        private function decrypt_and_validate($data = "")
        {
            if (empty($data)) return false;
            $decryptValues = $this->decrypt($data);
            if (empty($decryptValues)) return false;
            $decryptValues = simplexml_load_string($decryptValues) or die("Error: Cannot create object");
            //var_dump($decryptValues);exit;
            if (!$decryptValues) return false;
            if (!isset($decryptValues->txID) || empty($decryptValues->txID)) return false;
            if (!isset($decryptValues->bankTxStatus) || empty($decryptValues->bankTxStatus)) return false;
            if (!isset($decryptValues->spCode) || empty($decryptValues->spCode)) return false;
            //if (!isset($decryptValues->txnAmount) || empty($decryptValues->txnAmount)) return false;
            //var_dump($decryptValues);exit;
            return $decryptValues;
        }

        /**
         * Decrypt response
         * @param string $encryptedText
         * @return bool|null|string
         */
        private function decryptDeprecated($encryptedText = "")
        {
            if (empty($encryptedText)) return null;
            $url = $this->decrypt_url . '?data=' . $encryptedText;
            return file_get_contents($url);
        }

        private function decrypt($encryptedText = "")
        {
            if (empty($encryptedText)) return null;
            $url = $this->decrypt_url . '?data=' . $encryptedText;
            $ch = curl_init();  
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response_decrypted = curl_exec($ch);
            curl_close ($ch);
            //var_dump($response_decrypted);exit;
            return $response_decrypted;            
        }

        /**
         * Submit Gateway Data
         * @param string $url
         * @param string $postFields
         * @return mixed|null
         */
        private function shurjopay_submit_data($url = "", $postFields = "")
        {
            if (empty($url) || empty($postFields)) return null;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
    }
}
