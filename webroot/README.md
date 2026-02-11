# Webroot (Document Root) Option

If your hosting allows setting a specific document root, point it to this `webroot/` directory.
It contains lightweight wrappers for all top-level PHP entrypoints, while keeping the actual
application code one directory above.

This gives you the security benefit of not exposing folders like: includes/, config/, vendor/, storage/, plugins/, migrations/, tools/, etc.
