import AuthenticatedLayout from '@/layouts/header-layout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';

// --- Type Definitions (Identical to previous version) ---

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

interface MidtransResult {
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

interface Order {
    id: number;
    transaction_number: string;
    total_price: number;
}

interface PaytestProps extends PageProps {
    snapToken: string;
    order: Order;
    orderId: string;
    grossAmount: number;
    clientKey: string;
    isProduction: boolean;
}

// --- React Component ---

const Paytest: React.FC<PaytestProps> = ({ snapToken, order, orderId, grossAmount, clientKey, isProduction, auth }) => {
    // New 'idle' status for the initial state before user interaction
    const [status, setStatus] = useState<'idle' | 'paying' | 'success' | 'pending' | 'error' | 'closed'>('idle');
    const [isSnapReady, setIsSnapReady] = useState(false);
    const [paymentResult, setPaymentResult] = useState<MidtransResult | null>(null);

    // This useEffect hook is now ONLY responsible for loading the external Midtrans Snap.js script.
    useEffect(() => {
        const midtransScriptUrl = isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';

        let script = document.querySelector(`script[src="${midtransScriptUrl}"]`);

        if (!script) {
            script = document.createElement('script');
            script.src = midtransScriptUrl;
            script.setAttribute('data-client-key', clientKey);
            script.async = true;
            document.head.appendChild(script);
        }

        const handleScriptLoad = () => {
            console.log('Midtrans Snap.js is ready.');
            setIsSnapReady(true); // Set state to true once the script is loaded
        };

        script.addEventListener('load', handleScriptLoad);

        // Cleanup function
        return () => {
            script?.removeEventListener('load', handleScriptLoad);
        };
    }, [clientKey, isProduction]);

    // This function is triggered when the user clicks the "Pay Now" button.
    const handlePayNow = () => {
        if (!window.snap || !isSnapReady) {
            console.error('Snap.js is not ready yet.');
            return;
        }

        setStatus('paying'); // Update status to show feedback to the user

        window.snap.pay(snapToken, {
            onSuccess: (result) => {
                console.log('Payment Success:', result);
                setPaymentResult(result);
                setStatus('success');
                setTimeout(() => router.get(route('ecommerce.order.show', order.id)), 3000);
            },
            onPending: (result) => {
                console.log('Payment Pending:', result);
                setPaymentResult(result);
                setStatus('pending');
                setTimeout(() => router.get(route('ecommerce.order.show', order.id)), 5000);
            },
            onError: (result) => {
                console.error('Payment Error:', result);
                setPaymentResult(result);
                setStatus('error');
            },
            onClose: () => {
                console.log('Customer closed the popup without finishing the payment');
                // Don't change the status if it was already successful or pending
                if (status !== 'success' && status !== 'pending') {
                    setStatus('closed');
                }
            },
        });
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(amount);
    };

    // Renders the main content area based on the current payment status
    const renderContent = () => {
        switch (status) {
            case 'success':
                return (
                    <div className="rounded-lg bg-green-50 p-6 text-center">
                        <h2 className="text-2xl font-bold text-green-700">Payment Successful!</h2>
                        <p className="mt-2 text-gray-600">Thank you for your purchase. Your order is being processed.</p>
                        <p className="mt-1 text-sm text-gray-500">Transaction ID: {paymentResult?.transaction_id}</p>
                        <p className="mt-4 text-gray-600">Redirecting to your order details...</p>
                    </div>
                );
            case 'pending':
                return (
                    <div className="rounded-lg bg-yellow-50 p-6 text-center">
                        <h2 className="text-2xl font-bold text-yellow-700">Payment Pending</h2>
                        <p className="mt-2 text-gray-600">Please complete your payment. Instructions are available on the order details page.</p>
                        <p className="mt-1 text-sm text-gray-500">Transaction ID: {paymentResult?.transaction_id}</p>
                        <p className="mt-4 text-gray-600">Redirecting to your order details...</p>
                    </div>
                );
            case 'error':
                return (
                    <div className="rounded-lg bg-red-50 p-6 text-center">
                        <h2 className="text-2xl font-bold text-red-700">Payment Failed</h2>
                        <p className="mt-2 text-gray-600">{paymentResult?.status_message || 'An unexpected error occurred.'}</p>
                        <button
                            onClick={handlePayNow} // Allow user to try again
                            className="mt-4 rounded-md bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                        >
                            Try Again
                        </button>
                    </div>
                );
            case 'paying':
                return (
                    <div className="text-center">
                        <p className="text-lg font-semibold text-gray-700">Waiting for payment...</p>
                        <p className="text-gray-500">Please complete the transaction in the payment window.</p>
                    </div>
                );
            // Default cases: idle, closed. Both will show the "Pay Now" button.
            default:
                return (
                    <div className="text-center">
                        {status === 'closed' && <p className="mb-4 text-yellow-700">You closed the payment window. You can try again.</p>}
                        <button
                            id="pay-button"
                            onClick={handlePayNow}
                            disabled={!isSnapReady}
                            className="w-full rounded-lg bg-indigo-600 px-6 py-3 text-lg font-bold text-white transition-colors duration-200 hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-400"
                        >
                            {isSnapReady ? 'Pay Now' : 'Initializing Payment...'}
                        </button>
                    </div>
                );
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="text-xl leading-tight font-semibold text-gray-800">Payment Confirmation</h2>}>
            <Head title="Confirm Payment" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 md:p-8">
                            <h1 className="mb-2 text-center text-3xl font-bold">Order Summary</h1>
                            <p className="mb-6 text-center text-gray-500">Please review your order and proceed to payment.</p>

                            <div className="my-6 border-t border-b border-gray-200 py-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-600">Order ID</span>
                                    <span className="font-mono">{orderId}</span>
                                </div>
                                <div className="mt-3 flex items-center justify-between">
                                    <span className="font-semibold text-gray-600">Total Amount</span>
                                    <span className="text-2xl font-bold text-indigo-600">{formatCurrency(grossAmount)}</span>
                                </div>
                            </div>

                            <div className="mt-6 flex min-h-[80px] items-center justify-center">{renderContent()}</div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
};

export default Paytest;
