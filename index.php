<?php
/**
 * Big3Africa Editorial Portal — Main Theme Template
 */
if (!defined('ABSPATH')) {
  exit;
}
$theme_dir = get_template_directory();
$theme_uri = get_template_directory_uri();
$site_url  = site_url();

$js_file = '';
$css_file = '';

$search_paths = array(
  $theme_dir . '/assets',
  $theme_dir . '/dist/assets'
);

foreach ($search_paths as $dir) {
  if (is_dir($dir)) {
    $found_js = glob($dir . '/index-*.js');
    if (!empty($found_js) && empty($js_file)) {
      $rel = str_replace($theme_dir, '', $found_js[0]);
      $js_file = ltrim($rel, '/');
    }
    $found_css = glob($dir . '/index-*.css');
    if (!empty($found_css) && empty($css_file)) {
      $rel = str_replace($theme_dir, '', $found_css[0]);
      $css_file = ltrim($rel, '/');
    }
  }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,600;0,6..72,700;1,6..72,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    window.BIG3AFRICA_WP_CONFIG = {
      siteUrl: "<?php echo esc_url($site_url); ?>",
      themeUrl: "<?php echo esc_url($theme_uri); ?>",
      restUrl: "<?php echo esc_url_raw(rest_url()); ?>",
      restNonce: "<?php echo wp_create_nonce('wp_rest'); ?>",
      aiStudioOrigin: "https://ais-pre-dl45ufiqp24hr3tq7rk5la-266316147445.europe-west1.run.app"
    };
  </script>
  <?php wp_head(); ?>
  <?php if (!empty($css_file)) : ?>
    <link rel="stylesheet" crossorigin href="<?php echo esc_url($theme_uri . '/' . $css_file); ?>" />
  <?php endif; ?>
</head>
<body <?php body_class(); ?>>
  <?php wp_body_open(); ?>
  <div id="root"></div>
  <?php if (!empty($js_file)) : ?>
    <script type="module" crossorigin src="<?php echo esc_url($theme_uri . '/' . $js_file); ?>"></script>
  <?php endif; ?>
  <?php wp_footer(); ?>
</body>
</html>
