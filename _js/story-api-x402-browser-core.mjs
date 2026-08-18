import {
    decodePaymentRequiredHeader,
    decodePaymentResponseHeader,
    encodePaymentSignatureHeader,
} from '@x402/core/http';

export const BASE_SEPOLIA = {
    chainId: 84532,
    chainIdHex: '0x14a34',
    network: 'eip155:84532',
    asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
    priceAtomic: '10000',
    chainName: 'Base Sepolia',
    rpcUrl: 'https://sepolia.base.org',
    explorerUrl: 'https://sepolia.basescan.org',
};

export const BASE_MAINNET = {
    chainId: 8453,
    chainIdHex: '0x2105',
    network: 'eip155:8453',
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
    priceAtomic: '10000',
    chainName: 'Base',
    rpcUrl: 'https://mainnet.base.org',
    explorerUrl: 'https://basescan.org',
};

const BASE_NETWORKS = new Map([
    [BASE_SEPOLIA.network, BASE_SEPOLIA],
    [BASE_MAINNET.network, BASE_MAINNET],
]);

const AUTHORIZATION_TYPES = {
    // eth_signTypedData_v4 requires this explicit domain type. viem adds the
    // same four fields internally before the official ExactEvmScheme asks a
    // wallet to sign. Without it, MetaMask serializes an empty domain and the
    // facilitator correctly rejects the signature.
    EIP712Domain: [
        { name: 'name', type: 'string' },
        { name: 'version', type: 'string' },
        { name: 'chainId', type: 'uint256' },
        { name: 'verifyingContract', type: 'address' },
    ],
    TransferWithAuthorization: [
        { name: 'from', type: 'address' },
        { name: 'to', type: 'address' },
        { name: 'value', type: 'uint256' },
        { name: 'validAfter', type: 'uint256' },
        { name: 'validBefore', type: 'uint256' },
        { name: 'nonce', type: 'bytes32' },
    ],
};

export function decodeChallenge(header) {
    if (!header) {
        throw new Error('Die 402-Antwort enthält keinen PAYMENT-REQUIRED-Header.');
    }

    return decodePaymentRequiredHeader(header);
}

export function selectAndValidateRequirement(paymentRequired, expected) {
    if (!paymentRequired || paymentRequired.x402Version !== 2 || !Array.isArray(paymentRequired.accepts)) {
        throw new Error('Ungültige x402-v2-Zahlungsanforderung.');
    }

    const requirement = paymentRequired.accepts.find((candidate) => candidate && candidate.scheme === 'exact');
    if (!requirement) {
        throw new Error('Die Zahlungsanforderung enthält keine unterstützte exact-Option.');
    }

    const expectedPayTo = expected && expected.payTo;
    const expectedNetwork = getBaseNetwork(expected && expected.network);
    if (!isAddress(expectedPayTo)) {
        throw new Error('Die lokale Testseite hat keinen sicheren Empfänger konfiguriert.');
    }

    if (
        requirement.network !== expectedNetwork.network ||
        requirement.asset?.toLowerCase() !== expectedNetwork.asset.toLowerCase() ||
        requirement.amount !== expectedNetwork.priceAtomic ||
        !addressesEqual(requirement.payTo, expectedPayTo) ||
        expected.asset?.toLowerCase() !== expectedNetwork.asset.toLowerCase() ||
        expected.amount !== expectedNetwork.priceAtomic ||
        requirement.extra?.name !== 'USDC' ||
        requirement.extra?.version !== '2' ||
        expected.extra?.name !== 'USDC' ||
        expected.extra?.version !== '2'
    ) {
        throw new Error('Sicherheitsstopp: Netzwerk, Asset, Preis oder Empfänger weichen von der lokalen Pilot-Konfiguration ab.');
    }

    if (!paymentRequired.resource || typeof paymentRequired.resource.url !== 'string') {
        throw new Error('Die Zahlungsanforderung enthält keine gültige Ressource.');
    }

    if (!Number.isInteger(requirement.maxTimeoutSeconds) || requirement.maxTimeoutSeconds <= 0 || requirement.maxTimeoutSeconds > 60) {
        throw new Error('Sicherheitsstopp: Die Gültigkeitsdauer muss zwischen 1 und 60 Sekunden liegen.');
    }

    if (!requirement.extra || typeof requirement.extra.name !== 'string' || typeof requirement.extra.version !== 'string') {
        throw new Error('Die EIP-712-Domain-Parameter für USDC fehlen.');
    }

    return requirement;
}

export function createEip3009TypedData(requirement, account, nowSeconds, nonce) {
    if (!isAddress(account) || !/^0x[0-9a-fA-F]{64}$/.test(nonce)) {
        throw new Error('Konto oder EIP-3009-Nonce ist ungültig.');
    }

    const network = getBaseNetwork(requirement.network);

    return {
        domain: {
            name: requirement.extra.name,
            version: requirement.extra.version,
            chainId: network.chainId,
            verifyingContract: requirement.asset,
        },
        types: AUTHORIZATION_TYPES,
        primaryType: 'TransferWithAuthorization',
        message: {
            from: account,
            to: requirement.payTo,
            value: requirement.amount,
            validAfter: '0',
            validBefore: String(nowSeconds + requirement.maxTimeoutSeconds),
            nonce,
        },
    };
}

export async function createPaymentSignature(provider, account, paymentRequired, requirement, nowSeconds = Math.floor(Date.now() / 1000), nonce = randomNonce()) {
    const typedData = createEip3009TypedData(requirement, account, nowSeconds, nonce);
    const signature = await provider.request({
        method: 'eth_signTypedData_v4',
        params: [account, JSON.stringify(typedData)],
    });

    if (typeof signature !== 'string' || !/^0x[0-9a-fA-F]+$/.test(signature)) {
        throw new Error('MetaMask hat keine gültige EIP-712-Signatur zurückgegeben.');
    }

    return {
        x402Version: 2,
        resource: paymentRequired.resource,
        accepted: requirement,
        payload: {
            authorization: typedData.message,
            signature,
        },
    };
}

export function encodePayment(payload) {
    return encodePaymentSignatureHeader(payload);
}

export function decodePaymentResponse(header) {
    return header ? decodePaymentResponseHeader(header) : null;
}

export function isExpectedChain(chainId, network = BASE_SEPOLIA.network) {
    return chainId === getBaseNetwork(network).chainIdHex;
}

export function getBaseNetwork(network) {
    const configuration = BASE_NETWORKS.get(network);
    if (!configuration) {
        throw new Error('Sicherheitsstopp: Dieses Netzwerk wird vom Browser-Test nicht unterstützt.');
    }

    return configuration;
}

export function randomNonce() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);

    return `0x${Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')}`;
}

function isAddress(value) {
    return typeof value === 'string' && /^0x[0-9a-fA-F]{40}$/.test(value);
}

function addressesEqual(first, second) {
    return isAddress(first) && isAddress(second) && first.toLowerCase() === second.toLowerCase();
}
