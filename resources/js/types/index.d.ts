import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export type NavSection = {
    title: string; // 👈 Label section (Pembelian / Penjualan)
    items: NavItem[];
    isActive?: boolean;
};

export type ProductPivot = {
    id: number;
    sku: string;
    name: string;
    price: number;
    image?: string;
    pivot?: { quantity: number };
};

export type BuyerAddress = {
    recipient_name: string;
    address_line1: string;
    address_line2?: string;
    city: string;
    province: string;
    postal_code: string;
    country: string;
    phone?: string;
};

export interface Apotek {
    id: string;
    branch: string;
    sap_id: string;
    name: string;
    address: string;
    phone: string;
    latitude: number;
    longitude: number;
    zipcode: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface SharedData {
    name: string;
    user: User;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    ssoBaseUrl: string;
    digikopUrl: string;
    [key: string]: unknown;
}

export interface Role {
    id: number;
    name: string;
    permissions?: Permission[];
}

export interface Permission {
    id: number;
    name: string;
}

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    permissions?: Permission[];
    apotek?: Apotek;
    phone?: string;
    status: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface PackageItem {
    id: string;
    name: string;
    slug: string;
    price: number;
    image?: string;
    order_unit: string;
    content: number;
    base_uom: string;
    weight: number;
    isPackage: true;
    packageContents: Array<{
        product_id: number;
        name: string;
        quantity: number;
        price: number;
        image?: string;
        order_unit: string;
        content: number;
        base_uom: string;
        weight: number;
    }>;
}

export interface CartItem extends Product {
    quantity: number;
    total: number;
}

export type CartItemOrPackage = CartItem | PackageItem;

export interface Product {
    id: number;
    sku: string;
    name: string;
    slug: string;
    category_id?: number;
    price: number;
    weight: number;
    length?: number;
    width?: number;
    height?: number;
    base_uom: string;
    order_unit: string;
    content: number;
    brand?: string;
    image?: Record<string>;
    description?: string;
    usage_direction: string;
    pharmacology?: string;
    contraindication?: string;
    storage?: string;
    image_alt?: string;
    is_active?: boolean;
    is_featured?: boolean;
    category?: {
        main_category: string;
        subcategory1: string;
        subcategory2: string;
    };
}

// ✅ Order sinkron dengan migration + seeder
export interface Order {
    id: number;
    transaction_number: string;
    user_id: number;
    tenant_id: string;

    // Status
    status: string; // contoh: "On Delivery"

    // Payment
    source_of_fund: string; // pinjaman, pribadi
    account_no: string;
    account_bank: string;
    payment_type: string; // cad
    payment_method: string; // bri, bni, mandiri, bsi, btn, bca
    va_number: string;

    // Pricing
    subtotal: number;
    subtotal_delivered: number;
    tax_amount: number;
    tax_delivered
    shipping_amount: number;
    discount_amount: number;
    total_price: number;
    total_delivered: number;

    // Billing
    billing_name: string;
    billing_email: string;
    billing_phone: string | null;
    billing_address: string;
    billing_city: string;
    billing_state: string;
    billing_zip: string;

    // Shipping
    shipping_name: string | null;
    shipping_address: string | null;
    shipping_city: string | null;
    shipping_state: string | null;
    shipping_zip: string | null;

    // Shipping details
    shipping_method: string;
    tracking_number: string | null;
    estimated_delivery: string | null;
    shipped_at: string | null;
    delivered_at: string | null;

    // Notes
    customer_notes?: string | null;
    admin_notes?: string | null;

    // Relasi
    order_items?: OrderItem[];

    products?: (Product & { pivot?: { quantity: number; qty_delivered: number } })[];
    product_detail: {
        sku: string;
        quantity: number;
    }[]; //Relasi ke Product

    created_at: string;
    updated_at: string;

    user: User;
    snap_token?: string;
}

export interface OrderItem {
    id: number;
    product_name: string;
    quantity: number;
    qty_delivered: number;
    unit_price: number;
    total_price: number;
    content: number;
    product: Product;
    order_unit: string;
    image?: Record<string>;
}

// ✅ OrderPayload untuk request create order
export interface OrderPayload {
    transaction_number: string;
    user_id: number;
    tenant_id: string;
    status: string;
    source_of_fund: string;
    account_no: string;
    account_bank: string;
    payment_type: string;
    payment_method: string;
    va_number: string;
    subtotal: number;
    tax_amount: number;
    shipping_amount: number;
    discount_amount: number;
    total_price: number;
    billing_name: string;
    billing_email: string;
    billing_phone?: string;
    billing_address: string;
    billing_city: string;
    billing_state: string;
    billing_zip: string;
    shipping_name?: string;
    shipping_address?: string;
    shipping_city?: string;
    shipping_state?: string;
    shipping_zip?: string;
    shipping_method: string;
    tracking_number?: string;
    estimated_delivery?: string;
    shipped_at?: string;
    delivered_at?: string;
    customer_notes?: string;
    admin_notes?: string;
    order_items?: OrderItem[];
}

export interface FiltersProps {
    categories: string[];
    packages: string[];
    orderUnits: string[];
    onFilterChange: (filters: { categories: string[]; packages: string[]; orderUnits: string[] }) => void;
}

export interface Paginated<T> {
    data: T[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
    };
}
export class Category {
    id: number;
    main_category: string;
    subcategory1: string;
    subcategory2: string;
}
