export type Role = 'borrower' | 'staff' | 'admin';

// 對應 HandleInertiaRequests 分享的 auth.user（只挑前端需要的欄位）
export type User = {
    id: number;
    name: string;
    student_id: string | null;
    email: string | null;
    role: Role;
    avatar?: string;
    email_verified_at?: string | null;
    [key: string]: unknown;
};

export type Auth = {
    // 未登入時為 null；後台頁面都在 auth middleware 之下，可視為非 null
    user: User;
    can: {
        staff: boolean;
        admin: boolean;
    };
};

export type Flash = {
    success: string | null;
    error: string | null;
};
