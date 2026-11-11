document.querySelectorAll('input[name="avatar_default"]').forEach(function (i) { i.disabled = true; });
var row = document.querySelector('input[name="avatar_default"]');
if (row) {
    row = row.closest('td');
    row.insertAdjacentHTML('afterbegin', '<p><strong>The Minepic Avatars plugin handles default avatars (Minecraft username, then login name, then Steve/Alex).</strong></p>');
}
