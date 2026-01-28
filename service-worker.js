/* Compatibility Service Worker wrapper (Godyar)
 * - Keeps older registrations working (/service-worker.js)
 * - Loads the real worker from /sw.js
 */
'use strict';

// Use self.importScripts directly (avoid shadowing / duplicate calls)
self.importScripts(new URL('sw.js', self.registration.scope).toString());
