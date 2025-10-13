// src/components/PaymentButton.tsx

import React, { useEffect, useState } from 'react';

// --- Type Definitions ---
// It's good practice to have these in a shared types file,
// but we'll define them here for self-containment.

declare global {
    interface Window {
        snap?: {
            pay: (
                snapToken: string,
                options?: {
                    onSuccess?: (result: MidtransResult) => void;
                    onPending?: (result: MidtransResult) => void;
                    onError?: (result: MidtransResult) => void;
                    onClose?: () => void;
                },
            ) => void;
        };
    }
}

export interface MidtransResult {
    status_code: string;
    status_message: string;
    transaction_id: string;
    order_id: string;
    gross_amount: string;
    payment_type: string;
    transaction_time: string;
    transaction_status: string;
    fraud_status: string;
    va_numbers?: { bank: string; va_number: string }[];
    permata_va_number?: string;
}

// --- Component Props Interface ---

interface PaymentButtonProps {
    /** The Snap Token generated from your backend. */
    snapToken: string;
    /** Your Midtrans Client Key. */
    clientKey: string;
    /** Set to true if using Midtrans production environment. */
    isProduction: boolean;
    /** Callback function triggered on successful payment. */
    onSuccess: (result: MidtransResult) => void;
    /** Callback function triggered on pending payment. */
    onPending: (result: MidtransResult) => void;
    /** Callback function triggered on payment error. */
    onError: (result: MidtransResult) => void;
    /** Callback function triggered when the user closes the payment popup. */
    onClose: () => void;
    /** The content to display inside the button. */
    children: React.ReactNode;
    /** Optional additional CSS classes for the button. */
    className?: string;
    /** Optional prop to disable the button from the parent. */
    disabled?: boolean;
}

// --- The PaymentButton Component ---

const PaymentButton: React.FC<PaymentButtonProps> = ({
    snapToken,
    clientKey,
    isProduction,
    onSuccess,
    onPending,
    onError,
    onClose,
    children,
    className = '',
    disabled = false,
}) => {
    const [isSnapReady, setIsSnapReady] = useState(false);
    const [isPaying, setIsPaying] = useState(false);

    useEffect(() => {
        const midtransScriptUrl = isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';

        let script = document.querySelector(`script[src="${midtransScriptUrl}"]`);

        const handleScriptLoad = () => {
            console.log('Midtrans Snap.js is ready.');
            setIsSnapReady(true);
        };

        if (!script) {
            script = document.createElement('script');
            script.src = midtransScriptUrl;
            script.setAttribute('data-client-key', clientKey);
            script.async = true;
            document.head.appendChild(script);
            script.addEventListener('load', handleScriptLoad);
        } else if (window.snap) {
            // If script already exists and is loaded
            handleScriptLoad();
        } else {
            // If script exists but might still be loading
            script.addEventListener('load', handleScriptLoad);
        }

        return () => {
            script?.removeEventListener('load', handleScriptLoad);
        };
    }, [clientKey, isProduction]);

    const handlePay = () => {
        if (!window.snap || !isSnapReady || !snapToken) {
            console.error('Snap.js is not ready, or snapToken is missing.');
            return;
        }

        setIsPaying(true);

        window.snap.pay(snapToken, {
            onSuccess: (result) => {
                setIsPaying(false);
                onSuccess(result);
            },
            onPending: (result) => {
                setIsPaying(false);
                onPending(result);
            },
            onError: (result) => {
                setIsPaying(false);
                onError(result);
            },
            onClose: () => {
                // Only trigger the onClose callback if the payment wasn't
                // already completed (which would have set isPaying to false).
                if (isPaying) {
                    setIsPaying(false);
                    onClose();
                }
            },
        });
    };

    const isButtonDisabled = disabled || !isSnapReady || isPaying;

    return (
        <button onClick={handlePay} disabled={isButtonDisabled} className={className}>
            {isPaying ? 'Processing...' : !isSnapReady ? 'Loading...' : children}
        </button>
    );
};

export default PaymentButton;
