# Planet4 Performance Boost

Conservative performance plugin for Planet4. Each feature is independent and has its own switch under **Settings → Performance Boost**.

| Feature | Default | Notes |
|---|---|---|
| Preconnect hints | on | `preconnect` + `dns-prefetch` for the listed origins |
| LCP image priority | on | first non-icon `<img>` in `<body>` gets `fetchpriority="high" loading="eager"` (string edit, HTML is never re-parsed) |
| Script delay | off | holds JS-injected scripts (Hotjar, TikTok…) until first interaction or a fallback timeout |

Google Tag Manager, Google Analytics and OptiMonk are **never** delayed (hardcoded whitelist). The plugin does not touch GTM at all.

Debugging: add `?p4pb=off` to any front-end URL to bypass the plugin.

Do not run together with `planet4-performance-booster` (overlapping LCP / script features).
