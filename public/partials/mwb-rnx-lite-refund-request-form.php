<?php  
/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed = true;

//Product Return request form
$current_user_id = get_current_user_id();   //check user is logged in or not

if(!wp_verify_nonce( $_REQUEST['ced-rnx-nonce'], 'ced-rnx-nonce' ) || !current_user_can('ced-rnx-refund-request'))
{
	$allowed = false;
}

if($allowed)
{
	$subject = "";
	$reason = "";
	if(isset($_POST['order_id']))
	{
		$order_id = sanitize_text_field($_POST['order_id']);
	}
	elseif (isset($_GET['order_id'])) {
		$order_id = sanitize_text_field($_GET['order_id']);
	}
	else
	{
		$order_id = 0;
	}
	
	//check order id is valid
	if($order_id == 0 || $current_user_id == 0)
	{
		$allowed = false;
	}
	
	if(!is_numeric($order_id))
	{
		
		if(get_current_user_id() > 0)
		{
			$myaccount_page = get_option( 'woocommerce_myaccount_page_id' );
			$myaccount_page_url = get_permalink( $myaccount_page );
		}
		$allowed = false;
		$reason = __('Please choose an Order.','woocommerce-refund-and-exchange-lite').'<a href="'.$myaccount_page_url.'">'.__('Click Here','woocommerce-refund-and-exchange-lite').'</a>';
		$reason = apply_filters('ced_rnx_return_choose_order', $reason);
	}
	else 
	{
		$order_customer_id = get_post_meta($order_id, '_customer_user', true);
		
		if($current_user_id > 0) // check order associated to customer account or not for registered user
		{			
			if($order_customer_id != $current_user_id)
			{
				$myaccount_page = get_option( 'woocommerce_myaccount_page_id' );
				$myaccount_page_url = get_permalink( $myaccount_page );
				$allowed = false;
				$reason = __("This order #$order_id is not associated to your account. <a href='$myaccount_page_url'>Click Here</a>",'woocommerce-refund-and-exchange-lite' );
				$reason = apply_filters('ced_rnx_return_choose_order', $reason);
			}			
		}
	}	
	
	if($allowed)
	{
		if($allowed)
		{
			$order = wc_get_order($order_id);
			//Check enable return
			$return_enable = get_option('ced_rnx_return_enable', false);
			
			if(isset($return_enable) && !empty($return_enable))
			{
				if($return_enable == 'yes')
				{
					$allowed = true;
				}
				else
				{
					$allowed = false;
					$reason = __('Refund request is disabled.','woocommerce-refund-and-exchange-lite' );
					$reason = apply_filters('ced_rnx_return_order_amount', $reason);
				}
			}
			else
			{
				$allowed = false;
				$reason =  __('Refund request is disabled.','woocommerce-refund-and-exchange-lite' );
				$reason = apply_filters('ced_rnx_return_order_amount', $reason);
			}
			
			$products = get_post_meta($order_id, 'ced_rnx_return_product', true);
			
			//Get pending return request
			if(isset($products) && !empty($products))
			{
				foreach($products as $date=>$product)
				{
					if($product['status'] == 'pending')
					{
						$subject = $products[$date]['subject'];
						$reason = $products[$date]['reason'];
						$product_data = $product['products'];
					}
				}	
			}
			$order = wc_get_order($order_id);
			$items = $order->get_items();
			if( WC()->version < "3.0.0" )
			{
				$order_date = date_i18n('F d, Y', strtotime( $order->order_date  ) );
			}
			else
			{
				$order_date = date_i18n( 'F d, Y', strtotime( $order->get_date_created()  ) );
			}
			$today_date = date_i18n( 'F d, Y' );
	    	$order_date = strtotime($order_date);
	    	$today_date = strtotime($today_date);
			
			$days = $today_date - $order_date;
			$day_diff = floor($days/(60*60*24));
			$day_allowed = get_option('ced_rnx_return_days', false);  //Check allowed days
			
			
				if($day_allowed >= $day_diff && $day_allowed != 0)
				{
					$allowed = true;
				}
				else
				{
					$allowed = false;
					$reason =  __('Days exceed.','woocommerce-refund-and-exchange-lite' );
					$reason = apply_filters('ced_rnx_return_day_exceed', $reason);
				}
			
			if($allowed)
			{
				$order = wc_get_order( $order_id );
				$order_total = $order->get_total();
				

				if($allowed)
				{
					$statuses = get_option('ced_rnx_return_order_status', array());
					$order_status ="wc-".$order->get_status();
					if(!in_array($order_status, $statuses))
					{
						$allowed = false;
						$reason =  __('Update Refund request is disabled.','woocommerce-refund-and-exchange-lite' );
						$reason = apply_filters('ced_rnx_return_order_amount', $reason);
					}
				}
			}
		}
	}
}
get_header( 'shop' );

/**
 * woocommerce_before_main_content hook.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 */
do_action( 'woocommerce_before_main_content' );

if($allowed)
{
	$show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
	$show_customer_details = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
	
	
	?>
	<div class="woocommerce woocommerce-account ced_rnx_refund_form_wrapper">
		<div id="ced_rnx_return_request_form_wrapper">
			<div id="ced_rnx_return_request_container">
				<h1>
				<?php 
					 _e('Order Refund Request Form','woocommerce-refund-and-exchange-lite' );

				?>
				</h1>
			</div>
			<ul class="woocommerce-error" id="ced-return-alert" >
			</ul>
			<div class="ced_rnx_product_table_wrapper" >
				<table class="shop_table order_details ced_rnx_product_table">
					<thead>
						<tr>
							<th class="product-name"><?php _e( 'Product', 'woocommerce-refund-and-exchange-lite' ); ?></th>
							<th class="product-qty"><?php _e( 'Quantity', 'woocommerce-refund-and-exchange-lite' ); ?></th>
							<th class="product-total"><?php _e( 'Total', 'woocommerce-refund-and-exchange-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$ced_rnx_in_tax = get_option('ced_rnx_return_tax_enable', false);
						$in_tax = false;
						if($ced_rnx_in_tax == 'yes')
						{
							$in_tax = true;	
						}	
						$mwb_total_actual_price = 0;
						foreach( $order->get_items() as $item_id => $item ) 
						{

							if($item['qty'] > 0)
							{	
								if(isset($item['variation_id']) && $item['variation_id'] > 0)
								{
									$variation_id = $item['variation_id'];
									$product_id = $item['product_id'];
								}
								else
								{
									$product_id = $item['product_id'];
								}

				
								if($day_allowed >= $day_diff && $day_allowed != 0)
								{
									$show = true;
								}
								else
								{
									$show = false;
								}
									
								$product = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
								$thumbnail     = wp_get_attachment_image($product->get_image_id(),'thumbnail');
								$productdata = wc_get_product($product_id);
								
								$ced_product_total = $order->get_line_subtotal( $item, $in_tax );
								$ced_product_qty = $item['qty'];
								$ced_per_product_price = 0;
								if($ced_product_qty > 0)
								{
									$ced_per_product_price = $ced_product_total / $ced_product_qty;
								}
								$purchase_note = get_post_meta( $product_id, '_purchase_note', true );
							
								?>
								<tr class="ced_rnx_return_column" data-productid="<?php echo $product_id?>" data-variationid="<?php echo $item['variation_id']?>" data-itemid="<?php echo $item_id?>">
									<?php if($show)
									{
									  
										$mwb_actual_price = $order->get_item_total( $item, $in_tax );
										$mwb_total_price_of_product = $item['qty']*$mwb_actual_price;
										$mwb_total_actual_price += $mwb_total_price_of_product;
									} ?>
									 
									<td class="product-name">
									<?php
										$is_visible        = $product && $product->is_visible();
										$product_permalink = apply_filters( 'woocommerce_order_item_permalink', $is_visible ? $product->get_permalink( $item ) : '', $item, $order );
										
										if(isset($thumbnail) && !empty($thumbnail))
										{	
											echo  wp_kses_post( $thumbnail );
										}
										else
										{
											?>
											<img alt="Placeholder" width="150" height="150" class="attachment-thumbnail size-thumbnail wp-post-image" src="<?php echo plugins_url();?>/woocommerce/assets/images/placeholder.png">
											<?php 
										}	
										
										
										?>
										<div class="ced_rnx_product_title">
										<?php 
										echo apply_filters( 'woocommerce_order_item_name', $product_permalink ? sprintf( '<a href="%s">%s</a>', $product_permalink, $item['name'] ) : $item['name'], $item, $is_visible );
										echo apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times; %s', $item['qty'] ) . '</strong>', $item );
										
										do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order );
										if( WC()->version < "3.0.0" )
										{
											$order->display_item_meta( $item );
											$order->display_item_downloads( $item );
										}
										else
										{
											wc_display_item_meta( $item );
											wc_display_item_downloads( $item );
										}
										do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order );
										?>
										<p>
											<input type="hidden" name="ced_rnx_product_amount" class="ced_rnx_product_amount" value="<?php echo $mwb_actual_price; ?>">
											<b><?php _e( 'Price', 'woocommerce-refund-and-exchange-lite' ); ?> :</b> <?php 
											 echo wc_price( $mwb_actual_price ); 
											 if($in_tax == true)
											 {	
											 ?>
												<small class="tax_label"><?php _e('(incl. tax)','woocommerce-refund-and-exchange-lite'); ?></small>
											<?php 
											 }	
											?>	
										</p>
										</div>
									</td>
									<td class="product-quantity">
									<?php echo sprintf( '<input type="number" disabled value="'.$item['qty'].'" class="ced_rnx_return_product_qty form-control" name="ced_rnx_return_product_qty">' );?>
									</td>
									<td class="product-total">
										<?php echo wc_price( $mwb_total_price_of_product );

										 if($in_tax == true)
											 {	
											 ?>
												<small class="tax_label"><?php _e('(incl. tax)','woocommerce-refund-and-exchange-lite'); ?></small>
											<?php 
											 }	
											 ?>
								  	<input type="hidden" id="quanty" value="<?php echo $item['qty']; ?>"> 
									</td>
								</tr>
								<?php if ( $show_purchase_note && $purchase_note ) : ?>
								<tr class="product-purchase-note">
									<td colspan="3"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); ?></td>
								</tr>
								<?php endif; 
							}
						}
							?>
							<tr>
								<th scope="row" colspan="2"><?php _e('Total Refund Amount', 'woocommerce-refund-and-exchange-lite') ?></th>
								<td class="ced_rnx_total_amount_wrap"><span id="ced_rnx_total_refund_amount"><?php echo wc_price($mwb_total_actual_price);?></span>
								<input type="hidden" name="ced_rnx_total_refund_price" class="ced_rnx_total_refund_price" value="<?php echo $mwb_total_actual_price ?>">
									<?php 
									if($in_tax == true)
									{	
									?>
										<small class="tax_label"><?php _e( '(incl. tax)', 'woocommerce-refund-and-exchange-lite' ); ?></small>
									<?php 
									}	
									?>
								</td>
							</tr>
					</tbody>
				</table>
				<div class="ced_rnx_return_notification_checkbox"><img src="<?php echo MWB_REFUND_N_EXCHANGE_LITE_URL?>public/images/loading.gif" width="40px"></div>
			</div>
			<hr/>
			<div class="ced_rnx_note_tag_wrapper">
			<p class="form-row form-row form-row-wide">
				<label>
					<b>
						<?php 
							$subject_return_request = __('Subject of Refund Request :', 'woocommerce-refund-and-exchange-lite' );
							echo apply_filters('ced_rnx_return_request_subject', $subject_return_request);
						?>
					</b>
				</label>
				
				<?php 
				$predefined_return_reason = get_option('ced_rnx_return_predefined_reason', array());
				if(isset($predefined_return_reason) && !empty($predefined_return_reason))
				{	
				?>
				<div class="ced_rnx_subject_dropdown">
					<select name="ced_rnx_return_request_subject" id="ced_rnx_return_request_subject">
						<?php 
						foreach($predefined_return_reason as $predefine_reason)
						{
							?>
							<option value="<?php echo $predefine_reason?>"><?php echo $predefine_reason?></option>
							<?php 
						}
						?>
						<option value=""><?php _e( 'Other', 'woocommerce-refund-and-exchange-lite' )?></option>
					</select>
				</div>
				<?php 
				}
				?>
			</p>
			<p class="form-row form-row form-row-wide">
				<input type="text" name="ced_rnx_return_request_subject" class="input-text ced_rnx_return_request_subject" id="ced_rnx_return_request_subject_text" placeholder="<?php _e('Write your reason subject','woocommerce-refund-and-exchange-lite');?>">
			</p>
			
			<?php 
			$predefined_return_desc = get_option('ced_rnx_return_request_description', false);
			if(isset($predefined_return_desc))
			{	
				if($predefined_return_desc == 'yes')
				{
					?>
					<p class="form-row form-row form-row-wide">
						<label>
							<b>
								<?php 
									$reason_return_request = __('Reason of Refund Request', 'woocommerce-refund-and-exchange-lite' );
									echo apply_filters('ced_rnx_return_request_reason', $reason_return_request);
								?>
							</b>
						</label>
						<br/>
						<?php $placeholder = get_option( 'ced_rnx_return_placeholder_text' , 'Reason for Return Request' ); 
						if ($placeholder == '') {
						 	$placeholder =__('Reason for the Refund Request','woocommerce-refund-and-exchange-lite');
						 }
						 ?>
						<textarea name="ced_rnx_return_request_reason" cols="40" style="height: 222px;" class="ced_rnx_return_request_reason form-control" placeholder="<?php echo $placeholder; ?>"><?php echo $reason;?></textarea>
					</p>
					<?php 
				}
				else
				{
					?>
					<input type="hidden" name="ced_rnx_return_request_reason" class="ced_rnx_return_request_reason form-control" value="<?php _e('No Reason Enter', 'woocommerce-refund-and-exchange-lite' )?>">
					<?php 				
				}	
			}
			else 
			{
				?>
				<input type="hidden" name="ced_rnx_return_request_reason" class="ced_rnx_return_request_reason form-control" value="<?php _e('No Reason Enter', 'woocommerce-refund-and-exchange-lite' )?>">
				<?php 				
			}
			?>
			
			<form action="" method="post" id="ced_rnx_return_request_form" data-orderid="<?php echo $order_id;?>" enctype="multipart/form-data">
				<?php 
				$return_attachment = get_option('ced_rnx_return_attach_enable', false);
				if(isset($return_attachment) && !empty($return_attachment))
				{	
					if($return_attachment == 'yes')
					{
						?>
						<label><b><?php _e('Attach Files', 'woocommerce-refund-and-exchange-lite');?></b></label>
						<p class="form-row form-row form-row-wide">
							<span id="ced_rnx_return_request_files">
							<input type="hidden" name="ced_rnx_return_request_order" value="<?php echo $order_id;?>">
							<input type="hidden" name="action" value="<?php _e('ced_rnx_refund_upload_files', 'woocommerce-refund-and-exchange-lite');?>">
							<input type="file" name="ced_rnx_return_request_files[]" class="input-text ced_rnx_return_request_files"></span>
							<input type="button" value="<?php _e('Add More', 'woocommerce-refund-and-exchange-lite');?>" class="btn button ced_rnx_return_request_morefiles">
							<i><?php _e('Only .png, .jpeg extension file is approved.', 'woocommerce-refund-and-exchange-lite' )?></i>
						</p>
						<?php 
					}
				}?>
				<p class="form-row form-row form-row-wide">
					<input type="submit" name="ced_rnx_return_request_submit" value="<?php _e('Submit Request', 'woocommerce-refund-and-exchange-lite');?>" class="button btn">
					<div class="ced_rnx_return_notification"><img src="<?php echo MWB_REFUND_N_EXCHANGE_LITE_URL?>public/images/loading.gif" width="40px"></div>
				</p>
			</form>
			<br/>
			</div>
			<div class="ced-rnx_customer_detail">
				<?php 
				wc_get_template( 'order/order-details-customer.php', array( 'order' =>  $order ) ); 
				?>
			</div>
		</div>
	</div>	
	<?php 
}
else 
{
	$return_request_not_send = __('Refund Request can\'t be send. ', 'woocommerce-refund-and-exchange-lite' );
	$reason = apply_filters('ced_rnx_return_request_not_send', $return_request_not_send);
	echo $reason;
}	
/**
 * woocommerce_after_main_content hook.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action( 'woocommerce_after_main_content' );

/**
 * woocommerce_sidebar hook.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
?>