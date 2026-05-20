import { session } from './stores/session.js';

export const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

async function parseJson(response) {
  try {
    return await response.json();
  } catch {
    return {};
  }
}

export async function api(path, options = {}) {
  const headers = options.headers ? { ...options.headers } : {};
  const isForm = options.body instanceof FormData;
  if (!isForm) {
    headers['Content-Type'] = 'application/json';
  }
  if (session.csrfToken) {
    headers['X-CSRF-Token'] = session.csrfToken;
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: options.method || 'GET',
    body: isForm ? options.body : options.body ? JSON.stringify(options.body) : undefined,
    credentials: 'include',
    headers
  });

  const data = await parseJson(response);
  if (!response.ok) {
    const error = data.error || 'Request failed';
    throw new Error(error);
  }
  return data;
}

export async function refreshSession() {
  try {
    const data = await api('/auth/me');
    session.user = data.user || data.profile || null;
    session.csrfToken = data.csrfToken || session.csrfToken;
  } catch {
    session.user = null;
  }
}

export async function refreshCounters() {
  if (!session.user) return;
  try {
    const [messages, notifications] = await Promise.all([
      api('/messages/unread'),
      api('/notifications/unread')
    ]);
    session.unreadMessages = messages.count || 0;
    session.unreadNotifications = notifications.count || 0;
  } catch {
    session.unreadMessages = 0;
    session.unreadNotifications = 0;
  }
}
