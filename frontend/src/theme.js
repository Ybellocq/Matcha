const THEME_KEY = 'matcha-theme';

// Fallback mémoire : certains navigateurs (Safari privé, Firefox strict,
// extensions anti-cookies) lèvent "Access to storage is not allowed" à
// l'accès au localStorage. L'application doit rester fonctionnelle.
const memoryTheme = { value: 'light' };

function readStorage() {
  try {
    return localStorage.getItem(THEME_KEY);
  } catch {
    return memoryTheme.value;
  }
}

function writeStorage(theme) {
  try {
    localStorage.setItem(THEME_KEY, theme);
  } catch {
    memoryTheme.value = theme;
  }
}

export function initTheme() {
  const saved = readStorage();
  const theme = saved === 'dark' || saved === 'light' ? saved : 'light';
  applyTheme(theme);
}

export function currentTheme() {
  return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
}

export function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  writeStorage(theme);
}

export function toggleTheme() {
  applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}
