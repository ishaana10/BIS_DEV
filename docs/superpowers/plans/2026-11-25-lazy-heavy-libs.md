# Lazy-load heavy JS libraries Implementation Plan

> **For agentic workers:** use superpowers:executing-plans. Steps use checkbox syntax.

**Goal:** Stop every page and iframe from downloading/parsing webdatarocks (1.4 MB) and uppy (537 KB); load each only when code first reads its global.

**Architecture:** `index.php` replaces the two `<script src>` includes with one inline stub that defines non-enumerable lazy accessors for `window.WebDataRocks` and `window.Uppy`. First read deletes the accessor, fetches the cached script with a synchronous XHR, runs it, and returns the real global. Existing form code is unchanged.

**Tech Stack:** PHP 7.3-compatible, vanilla JS.

**Spec:** approved in chat 2026-11-25 (option 2, load on first use).

## Global Constraints
- PHP 7.3 syntax only.
- Keep `?ts=<filemtime>` versioning for the lazy URLs.
- CSS includes stay eager. TinyMCE include stays config-gated.

## Review Focus
- Code doing `typeof WebDataRocks`/`window.Uppy` checks must trigger a load, not see `undefined`.
- Enumerating `window` must not trigger loads (accessors non-enumerable).
- Missing/404 lib file must leave the global `undefined`, as before, with no exception.
- Assigning `window.Uppy = x` before first read must win over the stub.

### Task 1: Lazy stub in index.php
**Files:** Modify `index.php` (add `nuLazyLibsInclude(array $libs): void`; replace lines including `core/libs/uppy/uppy.min.js` and `webpivot/webdatarocks.js`).
- [ ] Add `nuLazyLibsInclude(['Uppy' => 'core/libs/uppy/uppy.min.js', 'WebDataRocks' => 'webpivot/webdatarocks.js'])`, emitting the inline stub with `?ts=filemtime` URLs.
- [ ] Verify in browser: page source has no `<script src>` for the two files; `Object.keys(window)` doesn't contain them; `typeof WebDataRocks` is `function` after load and a second read does no new request; `typeof Uppy` likewise; assignment-first case; 404 case.
- [ ] Verify regression: login, 7 forms via nuapi, popup iframe render, and a pivot + uppy render via console (`new WebDataRocks({container:...})`, `new Uppy.Core()`).
- [ ] Measure iframe load before/after (transfer + DOMContentLoaded). Commit, push `perf/lazy-libs`.
