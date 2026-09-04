<footer class="site-foot">
	<p class="closer"><?php echo esc_html( __stm( 'footer.closer', 'Verzameld door fans, persing voor persing.' ) ); ?></p>
	<div class="wrap">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> ilsedelangerecords.nl &middot; <?php echo esc_html( __stm( 'footer.disclaimer', 'fan-archief, geen officiële site' ) ); ?></span>
		<span><?php echo (int) wp_count_posts( 'idr_release' )->publish; ?> releases &middot; <?php echo (int) wp_count_posts( 'idr_song' )->publish; ?> songs &middot; <?php echo (int) wp_count_posts( 'idr_appearance' )->publish; ?> <?php echo esc_html( __stm( 'nav.appearances', 'gastbijdragen' ) ); ?></span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>"><?php echo esc_html( __stm( 'nav.releases', 'Releases' ) ); ?></a>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>"><?php echo esc_html( __stm( 'nav.songs', 'Lyrics & songs' ) ); ?></a>
		<a href="https://github.com/ilsedelangerecords" rel="noopener">GitHub</a>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
