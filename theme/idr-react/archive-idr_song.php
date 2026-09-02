<?php get_header(); ?>
<div class="wrap">
	<h1 class="page-title">Lyrics &amp; songs</h1>
	<div id="idr-browse-root">
		<noscript>
			<ul class="rows">
				<?php
				$all = get_posts( [ 'post_type' => 'idr_song', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
				foreach ( $all as $p ) { idr_song_row( $p ); }
				?>
			</ul>
		</noscript>
	</div>
</div>
<?php get_footer(); ?>
