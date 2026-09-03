<?php get_header(); the_post(); ?>
<div class="wrap">
	<nav class="breadcrumbs">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> &rsaquo;
		<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>">Lyrics &amp; songs</a> &rsaquo;
		<?php echo esc_html( idr_display_title() ); ?>
	</nav>
	<article class="record">
		<div class="cover-block">
			<?php if ( idr_cover_url() ) : ?>
				<img src="<?php echo esc_url( idr_cover_url() ); ?>" alt="<?php echo esc_attr( idr_display_title() ); ?>">
			<?php endif; ?>
		</div>
		<div>
			<h1><?php echo esc_html( idr_display_title() ); ?></h1>
			<p class="byline">
				<?php echo esc_html( idr_artist_label() ); ?>
				<?php if ( idr_meta( 'language' ) ) : ?>
					&middot; <span class="badge"><?php echo esc_html( strtoupper( idr_meta( 'language' ) ) ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( idr_meta( 'spotify_url' ) ) : ?>
				<p><a class="btn spotify-btn" href="<?php echo esc_url( idr_meta( 'spotify_url' ) ); ?>" rel="noopener">&#9654; Luister op Spotify</a></p>
			<?php endif; ?>

			<div class="legacy-content lyrics"><?php the_content(); ?></div>

			<?php idr_render_gallery(); ?>

			<?php $related = idr_related_posts(); ?>
			<?php if ( $related ) : ?>
				<section class="related">
					<h2>Verwant in het archief</h2>
					<ul class="rows">
						<?php foreach ( $related as $rel ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $rel['post'] ) ); ?>"><span class="t"><?php echo esc_html( idr_display_title( $rel['post']->ID ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>
	</article>
</div>
<?php get_footer(); ?>
