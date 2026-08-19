<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Bot, Plus, Search, Send, User } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { renderMarkdown } from '@/lib/markdown';
import { chat } from '@/routes';
import { store } from '@/routes/chat/messages';
import type { ChatMessage, Conversation, LocalChatMessage } from '@/types';

interface StreamEvent {
    type: string;
    invocation_id?: string;
    delta?: string;
    message?: string;
    name?: string;
}

const props = defineProps<{
    conversations: Conversation[];
    activeConversationId: string | null;
    messages: ChatMessage[];
}>();

const page = usePage();
const userId = page.props.auth.user.id;

const form = useForm({
    message: '',
    conversation_id: null as string | null,
});

const messages = ref<LocalChatMessage[]>([
    ...props.messages.map((message) => ({
        ...message,
        status: 'complete' as const,
    })),
]);
const activeConversationId = ref<string | null>(props.activeConversationId);
const streaming = ref(false);
const toolRunning = ref(false);
const streamError = ref<string | null>(null);

const messagesContainer = ref<HTMLElement | null>(null);

if (typeof window !== 'undefined') {
    useEcho<StreamEvent>(
        `user.${userId}`,
        [
            '.stream_start',
            '.text_start',
            '.text_delta',
            '.text_end',
            '.tool_call',
            '.tool_result',
            '.stream_end',
            '.stream_failed',
            '.error',
        ],
        (payload) => {
            switch (payload.type) {
                case 'text_delta':
                    appendDelta(payload.delta ?? '');
                    break;
                case 'tool_call':
                    toolRunning.value = true;
                    break;
                case 'tool_result':
                    toolRunning.value = false;
                    break;
                case 'stream_end':
                    finalizeStream();
                    break;
                case 'stream_failed':
                case 'error':
                    failStream(payload.message ?? 'La réponse a échoué.');
                    break;
            }
        },
    );
}

watch(
    () => [messages.value, streaming.value, toolRunning.value],
    () => scrollToBottom(),
    { deep: true },
);

function appendDelta(delta: string): void {
    const last = messages.value[messages.value.length - 1];

    if (last && last.role === 'assistant' && last.status === 'streaming') {
        last.content += delta;
    } else {
        messages.value.push({
            id: crypto.randomUUID(),
            role: 'assistant',
            content: delta,
            status: 'streaming',
            created_at: new Date().toISOString(),
        });
    }
}

function finalizeStream(): void {
    const last = messages.value[messages.value.length - 1];

    if (last && last.role === 'assistant' && last.status === 'streaming') {
        last.status = 'complete';
    }

    streaming.value = false;
    toolRunning.value = false;

    router.reload({ only: ['conversations'] });
}

function failStream(message: string): void {
    const last = messages.value[messages.value.length - 1];

    if (last && last.role === 'assistant' && last.status === 'streaming') {
        last.status = 'error';
    }

    streaming.value = false;
    toolRunning.value = false;
    streamError.value = message;
}

function send(): void {
    if (streaming.value || !form.message.trim()) {
        return;
    }

    const conversationId = activeConversationId.value ?? crypto.randomUUID();
    const text = form.message.trim();

    activeConversationId.value = conversationId;
    form.conversation_id = conversationId;
    streamError.value = null;

    messages.value.push({
        id: crypto.randomUUID(),
        role: 'user',
        content: text,
        status: 'complete',
        created_at: new Date().toISOString(),
    });
    messages.value.push({
        id: crypto.randomUUID(),
        role: 'assistant',
        content: '',
        status: 'streaming',
        created_at: new Date().toISOString(),
    });

    streaming.value = true;

    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.message = '';
        },
        onError: () => {
            messages.value.splice(-2);
            streaming.value = false;
        },
    });
}

function openConversation(id: string): void {
    if (streaming.value || id === activeConversationId.value) {
        return;
    }

    router.get(
        chat({ query: { conversation: id } }).url,
        {},
        { preserveState: false },
    );
}

function newConversation(): void {
    if (streaming.value) {
        return;
    }

    activeConversationId.value = null;
    form.conversation_id = null;
    messages.value = [];
    streamError.value = null;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send();
    }
}

async function scrollToBottom(): Promise<void> {
    await nextTick();

    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
}

function formatRelative(date: string): string {
    const formatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });
    const diff = Date.now() - new Date(date).getTime();
    const minutes = Math.round(diff / 60000);
    const hours = Math.round(diff / 3600000);
    const days = Math.round(diff / 86400000);

    if (minutes < 1) {
        return "à l'instant";
    }

    if (minutes < 60) {
        return formatter.format(-minutes, 'minute');
    }

    if (hours < 24) {
        return formatter.format(-hours, 'hour');
    }

    if (days < 30) {
        return formatter.format(-days, 'day');
    }

    return new Date(date).toLocaleDateString('fr');
}
</script>

<template>
    <Head title="Chat" />

    <div
        class="flex h-dvh overflow-hidden bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100"
    >
        <aside
            class="flex w-72 shrink-0 flex-col border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div
                class="flex items-center gap-2 border-b border-zinc-200 px-4 py-4 dark:border-zinc-800"
            >
                <AppLogoIcon className="size-6 shrink-0" />
                <span class="text-lg font-semibold">Marxtinus</span>
            </div>

            <div class="p-3">
                <Button
                    variant="outline"
                    class="w-full justify-start gap-2"
                    :disabled="streaming"
                    @click="newConversation"
                >
                    <Plus class="size-4" />
                    Nouvelle conversation
                </Button>
            </div>

            <nav class="flex-1 overflow-y-auto px-2 pb-3">
                <ul class="space-y-1">
                    <li
                        v-for="conversation in props.conversations"
                        :key="conversation.id"
                    >
                        <button
                            type="button"
                            class="w-full rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-zinc-800"
                            :class="
                                conversation.id === activeConversationId
                                    ? 'bg-zinc-100 dark:bg-zinc-800'
                                    : ''
                            "
                            :disabled="streaming"
                            @click="openConversation(conversation.id)"
                        >
                            <span class="block truncate font-medium">
                                {{ conversation.title }}
                            </span>
                            <span
                                class="block text-xs text-zinc-500 dark:text-zinc-400"
                            >
                                {{ formatRelative(conversation.updated_at) }}
                            </span>
                        </button>
                    </li>
                </ul>

                <p
                    v-if="props.conversations.length === 0"
                    class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400"
                >
                    Aucune conversation pour le moment.
                </p>
            </nav>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col">
            <header
                class="flex items-center justify-between border-b border-zinc-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <h1 class="truncate text-sm font-semibold">
                    {{
                        activeConversationId
                            ? (props.conversations.find(
                                  (conversation) =>
                                      conversation.id === activeConversationId,
                              )?.title ?? 'Conversation')
                            : 'Nouvelle conversation'
                    }}
                </h1>
            </header>

            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto px-4 py-6"
            >
                <div class="mx-auto w-full max-w-3xl space-y-4">
                    <p
                        v-if="messages.length === 0"
                        class="py-16 text-center text-zinc-500 dark:text-zinc-400"
                    >
                        Posez votre première question à Marxtinus.
                    </p>

                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="flex items-start gap-3"
                        :class="
                            message.role === 'user' ? 'flex-row-reverse' : ''
                        "
                    >
                        <Avatar class="size-8">
                            <AvatarFallback
                                :class="
                                    message.role === 'user'
                                        ? 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'
                                        : 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                "
                            >
                                <User
                                    v-if="message.role === 'user'"
                                    class="size-4"
                                />
                                <Bot v-else class="size-4" />
                            </AvatarFallback>
                        </Avatar>

                        <div
                            class="max-w-[75%] rounded-2xl px-4 py-2 text-sm leading-relaxed"
                            :class="[
                                message.role === 'user'
                                    ? 'bg-zinc-900 whitespace-pre-wrap text-white dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'border border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100',
                                message.status === 'error'
                                    ? 'border-red-300 dark:border-red-800'
                                    : '',
                            ]"
                        >
                            <template v-if="message.role === 'user'">
                                {{ message.content }}
                            </template>
                            <template v-else>
                                <div
                                    v-if="toolRunning"
                                    class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400"
                                >
                                    <Search class="size-3.5 animate-pulse" />
                                    Recherche web en cours…
                                </div>
                                <div
                                    v-if="message.content"
                                    v-html="renderMarkdown(message.content)"
                                    class="prose prose-sm max-w-none prose-zinc dark:prose-invert"
                                />
                                <span
                                    v-if="
                                        message.status === 'streaming' &&
                                        !message.content &&
                                        !toolRunning
                                    "
                                    class="inline-flex items-center gap-1"
                                >
                                    <span
                                        class="size-1.5 animate-bounce rounded-full bg-current"
                                    />
                                    <span
                                        class="size-1.5 animate-bounce rounded-full bg-current [animation-delay:120ms]"
                                    />
                                    <span
                                        class="size-1.5 animate-bounce rounded-full bg-current [animation-delay:240ms]"
                                    />
                                </span>
                            </template>
                        </div>
                    </div>

                    <p
                        v-if="streamError"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ streamError }}
                    </p>
                </div>
            </div>

            <div
                class="border-t border-zinc-200 bg-white px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="mx-auto flex w-full max-w-3xl items-end gap-2">
                    <textarea
                        v-model="form.message"
                        rows="1"
                        class="flex-1 resize-none rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm transition-colors outline-none placeholder:text-zinc-400 focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:placeholder:text-zinc-500 dark:focus:border-zinc-400"
                        placeholder="Écris un message…"
                        @keydown="handleKeydown"
                    />
                    <Button
                        size="icon"
                        :disabled="streaming || !form.message.trim()"
                        aria-label="Envoyer"
                        @click="send"
                    >
                        <Send class="size-4" />
                    </Button>
                </div>

                <p
                    v-if="form.errors.message"
                    class="mx-auto mt-2 max-w-3xl text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.message }}
                </p>
            </div>
        </main>
    </div>
</template>
