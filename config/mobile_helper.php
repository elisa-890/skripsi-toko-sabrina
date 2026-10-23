<?php
/**
 * config/mobile_helper.php
 * -----------------------------------------------------------------
 * Helper functions untuk deteksi device & optimasi mobile experience
 * Include di login.php & index.php untuk UX lebih baik di HP
 * -----------------------------------------------------------------
 */

/**
 * Deteksi apakah user mengakses dari device mobile
 * Return: true untuk mobile (HP/Tablet), false untuk desktop
 */
function adalah_mobile(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Pattern untuk Android, iPhone, iPad, dll
    $mobile_patterns = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod',
        'Windows Phone', 'BlackBerry', 'Opera Mini',
        'IEMobile', 'Tablet', 'Kindle', 'Silk'
    ];
    
    foreach ($mobile_patterns as $pattern) {
        if (stripos($ua, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Deteksi tipe device lebih spesifik
 * Return: 'android', 'ios', 'tablet', 'desktop', 'unknown'
 */
function deteksi_device_type(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPod') !== false) {
        return 'ios_phone';
    }
    if (stripos($ua, 'iPad') !== false) {
        return 'ios_tablet';
    }
    if (stripos($ua, 'Android') !== false) {
        // Android bisa phone atau tablet, cek lagi
        if (preg_match('/(tablet|pad)/i', $ua)) {
            return 'android_tablet';
        }
        return 'android_phone';
    }
    if (preg_match('/(tablet|pad|kindle|silk)/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/(Windows Phone|BlackBerry)/i', $ua)) {
        return 'other_mobile';
    }
    
    return 'desktop';
}

/**
 * Deteksi ukuran viewport (estimasi dari User-Agent)
 * Return: 'small' (phone), 'medium' (phablet/tablet), 'large' (desktop)
 */
function estimasi_viewport_size(): string
{
    $device = deteksi_device_type();
    
    if (in_array($device, ['ios_phone', 'android_phone'], true)) {
        return 'small';   // ~5-6 inci
    }
    if (in_array($device, ['ios_tablet', 'android_tablet', 'tablet'], true)) {
        return 'medium';  // ~7-10 inci
    }
    
    return 'large';       // desktop
}

/**
 * Dapatkan viewport meta tag yang optimal untuk device
 * Dipakai di <head> untuk setting viewport lebih agresif
 */
function get_viewport_meta(): string
{
    $device = deteksi_device_type();
    $meta = '<meta name="viewport" content="';
    
    // Base viewport
    $meta .= 'width=device-width, ';
    $meta .= 'initial-scale=1.0, ';
    $meta .= 'viewport-fit=cover, ';  // iPhone notch
    
    // Jika mobile, disable user zoom untuk tap lebih responsif
    // (tapi tetap allow double-tap zoom di desktop)
    if (adalah_mobile()) {
        $meta .= 'user-scalable=no, ';
        $meta .= 'maximum-scale=1.0';
    } else {
        $meta .= 'user-scalable=yes';
    }
    
    $meta .= '">';
    
    return $meta;
}

/**
 * Optimasi CSS class berdasarkan device
 * Dipakai untuk tambah class khusus di body/html
 */
function get_device_class(): string
{
    $device = deteksi_device_type();
    $viewport = estimasi_viewport_size();
    
    return "device-{$device} viewport-{$viewport}";
}

/**
 * Ambil ukuran font base yang optimal untuk viewport
 * Phone: 14px (kecil), Tablet: 15px, Desktop: 16px
 */
function get_base_font_size_px(): int
{
    $viewport = estimasi_viewport_size();
    
    $sizes = [
        'small'  => 14,
        'medium' => 15,
        'large'  => 16,
    ];
    
    return $sizes[$viewport] ?? 16;
}

/**
 * CSS snippet untuk optimasi mobile lebih lanjut
 * Inject di <head> atau di awal style.css
 */
function get_mobile_optimizations_css(): string
{
    if (!adalah_mobile()) {
        return '';  // Tidak perlu di desktop
    }
    
    return <<<'CSS'
/* Mobile Optimizations */
html {
    font-size: 14px;  /* Lebih kecil di HP supaya fit lebih banyak konten */
}

body {
    -webkit-font-smoothing: antialiased;
    -webkit-touch-callout: none;  /* Disable context menu long press */
}

/* Input lebih besar untuk jari */
input, textarea, select {
    font-size: 16px !important;  /* Ini penting! Menghindari auto-zoom saat focus di iOS */
    min-height: 44px;
    padding: 12px;
    border-radius: 10px;
}

button {
    min-height: 48px;  /* Tombol yang mudah di-tap */
    min-width: 48px;
    padding: 12px 16px;
}

/* Hapus delay 300ms pada tap (membuat feel lebih responsive) */
a, button, input, select, textarea {
    -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
}

/* Scrolling lebih smooth */
* {
    -webkit-overflow-scrolling: touch;
}

/* Sidebar auto-hide di phone */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100%;
        z-index: 999;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.terbuka {
        transform: translateX(0);
    }
}

/* Safe area untuk notch iPhone */
.app-shell {
    padding-left: max(0px, env(safe-area-inset-left));
    padding-right: max(0px, env(safe-area-inset-right));
    padding-top: max(24px, env(safe-area-inset-top));
    padding-bottom: max(0px, env(safe-area-inset-bottom));
}

/* Bottom nav fix untuk safe area */
.navigasi-bawah {
    padding-bottom: max(8px, env(safe-area-inset-bottom));
}

/* Prevent accidental zoom on double-tap */
input, textarea, select {
    font-size: 16px;  /* iOS >= 16px tidak auto-zoom saat focus */
}

/* Responsive font size */
@media (max-width: 480px) {
    html { font-size: 13px; }
}

@media (max-width: 360px) {
    html { font-size: 12px; }
}
CSS;
}

/**
 * Inject script untuk handle mobile gestures & features
 * Tap, swipe, orientation change, dll
 */
function get_mobile_interactions_script(): string
{
    if (!adalah_mobile()) {
        return '';
    }
    
    return <<<'JS'
<script>
// Prevent accidental zoom on input focus
(function() {
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            // Temporarily disable zoom
            document.querySelector('meta[name="viewport"]').setAttribute('content', 
                'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
        });
        
        input.addEventListener('blur', function() {
            // Re-enable zoom
            document.querySelector('meta[name="viewport"]').setAttribute('content', 
                'width=device-width, initial-scale=1.0, user-scalable=no');
        });
    });
})();

// Hapus delay 300ms pada tap
document.addEventListener('touchstart', function() {}, { passive: true });

// Handle orientation change (portrait/landscape)
window.addEventListener('orientationchange', function() {
    // Re-render atau adjust layout jika perlu
    console.log('Orientasi berubah:', window.orientation);
    
    // Contoh: tutup sidebar saat rotate
    const sidebar = document.getElementById('sidebarAplikasi');
    if (sidebar) {
        sidebar.classList.remove('terbuka');
    }
});

// Status bar color untuk Android (Chrome)
if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    document.querySelector('meta[name="theme-color"]').setAttribute('content', '#0d2818');
} else {
    document.querySelector('meta[name="theme-color"]').setAttribute('content', '#1b4332');
}
</script>
JS;
}

/**
 * Helper untuk detect dark mode preference
 * Return: 'dark', 'light', atau 'auto'
 */
function get_color_scheme_preference(): string
{
    if (isset($_COOKIE['color-scheme'])) {
        return $_COOKIE['color-scheme'];
    }
    
    // Default ke auto (ikuti system preference)
    return 'auto';
}

/**
 * Recommendation untuk file size & performance
 * Log ini untuk development saja
 */
function log_mobile_info(): void
{
    if (php_sapi_name() === 'cli') {
        return;  // Hanya untuk HTTP request
    }
    
    if (is_file(dirname(__DIR__) . '/.env') && 
        (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1')) {
        
        $device = deteksi_device_type();
        $viewport = estimasi_viewport_size();
        $font_size = get_base_font_size_px();
        
        error_log("[Mobile] Device: $device | Viewport: $viewport | Font: {$font_size}px");
    }
}

// Auto-log device info jika debug enabled
log_mobile_info();
