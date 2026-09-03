<?php
/** Galerij van hoezen/persingen uit de media-meta. Gebruik: idr_render_gallery(). */
function idr_render_gallery( $post_id = null ) {
	$media = idr_meta_json( 'media', $post_id );
	if ( count( $media ) < 1 ) { return; }
	?>
	<section class="related">
		<h2>Uit de collectie</h2>
		<div class="gallery">
			<?php foreach ( $media as $m ) : ?>
				<a href="<?php echo esc_url( $m['url'] ); ?>" data-caption="<?php echo esc_attr( $m['alt'] ?? '' ); ?>">
					<img src="<?php echo esc_url( $m['url'] ); ?>" alt="<?php echo esc_attr( $m['alt'] ?? '' ); ?>" loading="lazy">
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
