<?php
/**
 * Unit tests covering WP_REST_Sidebars_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.8.0
 *
 * @covers WP_REST_Sidebars_Controller
 *
 * @see WP_Test_REST_Controller_Testcase
 * @group restapi
 * @group widgets
 */
class WP_Test_REST_Sidebars_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id  = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$author_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$author_id );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		// Unregister all widgets and sidebars.
		global $wp_registered_sidebars, $_wp_sidebars_widgets;
		$wp_registered_sidebars = array();
		$_wp_sidebars_widgets   = array();
		update_option( 'sidebars_widgets', array() );
	}

	public function clean_up_global_scope() {
		global $wp_widget_factory, $wp_registered_sidebars, $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

		$wp_registered_sidebars        = array();
		$wp_registered_widgets         = array();
		$wp_registered_widget_controls = array();
		$wp_registered_widget_updates  = array();
		$wp_widget_factory->widgets    = array();

		parent::clean_up_global_scope();
	}

	private function setup_widget( $option_name, $number, $settings ) {
		$this->setup_widgets(
			$option_name,
			array(
				$number => $settings,
			)
		);
	}

	private function setup_widgets( $option_name, $settings ) {
		update_option( $option_name, $settings );
	}

	private function setup_sidebar( $id, $attrs = array(), $widgets = array() ) {
		global $wp_registered_sidebars;
		update_option(
			'sidebars_widgets',
			array(
				$id => $widgets,
			)
		);
		$wp_registered_sidebars[ $id ] = array_merge(
			array(
				'id'            => $id,
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			$attrs
		);

		global $wp_registered_widgets;
		foreach ( $wp_registered_widgets as $wp_registered_widget ) {
			if ( is_array( $wp_registered_widget['callback'] ) ) {
				$wp_registered_widget['callback'][0]->_register();
			}
		}
	}

	/**
	 * @ticket 41683
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/sidebars', $routes );
		$this->assertArrayHasKey( '/wp/v2/sidebars/(?P<id>[\w-]+)', $routes );
	}

	/**
	 * @ticket 41683
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items() {
		wp_widgets_init();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( array(), $data );
	}

	/**
	 * @ticket 56481
	 */
	public function test_get_items_with_head_request_should_not_prepare_sidebar_data() {
		wp_widgets_init();

		$request = new WP_REST_Request( 'HEAD', '/wp/v2/sidebars' );

		$hook_name = 'rest_prepare_sidebar';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );

		add_filter( $hook_name, $callback );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );

		$this->assertNotWPError( $response );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The "' . $hook_name . '" filter was called when it should not be for HEAD requests.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_no_permission( $method ) {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( $method, '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 53915
	 */
	public function test_get_items_no_permission_show_in_rest() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name'         => 'Test sidebar',
				'show_in_rest' => true,
			)
		);
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 53915
	 */
	public function test_get_items_without_show_in_rest_are_removed_from_the_list() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name'         => 'Test sidebar 1',
				'show_in_rest' => true,
			)
		);
		$this->setup_sidebar(
			'sidebar-2',
			array(
				'name'         => 'Test sidebar 2',
				'show_in_rest' => false,
			)
		);
		$this->setup_sidebar(
			'sidebar-3',
			array(
				'name'         => 'Test sidebar 3',
				'show_in_rest' => true,
			)
		);
		wp_set_current_user( self::$author_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar 1',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
				array(
					'id'            => 'sidebar-3',
					'name'          => 'Test sidebar 3',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_wrong_permission_author() {
		wp_set_current_user( self::$author_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_basic_sidebar() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'wp_inactive_widgets',
					'name'          => 'Inactive widgets',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'inactive',
					'widgets'       => array(),
				),
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_active_sidebar_with_widgets() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(
						'text-1',
						'rss-1',
					),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 53489
	 */
	public function test_get_items_when_registering_new_sidebars() {
		register_sidebar(
			array(
				'name'          => 'New Sidebar',
				'id'            => 'new-sidebar',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'wp_inactive_widgets',
					'name'          => 'Inactive widgets',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'inactive',
					'widgets'       => array(),
				),
				array(
					'id'            => 'new-sidebar',
					'name'          => 'New Sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 53646
	 */
	public function test_get_items_when_descriptions_have_markup() {
		register_sidebar(
			array(
				'name'          => 'New Sidebar',
				'id'            => 'new-sidebar',
				'description'   => '<iframe></iframe>This is a <b>description</b> with some <a href="#">markup</a>.<script></script>',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'wp_inactive_widgets',
					'name'          => 'Inactive widgets',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'inactive',
					'widgets'       => array(),
				),
				array(
					'id'            => 'new-sidebar',
					'name'          => 'New Sidebar',
					'description'   => 'This is a <b>description</b> with some <a href="#">markup</a>.',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'sidebar-1',
				'name'          => 'Test sidebar',
				'description'   => '',
				'class'         => '',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
				'status'        => 'active',
				'widgets'       => array(),
			),
			$data
		);
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_should_allow_adding_headers_via_filter( $method ) {
		$hook_name = 'rest_prepare_sidebar';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$header_filter = new class() {
			public static function add_custom_header( $response ) {
				$response->header( 'X-Test-Header', 'Test' );

				return $response;
			}
		};
		add_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );

		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( $method, '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );
		remove_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The "' . $hook_name . '" filter was not called when it should be for GET/HEAD requests.' );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-Test-Header', $headers, 'The "X-Test-Header" header should be present in the response.' );
		$this->assertSame( 'Test', $headers['X-Test-Header'], 'The "X-Test-Header" header value should be equal to "Test".' );
		if ( 'HEAD' !== $method ) {
			return null;
		}
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_head_request_with_specified_fields_returns_success_response
	 * @ticket 56481
	 *
	 * @param string $path The path to test.
	 */
	public function test_head_request_with_specified_fields_returns_success_response( $path ) {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'HEAD', $path );
		// This endpoint doesn't seem to support _fields param, but we need to set it to reproduce the fatal error.
		$request->set_param( '_fields', 'name' );
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

			'get_item request'  => array( '/wp/v2/sidebars/sidebar-1' ),
			'get_items request' => array( '/wp/v2/sidebars' ),
		);
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
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_no_permission( $method ) {
		wp_set_current_user( 0 );
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( $method, '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_no_permission_public() {
		wp_set_current_user( 0 );
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name'         => 'Test sidebar',
				'show_in_rest' => true,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'sidebar-1',
				'name'          => 'Test sidebar',
				'description'   => '',
				'class'         => '',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
				'status'        => 'active',
				'widgets'       => array(),
			),
			$data
		);
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 41683
	 * @ticket 56481
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_wrong_permission_author( $method ) {
		wp_set_current_user( self::$author_id );
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( $method, '/wp/v2/sidebars/sidebar-1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * The create_item() method does not exist for sidebar.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_widget(
			'widget_text',
			2,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(
					'text-1',
					'text-2',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'sidebar-1',
				'name'          => 'Test sidebar',
				'description'   => '',
				'class'         => '',
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
				'status'        => 'active',
				'widgets'       => array(
					'text-1',
					'text-2',
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_removes_widget_from_existing_sidebar() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);
		$this->setup_sidebar(
			'sidebar-2',
			array(
				'name' => 'Test sidebar 2',
			),
			array()
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-2' );
		$request->set_body_params(
			array(
				'widgets' => array(
					'text-1',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertContains( 'text-1', $data['widgets'] );

		$this->assertNotContains( 'text-1', rest_do_request( '/wp/v2/sidebars/sidebar-1' )->get_data()['widgets'] );
	}

	/**
	 * @ticket 53612
	 */
	public function test_batch_remove_widgets_from_existing_sidebar() {
		wp_widgets_init();

		$this->setup_widgets(
			'widget_text',
			array(
				2 => array( 'text' => 'Text widget' ),
				3 => array( 'text' => 'Text widget' ),
				4 => array( 'text' => 'Text widget' ),
				5 => array( 'text' => 'Text widget' ),
				6 => array( 'text' => 'Text widget' ),
			)
		);

		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-2', 'text-3', 'text-4', 'text-5', 'text-6' )
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'requests' => array(
					array(
						'method' => 'DELETE',
						'path'   => '/wp/v2/widgets/text-2?force=1',
					),
					array(
						'method' => 'DELETE',
						'path'   => '/wp/v2/widgets/text-3?force=1',
					),
				),
			)
		);
		rest_get_server()->dispatch( $request );

		$this->assertSame(
			array( 'text-4', 'text-5', 'text-6' ),
			rest_do_request( '/wp/v2/sidebars/sidebar-1' )->get_data()['widgets']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_moves_omitted_widget_to_inactive_sidebar() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_widget(
			'widget_text',
			2,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(
					'text-2',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertContains( 'text-2', $data['widgets'] );
		$this->assertNotContains( 'text-1', $data['widgets'] );

		$this->assertContains( 'text-1', rest_do_request( '/wp/v2/sidebars/wp_inactive_widgets' )->get_data()['widgets'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_inactive_widgets() {
		wp_widgets_init();

		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);
		update_option(
			'sidebars_widgets',
			array_merge(
				get_option( 'sidebars_widgets' ),
				array(
					'wp_inactive_widgets' => array( 'rss-1', 'rss' ),
				)
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/sidebars' );
		$request->set_param( 'context', 'view' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				array(
					'id'            => 'sidebar-1',
					'name'          => 'Test sidebar',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'active',
					'widgets'       => array(
						'text-1',
					),
				),
				array(
					'id'            => 'wp_inactive_widgets',
					'name'          => 'Inactive widgets',
					'description'   => '',
					'class'         => '',
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'status'        => 'inactive',
					'widgets'       => array(
						'rss-1',
					),
				),
			),
			$data
		);
	}

	/**
	 * @ticket 57531
	 * @covers WP_Test_REST_Sidebars_Controller::prepare_item_for_response
	 */
	public function test_prepare_item_for_response_to_set_inactive_on_theme_switch() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sidebars/sidebar-1' );

		// Set up the test.
		wp_widgets_init();
		$this->setup_widget(
			'widget_rss',
			1,
			array(
				'title' => 'RSS test',
			)
		);
		$this->setup_widget(
			'widget_text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Sidebar 1',
			),
			array( 'text-1', 'rss-1' )
		);

		// Validate the state before a theme switch.
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );

		$this->assertSame( 'active', $data['status'] );
		$this->assertFalse(
			get_theme_mod( 'wp_classic_sidebars' ),
			'wp_classic_sidebars theme mod should not exist before switching to block theme'
		);

		switch_theme( 'block-theme' );
		wp_widgets_init();

		// Validate the state after a theme switch.
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );

		$this->assertSame(
			'inactive',
			$data['status'],
			'Sidebar status should have changed to inactive'
		);
		$this->assertSame(
			array( 'text-1', 'rss-1' ),
			$data['widgets'],
			'The text and rss widgets should still in sidebar-1'
		);
		$this->assertArrayHasKey(
			'sidebar-1',
			get_theme_mod( 'wp_classic_sidebars' ),
			'sidebar-1 should be in "wp_classic_sidebars" theme mod'
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_no_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_wrong_permission_author() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * The delete_item() method does not exist for sidebar.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * The prepare_item() method does not exist for sidebar.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/sidebars' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'widgets', $properties );
		$this->assertArrayHasKey( 'class', $properties );
		$this->assertArrayHasKey( 'before_widget', $properties );
		$this->assertArrayHasKey( 'after_widget', $properties );
		$this->assertArrayHasKey( 'before_title', $properties );
		$this->assertArrayHasKey( 'after_title', $properties );
		$this->assertCount( 10, $properties );
	}

	/**
	 * Helper to remove links key.
	 *
	 * @param array $data Array of data.
	 *
	 * @return array
	 */
	protected function remove_links( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$count = 0;
		foreach ( $data as $item ) {
			if ( isset( $item['_links'] ) ) {
				unset( $data[ $count ]['_links'] );
			}
			++$count;
		}

		return $data;
	}
}
