<?php
/**
 * Contributie-workflow (Fase 4, taak 1292): fans dienen aanvullingen/correcties in via een
 * formulier op elke release/song-pagina -> proposal-CPT `idr_proposal` in een moderatiewachtrij
 * (geen accounts nodig; honeypot + rate-limit tegen spam). Redactie beoordeelt in wp-admin, met
 * diff + bron: accepteren past de wijziging toe op de doelpost, afwijzen archiveert met reden.
 *
 * Losse file (i.p.v. in idr-discography.php zelf), zelfde reden als idr-i18n.php: datamodel en
 * community-laag leesbaar gescheiden. Wordt door idr-discography.php geladen.
 *
 * Scope-keuze: alleen expliciet aangewezen structured velden (jaar/label/catalogusnummer/taal)
 * worden bij accepteren automatisch overgenomen op de doelpost -- nooit post_content (songtekst/
 * vrije toelichting), dat blijft altijd een bewuste handmatige stap van de redactie voordat ze op
 * "accepteren" klikt. Voorkomt dat een kort "voorgestelde waarde"-veld per ongeluk de volledige
 * legacy-HTML van een post overschrijft.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Per doel-CPT de velden die fans mogen voorstellen. `meta` = null betekent: alleen vrije
 *  toelichting, nooit automatisch toe te passen. */
const IDR_PROPOSAL_FIELDS = [
	'idr_release' => [
		'year'           => [ 'label' => 'Jaar',                        'meta' => '_idr_year' ],
		'label'          => [ 'label' => 'Label',                       'meta' => '_idr_label' ],
		'catalog_number' => [ 'label' => 'Catalogusnummer',             'meta' => '_idr_catalog_number' ],
		'content'        => [ 'label' => 'Tekst / algemene informatie', 'meta' => null ],
		'other'          => [ 'label' => 'Overig',                      'meta' => null ],
	],
	'idr_song' => [
		'language' => [ 'label' => 'Taal',      'meta' => '_idr_language' ],
		'content'  => [ 'label' => 'Songtekst', 'meta' => null ],
		'other'    => [ 'label' => 'Overig',    'meta' => null ],
	],
];

/** Veldenlijst voor een doel-post-type, leeg als het type geen voorstellen ondersteunt. */
function idr_proposal_fields( $post_type ) {
	return IDR_PROPOSAL_FIELDS[ $post_type ] ?? [];
}

// ── Datamodel: CPT + moderatiestatussen ─────────────────────────────────────

add_action( 'init', function () {
	register_post_type( 'idr_proposal', [
		'labels'          => [
			'name'          => 'Voorstellen',
			'singular_name' => 'Voorstel',
			'all_items'     => 'Moderatiewachtrij',
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-feedback',
		'supports'        => [ 'title' ],
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	] );

	foreach ( [
		'idr_pending'  => 'Te beoordelen',
		'idr_accepted' => 'Geaccepteerd',
		'idr_rejected' => 'Afgewezen',
	] as $status => $label ) {
		register_post_status( $status, [
			'label'                     => $label,
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ),
		] );
	}
} );

/** Pending-aantal als bubble bij het "Voorstellen"-menu, zelfde idee als WP's Reacties-teller. */
add_action( 'admin_menu', function () {
	global $menu;
	$count = (int) ( wp_count_posts( 'idr_proposal' )->idr_pending ?? 0 );
	if ( ! $count ) { return; }
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=idr_proposal' === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . $count . '</span></span>';
		}
	}
}, 999 );

// ── Admin-lijst: doel/veld/indiener in één oogopslag ────────────────────────

add_filter( 'manage_idr_proposal_posts_columns', function ( $cols ) {
	$new = [];
	foreach ( $cols as $key => $label ) {
		if ( 'date' === $key ) { continue; }
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['idrp_target'] = 'Doel';
			$new['idrp_field']  = 'Veld';
			$new['idrp_from']   = 'Van';
		}
	}
	$new['date'] = $cols['date'] ?? 'Datum';
	return $new;
} );

add_action( 'manage_idr_proposal_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'idrp_target':
			$target_id = (int) get_post_meta( $post_id, '_idrp_target_post_id', true );
			if ( $target_id && get_post( $target_id ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $target_id ) ) . '">' . esc_html( idr_display_title( $target_id ) ) . '</a>';
			} else {
				echo '&mdash;';
			}
			break;
		case 'idrp_field':
			$kind  = get_post_meta( $post_id, '_idrp_target_type', true );
			$field = get_post_meta( $post_id, '_idrp_field', true );
			echo esc_html( idr_proposal_fields( $kind )[ $field ]['label'] ?? $field );
			break;
		case 'idrp_from':
			echo esc_html( get_post_meta( $post_id, '_idrp_name', true ) ?: 'Anoniem' );
			break;
	}
}, 10, 2 );

// ── Admin: voorstel-detail + diff + accepteren/afwijzen ─────────────────────

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'idr-proposal-details', 'Voorstel', 'idr_render_proposal_metabox', 'idr_proposal', 'normal', 'high' );
} );

function idr_render_proposal_metabox( $post ) {
	$target_id = (int) get_post_meta( $post->ID, '_idrp_target_post_id', true );
	$target    = $target_id ? get_post( $target_id ) : null;
	$kind      = get_post_meta( $post->ID, '_idrp_target_type', true );
	$field     = get_post_meta( $post->ID, '_idrp_field', true );
	$fielddef  = idr_proposal_fields( $kind )[ $field ] ?? null;
	$proposed  = get_post_meta( $post->ID, '_idrp_proposed_value', true );
	$source    = get_post_meta( $post->ID, '_idrp_source', true );
	$name      = get_post_meta( $post->ID, '_idrp_name', true );
	$email     = get_post_meta( $post->ID, '_idrp_email', true );
	$ip        = get_post_meta( $post->ID, '_idrp_ip', true );
	$submitted = get_post_meta( $post->ID, '_idrp_submitted_at', true );
	$status_obj = get_post_status_object( $post->post_status );
	?>
	<table class="form-table">
		<tr>
			<th>Doel</th>
			<td>
				<?php if ( $target ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $target->ID ) ); ?>"><?php echo esc_html( idr_display_title( $target->ID ) ); ?></a>
					&middot; <a href="<?php echo esc_url( get_permalink( $target->ID ) ); ?>" target="_blank" rel="noopener">bekijk live</a>
				<?php else : ?>
					&mdash; (doelpost niet gevonden)
				<?php endif; ?>
			</td>
		</tr>
		<tr><th>Veld</th><td><?php echo esc_html( $fielddef['label'] ?? $field ); ?></td></tr>
		<?php if ( $fielddef && $fielddef['meta'] && $target ) : ?>
			<tr>
				<th>Diff</th>
				<td>
					<p><strong>Huidig:</strong> <?php echo esc_html( get_post_meta( $target->ID, $fielddef['meta'], true ) ?: '(leeg)' ); ?></p>
					<p><strong>Voorgesteld:</strong> <?php echo esc_html( $proposed ?: '(geen waarde opgegeven, alleen toelichting)' ); ?></p>
				</td>
			</tr>
		<?php elseif ( $proposed ) : ?>
			<tr><th>Voorgestelde waarde</th><td><?php echo esc_html( $proposed ); ?></td></tr>
		<?php endif; ?>
		<tr><th>Toelichting</th><td><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></td></tr>
		<?php if ( $source ) : ?>
			<tr><th>Bron</th><td><a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $source ); ?></a></td></tr>
		<?php endif; ?>
		<tr><th>Van</th><td><?php echo esc_html( $name ?: 'Anoniem' ); ?><?php echo $email ? ' &lt;' . esc_html( $email ) . '&gt;' : ''; ?></td></tr>
		<tr><th>Ingediend</th><td><?php echo esc_html( $submitted ); ?><?php echo $ip ? ' &middot; IP ' . esc_html( $ip ) : ''; ?></td></tr>
		<tr>
			<th>Status</th>
			<td>
				<?php echo esc_html( $status_obj->label ?? $post->post_status ); ?>
				<?php $reason = get_post_meta( $post->ID, '_idrp_reject_reason', true ); ?>
				<?php if ( $reason ) : ?>
					<p class="description">Reden afwijzing: <?php echo esc_html( $reason ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<?php if ( 'idr_pending' === $post->post_status ) : ?>
		<hr>
		<div style="display:flex; gap:32px; flex-wrap:wrap; align-items:flex-start;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'idr_proposal_action_' . $post->ID, 'idr_proposal_action_nonce' ); ?>
				<input type="hidden" name="action" value="idr_proposal_accept">
				<input type="hidden" name="proposal_id" value="<?php echo (int) $post->ID; ?>">
				<?php if ( $fielddef && $fielddef['meta'] && $proposed ) : ?>
					<p class="description">Past &ldquo;<?php echo esc_html( $fielddef['label'] ); ?>&rdquo; automatisch toe op de doelpost.</p>
				<?php else : ?>
					<p class="description">Geen automatisch toepasbare waarde &mdash; verwerk de toelichting handmatig in de doelpost, klik daarna hier om het voorstel als verwerkt te markeren.</p>
				<?php endif; ?>
				<button type="submit" class="button button-primary">Accepteren<?php echo ( $fielddef && $fielddef['meta'] && $proposed ) ? ' &amp; toepassen' : ' (handmatig verwerkt)'; ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'idr_proposal_action_' . $post->ID, 'idr_proposal_action_nonce' ); ?>
				<input type="hidden" name="action" value="idr_proposal_reject">
				<input type="hidden" name="proposal_id" value="<?php echo (int) $post->ID; ?>">
				<p><label>Reden (optioneel)<br><textarea name="reject_reason" rows="2" style="width:260px"></textarea></label></p>
				<button type="submit" class="button">Afwijzen</button>
			</form>
		</div>
	<?php else : ?>
		<p class="description">Dit voorstel is al beoordeeld (<?php echo esc_html( $status_obj->label ?? $post->post_status ); ?>).</p>
	<?php endif; ?>
	<?php
}

add_action( 'admin_post_idr_proposal_accept', function () { idr_handle_proposal_moderation( 'accept' ); } );
add_action( 'admin_post_idr_proposal_reject', function () { idr_handle_proposal_moderation( 'reject' ); } );

function idr_handle_proposal_moderation( $action ) {
	$proposal_id = (int) ( $_POST['proposal_id'] ?? 0 );
	if ( ! $proposal_id
		|| ! isset( $_POST['idr_proposal_action_nonce'] )
		|| ! wp_verify_nonce( wp_unslash( $_POST['idr_proposal_action_nonce'] ), 'idr_proposal_action_' . $proposal_id )
	) {
		wp_die( 'Ongeldig verzoek.' );
	}
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( 'Geen rechten om voorstellen te modereren.' );
	}
	$proposal = get_post( $proposal_id );
	if ( ! $proposal || 'idr_proposal' !== $proposal->post_type ) { wp_die( 'Voorstel niet gevonden.' ); }

	if ( 'accept' === $action ) {
		$target_id = (int) get_post_meta( $proposal_id, '_idrp_target_post_id', true );
		$kind      = get_post_meta( $proposal_id, '_idrp_target_type', true );
		$field     = get_post_meta( $proposal_id, '_idrp_field', true );
		$fielddef  = idr_proposal_fields( $kind )[ $field ] ?? null;
		$proposed  = get_post_meta( $proposal_id, '_idrp_proposed_value', true );
		if ( $target_id && $fielddef && $fielddef['meta'] && '' !== $proposed && current_user_can( 'edit_post', $target_id ) ) {
			update_post_meta( $target_id, $fielddef['meta'], wp_slash( sanitize_text_field( $proposed ) ) );
		}
		wp_update_post( [ 'ID' => $proposal_id, 'post_status' => 'idr_accepted' ] );
	} else {
		$reason = sanitize_textarea_field( wp_unslash( $_POST['reject_reason'] ?? '' ) );
		wp_update_post( [ 'ID' => $proposal_id, 'post_status' => 'idr_rejected' ] );
		if ( $reason ) { update_post_meta( $proposal_id, '_idrp_reject_reason', $reason ); }
	}
	update_post_meta( $proposal_id, '_idrp_moderated_by', get_current_user_id() );
	update_post_meta( $proposal_id, '_idrp_moderated_at', current_time( 'mysql' ) );

	wp_safe_redirect( add_query_arg( 'idr_moderated', $action, get_edit_post_link( $proposal_id, 'raw' ) ) );
	exit;
}

add_action( 'admin_notices', function () {
	global $pagenow, $typenow;
	if ( 'post.php' !== $pagenow || 'idr_proposal' !== $typenow || empty( $_GET['idr_moderated'] ) ) { return; }
	$done = 'accept' === sanitize_key( wp_unslash( $_GET['idr_moderated'] ) );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $done ? 'Voorstel geaccepteerd en toegepast.' : 'Voorstel afgewezen.' ) . '</p></div>';
} );

// ── Front-end: formulier-submit (geen account nodig) ────────────────────────

add_action( 'admin_post_nopriv_idr_submit_proposal', 'idr_handle_proposal_submit' );
add_action( 'admin_post_idr_submit_proposal', 'idr_handle_proposal_submit' );

function idr_proposal_client_ip() {
	return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
}

/** Max 5 voorstellen per IP per uur, transient-geteld. */
function idr_proposal_rate_limit_ok( $ip ) {
	if ( ! $ip ) { return true; }
	$key   = 'idr_proposal_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) { return false; }
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return true;
}

function idr_handle_proposal_submit() {
	$redirect = wp_get_referer() ?: home_url( '/' );

	if ( ! isset( $_POST['idr_proposal_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['idr_proposal_nonce'] ), 'idr_submit_proposal' ) ) {
		wp_safe_redirect( add_query_arg( 'idr_proposal', 'error', $redirect ) . '#contribute' );
		exit;
	}

	// Honeypot: een verborgen veld dat alleen bots invullen. Doe alsof het gelukt is, maar sla
	// niets op -- geen signaal teruggeven waarmee een bot zijn gedrag kan bijstellen.
	if ( '' !== trim( (string) ( $_POST['idr_website'] ?? '' ) ) ) {
		wp_safe_redirect( add_query_arg( 'idr_proposal', 'sent', $redirect ) . '#contribute' );
		exit;
	}

	$ip = idr_proposal_client_ip();
	if ( ! idr_proposal_rate_limit_ok( $ip ) ) {
		wp_safe_redirect( add_query_arg( 'idr_proposal', 'ratelimited', $redirect ) . '#contribute' );
		exit;
	}

	$target_id_str = sanitize_text_field( wp_unslash( $_POST['idr_target_id'] ?? '' ) );
	$target        = $target_id_str ? idr_post_by_idr_id( $target_id_str ) : null;
	$message       = trim( sanitize_textarea_field( wp_unslash( $_POST['idr_message'] ?? '' ) ) );

	if ( ! $target || '' === $message ) {
		wp_safe_redirect( add_query_arg( 'idr_proposal', 'error', $redirect ) . '#contribute' );
		exit;
	}

	$kind   = $target->post_type;
	$fields = idr_proposal_fields( $kind );
	$field  = sanitize_key( wp_unslash( $_POST['idr_field'] ?? '' ) );
	if ( ! isset( $fields[ $field ] ) ) { $field = isset( $fields['other'] ) ? 'other' : array_key_first( $fields ); }

	$proposed_value = sanitize_text_field( wp_unslash( $_POST['idr_proposed_value'] ?? '' ) );
	$name           = sanitize_text_field( wp_unslash( $_POST['idr_name'] ?? '' ) );
	$email          = sanitize_email( wp_unslash( $_POST['idr_email'] ?? '' ) );
	$source         = esc_url_raw( wp_unslash( $_POST['idr_source'] ?? '' ) );

	$proposal_id = wp_insert_post( [
		'post_type'    => 'idr_proposal',
		'post_status'  => 'idr_pending',
		'post_title'   => sprintf( '%s — %s', idr_display_title( $target->ID ), $fields[ $field ]['label'] ?? 'Overig' ),
		'post_content' => $message,
	], true );

	if ( is_wp_error( $proposal_id ) ) {
		wp_safe_redirect( add_query_arg( 'idr_proposal', 'error', $redirect ) . '#contribute' );
		exit;
	}

	update_post_meta( $proposal_id, '_idrp_target_post_id', $target->ID );
	update_post_meta( $proposal_id, '_idrp_target_idr_id', $target_id_str );
	update_post_meta( $proposal_id, '_idrp_target_type', $kind );
	update_post_meta( $proposal_id, '_idrp_field', $field );
	if ( $proposed_value ) { update_post_meta( $proposal_id, '_idrp_proposed_value', $proposed_value ); }
	if ( $name ) { update_post_meta( $proposal_id, '_idrp_name', $name ); }
	if ( $email ) { update_post_meta( $proposal_id, '_idrp_email', $email ); }
	if ( $source ) { update_post_meta( $proposal_id, '_idrp_source', $source ); }
	if ( $ip ) { update_post_meta( $proposal_id, '_idrp_ip', $ip ); }
	update_post_meta( $proposal_id, '_idrp_submitted_at', current_time( 'mysql' ) );

	wp_safe_redirect( add_query_arg( 'idr_proposal', 'sent', $redirect ) . '#contribute' );
	exit;
}
