'use strict';
// Clinical records are never cached. Offline navigation returns only an explanation.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate' && event.request.method === 'GET') {
    event.respondWith(fetch(event.request).catch(() => new Response('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>CRED · Sin conexión</title><h1>Sin conexión</h1><p>Conecta tu dispositivo a la red del establecimiento para consultar y guardar atenciones.</p><p><a href="./index.php">Volver a intentar</a></p></html>', {status:503,headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})));
  }
});
