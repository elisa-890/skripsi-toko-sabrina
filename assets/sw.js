/**
 * assets/sw.js
 * ---------------------------------------------------------------------
 * Service Worker sederhana untuk Dashboard Toko Sabrina.
 * Meng-cache aset statis (CSS/JS/ikon) agar aplikasi tetap tampil cepat
 * dan bisa dipasang ke layar utama HP (Add to Home Screen).
 * Halaman PHP (data dinamis) TIDAK di-cache agar data selalu terbaru.
 * ---------------------------------------------------------------------
 */

// Naikkan angka versi ini SETIAP kali mengganti file di ASET_STATIS (mis.
// ganti ikon, css, atau js) supaya browser tahu ada versi baru dan tidak
// terus-menerus menyajikan file lama dari cache.
const NAMA_CACHE = 'toko-sabrina-cache-v2';

// Catatan: path di bawah ini RELATIF TERHADAP LOKASI FILE INI (assets/sw.js),
// bukan relatif terhadap root aplikasi — makanya TIDAK diawali 'assets/' lagi.
const ASET_STATIS = [
    'css/style.css',
    'js/script.js',
    'manifest.json',
    'icons/icon-192.png',
    'icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(NAMA_CACHE).then((cache) => cache.addAll(ASET_STATIS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((namaCacheSemua) =>
            Promise.all(
                namaCacheSemua
                    .filter((nama) => nama !== NAMA_CACHE)
                    .map((nama) => caches.delete(nama))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Hanya terapkan strategi cache untuk aset statis milik aplikasi ini.
    const adalahAsetStatis = ASET_STATIS.some((path) => url.pathname.endsWith(path));

    if (adalahAsetStatis) {
        // Strategi "stale-while-revalidate": langsung sajikan versi cache
        // (kalau ada) supaya cepat, TAPI sekaligus selalu minta versi
        // terbaru dari jaringan di latar belakang dan perbarui cache.
        // Efeknya: kalau file (mis. ikon) diganti di server, pemuatan
        // BERIKUTNYA (refresh ke-2) akan otomatis dapat versi baru —
        // tidak perlu hapus cache manual lagi seperti strategi lama.
        event.respondWith(
            caches.open(NAMA_CACHE).then((cache) =>
                cache.match(event.request).then((responCache) => {
                    const ambilDariJaringan = fetch(event.request)
                        .then((responJaringan) => {
                            cache.put(event.request, responJaringan.clone());
                            return responJaringan;
                        })
                        .catch(() => responCache); // offline: tetap pakai cache lama kalau ada

                    return responCache || ambilDariJaringan;
                })
            )
        );
    }
    // Permintaan lain (halaman PHP, AJAX ke database) selalu diambil dari jaringan.
});
