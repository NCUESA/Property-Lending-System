// 對應後端傳給頁面的資料（Controller 的 Inertia props）

export type BorrowRequestStatus =
    | 'pending'
    | 'expired'
    | 'lent'
    | 'extended'
    | 'partially_returned'
    | 'overdue'
    | 'returned_late'
    | 'returned'
    | 'cancelled';

export type BorrowItemStatus = 'pending' | 'lent' | 'cancelled' | 'returned';

export type Option<T = string> = { value: T; label: string };

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type LocationRef = {
    id: number;
    name: string;
    code?: string;
    ssid_prefix?: string;
};

export type Subcategory = {
    id: number;
    property_category_id: number;
    code: string;
    name: string;
    properties_count?: number;
};

export type Category = {
    id: number;
    code: string;
    name: string;
    subcategories: Subcategory[];
};

export type PoolRef = {
    id: number;
    name: string;
    location_id: number;
    location?: LocationRef;
    auth_level?: number;
};

export type Property = {
    id: number;
    ssid: string;
    property_subcategory_id: number;
    sequence_no: number | null;
    name: string;
    second_name: string | null;
    spec: string | null;
    price: number | null;
    department: string | null;
    custodian: string | null;
    location_id: number;
    property_pool_id: number | null;
    acquired_on: string | null;
    is_school_owned: boolean;
    auth_level: number;
    image_path: string | null;
    image_url?: string | null;
    is_lendable: boolean;
    remark: string | null;
    location?: LocationRef;
    pool?: PoolRef | null;
    subcategory?: Subcategory & { category: Omit<Category, 'subcategories'> };
    active_borrow_item?: {
        id: number;
        status: BorrowItemStatus;
        borrow_request_id: number;
    } | null;
};

export type UserRef = { id: number; name: string; student_id?: string | null };

export type BorrowItem = {
    id: number;
    borrow_request_id: number;
    property_id: number | null;
    property_pool_id: number | null;
    quantity: number;
    returned_quantity: number;
    status: BorrowItemStatus;
    returned_at: string | null;
    property?: {
        id: number;
        ssid: string;
        name: string;
        second_name: string | null;
        location?: LocationRef;
    } | null;
    pool?: { id: number; name: string } | null;
    returner?: UserRef | null;
};

export type BorrowOverview = {
    id: number;
    location_id: number;
    user_id: number | null;
    borrower_department: string;
    borrower_name: string;
    phone: string;
    email: string;
    borrow_date: string;
    due_date: string;
    lent_at: string | null;
    id_card_taken: boolean;
    deposit_taken: boolean;
    deposit_amount: number | null;
    deposit_box_no: number | null;
    returned_at: string | null;
    id_card_returned: boolean;
    deposit_returned: boolean;
    remark: string | null;
    extension_reason: string | null;
    extended_at: string | null;
    extended_by: number | null;
    due_reminder_sent_at: string | null;
    legacy_lender_name: string | null;
    legacy_returner_name: string | null;
    created_at: string;
    status: BorrowRequestStatus;
    item_count: number;
    pending_count: number;
    lent_count: number;
    returned_count: number;
    cancelled_count: number;
    location?: LocationRef;
    items?: BorrowItem[];
    extender?: UserRef | null;
    lender?: UserRef | null;
    returner?: UserRef | null;
    borrower?: UserRef | null;
};
