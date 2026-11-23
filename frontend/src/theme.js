const THEME_KEY = 'matcha-theme';

export function initTheme() {
  const saved = localStorage.getItem(THEME_KEY);
  const theme = saved === 'dark' || saved === 'light' ? saved : 'light';
  applyTheme(theme);
}

export function currentTheme() {
  return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
}

export function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  localStorage.setItem(THEME_KEY, theme);
}

export function toggleTheme() {
  applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}
