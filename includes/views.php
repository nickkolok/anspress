<?php
/**
 * AnsPress views.
 *
 * @package   AnsPress
 * @author    Rahul Aryan <support@anspress.io>
 * @license   GPL-3.0+
 * @link      https://anspress.io
 * @copyright 2014 Rahul Aryan
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Views hooks
 */
class AnsPress_Views {
	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since 2.4.8 Removed `$ap` args.
	 */
	public static function init() {
		anspress()->add_action( 'shutdown', __CLASS__, 'insert_views' );
	}

	/**
	 * Insert view count on loading single question page.
	 *
	 * @param  string $template Template name.
	 */
	public static function insert_views( $template ) {
		if ( is_question() ) {
			ap_insert_views( get_question_id(), 'question' );

			// Update qameta.
			ap_update_views_count( get_question_id() );
		}
	}
}

/**
 * Insert view data in ap_meta table and update qameta.
 *
 * @param  integer       $ref_id Reference ID.
 * @param  string        $type View type, default is question.
 * @param  integer|false $user_id User ID.
 * @return boolean
 */
function ap_insert_views( $ref_id, $type = 'question', $user_id = false ) {
	global $wpdb;

	if ( false === $user_id ) {
		$user_id = get_current_user_id();
	}

	// Insert to DB only if not viewed before and not anonymous.
	if ( ! ap_is_viewed( $ref_id, $user_id ) && ! empty( $user_id ) ) {

		$ip = $log_ip ? $_SERVER['REMOTE_ADDR'] : ''; // @codingStandardsIgnoreLine
		$values = array(
			'view_user_id' => $user_id,
			'view_type'    => 'question',
			'view_ref_id'  => $ref_id,
			'view_ip'      => $ip,
			'view_date'    => current_time( 'mysql' ),
		);

		$insert = $wpdb->insert( $values, [ '%d', '%s', '%d', '%s', '%s' ] ); // db call okay.

		if ( false !== $insert ) {

			/**
				* Trigger action after inserting a view.
				*
				* @param integer $view_id Newly inserted view id.
				*/
			do_action( 'ap_insert_view', $wpdb->insert_id );

			return $wpdb->insert_id;
		}
	}

	return false;
}


/**
 * Check if user already viewd post or user profile.
 *
 * @param integer $ref_id Reference ID.
 * @param integer $user_id User ID.
 * @param string  $type View type.
 * @param string  $ip IP address.
 * @return boolean
 */
function ap_is_viewed( $ref_id, $user_id, $type = 'question', $ip = false ) {
	global $wpdb;
	$ip_clue = '';

	if ( false !== $ip ) {
		$ip_clue = $wpdb->prepare( " AND vote_ip = '%s'", $ip );
	}

	$query = $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ap_views} WHERE vote_user_id = %d AND vote_ref_id = %d AND vote_type = '%s' {$ip_clue}", $user_id, $ref_id, $type ); // @codingStandardsIgnoreLine

	$cache_key = md5( $query );
	$cache = wp_cache_get( $cache_key, 'ap_is_viewed' );

	if ( false === $cache ) {
		return $cache;
	}

	$count = $wpdb->get_var( $query ); // @codingStandardsIgnoreLine
	wp_cache_set( $cache_key, $count, 'ap_is_viewed' );

	return $count > 0 ? true : false;
}