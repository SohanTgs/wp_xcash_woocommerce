<?php

class xCashGateway extends WC_Payment_Gateway{

    public function __construct()
    {   
        $this->id = strtolower(xcash_title_to_key(XCASH_PLUGIN_NAME)).'_gateway';
        $this->alias = strtolower(xcash_title_to_key(XCASH_PLUGIN_NAME));
        $this->method_title = XCASH_PLUGIN_NAME;

        $this->init_form_fields();
        $this->init_settings();
        
        $this->icon = XCASH_PLUGIN_URL.'/logo.png';

        $this->has_fields = false;

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->method_description = $this->get_option('description');
        
        $this->success_page_id = $this->get_option('success_page_id');
        $this->cancel_page_id = $this->get_option('cancel_page_id');

        add_action('woocommerce_receipt_'.$this->id, array($this, 'payment_init')); 
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        if (!defined('XCASH_CALLBACK_URL')) {
            define('XCASH_CALLBACK_URL', WC()->api_request_url('wc_'.$this->id));
        }
        add_action('woocommerce_api_wc_'.$this->id, array($this, 'wc_ipn'));
        
        if(!@$this->settings['public_key'] || !@$this->settings['secret_key'] || !@$this->settings['payment_mode']){
            add_action('admin_notices', 'account_add_notice');
        }

    }

    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function init_form_fields()
    {
        $fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Payment Module.',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title:',
                'type' => 'text',
                'default' => XCASH_PLUGIN_NAME
            ),
            'public_key' => array(
                'title' => 'Public Key',
                'type' => 'text'
            ),
            'secret_key' => array(
                'title' => 'Secret Key',
                'type' => 'text'
            ),
        );
        $this->form_fields = array_merge($fields, array(
            'payment_mode' => array(
                'title' => 'Payment Mode',
                'type' => 'select',
                'options' => [
                    '0'=>'Select One',
                    'sandbox'=>'Sandbox',
                    'live'=>'Live',
                ],
            ),
            'theme' => array(
                'title' => 'Theme',
                'type' => 'select',
                'options' => [
                    'dark'=>'Dark',
                    'light'=>'Light'
                ],
            ),
            'success_page_id' => array(
                'title' => 'Success Page',
                'type' => 'select',
                'options' => $this->get_pages('Select Page'),
                'description' => "URL of success page"
            ),
            'cancel_page_id' => array(
                'title' => 'Cancel Page',
                'type' => 'select',
                'options' => $this->get_pages('Select Page'),
                'description' => "URL of cancel page"
            )
        ));
    }
    
    public function process_admin_options()
    {
        $this->init_settings();

        $post_data = $this->get_post_data();
        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                try {
                    $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                } catch (Exception $e) {
                    $this->add_error($e->getMessage());
                }
            }
        }
        
        return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
    }

    public function payment_init($order)
    {  
        $publicKey = @$this->settings['public_key'];   
        $secretKey = @$this->settings['secret_key']; 
        $paymentMode = @$this->settings['payment_mode']; 
        $theme = @$this->settings['theme']; 
         
        if(!$publicKey || !$secretKey || !$paymentMode || !$theme){
            echo '<ul style="background: #ff1b1b29;color: #ff1b1b;padding-top: 6px;padding-bottom: 6px;font-weight: 500;">
                    <li>Payment gateway configuratin is not complete yet!</strong></li>
            </ul>';
        }
     
        $order = wc_get_order($order); 
        $amount = $order->get_total();
        $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
        $productinfo = "WooCommerce Payment for Order ID: $order_id";

        $success_page = ($this->success_page_id == "" || $this->success_page_id == 0) ? get_site_url() . "/" : get_permalink($this->success_page_id);
        $cancel_page = ($this->cancel_page_id == "" || $this->cancel_page_id == 0) ? get_site_url() . "/" : get_permalink($this->cancel_page_id);
   
        $parameters = [
            'identifier' => $order_id,
            'currency' => get_woocommerce_currency(),
            'amount' => $amount,
            'details' => $productinfo,
            'ipn_url' => XCASH_CALLBACK_URL,
            'site_logo' => XCASH_CALLBACK_URL,
            'cancel_url' => $cancel_page,
            'success_url' => $success_page,
            'public_key' => $publicKey,
            'checkout_theme' => $theme,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
        ]; 

        $url = XCASH_API_ENDPOINT."/payment/initiate";

        if($paymentMode == 'sandbox'){
            $url = XCASH_API_ENDPOINT."/sandbox/payment/initiate";
        }
     
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $parameters);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($result);
       
        if (@$result->error == 'true' || @$result->error == true) { 

            $error = '<ul style="background: #ff1b1b29;color: #ff1b1b;padding-top: 6px;padding-bottom: 6px;font-weight: 500;">';
            $error .= "<li>$result->message</li>";
            $error .= '<ul>';
            
            echo $error;
        }else{
            xcashRedirect(@$result->url);
        }
      
    }

    public function wc_ipn()
    {   
        //Receive the response parameter
        $status = $_POST['status'];
        $signature = $_POST['signature'];
        $identifier = $_POST['identifier'];
        $data = $_POST['data'];

        // Generate your signature
        $customKey = $data['amount'].$identifier;
        $secret = $this->settings['secret_key'];
        $mySignature = strtoupper(hash_hmac('sha256', $customKey , $secret));

        $order = wc_get_order($identifier);

        if($status == "success" && $signature == $mySignature){
            $message = 'Payment Successful. Transaction Reference: ' . $identifier;

            // Add admin order note
            $order->add_order_note('Payment Via ' . $this->method_title . ' Transaction Reference: ' . $identifier);

            // Add customer order note
            $order->add_order_note('Payment Successful. Transaction Reference: ' . $identifier, 1);

            $order->payment_complete( $identifier );
            $order->reduce_order_stock();

            // Empty cart
            wc_empty_cart();
        }else{
            //process a failed transaction
            $message = 'Payment Failed. Reason: Hash not matched. Transaction Reference: ' . $identifier;

            //Add Customer Order Note
            $order->add_order_note($message, 1);

            //Add Admin Order Note
            $order->add_order_note($message);

            //Update the order status
            $order->update_status('failed', '');
        }
    }

    function get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}