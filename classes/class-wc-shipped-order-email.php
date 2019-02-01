<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A custom Expedited Order WooCommerce Email class
 *
 * @since 0.1
 * @extends \WC_Email
 */
class Printaura_WC_Shipped_Order_Email extends WC_Email {


	/**
	 * Set email defaults
	 *
	 * @since 0.1
	 */
	public function printaura_construct() {

		// set ID, this simply needs to be a unique name
		$this->id = 'wc_shipped_order';

		// this is the title in WooCommerce Email settings
		$this->title = 'Shipping Emails';

		// this is the description in WooCommerce email settings
		$this->description = 'Shipped Item Order Notification emails are sent when an item is marked as shipped';

		// these are the default heading and subject lines that can be overridden using the settings
		$this->heading = 'Ship Notification: #{ORDERNUMBER}';
		$this->subject = 'Ship Notification: #{ORDERNUMBER}';

               add_action( 'woocommerce_order_status_shipped', array( $this, 'trigger' ) );
               // Call parent constructor to load any other defaults not explicity defined here
		parent::__construct();

		// this sets the recipient to the settings defined below in init_form_fields()
		$this->recipient = $this->get_option( 'recipient' );

		// if none was entered, just use the WP admin email as a fallback
		if ( ! $this->recipient )
			$this->recipient = get_option( 'admin_email' );
	}


	/**
	 * Determine if the email should actually be sent and setup email merge variables
	 *
	 * @since 0.1
	 * @param int $order_id
	 */
	public function printaura_trigger( $order,$tracking_number="",$tracking_method="") {
          

		// setup order object
                if(is_numeric($order)){
		$this->object = new WC_Order( $order );
                $this->recipient	= $this->object->billing_email;

	        $this->find[] = '{ORDERNUMBER}';
		$this->replace[] = $this->object->get_order_number();

		if ( ! $this->is_enabled() || ! $this->get_recipient() )
			return;
                //$body = $this->get_body();
                ob_start();
                $content="";
                $content .= do_action( 'woocommerce_email_header',  $this->get_heading() );
                $content1=ob_get_clean();
                $data=get_object_vars($this);
                $content1 .=$data['settings']['body'];
                ob_start();
                do_action( 'woocommerce_email_footer' );
                $content1 .=ob_get_clean();
                $shipping_address=$this->object->shipping_company." ".$this->object->shipping_first_name." ".$this->object->shipping_last_name."<br />";
                $shipping_address .=$this->object->shipping_address_1.'<br />';
                $shipping_address .=($this->object->shipping_address_2 !="") ? $this->object->shipping_address_2.'<br />' : "";
                $shipping_address .= $this->object->shipping_city.", ".$this->object->shipping_state." ".$this->object->shipping_postcode."<br />";
                $shipping_address .= $this->object->shipping_country;
                $sh_address = $this->object->get_formatted_shipping_address();
                //{CLIENT PRODUCT TITLE} - {COLOR} / {SIZE} (Qty: {QTY})
                $Allitems='';
                foreach ($this->object->get_items() as $item) {
 $qty = ($item['qty'] !="") ? $item['qty'] : $item['quantity'];
                    $Allitems.=$item['name'].' - '.str_replace('/','-',$item['color']).' / '.$item['size'].' (Qty: '.$qty.')'."<br />";
                }
                
                $content1 = str_replace(array('{CustomerFirstName}','{ORDERNUMBER}','{SHIP_ADDRESS}','{SHIP ADDRESS}','{CLIENT PRODUCT TITLE} - {COLOR} / {SIZE} (Qty: {QTY})','{TRACKINGNUMBER}','{SHIP_METHOD}','{SHIP METHOD}'), array($this->object->billing_first_name,$this->object->get_order_number(),$shipping_address,$shipping_address,$Allitems,$tracking_number,$tracking_method,$tracking_method), $content1);
                
                
                }
                else if(is_array($order)) {
                    $this->object = new WC_Order( $order['order_id'] );
                $this->recipient	= $this->object->billing_email;

	        $this->find[] = '{ORDERNUMBER}';
		$this->replace[] = $this->object->get_order_number();

		if ( ! $this->is_enabled() || ! $this->get_recipient() )
			return;
                //$body = $this->get_body();
                ob_start();
                $content="";
                $content .= do_action( 'woocommerce_email_header',  $this->get_heading() );
                $content1=ob_get_clean();
                $data=get_object_vars($this);
                $content1 .=$data['settings']['body'];
                ob_start();
                do_action( 'woocommerce_email_footer' );
                $content1 .=ob_get_clean();
                $shipping_address=$this->object->shipping_company." ".$this->object->shipping_first_name." ".$this->object->shipping_last_name."<br />";
                $shipping_address .=$this->object->shipping_address_1.'<br />';
                $shipping_address .=($this->object->shipping_address_2 !="") ? $this->object->shipping_address_2.'<br />' : "";
                $shipping_address .= $this->object->shipping_city.", ".$this->object->shipping_state." ".$this->object->shipping_postcode."<br />";
                $shipping_address .= $this->object->shipping_country;
                $sh_address = $this->object->get_formatted_shipping_address();
                //{CLIENT PRODUCT TITLE} - {COLOR} / {SIZE} (Qty: {QTY})
                $Allitems='';
                foreach ($order['items'] as $item) {
                    $Allitems.=$item['title'].' - '.str_replace('/','-',$item['color']).' / '.$item['size'].' (Qty: '.$item['quantity'].')'."<br />";
                }
                
                $content1 = str_replace(array('{CustomerFirstName}','{ORDERNUMBER}','{SHIP_ADDRESS}','{SHIP ADDRESS}','{CLIENT PRODUCT TITLE} - {COLOR} / {SIZE} (Qty: {QTY})','{TRACKINGNUMBER}','{SHIP_METHOD}','{SHIP METHOD}'), array($this->object->billing_first_name,$this->object->get_order_number(),$shipping_address,$shipping_address,$Allitems,$order['TrackingNumber'],$order['TrackingMethod'],$order['TrackingMethod']), $content1);
                
                }
                // woohoo, send the email!
		$this->send( $this->get_recipient(), $this->get_subject(), $content1, $this->get_headers(), $this->get_attachments() );
	}


	/**
	 * get_content_html function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function printaura_get_content_html() {
		ob_start();
		woocommerce_get_template( $this->template_html, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading()
		) );
		return ob_get_clean();
	}


	/**
	 * get_content_plain function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function printaura_get_content_plain() {
		ob_start();
		woocommerce_get_template( $this->template_plain, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading()
		) );
		return ob_get_clean();
	}


	/**
	 * Initialize Settings Form Fields
	 *
	 * @since 2.0
	 */
	public function printaura_init_form_fields() {

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable this email notification',
				'default' => 'yes'
			),
			'recipient'  => array(
				'title'       => 'Recipient(s)',
				'type'        => 'text',
				'description' => sprintf( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', esc_attr( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => ''
			),
			'subject'    => array(
				'title'       => 'Subject',
				'type'        => 'text',
				'description' => sprintf( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', $this->subject ),
				'placeholder' => '',
				'default'     => ''
			),
			'heading'    => array(
				'title'       => 'Email Heading',
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.' ), $this->heading ),
				'placeholder' => '',
				'default'     => ''
			),
                       'body'    => array(
				'title'       => 'Email Body',
				'type'        => 'textarea',
				'description' => sprintf( __( 'This controls the main body contained within the email notification.' ), $this->heading ),
				'placeholder' => '',
				'default'     => '<p>Dear {CustomerFirstName},</p>
<p>The following items have shipped from order #{ORDERNUMBER}</p>

{CLIENT PRODUCT TITLE} - {COLOR} / {SIZE} (Qty: {QTY})
</p>
<p>
The order has been shipped to:
</p>
<p>
{SHIP_ADDRESS}
</p>
<p>via {SHIP_METHOD} (Tracking #: {TRACKINGNUMBER} )</p>
<p>Please note that it may take until the next business day before tracking becomes available.</p>

Thanks for your business.'
			),
			'email_type' => array(
				'title'       => 'Email type',
				'type'        => 'select',
				'description' => 'Choose which format of email to send.',
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'	    => __( 'Plain text', 'woocommerce' ),
					'html' 	    => __( 'HTML', 'woocommerce' ),
					'multipart' => __( 'Multipart', 'woocommerce' ),
				)
			)
		);
	}


} // end \WC_Expedited_Order_Email class

