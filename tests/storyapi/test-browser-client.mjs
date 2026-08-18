import assert from 'node:assert/strict';
import {
    BASE_SEPOLIA,
    createEip3009TypedData,
    createPaymentSignature,
    decodeChallenge,
    encodePayment,
    isExpectedChain,
    selectAndValidateRequirement,
} from '../../_js/story-api-x402-browser-core.mjs';

const payTo = '0x1111111111111111111111111111111111111111';
const account = '0x2222222222222222222222222222222222222222';
const validRequirement = {
    scheme: 'exact',
    network: BASE_SEPOLIA.network,
    asset: BASE_SEPOLIA.asset,
    amount: BASE_SEPOLIA.priceAtomic,
    payTo,
    maxTimeoutSeconds: 60,
    extra: { name: 'USDC', version: '2' },
};
const validPaymentRequired = {
    x402Version: 2,
    resource: { url: 'https://arminundartur.ddev.site/api/v1/stories/rotkaeppchen/reading.json', mimeType: 'application/json' },
    accepts: [validRequirement],
};

function expectThrow(callback, match) {
    assert.throws(callback, match);
}

expectThrow(() => decodeChallenge('not valid base64 !'), /Invalid|invalid|base64/);
expectThrow(() => selectAndValidateRequirement({ ...validPaymentRequired, x402Version: 1 }, validRequirement), /Ungültige/);
expectThrow(() => selectAndValidateRequirement({ ...validPaymentRequired, accepts: [{ ...validRequirement, network: 'eip155:1' }] }, validRequirement), /Sicherheitsstopp/);
expectThrow(() => selectAndValidateRequirement({ ...validPaymentRequired, accepts: [{ ...validRequirement, asset: '0x0000000000000000000000000000000000000000' }] }, validRequirement), /Sicherheitsstopp/);
expectThrow(() => selectAndValidateRequirement({ ...validPaymentRequired, accepts: [{ ...validRequirement, amount: '10001' }] }, validRequirement), /Sicherheitsstopp/);
expectThrow(() => selectAndValidateRequirement({ ...validPaymentRequired, accepts: [{ ...validRequirement, payTo: '0x3333333333333333333333333333333333333333' }] }, validRequirement), /Sicherheitsstopp/);
assert.equal(isExpectedChain(BASE_SEPOLIA.chainIdHex), true);
assert.equal(isExpectedChain('0x1'), false, 'Wrong network must never enable signing.');

const selected = selectAndValidateRequirement(validPaymentRequired, validRequirement);
const typedData = createEip3009TypedData(selected, account, 1_700_000_000, `0x${'ab'.repeat(32)}`);
assert.equal(typedData.domain.chainId, 84532);
assert.equal(typedData.message.to, payTo);
assert.equal(typedData.message.value, '10000');
assert.equal(typedData.message.validBefore, '1700000060');

const calls = [];
const provider = {
    async request(request) {
        calls.push(request);
        return `0x${'cd'.repeat(65)}`;
    },
};
assert.equal(calls.length, 0, 'Constructing the test state must not contact a wallet.');
const payload = await createPaymentSignature(provider, account, validPaymentRequired, selected, 1_700_000_000, `0x${'ab'.repeat(32)}`);
assert.equal(calls.length, 1, 'Only the explicit signature helper may request the wallet.');
assert.equal(calls[0].method, 'eth_signTypedData_v4');
assert.equal(payload.x402Version, 2);
assert.equal(payload.accepted.payTo, payTo);
assert.equal(payload.payload.authorization.value, '10000');
assert.match(encodePayment(payload), /^[A-Za-z0-9+/]+={0,2}$/);

console.log('x402 browser client guard and payload checks passed');
