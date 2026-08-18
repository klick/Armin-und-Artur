import assert from 'node:assert/strict';
import { ExactEvmScheme } from '@x402/evm/exact/client';
import { privateKeyToAccount } from 'viem/accounts';
import { recoverTypedDataAddress } from 'viem';
import {
    BASE_SEPOLIA,
    BASE_MAINNET,
    createEip3009TypedData,
    createPaymentSignature,
    decodeChallenge,
    encodePayment,
    getBaseNetwork,
    isExpectedChain,
    selectAndValidateRequirement,
} from '../../_js/story-api-x402-browser-core.mjs';

const payTo = '0x1111111111111111111111111111111111111111';
// Deliberately public throwaway key used solely for offline typed-data tests.
// It is never used by a browser, a funded account, or any network request.
const testAccount = privateKeyToAccount(`0x${'01'.repeat(32)}`);
const account = testAccount.address;
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
assert.equal(isExpectedChain(BASE_MAINNET.chainIdHex, BASE_MAINNET.network), true, 'Base mainnet must be accepted only when explicitly expected.');
assert.equal(isExpectedChain(BASE_SEPOLIA.chainIdHex, BASE_MAINNET.network), false, 'Testnet must never satisfy a mainnet challenge.');
assert.equal(getBaseNetwork(BASE_MAINNET.network).asset, BASE_MAINNET.asset);

const mainnetRequirement = {
    ...validRequirement,
    network: BASE_MAINNET.network,
    asset: BASE_MAINNET.asset,
};
const mainnetRequired = {
    ...validPaymentRequired,
    accepts: [mainnetRequirement],
};
const selectedMainnet = selectAndValidateRequirement(mainnetRequired, mainnetRequirement);
const mainnetTypedData = createEip3009TypedData(selectedMainnet, account, 1_700_000_000, `0x${'cd'.repeat(32)}`);
assert.equal(mainnetTypedData.domain.chainId, 8453, 'Mainnet authorization must use Base chain ID 8453.');
expectThrow(
    () => selectAndValidateRequirement(mainnetRequired, { ...mainnetRequirement, asset: BASE_SEPOLIA.asset }),
    /Sicherheitsstopp/,
);

const selected = selectAndValidateRequirement(validPaymentRequired, validRequirement);
const typedData = createEip3009TypedData(selected, account, 1_700_000_000, `0x${'ab'.repeat(32)}`);
assert.equal(typedData.domain.chainId, 84532);
assert.equal(typedData.message.to, payTo);
assert.equal(typedData.message.value, '10000');
assert.equal(typedData.message.validBefore, '1700000060');
assert.deepEqual(typedData.types.EIP712Domain, [
    { name: 'name', type: 'string' },
    { name: 'version', type: 'string' },
    { name: 'chainId', type: 'uint256' },
    { name: 'verifyingContract', type: 'address' },
], 'MetaMask must receive the same EIP-712 domain type that viem adds for ExactEvmScheme.');

const calls = [];
const provider = {
    async request(request) {
        calls.push(request);
        assert.equal(request.method, 'eth_signTypedData_v4');
        const requestedTypedData = JSON.parse(request.params[1]);
        return testAccount.signTypedData(requestedTypedData);
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
const requestedTypedData = JSON.parse(calls[0].params[1]);
const recovered = await recoverTypedDataAddress({
    domain: requestedTypedData.domain,
    types: requestedTypedData.types,
    primaryType: requestedTypedData.primaryType,
    message: requestedTypedData.message,
    signature: payload.payload.signature,
});
assert.equal(recovered.toLowerCase(), account.toLowerCase(), 'The emitted MetaMask signature must recover to authorization.from.');
assert.equal(payload.payload.authorization.from.toLowerCase(), recovered.toLowerCase());

// Compare the offline fixture against the installed official x402 scheme. Its
// timestamp and nonce are intentionally generated internally, but the domain,
// fields and recovered payer must be identical to the adapter's contract.
const officialPartialPayload = await new ExactEvmScheme(testAccount).createPaymentPayload(2, selected);
const officialAuthorization = officialPartialPayload.payload.authorization;
const officialTypedData = createEip3009TypedData(
    selected,
    officialAuthorization.from,
    Number(officialAuthorization.validBefore) - selected.maxTimeoutSeconds,
    officialAuthorization.nonce,
);
const officialRecovered = await recoverTypedDataAddress({
    domain: officialTypedData.domain,
    types: officialTypedData.types,
    primaryType: officialTypedData.primaryType,
    message: officialTypedData.message,
    signature: officialPartialPayload.payload.signature,
});
assert.equal(officialRecovered.toLowerCase(), account.toLowerCase(), 'Official ExactEvmScheme fixture must recover to the same payer.');
assert.deepEqual(
    Object.keys(officialAuthorization).sort(),
    Object.keys(payload.payload.authorization).sort(),
    'The EIP-1193 adapter must emit the same EIP-3009 authorization envelope as ExactEvmScheme.',
);

console.log('x402 browser client guard and payload checks passed');
