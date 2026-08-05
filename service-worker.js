// UZDUB PLATFORM — Service Worker o'chirilgan.
// Ushbu fayl faqat eski ro'yxatdan o'tgan Service Worker'larni bekor qilish
// va ularning keshlarini tozalash uchun qoldirilgan.
// Yangi tashriflar bu SW ni endi ro'yxatdan o'tkazmaydi (header.php).

self.addEventListener('install', function(e) {
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(names) {
      return Promise.all(names.map(function(n) { return caches.delete(n); }));
    }).then(function() {
      return self.registration.unregister();
    })
  );
  self.clients.claim();
});
