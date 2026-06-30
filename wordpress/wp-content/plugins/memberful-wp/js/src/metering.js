/**
 * Cache-safe metering runtime.
 *
 * The page HTML is a count-agnostic, cacheable default; this script applies the per-visitor decision:
 *   - free posts are metered entirely client-side via localStorage (no network in the critical path), and
 *   - protected samples are released only by the uncacheable admin-ajax endpoint, which is the server authority.
 */
(() => {
  const cfg = window.memberfulMetering;
  if (!cfg || !cfg.postId) {
    return;
  }

  const MAX_VIEWS = 100;
  const nowSeconds = () => Math.floor(Date.now() / 1000);

  const readViews = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(cfg.storageKey) || '{}');
      return parsed && parsed.views && typeof parsed.views === 'object' ? parsed.views : {};
    } catch (e) {
      return {};
    }
  };

  const prune = (views) => {
    const cutoff = nowSeconds() - cfg.periodDays * 86400;
    const kept = {};
    Object.keys(views).forEach((id) => {
      if (Number(views[id]) >= cutoff) {
        kept[id] = Number(views[id]);
      }
    });
    return kept;
  };

  const persist = (views) => {
    const ids = Object.keys(views);
    if (ids.length > MAX_VIEWS) {
      ids
        .sort((a, b) => views[a] - views[b])
        .slice(0, ids.length - MAX_VIEWS)
        .forEach((id) => delete views[id]);
    }
    try {
      window.localStorage.setItem(cfg.storageKey, JSON.stringify({ v: 1, views }));
    } catch (e) {
      // localStorage unavailable (private mode): the meter falls back to the cached HTML state.
    }
  };

  const record = (views) => {
    if (!Object.prototype.hasOwnProperty.call(views, cfg.postId)) {
      views[cfg.postId] = nowSeconds();
      persist(views);
    }

    return views;
  };

  const endpointPost = (op, keepalive) => {
    const body = new window.URLSearchParams();
    body.set('action', cfg.action);
    body.set('op', op);
    body.set('post_id', String(cfg.postId));
    return window.fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      keepalive: Boolean(keepalive),
    });
  };

  const setTripped = () => {
    document.documentElement.classList.add('memberful-metering-tripped');
  };

  const hydrateCountdown = (remaining) => {
    const node = document.querySelector('[data-memberful-countdown]');
    if (!node) {
      return;
    }
    const template = node.getAttribute('data-memberful-template') || '';
    node.textContent = template.replace(/\{count\}/g, String(Math.max(0, remaining)));
    node.hidden = false;
  };

  const runFree = () => {
    if (cfg.limit <= 0) {
      setTripped();
      hydrateCountdown(0);
      return;
    }

    let views = prune(readViews());
    const alreadyCounted = Object.prototype.hasOwnProperty.call(views, cfg.postId);

    if (!alreadyCounted && Object.keys(views).length >= cfg.limit) {
      setTripped();
      hydrateCountdown(0);
      return;
    }

    if (!alreadyCounted) {
      views = record(views);
      endpointPost('record', true).catch(() => {});
    }

    hydrateCountdown(cfg.limit - Object.keys(views).length);
  };

  const runProtected = () => {
    const container = document.querySelector('.memberful-metering');

    endpointPost('sample', false)
      .then((response) => response.json())
      .then((payload) => {
        const data = payload && payload.success ? payload.data : null;
        if (!data || !data.released || !container) {
          return;
        }

        const content = container.querySelector('.memberful-metering__content');
        const paywall = container.querySelector('.memberful-metering__paywall');
        if (content) {
          content.innerHTML = data.html || '';
          content.hidden = false;
        }
        if (paywall) {
          paywall.hidden = true;
        }

        record(prune(readViews()));
        hydrateCountdown(data.remaining || 0);
      })
      .catch(() => {
        // Endpoint/network failure leaves the cached paywall in place - fail-closed for protected content.
      });
  };

  if (cfg.mode === 'free_meter') {
    runFree();
  } else if (cfg.mode === 'protected_sample') {
    runProtected();
  }
})();
