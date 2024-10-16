<?

/**
 * To void/cancel/refund an order...
 *
 * Loosely based on https://www.ibenic.com/how-to-create-woocommerce-refunds-programmatically/ -- but needs review.
 *
 * @param mixed $order_id Post object or post ID of the order.
 * @return \WC_Order_Refund
 */
function sift_fraud_void_refund_order( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! is_a( $order, 'WC_Order' ) ) {
        return new \WP_Error(
            'wc-order',
            __( 'Provided ID is not a WC Order', 'sift-decisions' )
        );
    }

    if ( 'refunded' == $order->get_status() ) {
        return new \WP_Error(
            'wc-order',
            __( 'Order has been already refunded', 'sift-decisions' )
        );
    }

    $order_items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );

    $refund_amount = 0;
    $line_items = array();


    if ( ! $order_items ) {
        return new \WP_Error(
            'wc-order',
            __( 'This order has no items', 'sift-decisions' )
        );
    }

    foreach ( $order_items as $item_id => $item ) {
        $line_total = $order->get_line_total( $item, false, false );
        $qty        = $item->get_quantity();
        $tax_data   = wc_get_order_item_meta( $item_id, '_line_tax_data' );

        $refund_tax = array();

        // Check if it's shipping costs. If so, get shipping taxes.
        if ( $item instanceof \WC_Order_Item_Shipping ) {
            $tax_data = wc_get_order_item_meta( $item_id, 'taxes' );
        }

        // If taxdata is set, format as decimal.
        if ( ! empty( $tax_data['total'] ) ) {
            $refund_tax = array_filter( array_map( 'wc_format_decimal', $tax_data['total'] ) );
        }

        // Calculate line total, including tax.
        $line_total_inc_tax = wc_format_decimal( $line_total ) + ( is_numeric( reset( $refund_tax ) ) ? wc_format_decimal( reset( $refund_tax ) ) : 0 );

        // Add the total for this line tot the grand total.
        $refund_amount += round( $line_total_inc_tax, 2 );

        // Fill item per line.
        $line_items[ $item_id ] = array(
            'qty'          => $qty,
            'refund_total' => wc_format_decimal( $line_total ),
            'refund_tax'   => array_map( 'wc_round_tax_total', $refund_tax )
        );
    }

    $refund = wc_create_refund( array(
        'amount'         => $refund_amount,
        'reason'         => 'Sift Fraud Detection - Voiding Order',
        'order_id'       => $order->ID,
        'line_items'     => $line_items,
        'refund_payment' => true,
        'restock_items'  => true,
    ) );

    return $refund;
}
// Function to block user from adding products to the cart
add_filter( 'woocommerce_add_to_cart_validation', 'block_user_from_adding_to_cart', 10, 3 );

/**
 * Block a user from adding products to the cart.
 *
 * @param bool $passed Whether the product can be added to the cart.
 * @param int $product_id The ID of the product being added to the cart.
 * @param int $quantity The quantity of the product being added to the cart.
 */
function block_user_from_adding_to_cart( $passed, $product_id, $quantity ) {
    $user_id = get_current_user_id();

    // Check if the user is blocked from purchasing by looking at user meta or role
    if ( get_user_meta( $user_id, 'is_blocked_from_purchases', true ) ) {
        display_sgdc_error( 'You are blocked from making purchases due to a recent fraud review. SGDC Error OYBPXRQ', 'error' );
        return false; // Prevent the product from being added to the cart
    }

    return $passed;
}

/**
 * Block a user from making purchases.
 *
 * @param int $user_id The ID of the user to block from making purchases.
 */
function block_user_from_future_purchases( $user_id ) {
    update_user_meta( $user_id, 'is_blocked_from_purchases', true );
}

/**
 * Unblock a user from making purchases if Sift indicates that they are no longer a fraud risk.
 *
 * @param int $user_id The ID of the user to unblock from making purchases.
 */
function unblock_user_from_purchases( $user_id ) {
    delete_user_meta( $user_id, 'is_blocked_from_purchases' );
}

/**
 * Void and refund all orders for a user.
 *
 * @param int $user_id The ID of the user to void and refund orders for.
 */
function void_and_refund_user_orders( $user_id ) {
	// Get all orders for the user.
	$orders = wc_get_orders( array(
		'customer' => $user_id,
		'status'   => array( 'completed', 'processing' ),
	) );

	// Loop through each order.
	foreach ( $orders as $order ) {
		// Refund the order.
		sift_fraud_void_refund_order( $order->get_id() );
	}
}

/**
 * Cancel all subscriptions for a user.
 *
 * @param int $user_id The ID of the user to cancel subscriptions for.
 * return void
 */
function cancel_and_remove_user_subscriptions( $user_id ) {
	// Get all subscriptions for the user.
	$subscriptions = wcs_get_users_subscriptions( $user_id );

	foreach ( $subscriptions as $subscription ) {
		// Check if the subscription is active or requires cancellation.
		if ( 'active' === $subscription->get_status() || 'pending-cancel' === $subscription->get_status() ) {
			// Cancel the subscription.
			$subscription->update_status( 'cancelled', __( 'Cancelled by system due to account block. SGDC Error OYBPXRQ', 'sift-decisions' ) );

			// Remove the subscription if you want to completely delete it.
			wp_delete_post( $subscription->get_id(), true );
		}
	}
}

/**
 * Force user logout when they are blocked from making purchases.
 *
 * @param int $user_id The ID of the user to log out.
 */
function force_user_logout( $user_id ) {
	// Get all user sessions.
	if ( class_exists( 'WP_Session_Tokens' ) ) {
		$user_sessions = WP_Session_Tokens::get_instance( $user_id );
		$user_sessions->destroy_all();
	}

	// Optional: If the user is currently logged in, log them out.
	if ( get_current_user_id() === $user_id ) {
		wp_logout();
	}
}

/**
 * Disallow user access to the site when they are blocked from making purchases.
 *
 * @param int $user_id The ID of the user to block from accessing the site.
 */
// Prevent login for blocked users.
function disallow_blocked_user_login( $user, $password ) {
	// Check if the user is blocked.
	$is_blocked = get_user_meta( $user->ID, 'is_blocked_from_purchases', true );

	if ( $is_blocked ) {
		// Deny login if the user is blocked.
		return new WP_Error( 'blocked_user', __( 'Your account has been blocked from logging in. SGDC Error OYBPXRQ', 'sift-decisions' ) );
	}

	return $user;
}
add_filter( 'wp_authenticate_user', 'disallow_blocked_user_login', 10, 2 );

/**
 * Block a user from making purchases.
 *
 * @param int $user_id The ID of the user to block from making purchases.
 */
function block_user_from_purchasing( $user_id ) {
	// Block user from purchasing by updating user meta or status.
	update_user_meta( $user_id, 'is_blocked_from_purchases', true );

	// Remove subscriptions, licenses and product keys and refund.

	// Void and refund all orders for the user.
	void_and_refund_user_orders( $user_id );

	// Cancel all subscriptions for the user.
	cancel_and_remove_user_subscriptions( $user_id );


	// If a blocked user tries to make a purchase, display the “SGDC error” to the user.
	// When they contact Woo support to appeal their account block, support will identify the specific error code and escalate to Fraudsquad.
	// “SGDC error” can be used to identify a Woo.com account block specifically.
	display_sgdc_error( 'You are blocked from making purchases due to a recent fraud review. SGDC Error OYBPXRQ' );

	// Apply the same Sift decision to the associated WordPress.com account.
	// Log the user out of their Woo.com account and prevent further access.
	force_user_logout( $user_id );

}

/**
 * Display the SGDC error to the user.
 *
 * @param string $message Override the default message to display to the user.
 */
function display_sgdc_error( $message = '' ) {
	// Set the default message.
	$default_message = __( 'Your account has been blocked from making purchases. SGDC Error OYBPXRQ', 'sift-decisions' );

	// Use the provided message or fall back to the default message.
	$message = $message ? $message : $default_message;

	// Display the SGDC error to the user.
	wc_add_notice( $message, 'error' );
}

/**
 * Process the Sift decision received.
 *
 * @param mixed $return The return value.
 * @param string $decision_id The ID of the Sift decision.
 * @param string $entity_type The type of entity the decision is for.
 * @param int $entity_id The ID of the entity the decision is for.
 * @param int $time The time the decision was made.
 */
function process_sift_decision_received( $return, $decision_id, $entity_type, $entity_id, $time ) {

    switch( $decision_id ) {
        case 'trust_list_payment_abuse':
            // ✅ -- users
            /**
             * Need to have:
             *  Remove any purchasing block.
             */
            unblock_user_from_purchases( $entity_id );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             */
            break;

        case 'looks_good_payment_abuse':
            // ✅ -- users
            /**
             * Need to have:
             *  Remove any purchasing block.
             */
            unblock_user_from_purchases( $entity_id );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             */
            break;

        case 'not_likely_fraud_payment_abuse':
            // ⚠️ -- users
            /**
             * Need to have:
             *  Remove any purchasing block.
             */
            unblock_user_from_purchases( $entity_id );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             */
            break;

        case 'likely_fraud_refundno_renew_payment_abuse':
            // 🚫 -- users
            /**
             * Need to have:
             *  Block future purchases.
             */
            update_user_meta( $entity_id, 'is_blocked_from_purchases', true );

            /**
             *  Remove subscriptions, licenses and product keys, and refund.
             */
            void_and_refund_user_orders( $entity_id );
            cancel_and_remove_user_subscriptions( $entity_id );
            remove_user_licenses_and_product_keys( $entity_id ); // Suggested new function

            /**
             * If a blocked user tries to make a purchase, display the “SGDC error” to the user.
             */
            display_sgdc_error( 'You are blocked from making purchases due to a recent fraud review. SGDC Error OYBPXRQ' );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             */
            break;

        case 'likely_fraud_keep_purchases_payment_abuse':
            // 🚫 -- users
            /**
             * Need to have:
             *  Block future purchases.
             */
            update_user_meta( $entity_id, 'is_blocked_from_purchases', true );

            /**
             * If a blocked user tries to make a purchase, display the “SGDC error” to the user.
             */
            display_sgdc_error( 'You are blocked from making purchases due to a recent fraud review. SGDC Error OYBPXRQ' );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             */
            break;

        case 'fraud_payment_abuse':
            // 🚫 -- users
            /**
             * Need to have:
             *  Block future purchases.
             */
            update_user_meta( $entity_id, 'is_blocked_from_purchases', true );

            /**
             *  Remove subscriptions, licenses and product keys, and refund.
             */
            void_and_refund_user_orders( $entity_id );
            cancel_and_remove_user_subscriptions( $entity_id );
            remove_user_licenses_and_product_keys( $entity_id ); // Suggested new function

            /**
             * If a blocked user tries to make a purchase, display an error message to the user.
             * When they contact Woo support to appeal their account block, support will identify the specific error code and escalate to Fraudsquad.
             * “SGDC error” can be used to identify a Woo.com account block specifically.
             */
            display_sgdc_error( 'You are blocked from making purchases due to fraudulent activity. SGDC Error OYBPXRQ' );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             *  Log the user out of their Woo.com account and prevent further access.
             */
            force_user_logout( $entity_id );
            break;

        case 'block_wo_review_payment_abuse':
            // 🚫 -- users
            /**
             * Need to have:
             *  Block future purchases.
             */
            update_user_meta( $entity_id, 'is_blocked_from_purchases', true );

            /**
             * Nice to have:
             *  Apply the same Sift decision to the associated WordPress.com account.
             */
            break;

        case 'looks_ok_payment_abuse':
        case 'looks_suspicious_payment_abuse':
        case 'order_looks_ok_payment_abuse':
        case 'order_looks_suspicious_payment_abuse':
            // Not Currently Implemented.
            break;
    }

    return $return;
}
add_filter( 'sift_decision_received', __NAMESPACE__ . '\process_sift_decision_received', 10, 5 );
