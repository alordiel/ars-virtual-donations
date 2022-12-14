<?php
/** @noinspection ForgottenDebugOutputInspection */
function dbga( $arg ) {
	error_log( print_r( $arg, true ) );
}


/**
 * Checks which is the current page and returns a code string for marking which is the selected category
 * Used from the "select-kind-of-animal.php" template
 *
 * @param $settings
 *
 * @return string
 */
function ars_get_the_current_selected_kind( $settings ): string {

	if ( is_post_type_archive( 'sheltered-animal' ) ) {
		return 'all';
	}

	if ( empty( $settings ) ) {
		$settings = get_option( 'ars-settings' );
	}

	// If we don't have the saved settings we can do much about this
	if ( empty( $settings['animal-terms'] ) ) {
		return '';
	}
	$term_slug = get_query_var( 'term' );
	$term      = get_term_by( 'slug', $term_slug, 'kind-of-animal' );

	/** @noinspection NotOptimalIfConditionsInspection */
	if ( ars_is_wpml_activated() && ICL_LANGUAGE_CODE !== 'en' ) {
		$term_id = apply_filters( 'wpml_object_id', $term->term_id, 'kind-of-animal', false, 'en' );
		if ( ! empty( $term_id ) ) {
			global $sitepress;
			$current_language = $sitepress->get_current_language();
			$sitepress->switch_lang( 'en' );
			$term = get_term( $term_id, 'kind-of-animal' );
			$sitepress->switch_lang( $current_language );
		}
	}

	return $term->slug;
}

/**
 * Returns true if WPML is active
 *
 * @return bool
 */
function ars_is_wpml_activated(): bool {
	return class_exists( 'SitePress' ) && function_exists( 'wpml_is_setup_complete' ) && wpml_is_setup_complete();
}

/**
 * an obfuscation of the id
 *
 * @param int $id
 *
 * @return string
 */
function ars_encode_id( int $id ): string {
	return 's_' . ( ( $id * 3 ) + 13 );
}


/**
 * Deobfuscation of the id
 *
 * @param string $string
 *
 * @return int
 */
function ars_decode_id( string $string ): int {
	$int = (int) str_replace( 's_', '', $string );

	return ( $int - 13 ) / 3;
}

/**
 * Create
 *
 * @param string $page_type
 *
 * @return void
 */
function ars_create_template_page( string $page_type ) {
	$templates = [
		'checkout-page'         => [
			'title'    => __( 'Donation checkout', 'ars-virtual-donations' ),
			'template' => 'ars-donation-checkout.php',
			'slug'     => 'donations-checkout',
		],
		'thank-you-page'        => [
			'title'    => __( 'Virtual adopt - Thank you', 'ars-virtual-donations' ),
			'template' => 'ars-thank-you-donation.php',
			'slug'     => 'ars-thank-you',
		],
		'my-subscriptions-page' => [
			'title'    => __( 'Manage my adopted animals', 'ars-virtual-donations' ),
			'template' => 'ars-my-subscriptions.php',
			'slug'     => 'ars-my-donations',
		],
	];

	$user_id      = get_current_user_id();
	$current_page = $templates[ $page_type ];
	$page_id      = wp_insert_post( [
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_title'     => $current_page['title'],
		'post_author'    => $user_id,
		'comment_status' => 'closed',
		'page_template'  => $current_page['template'],
		'post_name'      => $current_page['slug'],
	] );

	$ars_settings               = get_option( 'ars-settings' );
	$ars_settings[ $page_type ] = $page_id;
	update_option( 'ars-settings', $ars_settings );
}

/**
 * General function that echos the response status and message from an ajax query and terminates the call
 *
 * @param int $code
 * @param string $message
 * @param array $some_data
 *
 * @return void
 */
function ars_json_response( int $code, string $message = '', array $some_data = [] ) {
	$data = [ 'status' => $code ];
	if ( $message !== '' ) {
		$data['message'] = $message;
	}

	if ( $some_data !== [] ) {
		$data['data'] = $some_data;
	}

	echo json_encode( $data, JSON_NUMERIC_CHECK );
	wp_die();
}

/**
 * Function will create new WP_User and will automatically log the user in.
 *
 * @param array $user_data
 *
 * @return string
 */
function ars_create_new_user( array $user_data ): string {
	if ( empty( $user_data['email'] ) || ! is_email( $user_data['email'] ) ) {
		return __( 'Email does not exist', 'ars-virtual-donations' );
	}
	if ( email_exists( $user_data['email'] ) || username_exists( $user_data['email'] ) ) {
		return __( 'There is already an user with that email', 'ars-virtual-donations' );
	}

	$user_id = wp_insert_user( [
		'user_login' => $user_data['email'],
		'user_pass'  => $user_data['password'],
		'user_email' => $user_data['email'],
		'first_name' => $user_data['first_name'],
		'last_name'  => $user_data['last_name'],
		'role'       => 'virtual-adopter'
	] );

	if ( is_wp_error( $user_id ) ) {
		return $user_id->get_error_message();
	}

	$credentials = array(
		'user_login'    => $user_data['email'],
		'user_password' => $user_data['password'],
		'remember'      => true
	);

	wp_signon( $credentials, false );
	wp_set_current_user( $user_id );

	return '';
}
