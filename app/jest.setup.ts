import '@testing-library/jest-dom';

// Mock scrollIntoView as it's not implemented in JSDOM
window.HTMLElement.prototype.scrollIntoView = jest.fn();

// Mock ResizeObserver
window.ResizeObserver = jest.fn().mockImplementation(() => ({
    observe: jest.fn(),
    unobserve: jest.fn(),
    disconnect: jest.fn(),
}));

// Mock PointerEvent
if (!window.PointerEvent) {
    class PointerEvent extends MouseEvent {
        constructor(type: string, params: PointerEventInit = {}) {
            super(type, params);
        }
    }
    // @ts-ignore
    window.PointerEvent = PointerEvent;
}
