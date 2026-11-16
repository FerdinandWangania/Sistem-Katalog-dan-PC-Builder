// Minimal API client for the Laravel backend (see backend/routes/api.php).
// Base URL via env, guest identity via session_id, login via user_id + Bearer.

import { getAuth, getSessionId } from './session.js';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';

export class ApiError extends Error {
  constructor(status, payload) {
    super(payload?.message || `Request failed (${status})`);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
    // Structured per-field errors for 422 responses
    this.fields = payload?.errors || null;
  }
}

/** Owner identity: { user_id } when logged in, { session_id } for guests. */
export function ownerParams() {
  const auth = getAuth();
  if (auth?.user_id) return { user_id: auth.user_id };
  return { session_id: getSessionId() };
}

function authHeaders() {
  const auth = getAuth();
  return auth?.token ? { Authorization: `Bearer ${auth.token}` } : {};
}

function buildUrl(path, query) {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(query || {})) {
    if (v !== undefined && v !== null && v !== '') qs.append(k, String(v));
  }
  const suffix = qs.toString();
  return `${BASE_URL}${path}${suffix ? `?${suffix}` : ''}`;
}

async function parseBody(res) {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}

export async function request(method, path, { query, body } = {}) {
  const res = await fetch(buildUrl(path, query), {
    method,
    headers: { 'Content-Type': 'application/json', ...authHeaders() },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const payload = await parseBody(res);
  if (!res.ok) throw new ApiError(res.status, payload);
  return payload;
}

export const api = {
  get: (path, opts) => request('GET', path, opts),
  post: (path, opts) => request('POST', path, opts),
  put: (path, opts) => request('PUT', path, opts),
  patch: (path, opts) => request('PATCH', path, opts),
  delete: (path, opts) => request('DELETE', path, opts),
  /** GET /api — backend health/info, no auth needed. */
  health: () => request('GET', ''),
};
