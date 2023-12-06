<?php
/*
 * Plugin Name: Newsletter Subscription for Ultimate Member
 * Plugin URI: https://github.com/fahdi/um-to-newsletter/
 * Description: This plugin extends the functionality of Ultimate Member by saving user registration details as a subscription in The Newsletter Plugin. It automatically subscribes users to your newsletter list during the registration process if they opt-in, ensuring seamless integration between user management and email marketing.
 * Version: 1.0.0
 * Author: Fahad Murtaza and Shahzad Raza
 * Author URI: https://wpacademy.pk/
 * License: GPL2
 * Text Domain: /um-to-newsletter
 */

add_action( 'um_after_save_registration_details', 'um_after_save_registration_details', 10, 2 );

function um_after_save_registration_details( $user_id, $submitted ) {

	if ( isset( $submitted['newsletter_subscription'] ) && $submitted['newsletter_subscription'][0] === 'Yes' ) {

		// Check if the user is not already subscribed
		// Call the my_subscribe function to create a subscription
		$subscription_result = um_my_subscribe( $submitted );

		if ( is_wp_error( $subscription_result ) ) {
			// Handle subscription error if needed
			error_log( 'Error creating subscription for user with ID ' . $user_id );
		} else {
			error_log( 'User with ID ' . $user_id . ' subscribed successfully.' );
		}
	}
}

function um_my_subscribe( $submitted ) {

	// Example: You can extract the email from the submitted data
	$user_email = isset( $submitted['user_email'] ) ? $submitted['user_email'] : '';
	$first_name = isset( $submitted['first_name'] ) ? $submitted['first_name'] : '';
	$last_name  = isset( $submitted['last_name'] ) ? $submitted['last_name'] : '';

	// You can also modify this function to set other subscription details
	// based on the submitted data

	// Create a subscription object and set its properties
	$subscription                = NewsletterSubscription::instance()->get_default_subscription();
	$subscription->data->email   = $user_email;
	$subscription->data->name    = $first_name;
	$subscription->data->surname = $last_name;

	$subscription->optin = 'single'; // Or 'double' based on your requirements

	// Call the subscribe2 method to create the subscription
	$result = NewsletterSubscription::instance()->subscribe2( $subscription );

	return $result;
}
