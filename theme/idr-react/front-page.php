<?php get_header();
$release_count = (int) wp_count_posts( 'idr_release' )->publish;
$song_count    = (int) wp_count_posts( 'idr_song' )->publish;
$appearance_count = (int) wp_count_posts( 'idr_appearance' )->publish;
?>

<section class="hero">
	<div class="wrap">
		<div>
			<p class="caps eyebrow">Het platenarchief &middot; sinds de eerste persing</p>
			<h1>Elke release, elke persing, elke songtekst. <em>Gedocumenteerd.</em></h1>
			<p>Het verzamelarchief van Ilse DeLange en The Common Linnets: albums, singles, live-registraties, promo-items en lyrics, met de hoezen en persingen uit de collectie.</p>
			<p class="actions">
				<a class="btn solid" href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>">Blader door de collectie</a>
				<a class="btn" href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>">Lyrics &amp; songs</a>
			</p>
			<div class="stats">
				<div><strong><?php echo $release_count; ?></strong><span>releases</span></div>
				<div><strong><?php echo $song_count; ?></strong><span>songs</span></div>
				<div><strong><?php echo $appearance_count; ?></strong><span>gastbijdragen</span></div>
				<div><strong>3.236</strong><span>scans in de collectie</span></div>
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
				<h2>Recent in de collectie</h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>">Alle <?php echo $release_count; ?> releases &rarr;</a>
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
				<h2>Songteksten</h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>">Alle songs &rarr;</a>
			</div>
			<ul class="rows">
				<?php foreach ( $with_lyrics as $p ) { idr_song_row( $p ); } ?>
			</ul>
		</section>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
