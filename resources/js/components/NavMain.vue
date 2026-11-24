<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

export type NavGroup = {
    label: string;
    icon: LucideIcon;
    items: NavItem[];
};

/**
 * 可收折的選單群組（類似 FortiGate 的側邊欄）：
 * 點群組展開／收合底下的頁面；目前所在頁面的群組一定展開。
 * 收合狀態記在瀏覽器（localStorage），重新整理後維持。
 */
const props = defineProps<{ groups: NavGroup[] }>();

const STORAGE_KEY = 'sidebar:open-groups';

const { isCurrentOrParentUrl } = useCurrentUrl();
const { state, setOpen, isMobile } = useSidebar();

const visibleGroups = computed(() =>
    props.groups.filter((g) => g.items.length),
);

const activeLabel = computed(
    () =>
        visibleGroups.value.find((g) =>
            g.items.some((i) => isCurrentOrParentUrl(i.href)),
        )?.label,
);

// 伺服器端渲染時只展開目前所在的群組，掛載後再套用瀏覽器記住的狀態，避免 hydration 不一致
const open = ref<Record<string, boolean>>(
    activeLabel.value ? { [activeLabel.value]: true } : {},
);

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');

        if (saved && typeof saved === 'object') {
            open.value = { ...saved, ...open.value };
        }
    } catch {
        // 讀不到就用預設值
    }
});

// 換頁到別的群組時自動展開
watch(activeLabel, (label) => {
    if (label) {
        open.value = { ...open.value, [label]: true };
    }
});

function setGroup(label: string, value: boolean) {
    open.value = { ...open.value, [label]: value };

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(open.value));
    } catch {
        // 無痕模式等情況存不了，不影響使用
    }
}

/** 側邊欄縮成圖示時看不到子選單，點群組就先把側邊欄打開 */
function onGroupClick(label: string) {
    if (state.value === 'collapsed' && !isMobile.value) {
        setOpen(true);
        setGroup(label, true);
    }
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <Collapsible
                v-for="group in visibleGroups"
                :key="group.label"
                as-child
                :open="open[group.label] ?? false"
                class="group/collapsible"
                @update:open="setGroup(group.label, $event)"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :tooltip="group.label"
                            :is-active="
                                state === 'collapsed' &&
                                activeLabel === group.label
                            "
                            class="font-medium"
                            @click="onGroupClick(group.label)"
                        >
                            <component :is="group.icon" />
                            <span>{{ group.label }}</span>
                            <ChevronRight
                                class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="item in group.items"
                                :key="item.title"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentOrParentUrl(item.href)"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
