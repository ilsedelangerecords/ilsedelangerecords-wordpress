<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:ital,wght@0,400;0,450;1,400@0,9..144&display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header class="site-head">
	<div class="wrap">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="wordmark">Ilse DeLange</span>
			<span class="records">Records</span>
		</a>
		<nav class="site-nav">
			<?php foreach ( idr_nav_items() as [ $label, $url ] ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			<form class="head-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" name="s" placeholder="<?php echo esc_attr( function_exists( '__stm' ) ? __stm( 'search.placeholder', 'Zoek in het archief' ) : 'Zoek in het archief' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
			</form>
			<?php if ( class_exists( 'STM\LanguageSwitcher' ) ) : ?>
				<?php STM\LanguageSwitcher::render( [ 'style' => 'buttons', 'show_flags' => false, 'show_names' => false ] ); ?>
			<?php endif; ?>
		</nav>
	</div>
</header>
