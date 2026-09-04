<?php
/**
 * Plugin Name: IDR Discography
 * Description: Datamodel, import-API en legacy-redirects voor het Ilse DeLange Records-archief.
 * Version: 0.1.0
 * Author: Jengo
 * Text Domain: idr
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/idr-i18n.php';

const IDR_KINDS = [
	'release'    => 'idr_release',
	'song'       => 'idr_song',
	'artist'     => 'idr_artist',
	'appearance' => 'idr_appearance',
	'page'       => 'idr_page',
];

add_action( 'init', function () {
	$common = [
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => true,
		'supports'     => [ 'title', 'editor', 'custom-fields' ],
		'menu_icon'    => 'dashicons-album',
	];
	register_post_type( 'idr_release', $common + [
		'labels'  => [ 'name' => 'Releases', 'singular_name' => 'Release' ],
		'rewrite' => [ 'slug' => 'release' ],
	] );
	register_post_type( 'idr_song', $common + [
		'labels'  => [ 'name' => 'Songs', 'singular_name' => 'Song' ],
		'rewrite' => [ 'slug' => 'song' ],
		'menu_icon' => 'dashicons-format-audio',
	] );
	register_post_type( 'idr_artist', $common + [
		'labels'      => [ 'name' => 'Artiesten', 'singular_name' => 'Artiest' ],
		'rewrite'     => [ 'slug' => 'artist' ],
		'has_archive' => false,
		'menu_icon'   => 'dashicons-groups',
	] );
	register_post_type( 'idr_appearance', $common + [
		'labels'  => [ 'name' => 'Gastbijdragen', 'singular_name' => 'Gastbijdrage' ],
		'rewrite' => [ 'slug' => 'appearance' ],
		'menu_icon' => 'dashicons-share-alt2',
	] );
	register_post_type( 'idr_page', $common + [
		'labels'      => [ 'name' => 'Archiefpagina\'s', 'singular_name' => 'Archiefpagina' ],
		'rewrite'     => [ 'slug' => 'archive-page' ],
		'has_archive' => false,
		'menu_icon'   => 'dashicons-media-document',
	] );

	register_taxonomy( 'idr_section', array_values( IDR_KINDS ), [
		'labels'       => [ 'name' => 'Secties', 'singular_name' => 'Sectie' ],
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => false,
		'rewrite'      => [ 'slug' => 'section' ],
	] );
	register_taxonomy( 'idr_artist_tax', array_values( IDR_KINDS ), [
		'labels'       => [ 'name' => 'Artiest (facet)', 'singular_name' => 'Artiest (facet)' ],
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => false,
		'rewrite'      => [ 'slug' => 'by-artist' ],
	] );
	register_taxonomy( 'idr_format', [ 'idr_release' ], [
		'labels'       => [ 'name' => 'Formaten', 'singular_name' => 'Formaat' ],
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => false,
		'rewrite'      => [ 'slug' => 'format' ],
	] );
} );

/** Meta die de templates en de browse-API gebruiken. Alles komt uit de astro-typed-data. */
const IDR_META_KEYS = [
	'_idr_id', '_idr_kind', '_idr_year', '_idr_released_text', '_idr_label', '_idr_catalog_number',
	'_idr_format', '_idr_spotify_url', '_idr_spotify_name', '_idr_cover', '_idr_media',
	'_idr_info_html', '_idr_info_of', '_idr_related', '_idr_language', '_idr_has_lyrics',
	'_idr_display_title', '_idr_legacy_filename', '_idr_source_description', '_idr_variant_of',
];

// ── Import-API ───────────────────────────────────────────────────────────────

function idr_import_authorized( WP_REST_Request $req ) {
	if ( ! defined( 'IDR_IMPORT_TOKEN' ) || IDR_IMPORT_TOKEN === '' ) { return false; }
	return hash_equals( IDR_IMPORT_TOKEN, (string) $req->get_header( 'x-idr-token' ) );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'idr/v1', '/import', [
		'methods'             => 'POST',
		'permission_callback' => 'idr_import_authorized',
		'callback'            => 'idr_handle_import',
	] );
	register_rest_route( 'idr/v1', '/import-meta', [
		'methods'             => 'POST',
		'permission_callback' => 'idr_import_authorized',
		'callback'            => 'idr_handle_import_meta',
	] );
	register_rest_route( 'idr/v1', '/status', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'idr_handle_status',
	] );
	register_rest_route( 'idr/v1', '/browse', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'idr_handle_browse',
	] );
} );

/** Upsert een batch records. Idempotent op _idr_id. */
function idr_handle_import( WP_REST_Request $req ) {
	// De import draait zonder ingelogde gebruiker; zonder dit stript kses de
	// legacy-iframes (YouTube-embeds) uit de content. Het endpoint is token-beveiligd.
	kses_remove_filters();
	$items   = $req->get_json_params()['items'] ?? [];
	$results = [];
	foreach ( $items as $item ) {
		$type = IDR_KINDS[ $item['kind'] ] ?? null;
		if ( ! $type ) {
			$results[] = [ 'id' => $item['id'] ?? '?', 'error' => 'unknown kind' ];
			continue;
		}
		$existing = idr_post_by_idr_id( $item['id'] );
		$postarr  = [
			'post_type'    => $type,
			'post_title'   => $item['title'],
			'post_name'    => $item['slug'],
			'post_status'  => 'publish',
			'post_content' => $item['content'] ?? '',
			'post_excerpt' => $item['excerpt'] ?? '',
		];
		if ( $existing ) { $postarr['ID'] = $existing->ID; }
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			$results[] = [ 'id' => $item['id'], 'error' => $post_id->get_error_message() ];
			continue;
		}
		update_post_meta( $post_id, '_idr_id', $item['id'] );
		update_post_meta( $post_id, '_idr_kind', $item['kind'] );
		foreach ( ( $item['meta'] ?? [] ) as $key => $value ) {
			$meta_key = '_idr_' . $key;
			if ( ! in_array( $meta_key, IDR_META_KEYS, true ) ) { continue; }
			if ( $value === null || $value === '' || $value === [] ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, wp_slash( is_scalar( $value ) ? $value : wp_json_encode( $value ) ) );
			}
		}
		if ( isset( $item['section'] ) ) { wp_set_object_terms( $post_id, $item['section'] ? [ $item['section'] ] : [], 'idr_section' ); }
		if ( isset( $item['artist'] ) ) { wp_set_object_terms( $post_id, $item['artist'] ? [ $item['artist'] ] : [], 'idr_artist_tax' ); }
		if ( isset( $item['format'] ) ) { wp_set_object_terms( $post_id, $item['format'] ? [ $item['format'] ] : [], 'idr_format' ); }
		$results[] = [ 'id' => $item['id'], 'post_id' => $post_id, 'updated' => (bool) $existing ];
	}
	return [ 'imported' => count( array_filter( $results, fn( $r ) => isset( $r['post_id'] ) ) ), 'results' => $results ];
}

/** Site-brede importdata: legacy-routes (redirects) en navigatiesecties. */
function idr_handle_import_meta( WP_REST_Request $req ) {
	$body = $req->get_json_params();
	if ( isset( $body['legacy_routes'] ) ) { update_option( 'idr_legacy_routes', $body['legacy_routes'], false ); }
	if ( isset( $body['sections'] ) ) { update_option( 'idr_sections', $body['sections'], false ); }
	if ( isset( $body['report'] ) ) { update_option( 'idr_import_report', $body['report'], false ); }
	return [ 'ok' => true, 'routes' => count( $body['legacy_routes'] ?? [] ) ];
}

function idr_post_by_idr_id( $idr_id ) {
	$q = new WP_Query( [
		'post_type'      => array_values( IDR_KINDS ),
		'meta_key'       => '_idr_id',
		'meta_value'     => $idr_id,
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'no_found_rows'  => true,
	] );
	return $q->posts[0] ?? null;
}

function idr_handle_status() {
	$counts = [];
	foreach ( IDR_KINDS as $kind => $type ) {
		$counts[ $kind ] = (int) wp_count_posts( $type )->publish;
	}
	$counts['legacy_routes'] = count( get_option( 'idr_legacy_routes', [] ) );
	return $counts;
}

/** Lichte index van alle releases + songs voor de browse-island (één call). */
function idr_handle_browse() {
	$cached = get_transient( 'idr_browse_index' );
	if ( $cached ) { return $cached; }
	$out = [ 'releases' => [], 'songs' => [] ];
	foreach ( [ 'idr_release' => 'releases', 'idr_song' => 'songs' ] as $type => $bucket ) {
		$posts = get_posts( [ 'post_type' => $type, 'posts_per_page' => -1, 'post_status' => 'publish' ] );
		foreach ( $posts as $p ) {
			$entry = [
				'title'   => html_entity_decode( get_post_meta( $p->ID, '_idr_display_title', true ) ?: $p->post_title ),
				'url'     => get_permalink( $p ),
				'artist'  => wp_get_post_terms( $p->ID, 'idr_artist_tax', [ 'fields' => 'names' ] )[0] ?? '',
				'section' => wp_get_post_terms( $p->ID, 'idr_section', [ 'fields' => 'names' ] )[0] ?? '',
				'year'    => (int) get_post_meta( $p->ID, '_idr_year', true ) ?: null,
				'cover'   => get_post_meta( $p->ID, '_idr_cover', true ) ?: null,
			];
			if ( 'idr_release' === $type ) {
				$entry['format'] = get_post_meta( $p->ID, '_idr_format', true ) ?: 'unknown';
				$entry['label']  = get_post_meta( $p->ID, '_idr_label', true ) ?: null;
			} else {
				$entry['hasLyrics'] = (bool) get_post_meta( $p->ID, '_idr_has_lyrics', true );
				$entry['language']  = get_post_meta( $p->ID, '_idr_language', true ) ?: null;
			}
			$out[ $bucket ][] = $entry;
		}
	}
	set_transient( 'idr_browse_index', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

add_action( 'save_post', function () { delete_transient( 'idr_browse_index' ); } );

// ── Legacy redirects (oude WebPlus-URL's → nieuwe permalinks) ───────────────

add_action( 'template_redirect', function () {
	if ( ! is_404() ) { return; }
	$path = rawurldecode( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '' );
	$name = strtolower( trim( basename( $path ) ) );
	if ( '' === $name || ! str_ends_with( $name, '.html' ) ) { return; }
	foreach ( get_option( 'idr_legacy_routes', [] ) as $route ) {
		if ( strtolower( $route['filename'] ) === $name ) {
			$target = idr_post_by_idr_id( $route['targetId'] );
			if ( $target ) {
				wp_redirect( get_permalink( $target ), 301 );
				exit;
			}
		}
	}
} );

// ── Template-helpers ─────────────────────────────────────────────────────────

function idr_meta( $key, $post_id = null ) {
	return get_post_meta( $post_id ?: get_the_ID(), '_idr_' . $key, true );
}

function idr_meta_json( $key, $post_id = null ) {
	$raw = idr_meta( $key, $post_id );
	if ( ! $raw ) { return []; }
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : [];
}

function idr_display_title( $post_id = null ) {
	return html_entity_decode( idr_meta( 'display_title', $post_id ) ?: get_the_title( $post_id ) );
}

/** Gerelateerde records (uit de legacy linkgraaf) als [label, WP_Post]-paren. */
function idr_related_posts( $post_id = null ) {
	$out = [];
	foreach ( idr_meta_json( 'related', $post_id ) as $rel ) {
		$post = idr_post_by_idr_id( $rel['targetId'] );
		if ( $post ) { $out[] = [ 'label' => $rel['label'], 'post' => $post ]; }
	}
	return $out;
}
