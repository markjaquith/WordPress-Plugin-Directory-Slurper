#!/usr/bin/env php
<?php

$handle = fopen( $argv[1], 'r' ) or die;

$scan_info       = array();
$current_plugin  = '';
$current_count   = 0;
$max_name_length = 0;

function save_current_plugin_info() {
	global $scan_info, $current_plugin, $current_count, $max_name_length;
	if ( $current_count > 0 ) {
		array_push( $scan_info, array(
			'plugin_name' => $current_plugin,
			'matches'     => $current_count,
		) );
		$current_count = 0;
		$max_name_length = max( strlen( $current_plugin ), $max_name_length );
	}
}

function get_http_response_code( $url ) {
	$headers = get_headers( $url);
	return substr( $headers[0], 9, 3 );
}

while ( ( $line = fgets( $handle ) ) !== false ) {
	if ( preg_match( '#^(plugins/)?([^/]+)/#', $line, $match ) ) {
		$plugin = $match[2];
		if ( $plugin !== $current_plugin ) {
			save_current_plugin_info();
			$current_plugin = $plugin;
		}
		$current_count++;
	}
}

fclose( $handle );

save_current_plugin_info();

$num_results = count( $scan_info );
fwrite( STDERR, sprintf(
	"%d matching plugin%s\n",
	$num_results,
	( $num_results === 1 ? '' : 's' )
) );

echo 'Matches  ' . str_pad( 'Plugin', $max_name_length - 3 ) . "Active installs\n";
echo '=======  ' . str_pad( '======', $max_name_length - 3 ) . "===============\n";

foreach ( $scan_info as $plugin ) {
	ini_set( 'user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:80.0) Gecko/20100101 Firefox/80.0' );
	$api_url = "https://api.wordpress.org/plugins/info/1.1/?action=plugin_information&request[slug]=$plugin[plugin_name]&request[fields][active_installs]=1";

	if ( get_http_response_code( $api_url ) != "200" ){
		$result = false;
	} else {
		$result = json_decode( $api_url );
	}

	if ( $result ) {
		$active_installs = str_pad(
			number_format( $result->active_installs ),
			9, ' ', STR_PAD_LEFT
		) . '+';
	} else {
		// The plugins API returns `null` for nonexistent/removed plugins
		$active_installs = '   REMOVED';
	}
	echo str_pad( $plugin['matches'], 7, ' ', STR_PAD_LEFT )
		. '  '
		. str_pad( $plugin['plugin_name'], $max_name_length )
		. '  '
		. "$active_installs\n";
}
