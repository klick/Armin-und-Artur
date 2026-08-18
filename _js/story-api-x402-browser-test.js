import {
    BASE_SEPOLIA,
    createPaymentSignature,
    decodeChallenge,
    decodePaymentResponse,
    encodePayment,
    isExpectedChain,
    selectAndValidateRequirement,
} from './story-api-x402-browser-core.mjs';

const root = document.querySelector('#x402-browser-test');

if (root) {
    const endpoint = root.dataset.endpoint;
    const expected = JSON.parse(root.dataset.expectedPayment || '{}');
    const controls = {
        load: root.querySelector('[data-action="load-challenge"]'),
        connect: root.querySelector('[data-action="connect-wallet"]'),
        switchNetwork: root.querySelector('[data-action="switch-network"]'),
        sign: root.querySelector('[data-action="sign-and-fetch"]'),
        accounts: root.querySelector('[data-account-select]'),
        walletStatus: root.querySelector('[data-wallet-status]'),
        result: root.querySelector('[data-result-status]'),
        preview: root.querySelector('[data-payment-preview]'),
        paymentResponse: root.querySelector('[data-payment-response]'),
        paymentResponseContent: root.querySelector('[data-payment-response-content]'),
        unlockedJson: root.querySelector('[data-unlocked-json]'),
        unlockedJsonContent: root.querySelector('[data-unlocked-json-content]'),
    };
    const state = { paymentRequired: null, requirement: null, chainId: null };

    controls.load.addEventListener('click', () => loadChallenge(state, controls, endpoint, expected));
    controls.connect.addEventListener('click', () => connectWallet(state, controls));
    controls.switchNetwork.addEventListener('click', () => switchNetwork(state, controls));
    controls.sign.addEventListener('click', () => signAndFetch(state, controls, endpoint));
    controls.accounts.addEventListener('change', () => updatePayControl(state, controls));
}

async function loadChallenge(state, controls, endpoint, expected) {
    setResult(controls, 'Lade die lokale x402-Anforderung – keine Wallet-Aktion.');
    controls.load.disabled = true;
    try {
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
        if (response.status !== 402) {
            throw new Error(`Erwartet wurde HTTP 402, erhalten wurde ${response.status}.`);
        }
        const paymentRequired = decodeChallenge(response.headers.get('PAYMENT-REQUIRED'));
        const requirement = selectAndValidateRequirement(paymentRequired, expected);
        state.paymentRequired = paymentRequired;
        state.requirement = requirement;
        showPreview(controls, requirement);
        setResult(controls, 'Anforderung geprüft. Verbinde jetzt ausdrücklich das Testkäuferkonto.');
    } catch (error) {
        state.paymentRequired = null;
        state.requirement = null;
        setResult(controls, error.message, true);
    } finally {
        controls.load.disabled = false;
        updatePayControl(state, controls);
    }
}

async function connectWallet(state, controls) {
    const provider = walletProvider();
    if (!provider) {
        setResult(controls, 'Keine injected Wallet gefunden. Bitte MetaMask im aktuellen Browser aktivieren.', true);
        return;
    }

    try {
        const accounts = await provider.request({ method: 'eth_requestAccounts' });
        if (!Array.isArray(accounts) || !accounts.length) {
            throw new Error('MetaMask hat kein Käuferkonto freigegeben.');
        }
        populateAccounts(controls, accounts);
        state.chainId = await provider.request({ method: 'eth_chainId' });
        renderChainStatus(state, controls);
        setResult(controls, 'Wallet verbunden. Wähle das Käuferkonto und prüfe das Netzwerk.');
    } catch (error) {
        setResult(controls, `Wallet-Verbindung abgebrochen oder fehlgeschlagen: ${error.message}`, true);
    } finally {
        updatePayControl(state, controls);
    }
}

async function switchNetwork(state, controls) {
    const provider = walletProvider();
    if (!provider) return;

    try {
        await provider.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: BASE_SEPOLIA.chainIdHex }] });
    } catch (error) {
        if (error.code !== 4902) {
            setResult(controls, `Netzwerkwechsel fehlgeschlagen: ${error.message}`, true);
            return;
        }
        await provider.request({
            method: 'wallet_addEthereumChain',
            params: [{
                chainId: BASE_SEPOLIA.chainIdHex,
                chainName: 'Base Sepolia',
                nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 },
                rpcUrls: ['https://sepolia.base.org'],
                blockExplorerUrls: ['https://sepolia.basescan.org'],
            }],
        });
    }

    state.chainId = await provider.request({ method: 'eth_chainId' });
    renderChainStatus(state, controls);
    updatePayControl(state, controls);
}

async function signAndFetch(state, controls, endpoint) {
    const provider = walletProvider();
    const account = controls.accounts.value;
    if (!provider || !state.paymentRequired || !state.requirement || !account) return;

    state.chainId = await provider.request({ method: 'eth_chainId' });
    updatePayControl(state, controls);
    if (!isExpectedChain(state.chainId)) {
        setResult(controls, 'Sicherheitsstopp: MetaMask ist nicht auf Base Sepolia. Es wurde nichts signiert.', true);
        return;
    }

    controls.sign.disabled = true;
    setResult(controls, 'MetaMask darf nun genau eine, auf 60 Sekunden begrenzte Test-USDC-Autorisierung anfragen.');
    try {
        const payload = await createPaymentSignature(provider, account, state.paymentRequired, state.requirement);
        const response = await fetch(endpoint, {
            headers: {
                Accept: 'application/json',
                'PAYMENT-SIGNATURE': encodePayment(payload),
            },
        });
        const body = await response.json();
        if (!response.ok) {
            throw new Error(`Abruf nach Signatur fehlgeschlagen (HTTP ${response.status}): ${body.error || body.message || 'unbekannter Fehler'}`);
        }

        const paymentResponse = decodePaymentResponse(response.headers.get('PAYMENT-RESPONSE'));
        controls.paymentResponseContent.textContent = JSON.stringify(paymentResponse, null, 2);
        controls.paymentResponse.hidden = false;
        controls.unlockedJsonContent.textContent = JSON.stringify(body, null, 2);
        controls.unlockedJson.hidden = false;
        setResult(controls, 'Zahlung bestätigt und Vorlese-JSON freigeschaltet.');
    } catch (error) {
        setResult(controls, error.message, true);
    } finally {
        updatePayControl(state, controls);
    }
}

function walletProvider() {
    return window.ethereum && typeof window.ethereum.request === 'function' ? window.ethereum : null;
}

function populateAccounts(controls, accounts) {
    controls.accounts.replaceChildren(new Option('Testkäuferkonto wählen', ''));
    for (const account of accounts) {
        controls.accounts.add(new Option(account, account));
    }
    controls.accounts.disabled = false;
}

function renderChainStatus(state, controls) {
    const correct = isExpectedChain(state.chainId);
    controls.switchNetwork.disabled = correct;
    controls.walletStatus.textContent = correct
        ? 'Base Sepolia ist aktiv.'
        : `Aktives Netzwerk: ${state.chainId || 'unbekannt'}. Vor dem Signieren zu Base Sepolia wechseln.`;
}

function showPreview(controls, requirement) {
    controls.preview.querySelector('[data-field="network"]').textContent = requirement.network;
    controls.preview.querySelector('[data-field="asset"]').textContent = requirement.asset;
    controls.preview.querySelector('[data-field="payTo"]').textContent = requirement.payTo;
    controls.preview.querySelector('[data-field="amount"]').textContent = `${requirement.amount} atomic = 0,01 Test-USDC`;
    controls.preview.hidden = false;
}

function updatePayControl(state, controls) {
    controls.sign.disabled = !(
        state.paymentRequired &&
        state.requirement &&
        controls.accounts.value &&
        isExpectedChain(state.chainId)
    );
}

function setResult(controls, message, isError = false) {
    controls.result.textContent = message;
    controls.result.classList.toggle('text-danger', isError);
    controls.result.classList.toggle('text-success', !isError && message.includes('freigeschaltet'));
}
