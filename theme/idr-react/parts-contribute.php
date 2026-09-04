<?php
/**
 * Community-bijdrage: "Vul aan / corrigeer"-formulier op release/song-pagina's.
 * Meldt aan in de moderatiewachtrij (idr_proposal, plugin/idr-discography/idr-proposals.php).
 * Werkt volledig zonder JS (echte POST naar admin-post.php, resultaat via redirect + querystring).
 */
function idr_render_contribute_form( $post_id = null ) {
	if ( ! function_exists( 'idr_proposal_fields' ) ) { return; }
	$post_id = $post_id ?: get_the_ID();
	$post    = get_post( $post_id );
	if ( ! $post ) { return; }
	$idr_id = idr_meta( 'id', $post_id );
	$fields = $idr_id ? idr_proposal_fields( $post->post_type ) : [];
	if ( ! $fields ) { return; }

	$notice = isset( $_GET['idr_proposal'] ) ? sanitize_key( wp_unslash( $_GET['idr_proposal'] ) ) : '';
	?>
	<section class="related contribute" id="contribute">
		<h2><?php echo esc_html( __stm( 'contribute.heading', 'Vul aan of corrigeer' ) ); ?></h2>
		<p class="description"><?php echo esc_html( __stm( 'contribute.intro', 'Zie je een fout of ontbreekt er iets? De redactie beoordeelt elk voorstel handmatig, er is geen account nodig.' ) ); ?></p>

		<?php if ( 'sent' === $notice ) : ?>
			<p class="contribute-notice"><?php echo esc_html( __stm( 'contribute.sent', 'Bedankt! Je voorstel staat in de moderatiewachtrij.' ) ); ?></p>
		<?php elseif ( 'ratelimited' === $notice ) : ?>
			<p class="contribute-notice error"><?php echo esc_html( __stm( 'contribute.ratelimited', 'Te veel voorstellen recent verstuurd, probeer het later opnieuw.' ) ); ?></p>
		<?php elseif ( 'error' === $notice ) : ?>
			<p class="contribute-notice error"><?php echo esc_html( __stm( 'contribute.error', 'Dat ging niet goed, vul in elk geval een toelichting in en probeer opnieuw.' ) ); ?></p>
		<?php endif; ?>

		<form class="contribute-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'idr_submit_proposal', 'idr_proposal_nonce' ); ?>
			<input type="hidden" name="action" value="idr_submit_proposal">
			<input type="hidden" name="idr_target_id" value="<?php echo esc_attr( $idr_id ); ?>">

			<div class="hp-field" aria-hidden="true">
				<label>Website<input type="text" name="idr_website" tabindex="-1" autocomplete="off"></label>
			</div>

			<p>
				<label for="idr_field"><?php echo esc_html( __stm( 'contribute.field_label', 'Wat klopt niet of ontbreekt er?' ) ); ?></label>
				<select name="idr_field" id="idr_field">
					<?php foreach ( $fields as $key => $def ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="idr_proposed_value"><?php echo esc_html( __stm( 'contribute.value_label', 'Voorgestelde waarde (indien van toepassing)' ) ); ?></label>
				<input type="text" id="idr_proposed_value" name="idr_proposed_value" maxlength="200">
			</p>
			<p>
				<label for="idr_message"><?php echo esc_html( __stm( 'contribute.message_label', 'Toelichting' ) ); ?> *</label>
				<textarea id="idr_message" name="idr_message" rows="4" required></textarea>
			</p>
			<p>
				<label for="idr_source"><?php echo esc_html( __stm( 'contribute.source_label', 'Bron (link, optioneel)' ) ); ?></label>
				<input type="url" id="idr_source" name="idr_source" maxlength="300">
			</p>
			<p class="contribute-grid">
				<label for="idr_name"><?php echo esc_html( __stm( 'contribute.name_label', 'Naam (optioneel)' ) ); ?>
					<input type="text" id="idr_name" name="idr_name" maxlength="120">
				</label>
				<label for="idr_email"><?php echo esc_html( __stm( 'contribute.email_label', 'E-mail (optioneel)' ) ); ?>
					<input type="email" id="idr_email" name="idr_email" maxlength="180">
				</label>
			</p>
			<p><button type="submit" class="btn solid"><?php echo esc_html( __stm( 'contribute.submit', 'Voorstel versturen' ) ); ?></button></p>
		</form>
	</section>
	<?php
}
