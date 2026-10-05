<?php
/*
 * Plugin Name: Newsletter Subscription for Ultimate Member
 * Plugin URI: https://github.com/fahdi/um-to-newsletter/
 * Description: This plugin extends the functionality of Ultimate Member by saving user registration details as a subscription in The Newsletter Plugin. It automatically subscribes users to your newsletter list during the registration process if they opt-in, ensuring seamless integration between user management and email marketing.
 * Version: 1.1.0
 * Author: Fahad Murtaza and Shahzad Raza
 * Author URI: https://wpacademy.pk/
 * License: GPL2
 * Text Domain: um-to-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'um_after_save_registration_details', 'umtn_after_save_registration_details', 10, 2 );

/**
 * Subscribe a freshly registered Ultimate Member user when (and only when) they ticked the opt-in box.
 *
 * @param int   $user_id
 * @param array $submitted UM form submission
 */
function umtn_after_save_registration_details( $user_id, $submitted ) {

	if ( ! umtn_user_opted_in( $submitted ) ) {
		return;
	}

	$result = umtn_subscribe( $user_id, $submitted );

	if ( is_wp_error( $result ) ) {
		error_log( sprintf( 'um-to-newsletter: could not subscribe user %d: %s', $user_id, $result->get_error_message() ) );

		return;
	}

	// Consent evidence (GDPR art. 7(1)): when and through which form the user opted in.
	update_user_meta( $user_id, 'umtn_newsletter_consent', array(
		'time'    => current_time( 'mysql', true ),
		'form_id' => isset( $submitted['form_id'] ) ? absint( $submitted['form_id'] ) : 0,
	) );
}

/**
 * Whether the registration form contained a ticked newsletter checkbox.
 *
 * UM submits a single-option checkbox as a string and a multi-option one as an array.
 *
 * @param array $submitted
 *
 * @return bool
 */
function umtn_user_opted_in( $submitted ) {
	if ( ! is_array( $submitted ) || empty( $submitted['newsletter_subscription'] ) ) {
		return false;
	}

	$values = array_map( 'strval', (array) $submitted['newsletter_subscription'] );

	/**
	 * Filter the checkbox values that count as consent.
	 *
	 * @param string[] $accepted
	 */
	$accepted = apply_filters( 'um_to_newsletter_optin_values', array( 'Yes', 'yes', '1', 'on' ) );

	return (bool) array_intersect( $values, $accepted );
}

/**
 * Create the Newsletter subscription.
 *
 * @param int   $user_id
 * @param array $submitted
 *
 * @return mixed|WP_Error Newsletter's result, or WP_Error when nothing was done.
 */
function umtn_subscribe( $user_id, $submitted ) {

	if ( ! class_exists( 'NewsletterSubscription' ) || ! class_exists( 'Newsletter' ) ) {
		return new WP_Error( 'umtn_missing_newsletter', 'The Newsletter plugin is not active.' );
	}

	$user_email = isset( $submitted['user_email'] ) ? sanitize_email( $submitted['user_email'] ) : '';

	if ( ! is_email( $user_email ) ) {
		$user = get_userdata( $user_id );
		$user_email = $user ? $user->user_email : '';
	}

	if ( ! is_email( $user_email ) ) {
		return new WP_Error( 'umtn_invalid_email', 'No valid e-mail address in the registration data.' );
	}

	// Already on the list and confirmed: do not create a duplicate or re-send a confirmation.
	$existing = Newsletter::instance()->get_user( $user_email );
	if ( $existing && isset( $existing->status ) && 'C' === $existing->status ) {
		return $existing;
	}

	$subscription                = NewsletterSubscription::instance()->get_default_subscription();
	$subscription->data->email   = $user_email;
	$subscription->data->name    = isset( $submitted['first_name'] ) ? sanitize_text_field( $submitted['first_name'] ) : '';
	$subscription->data->surname = isset( $submitted['last_name'] ) ? sanitize_text_field( $submitted['last_name'] ) : '';

	/**
	 * Opt-in mode. Defaults to whatever the site owner configured in Newsletter > Settings
	 * (previously hard-coded to 'single', which silently bypassed double opt-in).
	 *
	 * @param string $optin 'single' or 'double'
	 * @param int    $user_id
	 */
	$optin = apply_filters( 'um_to_newsletter_optin', $subscription->optin, $user_id );
	if ( in_array( $optin, array( 'single', 'double' ), true ) ) {
		$subscription->optin = $optin;
	}

	return NewsletterSubscription::instance()->subscribe2( $subscription );
}
