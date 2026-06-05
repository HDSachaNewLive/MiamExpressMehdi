<?php
// vanta_freeze.php
if (isset($_SESSION['user_id'])) {
    $s = $conn->prepare("SELECT reduire_animations FROM user_preferences WHERE user_id = ?");
    $s->execute([(int)$_SESSION['user_id']]);
    $r = $s->fetch();
    if ($r['reduire_animations'] ?? 0): ?>
<script>
setTimeout(() => {
    requestAnimationFrame(() => {
        if (window.vantaEffect) {
            cancelAnimationFrame(window.vantaEffect.req);
        }
    });
}, 50);
</script>
<?php   endif;
} ?>