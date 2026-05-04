<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Site_Nuke_Filesystem {

	public function chunked_nuke( $user_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return array( 'status' => 'error', 'message' => 'Filesystem access denied.' );
		}

		$queue = get_transient( 'site_nuke_fs_queue_' . $user_id );
		if ( false === $queue ) {
			$upload_dir = wp_upload_dir();
			$queue = array(
				wp_normalize_path( $upload_dir['basedir'] ),
				wp_normalize_path( WP_PLUGIN_DIR ),
				wp_normalize_path( get_theme_root() )
			);
		}

		$start_time = microtime( true );
		$deleted_count = 0;

		// Normalize paths to ensure Windows/Linux slash compatibility
		$our_plugin_dir   = wp_normalize_path( WP_PLUGIN_DIR . '/' . dirname( SITE_NUKE_BASENAME ) );
		$active_theme_dir = wp_normalize_path( get_theme_root() . '/' . get_stylesheet() );

		$roots = array(
			wp_normalize_path( wp_upload_dir()['basedir'] ),
			wp_normalize_path( WP_PLUGIN_DIR ),
			wp_normalize_path( get_theme_root() )
		);

		while ( ! empty( $queue ) && ( microtime( true ) - $start_time ) < 2.0 ) {
			$raw_path = array_shift( $queue );
			$path = wp_normalize_path( $raw_path );

			if ( ! file_exists( $path ) ) continue;

			// Skip our plugin and the active theme completely
			if ( $path === $our_plugin_dir || $path === $active_theme_dir ) {
				continue;
			}

			if ( is_dir( $path ) ) {
				$files = scandir( $path );
				$has_deletable = false;

				if ( is_array( $files ) ) {
					foreach ( $files as $file ) {
						if ( '.' === $file || '..' === $file ) continue;

						$full_path = wp_normalize_path( $path . '/' . $file );

						// Skip index.php in roots
						if ( 'index.php' === $file && in_array( $path, $roots, true ) ) {
							continue;
						}
						// Skip our plugin and active theme
						if ( $full_path === $our_plugin_dir || $full_path === $active_theme_dir ) {
							continue;
						}

						$has_deletable = true;
						// Add contents to the FRONT of the queue to drill down
						array_unshift( $queue, $full_path );
					}
				}

				// If it is NOT a root directory
				if ( ! in_array( $path, $roots, true ) ) {
					if ( ! $has_deletable ) {
						// Directory is empty (or only has ignored files), nuke it!
						$wp_filesystem->delete( $path, true );
						$deleted_count++;
					} else {
						// Directory has files, push it to the BACK of the line so it deletes later
						array_push( $queue, $path );
					}
				}
			} else {
				// It's a file.
				$wp_filesystem->delete( $path );
				$deleted_count++;
			}
		}

		if ( empty( $queue ) ) {
			delete_transient( 'site_nuke_fs_queue_' . $user_id );
			return array( 'status' => 'complete', 'deleted' => $deleted_count );
		} else {
			set_transient( 'site_nuke_fs_queue_' . $user_id, $queue, HOUR_IN_SECONDS );
			return array( 'status' => 'processing', 'deleted' => $deleted_count );
		}
	}
}