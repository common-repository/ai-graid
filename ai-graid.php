<?php
/**
 * Plugin Name: AI GrAid
 * Plugin URI: https://aigraid.com/
 * Description: AI Powered essay grading in LearnDash
 * Author: AI GrAid
 * Author URI: https://aigraid.com/
 * Version: 1.0.0
 * Requires PHP: 7.0
 * Tested up to: 6.6
 * Text Domain: ai-graid
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exif if accessed directly.

define( 'AIGA_VERSION', '1.0.0' );
define( 'AIGA_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'AIGA_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'AIGA_SIGNUP_URL', 'https://aigraid.com/register' );

if ( ! file_exists( AIGA_PATH . 'vendor/autoload.php' ) ) {

	add_action( 'admin_notices', function () {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>AI GrAID:</strong>&nbsp;<?php esc_html_e( 'Looks like you are running development version. Please run "composer install" to continue using the plugin.', 'ai-graid' ); ?></p>
        </div>
        <?php
	} );

} else {

	require_once AIGA_PATH . 'vendor/autoload.php';
	new \AIGrAid\Plugin\Boot();
}
