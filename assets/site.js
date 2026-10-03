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
