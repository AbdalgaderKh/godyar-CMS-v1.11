/* Compatibility Service Worker wrapper (Godyar)
importScripts(new URL('sw.js', self.registration.scope).toString());
importScripts(new URL('sw.js', self.registration.scope).toString());
importScripts(new URL('sw.js', self.registration.scope).toString());
 * - Keeps older registrations working (/service-worker.js)
 * - Loads the real worker from /sw.js
 */
/* global importScripts */
importScripts(new URL('sw.js', self.registration.scope).toString());
/* global importScripts */
importScripts(new URL('sw.js', self.registration.scope).toString());
const importScripts = self.importScripts;
importScripts(new URL('sw.js', self.registration.scope).toString());
importScripts(new URL('sw.js', self.registration.scope).toString());
