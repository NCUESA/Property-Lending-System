import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { reactive } from 'vue';

/**
 * 篩選條件同步到網址的 query string（GET），換條件時回到第一頁。
 * 文字搜尋有 debounce，其他欄位立即套用。
 */
export function useQueryFilters<T extends Record<string, unknown>>(
    url: string,
    initial: T,
) {
    const filters = reactive({ ...initial }) as T;

    const apply = () => {
        const query = Object.fromEntries(
            Object.entries(filters).filter(
                ([, v]) =>
                    v !== '' &&
                    v !== null &&
                    v !== undefined &&
                    !(Array.isArray(v) && v.length === 0),
            ),
        );

        router.get(url, query as Record<string, string>, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    watchDebounced(() => ({ ...filters }), apply, {
        debounce: 300,
        deep: true,
    });

    const reset = () => {
        for (const key of Object.keys(filters)) {
            (filters as Record<string, unknown>)[key] = Array.isArray(
                filters[key],
            )
                ? []
                : '';
        }
    };

    return { filters, reset };
}
