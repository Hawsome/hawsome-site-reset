<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class hawsome_reset_Filesystem {

	public function chunked_wipe( $user_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return array( 'status' => 'error', 'message' => __( 'Filesystem access denied.', 'hawsome-site-reset' ) );
		}

		$queue = get_transient( 'hawsome_reset_fs_queue_' . $user_id );
		
		if ( false === $queue ) {
			// Seed the queue with the absolute root of wp-content
			$queue = array( wp_normalize_path( WP_CONTENT_DIR ) );

			// Recursively delete non-default folders and cache drop-ins
			$drop_ins = array( 'advanced-cache.php', 'objectcache.php', 'db.php', 'maintenance.php', 'sunrise.php', 'upgrade', 'cache' );
			foreach ( $drop_ins as $drop_in ) {
				$drop_in_path = wp_normalize_path( WP_CONTENT_DIR . '/' . $drop_in );
				if ( $wp_filesystem->exists( $drop_in_path ) ) {
					// The 'true' parameter guarantees recursive deletion of folders
					$wp_filesystem->delete( $drop_in_path, true );
				}
			}
		}

		$start_time = microtime( true );
		$deleted_count = 0;

		$our_plugin_dir   = wp_normalize_path( WP_PLUGIN_DIR . '/' . dirname( hawsome_reset_BASENAME ) );
		$active_theme_dir = wp_normalize_path( get_theme_root() . '/' . get_stylesheet() );

		// Define all critical WordPress content roots so their native index.php files are spared
		$roots = array(
			wp_normalize_path( WP_CONTENT_DIR ),
			wp_normalize_path( wp_upload_dir()['basedir'] ),
			wp_normalize_path( WP_PLUGIN_DIR ),
			wp_normalize_path( get_theme_root() )
		);

		while ( ! empty( $queue ) && ( microtime( true ) - $start_time ) < 2.0 ) {
			$raw_path = array_shift( $queue );
			$path = wp_normalize_path( $raw_path );

			if ( ! file_exists( $path ) ) continue;

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

						if ( 'index.php' === $file && in_array( $path, $roots, true ) ) continue;
						if ( $full_path === $our_plugin_dir || $full_path === $active_theme_dir ) continue;

						$has_deletable = true;
						array_unshift( $queue, $full_path );
					}
				}

				if ( ! in_array( $path, $roots, true ) ) {
					if ( ! $has_deletable ) {
						$wp_filesystem->delete( $path, true );
						$deleted_count++;
					} else {
						array_push( $queue, $path );
					}
				}
			} else {
				$wp_filesystem->delete( $path );
				$deleted_count++;
			}
		}

		if ( empty( $queue ) ) {
			delete_transient( 'hawsome_reset_fs_queue_' . $user_id );
			return array( 'status' => 'complete', 'deleted' => $deleted_count );
		} else {
			set_transient( 'hawsome_reset_fs_queue_' . $user_id, $queue, HOUR_IN_SECONDS );
			return array( 'status' => 'processing', 'deleted' => $deleted_count );
		}
	}
}