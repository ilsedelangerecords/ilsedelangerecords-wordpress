<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header class="site-head">
	<div class="wrap">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">Ilse DeLange <span class="records">Records</span></a>
		<span class="tagline">Het discografie-archief van Ilse DeLange &amp; The Common Linnets</span>
		<nav class="site-nav">
			<?php foreach ( idr_nav_items() as [ $label, $url ] ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			<form class="head-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" name="s" placeholder="Zoek in het archief&hellip;" value="<?php echo esc_attr( get_search_query() ); ?>">
			</form>
		</nav>
	</div>
</header>
