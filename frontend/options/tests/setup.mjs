import { vi } from 'vitest';

const noop = () => {};

class ObserverStub {
    observe = noop;

    unobserve = noop;

    disconnect = noop;

    takeRecords = () => [];
}

globalThis.ResizeObserver = ObserverStub;
globalThis.IntersectionObserver = ObserverStub;
globalThis.matchMedia = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: noop,
    removeListener: noop,
    addEventListener: noop,
    removeEventListener: noop,
    dispatchEvent: () => false,
});
globalThis.scrollTo = noop;

Object.defineProperty(window, 'requestAnimationFrame', {
    configurable: true,
    value: (callback) => setTimeout(() => callback(Date.now()), 0),
});
Object.defineProperty(window, 'cancelAnimationFrame', {
    configurable: true,
    value: (id) => clearTimeout(id),
});

Element.prototype.scrollIntoView = noop;
HTMLCanvasElement.prototype.getContext = vi.fn(() => null);
