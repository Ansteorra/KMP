/** One Stimulus connection owns its listeners, finite reads and delayed work. */
export function createControllerScope() {
    const controller = new AbortController();
    const timers = new Set();
    return {
        signal: controller.signal,
        listen(target, type, callback, options = {}) {
            target.addEventListener(type, callback, {
                ...(typeof options === 'boolean' ? { capture: options } : options), signal: controller.signal,
            });
        },
        fetch(input, options = {}) {
            return window.fetch(input, { cache: 'no-store', ...options,
                signal: AbortSignal.any([controller.signal, options.signal].filter(Boolean)) });
        },
        setTimeout(callback, delay, ...args) {
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                if (!controller.signal.aborted) callback(...args);
            }, delay);
            timers.add(timer); return timer;
        },
        dispose() {
            controller.abort();
            timers.forEach(timer => window.clearTimeout(timer)); timers.clear();
        },
    };
}
