// Runs in <head> before first paint: applies the saved admin theme (no flash) and marks JS-enabled pages.
(function () {
  document.documentElement.classList.add('js');
  try { var t = localStorage.getItem('vw-theme'); if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t); } catch (e) {}
})();
