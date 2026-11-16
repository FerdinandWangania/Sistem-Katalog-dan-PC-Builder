// Guest session + auth storage (no UI logic here).
// Backend contract (EnsureOwner.php): guest requests carry `session_id`,
// logged-in requests carry `user_id` + `Authorization: Bearer <token>`.

const SESSION_KEY = 'bw_session_id';
const AUTH_KEY = 'bw_auth';

function newId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
  return `guest-${Date.now()}-${Math.floor(Math.random() * 1e9)}`;
}

export function getSessionId() {
  let id = null;
  try {
    id = localStorage.getItem(SESSION_KEY);
    if (!id) {
      id = newId();
      localStorage.setItem(SESSION_KEY, id);
    }
  } catch {
    id = id || newId();
  }
  return id;
}

export function getAuth() {
  try {
    const raw = localStorage.getItem(AUTH_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function setAuth(auth) {
  try {
    if (!auth) localStorage.removeItem(AUTH_KEY);
    else localStorage.setItem(AUTH_KEY, JSON.stringify(auth));
  } catch {
    // storage unavailable: auth lives in memory only for this session
  }
}

export function clearAuth() {
  setAuth(null);
}
