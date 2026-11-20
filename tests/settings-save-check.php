<?php
// Sojlo: (1) messages must stay translatable through activation and a settings save,
// (2) a save of the FREE screen must not write back a rule an add-on injected for the editor.
// Usage: php tests/settings-save-check.php; exit 0 = pass. Framework-free stubs.
namespace Minimum\Pro { const VERSION = '1'; } // PRO active: hides the promo and models the add-on below.
namespace {
define('ABSPATH', '/'); define('Minimum\VERSION', '9.9.9');
$GLOBALS['locale'] = 'en_US'; $GLOBALS['opts'] = []; $GLOBALS['inject'] = false;
function __($s, $d = '') { return $GLOBALS['locale'] === 'pl_PL' ? "PL: $s" : $s; }
function esc_html__($s, $d = '') { return __($s); } function esc_attr__($s, $d = '') { return __($s); }
function esc_html_e($s, $d = '') { echo __($s); } function esc_attr_e($s, $d = '') { echo __($s); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return $s; } function admin_url($p = '') { return "/wp-admin/$p"; }
function get_option($k, $def = false) { return $GLOBALS['opts'][$k] ?? $def; }
function add_option($k, $v) { if (!isset($GLOBALS['opts'][$k])) $GLOBALS['opts'][$k] = $v; return true; }
function update_option($k, $v) { $GLOBALS['opts'][$k] = $v; return true; }
function apply_filters($h, $v) {
    if ($h === 'minimum/rules' && $GLOBALS['inject']) { $v[] = ['scope' => 'role', 'target' => 0, 'role' => 'shop_manager', 'min' => 10, 'max' => 0, 'step' => 0]; }
    if ($h === 'minimum/min_order_total' && $GLOBALS['inject']) { return 500.0; }
    return $v;
}
function current_user_can() { return true; } function get_admin_page_title() { return 'Sojlo'; }
function settings_fields() {} function submit_button() {} function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function selected($a, $b) { echo $a === $b ? ' selected' : ''; } function disabled($a, $b) { echo $a === $b ? ' disabled' : ''; }
function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $s)); }
function absint($v) { return abs((int) $v); } function sanitize_text_field($s) { return trim((string) $s); }
function wc_format_decimal($v) { return (string) $v; } function is_user_logged_in() { return true; }
function get_woocommerce_currency_symbol() { return 'EUR'; }
$dir = $argv[1] ?? dirname(__DIR__);
foreach (['src/Contract/HasHooks.php', 'src/Rules/Settings.php', 'src/Rules/ProUpsell.php', 'src/Rules/Admin.php', 'src/Migrator.php'] as $f) require "$dir/$f";
$settings = new Minimum\Rules\Settings();
$fail = 0;

// 1. Activation request in English, storefront in Polish.
(new Minimum\Migrator())->maybeMigrate();
$GLOBALS['locale'] = 'pl_PL';
$m = $settings->message('msg_min_qty');
echo "after activation, storefront: $m\n"; $fail |= ! str_starts_with($m, 'PL: ');

// 2. Admin in English opens the screen (PRO injects a shop_manager rule), browser submits it.
$GLOBALS['locale'] = 'en_US'; $GLOBALS['inject'] = true;
$admin = new Minimum\Rules\Admin($settings);
ob_start(); $admin->render_page(); $html = ob_get_clean();
// Browser model: a select without a selected option submits its first option.
$post = [];
preg_match_all('/name="minimum_settings\[([^"]+)\]"([^>]*)>/', $html, $mm, PREG_SET_ORDER);
foreach ($mm as $f) { if (preg_match('/value="([^"]*)"/', $f[2], $v)) { $post[$f[1]] = html_entity_decode($v[1], ENT_QUOTES); } }
preg_match_all('/<select[^>]*name="minimum_settings\[([^"]+)\]"[^>]*>(.*?)<\/select>/s', $html, $ss, PREG_SET_ORDER);
foreach ($ss as $s) { preg_match('/<option value="([^"]*)"\s*selected/', $s[2], $sel) || preg_match('/<option value="([^"]*)"/', $s[2], $sel); $post[$s[1]] = $sel[1]; }
$raw = [];
foreach ($post as $k => $v) { parse_str('x[' . $k . ']=' . urlencode($v), $o); $raw = array_replace_recursive($raw, $o['x']); }
$GLOBALS['inject'] = false;
$GLOBALS['opts']['minimum_settings'] = $settings->sanitize($raw);
$GLOBALS['locale'] = 'pl_PL';
$m = $settings->message('msg_min_qty');
echo "after save, storefront: $m\n"; $fail |= ! str_starts_with($m, 'PL: ');
$saved = $GLOBALS['opts']['minimum_settings'];
echo 'saved rules: ', json_encode($saved['rules']), ' min_order_total: ', $saved['min_order_total'], "\n";
$fail |= [] !== $saved['rules'] || 0.0 !== (float) $saved['min_order_total'];
echo $fail ? "FAIL\n" : "PASS\n";
exit($fail ? 1 : 0);
}
