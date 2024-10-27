<?php

namespace AIGrAid\Plugin;

class Log {

	public static function info( $message ) {
		self::log( 'info', $message );
	}


	public static function log( $type, $message ) {
		$dir  = wp_upload_dir();
		$path = $dir['basedir'] . DIRECTORY_SEPARATOR . 'aiga' . DIRECTORY_SEPARATOR;
		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
		}
		$message = is_scalar( $message ) ? $message : print_r( $message, true );
		$message = sprintf( "[%s] %s", date( 'Y-m-d H:i:s' ), $message );
		self::write( $path . strtolower( $type ) . '.log', $message );
	}

	public static function write( $file, $contents, $force_flag = '' ) {
		if ( file_exists( $file ) ) {
			$flag = $force_flag !== '' ? $force_flag : 'a';
			$fp   = fopen( $file, $flag );
			fwrite( $fp, $contents . PHP_EOL );
		} else {
			$flag = $force_flag !== '' ? $force_flag : 'w';
			$fp   = fopen( $file, $flag );
			fwrite( $fp, $contents . PHP_EOL );
		}
		if ( is_resource( $fp ) ) {
			fclose( $fp );
		}
	}


}