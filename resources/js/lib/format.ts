import type { BorrowItemStatus, BorrowRequestStatus } from '@/types';

/*
 * 不用 Intl.DateTimeFormat：SSR（Node）與瀏覽器的 ICU 資料不同，
 * 產生的空白字元不一樣會造成 hydration mismatch。
 * 台灣沒有日光節約時間，直接以 UTC+8 換算即可。
 */
const TAIPEI_OFFSET_MS = 8 * 60 * 60 * 1000;

const pad = (n: number) => String(n).padStart(2, '0');

function toTaipei(date: Date): Date {
    return new Date(date.getTime() + TAIPEI_OFFSET_MS);
}

/** 日期欄位後端已序列化成 Y-m-d；保險起見只取前 10 碼。 */
export function formatDate(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '—';
}

/** 時間戳記（ISO，UTC）→ 台北時間 YYYY-MM-DD HH:mm */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const t = toTaipei(new Date(value));

    return `${t.getUTCFullYear()}-${pad(t.getUTCMonth() + 1)}-${pad(t.getUTCDate())} ${pad(t.getUTCHours())}:${pad(t.getUTCMinutes())}`;
}

/** 今天（台北時間）的 YYYY-MM-DD。 */
export function todayInTaipei(): string {
    return toTaipei(new Date()).toISOString().slice(0, 10);
}

export function addDays(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);

    return d.toISOString().slice(0, 10);
}

type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

export const requestStatusMeta: Record<
    BorrowRequestStatus,
    { label: string; tone: Tone }
> = {
    pending: { label: '申請中', tone: 'info' },
    expired: { label: '逾期未取', tone: 'neutral' },
    lent: { label: '借出中', tone: 'warning' },
    partially_returned: { label: '部分歸還', tone: 'warning' },
    overdue: { label: '逾期未還', tone: 'danger' },
    returned_late: { label: '逾期歸還', tone: 'warning' },
    returned: { label: '已歸還', tone: 'success' },
    cancelled: { label: '已退回', tone: 'neutral' },
};

export const itemStatusMeta: Record<
    BorrowItemStatus,
    { label: string; tone: Tone }
> = {
    pending: { label: '申請中', tone: 'info' },
    lent: { label: '已借出', tone: 'warning' },
    returned: { label: '已歸還', tone: 'success' },
    cancelled: { label: '已退回', tone: 'neutral' },
};

export const toneClasses: Record<Tone, string> = {
    neutral: 'border-transparent bg-muted text-muted-foreground',
    info: 'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    success:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    warning:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    danger: 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
};

export const roleLabels: Record<string, string> = {
    borrower: '借用人',
    staff: '值班人員',
    admin: '管理員',
};
