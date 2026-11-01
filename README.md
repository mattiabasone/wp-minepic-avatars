# wp-minepic-avatars

Replaces WordPress avatars with Minecraft avatars from [minepic.org](https://minepic.org).

## Install
Copy `wp-minepic-avatars.php` into `wp-content/plugins/wp-minepic-avatars/` and activate it.

## Usage
- **Style:** Settings > Discussion > Avatars > "Minepic style" (Avatar or Head).
- **Username:** each user sets "Minecraft username" in their profile, or on any page with the `[minepic_username_form]` shortcode.
- **Fallback order:** Minecraft username > WP login name > MHF_Steve/MHF_Alex (stable per user).
- Valid names: 3-16 characters, `A-Z a-z 0-9 _`. Guest commenters use the name they typed.

## Test
`php tests/check.php` prints `OK`.
