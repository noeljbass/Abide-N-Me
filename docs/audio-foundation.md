# Provider-neutral audio foundation

Iteration 9A deliberately ships with audio disabled. A provider must not be activated until its API version, authentication method, filesets, attribution, streaming rules, caching rules, and Catholic canon coverage are approved.

Private configuration accepts `AUDIO_ENABLED`, `AUDIO_PROVIDER`, `AUDIO_API_BASE_URL`, `AUDIO_API_KEY`, `AUDIO_ALLOWED_HOSTS`, and `AUDIO_REQUEST_TIMEOUT_SECONDS`. The API key is server-side only. Outbound provider requests must use HTTPS, an explicit hostname allowlist, no redirects, bounded response sizes, and public DNS addresses. Metadata may use `api_cache`; media bytes must not be cached without provider permission.

The browser consumes only normalized version and chapter metadata. Audio progress writes require an authenticated group member, CSRF protection, a non-sequential passage identifier, an active audio version/book mapping, and a playback location inside the assigned passage. No concrete Bible Brain response shape or endpoint is assumed by this foundation.
