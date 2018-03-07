<?php

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
	// Data structures defined previously for partial sync
	global $plugins, $revisions;

	$current_revision = $revisions[ count( $revisions ) - 1 ]['number'];

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
		'xargs -n 1 -P 12 ./download ' . $type,
		$descriptors,
		$pipes
	);

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
				// Ignored
				continue 2;
			case 'done':
				$status = ' OK ';
				$stats['updated']++;
				break;
			case 'fail':
				$status = 'FAIL';
				$stats['failed']++;
				file_put_contents(
					get_directory_by_type( $type ) . '/.failed_downloads',
					"$plugin\n",
					FILE_APPEND
				);
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
		);

		$revision_progress = '';
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
				$revision_progress = "  (local copy now at r$current_revision)";
				write_last_revision( $type, $current_revision );
			}
		}

		echo "[$status] $percent  $plugin$revision_progress\n";
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
