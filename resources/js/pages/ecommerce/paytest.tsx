import { usePage } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';

interface Order {
    id: number;
    transaction_number: string;
    total_price: number;
    status: string;
    created_at: string;
}

interface PageProps {
    snapToken: string;
    order: Order;
    orderId: string;
    grossAmount: number;
    clientKey: string;
    isProduction: boolean;
}

const PayTest: React.FC = () => {
    const { snapToken, order, orderId, grossAmount, clientKey, isProduction } = usePage<PageProps>().props;
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (snapToken) {
            // Check if Midtrans Snap script is already loaded
            if (typeof window !== 'undefined' && (window as any).snap) {
                initializeMidtrans();
            } else {
                loadMidtransScript();
            }
        } else {
            setError('No Snap token available');
            setLoading(false);
        }
    }, [snapToken]);

    const initializeMidtrans = () => {
        // @ts-ignore
        if (typeof window !== 'undefined' && window.snap) {
            // @ts-ignore
            window.snap.show({
                token: snapToken,
                onSuccess: function (result: any) {
                    console.log('Payment Success!', result);
                    alert('Payment Success!');
                    // Redirect to order completion page
                    window.location.href = `/ecommerce/order-completed?order_id=${orderId}`;
                },
                onPending: function (result: any) {
                    console.log('Payment Pending!', result);
                    alert('Payment Pending, please complete the payment');
                    // Redirect to order completion page to track pending order
                    window.location.href = `/ecommerce/order-completed?order_id=${orderId}`;
                },
                onError: function (result: any) {
                    console.log('Payment Error!', result);
                    alert('Payment Failed!');
                },
                onClose: function () {
                    console.log('Customer closed the popup');
                    alert('Payment cancelled by customer');
                },
            });
            setLoading(false);
        } else {
            setError('Midtrans Snap is not loaded');
            setLoading(false);
        }
    };

    const loadMidtransScript = () => {
        const script = document.createElement('script');
        // Use props passed from backend with fallback to environment variable
        const midtransClientKey = clientKey || import.meta.env.VITE_REACT_APP_MIDTRANS_CLIENT_KEY || '';
        const isProd = isProduction || import.meta.env.VITE_MIDTRANS_IS_PRODUCTION === 'true';
        script.src = isProd ? `https://app.midtrans.com/snap/snap.js` : `https://app.sandbox.midtrans.com/snap/snap.js`;
        script.setAttribute('data-client-key', midtransClientKey);

        script.onload = () => {
            if (typeof window !== 'undefined' && (window as any).snap) {
                // @ts-ignore
                (window as any).snap.show({
                    token: snapToken,
                    onSuccess: function (result: any) {
                        console.log('Payment Success!', result);
                        alert('Payment Success!');
                        // Redirect to order completion page
                        window.location.href = `/ecommerce/order-completed?order_id=${orderId}`;
                    },
                    onPending: function (result: any) {
                        console.log('Payment Pending!', result);
                        alert('Payment Pending, please complete the payment');
                        // Redirect to order completion page to track pending order
                        window.location.href = `/ecommerce/order-completed?order_id=${orderId}`;
                    },
                    onError: function (result: any) {
                        console.log('Payment Error!', result);
                        alert('Payment Failed!');
                    },
                    onClose: function () {
                        console.log('Customer closed the popup');
                        alert('Payment cancelled by customer');
                    },
                });
                setLoading(false);
            } else {
                setError('Failed to load Midtrans Snap');
                setLoading(false);
            }
        };

        script.onerror = () => {
            setError('Failed to load Midtrans Snap script');
            setLoading(false);
        };

        document.head.appendChild(script);
    };

    const handleManualPayment = () => {
        if (snapToken) {
            window.snap
                ? (window as any).snap.show({
                      token: snapToken,
                      onSuccess: function (result: any) {
                          console.log('Payment Success!', result);
                          alert('Payment Success!');
                          window.location.href = `/ecommerce/order-completed?order_id=${orderId}`;
                      },
                      onPending: function (result: any) {
                          console.log('Payment Pending!', result);
                          alert('Payment Pending, please complete the payment');
                          window.location.href = `/ecommerce/order-completed?order_id=${orderId}`;
                      },
                      onError: function (result: any) {
                          console.log('Payment Error!', result);
                          alert('Payment Failed!');
                      },
                      onClose: function () {
                          console.log('Customer closed the popup');
                          alert('Payment cancelled by customer');
                      },
                  })
                : alert('Midtrans Snap is not loaded');
        }
    };

    if (loading) {
        return (
            <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50">
                <div className="mb-4 h-12 w-12 animate-spin rounded-full border-t-2 border-b-2 border-blue-500"></div>
                <p className="text-gray-700">Loading payment gateway...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 p-4">
                <div className="relative rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700" role="alert">
                    <strong className="font-bold">Error: </strong>
                    <span className="block sm:inline">{error}</span>
                </div>
                <button onClick={handleManualPayment} className="mt-4 rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700">
                    Retry Payment
                </button>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 p-4">
            <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-md">
                <h1 className="mb-6 text-center text-2xl font-bold text-gray-800">Payment Confirmation</h1>

                <div className="mb-4">
                    <p className="text-gray-700">
                        <span className="font-semibold">Order ID:</span> {orderId}
                    </p>
                    <p className="text-gray-700">
                        <span className="font-semibold">Amount:</span> IDR {grossAmount.toLocaleString()}
                    </p>
                    <p className="text-gray-700">
                        <span className="font-semibold">Status:</span> {order.status}
                    </p>
                </div>

                <div className="mt-6">
                    <p className="mb-4 text-center text-gray-600">Please complete your payment using the button below</p>
                    <button
                        onClick={handleManualPayment}
                        className="focus:shadow-outline w-full rounded bg-green-600 px-4 py-3 font-bold text-white hover:bg-green-700 focus:outline-none"
                    >
                        Pay Now
                    </button>
                </div>

                <div className="mt-6 text-center">
                    <p className="text-sm text-gray-500">Secure payment powered by Midtrans</p>
                </div>
            </div>
        </div>
    );
};

export default PayTest;
