import { vi } from 'vitest';
import * as VueModule from 'vue';
import ArcoVue from '@arco-design/web-vue';
import ArcoVueIcon from '@arco-design/web-vue/es/icon/index.js';

const noop = () => {};

class ResizeObserverStub {
    observe = noop;

    unobserve = noop;

    disconnect = noop;
}

class IntersectionObserverStub {
    observe = noop;

    unobserve = noop;

    disconnect = noop;

    takeRecords = () => [];
}

const matchMediaStub = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: noop,
    removeListener: noop,
    addEventListener: noop,
    removeEventListener: noop,
    dispatchEvent: () => false
});

globalThis.Vue = VueModule;
globalThis.ArcoVue = ArcoVue;
globalThis.ArcoVueIcon = ArcoVueIcon;
globalThis.ResizeObserver = ResizeObserverStub;
globalThis.IntersectionObserver = IntersectionObserverStub;
globalThis.matchMedia = matchMediaStub;
globalThis.scrollTo = noop;

window.Vue = VueModule;
window.ArcoVue = ArcoVue;
window.ArcoVueIcon = ArcoVueIcon;
window.ResizeObserver = ResizeObserverStub;
window.IntersectionObserver = IntersectionObserverStub;
window.matchMedia = matchMediaStub;
window.scrollTo = noop;
window.__TTDF_OPTIONS_TEST__ = true;
window.__TTDF_OPTIONS_TEST_CLEANUP__ = async () => {
    document.body.innerHTML = '';
};

Object.defineProperty(window, 'requestAnimationFrame', {
    configurable: true,
    value: (callback) => setTimeout(() => callback(Date.now()), 0)
});
Object.defineProperty(window, 'cancelAnimationFrame', {
    configurable: true,
    value: (id) => clearTimeout(id)
});

Element.prototype.scrollIntoView = noop;
Element.prototype.scrollTo = noop;
HTMLCanvasElement.prototype.getContext = vi.fn(() => null);
