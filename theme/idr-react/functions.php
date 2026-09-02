<?php
/** Ilse DeLange Records theme. Servergerenderde schil + React-island voor de releases-browser. */

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', [ 'search-form' ] );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'idr', get_stylesheet_uri(), [], wp_get_theme()->get( 'Version' ) );
	if ( is_post_type_archive( 'idr_release' ) || is_post_type_archive( 'idr_song' ) ) {
		wp_enqueue_script( 'react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18', true );
		wp_enqueue_script( 'react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', [ 'react' ], '18', true );
		wp_enqueue_script( 'idr-browse', get_template_directory_uri() . '/browse.js', [ 'react-dom' ], wp_get_theme()->get( 'Version' ), true );
		wp_localize_script( 'idr-browse', 'IDR_BROWSE', [
			'endpoint' => rest_url( 'idr/v1/browse' ),
			'mode'     => is_post_type_archive( 'idr_song' ) ? 'songs' : 'releases',
		] );
	}
} );

/** Vaste navigatie: de artiesten + hoofdingangen van het archief. */
function idr_nav_items() {
	$items = [
		[ 'Releases', get_post_type_archive_link( 'idr_release' ) ],
		[ 'Lyrics & songs', get_post_type_archive_link( 'idr_song' ) ],
	];
	foreach ( [ 'ilse-delange', 'tcl-info' ] as $artist_id ) {
		$post = function_exists( 'idr_post_by_idr_id' ) ? idr_post_by_idr_id( $artist_id ) : null;
		if ( $post ) {
			$label = 'tcl-info' === $artist_id ? 'The Common Linnets' : 'Ilse DeLange';
			$items[] = [ $label, get_permalink( $post ) ];
		}
	}
	$items[] = [ 'Gastbijdragen', get_post_type_archive_link( 'idr_appearance' ) ];
	return $items;
}

function idr_cover_url( $post_id = null ) {
	return function_exists( 'idr_meta' ) ? idr_meta( 'cover', $post_id ) : '';
}

function idr_card( $post ) {
	$cover = idr_cover_url( $post->ID );
	$title = function_exists( 'idr_display_title' ) ? idr_display_title( $post->ID ) : get_the_title( $post );
	$year  = function_exists( 'idr_meta' ) ? idr_meta( 'year', $post->ID ) : '';
	$format = function_exists( 'idr_meta' ) ? idr_meta( 'format', $post->ID ) : '';
	?>
	<a class="card" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<span class="cover<?php echo $cover ? '' : ' empty'; ?>">
			<?php if ( $cover ) : ?>
				<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
			<?php else : ?>&#9834;<?php endif; ?>
		</span>
		<h3><?php echo esc_html( $title ); ?></h3>
		<span class="sub"><?php echo esc_html( trim( ( $year ? $year . ' · ' : '' ) . ucfirst( $format ?: '' ), ' ·' ) ); ?></span>
	</a>
	<?php
}

/** Songrij met taal/lyrics-badges. */
function idr_song_row( $post ) {
	$lang = function_exists( 'idr_meta' ) ? idr_meta( 'language', $post->ID ) : '';
	$has  = function_exists( 'idr_meta' ) ? (bool) idr_meta( 'has_lyrics', $post->ID ) : false;
	$title = function_exists( 'idr_display_title' ) ? idr_display_title( $post->ID ) : get_the_title( $post );
	?>
	<li><a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<span class="t"><?php echo esc_html( $title ); ?></span>
		<?php if ( $lang ) : ?><span class="badge"><?php echo esc_html( strtoupper( $lang ) ); ?></span><?php endif; ?>
		<?php if ( $has ) : ?><span class="badge accent">lyrics</span><?php endif; ?>
	</a></li>
	<?php
}
