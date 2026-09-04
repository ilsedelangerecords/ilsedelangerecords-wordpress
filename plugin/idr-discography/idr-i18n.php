<?php
/**
 * i18n-glue voor Simple Translation Manager (Fase 3, taak 1297).
 *
 * - Taalconfiguratie NL(bron)/EN/DE + <html lang> volgt de STM-taalkeuze.
 * - DE/NL-songvariant-koppeling: idr_song-posts van dezelfde song in een
 *   andere taal zijn APARTE posts (andere opname/tekst, geen vertaling),
 *   gekoppeld via _idr_variant_of (JSON-array van _idr_id's).
 * - STM-les: vertalingen dragen geen bron-hash, dus na een NL-contentopschoning
 *   (post verwijderd/heraangemaakt) blijven oude vertaalrijen anders als wees
 *   achter. Twee vangnetten: automatisch bij post-verwijdering + een
 *   `wp idr clean-stale-translations` sweep voor na een opschoningsronde.
 *
 * Losse file (i.p.v. in idr-discography.php zelf) zodat het datamodel en de
 * i18n-laag leesbaar gescheiden blijven; wordt door idr-discography.php
 * geladen zodat er geen extra deploy/activatie-stap nodig is.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Taalconfiguratie ──────────────────────────────────────────────────────
// Het archief is initieel NL/EN geïmporteerd (zie idr_language op idr_song);
// NL is de brontaal van de redactionele pagina's en UI-strings. EN + DE erbij
// voor STM, zoals PLAN.md §2.4 vraagt.
add_filter( 'stm_default_languages', function () {
	return [
		[ 'code' => 'nl', 'name' => 'Dutch', 'native_name' => 'Nederlands', 'is_default' => 1, 'flag_emoji' => '🇳🇱', 'order_index' => 1 ],
		[ 'code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => 0, 'flag_emoji' => '🇬🇧', 'order_index' => 2 ],
		[ 'code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'is_default' => 0, 'flag_emoji' => '🇩🇪', 'order_index' => 3 ],
	];
} );

// <html lang="..."> volgt de STM-taalkeuze i.p.v. de vaste site-locale.
add_filter( 'language_attributes', function ( $output ) {
	if ( ! function_exists( 'stm_get_current_language' ) ) { return $output; }
	$map  = [ 'nl' => 'nl-NL', 'en' => 'en-GB', 'de' => 'de-DE' ];
	$lang = $map[ stm_get_current_language() ] ?? 'nl-NL';
	return preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output, 1 );
} );

// ── DE/NL-songvariant-koppeling ──────────────────────────────────────────

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'idr-variants', 'Taalvarianten', 'idr_render_variant_metabox', 'idr_song', 'side' );
} );

function idr_render_variant_metabox( $post ) {
	$linked = function_exists( 'idr_meta_json' ) ? idr_meta_json( 'variant_of', $post->ID ) : [];
	wp_nonce_field( 'idr_save_variants', 'idr_variants_nonce' );
	echo '<p class="description">idr_id&rsquo;s van taalvarianten van dit nummer (andere opname/tekst, geen vertaling), kommagescheiden.</p>';
	echo '<input type="text" name="idr_variant_of" style="width:100%" value="' . esc_attr( implode( ', ', $linked ) ) . '">';
	if ( $linked ) {
		echo '<ul>';
		foreach ( $linked as $vid ) {
			$vp = idr_post_by_idr_id( $vid );
			if ( $vp ) {
				$label = idr_display_title( $vp->ID ) . ' (' . strtoupper( idr_meta( 'language', $vp->ID ) ?: '?' ) . ')';
			} else {
				$label = $vid . ' (niet gevonden)';
			}
			echo '<li>' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
	}
}

add_action( 'save_post_idr_song', function ( $post_id ) {
	if ( ! isset( $_POST['idr_variants_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['idr_variants_nonce'] ), 'idr_save_variants' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$raw = sanitize_text_field( wp_unslash( $_POST['idr_variant_of'] ?? '' ) );
	$ids = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );

	if ( ! $ids ) {
		delete_post_meta( $post_id, '_idr_variant_of' );
		return;
	}

	update_post_meta( $post_id, '_idr_variant_of', wp_slash( wp_json_encode( $ids ) ) );

	// Wederkerig koppelen: elke opgegeven variant krijgt deze song ook terug
	// in zijn eigen lijst, zodat de koppeling nooit eenrichting blijft staan.
	$self_id = idr_meta( 'id', $post_id );
	if ( ! $self_id ) { return; }
	foreach ( $ids as $vid ) {
		$vp = idr_post_by_idr_id( $vid );
		if ( ! $vp || $vp->ID === $post_id ) { continue; }
		$their = idr_meta_json( 'variant_of', $vp->ID );
		if ( ! in_array( $self_id, $their, true ) ) {
			$their[] = $self_id;
			update_post_meta( $vp->ID, '_idr_variant_of', wp_slash( wp_json_encode( $their ) ) );
		}
	}
} );

/** Gekoppelde taalvarianten van een song als WP_Post[]. */
function idr_song_variants( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$out     = [];
	foreach ( idr_meta_json( 'variant_of', $post_id ) as $vid ) {
		$vp = idr_post_by_idr_id( $vid );
		if ( $vp ) { $out[] = $vp; }
	}
	return $out;
}

// ── STM-les: stale vertalingen opruimen na NL-contentopschoning ─────────
// De vertaalrijen in wp_stm_post_translations dragen geen bron-hash: als een
// post bij een opschoningsronde verwijderd of vervangen wordt, blijven de
// oude vertalingen anders als wees achter (en kunnen bij een nieuwe post met
// hetzelfde ID per ongeluk weer opduiken). Twee vangnetten:
//  1. Automatisch opruimen zodra een post écht verwijderd wordt.
//  2. Een `wp idr clean-stale-translations` sweep voor een volledige opschoning
//     (bv. na de import-heruitvoer waar meerdere posts in een keer wijzigen).

add_action( 'before_delete_post', 'idr_clean_stale_translations_for_post' );

/** Verwijdert alle stm_post_translations-rijen voor één post-ID. Retourneert het aantal verwijderde rijen. */
function idr_clean_stale_translations_for_post( $post_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'stm_post_translations';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) { return 0; }
	return (int) $wpdb->delete( $table, [ 'post_id' => (int) $post_id ] );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'idr clean-stale-translations', function ( $args, $assoc_args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'stm_post_translations';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			WP_CLI::warning( 'wp_stm_post_translations bestaat niet (STM niet actief?).' );
			return;
		}
		$orphans = $wpdb->get_col(
			"SELECT DISTINCT t.post_id FROM {$table} t LEFT JOIN {$wpdb->posts} p ON p.ID = t.post_id WHERE p.ID IS NULL"
		);
		if ( ! $orphans ) {
			WP_CLI::success( 'Geen stale vertalingen gevonden.' );
			return;
		}
		if ( isset( $assoc_args['dry-run'] ) ) {
			WP_CLI::log( 'Zou vertalingen verwijderen voor post_id\'s: ' . implode( ', ', $orphans ) );
			return;
		}
		$deleted = 0;
		foreach ( $orphans as $pid ) { $deleted += idr_clean_stale_translations_for_post( (int) $pid ); }
		WP_CLI::success( "{$deleted} stale vertaalrijen verwijderd voor " . count( $orphans ) . ' verdwenen post-ID\'s.' );
	} );
}
