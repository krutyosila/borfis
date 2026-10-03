// Börfi's — küçük yardımcılar (çerezsiz, kütüphanesiz)
document.addEventListener('DOMContentLoaded', () => {
  // Harita yalnızca istenince yüklenir (hız + gizlilik)
  document.querySelectorAll('.map-box[data-map-src]').forEach((box) => {
    const load = () => {
      if (box.querySelector('iframe')) return;
      const f = document.createElement('iframe');
      f.src = box.dataset.mapSrc;
      f.title = 'Harita';
      f.loading = 'lazy';
      f.referrerPolicy = 'no-referrer-when-downgrade';
      box.replaceChildren(f);
    };
    box.querySelector('.map-load')?.addEventListener('click', load);
  });

  // Menü sekmelerinde aktif kategoriyi işaretle
  const tabs = [...document.querySelectorAll('.cat-tabs .tab')];
  if (tabs.length && 'IntersectionObserver' in window) {
    const byId = new Map(tabs.map((t) => [t.getAttribute('href').slice(1), t]));
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting) {
          tabs.forEach((t) => t.classList.remove('is-active'));
          byId.get(en.target.id)?.classList.add('is-active');
        }
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    byId.forEach((_, id) => { const el = document.getElementById(id); if (el) io.observe(el); });
  }
});

// Fotoğraf büyütme: dokununca tam ekran, iki parmak / çift dokunma / tekerlek ile yakınlaştırma
(() => {
  const dlg = document.querySelector('dialog.lightbox');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  const stage = dlg.querySelector('.lb-stage');
  const img = dlg.querySelector('.lb-img');
  const MIN = 1, MAX = 5;
  let scale = 1, x = 0, y = 0, base = null;
  const pts = new Map();
  let pinch = null, pan = null, lastTap = 0, moved = false;

  const fit = () => {
    const r = stage.getBoundingClientRect();
    const iw = img.naturalWidth || 1, ih = img.naturalHeight || 1;
    const k = Math.min(r.width / iw, r.height / ih, 1);
    base = { w: iw * k, h: ih * k, sw: r.width, sh: r.height };
  };
  const clamp = () => {
    scale = Math.min(MAX, Math.max(MIN, scale));
    const w = base.w * scale, h = base.h * scale;
    const minX = Math.min(0, base.sw - w), maxX = Math.max(0, base.sw - w);
    const minY = Math.min(0, base.sh - h), maxY = Math.max(0, base.sh - h);
    x = w <= base.sw ? (base.sw - w) / 2 : Math.min(maxX, Math.max(minX, x));
    y = h <= base.sh ? (base.sh - h) / 2 : Math.min(maxY, Math.max(minY, y));
  };
  const render = () => {
    clamp();
    img.style.width = base.w + 'px';
    img.style.height = base.h + 'px';
    img.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
  };
  const zoomAt = (newScale, cx, cy) => {
    const r = stage.getBoundingClientRect();
    const px = cx - r.left, py = cy - r.top;
    const ratio = Math.min(MAX, Math.max(MIN, newScale)) / scale;
    x = px - (px - x) * ratio;
    y = py - (py - y) * ratio;
    scale *= ratio;
    render();
  };
  const reset = () => { scale = 1; x = 0; y = 0; if (base) render(); };

  const open = (src, alt) => {
    img.style.cssText = 'position:absolute;left:0;top:0;max-width:none;max-height:none';
    img.alt = alt || '';
    img.onload = () => { fit(); reset(); };
    img.src = src;
    dlg.showModal();
    if (img.complete && img.naturalWidth) { fit(); reset(); }
  };

  document.addEventListener('click', (e) => {
    const t = e.target.closest('img[data-zoom]');
    if (t) { e.preventDefault(); open(t.currentSrc || t.src, t.alt); }
  });
  dlg.querySelector('.lb-close').addEventListener('click', () => dlg.close());
  dlg.addEventListener('close', () => { pts.clear(); img.removeAttribute('src'); });
  window.addEventListener('resize', () => { if (dlg.open) { fit(); reset(); } });

  let downOnBackdrop = false;
  stage.addEventListener('pointerdown', (e) => {
    if (pts.size === 0) downOnBackdrop = e.target === stage;
    stage.setPointerCapture(e.pointerId);
    pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    moved = false;
    if (pts.size === 2) {
      const [a, b] = [...pts.values()];
      pinch = { d: Math.hypot(a.x - b.x, a.y - b.y), s: scale };
      pan = null;
    } else if (pts.size === 1) {
      pan = { x: e.clientX, y: e.clientY, ox: x, oy: y };
    }
  });
  stage.addEventListener('pointermove', (e) => {
    if (!pts.has(e.pointerId)) return;
    pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pinch && pts.size === 2) {
      const [a, b] = [...pts.values()];
      const d = Math.hypot(a.x - b.x, a.y - b.y);
      zoomAt(pinch.s * d / pinch.d, (a.x + b.x) / 2, (a.y + b.y) / 2);
      moved = true;
    } else if (pan && pts.size === 1) {
      if (Math.abs(e.clientX - pan.x) + Math.abs(e.clientY - pan.y) > 6) moved = true;
      x = pan.ox + (e.clientX - pan.x);
      y = pan.oy + (e.clientY - pan.y);
      render();
    }
  });
  const end = (e) => {
    pts.delete(e.pointerId);
    if (pts.size < 2) pinch = null;
    if (pts.size === 1) {
      const p = [...pts.values()][0];
      pan = { x: p.x, y: p.y, ox: x, oy: y };
    } else if (pts.size === 0) {
      pan = null;
      if (!moved && e.type === 'pointerup') {
        const now = Date.now();
        if (now - lastTap < 300) {
          zoomAt(scale > 1.2 ? 1 : 2.5, e.clientX, e.clientY);
          lastTap = 0;
        } else {
          lastTap = now;
          // Fotoğrafın dışına tek dokunuş kapatır
          if (downOnBackdrop && scale === 1) setTimeout(() => { if (lastTap === now) dlg.close(); }, 320);
        }
      }
    }
  };
  stage.addEventListener('pointerup', end);
  stage.addEventListener('pointercancel', end);
  stage.addEventListener('wheel', (e) => {
    e.preventDefault();
    zoomAt(scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15), e.clientX, e.clientY);
  }, { passive: false });
})();
