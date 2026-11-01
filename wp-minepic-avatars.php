<?php
/**
 * Plugin Name: Minepic Avatars
 * Description: Replaces avatars with Minecraft avatars from minepic.org.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

function minepic_is_valid_username( string $name ): bool {
	return (bool) preg_match( '/^[A-Za-z0-9_]{3,16}$/', $name );
}

// First valid candidate wins; otherwise a Steve/Alex pick that is stable per seed.
function minepic_resolve_username( array $candidates, string $seed ): string {
	foreach ( $candidates as $name ) {
		if ( minepic_is_valid_username( (string) $name ) ) {
			return $name;
		}
	}
	return crc32( $seed ) % 2 ? 'MHF_Alex' : 'MHF_Steve';
}

function minepic_url( string $type, string $name, int $size ): string {
	if ( ! in_array( $type, [ 'avatar', 'head' ], true ) ) {
		$type = 'avatar';
	}
	return "https://minepic.org/{$type}/{$name}/{$size}";
}

function minepic_save_username( int $user_id, string $raw ): bool {
	$name = sanitize_text_field( $raw );
	if ( '' === $name ) {
		delete_user_meta( $user_id, 'minepic_username' );
		return true;
	}
	update_user_meta( $user_id, 'minepic_username', $name );
	return minepic_is_valid_username( $name );
}

function minepic_user_candidates( $user ): array {
	return $user ? [get_user_meta( $user->ID, 'minepic_username', true ), $user->user_login] : [];
}

add_filter(
	'pre_get_avatar_data',
	function ( $args, $id_or_email ) {
		$user       = false;
		$candidates = [];
		$seed       = '';

		if ( $id_or_email instanceof WP_Post ) {
			$id_or_email = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			if ( $id_or_email->user_id ) {
				$id_or_email = (int) $id_or_email->user_id;
			} else {
				$candidates = array( $id_or_email->comment_author );
				$seed       = $id_or_email->comment_author_email ?: $id_or_email->comment_author;
			}
		}

		if ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		} elseif ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', (int) $id_or_email );
			$seed = (string) $id_or_email;
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			$seed = $id_or_email;
		}

		if ( $user ) {
			$candidates = minepic_user_candidates( $user );
			$seed       = (string) $user->ID;
		}

		$name                 = minepic_resolve_username( $candidates, $seed );
		$args['url']          = minepic_url( get_option( 'minepic_avatar_type', 'avatar' ), $name, (int) $args['size'] );
		$args['found_avatar'] = true;
		return $args;
	},
	10,
	2
);

// Block themes round the Avatar block; Minecraft skins should stay square. Beats `:root :where(.wp-block-avatar img)`.
add_action(
	'wp_head',
	function () {
		echo '<style>.wp-block-avatar img,.wp-block-avatar.is-style-rounded img{border-radius:0}</style>';
	}
);

// Profile field (wp-admin).
function minepic_profile_field( $user ) {
	?>
	<h2>Minecraft</h2>
	<table class="form-table"><tr>
		<th><label for="minepic_username">Minecraft username</label></th>
		<td>
			<input type="text" name="minepic_username" id="minepic_username" class="regular-text"
				value="<?php echo esc_attr( get_user_meta( $user->ID, 'minepic_username', true ) ); ?>">
			<p class="description">3-16 characters: letters, numbers, underscore. Falls back to your login name.</p>
		</td>
	</tr></table>
	<?php
}
add_action( 'show_user_profile', 'minepic_profile_field' );
add_action( 'edit_user_profile', 'minepic_profile_field' );

// Core has already verified the update-user_{id} nonce when these hooks fire.
function minepic_profile_save( $user_id ) {
	if ( current_user_can( 'edit_user', $user_id ) && isset( $_POST['minepic_username'] ) ) {
		minepic_save_username( (int) $user_id, wp_unslash( $_POST['minepic_username'] ) );
	}
}
add_action( 'personal_options_update', 'minepic_profile_save' );
add_action( 'edit_user_profile_update', 'minepic_profile_save' );

// Avatar style setting (Settings > Discussion).
add_action(
	'admin_init',
	function () {
		register_setting(
			'discussion',
			'minepic_avatar_type',
			array(
				'sanitize_callback' => fn( $v ) => 'head' === $v ? 'head' : 'avatar',
				'default'           => 'avatar',
			)
		);
		add_settings_field(
			'minepic_avatar_type',
			'Minepic style',
			function () {
				$cur = get_option( 'minepic_avatar_type', 'avatar' );
				echo '<select name="minepic_avatar_type">';
				foreach ( array( 'avatar' => 'Avatar', 'head' => 'Head' ) as $k => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $cur, $k, false ), esc_html( $label ) );
				}
				echo '</select>';
			},
			'discussion',
			'avatars'
		);
	}
);

// Core has no hook to disable "Default Avatar" (it is registered at file scope in options-discussion.php), so do it client-side.
// Disabled inputs aren't submitted, so the stored avatar_default value stays untouched.
add_action(
	'admin_footer-options-discussion.php',
	function () {
		?>
		<script>
			document.querySelectorAll('input[name="avatar_default"]').forEach(function (i) { i.disabled = true; });
			var row = document.querySelector('input[name="avatar_default"]');
			if (row) {
				row = row.closest('td');
				row.insertAdjacentHTML('afterbegin', '<p><strong>The Minepic Avatars plugin handles default avatars (Minecraft username, then login name, then Steve/Alex).</strong></p>');
			}
		</script>
		<?php
	}
);

// [minepic_username_form]: frontend editor for the current user's own field.
add_shortcode(
	'minepic_username_form',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to set your Minecraft username.</p>';
		}
		$user_id = get_current_user_id();
		$notice  = '';
		if ( isset( $_POST['minepic_username'] ) && wp_verify_nonce( $_POST['minepic_nonce'] ?? '', 'minepic_save' ) ) {
			$ok     = minepic_save_username( $user_id, wp_unslash( $_POST['minepic_username'] ) );
			$notice = $ok
				? '<p>Saved.</p>'
				: '<p>Saved, but the format is invalid: your avatar will use your login name or Steve/Alex.</p>';
		}
		ob_start();
		echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- static strings above.
		?>
		<form method="post">
			<?php wp_nonce_field( 'minepic_save', 'minepic_nonce' ); ?>
			<label>Minecraft username
				<input type="text" name="minepic_username" maxlength="16"
					value="<?php echo esc_attr( get_user_meta( $user_id, 'minepic_username', true ) ); ?>">
			</label>
			<button type="submit">Save</button>
		</form>
		<?php
		return ob_get_clean();
	}
);
