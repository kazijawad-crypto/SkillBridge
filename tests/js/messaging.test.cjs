const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

for (const startsEditing of [false, true]) {
    test(startsEditing ? 'a delayed refresh preserves an edit started during the request' : 'a delayed refresh updates an idle conversation', async () => {
        let tick;
        let finishFetch;
        let editing = false;
        let content = 'original content';
        const container = {
            dataset: { pollUrl: '/messages/1/poll' },
            contains: () => editing,
            querySelector: () => editing ? {} : null,
            get innerHTML() { return content; },
            set innerHTML(value) { content = value; },
        };
        const sandbox = {
            document: {
                hidden: false,
                activeElement: null,
                querySelectorAll: () => [container],
                querySelector: () => null,
            },
            setInterval(callback) { tick = callback; },
            fetch: () => new Promise(resolve => { finishFetch = resolve; }),
        };
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../resources/js/messaging.js'), 'utf8'), sandbox);
        const pending = tick();
        editing = startsEditing;
        finishFetch({ ok: true, redirected: false, text: async () => 'updated content' });
        await pending;
        assert.equal(content, startsEditing ? 'original content' : 'updated content');
    });
}
