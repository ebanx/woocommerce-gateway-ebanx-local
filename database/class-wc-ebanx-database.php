<?php

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Class WC_EBANX_Database
 */
class WC_EBANX_Database {
	/**
	 * Table names.
	 *
	 * @return array
	 */
	public static function tables() {
		global $wpdb;

		return array(
			'logs' => $wpdb->prefix . 'ebanx_logs',
		);
	}

	/**
	 * Migrate tables.
	 */
	public static function migrate() {
		self::create_log_table();
	}

	/**
	 * Creates table used to store logs.
	 */
	private static function create_log_table() {
		global $wpdb;

		$table_name      = self::tables()['logs'];
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
			id int NOT NULL AUTO_INCREMENT,
			time datetime NOT NULL,
			integration_key varchar(255) DEFAULT NULL,
			event varchar(255) NOT NULL,
			log blob NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate";

		dbDelta( $sql );
	}

	/**
	 * Wrapper for `$wpdb` `insert` method, getting table name from `tables` method
	 *
	 * @param string $table table name.
	 * @param array  $data data to be inserted.
	 *
	 * @return int|false
	 */
	public static function insert( $table, $data ) {
		global $wpdb;

		return $wpdb->insert( self::tables()[ $table ], $data );
	}

	/**
	 * Truncate table
	 *
	 * @param string $table table name.
	 * @param string $where
	 */
	public static function truncate( $table, $where = '1=1' ) {
		global $wpdb;

		$table_name = self::tables()[ $table ];

		// @codingStandardsIgnoreLine
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$table_name` WHERE $where", null ) );
	}

	/**
	 * Select all columns from $table
	 * Commonly used to get all logs before truncate table
	 *
	 * @param string $table table name.
	 * @param string $where
	 *
	 * @return array|null|object
	 */
	public static function select( $table, $where = '1=1' ) {
		global $wpdb;

		$table_name = self::tables()[ $table ];

		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %s WHERE %s', $table_name, $where ) );
	}
}
