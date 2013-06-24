<div class="sixteen columns first last row">
	<table>
		<thead>
			<tr>
				<th class="item">Item</th>
				<th class="quantity">Quantity</th>
				<th class="price">Unit Price</th>
				<th class="tax">Tax Rate</th>
				<?php if ( $basket->requires_shipping ) : ?>
				<th class="shipping">Shipping</th>
				<?php endif; ?>
				<th class="total">Total</th>
			</tr>
		</thead>
		<tbody>
		
			<!--	ITEMS	-->
			<?php
			
				$_i = 0;
				
				foreach ( $basket->items AS $key => $item ) :
				
					$_stripe = $_i % 2 ? 'odd' : 'even';
					$_i++;
					
					?>
					<tr data-product_id="<?=$item->id?>" data-key="<?=$key?>" class="<?=$_stripe?>">
						<td class="item">
						<?php

							//	Load the 'details' view; in a separate view so apps can easily customise the layout/content
							//	of this part of the view without having to duplicate the entire basket view.

							$this->load->view( 'shop/basket/basket-item-cell', array( 'item' => &$item ) );

						?>
						</td>
						<td class="quantity">
						<?php
						
							//	Decrement
							if ( ! isset( $no_changes ) || ! $no_changes ) :
							
								echo anchor( shop_setting( 'shop_url' ) . 'basket/decrement/' . $item->id, 'Decrement', 'class="decrement"' );
								
							endif;
							
							//	Quantity
							echo '<span class="value">' . $item->quantity . '</span>';
							
							//	Increment
							if ( ! isset( $no_changes ) || ! $no_changes ) :
							
								if ( ! isset( $item->type->max_per_order ) || is_null( $item->type->max_per_order ) || $item->quantity < $item->type->max_per_order ) :
								
									echo anchor( shop_setting( 'shop_url' ) . 'basket/increment/' . $item->id, 'Increment', 'class="increment"' );
									
								endif;
							
							endif;
							
						?>
						</td>
						<?php
						
							if ( $item->is_on_sale ) :
							
								echo '<td class="price on-sale">';
								echo '<span>' . shop_format_price( $item->sale_price, TRUE ) . '</span>';
								echo '<span class="ribbon"></span>';
								echo '<del>was ' . shop_format_price( $item->price, TRUE ) . '</del>';
								echo '</td>';
							
							else :
							
								echo '<td class="price">';
								echo shop_format_price( $item->price, TRUE );
								echo '</td>';
							
							endif;
							
						?>
						<td class="tax"><?=$item->tax_rate->label?></td>
						<?php
						
							if ( $basket->requires_shipping ) :

								if ( $item->type->requires_shipping && $item->shipping ) :
								
									echo '<td class="shipping">';
									echo shop_format_price( $item->shipping, TRUE );
									echo '</td>';
								
								elseif ( $item->type->requires_shipping && ! $item->shipping ) :
								
									echo '<td class="shipping free">';
									echo 'FREE';
									echo '</td>';
								
								else :

									echo '<td class="shipping free">';
									echo '&mdash;';
									echo '</td>';

								endif;

							endif;
							
						?>
						<td class="total"><?=shop_format_price( $item->total, TRUE )?></td>
					</tr>
					<?php
			
				endforeach;
				
			?>

			<!--	SHIPPING CHOOSER	-->
			<?php

				if ( $basket->requires_shipping ) :

					echo '<tr class="shipping-chooser">';
					echo '<td colspan="6">';

					if ( isset( $show_shipping_chooser ) && $show_shipping_chooser ) :


						echo form_open( shop_setting( 'shop_url' ) . 'basket/set_shipping_method' );
						echo 'Shipping method: ';
						echo '<select name="shipping_method" id="shipping-chooser">';

						$_notes = FALSE;

						foreach ( $shipping_methods AS $method ) :

							if ( $method->id == $basket->shipping_method ) :

								$_selected	= 'selected="selected"';
								$_notes		= $method->notes;

							else :

								$_selected = '';

							endif;
							echo '<option value="' . $method->id . '" ' . $_selected . '>' . $method->courier . ' - ' . $method->method . '</option>';

						endforeach;

						echo '</select>';

						echo '<noscript>';
						echo form_submit( 'submit', lang( 'action_update' ), 'class="awesome small"' );
						echo '</noscript>';

						if ( $_notes ) :

							echo '<small><strong>Please note:</strong> ' . $_notes . '</small>';

						endif;

						echo form_close();

					else :

						echo 'Shipping method: TODO TODO';

					endif;

					echo '</td>';
					echo '</tr>';

				endif;

			?>
			
			<!--	TOTALS	-->
			<tr class="total sub">
				<td class="label" colspan="4">Sub Total</td>
				<?php

					if ( $basket->requires_shipping ) :

						echo '<td class="value">';
						
						if ( $basket->totals->shipping ) :
						
							echo shop_format_price( $basket->totals->shipping, TRUE );
						
						else :
						
							echo 'FREE';
							
						endif;

						echo '</td>';

					endif;
					
				?>
				<td class="value">
				<?php
					
					if ( $basket->totals->sub ) :
					
						echo shop_format_price( $basket->totals->sub, TRUE );
					
					else :
					
						echo 'FREE';
						
					endif;
					
				?>
				</td>
			</tr>

			<tr class="total tax">
				<td class="label" colspan="4">TAX</td>
				<?php if ( $basket->requires_shipping ) : ?>
				<td class="value"><?=shop_format_price( $basket->totals->tax_shipping, TRUE )?></td>
			<?php endif; ?>
				<td class="value"><?=shop_format_price( $basket->totals->tax_items, TRUE )?></td>
			</tr>

			<?php if ( $basket->discount->shipping || $basket->discount->items ) : ?>
			<tr class="total discount">
				<td class="label" colspan="4">Discounts</td>
				<?php if ( $basket->requires_shipping ) : ?>
				<td class="value">
					<?php

						if ( $basket->discount->shipping ) :

							echo shop_format_price( $basket->discount->shipping, TRUE );

						else :

							echo '<span class="blank">&mdash;</span>';

						endif;

					?>
				</td>
				<?php endif; ?>
				<td class="value">
					<?php

						if ( $basket->discount->items ) :

							echo shop_format_price( $basket->discount->items, TRUE );

						else :

							echo '<span class="blank">&mdash;</span>';

						endif;

					?>
				</td>
			</tr>
			<?php endif; ?>

			<tr class="total grand">
				<td class="label" colspan="4">Grand Total</td>
				<?php if ( $basket->requires_shipping ) : ?>
				<td class="value">&nbsp;</td>
				<?php endif; ?>
				<td class="value"><?=shop_format_price( $basket->totals->grand, TRUE )?></td>
			</tr>
			
		</tbody>
	</table>
</div>
<?php if ( isset( $show_shipping_chooser ) && $show_shipping_chooser ) : ?>

	<script type="text/javascript">

		$(function(){

			$( '#shipping-chooser' ).on( 'change', function() {
			
				$(this).closest( '.shipping-chooser' ).addClass( 'working' );
				$(this).closest( 'form' ).submit();

			});

		});

	</script>

<?php endif; ?>