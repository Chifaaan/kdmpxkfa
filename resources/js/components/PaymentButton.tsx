import React, { useEffect, useState } from 'react';

declare global {
    interface Window {
        snap: any;
    }
}

interface PaymentButtonProps {
    orderId: number | string;
    disabled?: boolean;
    onSuccess?: (result: any) => void;
    onError?: (error: any) => void;
}

const PaymentButton: React.FC<PaymentButtonProps> = ({ orderId, disabled, onSuccess, onError }) => {
    const [loading, setLoading] = useState(false);

    // Load Midtrans Snap.js
    useEffect(() => {
        const script = document.createElement('script');
        script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.setAttribute('data-client-key', import.meta.env.MIDTRANS_CLIENT_KEY!);
        script.async = true;
        document.body.appendChild(script);
        return () => {
            document.body.removeChild(script);
        };
    }, []);

    const handlePayment = async () => {
        setLoading(true);
        try {
            const res = await fetch(`/ecommerce/paytest/${orderId}`);
            const data = await res.json();

            if (!data.token) throw new Error('Failed to get Snap token');

            window.snap.pay(data.token, {
                onSuccess: (result: any) => {
                    console.log('Payment success:', result);
                    onSuccess?.(result);
                },
                onPending: (result: any) => {
                    console.log('Payment pending:', result);
                    alert('Payment is pending.');
                },
                onError: (result: any) => {
                    console.error('Payment error:', result);
                    onError?.(result);
                    alert('Payment failed!');
                },
                onClose: () => {
                    console.log('Payment popup closed.');
                },
            });
        } catch (err) {
            console.error('Error:', err);
            onError?.(err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <button
            type="button"
            onClick={handlePayment}
            disabled={disabled || loading}
            className="w-full rounded-md bg-primary px-4 py-3 text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
        >
            {loading ? 'Processing...' : 'Pay with Midtrans'}
        </button>
    );
};

export default PaymentButton;
