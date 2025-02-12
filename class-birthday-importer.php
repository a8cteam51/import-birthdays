<?php
/**
 * Plugin Name: Import Birthdays
 * Description: Import user birthdays from CSV file
 * Version: 1.0.0
 * Author: WP Special Projects
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class for importing birthdays
 */
class Birthday_Importer {
	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_process_birthday_import', array( $this, 'process_birthday_import' ) );

		// Add filter for plugin action links.
		add_filter(
			'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'import-birthdays.php' ),
			array( $this, 'add_action_links' )
		);
	}

	/**
	 * Add menu item under Tools
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'Import Birthdays', 'import-birthdays' ),
			__( 'Import Birthdays', 'import-birthdays' ),
			'manage_options',
			'import-birthdays',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue necessary scripts and styles
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'tools_page_import-birthdays' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'birthday-importer',
			plugin_dir_url( __FILE__ ) . 'css/importer.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'birthday-importer',
			plugin_dir_url( __FILE__ ) . 'js/importer.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'birthday-importer',
			'birthdayImporter',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'birthday_import_nonce' ),
			)
		);
	}

	/**
	 * Render the admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'import-birthdays' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Birthdays', 'import-birthdays' ); ?></h1>

			<div class="birthday-import-help">
				<a href="#" class="help-toggle">
					<?php esc_html_e( 'Show usage instructions', 'import-birthdays' ); ?>
				</a>
				<div class="help-content" style="display: none;">
					<h3><?php esc_html_e( 'How to Use the Birthday Importer', 'import-birthdays' ); ?></h3>

					<h4><?php esc_html_e( 'CSV File Format', 'import-birthdays' ); ?></h4>
					<p><?php esc_html_e( 'Your CSV file must:', 'import-birthdays' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Include a header row', 'import-birthdays' ); ?></li>
						<li><?php esc_html_e( 'Contain "user_id" and "birthday" columns', 'import-birthdays' ); ?></li>
						<li><?php esc_html_e( 'Use YYYY-MM-DD format for dates', 'import-birthdays' ); ?></li>
					</ul>

					<h4><?php esc_html_e( 'Example CSV Content:', 'import-birthdays' ); ?></h4>
					<pre>user_id,birthday
1,1990-01-15
2,1985-12-25</pre>

					<h4><?php esc_html_e( 'Process', 'import-birthdays' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Prepare your CSV file according to the format above', 'import-birthdays' ); ?></li>
						<li><?php esc_html_e( 'Click "Choose File" and select your CSV', 'import-birthdays' ); ?></li>
						<li><?php esc_html_e( 'Click "Import Now" to begin the import', 'import-birthdays' ); ?></li>
						<li><?php esc_html_e( 'Wait for the progress bar to complete', 'import-birthdays' ); ?></li>
						<li><?php esc_html_e( 'Review the results summary', 'import-birthdays' ); ?></li>
					</ol>
				</div>
			</div>

			<div class="birthday-import-form">
				<form id="birthday-import-form" method="post" enctype="multipart/form-data">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="birthday_csv"><?php esc_html_e( 'CSV File', 'import-birthdays' ); ?></label>
							</th>
							<td>
								<input type="file"
									name="birthday_csv"
									id="birthday_csv"
									accept=".csv"
									required>
								<p class="description">
									<?php esc_html_e( 'CSV file should contain user_id and birthday columns.', 'import-birthdays' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="import-controls">
						<?php wp_nonce_field( 'birthday_import_nonce', 'birthday_import_nonce' ); ?>
						<button type="submit" class="button button-primary" id="start-import">
							<?php esc_html_e( 'Import Now', 'import-birthdays' ); ?>
						</button>
					</div>
				</form>

				<div id="import-progress" style="display: none;">
					<div class="progress-bar-wrapper">
						<div class="progress-bar"></div>
					</div>
					<div class="progress-text"></div>
				</div>

				<div id="import-results" style="display: none;">
					<h3><?php esc_html_e( 'Import Results', 'import-birthdays' ); ?></h3>
					<div class="results-content"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the AJAX import request
	 */
	public function process_birthday_import() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'birthday_import_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => '<p class="import-error">Security verification failed. Please refresh the page and try again.</p>',
				)
			);
		}

		// Verify user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => '<p class="import-error">You do not have permission to perform this action.</p>',
				)
			);
		}

		// Check if file was uploaded.
		if ( empty( $_FILES['birthday_csv'] ) ) {
			wp_send_json_error(
				array(
					'message' => '<p class="import-error">No file was selected for upload.</p>',
				)
			);
		}

		$file = $_FILES['birthday_csv'];

		// Basic file validation.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			$error_message = $this->get_upload_error_message( $file['error'] );
			wp_send_json_error(
				array(
					'message' => sprintf( '<p class="import-error">%s</p>', esc_html( $error_message ) ),
				)
			);
		}

		// Verify file type.
		$file_type = wp_check_filetype( $file['name'] );
		if ( 'csv' !== $file_type['ext'] ) {
			wp_send_json_error(
				array(
					'message' => '<p class="import-error">Please upload a valid CSV file.</p>',
				)
			);
		}

		// Create uploads directory if it doesn't exist.
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . '/birthday-imports';
		if ( ! file_exists( $import_dir ) ) {
			wp_mkdir_p( $import_dir );
		}

		// Create .htaccess to prevent direct access.
		$htaccess_file = $import_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, 'deny from all' );
		}

		// Move uploaded file to our processing directory.
		$temp_file = $import_dir . '/' . sanitize_file_name( $file['name'] );
		move_uploaded_file( $file['tmp_name'], $temp_file );

		// Start output buffering to capture import messages.
		ob_start();

		try {
			$this->import_birthdays_from_csv( $temp_file );
			$import_results = ob_get_clean();

			// Clean up.
			unlink( $temp_file );

			wp_send_json_success(
				array(
					'message' => $import_results,
				)
			);

		} catch ( Exception $e ) {
			ob_end_clean();
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}

			wp_send_json_error(
				array(
					'message' => sprintf(
						'<p class="import-error">Import failed: %s</p>',
						esc_html( $e->getMessage() )
					),
				)
			);
		}
	}

	/**
	 * Import birthdays from CSV file
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return void
	 */
	public function import_birthdays_from_csv( $file_path ) {
		// Check if file exists.
		if ( ! file_exists( $file_path ) ) {
			wp_die( '<p class="import-error">Error: File not found.</p>' );
		}

		// Open file.
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			wp_die( '<p class="import-error">Error: Could not open file for reading.</p>' );
		}

		// Read headers.
		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			wp_die( '<p class="import-error">Error: Could not read CSV headers.</p>' );
		}

		// Find required columns.
		$user_id_col  = array_search( 'user_id', array_map( 'strtolower', $headers ), true );
		$birthday_col = array_search( 'birthday', array_map( 'strtolower', $headers ), true );

		if ( false === $user_id_col || false === $birthday_col ) {
			fclose( $handle );
			wp_die( '<p class="import-error">Error: CSV must contain \'user_id\' and \'birthday\' columns.</p>' );
		}

		$row           = 2; // Start at row 2 (after headers).
		$success_count = 0;
		$error_count   = 0;
		$results       = array();

		// Process each row.
		$data = fgetcsv( $handle );
		while ( false !== $data ) {
			$user_id  = isset( $data[ $user_id_col ] ) ? absint( $data[ $user_id_col ] ) : 0;
			$birthday = isset( $data[ $birthday_col ] ) ? trim( $data[ $birthday_col ] ) : '';

			// Skip invalid user IDs
			if ( empty( $user_id ) ) {
				$results[] = sprintf(
					'<p class="import-error">Error on row %d: Invalid user ID format</p>',
					$row
				);
				$error_count++;
				$row++;
				$data = fgetcsv( $handle );
				continue;
			}

			// Validate user exists - using get_userdata() which is more memory efficient
			$user = get_userdata( $user_id );
			if ( ! $user instanceof WP_User ) {
				$results[] = sprintf(
					'<p class="import-error">Error on row %d: User ID %d not found</p>',
					$row,
					$user_id
				);
				$error_count++;
				$row++;
				$data = fgetcsv( $handle );
				continue;
			}

			// Parse birthday
			$date = DateTime::createFromFormat( 'Y-m-d', $birthday );
			if ( ! $date ) {
				$results[] = sprintf(
					'<p class="import-error">Error on row %d: Invalid date format for %s</p>',
					$row,
					esc_html( $birthday )
				);
				$error_count++;
				$row++;
				$data = fgetcsv( $handle );
				continue;
			}

			try {
				// Extract day, month, year.
				$day   = $date->format( 'd' );
				$month = $date->format( 'm' );
				$year  = $date->format( 'Y' );

				// Set birthday using AutomateWoo Birthdays addon.
				AW_Birthdays()->set_user_birthday( $user_id, $day, $month, $year );
				$results[] = sprintf(
					'<p class="import-success">Success: Birthday set for user %d</p>',
					$user_id
				);
				$success_count++;
			} catch ( Exception $e ) {
				$results[] = sprintf(
					'<p class="import-error">Error on row %d: %s</p>',
					$row,
					esc_html( $e->getMessage() )
				);
				$error_count++;
			}

			$row++;
			$data = fgetcsv( $handle );
		}

		fclose( $handle );

		// Output results.
		echo '<div class="import-results">';
		echo wp_kses_post( implode( '', $results ) );
		echo '<div class="import-summary">';
		echo '<h4>Import Summary</h4>';
		echo sprintf(
			'<p>Successful imports: <strong>%s</strong></p>',
			esc_html( $success_count )
		);
		echo sprintf(
			'<p>Failed imports: <strong>%s</strong></p>',
			esc_html( $error_count )
		);
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Add action links to the plugin listing page
	 *
	 * @param array $actions An array of plugin action links.
	 * @return array
	 */
	public function add_action_links( $actions ) {
		$import_link = array(
			'import' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=import-birthdays' ) ),
				esc_html__( 'Run now', 'import-birthdays' )
			),
		);

		return array_merge( $import_link, $actions );
	}

	/**
	 * Get human-readable upload error message
	 *
	 * @param int $error_code PHP file upload error code.
	 * @return string
	 */
	private function get_upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
				return 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
			case UPLOAD_ERR_FORM_SIZE:
				return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.';
			case UPLOAD_ERR_PARTIAL:
				return 'The uploaded file was only partially uploaded.';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Missing a temporary folder.';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk.';
			case UPLOAD_ERR_EXTENSION:
				return 'A PHP extension stopped the file upload.';
			default:
				return 'Unknown upload error.';
		}
	}
}

// Initialize the plugin
new Birthday_Importer();
