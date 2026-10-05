/**
 * Vite 8 / Rolldown may deliver vue-json-pretty's UMD as { default: Component }
 * or as a CJS thunk. Registering the raw import makes Vue mount an empty tag.
 *
 * @param {unknown} imported
 * @returns {unknown}
 */
export default function resolveVueJsonPretty(imported) {
    let component = imported;

    if (typeof component === 'function' && !component.render && !component.options) {
        component = component();
    }

    if (component && !component.render && component.default) {
        component = component.default;
    }

    return component;
}
