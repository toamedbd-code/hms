const DATABASE_NAME = 'hms-offline-billing';
const DATABASE_VERSION = 3;
const REQUEST_STORE = 'requests';
const META_STORE = 'meta';
const DEVICE_KEY_META = 'persistent-device-crypto-key:v1';
const legacyDeviceKeyMeta = (adminId) => `persistent-device-crypto-key:v1:${Number(adminId)}`;
const disabledOfflineBillingMeta = (adminId) => `offline-billing-disabled:${Number(adminId)}`;
const offlineBillingControlMeta = (adminId) => `offline-billing-control:${Number(adminId)}`;

let databasePromise;
let activeSync = false;
let installed = false;
let offlineCryptoModule;
const unlockedOfflineKeys = new Map();

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.__hmsInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('offline-billing:install-available'));
    });
    window.addEventListener('appinstalled', () => {
        window.__hmsInstallPrompt = null;
        window.dispatchEvent(new CustomEvent('offline-billing:install-complete'));
    });
    window.installHmsOfflineApp = async () => {
        const promptEvent = window.__hmsInstallPrompt;
        if (!promptEvent) return;
        await promptEvent.prompt();
        await promptEvent.userChoice;
        window.__hmsInstallPrompt = null;
        window.dispatchEvent(new CustomEvent('offline-billing:install-complete'));
    };
}

export const createOfflineRequestId = () => {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    globalThis.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
};

export const formatOfflineBillReference = (requestId) => {
    const hex = String(requestId).replace(/-/g, '');
    if (!/^[\da-f]{32}$/i.test(hex)) {
        throw new Error('The offline bill reference could not be formatted.');
    }

    const bytes = hex.match(/.{2}/g).slice(0, 8).map((byte) => Number.parseInt(byte, 16));
    const token = btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/g, '')
        .slice(0, 10)
        .toUpperCase();
    return `OFF-${token}`;
};

const openDatabase = () => {
    if (databasePromise) return databasePromise;
    if (typeof indexedDB === 'undefined') {
        return Promise.reject(new Error('Offline billing storage is not available in this browser.'));
    }

    databasePromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onupgradeneeded = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains(REQUEST_STORE)) {
                const store = database.createObjectStore(REQUEST_STORE, { keyPath: 'id' });
                store.createIndex('adminId', 'adminId', { unique: false });
            }
            if (!database.objectStoreNames.contains(META_STORE)) {
                database.createObjectStore(META_STORE, { keyPath: 'key' });
            }
        };
        request.onsuccess = () => {
            request.result.onversionchange = () => {
                request.result.close();
                databasePromise = null;
            };
            resolve(request.result);
        };
        request.onerror = () => reject(request.error ?? new Error('Could not open offline billing storage.'));
        request.onblocked = () => reject(new Error('Offline billing storage is blocked by another browser tab.'));
    }).catch((error) => {
        databasePromise = null;
        throw error;
    });

    return databasePromise;
};

const withStore = async (mode, callback) => {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(REQUEST_STORE, mode);
        const store = transaction.objectStore(REQUEST_STORE);
        let result;
        try {
            result = callback(store);
        } catch (error) {
            reject(error);
            return;
        }
        transaction.oncomplete = () => resolve(result?.result);
        transaction.onerror = () => reject(transaction.error ?? new Error('Offline billing storage failed.'));
        transaction.onabort = () => reject(transaction.error ?? new Error('Offline billing storage was interrupted.'));
    });
};

const getMeta = async (key) => {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(META_STORE, 'readonly');
        const request = transaction.objectStore(META_STORE).get(key);
        request.onsuccess = () => resolve(request.result?.value ?? null);
        request.onerror = () => reject(request.error ?? new Error('Could not read offline billing settings.'));
    });
};

const setMeta = async (key, value) => {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(META_STORE, 'readwrite');
        transaction.objectStore(META_STORE).put({ key, value });
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error ?? new Error('Could not save offline billing settings.'));
    });
};

const cryptoModule = () => {
    if (!offlineCryptoModule) {
        offlineCryptoModule = import(/* @vite-ignore */ '/offline-crypto.js');
    }
    return offlineCryptoModule;
};

const requireOfflineKey = (adminId) => {
    const key = unlockedOfflineKeys.get(Number(adminId));
    if (!key) throw new Error('Enable offline billing on this device before saving or syncing while offline.');
    if (!isOfflineCryptoKey(key)) {
        throw new Error('The saved offline encryption key is invalid. Ask the developer to prepare offline billing on this browser again.');
    }
    return key;
};

const isOfflineCryptoKey = (key) => (
    Boolean(key)
    && key.type === 'secret'
    && key.algorithm?.name === 'AES-GCM'
    && Array.from(key.usages || []).includes('decrypt')
    && Array.from(key.usages || []).includes('encrypt')
);

const getPersistentOfflineKeys = async (adminId) => {
    const [accountKey, deviceKey] = await Promise.all([
        getMeta(legacyDeviceKeyMeta(adminId)),
        getMeta(DEVICE_KEY_META),
    ]);
    return [...new Set([accountKey, deviceKey].filter(isOfflineCryptoKey))];
};

const verifyPersistentOfflineKey = async (key, adminId, settings) => {
    if (!isOfflineCryptoKey(key)) return false;
    const { decryptOfflineValue, encryptOfflineValue } = await cryptoModule();
    try {
        if (settings?.deviceVerifier?.ciphertext) {
            return await decryptOfflineValue(
                key,
                settings.deviceVerifier,
                `verifier:${adminId}`
            ) === `hms-offline-key-check:v1:${adminId}`;
        }
        if (settings?.verifier?.ciphertext) {
            return await decryptOfflineValue(
                key,
                settings.verifier,
                `verifier:${adminId}`
            ) === `hms-offline-key-check:v1:${adminId}`;
        }
        await encryptOfflineValue(key, 'hms-offline-key-health-check:v1', `health:${adminId}`);
        return true;
    } catch {
        return false;
    }
};

const encryptedRequest = async (entry, explicitKey = null) => {
    const key = explicitKey || requireOfflineKey(entry.adminId);
    const { encryptOfflineValue } = await cryptoModule();
    const protectedFields = {
        url: entry.url,
        method: entry.method,
        data: entry.data ?? null,
        routeName: entry.routeName ?? '',
        label: entry.label ?? 'Billing request',
        csrfToken: entry.csrfToken ?? '',
        lastError: entry.lastError ?? '',
        syncedAt: entry.syncedAt ?? null,
    };
    const encrypted = await encryptOfflineValue(
        key,
        protectedFields,
        `request:${entry.adminId}:${entry.id}`
    );

    return {
        id: entry.id,
        adminId: Number(entry.adminId),
        status: entry.status,
        attempts: Number(entry.attempts || 0),
        createdAt: entry.createdAt,
        updatedAt: new Date().toISOString(),
        cryptoVersion: 1,
        iv: encrypted.iv,
        ciphertext: encrypted.ciphertext,
    };
};

const decryptedRequest = async (entry, explicitKey = null) => {
    if (!entry.cryptoVersion) return entry;
    const key = explicitKey || requireOfflineKey(entry.adminId);
    const { decryptOfflineValue } = await cryptoModule();
    const protectedFields = JSON.parse(await decryptOfflineValue(
        key,
        { iv: entry.iv, ciphertext: entry.ciphertext },
        `request:${entry.adminId}:${entry.id}`
    ));
    return { ...entry, ...protectedFields };
};

const decryptedRequestWithAvailableKeys = async (entry, preferredKey = null) => {
    if (!entry.cryptoVersion) return entry;
    const keys = [...new Set([
        preferredKey,
        unlockedOfflineKeys.get(Number(entry.adminId)),
        ...(await getPersistentOfflineKeys(entry.adminId)),
    ].filter(isOfflineCryptoKey))];
    let lastError;

    for (const key of keys) {
        try {
            return await decryptedRequest(entry, key);
        } catch (error) {
            lastError = error;
        }
    }

    const detail = lastError instanceof Error ? lastError.message : 'No stored key could decrypt this request.';
    throw new Error(`Could not decrypt saved offline bill ${String(entry.id).slice(0, 8)} with available device keys. ${detail}`);
};

const encryptLegacyRequests = async (adminId) => {
    const database = await openDatabase();
    const entries = await new Promise((resolve, reject) => {
        const request = database.transaction(REQUEST_STORE, 'readonly')
            .objectStore(REQUEST_STORE)
            .getAll();
        request.onsuccess = () => resolve(request.result.filter((entry) => Number(entry.adminId) === Number(adminId)));
        request.onerror = () => reject(request.error ?? new Error('Could not read saved offline requests.'));
    });

    for (const entry of entries.filter((request) => !request.cryptoVersion)) {
        const encrypted = await encryptedRequest(entry);
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(REQUEST_STORE, 'readwrite');
            transaction.objectStore(REQUEST_STORE).put(encrypted);
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error ?? new Error('Could not encrypt a saved offline request.'));
        });
    }
};

const notifyQueueChanged = () => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('offline-billing:queue-updated'));
    }
};

export const getOfflineBillingEncryptionStatus = async (adminId) => {
    const numericAdminId = Number(adminId);
    const disabled = Boolean(await getMeta(disabledOfflineBillingMeta(numericAdminId)));
    if (!disabled) {
        await restorePersistentOfflineBillingKey(numericAdminId);
    }
    const settings = await getMeta(`crypto:${numericAdminId}`);
    return {
        configured: Boolean(
            (settings?.salt && settings?.verifier?.ciphertext)
            || settings?.deviceVerifier?.ciphertext
        ),
        passphraseProtected: Boolean(settings?.salt && settings?.verifier?.ciphertext),
        controlPasswordConfigured: Boolean(
            (await getMeta(offlineBillingControlMeta(numericAdminId)))?.verifier?.ciphertext
        ),
        enabled: !disabled,
        deviceReady: (await getPersistentOfflineKeys(numericAdminId)).length > 0,
        unlocked: !disabled && unlockedOfflineKeys.has(numericAdminId),
    };
};

export const isOfflineBillingUnlocked = (adminId) => unlockedOfflineKeys.has(Number(adminId));

const prepareUnlockedOfflineAccount = async (numericAdminId, key) => {
    unlockedOfflineKeys.set(numericAdminId, key);
    await setMeta('active-admin-id', numericAdminId);
    try {
        await encryptLegacyRequests(numericAdminId);
        await clearStaleOfflineCryptoErrors(numericAdminId, key);
        const legacyDraftKey = `hms:offline-billing-draft:${numericAdminId}`;
        const legacyDraft = localStorage.getItem(legacyDraftKey);
        if (legacyDraft && !(await getMeta(`encrypted-draft:${numericAdminId}`))) {
            const { encryptOfflineValue } = await cryptoModule();
            const encryptedDraft = await encryptOfflineValue(
                key,
                JSON.parse(legacyDraft),
                `draft:${numericAdminId}`
            );
            await setMeta(`encrypted-draft:${numericAdminId}`, encryptedDraft);
        }
        if (legacyDraft) localStorage.removeItem(legacyDraftKey);
    } catch (error) {
        unlockedOfflineKeys.delete(numericAdminId);
        throw error;
    }
    notifyQueueChanged();
};

const clearStaleOfflineCryptoErrors = async (adminId, key) => {
    const database = await openDatabase();
    const entries = await new Promise((resolve, reject) => {
        const request = database.transaction(REQUEST_STORE, 'readonly')
            .objectStore(REQUEST_STORE)
            .getAll();
        request.onsuccess = () => resolve(request.result.filter((entry) => (
            Number(entry.adminId) === Number(adminId)
        )));
        request.onerror = () => reject(request.error ?? new Error('Could not check offline requests for stale encryption errors.'));
    });

    for (const entry of entries) {
        const current = await decryptedRequestWithAvailableKeys(entry, key);
        const hasStaleCryptoError = /not of type ['"]?CryptoKey/i.test(String(current.lastError || ''));
        if (!hasStaleCryptoError && entry.cryptoVersion) continue;
        const updated = await encryptedRequest({
            ...current,
            status: hasStaleCryptoError && current.status !== 'needs_review' ? 'queued' : current.status,
            lastError: hasStaleCryptoError ? '' : current.lastError,
            updatedAt: new Date().toISOString(),
        }, key);
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(REQUEST_STORE, 'readwrite');
            transaction.objectStore(REQUEST_STORE).put(updated);
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error ?? new Error('Could not clear a stale offline encryption error.'));
        });
    }
};

export const unlockOfflineBilling = async (adminId, passphrase, rememberOnDevice = false) => {
    const numericAdminId = Number(adminId);
    if (!Number.isInteger(numericAdminId) || numericAdminId < 1) {
        throw new Error('Could not identify the signed-in admin account.');
    }

    const { createOfflineCryptoSalt, deriveOfflineCryptoKey, encryptOfflineValue, decryptOfflineValue } = await cryptoModule();
    let settings = await getMeta(`crypto:${numericAdminId}`);
    let key;

    if (settings?.salt && settings?.verifier?.ciphertext) {
        if (typeof passphrase !== 'string' || passphrase.length < 12) {
            throw new Error('Enter the existing offline passphrase (at least 12 characters).');
        }
        key = await deriveOfflineCryptoKey(passphrase, settings.salt);
        try {
            const verifier = await decryptOfflineValue(
                key,
                settings.verifier,
                `verifier:${numericAdminId}`
            );
            if (verifier !== `hms-offline-key-check:v1:${numericAdminId}`) {
                throw new Error('The offline passphrase is incorrect.');
            }
        } catch (error) {
            throw new Error(error instanceof Error && error.message === 'The offline passphrase is incorrect.'
                ? error.message
                : 'The offline passphrase is incorrect.');
        }
    } else if (settings?.deviceVerifier?.ciphertext) {
        const persistentKeys = await getPersistentOfflineKeys(numericAdminId);
        let deviceKey = null;
        for (const candidate of persistentKeys) {
            if (await verifyPersistentOfflineKey(candidate, numericAdminId, settings)) {
                deviceKey = candidate;
                break;
            }
        }
        if (!deviceKey) {
            throw new Error('This browser no longer has the encryption key. Contact the developer before clearing browser data.');
        }
        key = deviceKey;
    } else {
        if (typeof passphrase !== 'string' || passphrase.length < 12) {
            throw new Error('Choose a passphrase with at least 12 characters.');
        }
        const salt = createOfflineCryptoSalt();
        key = await deriveOfflineCryptoKey(passphrase, salt);
        settings = {
            salt,
            verifier: await encryptOfflineValue(
                key,
                `hms-offline-key-check:v1:${numericAdminId}`,
                `verifier:${numericAdminId}`
            ),
        };
    }

    if (rememberOnDevice) {
        const accountKeyName = legacyDeviceKeyMeta(numericAdminId);
        const existingAccountKey = await getMeta(accountKeyName);
        const persistentKeys = await getPersistentOfflineKeys(numericAdminId);
        let existingDeviceKey = null;
        for (const candidate of persistentKeys) {
            if (await verifyPersistentOfflineKey(candidate, numericAdminId, settings)) {
                existingDeviceKey = candidate;
                break;
            }
        }
        let deviceKey = existingDeviceKey;
        if (deviceKey) {
            try {
                const proof = await encryptOfflineValue(key, 'hms-device-key-match:v1', 'device-key:v1');
                const verified = await decryptOfflineValue(deviceKey, proof, 'device-key:v1');
                if (verified !== 'hms-device-key-match:v1') {
                    throw new Error('Invalid device key proof.');
                }
            } catch {
                if (isOfflineCryptoKey(existingAccountKey)) {
                    throw new Error('This account already has offline data protected by a different device key. Existing data was not changed.');
                }
                if (!settings?.salt || !settings?.verifier?.ciphertext) {
                    throw new Error('The existing device key does not match this account. Existing offline data was not changed.');
                }
                deviceKey = key;
                await setMeta(accountKeyName, deviceKey);
            }
        } else {
            deviceKey = key;
            await setMeta(DEVICE_KEY_META, deviceKey);
        }

        key = deviceKey;
        settings = {
            ...(settings || {}),
            deviceVerifier: await encryptOfflineValue(
                key,
                `hms-offline-key-check:v1:${numericAdminId}`,
                `verifier:${numericAdminId}`
            ),
        };
        await setMeta(`crypto:${numericAdminId}`, settings);
    } else if (!(await getMeta(`crypto:${numericAdminId}`))) {
        await setMeta(`crypto:${numericAdminId}`, settings);
    }

    const wasDisabled = Boolean(await getMeta(disabledOfflineBillingMeta(numericAdminId)));
    if (wasDisabled) {
        await setMeta(disabledOfflineBillingMeta(numericAdminId), false);
    }
    try {
        await prepareUnlockedOfflineAccount(numericAdminId, key);
    } catch (error) {
        if (wasDisabled) {
            await setMeta(disabledOfflineBillingMeta(numericAdminId), true);
        }
        throw error;
    }
};

const enableOfflineBillingOnDevice = async (adminId) => {
    const numericAdminId = Number(adminId);
    if (!Number.isInteger(numericAdminId) || numericAdminId < 1) {
        throw new Error('Could not identify the signed-in admin account.');
    }
    if (!globalThis.crypto?.subtle) {
        throw new Error('This browser does not support secure offline billing.');
    }

    const settingsKey = `crypto:${numericAdminId}`;
    const settings = await getMeta(settingsKey);
    if (settings?.salt || settings?.verifier?.ciphertext) {
        throw new Error('This account already uses a passphrase-protected offline key. Enter that passphrase to unlock it.');
    }
    if (settings?.deviceVerifier?.ciphertext) {
        await setMeta(disabledOfflineBillingMeta(numericAdminId), false);
        await restorePersistentOfflineBillingKey(numericAdminId);
        if (!isOfflineBillingUnlocked(numericAdminId)) {
            await setMeta(disabledOfflineBillingMeta(numericAdminId), true);
            throw new Error('This device no longer has the saved offline key. Existing offline data was not changed.');
        }
        return;
    }

    const accountKeyName = legacyDeviceKeyMeta(numericAdminId);
    let key = await getMeta(accountKeyName);
    if (!isOfflineCryptoKey(key)) {
        key = await globalThis.crypto.subtle.generateKey(
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
        await setMeta(accountKeyName, key);
    }

    const { encryptOfflineValue } = await cryptoModule();
    await setMeta(settingsKey, {
        deviceVerifier: await encryptOfflineValue(
            key,
            `hms-offline-key-check:v1:${numericAdminId}`,
            `verifier:${numericAdminId}`
        ),
    });

    await setMeta(disabledOfflineBillingMeta(numericAdminId), false);
    try {
        await prepareUnlockedOfflineAccount(numericAdminId, key);
    } catch (error) {
        await setMeta(disabledOfflineBillingMeta(numericAdminId), true);
        throw error;
    }
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('offline-billing:unlocked'));
    }
};

const restorePersistentOfflineBillingKey = async (adminId) => {
    if (!Number.isInteger(adminId) || adminId < 1 || unlockedOfflineKeys.has(adminId)) return;
    if (await getMeta(disabledOfflineBillingMeta(adminId))) return;
    const settings = await getMeta(`crypto:${adminId}`);
    const persistentKeys = await getPersistentOfflineKeys(adminId);
    let key = null;
    for (const candidate of persistentKeys) {
        if (await verifyPersistentOfflineKey(candidate, adminId, settings)) {
            key = candidate;
            break;
        }
    }
    if (!key) {
        if (settings?.salt || settings?.verifier?.ciphertext || settings?.deviceVerifier?.ciphertext) return;
        key = persistentKeys[0];
        if (!key) return;
        const { encryptOfflineValue } = await cryptoModule();
        await setMeta(`crypto:${adminId}`, {
            deviceVerifier: await encryptOfflineValue(
                key,
                `hms-offline-key-check:v1:${adminId}`,
                `verifier:${adminId}`
            ),
        });
    }

    await prepareUnlockedOfflineAccount(adminId, key);
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('offline-billing:unlocked'));
    }
};

export const lockOfflineBilling = (adminId) => {
    unlockedOfflineKeys.delete(Number(adminId));
    notifyQueueChanged();
    window.dispatchEvent(new CustomEvent('offline-billing:locked'));
};

const disableOfflineBilling = async (adminId) => {
    const numericAdminId = Number(adminId);
    if (!Number.isInteger(numericAdminId) || numericAdminId < 1) {
        throw new Error('Could not identify the signed-in admin account.');
    }
    await setMeta(disabledOfflineBillingMeta(numericAdminId), true);
    lockOfflineBilling(numericAdminId);
};

const verifyOfflineBillingControlPassword = async (adminId, password, createIfMissing = false) => {
    if (typeof password !== 'string' || password.length < 12) {
        throw new Error('Enter a password with at least 12 characters.');
    }

    const settingsKey = offlineBillingControlMeta(adminId);
    const settings = await getMeta(settingsKey);
    const { createOfflineCryptoSalt, deriveOfflineCryptoKey, encryptOfflineValue, decryptOfflineValue } = await cryptoModule();
    const expectedVerifier = `hms-offline-control-check:v1:${adminId}`;
    const context = `offline-control:${adminId}`;

    if (!settings?.verifier?.ciphertext) {
        if (!createIfMissing) {
            throw new Error('Set an offline billing password before changing its status.');
        }
        const salt = createOfflineCryptoSalt();
        const key = await deriveOfflineCryptoKey(password, salt);
        await setMeta(settingsKey, {
            salt,
            verifier: await encryptOfflineValue(key, expectedVerifier, context),
        });
        return;
    }

    const key = await deriveOfflineCryptoKey(password, settings.salt);
    let verifier;
    try {
        verifier = await decryptOfflineValue(key, settings.verifier, context);
    } catch (error) {
        if (error instanceof Error && error.name !== 'OperationError') {
            throw error;
        }
        throw new Error('The offline billing password is incorrect.');
    }
    if (verifier !== expectedVerifier) {
        throw new Error('The offline billing password is incorrect.');
    }
};

export const enableOfflineBillingWithPassword = async (adminId, password, confirmation = '') => {
    const numericAdminId = Number(adminId);
    if (!Number.isInteger(numericAdminId) || numericAdminId < 1) {
        throw new Error('Could not identify the signed-in admin account.');
    }
    if (typeof password !== 'string' || password.length < 12) {
        throw new Error('Choose a password with at least 12 characters.');
    }

    const controlSettings = await getMeta(offlineBillingControlMeta(numericAdminId));
    if (controlSettings?.verifier?.ciphertext) {
        await verifyOfflineBillingControlPassword(numericAdminId, password);
    } else {
        if (password !== confirmation) {
            throw new Error('The passwords do not match.');
        }

        const settings = await getMeta(`crypto:${numericAdminId}`);
        if (settings?.salt && settings?.verifier?.ciphertext) {
            await unlockOfflineBilling(numericAdminId, password, true);
        } else if (settings?.deviceVerifier?.ciphertext) {
            await enableOfflineBillingOnDevice(numericAdminId);
        } else {
            await unlockOfflineBilling(numericAdminId, password, true);
        }
        await verifyOfflineBillingControlPassword(numericAdminId, password, true);
    }

    const settings = await getMeta(`crypto:${numericAdminId}`);
    if (settings?.salt && settings?.verifier?.ciphertext) {
        await unlockOfflineBilling(numericAdminId, password, true);
    } else {
        await enableOfflineBillingOnDevice(numericAdminId);
    }
};

export const disableOfflineBillingWithPassword = async (adminId, password) => {
    const numericAdminId = Number(adminId);
    if (!Number.isInteger(numericAdminId) || numericAdminId < 1) {
        throw new Error('Could not identify the signed-in admin account.');
    }
    await verifyOfflineBillingControlPassword(numericAdminId, password);
    await disableOfflineBilling(numericAdminId);
};

const offlineBillingDraftKey = (adminId, draftId = null) => {
    const numericAdminId = Number(adminId);
    if (!draftId) {
        return {
            key: `encrypted-draft:${numericAdminId}`,
            additionalData: `draft:${numericAdminId}`,
        };
    }

    if (!/^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i.test(draftId)) {
        throw new Error('The billing draft identifier is invalid.');
    }

    return {
        key: `encrypted-draft:${numericAdminId}:${draftId}`,
        additionalData: `draft:${numericAdminId}:${draftId}`,
    };
};

export const saveOfflineBillingDraft = async (adminId, draft, draftId = null) => {
    const key = requireOfflineKey(adminId);
    const draftStorage = offlineBillingDraftKey(adminId, draftId);
    const { encryptOfflineValue } = await cryptoModule();
    const encrypted = await encryptOfflineValue(key, draft, draftStorage.additionalData);
    await setMeta(draftStorage.key, encrypted);
};

export const getOfflineBillingDraft = async (adminId, draftId = null) => {
    const key = requireOfflineKey(adminId);
    const draftStorage = offlineBillingDraftKey(adminId, draftId);
    const encrypted = await getMeta(draftStorage.key);
    if (!encrypted) return null;
    const { decryptOfflineValue } = await cryptoModule();
    return JSON.parse(await decryptOfflineValue(key, encrypted, draftStorage.additionalData));
};

export const clearOfflineBillingDraft = async (adminId, draftId = null) => {
    const database = await openDatabase();
    const draftStorage = offlineBillingDraftKey(adminId, draftId);
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(META_STORE, 'readwrite');
        transaction.objectStore(META_STORE).delete(draftStorage.key);
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error ?? new Error('Could not clear the saved billing draft.'));
    });
};

export const saveEncryptedOfflineBillingShell = async (adminId, html) => {
    const key = requireOfflineKey(adminId);
    const { encryptOfflineValue } = await cryptoModule();
    const encrypted = await encryptOfflineValue(key, html, `shell:${Number(adminId)}`);
    await setMeta(`encrypted-shell:${Number(adminId)}`, encrypted);
};

const cacheOfflineInvoiceDesignAssets = async (design = {}) => {
    const urls = [...new Set([
        design.header_image,
        design.footer_image,
        design.full_page_pad_image,
    ].filter((url) => typeof url === 'string' && url.trim() && !url.startsWith('data:')))];
    if (!urls.length) return;

    const cachedAssets = await getMeta('offline-invoice-design-assets:v1') || {};
    await Promise.all(urls.map(async (url) => {
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Could not cache invoice design image (${response.status}).`);
            const blob = await response.blob();
            const dataUrl = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(reader.error ?? new Error('Could not read invoice design image.'));
                reader.readAsDataURL(blob);
            });
            cachedAssets[url] = dataUrl;
        } catch (error) {
            console.warn('Offline invoice design image could not be cached.', error);
        }
    }));
    await setMeta('offline-invoice-design-assets:v1', cachedAssets);
};

export const getOfflineInvoiceDesignAssets = async (design = {}) => {
    const cachedAssets = await getMeta('offline-invoice-design-assets:v1') || {};
    return Object.fromEntries(
        ['header_image', 'footer_image', 'full_page_pad_image']
            .map((key) => {
                const url = design[key];
                if (typeof url === 'string' && url.startsWith('data:')) return [key, url];
                const cachedUrl = typeof url === 'string' ? cachedAssets[url] : '';
                return [key, cachedUrl || (navigator.onLine ? (url || '') : '')];
            })
    );
};

export const saveOfflineBillingRequest = async ({
    id = createOfflineRequestId(),
    adminId,
    url,
    method = 'POST',
    data,
    routeName = '',
    label = 'Billing request',
}) => {
    if (!Number.isInteger(Number(adminId)) || Number(adminId) < 1) {
        throw new Error('Could not identify the signed-in user. The billing request was not saved offline.');
    }
    if (!url || !data || typeof data !== 'object') {
        throw new Error('The billing request is incomplete and was not saved offline.');
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const entry = {
        id,
        adminId: Number(adminId),
        url: new URL(url, window.location.href).toString(),
        method: String(method).toUpperCase(),
        data,
        routeName,
        label,
        csrfToken,
        status: 'queued',
        attempts: 0,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        lastError: '',
    };

    const encrypted = await encryptedRequest(entry);
    await withStore('readwrite', (store) => store.put(encrypted));
    notifyQueueChanged();

    return id;
};

export const getOfflineBillingRequests = async (adminId) => {
    const database = await openDatabase();
    const entries = await new Promise((resolve, reject) => {
        const transaction = database.transaction(REQUEST_STORE, 'readonly');
        const request = transaction.objectStore(REQUEST_STORE).getAll();
        request.onsuccess = () => resolve(request.result
            .filter((entry) => Number(entry.adminId) === Number(adminId))
            .sort((left, right) => left.createdAt.localeCompare(right.createdAt)));
        request.onerror = () => reject(request.error ?? new Error('Could not read offline billing requests.'));
    });
    if (!isOfflineBillingUnlocked(adminId)) {
        return entries.map((entry) => ({
            id: entry.id,
            adminId: entry.adminId,
            status: entry.status,
            attempts: entry.attempts,
            createdAt: entry.createdAt,
            updatedAt: entry.updatedAt,
            label: 'Locked offline request',
            lastError: '',
        }));
    }
    return Promise.all(entries.map((entry) => decryptedRequestWithAvailableKeys(entry)));
};

const updateRequest = async (id, updates) => {
    const database = await openDatabase();
    const requestEntry = await new Promise((resolve, reject) => {
        const transaction = database.transaction(REQUEST_STORE, 'readwrite');
        const store = transaction.objectStore(REQUEST_STORE);
        const request = store.get(id);
        request.onsuccess = () => {
            if (!request.result) {
                resolve(null);
                return;
            }
            resolve(request.result);
        };
        request.onerror = () => reject(request.error ?? new Error('Could not update offline billing request.'));
    });
    if (!requestEntry) return;

    const current = await decryptedRequestWithAvailableKeys(
        requestEntry,
        requireOfflineKey(requestEntry.adminId)
    );
    const updated = await encryptedRequest({ ...current, ...updates, updatedAt: new Date().toISOString() });
    await new Promise((resolve, reject) => {
        const transaction = database.transaction(REQUEST_STORE, 'readwrite');
        transaction.objectStore(REQUEST_STORE).put(updated);
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error ?? new Error('Could not update offline billing request.'));
    });
    notifyQueueChanged();
};

export const syncOfflineBillingRequests = async (adminId, csrfToken = '') => {
    if (activeSync || !navigator.onLine || !isOfflineBillingUnlocked(adminId)
        || !Number.isInteger(Number(adminId)) || Number(adminId) < 1) {
        return;
    }

    activeSync = true;
    let syncedBillingRequest = false;
    try {
        const requests = await getOfflineBillingRequests(adminId);
        const staleSyncThreshold = Date.now() - 2 * 60 * 1000;
        for (const entry of requests.filter((item) => (
            item.status === 'queued'
            || (item.status === 'syncing' && Date.parse(item.updatedAt) < staleSyncThreshold)
        ))) {
            await updateRequest(entry.id, { status: 'syncing', attempts: entry.attempts + 1, lastError: '' });
            try {
                const response = await fetch(entry.url, {
                    method: entry.method,
                    credentials: 'same-origin',
                    redirect: 'manual',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || entry.csrfToken,
                        'X-Offline-Request-Id': entry.id,
                        'X-Offline-Admin-Id': String(entry.adminId),
                        'X-Offline-Replay': '1',
                    },
                    body: JSON.stringify(entry.data),
                });

                if (response.status === 401 || response.status === 419) {
                    await updateRequest(entry.id, {
                        status: 'queued',
                        lastError: 'Sign in again to synchronize this saved bill.',
                    });
                    break;
                }

                const location = response.headers.get('Location') || '';
                if (response.type === 'opaqueredirect'
                    || (response.status >= 300 && response.status < 400 && /login/i.test(location))) {
                    await updateRequest(entry.id, {
                        status: 'queued',
                        lastError: 'Sign in again to synchronize this saved bill.',
                    });
                    break;
                }

                if (!response.ok && !(response.status >= 300 && response.status < 400)) {
                    let detail = `Server rejected this request (${response.status}).`;
                    try {
                        const body = await response.json();
                        detail = body.message || body.errors?.[Object.keys(body.errors || {})[0]]?.[0] || detail;
                    } catch {
                        // Keep the explicit HTTP status if the server did not return JSON.
                    }
                    await updateRequest(entry.id, { status: 'needs_review', lastError: String(detail) });
                    break;
                }

                await updateRequest(entry.id, {
                    status: 'synced',
                    data: null,
                    csrfToken: '',
                    lastError: '',
                    syncedAt: new Date().toISOString(),
                });
                if (entry.routeName === 'backend.billing.store') {
                    syncedBillingRequest = true;
                }
            } catch (error) {
                await updateRequest(entry.id, {
                    status: 'queued',
                    lastError: error instanceof Error ? error.message : 'Network is unavailable.',
                });
                break;
            }
        }
    } finally {
        activeSync = false;
        notifyQueueChanged();
        if (syncedBillingRequest && typeof window !== 'undefined') {
            window.dispatchEvent(new Event('billing:list:refresh'));
            window.dispatchEvent(new Event('sample-collection:list:refresh'));
        }
    }
};

export const deleteOfflineBillingRequest = async (id) => {
    await withStore('readwrite', (store) => store.delete(id));
    notifyQueueChanged();
};

const setOfflineBillingAdmin = async (registration, adminId) => new Promise((resolve, reject) => {
    const worker = registration.active || registration.waiting || registration.installing;
    if (!worker) {
        reject(new Error('Offline support is not ready yet.'));
        return;
    }

    const channel = new MessageChannel();
    const timeout = window.setTimeout(() => {
        channel.port1.close();
        reject(new Error('Offline support did not respond in time.'));
    }, 5000);
    channel.port1.onmessage = (event) => {
        window.clearTimeout(timeout);
        channel.port1.close();
        if (event.data?.success) resolve();
        else reject(new Error('Could not prepare this account for offline billing.'));
    };
    worker.postMessage({ type: 'SET_OFFLINE_BILLING_ADMIN', adminId }, [channel.port2]);
});

const cacheOfflineBillingPage = async (adminId, invoiceDesign = {}) => {
    if (!isOfflineBillingUnlocked(adminId)
        || window.location.pathname !== '/view-billing-page'
        || window.location.search) return;

    await saveEncryptedOfflineBillingShell(adminId, document.documentElement.outerHTML);
    await cacheOfflineInvoiceDesignAssets(invoiceDesign);

    const assetCache = await caches.open('hms-offline-assets-v1');
    const assetUrls = [...new Set(performance.getEntriesByType('resource')
        .map((entry) => entry.name)
        .filter((resourceUrl) => {
            const url = new URL(resourceUrl, window.location.href);
            return url.origin === window.location.origin && url.pathname.startsWith('/build/assets/');
        }))];

    await Promise.all(assetUrls.map(async (assetUrl) => {
        const cached = await assetCache.match(assetUrl);
        if (cached) return;
        const assetResponse = await fetch(assetUrl, { credentials: 'same-origin' });
        if (assetResponse.ok) await assetCache.put(assetUrl, assetResponse);
    }));

    const cryptoCache = await caches.open('hms-offline-unlock-v1');
    await Promise.all(['/offline-billing.html', '/offline-billing-unlock.js', '/offline-crypto.js'].map(async (url) => {
        if (await cryptoCache.match(url)) return;
        const response = await fetch(url, { credentials: 'same-origin' });
        if (response.ok) await cryptoCache.put(url, response);
    }));
};

export const prepareOfflineBillingPage = async (adminId, invoiceDesign = {}) => {
    if (!isOfflineBillingUnlocked(adminId)) return;
    await setMeta('active-admin-id', Number(adminId));
    await cacheOfflineBillingPage(adminId, invoiceDesign);
};

export const installOfflineBillingSupport = (adminId) => {
    if (installed || typeof window === 'undefined') return;
    installed = true;

    const currentCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const sync = () => syncOfflineBillingRequests(adminId, currentCsrfToken());

    window.addEventListener('online', sync);
    window.addEventListener('offline-billing:sync-requested', sync);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') sync();
    });
    navigator.serviceWorker?.addEventListener('message', (event) => {
        if (event.data?.type === 'OFFLINE_BILLING_QUEUE_UPDATED') notifyQueueChanged();
    });
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || navigator.onLine) return;

        const pathname = new URL(form.action, window.location.href).pathname;
        if (!/\/(?:due-collect|opd-due-collect)\/\d+\/?$/.test(pathname)) return;
        if (!form.reportValidity()) return;

        event.preventDefault();
        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        try {
            const data = Object.fromEntries(new FormData(form).entries());
            delete data._token;
            await saveOfflineBillingRequest({
                adminId,
                url: form.action,
                method: form.method || 'POST',
                data,
                routeName: pathname.includes('/opd-due-collect/') ? 'backend.opd.due.collect.store' : 'backend.due.collect.store',
                label: 'Due collection',
            });
            window.alert('Due collection saved on this device. It will sync automatically when internet returns.');
            if (submitButton) submitButton.textContent = 'Saved offline';
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Could not save this due collection offline.');
            if (submitButton) submitButton.disabled = false;
        }
    }, true);

    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .then(async (registration) => {
                await navigator.serviceWorker.ready;
                await setOfflineBillingAdmin(registration, adminId);
                await setMeta('active-admin-id', Number(adminId));
                await cacheOfflineBillingPage(adminId, window.__hmsOfflineInvoiceDesign || {});
                window.dispatchEvent(new CustomEvent('offline-billing:ready'));
                await sync();
            })
            .catch((error) => {
                console.error('Offline billing service worker registration failed.', error);
                window.dispatchEvent(new CustomEvent('offline-billing:setup-failed', {
                    detail: { message: error instanceof Error ? error.message : 'Offline support could not be prepared.' },
                }));
            });
    }

    const offlinePassphrase = window.__offlineBillingPassphrase;
    if (typeof offlinePassphrase === 'string' && offlinePassphrase.length >= 12) {
        delete window.__offlineBillingPassphrase;
        unlockOfflineBilling(adminId, offlinePassphrase)
            .then(async () => {
                await prepareOfflineBillingPage(adminId);
                window.dispatchEvent(new CustomEvent('offline-billing:unlocked'));
            })
            .catch((error) => {
                console.error('Could not unlock recovered offline billing.', error);
                window.dispatchEvent(new CustomEvent('offline-billing:unlock-failed', {
                    detail: { message: error instanceof Error ? error.message : 'Could not unlock offline billing.' },
                }));
            });
    }

    sync();
};
