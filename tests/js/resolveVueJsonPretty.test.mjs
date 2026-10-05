import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import resolveVueJsonPretty from '../../resources/js/resolveVueJsonPretty.js';

describe('resolveVueJsonPretty', () => {
    it('returns a component that already has render', () => {
        const component = { render() {}, name: 'AlreadyResolved' };

        assert.equal(resolveVueJsonPretty(component), component);
    });

    it('unwraps an ESM default export module', () => {
        const component = { render() {}, name: 'FromDefault' };
        const imported = { default: component };

        assert.equal(resolveVueJsonPretty(imported), component);
    });

    it('invokes a CJS thunk and unwraps its default export', () => {
        const component = { render() {}, name: 'FromThunk' };
        const thunk = () => ({ default: component });

        assert.equal(resolveVueJsonPretty(thunk), component);
    });

    it('leaves Vue 2 options components untouched', () => {
        const component = {
            options: { render() {} },
            name: 'OptionsApi',
        };

        assert.equal(resolveVueJsonPretty(component), component);
    });
});
