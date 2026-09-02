<?php get_header(); ?>
<div class="wrap">
	<h1 class="page-title">Releases</h1>
	<div id="idr-browse-root">
		<noscript>
			<div class="grid">
				<?php
				$all = get_posts( [ 'post_type' => 'idr_release', 'posts_per_page' => -1, 'meta_key' => '_idr_year', 'orderby' => 'meta_value_num', 'order' => 'DESC' ] );
				foreach ( $all as $p ) { idr_card( $p ); }
				?>
			</div>
		</noscript>
	</div>
</div>
<?php get_footer(); ?>
