<?php
/**
 * Unit tests covering WP_REST_Global_Styles_Revisions_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @covers WP_REST_Global_Styles_Revisions_Controller
 *
 * @group restapi-global-styles
 * @group restapi
 */
class WP_REST_Global_Styles_Revisions_Controller_Test extends WP_Test_REST_Controller_Testcase {
	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $second_admin_id;

	/**
	 * @var int
	 */
	protected static $author_id;

	/**
	 * @var int
	 */
	protected static $global_styles_id;

	/**
	 * @var int
	 */
	private $total_revisions;

	/**
	 * @var array
	 */
	private $revision_1;

	/**
	 * @var int
	 */
	private $revision_1_id;

	/**
	 * @var array
	 */
	private $revision_2;

	/**
	 * @var int
	 */
	private $revision_2_id;

	/**
	 * @var array
	 */
	private $revision_3;

	/**
	 * @var int
	 */
	private $revision_3_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetupBeforeClass( $factory ) {
		self::$admin_id        = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$second_admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$author_id       = $factory->user->create(
			array(
				'role' => 'author',
			)
		);

		wp_set_current_user( self::$admin_id );
		// This creates the global styles for the current theme.
		self::$global_styles_id = $factory->post->create(
			array(
				'post_content' => '{"version": ' . WP_Theme_JSON::LATEST_SCHEMA . ', "isGlobalStylesUserThemeJSON": true }',
				'post_status'  => 'publish',
				'post_title'   => __( 'Custom Styles', 'default' ),
				'post_type'    => 'wp_global_styles',
				'post_name'    => 'wp-global-styles-tt1-blocks-revisions',
				'tax_input'    => array(
					'wp_theme' => 'tt1-blocks',
				),
			)
		);

		// Update post to create a new revisions.
		$new_styles_post = array(
			'ID'           => self::$global_styles_id,
			'post_content' => wp_json_encode(
				array(
					'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
					'isGlobalStylesUserThemeJSON' => true,
					'styles'                      => array(
						'color' => array(
							'background' => 'hotpink',
						),
					),
					'settings'                    => array(
						'color' => array(
							'palette' => array(
								'custom' => array(
									array(
										'name'  => 'Ghost',
										'slug'  => 'ghost',
										'color' => 'ghost',
									),
								),
							),
						),
					),
				)
			),
		);

		wp_update_post( $new_styles_post, true );

		$new_styles_post = array(
			'ID'           => self::$global_styles_id,
			'post_content' => wp_json_encode(
				array(
					'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
					'isGlobalStylesUserThemeJSON' => true,
					'styles'                      => array(
						'color' => array(
							'background' => 'lemonchiffon',
						),
					),
					'settings'                    => array(
						'color' => array(
							'palette' => array(
								'custom' => array(
									array(
										'name'  => 'Gwanda',
										'slug'  => 'gwanda',
										'color' => 'gwanda',
									),
								),
							),
						),
					),
				)
			),
		);

		wp_update_post( $new_styles_post, true );

		$new_styles_post = array(
			'ID'           => self::$global_styles_id,
			'post_content' => wp_json_encode(
				array(
					'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
					'isGlobalStylesUserThemeJSON' => true,
					'styles'                      => array(
						'color' => array(
							'background' => 'chocolate',
						),
					),
					'settings'                    => array(
						'color' => array(
							'palette' => array(
								'custom' => array(
									array(
										'name'  => 'Stacy',
										'slug'  => 'stacy',
										'color' => 'stacy',
									),
								),
							),
						),
					),
				)
			),
		);

		wp_update_post( $new_styles_post, true );
		wp_set_current_user( 0 );
	}

	/**
	 * Removes users after our tests run.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$second_admin_id );
		self::delete_user( self::$author_id );
	}

	/**
	 * Sets up before tests.
	 */
	public function set_up() {
		parent::set_up();
		switch_theme( 'tt1-blocks' );
		$revisions             = wp_get_post_revisions( self::$global_styles_id );
		$this->total_revisions = count( $revisions );

		$this->revision_1    = array_pop( $revisions );
		$this->revision_1_id = $this->revision_1->ID;

		$this->revision_2    = array_pop( $revisions );
		$this->revision_2_id = $this->revision_2->ID;

		$this->revision_3    = array_pop( $revisions );
		$this->revision_3_id = $this->revision_3->ID;
	}

	/**
	 * @ticket 58524
	 * @ticket 59810
	 *
	 * @covers WP_REST_Global_Styles_Controller::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/global-styles/(?P<parent>[\d]+)/revisions',
			$routes,
			'Global style revisions based on the given parentID route does not exist.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/global-styles/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)',
			$routes,
			'Single global style revisions based on the given parentID and revision ID route does not exist.'
		);
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_missing_parent( $method ) {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/global-styles/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	/**
	 * Data provider intended to provide HTTP method names for testing GET and HEAD requests.
	 *
	 * @return array
	 */
	public static function data_readable_http_methods() {
		return array(
			'GET request'  => array( 'GET' ),
			'HEAD request' => array( 'HEAD' ),
		);
	}

	/**
	 * Utility function to check the items in WP_REST_Global_Styles_Controller::get_items
	 * against the expected values.
	 *
	 * @ticket 58524
	 */
	protected function check_get_revision_response( $response_revision_item, $revision_expected_item ) {
		$this->assertSame( (int) $revision_expected_item->post_author, $response_revision_item['author'], 'Check that the revision item `author` exists.' );
		$this->assertSame( mysql_to_rfc3339( $revision_expected_item->post_date ), $response_revision_item['date'], 'Check that the revision item `date` exists.' );
		$this->assertSame( mysql_to_rfc3339( $revision_expected_item->post_date_gmt ), $response_revision_item['date_gmt'], 'Check that the revision item `date_gmt` exists.' );
		$this->assertSame( mysql_to_rfc3339( $revision_expected_item->post_modified ), $response_revision_item['modified'], 'Check that the revision item `modified` exists.' );
		$this->assertSame( mysql_to_rfc3339( $revision_expected_item->post_modified_gmt ), $response_revision_item['modified_gmt'], 'Check that the revision item `modified_gmt` exists.' );
		$this->assertSame( $revision_expected_item->post_parent, $response_revision_item['parent'], 'Check that an id for the parent exists.' );

		// Global styles.
		$config = ( new WP_Theme_JSON( json_decode( $revision_expected_item->post_content, true ), 'custom' ) )->get_raw_data();
		$this->assertSame(
			$config['settings'],
			$response_revision_item['settings'],
			'Check that the revision settings exist in the response.'
		);
		$this->assertSame(
			$config['styles'],
			$response_revision_item['styles'],
			'Check that the revision styles match the updated styles.'
		);
	}

	/**
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Response status is 200.' );
		$this->assertCount( $this->total_revisions, $data, 'Check that correct number of revisions exists.' );

		// Reverse chronology.
		$this->assertSame( $this->revision_3_id, $data[0]['id'] );
		$this->check_get_revision_response( $data[0], $this->revision_3 );

		$this->assertSame( $this->revision_2_id, $data[1]['id'] );
		$this->check_get_revision_response( $data[1], $this->revision_2 );

		$this->assertSame( $this->revision_1_id, $data[2]['id'] );
		$this->check_get_revision_response( $data[2], $this->revision_1 );
	}

	/**
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::prepare_item_for_response
	 */
	public function test_get_items_should_return_no_response_body_for_head_requests() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'HEAD', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Response status is 200.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_head_request_with_specified_fields_returns_success_response
	 * @ticket 56481
	 *
	 * @param string $path The path to test.
	 */
	public function test_head_request_with_specified_fields_returns_success_response( $path ) {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', sprintf( $path, self::$global_styles_id, $this->revision_1_id ) );
		$request->set_param( '_fields', 'id' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
	}

	/**
	 * Data provider intended to provide paths for testing HEAD requests.
	 *
	 * @return array
	 */
	public static function data_head_request_with_specified_fields_returns_success_response() {
		return array(
			'get_item request'  => array( '/wp/v2/global-styles/%d/revisions/%d' ),
			'get_items request' => array( '/wp/v2/global-styles/%d/revisions' ),
		);
	}

	/**
	 * @ticket 59810
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions/' . $this->revision_1_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Response status is 200.' );
		$this->check_get_revision_response( $data, $this->revision_1 );
	}

	/**
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 * @covers WP_REST_Global_Styles_Controller::prepare_item_for_response
	 */
	public function test_get_item_should_return_no_response_body_for_head_requests() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'HEAD', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions/' . $this->revision_1_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Response status is 200.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 59810
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_revision
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_invalid_revision_id_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$expected_error  = 'rest_post_invalid_id';
		$expected_status = 404;
		$request         = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions/20000001' );
		$response        = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items_eligible_roles() {
		wp_set_current_user( self::$second_admin_id );
		$config              = array(
			'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
			'isGlobalStylesUserThemeJSON' => true,
			'styles'                      => array(
				'color' => array(
					'background' => 'whitesmoke',
				),
			),
			'settings'                    => array(),
		);
		$updated_styles_post = array(
			'ID'           => self::$global_styles_id,
			'post_content' => wp_json_encode( $config ),
		);

		wp_update_post( $updated_styles_post, true );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( $this->total_revisions + 1, $data, 'Check that extra revision exist' );
		$this->assertSame( self::$second_admin_id, $data[0]['author'], 'Check that second author id returns expected value.' );
	}

	/**
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items with context arg.
	 */
	public function test_get_item_embed_context() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_param( 'context', 'embed' );
		$response = rest_get_server()->dispatch( $request );
		$fields   = array(
			'author',
			'date',
			'id',
			'parent',
		);
		$data     = $response->get_data();
		$this->assertSameSets( $fields, array_keys( $data[0] ) );
	}

	/**
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( 9, $properties, 'Schema properties array has exactly 9 elements.' );
		$this->assertArrayHasKey( 'id', $properties, 'Schema properties array has "id" key.' );
		$this->assertArrayHasKey( 'styles', $properties, 'Schema properties array has "styles" key.' );
		$this->assertArrayHasKey( 'settings', $properties, 'Schema properties array has "settings" key.' );
		$this->assertArrayHasKey( 'parent', $properties, 'Schema properties array has "parent" key.' );
		$this->assertArrayHasKey( 'author', $properties, 'Schema properties array has "author" key.' );
		$this->assertArrayHasKey( 'date', $properties, 'Schema properties array has "date" key.' );
		$this->assertArrayHasKey( 'date_gmt', $properties, 'Schema properties array has "date_gmt" key.' );
		$this->assertArrayHasKey( 'modified', $properties, 'Schema properties array has "modified" key.' );
		$this->assertArrayHasKey( 'modified_gmt', $properties, 'Schema properties array has "modified_gmt" key.' );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 60131
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_item_permissions_check
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_permissions_check( $method ) {
		wp_set_current_user( self::$author_id );
		$request  = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * Tests the pagination header of the first page.
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_pagination_header_of_the_first_page( $method ) {
		wp_set_current_user( self::$admin_id );

		$rest_route  = '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions';
		$per_page    = 2;
		$total_pages = (int) ceil( $this->total_revisions / $per_page );
		$page        = 1;  // First page.

		$request = new WP_REST_Request( $method, $rest_route );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $this->total_revisions, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'per_page' => $per_page,
				'page'     => $page + 1,
			),
			rest_url( $rest_route )
		);
		$this->assertStringNotContainsString( 'rel="prev"', $headers['Link'] );
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );
	}

	/**
	 * Tests the pagination header of the last page.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_pagination_header_of_the_last_page
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_pagination_header_of_the_last_page( $method ) {
		wp_set_current_user( self::$admin_id );

		$rest_route  = '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions';
		$per_page    = 2;
		$total_pages = (int) ceil( $this->total_revisions / $per_page );
		$page        = 2;  // Last page.

		$request = new WP_REST_Request( $method, $rest_route );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $this->total_revisions, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'per_page' => $per_page,
				'page'     => $page - 1,
			),
			rest_url( $rest_route )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
	}

	/**
	 * Tests that invalid 'per_page' query should error.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_invalid_per_page_should_error
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_invalid_per_page_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = -1; // Invalid number.
		$expected_error  = 'rest_invalid_param';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_param( 'per_page', $per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that out of bounds 'page' query should error.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_out_of_bounds_page_should_error
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_out_of_bounds_page_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = 2;
		$total_pages     = (int) ceil( $this->total_revisions / $per_page );
		$page            = $total_pages + 1; // Out of bound page.
		$expected_error  = 'rest_revision_invalid_page_number';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that impossibly high 'page' query should error.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_invalid_max_pages_should_error
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_invalid_max_pages_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = 2;
		$page            = REST_TESTS_IMPOSSIBLY_HIGH_NUMBER; // Invalid number.
		$expected_error  = 'rest_revision_invalid_page_number';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that the default query should fetch all revisions.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_default_query_should_fetch_all_revisions
	 *
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items_default_query_should_fetch_all_revisions() {
		wp_set_current_user( self::$admin_id );

		$expected_count = $this->total_revisions;

		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Tests that 'offset' query shouldn't work without 'per_page' (fallback -1).
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_offset_should_not_work_without_per_page
	 *
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items_offset_should_not_work_without_per_page() {
		wp_set_current_user( self::$admin_id );

		$offset         = 1;
		$expected_count = $this->total_revisions;

		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_param( 'offset', $offset );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Tests that 'offset' query should work with 'per_page'.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_offset_should_work_with_per_page
	 *
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items_offset_should_work_with_per_page() {
		wp_set_current_user( self::$admin_id );

		$per_page       = 2;
		$offset         = 1;
		$expected_count = 2;

		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Tests that 'offset' query should take priority over 'page'.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_offset_should_take_priority_over_page
	 *
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items_offset_should_take_priority_over_page() {
		wp_set_current_user( self::$admin_id );

		$per_page       = 2;
		$offset         = 1;
		$page           = 1;
		$expected_count = 2;

		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Tests that 'offset' query, as the total revisions count, should return empty data.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_total_revisions_offset_should_return_empty_data
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_total_revisions_offset_should_return_empty_data( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = 2;
		$offset          = $this->total_revisions;
		$expected_error  = 'rest_revision_invalid_offset_number';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that out of bound 'offset' query should error.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_out_of_bound_offset_should_error
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_out_of_bound_offset_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = 2;
		$offset          = $this->total_revisions + 1;
		$expected_error  = 'rest_revision_invalid_offset_number';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that impossible high number for 'offset' query should error.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_impossible_high_number_offset_should_error
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_impossible_high_number_offset_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = 2;
		$offset          = REST_TESTS_IMPOSSIBLY_HIGH_NUMBER;
		$expected_error  = 'rest_revision_invalid_offset_number';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that invalid 'offset' query should error.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_invalid_offset_should_error
	 *
	 * @dataProvider data_readable_http_methods
	 * @ticket 58524
	 * @ticket 56481
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_items_invalid_offset_should_error( $method ) {
		wp_set_current_user( self::$admin_id );

		$per_page        = 2;
		$offset          = 'moreplease';
		$expected_error  = 'rest_invalid_param';
		$expected_status = 400;

		$request = new WP_REST_Request( $method, '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Tests that out of bounds 'page' query should not error when offset is provided,
	 * because it takes precedence.
	 *
	 * Duplicate of WP_Test_REST_Revisions_Controller::test_get_items_out_of_bounds_page_should_not_error_if_offset
	 *
	 * @ticket 58524
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_items_out_of_bounds_page_should_not_error_if_offset() {
		wp_set_current_user( self::$admin_id );

		$per_page       = 2;
		$total_pages    = (int) ceil( $this->total_revisions / $per_page );
		$page           = $total_pages + 1; // Out of bound page.
		$expected_count = 2;

		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => 1,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}


	/**
	 * Tests for the pagination.
	 *
	 * @ticket 62292
	 *
	 * @covers WP_REST_Global_Styles_Controller::get_items
	 */
	public function test_get_global_styles_revisions_pagination() {
		wp_set_current_user( self::$admin_id );

		// Test offset.
		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_param( 'offset', 1 );
		$request->set_param( 'per_page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 3, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 3, $response->get_headers()['X-WP-TotalPages'] );

		// Test paged.
		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 3, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 2, $response->get_headers()['X-WP-TotalPages'] );

		// Test out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id . '/revisions' );
		$request->set_param( 'page', 4 );
		$request->set_param( 'per_page', 6 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_revision_invalid_page_number', $response, 400 );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
		// Controller does not implement get_context_param().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}
}
