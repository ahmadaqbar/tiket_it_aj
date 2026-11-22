// Service worker minimal (PWA-ready). Tidak meng-cache halaman yang memerlukan login —
// hanya menampilkan halaman offline bila jaringan putus.
const OFFLINE_URL = '/offline.html';
self.addEventListener('install', (event) => {
  event.waitUntil(caches.open('helpdesk-offline-v1').then((cache) => cache.add(OFFLINE_URL)));
  self.skipWaiting();
});
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
  }
});
