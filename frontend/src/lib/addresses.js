import { api } from './api.js';
import { getAuth, getSessionId } from './session.js';
import { shouldMock } from './data.js';

function identity() {
  const auth = getAuth();
  const q = { session_id: getSessionId() };
  if (auth?.user_id) q.user_id = auth.user_id;
  return q;
}

const MOCK_KEY = 'bw_addresses_mock';
const LEGACY_KEY = 'bw_address';

function readMock() {
  try {
    const raw = JSON.parse(localStorage.getItem(MOCK_KEY) || 'null');
    if (Array.isArray(raw)) return raw;
  } catch {
    // abaikan
  }
  // Migrasi sekali dari form manual lama agar tidak hilang.
  try {
    const legacy = JSON.parse(localStorage.getItem(LEGACY_KEY) || 'null');
    if (legacy && (legacy.street || legacy.address)) {
      const seeded = [
        {
          _id: `mock-addr-${Date.now()}`,
          label: 'Rumah',
          recipient: legacy.name || 'Penerima',
          phone: legacy.phone || '',
          address: legacy.street || legacy.address || '',
          city: legacy.city || '',
          is_default: true,
        },
      ];
      localStorage.setItem(MOCK_KEY, JSON.stringify(seeded));
      return seeded;
    }
  } catch {
    // abaikan
  }
  return [];
}

function writeMock(list) {
  try {
    localStorage.setItem(MOCK_KEY, JSON.stringify(list));
  } catch {
    // abaikan
  }
}

/** GET /addresses — { list, isMock }. */
export async function listAddresses() {
  try {
    const res = await api.get('/addresses', { query: identity() });
    return { list: res.data || [], isMock: false };
  } catch (e) {
    if (!shouldMock(e)) throw e;
    console.warn('[mock] backend unreachable (/addresses), using mock data.');
    return { list: readMock(), isMock: true };
  }
}

function validateInput(input, currentCount) {
  const errs = {};
  if (!String(input.label || '').trim()) errs.label = 'Label wajib diisi (cth. Rumah/Kantor).';
  if (!String(input.recipient || '').trim()) errs.recipient = 'Nama penerima wajib diisi.';
  if (!String(input.phone || '').trim()) errs.phone = 'No. telepon wajib diisi.';
  else if (!/^[0-9+()\-.\s]{9,16}$/.test(String(input.phone).trim())) errs.phone = 'No. telepon tidak valid (9–16 digit).';
  if (!String(input.address || '').trim()) errs.address = 'Alamat lengkap wajib diisi.';
  if (!String(input.city || '').trim()) errs.city = 'Kota wajib diisi.';
  if (currentCount >= 10) errs._global = 'Maksimal 10 alamat per akun.';
  return errs;
}

/** POST /addresses */
export async function createAddress(input) {
  const body = { ...identity(), ...input };
  try {
    return await api.post('/addresses', { body });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const list = readMock();
    const errs = validateInput(input, list.length);
    if (Object.keys(errs).length > 0) {
      throw Object.assign(new Error(errs._global || 'Periksa lagi isian alamat.'), { status: 422, fields: errs });
    }
    const isDefault = Boolean(input.is_default) || list.length === 0;
    const created = { _id: `mock-addr-${Date.now()}`, ...input, is_default: isDefault };
    writeMock([...(isDefault ? list.map((a) => ({ ...a, is_default: false })) : list), created]);
    return { status: 'success', message: 'Alamat berhasil ditambahkan', data: created };
  }
}

/** PUT /addresses/{id} */
export async function updateAddress(id, input) {
  const body = { ...identity(), ...input };
  try {
    return await api.put(`/addresses/${id}`, { body });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const list = readMock();
    if (!list.some((a) => a._id === id)) throw Object.assign(new Error('Alamat tidak ditemukan'), { status: 404 });
    const next = list.map((a) => {
      if (a._id !== id) return input.is_default ? { ...a, is_default: false } : a;
      return { ...a, ...input, is_default: input.is_default ?? a.is_default };
    });
    writeMock(next);
    return { status: 'success', message: 'Alamat berhasil diubah', data: next.find((a) => a._id === id) };
  }
}

/** DELETE /addresses/{id} */
export async function deleteAddress(id) {
  try {
    return await api.delete(`/addresses/${id}`, { query: identity() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const list = readMock().filter((a) => a._id !== id);
    if (list.length > 0 && !list.some((a) => a.is_default)) list[0] = { ...list[0], is_default: true };
    writeMock(list);
    return { status: 'success', message: 'Alamat dihapus' };
  }
}

/** Jadikan utama (PUT is_default=true). */
export async function setDefaultAddress(id) {
  return updateAddress(id, { is_default: true });
}

/** Rangkaian alamat menjadi satu baris untuk shipping_address. */
export function formatAddress(a) {
  if (!a) return '';
  return `${a.recipient || a.name || ''} | ${a.phone || ''} | ${a.address || a.street || ''}, ${a.city || ''}`.trim();
}
