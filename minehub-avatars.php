<?php
/**
 * Plugin Name: MineHub - Avatars for Minecraft
 * Author: Mattia Basone
 * Author URI: https://github.com/mattiabasone
 * Description: Replaces avatars with Minecraft avatars from minepic.org.
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.md
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'MINEHUB_AVATARS_VERSION', '1.0.0' );

function minepic_is_valid_username( string $name ): bool {
	return (bool) preg_match( '/^[A-Za-z0-9_]{3,16}$/', $name );
}

// First valid candidate wins; otherwise a Steve/Alex pick that is stable per seed.
function minehub_resolve_username( array $candidates, string $seed ): string {
	foreach ( $candidates as $name ) {
		if ( minepic_is_valid_username( (string) $name ) ) {
			return $name;
		}
	}
	return crc32( $seed ) % 2 ? 'MHF_Alex' : 'MHF_Steve';
}

function minehub_minepic_url( string $type, string $name, int $size ): string {
	if ( ! in_array( $type, [ 'avatar', 'head' ], true ) ) {
		$type = 'avatar';
	}
	return "https://minepic.org/{$type}/{$name}/{$size}";
}

function minehub_save_username( int $user_id, string $name ): bool {
	if ( '' === $name ) {
		delete_user_meta( $user_id, 'minehub_minecraft_username' );
		return true;
	}
	update_user_meta( $user_id, 'minehub_minecraft_username', $name );
	return minepic_is_valid_username( $name );
}

function minehub_user_candidates( $user ): array {
	return $user ? [get_user_meta( $user->ID, 'minehub_minecraft_username', true ), $user->user_login] : [];
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
			$candidates = minehub_user_candidates( $user );
			$seed       = (string) $user->ID;
		}

		$name                 = minehub_resolve_username( $candidates, $seed );
		$args['url']          = minehub_minepic_url( get_option( 'minepic_avatar_type', 'avatar' ), $name, (int) $args['size'] );
		$args['found_avatar'] = true;
		return $args;
	},
	10,
	2
);

function minepic_enqueue_style() {
    wp_enqueue_style( 'minehub_avatars_css', plugins_url( 'assets/minehub-avatars.css', __FILE__ ), [], MINEHUB_AVATARS_VERSION );
}

// Block themes round the Avatar block; Minecraft skins should stay square. Beats `:root :where(.wp-block-avatar img)`.
add_action( 'wp_enqueue_scripts', 'minepic_enqueue_style' );

// Profile field (wp-admin).
function minepic_profile_field( $user ) {
	?>
	<h2>Minecraft</h2>
	<table class="form-table"><tr>
		<th><label for="minehub_minecraft_username">Minecraft username</label></th>
		<td>
			<input type="text" name="minehub_minecraft_username" id="minehub_minecraft_username" class="regular-text"
				value="<?php echo esc_attr( get_user_meta( $user->ID, 'minehub_minecraft_username', true ) ); ?>">
			<p class="description">3-16 characters: letters, numbers, underscore. Falls back to your login name.</p>
		</td>
	</tr></table>
	<?php
}
add_action( 'show_user_profile', 'minepic_profile_field' );
add_action( 'edit_user_profile', 'minepic_profile_field' );

// Core has already verified the update-user_{id} nonce when these hooks fire.
function minepic_profile_save( $user_id ) {
	if ( current_user_can( 'edit_user', $user_id ) && isset( $_POST['minehub_minecraft_username'] ) ) {
		minehub_save_username((int) $user_id, sanitize_text_field(wp_unslash($_POST['minehub_minecraft_username'])));
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

function minehub_avatars_enqueue_admin_script( $hook ) {
	if ( 'options-discussion.php' !== $hook ) {
		return;
	}
	wp_enqueue_script( 'minepic_avatars_admin_js', plugins_url( 'assets/minehub-avatars-admin.js', __FILE__ ), [], MINEHUB_AVATARS_VERSION, true );
}

add_action( 'admin_enqueue_scripts', 'minehub_avatars_enqueue_admin_script' );

// [minehub_username_form]: frontend editor for the current user's own field.
add_shortcode(
	'minehub_username_form',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to set your Minecraft username.</p>';
		}
		$user_id = get_current_user_id();
		$notice  = '';

		if (
			isset( $_POST['minehub_minecraft_username'], $_POST['minehub_save_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['minehub_save_nonce'] ) ), 'minehub_username_save' )
		) {
			$ok     = minehub_save_username( $user_id, sanitize_text_field(wp_unslash( $_POST['minehub_minecraft_username'] )) );
			$notice = $ok
				? '<p>Saved.</p>'
				: '<p>Saved, but the format is invalid: your avatar will use your login name or Steve/Alex.</p>';
		}
		ob_start();
		echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- static strings above.
		?>
		<form method="post">
			<?php wp_nonce_field( 'minehub_username_save', 'minehub_save_nonce' ); ?>
			<label>Minecraft username
				<input type="text" name="minehub_minecraft_username" maxlength="16"
					value="<?php echo esc_attr( get_user_meta( $user_id, 'minehub_minecraft_username', true ) ); ?>">
			</label>
			<button type="submit">Save</button>
		</form>
		<?php
		return ob_get_clean();
	}
);
