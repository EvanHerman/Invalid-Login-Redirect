<?php
/**
 * Error log table
 *
 * @since 1.0.0
 */
class Invalid_Login_Redirect_Log_Table extends WP_List_Table {

	function __construct() {

		global $status, $page;

		parent::__construct( array(
			'singular'  => 'log',
			'plural'    => 'logs',
			'ajax'      => false,
		) );

	}


	function column_default( $item, $column_name ) {

		switch ( $column_name ) {

			case 'username':
			case 'attempt':
			case 'ip_address':
			case 'type':
				return $item[ $column_name ];

			case 'timestamp':
				return date( get_option( 'date_format' ), $item[ $column_name ] ) . ' &ndash; ' . date( get_option( 'time_format' ), $item[ $column_name ] );

			default:
				return print_r( $item,true );

		}

	}

	function column_username( $item ) {

		$user_obj = get_user_by( ( is_email( $item['username'] ) ? 'email' : 'login' ), $item['username'] );

		$actions = [];

		if ( $user_obj ) {

			$actions['view'] = sprintf(
				'<small><a href="?page=%s&action=%s&movie=%s">' . esc_html__( 'View User', 'invalid-login-redirect' ) . '</a></small>',
				$_REQUEST['page'],
				'edit',
				$item['ID']
			);

		}

		$actions = apply_filters( 'ilr_username_column_actions', $actions );

		return sprintf(
			'%1$s %2$s',
			$item['username'],
			$this->row_actions( $actions )
		);

	}

	function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( (int) $item['ID'] )
		);

	}

	function get_columns() {

		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'timestamp'  => __( 'Date', 'invalid-login-redirect' ),
			'username'   => __( 'Username', 'invalid-login-redirect' ),
			'attempt'    => __( 'Attempt', 'invalid-login-redirect' ),
			'ip_address' => __( 'IP Address', 'invalid-login-redirect' ),
			'type'       => __( 'Type', 'invalid-login-redirect' ),
		);

		return $columns;

	}


	/** ************************************************************************
	* Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	* you will need to register it here. This should return an array where the
	* key is the column that needs to be sortable, and the value is db column to
	* sort by. Often, the key and value will be the same, but this is not always
	* the case (as the value is a column name from the database, not the list table).
	*
	* This method merely defines which columns should be sortable and makes them
	* clickable - it does not handle the actual sorting. You still need to detect
	* the ORDERBY and ORDER querystring variables within prepare_items() and sort
	* your data accordingly (usually by modifying your query).
	*
	* @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	**************************************************************************/
	function get_sortable_columns() {

		$sortable_columns = [
			'timestamp'  => [ 'timestamp', false ],
			'username'   => [ 'username', false ],
			'ip_address' => [ 'ip_address', false ],
			'type'       => [ 'type', false ],
		];

		return $sortable_columns;

	}


	function get_bulk_actions() {

		$actions = [
			'delete' => __( 'Delete', 'invalid-login-redirect' ),
			'ban'    => __( 'Ban', 'invalid-login-redirect' ),
		];

		return $actions;

	}


	function process_bulk_action() {

		if ( 'delete' === $this->current_action() ) {

			wp_die( 'Items deleted (or they would be if we had items to delete)!' );

		}

	}


	/** ************************************************************************
	* REQUIRED! This is where you prepare your data for display. This method will
	* usually be used to query the database, sort and filter the data, and generally
	* get it ready to be displayed. At a minimum, we should set $this->items and
	* $this->set_pagination_args(), although the following properties and methods
	* are frequently interacted with here...
	*
	* @global WPDB $wpdb
	* @uses $this->_column_headers
	* @uses $this->items
	* @uses $this->get_columns()
	* @uses $this->get_sortable_columns()
	* @uses $this->get_pagenum()
	* @uses $this->set_pagination_args()
	**************************************************************************/
	function prepare_items() {

		$per_page = 5;

		$columns = $this->get_columns();

		$hidden = array();

		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$log_data = [];

		$log_query = new WP_Query( [
			'post_type'      => 'ilr_log',
			'posts_per_page' => 200,
		] );

		if ( $log_query->have_posts() ) {

			while ( $log_query->have_posts() ) {

				$log_query->the_post();

				$log_data[] = [
					'username'   => get_post_meta( get_the_ID(), 'ilr_log_username', true ),
					'attempt'    => get_post_meta( get_the_ID(), 'ilr_log_attempt', true ),
					'timestamp'  => get_post_meta( get_the_ID(), 'ilr_log_timestamp', true ),
					'ip_address' => get_post_meta( get_the_ID(), 'ilr_log_ip_address', true ),
					'type'       => get_post_meta( get_the_ID(), 'ilr_log_type', true ),
				];

			}

		}

		/**
		* This checks for sorting input and sorts the data in our array accordingly.
		*
		* In a real-world situation involving a database, you would probably want
		* to handle sorting by passing the 'orderby' and 'order' values directly
		* to a custom query. The returned data will be pre-sorted, and this array
		* sorting technique would be unnecessary.
		*/
		function usort_reorder( $a, $b ) {

			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'title';

			$order = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc';

			$result = strcmp( $a[ $orderby ], $b[ $orderby ] );

			return ( 'asc' === $order ) ? $result : -$result;

		}

		usort( $log_data, 'usort_reorder' );

		$current_page = $this->get_pagenum();

		$total_items = count( $log_data );

		$log_data = array_slice( $log_data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->items = $log_data;

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
	}


}