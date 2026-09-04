<?php get_header(); the_post(); ?>
<div class="wrap">
	<nav class="breadcrumbs">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( __stm( 'nav.home', 'Home' ) ); ?></a> &rsaquo;
		<?php echo esc_html( function_exists( 'idr_display_title' ) ? idr_display_title() : get_the_title() ); ?>
	</nav>
	<h1 class="page-title"><?php echo esc_html( function_exists( 'idr_display_title' ) ? idr_display_title() : get_the_title() ); ?></h1>
	<div class="prose legacy-content"><?php the_content(); ?></div>

	<?php if ( function_exists( 'idr_related_posts' ) ) : $related = idr_related_posts(); ?>
		<?php if ( $related ) : ?>
			<section class="related">
				<h2><?php echo esc_html( __stm( 'song.related_heading', 'Verwant in het archief' ) ); ?></h2>
				<ul class="rows">
					<?php foreach ( $related as $rel ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $rel['post'] ) ); ?>"><span class="t"><?php echo esc_html( idr_display_title( $rel['post']->ID ) ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
