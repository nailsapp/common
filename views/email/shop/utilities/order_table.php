<table class="default-style">
	<thead>
		<tr>
			<th>Item</th>
			<th class="center">Quantity</th>
			<th class="center">Unit Price</th>
			<th class="center">Tax Rate</th>
			<?php if ( $order->requires_shipping ) :?>
			<th class="center">Shipping</th>
			<?php endif; ?>
			<th class="center">Total</th>
		</tr>
	</thead>
	<tbody>
	<?php
	
		foreach ( $order->items AS $item ) :
		
			echo '<tr class="line-bottom">';
			echo '<td>';
			echo $item->title;
			echo '<small>' . $item->type->label . '; Product ID: ' . $item->product_id . '</small>';
			echo '</td>';
			echo '<td class="center">' . $item->quantity . '</td>';
			
			if ( $item->was_on_sale ) :
			
				echo '<td class="center">' . $order->currency->order->symbol . number_format( $item->sale_price, $order->currency->order->precision ) . '</td>';
				
			else :
			
				echo '<td class="center">' . $order->currency->order->symbol . number_format( $item->price, $order->currency->order->precision ) . '</td>';
			
			endif;
			
				echo '<td class="center">' . $item->tax_rate->rate *100 . '%</td>';
			
			if ( $order->requires_shipping ) :

				if ( $item->shipping ) :
				 
					echo '<td class="center">' . $order->currency->order->symbol . number_format( $item->shipping, $order->currency->order->precision ) . '</td>';
					
				else :
				
					echo '<td class="center">FREE</td>';
				
				endif;

			endif;

			echo '<td class="center">' . $order->currency->order->symbol . number_format( $item->total, $order->currency->order->precision ) . '</td>';

			echo '</tr>';
		
		endforeach;
	
	?>
	
	<tr>
		<td colspan="4" class="right"><strong>Sub Total</strong></td>
		<?php if ( $order->requires_shipping ) : ?>
		<td class="center"><?=$order->currency->order->symbol . number_format( $order->totals->shipping, $order->currency->order->precision )?></td>
		<?php endif; ?>
		<td class="center"><?=$order->currency->order->symbol . number_format( $order->totals->sub, $order->currency->order->precision )?></td>
	</tr>
	<tr>
		<td colspan="4" class="right"><strong>Tax</strong></td>
		<?php if ( $order->requires_shipping ) : ?>
		<td class="center"><?=$order->currency->order->symbol . number_format( $order->totals->tax_shipping, $order->currency->order->precision )?></td>
		<?php endif; ?>
		<td class="center"><?=$order->currency->order->symbol . number_format( $order->totals->tax_items, $order->currency->order->precision )?></td>
	</tr>
	<?php

		if ( $order->discount->shipping || $order->discount->items ) :

			echo '<tr>';
			echo '<td colspan="4" class="right"><strong>Discounts</strong></td>';
			if ( $order->requires_shipping && $order->discount->shipping ) :

				echo '<td class="center">' . $order->currency->order->symbol . number_format( $order->discount->shipping, $order->currency->order->precision ) . '</td>';

			elseif( $order->requires_shipping ) :

				echo '<td class="center">&mdash;</td>';

			endif;

			if ( $order->discount->items ) :

				echo '<td class="center">' . $order->currency->order->symbol . number_format( $order->discount->items, $order->currency->order->precision ) . '</td>';

			else :

				echo '<td class="center">&mdash;</td>';

			endif;
			echo '</tr>';

		endif;

	?>
	<tr>
		<td colspan="4" class="right"><strong>Grand Total</strong></td>
		<?php if ( $order->requires_shipping ) : ?>
		<td class="center">&nbsp;</td>
		<?php endif; ?>
		<td class="center"><?=$order->currency->order->symbol . number_format( $order->totals->grand, $order->currency->order->precision )?></td>
	</tr>
	</tbody>
</table>

<?php

	if ( $order->voucher ) :

		?>
		<p>
			The following voucher was used with this order:
		</p>
		<p class="heads-up">
			<strong style="padding-right:15px;margin-right:10px;border-right:1px solid #CCC"><?=$order->voucher->code?></strong><?=$order->voucher->label?>
		</p>
		<?php

	endif;

	// --------------------------------------------------------------------------

	if ( $order->requires_shipping ) :

		if ( $type == 'receipt' ) :

			echo '<p>The items in your order which require shipping will be shipped to the following address:</p>';

		elseif ( $type == 'notification' ) :

			echo '<p>The items in the order which require shipping must be shipped to the following address:</p>';

		endif;

		?>
		<table style="width:100%;padding:10px;border:1px solid #CCC;">
			<tr>
				<td>
					<ul style="margin:0;">
						<li style="font-size:1.2em;"><strong><?=$order->shipping_details->addressee?></strong></li>
						<?=$order->shipping_details->line_1 ? '<li>' . $order->shipping_details->line_1 . '</li>' : '' ?>
						<?=$order->shipping_details->line_2 ? '<li>' . $order->shipping_details->line_2 . '</li>' : '' ?>
						<?=$order->shipping_details->town ? '<li>' . $order->shipping_details->town . '</li>' : '' ?>
						<?=$order->shipping_details->postcode ? '<li>' . $order->shipping_details->postcode . '</li>' : '' ?>
						<?=$order->shipping_details->country ? '<li>' . $order->shipping_details->country . '</li>' : '' ?>
						<?=$order->shipping_details->state ? '<li>' . $order->shipping_details->state . '</li>' : '' ?>
					</ul>
				</td>
				<td align="right" valign="top">
					<?=img( NAILS_URL . 'img/modules/shop/email/post-mark.png' )?>
				</td>
			</tr>
		</table>
		<?php

		if ( $type == 'receipt' ) :

			$_track_token = urlencode( $this->encrypt->encode( $order->ref . '|' . $order->id . '|' . time(), APP_PRIVATE_KEY ) );

			echo '<p>';
			echo 'They will be shipped using <strong>' . $order->shipping_method->courier . ' - ' . $order->shipping_method->method . '</strong>; you can also ';
			echo anchor( shop_setting( 'shop_url' ) . 'order/track?token=' . $_track_token , 'track the status of your order' ) . '.';
			echo '</p>';

		elseif ( $type == 'notification' ) :

			echo '<p>';
			echo 'They must be shipped using <strong>' . $order->shipping_method->courier . ' - ' . $order->shipping_method->method . '</strong>.';
			echo '</p>';

		endif;

	
	endif;

?>