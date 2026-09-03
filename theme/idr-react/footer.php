<footer class="site-foot">
	<div class="wrap">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> ilsedelangerecords.nl &middot; fan-archief, geen officiële site</span>
		<span><?php echo (int) wp_count_posts( 'idr_release' )->publish; ?> releases &middot; <?php echo (int) wp_count_posts( 'idr_song' )->publish; ?> songs &middot; <?php echo (int) wp_count_posts( 'idr_appearance' )->publish; ?> gastbijdragen</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_release' ) ); ?>">Releases</a>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'idr_song' ) ); ?>">Lyrics &amp; songs</a>
		<a href="https://github.com/ilsedelangerecords" rel="noopener">GitHub</a>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
