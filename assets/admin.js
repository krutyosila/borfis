// Silme gibi geri alınamaz işlemlerden önce onay iste
document.addEventListener('submit', (ev) => {
  const form = ev.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    ev.preventDefault();
  }
});

// Görsel seçilince önizleme göster
document.addEventListener('change', (ev) => {
  const input = ev.target;
  if (!(input instanceof HTMLInputElement) || input.type !== 'file' || input.multiple || !input.files?.[0]) return;
  const wrap = input.closest('.image-field');
  if (!wrap) return;
  let img = wrap.querySelector('img.preview');
  if (!img) {
    img = document.createElement('img');
    img.className = 'preview';
    img.alt = '';
    wrap.prepend(img);
  }
  img.src = URL.createObjectURL(input.files[0]);
});
