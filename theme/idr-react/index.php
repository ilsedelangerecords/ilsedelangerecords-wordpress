<?php get_header(); ?>
<div class="wrap">
	<h1 class="page-title">
		<?php
		if ( is_search() ) {
			printf( 'Zoekresultaten voor "%s"', esc_html( get_search_query() ) );
		} elseif ( is_archive() ) {
			echo esc_html( post_type_archive_title( '', false ) ?: get_the_archive_title() );
		} else {
			bloginfo( 'name' );
		}
		?>
	</h1>
	<?php if ( have_posts() ) : ?>
		<ul class="rows">
			<?php
			while ( have_posts() ) {
				the_post();
				if ( 'idr_song' === get_post_type() && function_exists( 'idr_song_row' ) ) {
					idr_song_row( get_post() );
					continue;
				}
				?>
				<li><a href="<?php the_permalink(); ?>">
					<span class="t"><?php echo esc_html( function_exists( 'idr_display_title' ) ? idr_display_title() : get_the_title() ); ?></span>
					<span class="badge"><?php echo esc_html( str_replace( 'idr_', '', get_post_type() ) ); ?></span>
				</a></li>
			<?php } ?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p>Niets gevonden in het archief.</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
