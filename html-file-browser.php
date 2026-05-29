<?php
/**
 * Plugin Name:       HTML File Browser
 * Plugin URI:        https://yoursite.com
 * Description:       Browse and serve static HTML files from the plugin's dist/ folder with a clean listing UI and direct URL access.
 * Version:           1.0.0
 * Author:            Your Name
 * License:           GPL-2.0+
 * Text Domain:       html-file-browser
 */

defined( 'ABSPATH' ) || exit;

define( 'HFB_VERSION',     '1.0.0' );
define( 'HFB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'HFB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'HFB_DIST_DIR',    HFB_PLUGIN_DIR . 'dist/' );
define( 'HFB_DIST_URL',    HFB_PLUGIN_URL . 'dist/' );
define( 'HFB_SLUG',        'html-pages' );   // URL prefix: /html-pages/filename

/* ─────────────────────────────────────────────
   1.  REWRITE RULES
   ───────────────────────────────────────────── */

add_action( 'init', 'hfb_register_rewrite_rules' );
function hfb_register_rewrite_rules() {
    // /html-pages/          → listing page
    add_rewrite_rule(
        '^' . HFB_SLUG . '/?$',
        'index.php?hfb_page=__listing__',
        'top'
    );
    // /html-pages/some-file → serve dist/some-file.html
    add_rewrite_rule(
        '^' . HFB_SLUG . '/([^/]+)/?$',
        'index.php?hfb_page=$matches[1]',
        'top'
    );
}

add_filter( 'query_vars', 'hfb_register_query_var' );
function hfb_register_query_var( $vars ) {
    $vars[] = 'hfb_page';
    return $vars;
}

// Flush rules only once after activation
register_activation_hook( __FILE__, 'hfb_activate' );
function hfb_activate() {
    hfb_register_rewrite_rules();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* ─────────────────────────────────────────────
   2.  INTERCEPT REQUESTS
   ───────────────────────────────────────────── */

add_action( 'template_redirect', 'hfb_handle_request' );
function hfb_handle_request() {
    $page = get_query_var( 'hfb_page', '' );
    if ( $page === '' ) return;

    if ( $page === '__listing__' ) {
        hfb_render_listing();
        exit;
    }

    hfb_serve_file( $page );
}

/* ─────────────────────────────────────────────
   3.  SERVE A SINGLE HTML FILE
   ───────────────────────────────────────────── */

function hfb_serve_file( $requested ) {
    // Sanitise: strip directory traversal, allow only safe chars
    $name = preg_replace( '/[^a-zA-Z0-9_\-]/', '', basename( $requested ) );
    if ( $name === '' ) {
        hfb_404(); return;
    }

    $file = HFB_DIST_DIR . $name . '.html';
    if ( ! file_exists( $file ) ) {
        hfb_404( $name ); return;
    }

    // Inject a small "back to listing" bar, then the file content
    $content = file_get_contents( $file );
    $bar     = hfb_back_bar( $name );

    // Try to inject bar right after <body …> or prepend it
    if ( preg_match( '/<body[^>]*>/i', $content ) ) {
        $content = preg_replace( '/(<body[^>]*>)/i', '$1' . $bar, $content, 1 );
    } else {
        $content = $bar . $content;
    }

    status_header( 200 );
    header( 'Content-Type: text/html; charset=UTF-8' );
    echo $content;
    exit;
}

function hfb_back_bar( $name ) {
    $listing_url = home_url( '/' . HFB_SLUG . '/' );
    $label       = esc_html( $name ) . '.html';
    return <<<HTML
<div id="hfb-bar" style="
    position:fixed;top:0;left:0;right:0;z-index:99999;
    display:flex;align-items:center;gap:12px;
    background:#1a1a2e;color:#e2e8f0;
    font-family:'Segoe UI',system-ui,sans-serif;font-size:13px;
    padding:8px 18px;box-shadow:0 2px 8px rgba(0,0,0,.4);">
  <a href="{$listing_url}" style="color:#7dd3fc;text-decoration:none;display:flex;align-items:center;gap:6px;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    All Files
  </a>
  <span style="opacity:.4">|</span>
  <span style="opacity:.8">📄 {$label}</span>
</div>
<div style="height:42px"></div>
HTML;
}

function hfb_404( $name = '' ) {
    status_header( 404 );
    header( 'Content-Type: text/html; charset=UTF-8' );
    $listing_url = esc_url( home_url( '/' . HFB_SLUG . '/' ) );
    $label       = $name ? esc_html( $name ) . '.html' : 'that file';
    echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>File Not Found – HTML Browser</title>
<style>body{font-family:system-ui;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0f0f1a;color:#e2e8f0}
h1{font-size:5rem;margin:0;color:#ef4444}p{opacity:.7}a{color:#7dd3fc}</style></head>
<body><h1>404</h1><p><strong>{$label}</strong> was not found in the dist/ folder.</p>
<a href="{$listing_url}">← Back to file listing</a></body></html>
HTML;
    exit;
}

/* ─────────────────────────────────────────────
   4.  LISTING PAGE
   ───────────────────────────────────────────── */

function hfb_get_dist_files() {
    if ( ! is_dir( HFB_DIST_DIR ) ) return [];
    $files = glob( HFB_DIST_DIR . '*.html' );
    if ( ! $files ) return [];
    $out = [];
    foreach ( $files as $f ) {
        $out[] = [
            'name'     => basename( $f, '.html' ),
            'filename' => basename( $f ),
            'size'     => filesize( $f ),
            'modified' => filemtime( $f ),
            'url'      => home_url( '/' . HFB_SLUG . '/' . basename( $f, '.html' ) . '/' ),
        ];
    }
    usort( $out, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
    return $out;
}

function hfb_format_size( $bytes ) {
    if ( $bytes < 1024 ) return $bytes . ' B';
    if ( $bytes < 1048576 ) return round( $bytes / 1024, 1 ) . ' KB';
    return round( $bytes / 1048576, 2 ) . ' MB';
}

function hfb_render_listing() {
    $files       = hfb_get_dist_files();
    $count       = count( $files );
    $dist_path   = HFB_DIST_DIR;
    $plugin_slug = HFB_SLUG;
    $site_name   = esc_html( get_bloginfo( 'name' ) );

    status_header( 200 );
    header( 'Content-Type: text/html; charset=UTF-8' );
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HTML File Browser — <?php echo $site_name; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0b0c10;
    --surface:   #13141a;
    --surface2:  #1c1e28;
    --border:    #2a2d3a;
    --accent:    #00e5ff;
    --accent2:   #7c3aed;
    --text:      #e2e8f0;
    --muted:     #6b7280;
    --green:     #34d399;
    --red:       #f87171;
  }

  html { font-size: 16px; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Syne', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ── Grid noise bg ── */
  body::before {
    content: '';
    position: fixed; inset: 0; z-index: 0;
    background-image:
      linear-gradient(rgba(0,229,255,.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,229,255,.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
  }

  .wrap { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; padding: 48px 24px 80px; }

  /* ── Header ── */
  header { margin-bottom: 48px; }
  .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
  .logo-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: linear-gradient(135deg, var(--accent2), var(--accent));
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
  }
  .logo-text { font-size: 1.1rem; font-weight: 600; color: var(--muted); letter-spacing: .05em; text-transform: uppercase; }
  h1 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; line-height: 1.1; }
  h1 span { color: var(--accent); }
  .subtitle { margin-top: 10px; color: var(--muted); font-family: 'JetBrains Mono', monospace; font-size: .85rem; }
  .subtitle strong { color: var(--text); }

  /* ── Stats bar ── */
  .stats {
    display: flex; gap: 16px; flex-wrap: wrap;
    margin-bottom: 32px;
  }
  .stat {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 20px;
    display: flex; align-items: center; gap: 10px;
    font-size: .9rem;
  }
  .stat-val { font-size: 1.3rem; font-weight: 800; color: var(--accent); font-family: 'JetBrains Mono', monospace; }
  .stat-label { color: var(--muted); font-size: .8rem; }

  /* ── Search ── */
  .search-wrap { position: relative; margin-bottom: 28px; }
  .search-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
  #hfb-search {
    width: 100%; padding: 13px 16px 13px 46px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; color: var(--text);
    font-family: 'JetBrains Mono', monospace; font-size: .9rem;
    outline: none; transition: border-color .2s;
  }
  #hfb-search:focus { border-color: var(--accent); }
  #hfb-search::placeholder { color: var(--muted); }

  /* ── Table ── */
  .table-wrap {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
  }
  .table-header {
    display: grid; grid-template-columns: 1fr 100px 160px 56px;
    padding: 12px 20px;
    background: var(--surface2); border-bottom: 1px solid var(--border);
    font-size: .72rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: .1em; color: var(--muted);
  }
  .file-row {
    display: grid; grid-template-columns: 1fr 100px 160px 56px;
    padding: 14px 20px; align-items: center;
    border-bottom: 1px solid var(--border);
    transition: background .15s;
    animation: fadeIn .3s ease both;
  }
  .file-row:last-child { border-bottom: none; }
  .file-row:hover { background: var(--surface2); }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

  .file-name-col { display: flex; align-items: center; gap: 12px; min-width: 0; }
  .file-icon {
    width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
    background: linear-gradient(135deg, #1e3a5f, #0e2a4a);
    border: 1px solid #1e4976;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
  }
  .file-name {
    font-family: 'JetBrains Mono', monospace; font-size: .88rem; font-weight: 500;
    color: var(--text); text-decoration: none; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
  }
  .file-name:hover { color: var(--accent); }
  .file-ext { font-size: .75rem; color: var(--muted); margin-top: 2px; }

  .file-size { font-family: 'JetBrains Mono', monospace; font-size: .82rem; color: var(--muted); }
  .file-date { font-size: .82rem; color: var(--muted); }

  .open-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    background: transparent; border: 1px solid var(--border);
    color: var(--muted); text-decoration: none;
    transition: background .15s, color .15s, border-color .15s;
  }
  .open-btn:hover { background: var(--accent); border-color: var(--accent); color: #000; }

  /* ── Empty state ── */
  .empty {
    text-align: center; padding: 64px 24px; color: var(--muted);
  }
  .empty-icon { font-size: 3rem; margin-bottom: 16px; }
  .empty h2 { font-size: 1.3rem; color: var(--text); margin-bottom: 8px; }
  .empty code { background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; padding: 2px 8px; font-family: 'JetBrains Mono', monospace; font-size: .85rem; color: var(--accent); }

  /* ── No-results ── */
  #hfb-noresult { display: none; text-align: center; padding: 40px; color: var(--muted); }

  /* ── Path hint ── */
  .path-hint {
    margin-top: 28px; background: var(--surface); border: 1px solid var(--border);
    border-left: 3px solid var(--accent2);
    border-radius: 10px; padding: 14px 18px;
    font-family: 'JetBrains Mono', monospace; font-size: .82rem; color: var(--muted);
  }
  .path-hint span { color: var(--text); }
  .path-hint strong { color: var(--accent); }

  /* ── Footer ── */
  footer { margin-top: 40px; text-align: center; font-size: .78rem; color: var(--muted); }
  footer a { color: var(--muted); text-decoration: none; }
  footer a:hover { color: var(--text); }

  @media (max-width: 580px) {
    .table-header, .file-row { grid-template-columns: 1fr 80px 44px; }
    .file-date { display: none; }
  }
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <header>
    <div class="logo">
      <div class="logo-icon">🗂</div>
      <div class="logo-text">HTML File Browser</div>
    </div>
    <h1>Static <span>Files</span></h1>
    <p class="subtitle">
      Serving from <strong><?php echo esc_html( str_replace( ABSPATH, '/', $dist_path ) ); ?></strong>
      &nbsp;·&nbsp; URL prefix: <strong>/<?php echo esc_html( $plugin_slug ); ?>/</strong>
    </p>
  </header>

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div>
        <div class="stat-val"><?php echo $count; ?></div>
        <div class="stat-label">HTML Files</div>
      </div>
    </div>
    <?php if ( $count > 0 ) :
        $total = array_sum( array_column( $files, 'size' ) );
        $newest = max( array_column( $files, 'modified' ) );
    ?>
    <div class="stat">
      <div>
        <div class="stat-val"><?php echo hfb_format_size( $total ); ?></div>
        <div class="stat-label">Total Size</div>
      </div>
    </div>
    <div class="stat">
      <div>
        <div class="stat-val"><?php echo date( 'M j', $newest ); ?></div>
        <div class="stat-label">Last Modified</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Search -->
  <?php if ( $count > 0 ) : ?>
  <div class="search-wrap">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" id="hfb-search" placeholder="Filter files…" autocomplete="off">
  </div>
  <?php endif; ?>

  <!-- File table -->
  <div class="table-wrap">
    <?php if ( $count === 0 ) : ?>
      <div class="empty">
        <div class="empty-icon">📭</div>
        <h2>No HTML files found</h2>
        <p>Drop <code>.html</code> files into the <code>dist/</code> folder and they'll appear here.</p>
      </div>
    <?php else : ?>
      <div class="table-header">
        <div>File</div>
        <div>Size</div>
        <div>Modified</div>
        <div></div>
      </div>
      <?php foreach ( $files as $i => $f ) : ?>
      <div class="file-row" data-name="<?php echo esc_attr( strtolower( $f['name'] ) ); ?>"
           style="animation-delay:<?php echo $i * 35; ?>ms">
        <div class="file-name-col">
          <div class="file-icon">📄</div>
          <div>
            <a class="file-name" href="<?php echo esc_url( $f['url'] ); ?>">
              <?php echo esc_html( $f['name'] ); ?>
            </a>
            <div class="file-ext">.html</div>
          </div>
        </div>
        <div class="file-size"><?php echo hfb_format_size( $f['size'] ); ?></div>
        <div class="file-date"><?php echo date( 'M j, Y  H:i', $f['modified'] ); ?></div>
        <div>
          <a class="open-btn" href="<?php echo esc_url( $f['url'] ); ?>" title="Open file">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
      <div id="hfb-noresult">No files match your search.</div>
    <?php endif; ?>
  </div>

  <!-- Path hint -->
  <div class="path-hint">
    📁 To add files: upload <code>.html</code> files to <strong><?php echo esc_html( str_replace( ABSPATH, '', $dist_path ) ); ?></strong><br>
    🔗 Each file is reachable at: <strong>/<?php echo esc_html( $plugin_slug ); ?>/filename</strong>  (no .html needed)
  </div>

  <footer>
    <p>HTML File Browser v<?php echo HFB_VERSION; ?> · <a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Flush permalinks</a> if URLs 404</p>
  </footer>

</div><!-- .wrap -->

<script>
(function () {
  var input = document.getElementById('hfb-search');
  if (!input) return;
  var rows    = document.querySelectorAll('.file-row');
  var noResult = document.getElementById('hfb-noresult');

  input.addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    var visible = 0;
    rows.forEach(function (row) {
      var match = !q || row.dataset.name.includes(q);
      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    noResult.style.display = visible === 0 ? 'block' : 'none';
  });
})();
</script>
</body>
</html>
    <?php
}

/* ─────────────────────────────────────────────
   5.  ADMIN MENU  (Dashboard panel)
   ───────────────────────────────────────────── */

add_action( 'admin_menu', 'hfb_admin_menu' );
function hfb_admin_menu() {
    add_menu_page(
        'HTML File Browser',
        'HTML Browser',
        'manage_options',
        'html-file-browser',
        'hfb_admin_page',
        'dashicons-media-code',
        80
    );
}

add_action( 'admin_enqueue_scripts', 'hfb_admin_styles' );
function hfb_admin_styles( $hook ) {
    if ( $hook !== 'toplevel_page_html-file-browser' ) return;
    wp_add_inline_style( 'wp-admin', '
        .hfb-admin-wrap { max-width: 780px; }
        .hfb-admin-wrap h1 { margin-bottom: 20px; }
        .hfb-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px 24px; margin-bottom: 20px; }
        .hfb-card h2 { margin: 0 0 12px; font-size: 1rem; }
        .hfb-file-list { list-style: none; margin: 0; padding: 0; }
        .hfb-file-list li { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-family: monospace; font-size: .9rem; }
        .hfb-file-list li:last-child { border-bottom: none; }
        .hfb-badge { display: inline-block; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; border-radius: 999px; font-size: .75rem; padding: 1px 10px; }
        .hfb-empty { color: #94a3b8; font-style: italic; font-size: .9rem; }
        .hfb-path { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; font-family: monospace; font-size: .85rem; color: #475569; word-break: break-all; }
    ' );
}

function hfb_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $files       = hfb_get_dist_files();
    $count       = count( $files );
    $listing_url = home_url( '/' . HFB_SLUG . '/' );
    $dist_path   = HFB_DIST_DIR;
    ?>
    <div class="wrap hfb-admin-wrap">
      <h1>🗂 HTML File Browser</h1>

      <div class="hfb-card">
        <h2>📋 Quick Info</h2>
        <table class="form-table" style="margin:0">
          <tr>
            <th style="width:160px">Listing URL</th>
            <td><a href="<?php echo esc_url( $listing_url ); ?>" target="_blank"><?php echo esc_html( $listing_url ); ?></a></td>
          </tr>
          <tr>
            <th>dist/ folder</th>
            <td><div class="hfb-path"><?php echo esc_html( $dist_path ); ?></div></td>
          </tr>
          <tr>
            <th>Files found</th>
            <td><span class="hfb-badge"><?php echo $count; ?> file<?php echo $count !== 1 ? 's' : ''; ?></span></td>
          </tr>
          <tr>
            <th>URL pattern</th>
            <td><code>/<?php echo esc_html( HFB_SLUG ); ?>/&lt;filename&gt;</code> &nbsp;(no .html needed)</td>
          </tr>
        </table>
      </div>

      <div class="hfb-card">
        <h2>📁 Files in dist/</h2>
        <?php if ( $count === 0 ) : ?>
          <p class="hfb-empty">No .html files found in the dist/ folder yet.</p>
        <?php else : ?>
          <ul class="hfb-file-list">
            <?php foreach ( $files as $f ) : ?>
            <li>
              <span>📄 <?php echo esc_html( $f['filename'] ); ?> <span style="color:#94a3b8;font-size:.8em"><?php echo hfb_format_size( $f['size'] ); ?></span></span>
              <a href="<?php echo esc_url( $f['url'] ); ?>" target="_blank" class="button button-small">View →</a>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="hfb-card">
        <h2>🔧 Troubleshooting</h2>
        <p style="margin-bottom:10px;font-size:.9rem">If pages return 404, flush your permalink settings:</p>
        <a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button button-primary">Flush Permalinks</a>
      </div>
    </div>
    <?php
}
