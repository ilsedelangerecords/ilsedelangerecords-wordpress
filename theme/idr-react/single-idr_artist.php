<?php get_header(); the_post();
$profiles = idr_artist_profiles();
$profile  = $profiles[ idr_meta( 'id' ) ?: get_post()->post_name ] ?? null;
if ( ! $profile ) {
	foreach ( $profiles as $idr_id => $p ) {
		if ( idr_post_by_idr_id( $idr_id ) && idr_post_by_idr_id( $idr_id )->ID === get_the_ID() ) { $profile = $p; break; }
	}
}
$term = $profile['term'] ?? 'ilse-delange';
$name = $profile['name'] ?? idr_display_title();
?>
<div class="wrap">
	<nav class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> &rsaquo; <?php echo esc_html( $name ); ?></nav>
	<h1 class="page-title"><?php echo esc_html( $name ); ?></h1>
	<?php if ( ! empty( $profile['tagline'] ) ) : ?><p class="byline"><?php echo esc_html( $profile['tagline'] ); ?></p><?php endif; ?>

	<?php
	$sections = get_terms( [ 'taxonomy' => 'idr_section', 'hide_empty' => true ] );
	$order    = [ 'Album', 'Singles', 'Live', 'Other artist', 'Various artist', 'Items',
	              'TCL album', 'TCL singles', 'TCL other', 'TCL various' ];
	usort( $sections, function ( $a, $b ) use ( $order ) {
		$pa = array_search( $a->name, $order, true ); $pb = array_search( $b->name, $order, true );
		return ( false === $pa ? 99 : $pa ) <=> ( false === $pb ? 99 : $pb );
	} );
	foreach ( $sections as $section ) {
		$releases = get_posts( [
			'post_type'      => [ 'idr_release', 'idr_appearance' ],
			'posts_per_page' => -1,
			'meta_key'       => '_idr_year',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'tax_query'      => [
				'relation' => 'AND',
				[ 'taxonomy' => 'idr_section', 'field' => 'slug', 'terms' => $section->slug ],
				[ 'taxonomy' => 'idr_artist_tax', 'field' => 'slug', 'terms' => $term ],
			],
		] );
		if ( ! $releases ) { continue; }
		?>
		<section class="section">
			<div class="section-head"><h2><?php echo esc_html( $section->name ); ?></h2>
				<span class="browse-count"><?php echo count( $releases ); ?></span></div>
			<div class="grid">
				<?php foreach ( $releases as $p ) { idr_card( $p ); } ?>
			</div>
		</section>
		<?php
	}

	$songs = get_posts( [
		'post_type' => 'idr_song', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
		'tax_query' => [ [ 'taxonomy' => 'idr_artist_tax', 'field' => 'slug', 'terms' => $term ] ],
	] );
	if ( $songs ) :
		?>
		<section class="section">
			<div class="section-head"><h2>Songs &amp; lyrics</h2>
				<span class="browse-count"><?php echo count( $songs ); ?></span></div>
			<ul class="rows">
				<?php foreach ( $songs as $p ) { idr_song_row( $p ); } ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( trim( get_the_content() ) ) : ?>
		<section class="section">
			<div class="section-head"><h2>Uit het archief</h2></div>
			<div class="prose legacy-content"><?php the_content(); ?></div>
			<?php idr_render_gallery(); ?>
		</section>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
