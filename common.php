<?php

require_once 'formatting.php';

function get_directory_by_type( $type ) {
	switch ( $type ) {
		case 'readme':
			return 'readmes';
		case 'all':
			return 'plugins';
	}
}

function read_last_revision( $type ) {
	$directory = get_directory_by_type( $type );

	if ( file_exists( $directory . '/.last-revision' ) ) {
		return (int) file_get_contents( $directory . '/.last-revision' );
	} else {
		return 0;
	}
}

function write_last_revision( $type, $revision ) {
	$directory = get_directory_by_type( $type );

	file_put_contents(
		$directory . '/.last-revision',
		"$revision\n"
	);
}

function download_plugins( $type, $plugin_names, $is_partial_sync ) {
	try {
		return download_plugins_internal( $type, $plugin_names, $is_partial_sync );
	} catch ( Exception $e ) {
		echo $e->getMessage() . "\n";
		exit( 1 );
	}
}

function download_plugins_internal( $type, $plugin_names, $is_partial_sync ) {
	// Number of simultaneous downloads
	global $parallel;

	// Data structures defined previously for partial sync
	global $plugins, $revisions;

	if ( $is_partial_sync ) {
		$current_revision = $revisions[ count( $revisions ) - 1 ]['number'];
	}

	$stats = array(
		'total'   => count( $plugin_names ),
		'updated' => 0,
		'failed'  => 0,
	);

	$download_path = get_directory_by_type( $type ) . '/.to_download';
	file_put_contents(
		$download_path,
		implode( "\n", $plugin_names )
	);

	// Start `xargs` to process plugin downloads in parallel.
	$descriptors = array(
		0 => array( 'file', $download_path, 'r' ), // `xargs` will read from this file
		1 => array( 'pipe', 'w' ),                 // `xargs` will write to stdout
		2 => STDERR,
	);
	$xargs = proc_open(
		"xargs -n 1 -P $parallel ./download $type",
		$descriptors,
		$pipes
	);

	// Track which plugins are in progress and when they were started
	$in_progress = array();

	// Process output from `./download` script instances (newline-delimited
	// JSON messages).
	while ( ( $line = fgets( $pipes[1] ) ) !== false ) {
		$line = trim( $line );
		$data = json_decode( $line, true );
		if ( ! $data || ! $data['type'] || ! $data['plugin'] ) {
			throw new Exception(
				"Invalid progress update message: $line"
			);
		}

		$plugin = $data['plugin'];

		switch ( $data['type'] ) {
			case 'start':
				$in_progress[ $plugin ] = array(
					'started'       => time(),
					'download_path' => $data['download_path'],
					'download_url'  => $data['download_url'],
				);
				// No further action; go back to while() above
				continue 2;
			case 'done':
				$status = ' OK ';
				$stats['updated']++;
				unset( $in_progress[ $plugin ] );
				break;
			case 'fail':
				$status = 'FAIL';
				$stats['failed']++;
				file_put_contents(
					get_directory_by_type( $type ) . '/.failed_downloads',
					"$plugin\n",
					FILE_APPEND
				);
				unset( $in_progress[ $plugin ] );
				break;
			case 'error':
				throw new Exception(
					'Error from download script: ' . $data['details']
				);
			default:
				throw new Exception(
					'Unrecognized update type: ' . $data['type']
				);
		}

		$percent = str_pad(
			number_format(
				100 * ( $stats['updated'] + $stats['failed'] ) / $stats['total'],
				1
			) . '%',
			6, ' ', STR_PAD_LEFT
		) . '%'; // sprintf placeholder

		$message1 = "[$status] $percent  %s";
		$message2 = null;
		$m_plugin2 = null;

		if ( $is_partial_sync ) {
			// Look through each revision associated with this plugin and
			// un-mark the plugin as having a pending update.
			foreach ( $plugins[ $plugin ] as $index ) {
				unset( $revisions[ $index ]['to_update'][ $plugin ] );
			}
			// Look for revisions that have no more plugins left to update.
			$last_revision = $current_revision;
			for ( $i = count( $revisions ) - 1; $i >= 0; $i-- ) {
				if ( empty( $revisions[ $i ]['to_update'] ) ) {
					$current_revision = $revisions[ $i ]['number'];
					array_pop( $revisions );
				} else {
					break;
				}
			}
			if ( $current_revision !== $last_revision ) {
				$message2 = "-> local copy now at r$current_revision";
				write_last_revision( $type, $current_revision );
			}
		}

		if ( $is_partial_sync && ! $message2 ) {
			// The svn revision of the local copy should advance throughout a
			// partial sync, but sometimes this takes a while when we're
			// waiting on a large download.  Try to show progress in this case.
			$rev_waiting = $revisions[ count( $revisions ) - 1 ];
			foreach ( $in_progress as $p_plugin => &$p_info ) {
				if (
					isset( $rev_waiting['to_update'][ $p_plugin ] ) &&
					time() > $p_info['started'] + 30
				) {
					if ( ! isset( $p_info['size'] ) ) {
						// Do a HEAD request for the plugin zip
						exec(
							"wget '$p_info[download_url]' --spider 2>&1",
							$p_output
						);
						$match = preg_match(
							'#^Length: ([0-9]+) #m',
							implode( "\n", $p_output ),
							$p_size
						);
						$p_info['size'] = $match ? (int) $p_size[1] : 0;
						// "Note that if the array already contains some
						// elements, exec() will append to the end of the
						// array."  Yay PHP!
						unset( $p_output, $p_size );
					}
					$p_percent = '';
					if ( ! empty( $p_info['size'] ) ) {
						clearstatcache();
						$file_size = @filesize( $p_info['download_path'] );
						$p_percent = ' '
							. floor( $file_size * 100 / $p_info['size'] )
							. '%%';
					}
					$message2 = "[%s$p_percent]";
					$m_plugin2 = $p_plugin;
				}
			}
			unset( $p_info );
		}

		echo fit_message( $message1, $plugin, $message2, $m_plugin2 ) . "\n";
	}

	fclose( $pipes[1] );

	$status = proc_get_status( $xargs );
	if ( $status['running'] ) {
		throw new Exception(
			'xargs should not still be running'
		);
	}
	if ( $status['exitcode'] ) {
		throw new Exception(
			'unexpected xargs exit code: ' . $status['exitcode']
		);
	}

	proc_close( $xargs );

	return $stats;
}
