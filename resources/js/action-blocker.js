const EVENT_NAME = 'veshop:action-blocked';

export const emitActionBlocked = (payload = {}) => {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: payload }));
};

export const onActionBlocked = (callback) => {
    if (typeof window === 'undefined' || typeof callback !== 'function') {
        return () => {};
    }

    const handler = (event) => {
        callback(event?.detail ?? {});
    };

    window.addEventListener(EVENT_NAME, handler);

    return () => {
        window.removeEventListener(EVENT_NAME, handler);
    };
};
