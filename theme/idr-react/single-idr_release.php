<?php get_header(); the_post(); ?>
<div class="wrap">
	<nav class="breadcrumbs">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> &rsaquo;
		<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>">Releases</a> &rsaquo;
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
				<?php
				echo esc_html( idr_artist_label() );
				$format = idr_meta( 'format' );
				if ( $format && 'unknown' !== $format ) { echo ' &middot; ' . esc_html( ucfirst( $format ) ); }
				?>
			</p>
			<div class="facts">
				<dl>
					<?php
					$facts = [
						'Uitgebracht'    => idr_meta( 'released_text' ) ?: idr_meta( 'year' ),
						'Label'          => idr_meta( 'label' ),
						'Catalogusnummer' => idr_meta( 'catalog_number' ),
						'Sectie'         => wp_get_post_terms( get_the_ID(), 'idr_section', [ 'fields' => 'names' ] )[0] ?? '',
					];
					foreach ( $facts as $label => $value ) {
						if ( ! $value ) { continue; }
						echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( html_entity_decode( $value ) ) . '</dd>';
					}
					?>
				</dl>
				<?php if ( idr_meta( 'spotify_url' ) ) : ?>
					<p><a class="btn spotify-btn" href="<?php echo esc_url( idr_meta( 'spotify_url' ) ); ?>" rel="noopener">&#9654; Luister op Spotify</a></p>
				<?php endif; ?>
			</div>

			<?php $related = idr_related_posts(); ?>
			<?php if ( $related ) : ?>
				<p>
					<?php foreach ( $related as $rel ) : ?>
						<a class="btn" href="<?php echo esc_url( get_permalink( $rel['post'] ) ); ?>"><?php echo esc_html( 'Information' === $rel['label'] ? 'Tracklist & credits' : $rel['label'] ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<div class="legacy-content"><?php the_content(); ?></div>

			<?php $info = idr_meta( 'info_html' ); ?>
			<?php if ( $info ) : ?>
				<section class="related">
					<h2>Tracklist &amp; credits</h2>
					<div class="legacy-content"><?php echo wp_kses_post( $info ); ?></div>
				</section>
			<?php endif; ?>

			<?php idr_render_gallery(); ?>
		</div>
	</article>
</div>
<?php get_footer(); ?>
