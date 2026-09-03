<?php get_header(); ?>
<div class="wrap">
	<h1 class="page-title">Gastbijdragen</h1>
	<p class="byline">Releases van andere artiesten waaraan Ilse DeLange of The Common Linnets meewerkten.</p>
	<div class="grid">
		<?php
		$all = get_posts( [ 'post_type' => 'idr_appearance', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
		foreach ( $all as $p ) { idr_card( $p ); }
		?>
	</div>
</div>
<?php get_footer(); ?>
