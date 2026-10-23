/**
 * assets/js/script.js
 * ---------------------------------------------------------------------
 * Interaksi UI umum yang dipakai di banyak halaman:
 *  - Registrasi service worker (PWA)
 *  - Efek sentuh halus pada tombol (feedback taktil visual)
 *  - Auto-hilang pesan sukses/error setelah beberapa detik
 * ---------------------------------------------------------------------
 */

// Registrasi Service Worker agar aplikasi bisa dipasang sebagai PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Path sw.js relatif terhadap root aplikasi, dicoba dari root
        const pathSw = window.location.pathname.includes('/halaman/')
            ? '../assets/sw.js'
            : 'assets/sw.js';
        navigator.serviceWorker.register(pathSw).catch(() => {
            // Diam saja jika gagal (misalnya dijalankan dari file:// lokal)
        });
    });
}

// Sembunyikan pesan notifikasi otomatis setelah 5 detik
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.pesan').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });
});
