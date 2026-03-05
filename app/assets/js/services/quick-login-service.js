const STORAGE_KEYS = {
    rememberedId: "kmp.login.rememberedId",
    deviceId: "kmp.quickLogin.deviceId",
    quickConfig: "kmp.quickLogin.config"
};

const encoder = new TextEncoder();

const toHex = (buffer) => {
    return Array.from(new Uint8Array(buffer))
        .map((byte) => byte.toString(16).padStart(2, "0"))
        .join("");
};

const hashPinWithSalt = async (pin, salt) => {
    const payload = `${salt}:${pin}`;
    if (window.crypto?.subtle) {
        const digest = await window.crypto.subtle.digest("SHA-256", encoder.encode(payload));
        return toHex(digest);
    }

    // Non-crypto fallback for older browsers (privacy gate only).
    let hash = 0;
    for (let i = 0; i < payload.length; i += 1) {
        hash = ((hash << 5) - hash) + payload.charCodeAt(i);
        hash |= 0;
    }

    return `fallback-${Math.abs(hash)}`;
};

const randomSalt = () => {
    if (window.crypto?.getRandomValues) {
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes).map((byte) => byte.toString(16).padStart(2, "0")).join("");
    }

    return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`;
};

const parseQuickConfig = (raw) => {
    if (typeof raw !== "string" || raw.trim() === "") {
        return null;
    }

    try {
        const parsed = JSON.parse(raw);
        if (
            typeof parsed?.email !== "string" ||
            typeof parsed?.deviceId !== "string" ||
            typeof parsed?.pinSalt !== "string" ||
            typeof parsed?.pinHash !== "string"
        ) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
};

const QuickLoginService = {
    storageKeys: STORAGE_KEYS,

    getRememberedId() {
        return localStorage.getItem(STORAGE_KEYS.rememberedId) || "";
    },

    setRememberedId(emailAddress) {
        const normalized = String(emailAddress || "").trim();
        if (normalized === "") {
            localStorage.removeItem(STORAGE_KEYS.rememberedId);
            return;
        }

        localStorage.setItem(STORAGE_KEYS.rememberedId, normalized);
    },

    clearRememberedId() {
        localStorage.removeItem(STORAGE_KEYS.rememberedId);
    },

    getOrCreateDeviceId() {
        const existing = localStorage.getItem(STORAGE_KEYS.deviceId);
        if (existing && existing.length >= 16) {
            return existing;
        }

        const generated = window.crypto?.randomUUID
            ? window.crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;

        localStorage.setItem(STORAGE_KEYS.deviceId, generated);

        return generated;
    },

    getQuickConfig() {
        const raw = localStorage.getItem(STORAGE_KEYS.quickConfig);
        return parseQuickConfig(raw);
    },

    clearQuickConfig() {
        localStorage.removeItem(STORAGE_KEYS.quickConfig);
    },

    clearLoginState() {
        this.clearRememberedId();
        this.clearQuickConfig();
    },

    async saveQuickConfig({ email, deviceId, pin }) {
        const normalizedEmail = String(email || "").trim();
        const normalizedDeviceId = String(deviceId || "").trim();
        const normalizedPin = String(pin || "").trim();
        if (
            normalizedEmail === "" ||
            normalizedDeviceId === "" ||
            !/^\d{4,10}$/.test(normalizedPin)
        ) {
            return null;
        }

        const pinSalt = randomSalt();
        const pinHash = await hashPinWithSalt(normalizedPin, pinSalt);
        const config = {
            email: normalizedEmail,
            deviceId: normalizedDeviceId,
            pinSalt,
            pinHash,
            updatedAt: new Date().toISOString()
        };
        localStorage.setItem(STORAGE_KEYS.quickConfig, JSON.stringify(config));

        return config;
    },

    async verifyPin(pin, quickConfig = null) {
        const config = quickConfig || this.getQuickConfig();
        if (!config) {
            return false;
        }

        const candidate = await hashPinWithSalt(String(pin || "").trim(), config.pinSalt);
        return candidate === config.pinHash;
    }
};

window.KMPQuickLoginService = QuickLoginService;

export default QuickLoginService;
