import PriceDisplay from '@/components/priceDisplay';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import HeaderLayout from '@/layouts/header-layout';
import { CartItem, PackageItem, type CartItemOrPackage } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CreditCard, Wallet } from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface PaymentProps {
    billing: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        address: string;
        city: string;
        state: string;
        zip: string;
        country: string;
        notes: string;
    };
    shipping: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        address: string;
        city: string;
        state: string;
        zip: string;
        country: string;
    };
}

export default function PaymentPage({ billing, shipping }: PaymentProps) {
    const [sourceOfFund] = useState('pinjaman');
    const [paymentMethod, setPaymentMethod] = useState('kredit-koperasi'); // 'kredit-koperasi' or 'payment-gateway'
    const [paymentType, setPaymentType] = useState('cad');
    const [isProcessing, setIsProcessing] = useState(false);
    const [cartItems, setCartItems] = useState<CartItem[]>([]);

    const storedData = localStorage.getItem('creditLimitData') || 'null';
    const parsedData = JSON.parse(storedData);
    const creditLimit = parsedData?.creditLimit ?? 0;

    // Helper function to process cart items - used in both useEffect and handleSubmit
    const processCartItems = useCallback((parsedCart: CartItemOrPackage[]): CartItem[] => {
        // Extract package products and merge quantities if needed
        const itemMap = new Map<string, CartItem>();

        parsedCart.forEach((item: CartItemOrPackage) => {
            if ('isPackage' in item && item.isPackage && 'packageContents' in item) {
                // Unwrap package items and merge with existing items
                (item as PackageItem).packageContents.forEach((content) => {
                    const existingItemKey = content.product_id.toString();
                    const existingItem = itemMap.get(existingItemKey);

                    if (existingItem) {
                        // Merge quantities if item already exists
                        itemMap.set(existingItemKey, {
                            ...existingItem,
                            quantity: existingItem.quantity + content.quantity,
                            total: existingItem.price * (existingItem.quantity + content.quantity) * (existingItem.content || 1),
                        });
                    } else {
                        // Add as new item
                        const newItem: CartItem = {
                            id: content.product_id,
                            name: content.name,
                            slug: content.name.toLowerCase().replace(/\s+/g, '-'),
                            quantity: content.quantity,
                            price: content.price,
                            image: content.image || '/products/Placeholder_Medicine.png',
                            order_unit: content.order_unit,
                            base_uom: content.base_uom,
                            content: content.content || 1,
                        };
                        itemMap.set(existingItemKey, newItem);
                    }
                });
            } else {
                // Handle regular items, merging if they already exist in the map
                const regularItem = item as CartItem;
                const itemId = regularItem.id.toString();

                const existingItem = itemMap.get(itemId);
                if (existingItem) {
                    // Merge quantities if item already exists
                    itemMap.set(itemId, {
                        ...existingItem,
                        quantity: existingItem.quantity + regularItem.quantity,
                        total: existingItem.price * (existingItem.quantity + regularItem.quantity) * (existingItem.content || 1),
                    });
                } else {
                    // Add as new item
                    itemMap.set(itemId, regularItem);
                }
            }
        });

        // Convert the map back to an array
        return Array.from(itemMap.values());
    }, []);

    useEffect(() => {
        const storedCart = localStorage.getItem('cart');
        if (!storedCart) {
            localStorage.setItem('cartmsg', 'Your cart is empty.');
            window.location.href = route('cart');
        } else {
            const parsedCart: CartItemOrPackage[] = JSON.parse(storedCart);

            const processedCart = processCartItems(parsedCart);
            setCartItems(processedCart);
        }
    }, [processCartItems]);

    const handleSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            setIsProcessing(true);

            const cartData = localStorage.getItem('cart') || '[]';
            const parsedCart: CartItemOrPackage[] = JSON.parse(cartData);

            const processedCart = processCartItems(parsedCart);

            // Determine the final payment type based on the selected payment method
            let finalPaymentType = paymentType;
            if (paymentMethod === 'payment-gateway') {
                finalPaymentType = 'va'; // Use virtual account for payment gateway
            }

            // Route to different endpoints based on payment method
            if (paymentMethod === 'payment-gateway') {
                // Call payment gateway endpoint
                router.post(
                    route('payment.gateway'),
                    {
                        source_of_fund: sourceOfFund,
                        cart: processedCart, // Inertia.js should handle this automatically
                    },
                    {
                        onSuccess: () => {
                            localStorage.removeItem('cart');
                        },
                        onError: (errors) => {
                            if (errors.credit_limit_error) {
                                toast.error('Saldo Kredit Anda Kurang!, Cek kembali Saldo Kredit yang Anda miliki!', {
                                    duration: 5000,
                                });
                            } else if (errors.mapping_error) {
                                toast.error('Koperasi belum dimapping dengan Apotek KF, Silakan hubungi administrator.', {
                                    duration: 5000,
                                });
                            } else if (errors.generic_payment_error) {
                                toast.error('A technical error occurred. Our team has been notified. Please try again later.', {
                                    duration: 5000,
                                });
                            } else {
                                toast.error('Payment Failed', {
                                    description: 'An unknown error occurred. Please check your details and try again.',
                                    duration: 10000,
                                });
                            }
                        },
                        onFinish: () => {
                            setIsProcessing(false);
                        },
                    },
                );
            } else {
                // Call regular payment endpoint for Kredit Koperasi
                router.post(
                    route('payment.process'),
                    {
                        source_of_fund: sourceOfFund,
                        payment_type: finalPaymentType,
                        cart: processedCart, // Inertia.js should handle this automatically
                    },
                    {
                        onSuccess: () => {
                            localStorage.removeItem('cart');
                        },
                        onError: (errors) => {
                            if (errors.credit_limit_error) {
                                toast.error('Saldo Kredit Anda Kurang!, Cek kembali Saldo Kredit yang Anda miliki!', {
                                    duration: 5000,
                                });
                            } else if (errors.mapping_error) {
                                toast.error('Koperasi belum dimapping dengan Apotek KF, Silakan hubungi administrator.', {
                                    duration: 5000,
                                });
                            } else if (errors.generic_payment_error) {
                                toast.error('A technical error occurred. Our team has been notified. Please try again later.', {
                                    duration: 5000,
                                });
                            } else {
                                toast.error('Payment Failed', {
                                    description: 'An unknown error occurred. Please check your details and try again.',
                                    duration: 10000,
                                });
                            }
                        },
                        onFinish: () => {
                            setIsProcessing(false);
                        },
                    },
                );
            }
        },
        [sourceOfFund, paymentMethod, paymentType, processCartItems],
    );

    // Use useMemo to calculate totals only when cartItems change
    const { subtotal, ppn, grandTotal } = useMemo(() => {
        const subtotal = cartItems.reduce((sum, item) => sum + item.price * item.quantity * item.content, 0);
        const ppn = subtotal * 0.11;
        const grandTotal = subtotal + ppn;
        return { subtotal, ppn, grandTotal };
    }, [cartItems]);

    const shipping_amount = 0;

    // Memoize the cart items for the order summary to prevent re-rendering
    const cartItemElements = useMemo(
        () =>
            cartItems.map((item) => (
                <div key={item.id} className="flex items-start justify-between py-2">
                    <div className="flex-1">
                        <p className="text-sm font-medium">{item.name}</p>
                        <p className="text-xs text-muted-foreground">
                            Qty: {item.quantity} {item.order_unit}
                        </p>
                    </div>
                    <p className="text-sm font-medium whitespace-nowrap">Rp{(item.price * item.quantity).toLocaleString()}</p>
                </div>
            )),
        [cartItems],
    );

    return (
        <HeaderLayout>
            <Head title="Payment" />
            <div className="container mx-auto px-4 py-8 pb-28 lg:pb-8">
                <h1 className="mb-8 text-2xl font-bold text-foreground">Payment</h1>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Payment Methods */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Payment Method</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form id="payment-form" onSubmit={handleSubmit} className="space-y-4">
                                    {/* Payment Gateway option */}
                                    <div
                                        className={`flex cursor-pointer items-center justify-between rounded-xl border-2 p-4 transition-all duration-200 ${
                                            paymentMethod === 'payment-gateway' ? 'border-primary bg-primary/10' : 'border-border'
                                        }`}
                                        onClick={() => setPaymentMethod('payment-gateway')}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-full bg-secondary p-3">
                                                <CreditCard className="h-6 w-6 text-secondary-foreground" />
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-card-foreground">Payment Gateway</h3>
                                                <p className="text-sm text-muted-foreground">Virtual Account</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        className={`flex cursor-pointer flex-col gap-3 rounded-xl border-2 p-4 transition-all duration-200 ${
                                            paymentMethod === 'kredit-koperasi' ? 'border-primary bg-primary/10' : 'border-border'
                                        }`}
                                        onClick={() => setPaymentMethod('kredit-koperasi')}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-full bg-secondary p-3">
                                                <Wallet className="h-6 w-6 text-secondary-foreground" />
                                            </div>
                                            <div>
                                                <h3 className="font-semibold text-card-foreground">Kredit Koperasi</h3>
                                                <p className="text-sm text-muted-foreground">Remaining Credits: Rp {creditLimit.toLocaleString()}</p>
                                            </div>
                                        </div>
                                        <div className="mt-2 pl-12">
                                            <Label className="mb-2 block font-medium">Payment Type</Label>
                                            <Select value={paymentType} onValueChange={setPaymentType}>
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Select payment type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="cad">Cash after Delivery</SelectItem>
                                                    <SelectItem value="top30" disabled>
                                                        Term of Payment 30 Days
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="hidden pt-4 lg:block">
                                        <button
                                            type="submit"
                                            disabled={isProcessing}
                                            className="w-full rounded-md bg-primary px-4 py-3 text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                        >
                                            {isProcessing ? 'Processing...' : 'Place Order'}
                                        </button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        {/* ... Billing & Shipping Info ... */}
                        <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="rounded-lg bg-card p-6 text-card-foreground shadow-sm">
                                <h3 className="text-md mb-3 font-semibold">Billing Address</h3>
                                <div className="space-y-1 text-sm text-muted-foreground">
                                    <p className="text-card-foreground">
                                        {billing.first_name} {billing.last_name}
                                    </p>
                                    <p>{billing.email}</p>
                                    <p>{billing.phone}</p>
                                    <p className="mt-2">{billing.address}</p>
                                    <p>
                                        {billing.city}, {billing.state} {billing.zip}
                                    </p>
                                    <p>{billing.country}</p>
                                    {billing.notes && <p className="mt-2 italic">Notes: {billing.notes}</p>}
                                </div>
                            </div>
                            <div className="rounded-lg bg-card p-6 text-card-foreground shadow-sm">
                                <h3 className="text-md mb-3 font-semibold">Shipping Address</h3>
                                <div className="space-y-1 text-sm text-muted-foreground">
                                    <p className="text-card-foreground">
                                        {shipping.first_name} {shipping.last_name}
                                    </p>
                                    <p>{shipping.email}</p>
                                    <p>{shipping.phone}</p>
                                    <p className="mt-2">{shipping.address}</p>
                                    <p>
                                        {shipping.city}, {shipping.state} {shipping.zip}
                                    </p>
                                    <p>{shipping.country}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ... Order Summary ... */}
                    <div className="lg:col-span-1">
                        <div className="rounded-lg bg-card p-6 text-card-foreground shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold">Order Summary</h2>
                            <div className="space-y-4">
                                <div className="max-h-60 overflow-y-auto pr-2">{cartItemElements}</div>
                                <div className="border-t border-border pt-4">
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Subtotal</span>
                                            <PriceDisplay price={subtotal} />
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Shipping</span>
                                            <span className="font-medium text-green-600 dark:text-green-400">
                                                {shipping_amount === 0 ? 'Free' : <PriceDisplay price={shipping_amount} />}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Tax (11%)</span>
                                            <PriceDisplay price={ppn} />
                                        </div>
                                        <div className="mt-2 flex justify-between border-t border-border pt-2">
                                            <span className="text-lg font-semibold text-primary">Total</span>{' '}
                                            <PriceDisplay price={grandTotal} className="text-lg font-semibold text-primary" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Mobile Sticky Footer - Vertical Layout */}
            <div className="fixed right-0 bottom-0 left-0 block border-t bg-card px-4 pt-3 pb-4 shadow-[0_-4px_10px_-1px_rgba(0,0,0,0.05)] lg:hidden">
                <div className="mx-auto flex max-w-screen-xl flex-col items-center gap-3">
                    {/* Total Information */}
                    <div className="text-center">
                        <span className="text-xs text-muted-foreground">Total Payment</span>
                        {/* Added 'block' class to ensure PriceDisplay takes its own line */}
                        <PriceDisplay price={grandTotal} className="block text-xl font-bold text-primary" />
                    </div>

                    {/* Checkout Button */}
                    <div className="w-full max-w-sm">
                        <button
                            type="submit"
                            form="payment-form"
                            disabled={isProcessing}
                            className="w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {isProcessing ? 'Processing...' : 'Place Order'}
                        </button>
                    </div>
                </div>
            </div>
        </HeaderLayout>
    );
}
