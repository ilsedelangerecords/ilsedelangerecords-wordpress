<?php get_header(); ?>

<section class="hero">
	<div class="wrap">
		<h1>Elke release, elke single, elke songtekst &mdash; gedocumenteerd.</h1>
		<p>Het complete verzamelarchief van Ilse DeLange en The Common Linnets: albums, singles, live-registraties, promo-items en lyrics, met hoezen en persingen uit de collectie.</p>
		<p class="actions">
			<a class="btn solid" href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>">Blader door de releases</a>
			<a class="btn" href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>">Lyrics &amp; songs</a>
		</p>
	</div>
</section>

<div class="wrap">
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
				<h2>Recente releases</h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>">Alle <?php echo (int) wp_count_posts( 'idr_release' )->publish; ?> releases &rarr;</a>
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
