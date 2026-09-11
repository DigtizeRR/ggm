<?php
/**
 * Build the locally hosted country-flag SVG symbol sprite.
 *
 * Usage: php scripts/build-country-flag-sprite.php <flag-icons 4x3 directory>
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$source_dir = isset( $argv[1] ) ? rtrim( $argv[1], '/\\' ) : '';
$root_dir   = dirname( __DIR__ );
$builder    = file_get_contents( $root_dir . '/modules/workshop/class-ggm-form-builder.php' );

if ( ! $source_dir || ! is_dir( $source_dir ) ) {
	fwrite( STDERR, "Pass the flag-icons flags/4x3 directory.\n" );
	exit( 1 );
}

if ( ! preg_match( '~\$raw\s*=\s*\'([^\']+)\'\s*;~', $builder, $raw_match ) ) {
	fwrite( STDERR, "Could not find the country calling-code list.\n" );
	exit( 1 );
}

preg_match_all( '/\b([A-Z]{2}):\+\d+\b/', $raw_match[1], $iso_matches );
$country_isos = array_values( array_unique( array_map( 'strtolower', $iso_matches[1] ) ) );

$namespace = 'http://www.w3.org/2000/svg';
$sprite    = new DOMDocument( '1.0', 'UTF-8' );
$sprite->formatOutput = false;
$root = $sprite->createElementNS( $namespace, 'svg' );
$root->setAttribute( 'xmlns', $namespace );
$root->appendChild( $sprite->createComment( 'Country flag SVG symbols from flag-icons 7.5.0 (MIT).' ) );
$sprite->appendChild( $root );

foreach ( $country_isos as $iso ) {
	$source_file = $source_dir . DIRECTORY_SEPARATOR . $iso . '.svg';
	if ( ! is_file( $source_file ) ) {
		fwrite( STDERR, "Missing SVG for {$iso}.\n" );
		exit( 1 );
	}

	$source = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$loaded = $source->load( $source_file, LIBXML_NONET | LIBXML_NOBLANKS );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	if ( ! $loaded || ! $source->documentElement ) {
		fwrite( STDERR, "Invalid SVG for {$iso}.\n" );
		exit( 1 );
	}

	$elements = array();
	foreach ( $source->getElementsByTagName( '*' ) as $element ) {
		$elements[] = $element;
	}

	$id_map = array();
	foreach ( $elements as $element ) {
		if ( $element->hasAttribute( 'id' ) ) {
			$old_id = $element->getAttribute( 'id' );
			$new_id = 'ggm-' . $iso . '-' . $old_id;
			$id_map[ $old_id ] = $new_id;
			$element->setAttribute( 'id', $new_id );
		}
	}

	foreach ( $elements as $element ) {
		foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
			$value = $attribute->value;
			foreach ( $id_map as $old_id => $new_id ) {
				$value = str_replace( 'url(#' . $old_id . ')', 'url(#' . $new_id . ')', $value );
				if ( '#' . $old_id === $value ) {
					$value = '#' . $new_id;
				}
			}
			$attribute->value = $value;
		}
	}

	$source_root = $source->documentElement;
	$symbol = $sprite->createElementNS( $namespace, 'symbol' );
	$symbol->setAttribute( 'id', 'ggm-flag-' . $iso );
	$symbol->setAttribute( 'viewBox', $source_root->getAttribute( 'viewBox' ) ?: '0 0 640 480' );
	if ( $source_root->hasAttribute( 'preserveAspectRatio' ) ) {
		$symbol->setAttribute( 'preserveAspectRatio', $source_root->getAttribute( 'preserveAspectRatio' ) );
	}
	foreach ( iterator_to_array( $source_root->childNodes ) as $child ) {
		$symbol->appendChild( $sprite->importNode( $child, true ) );
	}
	$root->appendChild( $symbol );
}

$output = $root_dir . '/assets/svg/country-flags.svg';
if ( false === file_put_contents( $output, $sprite->saveXML( $sprite->documentElement ) ) ) {
	fwrite( STDERR, "Could not write {$output}.\n" );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Built %d country flag symbols in %s\n", count( $country_isos ), $output ) );
