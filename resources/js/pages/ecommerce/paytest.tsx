// src/Pages/Paytest.tsx (or wherever your page is located)

import PaymentButton, { MidtransResult } from '@/components/PaymentButton'; // Import the new component
import AuthenticatedLayout from '@/layouts/header-layout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { toast } from 'sonner';

// --- Type Definitions (can be removed if they are in a shared file) ---
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

// --- The Refactored Paytest Component ---

const Paytest: React.FC<PaytestProps> = ({ snapToken, order, clientKey, isProduction, auth }) => {
    const [status, setStatus] = useState<'idle' | 'success' | 'pending' | 'error' | 'closed'>('idle');
    const [paymentResult, setPaymentResult] = useState<MidtransResult | null>(null);

    // --- Callback Handlers ---

    const handleSuccess = (result: MidtransResult) => {
        console.log('Payment Success:', result);
        setPaymentResult(result);
        setStatus('success');
        toast.success('Payment successful! Redirecting...');
        setTimeout(() => router.get(route('order.complete', order.id)), 3000);
    };

    const handlePending = (result: MidtransResult) => {
        console.log('Payment Pending:', result);
        setPaymentResult(result);
        setStatus('pending');
        toast.info('Your payment is pending. We will update you soon. Redirecting...');
        setTimeout(() => router.get(route('history.index')), 3000);
    };

    const handleError = (result: MidtransResult) => {
        console.error('Payment Error:', result);
        setPaymentResult(result);
        setStatus('error');
        toast.error('Payment failed. Please try again.');
    };

    const handleClose = () => {
        console.log('Customer closed the popup without finishing the payment');
        // Only update status if it's still in the initial 'idle' state
        if (status === 'idle') {
            setStatus('closed');
            toast.warning('Payment popup was closed.');
        }
    };

    // --- UI Rendering Logic ---

    const renderStatusMessage = () => {
        switch (status) {
            case 'success':
                return (
                    <div className="mt-4 rounded border border-green-400 bg-green-100 p-4 text-green-700">
                        <h3 className="font-bold">Payment Successful!</h3>
                        <p>Transaction ID: {paymentResult?.transaction_id}</p>
                        <p>You will be redirected to your order details shortly.</p>
                    </div>
                );
            case 'pending':
                return (
                    <div className="mt-4 rounded border border-yellow-400 bg-yellow-100 p-4 text-yellow-700">
                        <h3 className="font-bold">Waiting for Payment</h3>
                        <p>Your transaction is pending. Please complete the payment.</p>
                        <p>Order ID: {paymentResult?.order_id}</p>
                        <p>You will be redirected soon.</p>
                    </div>
                );
            case 'error':
                return (
                    <div className="mt-4 rounded border border-red-400 bg-red-100 p-4 text-red-700">
                        <h3 className="font-bold">Payment Failed</h3>
                        <p>{paymentResult?.status_message || 'An unknown error occurred.'}</p>
                    </div>
                );
            case 'closed':
                return (
                    <div className="mt-4 rounded border border-gray-400 bg-gray-100 p-4 text-gray-700">
                        <p>You closed the payment window. Click "Pay Now" to try again.</p>
                    </div>
                );
            case 'idle':
            default:
                return (
                    <div className="mt-4">
                        <p>Click the button below to complete your payment.</p>
                    </div>
                );
        }
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Complete Your Payment" />
            <div className="container mx-auto py-8">
                <div className="mx-auto max-w-lg rounded-lg bg-white p-6 shadow-md">
                    <h1 className="text-2xl font-bold">Payment for Order #{order.transaction_number}</h1>
                    <p className="text-lg text-gray-600">Total Amount: ${order.total_price.toFixed(2)}</p>

                    <div className="mt-6">
                        {status !== 'success' && status !== 'pending' && (
                            <PaymentButton
                                snapToken={snapToken}
                                clientKey={clientKey}
                                isProduction={isProduction}
                                onSuccess={handleSuccess}
                                onPending={handlePending}
                                onError={handleError}
                                onClose={handleClose}
                                className="w-full rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700 disabled:bg-gray-400"
                            >
                                Pay Now
                            </PaymentButton>
                        )}
                    </div>
                    {renderStatusMessage()}
                </div>
            </div>
        </AuthenticatedLayout>
    );
};

export default Paytest;
