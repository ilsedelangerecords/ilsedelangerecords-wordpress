<?php get_header();
$release_count = (int) wp_count_posts( 'idr_release' )->publish;
$song_count    = (int) wp_count_posts( 'idr_song' )->publish;
$appearance_count = (int) wp_count_posts( 'idr_appearance' )->publish;
?>

<?php
// Achtergrondvideo's: geverifieerd embedbare officiële clips (03-09-2026).
$hero_videos = [ 'hkrF8uC92O4', 'n6MVcBM4P74', 'hOjFQCGKDZA', '8pP0y9HdHz8', 'paa2NRBA7eU' ];
$hero_src = 'https://www.youtube-nocookie.com/embed/' . $hero_videos[0]
	. '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . implode( ',', $hero_videos )
	. '&rel=0&iv_load_policy=3&modestbranding=1&playsinline=1&enablejsapi=1&origin=' . rawurlencode( home_url() );
?>
<section class="hero has-video">
	<div class="hero-video" aria-hidden="true">
		<iframe id="idr-hero-video" src="<?php echo esc_url( $hero_src ); ?>"
			title="" tabindex="-1" allow="autoplay; encrypted-media" frameborder="0"></iframe>
	</div>
	<div class="hero-scrim" aria-hidden="true"></div>
	<button type="button" class="sound-toggle" id="idr-sound" aria-pressed="false"><?php echo esc_html( __stm( 'hero.sound_on', 'Geluid aan' ) ); ?></button>
	<div class="wrap">
		<div>
			<p class="caps eyebrow"><?php echo esc_html( __stm( 'hero.eyebrow', 'Het platenarchief · sinds de eerste persing' ) ); ?></p>
			<h1><?php echo wp_kses_post( __stm( 'hero.title', 'Elke release, elke persing, elke songtekst. <em>Gedocumenteerd.</em>' ) ); ?></h1>
			<p><?php echo esc_html( __stm( 'hero.intro', 'Het verzamelarchief van Ilse DeLange en The Common Linnets: albums, singles, live-registraties, promo-items en lyrics, met de hoezen en persingen uit de collectie.' ) ); ?></p>
			<p class="actions">
				<a class="btn solid" href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>"><?php echo esc_html( __stm( 'hero.browse', 'Blader door de collectie' ) ); ?></a>
				<a class="btn" href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>"><?php echo esc_html( __stm( 'nav.songs', 'Lyrics & songs' ) ); ?></a>
			</p>
			<div class="stats">
				<div><strong><?php echo $release_count; ?></strong><span>releases</span></div>
				<div><strong><?php echo $song_count; ?></strong><span>songs</span></div>
				<div><strong><?php echo $appearance_count; ?></strong><span><?php echo esc_html( __stm( 'nav.appearances', 'gastbijdragen' ) ); ?></span></div>
				<div><strong>3.236</strong><span><?php echo esc_html( __stm( 'hero.scans', 'scans in de collectie' ) ); ?></span></div>
			</div>
		</div>
		<div class="center-label" aria-hidden="true">
			<svg viewBox="0 0 250 250">
				<defs>
					<path id="labelring" d="M 125,125 m -75,0 a 75,75 0 1,1 150,0 a 75,75 0 1,1 -150,0"/>
				</defs>
				<text><textPath href="#labelring">Ilse DeLange Records &#183; het discografie-archief &#183; 45 RPM &#183;</textPath></text>
			</svg>
		</div>
	</div>
</section>

<nav class="section-rail" aria-label="Secties van het archief">
	<div class="wrap">
		<?php
		$rail = get_terms( [ 'taxonomy' => 'idr_section', 'hide_empty' => true ] );
		$order = [ 'Album', 'Singles', 'Live', 'Other artist', 'Various artist', 'Items', 'Lyrics',
		           'TCL album', 'TCL singles', 'TCL other', 'TCL various', 'TCL lyrics' ];
		usort( $rail, function ( $a, $b ) use ( $order ) {
			$pa = array_search( $a->name, $order, true ); $pb = array_search( $b->name, $order, true );
			return ( false === $pa ? 99 : $pa ) <=> ( false === $pb ? 99 : $pb );
		} );
		foreach ( $rail as $term ) :
			?>
			<a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
		<?php endforeach; ?>
	</div>
</nav>

<div class="wrap">
	<section class="section">
		<div class="artist-cards">
			<?php foreach ( idr_artist_profiles() as $idr_id => $profile ) :
				$post = idr_post_by_idr_id( $idr_id );
				if ( ! $post ) { continue; }
				$count = count( get_posts( [
					'post_type' => [ 'idr_release', 'idr_appearance' ], 'posts_per_page' => -1, 'fields' => 'ids',
					'tax_query' => [ [ 'taxonomy' => 'idr_artist_tax', 'field' => 'slug', 'terms' => $profile['term'] ] ],
				] ) );
				$cover = idr_cover_url( $post->ID );
				if ( ! $cover ) {
					$fallback = get_posts( [
						'post_type' => 'idr_release', 'posts_per_page' => 1, 'meta_key' => '_idr_year',
						'orderby' => 'meta_value_num', 'order' => 'DESC',
						'tax_query' => [ [ 'taxonomy' => 'idr_artist_tax', 'field' => 'slug', 'terms' => $profile['term'] ] ],
					] );
					$cover = $fallback ? idr_cover_url( $fallback[0]->ID ) : '';
				}
				?>
				<a class="artist-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
					<?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy"><?php endif; ?>
					<span class="artist-card-body">
						<strong><?php echo esc_html( $profile['name'] ); ?></strong>
						<span><?php echo esc_html( $profile['tagline'] ); ?></span>
						<span class="badge"><?php echo (int) $count; ?> releases in het archief</span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<?php
	$recent = get_posts( [
		'post_type'      => 'idr_release',
		'posts_per_page' => 12,
		'meta_key'       => '_idr_year',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	] );
	if ( $recent ) :
		?>
		<section class="section">
			<div class="section-head">
				<h2><?php echo esc_html( __stm( 'home.recent', 'Recent in de collectie' ) ); ?></h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>"><?php echo esc_html( sprintf( __stm( 'home.all_releases', 'Alle %d releases →' ), $release_count ) ); ?></a>
			</div>
			<div class="grid">
				<?php foreach ( $recent as $p ) { idr_card( $p ); } ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$with_lyrics = get_posts( [
		'post_type'      => 'idr_song',
		'posts_per_page' => 10,
		'meta_key'       => '_idr_has_lyrics',
		'meta_value'     => '1',
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );
	if ( $with_lyrics ) :
		?>
		<section class="section">
			<div class="section-head">
				<h2><?php echo esc_html( __stm( 'home.lyrics_heading', 'Songteksten' ) ); ?></h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>"><?php echo esc_html( __stm( 'home.all_songs', 'Alle songs →' ) ); ?></a>
			</div>
			<ul class="rows">
				<?php foreach ( $with_lyrics as $p ) { idr_song_row( $p ); } ?>
			</ul>
		</section>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
